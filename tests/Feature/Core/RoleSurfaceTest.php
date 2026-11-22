<?php

namespace Tests\Feature\Core;

use App\Enums\SystemRole;
use App\Models\ResidentialComplex;
use App\Models\Role;

/**
 * Superficie de roles del conjunto (specs S83-S87).
 *
 * La superficie es `/api/v1/core/roles` y gobierna los roles: lista los del
 * sistema y los propios del conjunto activo, crea roles propios y los edita
 * (nombre, descripcion y permisos). El permiso de escritura es
 * `complex.manage_members`.
 *
 * Reglas que se comprueban aqui:
 * - Aislamiento: un rol de otro conjunto no aparece en el listado.
 * - Matriz de permisos: con y sin `complex.manage_members`.
 * - La `key` del rol propio es unica dentro del conjunto y no puede
 *   coincidir con la de un rol del catalogo; el `name` es unico por conjunto.
 * - Los permisos salen del catalogo cerrado (`ComplexPermission`).
 */
class RoleSurfaceTest extends CoreTestCase
{
    // ------------------------------------------------------------- listado

    public function test_a_member_without_the_permission_cannot_list_the_roles(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($resident)
            ->getJson('/api/v1/core/roles')
            ->assertForbidden();
    }

    public function test_the_admin_lists_the_system_roles_and_the_roles_of_the_complex(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $own = $this->makeComplexRole($complex, 'seguridad', 'Seguridad');
        $own->grantPermission('unit.view');

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/roles')
            ->assertOk();

        $keys = array_column($response->json('data'), 'key');

        $this->assertContains(SystemRole::COMPLEX_ADMIN->value, $keys);
        $this->assertContains('seguridad', $keys);

        // El rol propio expone su conjunto de permisos.
        $listed = collect($response->json('data'))->firstWhere('key', 'seguridad');

        $this->assertSame(['unit.view'], $listed['permissions']);
        $this->assertFalse($listed['is_system']);
    }

    public function test_the_roles_of_another_complex_are_not_listed(): void
    {
        $complex = $this->makeComplex('Torres');
        $other = $this->makeComplex('Otro conjunto');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeComplexRole($other, 'portero', 'Portero');

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/roles')
            ->assertOk();

        $this->assertNotContains('portero', array_column($response->json('data'), 'key'));
    }

    // ---------------------------------------------------------------- alta

    public function test_the_admin_creates_a_role_of_the_complex(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/roles', [
                'key' => 'seguridad',
                'name' => 'Seguridad',
                'description' => 'Personal de seguridad',
                'permissions' => ['complex.manage_members', 'unit.view'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'seguridad')
            ->assertJsonPath('data.name', 'Seguridad')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.permissions', ['complex.manage_members', 'unit.view']);

        $role = Role::where('key', 'seguridad')->firstOrFail();

        $this->assertSame($complex->id, $role->residential_complex_id);
        $this->assertFalse($role->is_system);
        $this->assertTrue($role->is_editable);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission_key' => 'complex.manage_members',
        ]);
    }

    public function test_the_creation_is_rejected_when_a_permission_is_not_in_the_catalogue(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/roles', [
                'key' => 'seguridad',
                'name' => 'Seguridad',
                'permissions' => ['permiso.inventado'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El permiso no pertenece al catálogo de permisos.');

        // El rechazo no deja el rol a medias.
        $this->assertDatabaseMissing('roles', [
            'residential_complex_id' => $complex->id,
        ]);
    }

    public function test_the_creation_is_rejected_when_the_key_already_exists_in_the_complex(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeComplexRole($complex, 'seguridad', 'Seguridad');

        $this->actingAs($admin)
            ->postJson('/api/v1/core/roles', [
                'key' => 'seguridad',
                'name' => 'Otro nombre',
                'permissions' => [],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ya existe un rol con esa clave en el conjunto.');

        $this->assertSame(1, Role::where('residential_complex_id', $complex->id)->count());
    }

    public function test_the_creation_is_rejected_when_the_name_already_exists_in_the_complex(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeComplexRole($complex, 'seguridad', 'Seguridad');

        $this->actingAs($admin)
            ->postJson('/api/v1/core/roles', [
                'key' => 'otro_rol',
                'name' => 'Seguridad',
                'permissions' => [],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ya existe un rol con ese nombre en el conjunto.');

        $this->assertSame(1, Role::where('residential_complex_id', $complex->id)->count());
    }

    public function test_the_creation_is_rejected_when_the_key_belongs_to_a_system_role(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/roles', [
                'key' => SystemRole::COMPLEX_ADMIN->value,
                'name' => 'Administrador propio',
                'permissions' => [],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'La clave ya pertenece a un rol del catálogo de roles.');

        $this->assertDatabaseMissing('roles', [
            'residential_complex_id' => $complex->id,
        ]);
    }

    public function test_a_member_without_the_permission_cannot_create_a_role(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($resident)
            ->postJson('/api/v1/core/roles', [
                'key' => 'seguridad',
                'name' => 'Seguridad',
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', [
            'residential_complex_id' => $complex->id,
        ]);
    }

    // ------------------------------------------------------------- edicion

    public function test_the_admin_edits_the_name_and_replaces_the_permissions_of_a_role(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $role = $this->makeComplexRole($complex, 'seguridad', 'Seguridad');
        $role->grantPermission('unit.view');

        $this->actingAs($admin)
            ->putJson("/api/v1/core/roles/{$role->id}", [
                'name' => 'Seguridad nocturna',
                'permissions' => ['complex.manage_members'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Seguridad nocturna')
            ->assertJsonPath('data.permissions', ['complex.manage_members']);

        $this->assertSame('Seguridad nocturna', $role->fresh()->name);

        // El conjunto de permisos se reemplaza, no se acumula.
        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'permission_key' => 'unit.view',
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission_key' => 'complex.manage_members',
        ]);
    }

    public function test_the_key_of_a_role_cannot_be_changed(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $role = $this->makeComplexRole($complex, 'seguridad', 'Seguridad');

        $this->actingAs($admin)
            ->putJson("/api/v1/core/roles/{$role->id}", [
                'key' => 'otro_rol',
                'name' => 'Seguridad',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'La clave del rol es inmutable.');

        $this->assertSame('seguridad', $role->fresh()->key);
    }

    public function test_a_system_role_cannot_be_edited(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $systemRole = Role::where('key', SystemRole::COMPLEX_ADMIN->value)->firstOrFail();

        $this->actingAs($admin)
            ->putJson("/api/v1/core/roles/{$systemRole->id}", [
                'name' => 'Administrador',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Los roles del sistema no se pueden editar.');

        $this->assertSame(SystemRole::COMPLEX_ADMIN->label(), $systemRole->fresh()->name);
    }

    public function test_a_role_of_another_complex_is_not_found(): void
    {
        $complex = $this->makeComplex('Torres');
        $other = $this->makeComplex('Otro conjunto');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $foreign = $this->makeComplexRole($other, 'portero', 'Portero');

        $this->actingAs($admin)
            ->putJson("/api/v1/core/roles/{$foreign->id}", [
                'name' => 'Portero nocturno',
            ])
            ->assertNotFound();

        $this->assertSame('Portero', $foreign->fresh()->name);
    }

    public function test_a_member_without_the_permission_cannot_edit_a_role(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);

        $role = $this->makeComplexRole($complex, 'seguridad', 'Seguridad');

        $this->actingAs($resident)
            ->putJson("/api/v1/core/roles/{$role->id}", [
                'name' => 'Seguridad nocturna',
            ])
            ->assertForbidden();

        $this->assertSame('Seguridad', $role->fresh()->name);
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
}
