<?php

namespace Tests\Feature\Web;

use App\Enums\MembershipStatus;
use App\Enums\PropertyUnitStatus;
use App\Enums\SystemRole;
use App\Enums\TipoDocumento;
use App\Models\Building;
use App\Models\BuildingFloor;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Core\CoreTestCase;

/**
 * La UI web de unidades y personas sobre el dominio nuevo (spec S38).
 *
 * Las rutas `/units` y `/people` se sirven con `property_units` y `people`:
 * el conjunto activo se resuelve en el servidor, un recurso de otro conjunto
 * no se expone (404) y el documento de identidad se muestra enmascarado
 * salvo el permiso explicito `person.view_document` (SEG-P01).
 *
 * Las afirmaciones son sobre la interfaz publica: codigo de estado y props
 * de Inertia, nunca sobre el estado interno.
 */
class UnitsPeoplePagesTest extends CoreTestCase
{
    // ==================== Units ====================

    public function test_the_units_index_lists_the_units_of_the_active_complex_with_the_new_shape(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $building = $this->makeBuilding($complex, 'Torre A');
        $floor = $this->makeFloor($building, 3);

        PropertyUnit::factory()->create([
            'residential_complex_id' => $complex->id,
            'building_id' => $building->id,
            'building_floor_id' => $floor->id,
            'number' => 'A-301',
            'unit_type' => 'apartment',
            'status' => PropertyUnitStatus::ACTIVE,
        ]);

        $response = $this->actingAs($admin)->get('/units');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Units/Index')
            ->has('units.data', 1)
            ->where('units.data.0.number', 'A-301')
            ->where('units.data.0.unit_type', 'apartment')
            ->where('units.data.0.status', 'active')
            ->where('units.data.0.building.name', 'Torre A')
            ->where('units.data.0.building_floor.floor_number', 3)
            // El shape legacy (numero/torre/piso/tipo) desaparece.
            ->missing('units.data.0.numero')
            ->missing('units.data.0.torre')
            ->missing('units.data.0.piso')
            ->missing('units.data.0.tipo')
            ->missing('units.data.0.personas')
            // La UI decide la baja por permiso, no por el cliente.
            ->where('canDelete', true)
        );
    }

    public function test_the_units_index_does_not_expose_the_units_of_another_complex(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$adminA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $this->makeUnit($complexA, 'A-101');
        $this->makeUnit($complexB, 'B-202');

        $response = $this->actingAs($adminA)->get('/units');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('units.data', 1)
            ->where('units.data.0.number', 'A-101')
        );
    }

    public function test_a_unit_of_another_complex_is_not_found(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$adminA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $unitB = $this->makeUnit($complexB, 'B-202');

        $this->actingAs($adminA)
            ->get("/units/{$unitB->uuid}")
            ->assertNotFound();
    }

    public function test_the_units_index_only_shows_the_units_within_the_scope_of_the_actor(): void
    {
        // Spec §6 "Alcance": el propietario solo ve las unidades que le
        // alcanzan por titularidad vigente.
        $complex = $this->makeComplex('Torres');
        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        $mine = $this->makeUnit($complex, 'A-101');
        $this->makeUnit($complex, 'A-202');

        $person = $this->makePerson($complex, $owner, 'Titular');
        $this->giveOwnership($complex, $mine, $person);

        $response = $this->actingAs($owner)->get('/units');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('units.data', 1)
            ->where('units.data.0.number', 'A-101')
        );
    }

    public function test_a_role_without_unit_permissions_cannot_list_the_units(): void
    {
        $complex = $this->makeComplex('Torres');
        // Desde la matriz de §1.3 (S69) todos los roles del catalogo de
        // sistema tienen permiso de unidades: el actor sin permisos es un
        // miembro con un rol propio sin permisos sembrados.
        [$user, $role] = $this->makeMemberWithoutUnitPermissions($complex);

        // El fixture tiene que ser genuinamente sin permisos: si una siembra
        // futura le concediera alguno, el test debe fallar de forma clara.
        $this->assertSame(0, $role->permissions()->count());

        $this->actingAs($user)
            ->get('/units')
            ->assertForbidden();
    }

    public function test_a_unit_is_created_in_the_active_complex(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)->post('/units', [
            'number' => 'A-404',
            'unit_type' => 'apartment',
            'status' => 'active',
        ]);

        $response->assertRedirect('/units');

        $this->assertDatabaseHas('property_units', [
            'residential_complex_id' => $complex->id,
            'number' => 'A-404',
            'unit_type' => 'apartment',
            'status' => 'active',
        ]);
    }

    public function test_an_invalid_unit_payload_is_rejected_without_persisting(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)->post('/units', [
            'unit_type' => 'apartment',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('property_units', 0);
    }

    public function test_a_unit_is_deactivated_and_not_deleted(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complex, 'A-505');

        $response = $this->actingAs($admin)->delete("/units/{$unit->uuid}");

        $response->assertRedirect('/units');

        $this->assertDatabaseHas('property_units', [
            'uuid' => $unit->uuid,
            'status' => PropertyUnitStatus::INACTIVE->value,
        ]);
    }

    public function test_the_unit_detail_shows_the_typed_relations(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complex, 'A-606');
        $owner = $this->makePerson($complex, null, 'Ana Torres');
        $this->giveOwnership($complex, $unit, $owner);

        $response = $this->actingAs($admin)->get("/units/{$unit->uuid}");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Units/Show')
            ->where('unit.number', 'A-606')
            ->has('unit.ownerships', 1)
            ->where('unit.ownerships.0.person_name', 'Ana Torres')
            ->where('unit.ownerships.0.ownership_type', 'owner')
            ->has('unit.occupancies', 0)
            ->has('unit.leases', 0)
        );
    }

    public function test_the_unit_creation_page_offers_the_buildings_of_the_complex(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeBuilding($complex, 'Torre A');

        $response = $this->actingAs($admin)->get('/units/create');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Units/Create')
            ->has('unitTypes', 5)
            ->has('statuses', 3)
            ->has('buildings', 1)
            ->where('buildings.0.name', 'Torre A')
        );
    }

    public function test_a_unit_is_updated(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complex, 'A-707');

        $response = $this->actingAs($admin)->put("/units/{$unit->uuid}", [
            'number' => 'A-708',
            'unit_type' => 'apartment',
            'status' => 'under_review',
        ]);

        $response->assertRedirect('/units');

        $this->assertDatabaseHas('property_units', [
            'uuid' => $unit->uuid,
            'number' => 'A-708',
            'status' => 'under_review',
        ]);
    }

    // ==================== People ====================

    public function test_the_people_index_masks_the_document_without_the_explicit_permission(): void
    {
        $complex = $this->makeComplex('Torres');
        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_type' => TipoDocumento::CC,
            'document_number' => '12345678',
        ]);

        $response = $this->actingAs($owner)->get('/people');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('People/Index')
            ->has('people.data', 1)
            ->where('people.data.0.full_name', 'Ana Torres')
            ->where('people.data.0.document_number', '****5678')
            ->where('people.data.0.status', 'active')
        );

        $this->assertStringNotContainsString('12345678', $response->getContent());
    }

    public function test_the_people_index_shows_the_document_with_the_explicit_permission(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_type' => TipoDocumento::CC,
            'document_number' => '12345678',
        ]);

        $response = $this->actingAs($admin)->get('/people');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('People/Index')
            ->has('people.data', 1)
            ->where('people.data.0.document_number', '12345678')
            ->where('canViewDocument', true)
            ->where('canDelete', true)
        );
    }

    public function test_the_people_index_lists_the_units_reached_by_each_person(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complex, 'A-707');
        $person = $this->makePerson($complex, null, 'Ana Torres');
        $this->giveOwnership($complex, $unit, $person);

        $response = $this->actingAs($admin)->get('/people');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('People/Index')
            ->has('people.data', 1)
            ->has('people.data.0.units', 1)
            ->where('people.data.0.units.0.number', 'A-707')
            ->where('people.data.0.units.0.relation', 'owner')
        );
    }

    public function test_a_person_of_another_complex_is_not_found(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$adminA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $personB = $this->makePerson($complexB, null, 'Luis Paz');

        $this->actingAs($adminA)
            ->get("/people/{$personB->uuid}")
            ->assertNotFound();
    }

    public function test_the_people_index_does_not_expose_the_people_of_another_complex(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$adminA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $this->makePerson($complexA, null, 'Ana Torres');
        $this->makePerson($complexB, null, 'Luis Paz');

        $response = $this->actingAs($adminA)->get('/people');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('people.data', 1)
            ->where('people.data.0.full_name', 'Ana Torres')
        );
    }

    public function test_the_people_index_only_exposes_the_minimal_projection_for_the_doorman(): void
    {
        // Spec §6 "Privacidad": la porteria no accede a los datos personales.
        $complex = $this->makeComplex('Torres');
        [$doorman] = $this->makeMember($complex, SystemRole::DOORMAN);

        Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_number' => '12345678',
            'email' => 'ana@example.com',
            'phone' => '3001234567',
        ]);

        $response = $this->actingAs($doorman)->get('/people');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('People/Index')
            ->has('people.data', 1)
            ->where('people.data.0.full_name', 'Ana Torres')
            ->where('people.data.0.status', 'active')
            ->missing('people.data.0.document_number')
            ->missing('people.data.0.email')
            ->missing('people.data.0.phone')
        );
    }

    public function test_the_person_detail_masks_the_document_without_the_explicit_permission(): void
    {
        $complex = $this->makeComplex('Torres');
        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        $person = Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_type' => TipoDocumento::CC,
            'document_number' => '12345678',
        ]);

        $response = $this->actingAs($owner)->get("/people/{$person->uuid}");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('People/Show')
            ->where('person.full_name', 'Ana Torres')
            ->where('person.document_number', '****5678')
            ->has('person.ownerships', 0)
            ->has('person.occupancies', 0)
            ->has('person.leases', 0)
        );
    }

    public function test_a_person_is_deactivated_and_not_deleted(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $person = $this->makePerson($complex, null, 'Ana Torres');

        $response = $this->actingAs($admin)->delete("/people/{$person->uuid}");

        $response->assertRedirect('/people');

        $this->assertDatabaseHas('people', [
            'uuid' => $person->uuid,
            'status' => 'inactive',
        ]);
    }

    public function test_a_person_is_created_in_the_active_complex(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)->post('/people', [
            'full_name' => 'Ana Torres',
            'document_type' => 'CC',
            'document_number' => '12345678',
            'phone' => '3001234567',
            'email' => 'ana@example.com',
            'status' => 'active',
        ]);

        $response->assertRedirect('/people');

        $this->assertDatabaseHas('people', [
            'residential_complex_id' => $complex->id,
            'full_name' => 'Ana Torres',
            'document_number' => '12345678',
        ]);
    }

    public function test_a_person_with_an_existing_document_reuses_the_existing_record(): void
    {
        // RN-P02: antes de crear se busca por documento en el conjunto.
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $existing = Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_type' => TipoDocumento::CC,
            'document_number' => '12345678',
        ]);

        $response = $this->actingAs($admin)->post('/people', [
            'full_name' => 'Ana Torres',
            'document_type' => 'CC',
            'document_number' => '12345678',
        ]);

        $response->assertRedirect("/people/{$existing->uuid}");

        $this->assertDatabaseCount('people', 1);
    }

    public function test_an_invalid_person_payload_is_rejected_without_persisting(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)->post('/people', [
            'email' => 'no-es-un-correo',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('people', 0);
    }

    // ==================== Helpers ====================

    /**
     * Miembro activo con un rol propio sin permisos sembrados.
     *
     * @return array{0: User, 1: Role}
     */
    private function makeMemberWithoutUnitPermissions(ResidentialComplex $complex): array
    {
        $user = User::factory()->create();

        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::factory()->complexLevel($complex)->create();

        $membership->roles()->attach($role->id);

        return [$user, $role];
    }

    private function makeBuilding(ResidentialComplex $complex, string $name): Building
    {
        return Building::factory()->create([
            'residential_complex_id' => $complex->id,
            'name' => $name,
            'code' => strtoupper(substr(md5($name), 0, 3)),
        ]);
    }

    private function makeFloor(Building $building, int $number): BuildingFloor
    {
        return BuildingFloor::factory()->create([
            'building_id' => $building->id,
            'floor_number' => $number,
        ]);
    }
}
