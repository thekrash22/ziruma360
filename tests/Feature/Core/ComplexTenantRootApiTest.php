<?php

namespace Tests\Feature\Core;

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\ResidentialComplexMembership;
use App\Models\User;
use App\Services\ComplexContext;
use Illuminate\Support\Facades\Route;

/**
 * Capa del tenant root: la superficie de conjuntos y la seleccion del
 * conjunto activo (specs S90-S93).
 *
 * El conjunto tiene nombre propio: la API vive en `/api/v1/complexes` y el
 * path viejo `/api/v1/buildings` deja de existir (sin alias). El actor ve
 * solo sus conjuntos y elige de forma explicita cual es el activo.
 */
class ComplexTenantRootApiTest extends CoreTestCase
{
    /*
    |--------------------------------------------------------------------------
    | S90: la superficie tiene nombre propio
    |--------------------------------------------------------------------------
    */

    public function test_the_complex_api_lives_under_its_own_path_and_route_names(): void
    {
        $this->assertSame(url('/api/v1/complexes'), route('api.complexes.index'));

        foreach (['index', 'store', 'show', 'update', 'destroy', 'select'] as $action) {
            $this->assertNotNull(
                Route::getRoutes()->getByName('api.complexes.'.$action),
                "Falta la ruta api.complexes.{$action}."
            );
        }

        foreach (['index', 'store', 'show', 'update', 'destroy'] as $action) {
            $this->assertNull(
                Route::getRoutes()->getByName('api.buildings.'.$action),
                "La ruta vieja api.buildings.{$action} sigue existiendo."
            );
        }
    }

    public function test_the_old_buildings_path_does_not_exist(): void
    {
        $this->getJson('/api/v1/buildings')->assertNotFound();
        $this->postJson('/api/v1/buildings', [])->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | S92: aislamiento: el actor ve sus conjuntos y solo ellos
    |--------------------------------------------------------------------------
    */

    public function test_an_actor_without_memberships_gets_an_empty_listing(): void
    {
        // Un actor sin membresia activa no tiene conjuntos: el listado es
        // vacio, no un 403, para que la UI pueda ofrecer el alta.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/complexes')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_a_complex_of_another_actor_is_not_listed_and_is_not_resolved(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$user] = $this->makeMember($mine, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($user)->getJson('/api/v1/complexes')->assertOk();

        $this->assertNotContains(
            $other->uuid,
            array_column($response->json('data'), 'uuid'),
            'Un conjunto ajeno aparece en el listado.'
        );

        $this->actingAs($user)
            ->getJson("/api/v1/complexes/{$other->uuid}")
            ->assertNotFound();
    }

    public function test_the_platform_sees_the_whole_catalog_and_a_complex_admin_only_its_own(): void
    {
        $one = $this->makeComplex('Conjunto Uno');
        $two = $this->makeComplex('Conjunto Dos');

        [$platform] = $this->makeMember($one, SystemRole::PLATFORM_ADMIN);
        [$admin] = $this->makeMember($two, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($platform)->getJson('/api/v1/complexes')->assertOk();

        $uuids = array_column($response->json('data'), 'uuid');
        $this->assertContains($one->uuid, $uuids);
        $this->assertContains($two->uuid, $uuids);

        $response = $this->actingAs($admin)->getJson('/api/v1/complexes')->assertOk();

        $uuids = array_column($response->json('data'), 'uuid');
        $this->assertContains($two->uuid, $uuids);
        $this->assertNotContains($one->uuid, $uuids);
    }

    /*
    |--------------------------------------------------------------------------
    | S92: la puerta del alta (matriz con y sin `complex.create`)
    |--------------------------------------------------------------------------
    */

    public function test_the_platform_can_create_a_complex_and_a_complex_admin_cannot(): void
    {
        $complex = $this->makeComplex('Conjunto Uno');

        [$platform] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $payload = [
            'name' => 'Conjunto Nuevo',
            'code' => 'conjunto-nuevo-'.uniqid(),
            'address' => 'Calle 1 # 2-3',
            'city' => 'Bogota',
        ];

        $this->actingAs($admin)
            ->postJson('/api/v1/complexes', $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('residential_complexes', ['name' => 'Conjunto Nuevo']);

        $this->actingAs($platform)
            ->postJson('/api/v1/complexes', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('residential_complexes', ['name' => 'Conjunto Nuevo']);
    }

    /*
    |--------------------------------------------------------------------------
    | S92: el listado indica el conjunto activo
    |--------------------------------------------------------------------------
    */

    public function test_the_listing_indicates_the_active_complex_of_the_actor(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$user] = $this->makeMember($mine, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->postJson("/api/v1/complexes/{$mine->uuid}/select")
            ->assertOk();

        $response = $this->actingAs($user)->getJson('/api/v1/complexes')->assertOk();

        $response->assertJsonPath('active_complex_uuid', $mine->uuid);
        $response->assertJsonPath('data.0.is_current', true);
    }

    /*
    |--------------------------------------------------------------------------
    | S93: la seleccion explicita del conjunto activo
    |--------------------------------------------------------------------------
    */

    public function test_selecting_a_complex_fixes_the_active_context(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');

        [$user] = $this->makeMember($mine, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/complexes/{$mine->uuid}/select")
            ->assertOk();

        $response->assertJsonPath('data.uuid', $mine->uuid);

        $this->assertSame($mine->id, ComplexContext::getCurrentComplexId());
    }

    public function test_selecting_a_complex_clears_the_selection_requirement(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');

        [$user] = $this->makeMember($mine, SystemRole::COMPLEX_ADMIN);

        $this->withSession(['complex_selection_required' => true]);

        $this->actingAs($user)
            ->postJson("/api/v1/complexes/{$mine->uuid}/select")
            ->assertOk();

        $this->assertNull(session('complex_selection_required'));
    }

    public function test_a_complex_of_another_actor_cannot_be_selected(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$user] = $this->makeMember($mine, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->postJson("/api/v1/complexes/{$other->uuid}/select")
            ->assertNotFound();

        $this->assertNull(ComplexContext::getCurrentComplexId());
    }

    public function test_a_complex_without_an_active_membership_cannot_be_selected(): void
    {
        $complex = $this->makeComplex('Conjunto Propio');

        $user = User::factory()->create();
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::SUSPENDED,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/complexes/{$complex->uuid}/select")
            ->assertNotFound();

        $this->assertNull(ComplexContext::getCurrentComplexId());
    }

    public function test_a_nonexistent_complex_cannot_be_selected(): void
    {
        $complex = $this->makeComplex('Conjunto Propio');

        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->postJson('/api/v1/complexes/2c1f4b0e-0000-4000-8000-000000000000/select')
            ->assertNotFound();

        $this->assertNull(ComplexContext::getCurrentComplexId());
    }
}
