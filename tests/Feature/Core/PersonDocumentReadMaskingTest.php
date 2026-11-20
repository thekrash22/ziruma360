<?php

namespace Tests\Feature\Core;

use App\Enums\ComplexPermission;
use App\Enums\MembershipStatus;
use App\Enums\TipoDocumento;
use App\Models\Person;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * Enmascarado de la lectura del documento de identidad (spec S35, SEG-P01).
 *
 * El documento de identidad es el PAR (`document_type`, `document_number`):
 * escribir cualquiera de los dos exige `person.view_document` (lo fija
 * `PersonDocumentWriteGuardTest`). La lectura tiene que ser coherente con
 * esa escritura: si el numero va enmascarado, el tipo tampoco se expone,
 * porque si no la lectura revelaria mas dato del que la escritura protege.
 *
 * Con la politica:
 * - sin `person.view_document`, el numero se enmascara y el tipo se oculta;
 * - con `person.view_document`, ambos salen en claro;
 * - una persona sin documento no inventa documento: tipo y numero siguen
 *   siendo nulos para cualquiera de los dos actores.
 *
 * Las afirmaciones son sobre la interfaz publica: el cuerpo de la respuesta
 * de la API y el payload Inertia de la web.
 */
class PersonDocumentReadMaskingTest extends CoreTestCase
{
    public function test_the_api_masks_the_document_type_when_the_actor_lacks_the_document_permission(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [ComplexPermission::PERSON_VIEW]);
        $person = $this->makePersonWithDocument($complex);

        $response = $this->actingAs($actor)
            ->getJson("/api/v1/people/{$person->uuid}")
            ->assertOk();

        $this->assertNull(
            $response->json('data.document_type'),
            'Sin el permiso el tipo de documento no se expone.'
        );
        $this->assertSame(
            '****5678',
            $response->json('data.document_number'),
            'Sin el permiso el numero va enmascarado.'
        );
    }

    public function test_the_api_shows_the_document_type_in_clear_with_the_document_permission(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);
        $person = $this->makePersonWithDocument($complex);

        $response = $this->actingAs($actor)
            ->getJson("/api/v1/people/{$person->uuid}")
            ->assertOk();

        $this->assertSame('CC', $response->json('data.document_type'));
        $this->assertSame('12345678', $response->json('data.document_number'));
    }

    public function test_the_web_detail_hides_the_document_type_without_the_document_permission(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [ComplexPermission::PERSON_VIEW]);
        $person = $this->makePersonWithDocument($complex);

        $this->actingAs($actor)
            ->get("/people/{$person->uuid}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('People/Show')
                ->where('person.document_type', null)
                ->where('person.document_type_label', null)
                ->where('person.document_number', '****5678')
            );
    }

    public function test_the_web_detail_shows_the_document_type_with_the_document_permission(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);
        $person = $this->makePersonWithDocument($complex);

        $this->actingAs($actor)
            ->get("/people/{$person->uuid}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('People/Show')
                ->where('person.document_type', 'CC')
                ->where('person.document_number', '12345678')
            );
    }

    public function test_the_web_list_hides_the_document_type_without_the_document_permission(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [ComplexPermission::PERSON_VIEW]);
        $person = $this->makePersonWithDocument($complex);

        $this->actingAs($actor)
            ->get('/people')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('People/Index')
                ->where('people.data.0.document_type', null)
                ->where('people.data.0.document_type_label', null)
                ->where('people.data.0.document_number', '****5678')
            );

        $this->assertNotNull($person->document_number);
    }

    public function test_a_person_without_document_has_no_document_for_either_actor(): void
    {
        $complex = $this->makeComplex('Torres');
        [$viewer] = $this->makeActor($complex, [ComplexPermission::PERSON_VIEW]);
        [$privileged] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);
        $person = Person::factory()->forComplex($complex)->create([
            'full_name' => 'Sin Documento',
            'document_type' => null,
            'document_number' => null,
        ]);

        $this->actingAs($privileged)
            ->getJson("/api/v1/people/{$person->uuid}")
            ->assertOk()
            ->assertJsonPath('data.document_type', null)
            ->assertJsonPath('data.document_number', null);

        $this->actingAs($viewer)
            ->getJson("/api/v1/people/{$person->uuid}")
            ->assertOk()
            ->assertJsonPath('data.document_type', null)
            ->assertJsonPath('data.document_number', null);
    }

    /**
     * Persona del conjunto con documento de identidad completo.
     */
    protected function makePersonWithDocument(ResidentialComplex $complex): Person
    {
        return Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_type' => TipoDocumento::CC,
            'document_number' => '12345678',
        ]);
    }

    /**
     * Miembro activo con un rol propio del conjunto que tiene exactamente los
     * permisos indicados.
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
