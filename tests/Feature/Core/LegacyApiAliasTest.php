<?php

namespace Tests\Feature\Core;

use App\Enums\SystemRole;
use App\Models\Building;
use App\Models\BuildingFloor;
use App\Models\Person;
use App\Models\PropertyUnit;

/**
 * Alias legacy de la API (spec S39, work unit T17 / unidad A).
 *
 * `/api/v1/units` y `/api/v1/people` se re-apuntan al dominio nuevo
 * (`property_units`, `people`) con el mismo middleware que `/api/v1/core/*`:
 * sesion, `auth` y resolucion del conjunto activo en el servidor.
 *
 * Lo que se afirma aqui es el efecto observable por la interfaz publica:
 * codigo de estado, cuerpo de la respuesta y, cuando la peticion se
 * rechaza, que los datos y los contadores quedan sin cambios.
 */
class LegacyApiAliasTest extends CoreTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Invitado sin sesion
    |--------------------------------------------------------------------------
    */

    public function test_a_guest_does_not_obtain_units(): void
    {
        $this->getJson('/api/v1/units')->assertUnauthorized();
    }

    public function test_a_guest_does_not_obtain_people(): void
    {
        $this->getJson('/api/v1/people')->assertUnauthorized();
    }

    public function test_a_guest_cannot_create_units_or_people(): void
    {
        $this->postJson('/api/v1/units', [
            'number' => 'X-1',
            'unit_type' => 'apartment',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/people', ['full_name' => 'Intruso'])->assertUnauthorized();

        $this->assertSame(0, PropertyUnit::withoutGlobalScope('complex')->count());
        $this->assertSame(0, Person::withoutGlobalScope('complex')->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Listado: solo el conjunto activo
    |--------------------------------------------------------------------------
    */

    public function test_a_member_only_sees_the_units_of_the_active_complex(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $unitA = $this->makeUnit($complexA);
        $unitB = $this->makeUnit($complexB);

        $response = $this->actingAs($userA)->getJson('/api/v1/units')->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertTrue($uuids->contains($unitA->uuid), 'La unidad del conjunto activo debe aparecer en el listado.');
        $this->assertFalse($uuids->contains($unitB->uuid), 'La unidad de otro conjunto no debe aparecer en el listado.');
    }

    public function test_a_member_only_sees_the_people_of_the_active_complex(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $personA = $this->makePerson($complexA, null, 'Ana Ruiz');
        $personB = $this->makePerson($complexB, null, 'Beto Gomez');

        $response = $this->actingAs($userA)->getJson('/api/v1/people')->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertTrue($uuids->contains($personA->uuid), 'La persona del conjunto activo debe aparecer en el listado.');
        $this->assertFalse($uuids->contains($personB->uuid), 'La persona de otro conjunto no debe aparecer en el listado.');
    }

    /*
    |--------------------------------------------------------------------------
    | Alta de unidades
    |--------------------------------------------------------------------------
    */

    public function test_a_member_creates_a_unit_in_the_active_complex(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($userA)->postJson('/api/v1/units', [
            'number' => 'A-101',
            'unit_type' => 'apartment',
        ])->assertCreated();

        $this->assertSame('A-101', $response->json('data.number'));
        $this->assertSame('apartment', $response->json('data.unit_type'));

        $unit = PropertyUnit::withoutGlobalScope('complex')->where('number', 'A-101')->first();

        $this->assertNotNull($unit, 'La unidad creada debe existir en la base.');
        $this->assertSame($complexA->id, (int) $unit->residential_complex_id);
    }

    public function test_the_complex_of_the_created_unit_is_the_active_one_and_not_the_one_the_client_sends(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($userA)->postJson('/api/v1/units', [
            'number' => 'A-505',
            'unit_type' => 'apartment',
            'residential_complex_id' => $complexB->id,
        ])->assertCreated();

        $unit = PropertyUnit::withoutGlobalScope('complex')->where('number', 'A-505')->first();

        $this->assertNotNull($unit);
        $this->assertSame($complexA->id, (int) $unit->residential_complex_id);
    }

    public function test_an_invalid_unit_payload_is_rejected_and_persists_nothing(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $before = PropertyUnit::withoutGlobalScope('complex')->count();

        $this->actingAs($userA)
            ->postJson('/api/v1/units', ['number' => '', 'unit_type' => 'no-existe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['number', 'unit_type']);

        $this->assertSame($before, PropertyUnit::withoutGlobalScope('complex')->count());
    }

    public function test_a_unit_is_created_with_its_building_and_floor(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $building = Building::factory()->create([
            'residential_complex_id' => $complexA->id,
            'name' => 'Torre A',
        ]);

        $floor = BuildingFloor::factory()->create([
            'building_id' => $building->id,
            'floor_number' => 3,
            'name' => 'Piso 3',
        ]);

        $response = $this->actingAs($userA)->postJson('/api/v1/units', [
            'number' => 'A-303',
            'unit_type' => 'apartment',
            'building_id' => $building->id,
            'building_floor_id' => $floor->id,
        ])->assertCreated();

        $this->assertSame($building->uuid, $response->json('data.building.uuid'));
        $this->assertSame('Torre A', $response->json('data.building.name'));
        $this->assertSame($floor->uuid, $response->json('data.building_floor.uuid'));
        $this->assertSame('Piso 3', $response->json('data.building_floor.name'));
    }

    public function test_a_floor_of_another_building_is_rejected(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $building = Building::factory()->create(['residential_complex_id' => $complexA->id]);
        $otherBuilding = Building::factory()->create(['residential_complex_id' => $complexA->id]);

        $floor = BuildingFloor::factory()->create(['building_id' => $otherBuilding->id]);

        $before = PropertyUnit::withoutGlobalScope('complex')->count();

        $this->actingAs($userA)
            ->postJson('/api/v1/units', [
                'number' => 'A-404',
                'unit_type' => 'apartment',
                'building_id' => $building->id,
                'building_floor_id' => $floor->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['building_floor_id']);

        $this->assertSame($before, PropertyUnit::withoutGlobalScope('complex')->count());
    }

    public function test_a_unit_of_another_complex_is_not_found(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $unitB = $this->makeUnit($complexB);

        $this->actingAs($userA)
            ->getJson("/api/v1/units/{$unitB->uuid}")
            ->assertNotFound();
    }

    public function test_a_member_reads_and_updates_a_unit_of_the_active_complex(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complexA);

        $this->actingAs($userA)
            ->getJson("/api/v1/units/{$unit->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $unit->uuid);

        $this->actingAs($userA)
            ->putJson("/api/v1/units/{$unit->uuid}", ['internal_code' => 'INT-01'])
            ->assertOk()
            ->assertJsonPath('data.internal_code', 'INT-01');

        $this->assertSame('INT-01', $unit->fresh()->internal_code);
    }

    public function test_deleting_a_unit_deactivates_it_instead_of_deleting_it(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complexA);

        $this->actingAs($userA)
            ->deleteJson("/api/v1/units/{$unit->uuid}")
            ->assertOk();

        $row = PropertyUnit::withoutGlobalScope('complex')->find($unit->id);

        $this->assertNotNull($row, 'La unidad desactivada no debe borrarse de la base.');
        $this->assertSame('inactive', $row->status->value);
    }

    /*
    |--------------------------------------------------------------------------
    | Alta de personas
    |--------------------------------------------------------------------------
    */

    public function test_a_member_creates_a_person_in_the_active_complex(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($userA)->postJson('/api/v1/people', [
            'full_name' => 'Carla Nunez',
            'document_type' => 'CC',
            'document_number' => 'CC-778899',
        ])->assertCreated();

        $this->assertSame('Carla Nunez', $response->json('data.full_name'));

        $person = Person::withoutGlobalScope('complex')->where('full_name', 'Carla Nunez')->first();

        $this->assertNotNull($person, 'La persona creada debe existir en la base.');
        $this->assertSame($complexA->id, (int) $person->residential_complex_id);
    }

    public function test_an_invalid_person_payload_is_rejected_and_persists_nothing(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $before = Person::withoutGlobalScope('complex')->count();

        $this->actingAs($userA)
            ->postJson('/api/v1/people', ['full_name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['full_name']);

        $this->assertSame($before, Person::withoutGlobalScope('complex')->count());
    }

    public function test_a_person_of_another_complex_is_not_found(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $personB = $this->makePerson($complexB);

        $this->actingAs($userA)
            ->getJson("/api/v1/people/{$personB->uuid}")
            ->assertNotFound();
    }

    public function test_a_member_reads_and_updates_a_person_of_the_active_complex(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $person = $this->makePerson($complexA, null, 'Ana Ruiz');

        $this->actingAs($userA)
            ->getJson("/api/v1/people/{$person->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $person->uuid);

        $this->actingAs($userA)
            ->putJson("/api/v1/people/{$person->uuid}", ['phone' => '3001234567'])
            ->assertOk()
            ->assertJsonPath('data.phone', '3001234567');

        $this->assertSame('3001234567', $person->fresh()->phone);
    }

    public function test_deleting_a_person_deactivates_it_instead_of_deleting_it(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $person = $this->makePerson($complexA);

        $this->actingAs($userA)
            ->deleteJson("/api/v1/people/{$person->uuid}")
            ->assertOk();

        $row = Person::withoutGlobalScope('complex')->find($person->id);

        $this->assertNotNull($row, 'La persona desactivada no debe borrarse de la base.');
        $this->assertSame('inactive', $row->status->value);
    }

    /*
    |--------------------------------------------------------------------------
    | Autorizacion
    |--------------------------------------------------------------------------
    */

    public function test_an_actor_without_the_create_permission_cannot_create_a_unit(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        // El propietario tiene UNIT_VIEW y UNIT_MANAGE, pero no UNIT_CREATE.
        [$userA] = $this->makeMember($complexA, SystemRole::OWNER);

        $before = PropertyUnit::withoutGlobalScope('complex')->count();

        $this->actingAs($userA)
            ->postJson('/api/v1/units', [
                'number' => 'A-999',
                'unit_type' => 'apartment',
            ])
            ->assertForbidden();

        $this->assertSame($before, PropertyUnit::withoutGlobalScope('complex')->count());
    }

    public function test_an_actor_without_the_create_permission_cannot_create_a_person(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        // El propietario tiene PERSON_VIEW, pero no PERSON_CREATE.
        [$userA] = $this->makeMember($complexA, SystemRole::OWNER);

        $before = Person::withoutGlobalScope('complex')->count();

        $this->actingAs($userA)
            ->postJson('/api/v1/people', ['full_name' => 'Intruso'])
            ->assertForbidden();

        $this->assertSame($before, Person::withoutGlobalScope('complex')->count());
    }
}
