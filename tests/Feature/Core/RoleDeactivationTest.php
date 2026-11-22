<?php

namespace Tests\Feature\Core;

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;

/**
 * Baja de los roles del conjunto: la desactivacion (spec S88).
 *
 * Un rol no se elimina: se desactiva. El rol desactivado conserva su traza
 * y sus asignaciones, pero deja de estar en vigor: `ScopedPermission` lo
 * ignora, de modo que deja de conceder permisos de inmediato, y la superficie
 * de membresias deja de exponerlo como rol en vigor.
 */
class RoleDeactivationTest extends CoreTestCase
{
    public function test_a_new_role_of_the_complex_is_in_force(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/roles', [
                'key' => 'seguridad',
                'name' => 'Seguridad',
                'permissions' => ['complex.manage_members'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_a_role_is_deactivated_instead_of_deleted(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $role = $this->makeComplexRole($complex, 'seguridad', 'Seguridad');

        $this->actingAs($admin)
            ->putJson("/api/v1/core/roles/{$role->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // El rol sigue existiendo: se desactiva, no se elimina.
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'is_active' => false,
        ]);
    }

    public function test_a_deactivated_role_stops_granting_permissions_immediately(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [$member, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $role = $this->makeComplexRole($complex, 'seguridad', 'Seguridad');
        $role->grantPermission('complex.manage_members');
        $membership->roles()->attach($role->id);

        // Con el rol en vigor, el miembro administra las membresias.
        $this->actingAs($member)
            ->getJson('/api/v1/core/members')
            ->assertOk();

        $this->actingAs($admin)
            ->putJson("/api/v1/core/roles/{$role->id}", ['is_active' => false])
            ->assertOk();

        // Desactivado, el rol deja de conceder permisos de inmediato.
        $this->actingAs($member)
            ->getJson('/api/v1/core/members')
            ->assertForbidden();
    }

    public function test_a_deactivated_role_does_not_grant_permissions_on_the_other_surfaces(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        // Un miembro con **solo** el rol propio del conjunto: sus permisos
        // vienen del rol, no de un rol del catalogo.
        $member = User::factory()->create();
        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
            'user_id' => $member->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = $this->makeComplexRole($complex, 'seguridad', 'Seguridad');
        $role->grantPermission('unit.view');
        $membership->roles()->attach($role->id);

        // El rol propio concede el permiso de ver unidades.
        $this->actingAs($member)
            ->getJson('/api/v1/core/property-units')
            ->assertOk();

        $this->actingAs($admin)
            ->putJson("/api/v1/core/roles/{$role->id}", ['is_active' => false])
            ->assertOk();

        $this->actingAs($member)
            ->getJson('/api/v1/core/property-units')
            ->assertForbidden();
    }

    public function test_a_deactivated_role_is_not_exposed_as_a_role_in_force(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $role = $this->makeComplexRole($complex, 'seguridad', 'Seguridad');
        $membership->roles()->attach($role->id);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/members')
            ->assertOk();

        $this->assertContains('seguridad', $this->rolesOf($response->json('data'), $membership->id));

        $this->actingAs($admin)
            ->putJson("/api/v1/core/roles/{$role->id}", ['is_active' => false])
            ->assertOk();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/members')
            ->assertOk();

        $this->assertNotContains('seguridad', $this->rolesOf($response->json('data'), $membership->id));
    }

    public function test_a_deactivated_role_can_be_reactivated(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [$member, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $role = $this->makeComplexRole($complex, 'seguridad', 'Seguridad');
        $role->grantPermission('complex.manage_members');
        $membership->roles()->attach($role->id);

        $this->actingAs($admin)
            ->putJson("/api/v1/core/roles/{$role->id}", ['is_active' => false])
            ->assertOk();

        $this->actingAs($member)
            ->getJson('/api/v1/core/members')
            ->assertForbidden();

        $this->actingAs($admin)
            ->putJson("/api/v1/core/roles/{$role->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->actingAs($member)
            ->getJson('/api/v1/core/members')
            ->assertOk();
    }

    // ------------------------------------------------------------- helpers

    /**
     * Rol propio del conjunto.
     */
    private function makeComplexRole(ResidentialComplex $complex, string $key, string $name): Role
    {
        return Role::factory()->complexLevel($complex)->create([
            'key' => $key,
            'name' => $name,
        ]);
    }

    /**
     * Las claves de los roles en vigor de una membresia del listado.
     *
     * @param  array<int, array<string, mixed>>  $memberships
     * @return array<int, string>
     */
    private function rolesOf(array $memberships, int $membershipId): array
    {
        foreach ($memberships as $membership) {
            if ((int) ($membership['id'] ?? 0) === $membershipId) {
                return array_column($membership['roles'] ?? [], 'key');
            }
        }

        return [];
    }
}
