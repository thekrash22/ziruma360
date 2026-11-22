<?php

namespace Tests\Feature\Web;

use App\Enums\ComplexModule;
use App\Enums\SystemRole;
use App\Models\Building;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use App\Models\User;
use App\Services\ComplexContext;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Core\CoreTestCase;

/**
 * La UI web del tenant root (spec S94): el conjunto tiene nombre propio en
 * la web (`/complexes`), el actor elige de forma explicita su conjunto
 * activo y el header y el dashboard muestran el conjunto activo.
 *
 * Las afirmaciones son sobre la interfaz publica: codigo de estado y props
 * de Inertia, nunca sobre el estado interno.
 */
class ComplexesPagesTest extends CoreTestCase
{
    // ==================== Listado y selector ====================

    public function test_the_complexes_index_lists_the_complexes_of_the_actor_and_marks_the_active_one(): void
    {
        $one = $this->makeComplex('Conjunto A');
        $two = $this->makeComplex('Conjunto B');

        [$user] = $this->makeMember($one, SystemRole::COMPLEX_ADMIN);
        $this->makeMember($two, SystemRole::COMPLEX_ADMIN, $user);

        $this->actingAs($user)
            ->post("/complexes/{$one->uuid}/select")
            ->assertRedirect();

        $response = $this->actingAs($user)->get('/complexes');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Complexes/Index')
            ->has('complexes', 2)
            ->where('complexes.0.uuid', $one->uuid)
            ->where('complexes.0.is_current', true)
            ->where('complexes.1.uuid', $two->uuid)
            ->where('complexes.1.is_current', false)
            ->where('activeComplexUuid', $one->uuid)
            // Con mas de un conjunto la pantalla ofrece el selector.
            ->where('canSelect', true)
        );
    }

    public function test_the_complexes_index_does_not_list_the_complexes_of_another_actor(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$admin] = $this->makeMember($mine, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)->get('/complexes');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('complexes', 1)
            ->where('complexes.0.uuid', $mine->uuid)
        );
    }

    public function test_the_complexes_index_explains_that_the_actor_has_no_complex_and_offers_the_creation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/complexes');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Complexes/Index')
            ->has('complexes', 0)
            ->where('canCreate', false)
            ->where('activeComplexUuid', null)
        );
    }

    // ==================== Aislamiento ====================

    public function test_a_complex_of_another_actor_is_not_found(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$admin] = $this->makeMember($mine, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->get("/complexes/{$other->uuid}")
            ->assertNotFound();
    }

    // ==================== Alta ====================

    public function test_the_creation_form_is_forbidden_without_the_create_permission(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->get('/complexes/create')
            ->assertForbidden();

        $this->actingAs($admin)
            ->post('/complexes', [
                'name' => 'Conjunto Nuevo',
                'code' => 'conjunto-nuevo-'.uniqid(),
                'address' => 'Calle 1 # 2-3',
                'city' => 'Bogota',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('residential_complexes', ['name' => 'Conjunto Nuevo']);
    }

    public function test_the_platform_admin_can_create_a_complex(): void
    {
        $complex = $this->makeComplex('Torres');
        [$platform] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->actingAs($platform)
            ->get('/complexes/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Complexes/Create')
            );

        $response = $this->actingAs($platform)->post('/complexes', [
            'name' => 'Conjunto Nuevo',
            'code' => 'conjunto-nuevo-'.uniqid(),
            'address' => 'Calle 1 # 2-3',
            'city' => 'Bogota',
        ]);

        $response->assertRedirect();

        // El conjunto nace siempre en borrador.
        $this->assertDatabaseHas('residential_complexes', [
            'name' => 'Conjunto Nuevo',
            'status' => 'draft',
        ]);
    }

    // ==================== Detalle ====================

    public function test_the_complex_detail_shows_the_identity_the_modules_the_roles_and_the_members(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $complex->enableModule(ComplexModule::ACCESS_CONTROL);

        $response = $this->actingAs($admin)->get("/complexes/{$complex->uuid}");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Complexes/Show')
            ->where('complex.name', 'Torres')
            ->where('complex.status', 'draft')
            ->where('complex.status_label', 'Borrador')
            ->where('complex.uuid', $complex->uuid)
            // Modulos habilitados (los dos nucleos mas el de acceso).
            ->has('modules', 3)
            ->where('modules.0.key', 'core.structure')
            ->where('modules.0.label', 'Estructura física')
            // Roles del catalogo.
            ->has('roles', 8)
            ->where('roles.0.key', 'platform_admin')
            // Miembros del conjunto.
            ->has('members', 1)
            ->where('members.0.email', $admin->email)
            ->where('members.0.status', 'active')
            ->where('members.0.roles.0', 'Administrador del conjunto')
        );
    }

    // ==================== Seleccion ====================

    public function test_selecting_a_complex_fixes_the_active_context(): void
    {
        $one = $this->makeComplex('Conjunto A');
        $two = $this->makeComplex('Conjunto B');

        [$user] = $this->makeMember($one, SystemRole::COMPLEX_ADMIN);
        $this->makeMember($two, SystemRole::COMPLEX_ADMIN, $user);

        $this->assertNull(ComplexContext::getCurrentComplexId());

        $response = $this->actingAs($user)
            ->from('/complexes')
            ->post("/complexes/{$two->uuid}/select");

        $response->assertRedirect('/complexes');

        $this->assertSame($two->id, ComplexContext::getCurrentComplexId());
    }

    public function test_selecting_a_complex_of_another_actor_is_not_found(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$user] = $this->makeMember($mine, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->post("/complexes/{$other->uuid}/select")
            ->assertNotFound();

        // El conjunto ajeno no se elige nunca como activo.
        $this->assertNotSame($other->id, ComplexContext::getCurrentComplexId());
    }

    public function test_with_two_complexes_the_actor_is_taken_to_the_selection_screen(): void
    {
        $one = $this->makeComplex('Conjunto A');
        $two = $this->makeComplex('Conjunto B');

        [$user] = $this->makeMember($one, SystemRole::COMPLEX_ADMIN);
        $this->makeMember($two, SystemRole::COMPLEX_ADMIN, $user);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/complexes');

        // La pantalla de seleccion no se redirige a si misma: no hay bucle.
        $this->actingAs($user)
            ->get('/complexes')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Complexes/Index')
                ->has('complexes', 2)
                ->where('selectionRequired', true)
            );
    }

    public function test_the_actor_returns_to_the_page_he_was_trying_to_reach_after_selecting(): void
    {
        $one = $this->makeComplex('Conjunto A');
        $two = $this->makeComplex('Conjunto B');

        [$user] = $this->makeMember($one, SystemRole::COMPLEX_ADMIN);
        $this->makeMember($two, SystemRole::COMPLEX_ADMIN, $user);

        // El actor entra al dashboard, lo mandan a elegir conjunto...
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/complexes');

        // ...y al elegir vuelve a donde estaba: no es un callejon sin salida.
        $this->actingAs($user)
            ->post("/complexes/{$two->uuid}/select")
            ->assertRedirect('/dashboard');

        $this->assertSame($two->id, ComplexContext::getCurrentComplexId());
    }

    public function test_after_choosing_from_the_selection_screen_the_actor_returns_to_the_page_he_was_trying_to_reach(): void
    {
        // El flujo real: el actor pide el dashboard, lo mandan a elegir
        // conjunto y elige **desde la pantalla de seleccion** (el referer
        // del POST es esa pantalla). Al terminar tiene que volver al
        // dashboard, no quedarse en la pantalla de seleccion.
        $one = $this->makeComplex('Conjunto A');
        $two = $this->makeComplex('Conjunto B');

        [$user] = $this->makeMember($one, SystemRole::COMPLEX_ADMIN);
        $this->makeMember($two, SystemRole::COMPLEX_ADMIN, $user);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/complexes');

        $this->actingAs($user)
            ->withHeaders(['referer' => url('/complexes')])
            ->post("/complexes/{$two->uuid}/select")
            ->assertRedirect('/dashboard');

        $this->assertSame($two->id, ComplexContext::getCurrentComplexId());
    }

    public function test_the_explicit_destination_of_the_selector_wins(): void
    {
        // El selector de la cabecera manda el destino explicito en el cuerpo
        // del POST: ese destino es el que gana.
        $one = $this->makeComplex('Conjunto A');
        $two = $this->makeComplex('Conjunto B');

        [$user] = $this->makeMember($one, SystemRole::COMPLEX_ADMIN);
        $this->makeMember($two, SystemRole::COMPLEX_ADMIN, $user);

        $this->actingAs($user)
            ->post("/complexes/{$two->uuid}/select", ['redirect' => '/units'])
            ->assertRedirect('/units');

        $this->assertSame($two->id, ComplexContext::getCurrentComplexId());
    }

    // ==================== Header y dashboard ====================

    public function test_the_header_shows_the_active_complex(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('complex.uuid', $complex->uuid)
            ->where('complex.name', 'Torres')
            ->where('complex.is_current', true)
            ->has('complexes', 1)
            ->where('complexes.0.uuid', $complex->uuid)
        );
    }

    public function test_the_header_offers_the_change_when_the_actor_has_more_than_one_complex(): void
    {
        $one = $this->makeComplex('Conjunto A');
        $two = $this->makeComplex('Conjunto B');

        [$user] = $this->makeMember($one, SystemRole::COMPLEX_ADMIN);
        $this->makeMember($two, SystemRole::COMPLEX_ADMIN, $user);

        $this->actingAs($user)
            ->post("/complexes/{$one->uuid}/select")
            ->assertRedirect();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('complex.uuid', $one->uuid)
            ->has('complexes', 2)
        );
    }

    public function test_the_dashboard_shows_the_real_counts_of_the_active_complex(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeBuilding($complex, 'Torre A');
        $this->makeBuilding($complex, 'Torre B');

        PropertyUnit::factory()->create([
            'residential_complex_id' => $complex->id,
            'number' => 'A-101',
        ]);

        Person::factory()->forComplex($complex)->create(['full_name' => 'Ana Torres']);
        Person::factory()->forComplex($complex)->create(['full_name' => 'Luis Paz']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('complex.uuid', $complex->uuid)
            ->where('stats.buildings', 2)
            ->where('stats.units', 1)
            ->where('stats.people', 2)
            ->where('stats.members', 1)
        );
    }

    public function test_the_dashboard_explains_that_a_complex_must_be_chosen_when_there_is_none(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('needsComplex', true)
            ->where('complex', null)
        );

        // Ninguna cifra inventada: el dashboard no muestra numeros de ejemplo.
        $this->assertStringNotContainsString('Total Units', $response->getContent());
        $this->assertStringNotContainsString('John Doe', $response->getContent());
    }

    // ==================== Helpers ====================

    private function makeBuilding(ResidentialComplex $complex, string $name): Building
    {
        return Building::factory()->create([
            'residential_complex_id' => $complex->id,
            'name' => $name,
            'code' => strtoupper(substr(md5($name), 0, 3)),
        ]);
    }
}
