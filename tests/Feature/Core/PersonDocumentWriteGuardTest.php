<?php

namespace Tests\Feature\Core;

use App\Enums\ComplexPermission;
use App\Enums\MembershipStatus;
use App\Models\Person;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;

/**
 * Guarda de escritura del documento de identidad (spec S35, S38, SEG-P01).
 *
 * El permiso `person.view_document` es el permiso explicito para el
 * documento de identidad: sin el, el documento se muestra enmascarado. La
 * incoherencia que se cierra aqui es que la UI no lo envia pero un POST
 * directo si lo hacia: un actor sin el permiso podia ESCRIBIR un documento
 * que despues no podia leer en claro.
 *
 * Con la guarda:
 * - un actor sin `person.view_document` que escribe un documento recibe 403
 *   y no persiste nada (ni crea la persona ni modifica la existente);
 * - un actor con el permiso puede escribirlo y leerlo en claro.
 *
 * Las afirmaciones son sobre la interfaz publica: codigo de estado, cuerpo de
 * la respuesta y, cuando la peticion se rechaza, que los datos y los
 * contadores quedan sin cambios.
 */
class PersonDocumentWriteGuardTest extends CoreTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Superficie web
    |--------------------------------------------------------------------------
    */

    public function test_an_actor_without_the_document_permission_cannot_create_a_person_with_an_identity_document(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_CREATE,
        ]);

        $before = Person::withoutGlobalScope('complex')->count();

        $this->actingAs($actor)
            ->post('/people', [
                'full_name' => 'Ana Torres',
                'document_type' => 'CC',
                'document_number' => '12345678',
            ])
            ->assertForbidden();

        $this->assertSame(
            $before,
            Person::withoutGlobalScope('complex')->count(),
            'El rechazo no debe crear ninguna persona.'
        );

        $this->assertDatabaseMissing('people', ['document_number' => '12345678']);
    }

    public function test_an_actor_without_the_document_permission_can_create_a_person_without_an_identity_document(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_CREATE,
        ]);

        $this->actingAs($actor)
            ->post('/people', [
                'full_name' => 'Ana Torres',
                'phone' => '3001234567',
            ])
            ->assertRedirect('/people');

        $person = Person::withoutGlobalScope('complex')->where('full_name', 'Ana Torres')->first();

        $this->assertNotNull($person, 'La persona debe crearse.');
        $this->assertNull($person->document_number, 'Sin permiso no se escribe documento de identidad.');
    }

    public function test_an_actor_without_the_document_permission_cannot_write_an_identity_document_on_update(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_EDIT,
        ]);

        $person = $this->makePerson($complex, null, 'Ana Torres');

        $documentBefore = $person->document_number;
        $documentTypeBefore = $person->document_type?->value;
        $phoneBefore = $person->phone;

        $this->actingAs($actor)
            ->put("/people/{$person->uuid}", [
                'full_name' => 'Ana Torres',
                'phone' => '3009999999',
                'document_type' => 'CE',
                'document_number' => '12345678',
            ])
            ->assertForbidden();

        $fresh = $person->fresh();

        $this->assertSame($documentBefore, $fresh->document_number, 'El documento no debe escribirse.');
        $this->assertSame($documentTypeBefore, $fresh->document_type?->value, 'El tipo de documento no debe escribirse.');
        $this->assertSame($phoneBefore, $fresh->phone, 'El rechazo no debe aplicar el resto de los cambios.');
    }

    public function test_an_actor_without_the_document_permission_cannot_clear_the_identity_document(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_EDIT,
        ]);

        $person = Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_type' => 'CC',
            'document_number' => '12345678',
        ]);

        $this->actingAs($actor)
            ->put("/people/{$person->uuid}", [
                'full_name' => 'Ana Torres',
                'document_number' => null,
            ])
            ->assertRedirect('/people');

        $this->assertSame(
            '12345678',
            $person->fresh()->document_number,
            'Sin el permiso el documento existente no se modifica.'
        );
    }

    public function test_an_actor_with_the_document_permission_can_write_and_read_the_identity_document_in_clear(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_EDIT,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $person = $this->makePerson($complex, null, 'Ana Torres');

        $this->actingAs($actor)
            ->put("/people/{$person->uuid}", [
                'full_name' => 'Ana Torres',
                'document_type' => 'CC',
                'document_number' => '12345678',
            ])
            ->assertRedirect('/people');

        $this->assertSame('12345678', $person->fresh()->document_number);

        $this->actingAs($actor)
            ->get('/people')
            ->assertOk()
            ->assertSee('12345678', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Superficie API
    |--------------------------------------------------------------------------
    */

    public function test_an_actor_without_the_document_permission_cannot_create_a_person_with_an_identity_document_through_the_api(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_CREATE,
        ]);

        $before = Person::withoutGlobalScope('complex')->count();

        $this->actingAs($actor)
            ->postJson('/api/v1/people', [
                'full_name' => 'Ana Torres',
                'document_type' => 'CC',
                'document_number' => '12345678',
            ])
            ->assertForbidden();

        $this->assertSame(
            $before,
            Person::withoutGlobalScope('complex')->count(),
            'El rechazo no debe crear ninguna persona.'
        );
    }

    public function test_an_actor_without_the_document_permission_cannot_write_an_identity_document_through_the_api(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_EDIT,
        ]);

        $person = $this->makePerson($complex, null, 'Ana Torres');

        $documentBefore = $person->document_number;
        $phoneBefore = $person->phone;

        $this->actingAs($actor)
            ->putJson("/api/v1/people/{$person->uuid}", [
                'phone' => '3009999999',
                'document_type' => 'CE',
                'document_number' => '12345678',
            ])
            ->assertForbidden();

        $fresh = $person->fresh();

        $this->assertSame($documentBefore, $fresh->document_number, 'El documento no debe escribirse.');
        $this->assertSame($phoneBefore, $fresh->phone, 'El rechazo no debe aplicar el resto de los cambios.');
    }

    public function test_an_actor_with_the_document_permission_can_write_the_identity_document_through_the_api(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_EDIT,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $person = $this->makePerson($complex, null, 'Ana Torres');

        $this->actingAs($actor)
            ->putJson("/api/v1/people/{$person->uuid}", [
                'document_type' => 'CC',
                'document_number' => '12345678',
            ])
            ->assertOk();

        $this->assertSame('12345678', $person->fresh()->document_number);

        $this->actingAs($actor)
            ->getJson("/api/v1/people/{$person->uuid}")
            ->assertOk()
            ->assertJsonPath('data.document_number', '12345678');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Miembro activo con un rol propio del conjunto que tiene exactamente los
     * permisos indicados (los roles del catalogo de sistema no cubren el
     * caso "puede gestionar personas pero no ver el documento").
     *
     * @param  list<ComplexPermission>  $permissions
     * @return array{0: User, 1: ResidentialComplexMembership}
     */
    protected function makeActor(ResidentialComplex $complex, array $permissions): array
    {
        $user = User::factory()->create();

        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::create([
            'residential_complex_id' => $complex->id,
            'key' => 'prueba-'.uniqid(),
            'name' => 'Rol de prueba '.uniqid(),
            'is_system' => false,
            'is_editable' => true,
        ]);

        foreach ($permissions as $permission) {
            $role->grantPermission($permission);
        }

        $membership->roles()->attach($role->id);

        return [$user, $membership];
    }
}
