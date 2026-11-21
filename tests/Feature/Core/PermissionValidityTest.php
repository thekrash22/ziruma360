<?php

namespace Tests\Feature\Core;

use App\Enums\ComplexPermission;
use App\Enums\SystemRole;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\RolePermission;
use App\Services\PermissionService;
use App\Services\ScopedPermission;
use Illuminate\Support\Facades\Gate;

/**
 * Vigencia y alcance del permiso resuelto por `PermissionService` (S79-S80).
 *
 * `PermissionService` resuelve el permiso con la **misma** semantica que
 * `ScopedPermission`: una membresia activa, un rol del propio conjunto o del
 * catalogo de sistema y una asignacion de rol **vigente**.
 *
 * Lo que se comprueba aqui, por HTTP real sobre las superficies que
 * resuelven por el servicio (personas y unidades de la API):
 * - una asignacion vencida (o todavia no vigente) no concede nada;
 * - una asignacion vigente si concede;
 * - un rol de otro conjunto no concede nada.
 *
 * Y ademas que el servicio y la policy resuelven lo mismo: hay una sola
 * fuente de verdad (S82 f).
 */
class PermissionValidityTest extends CoreTestCase
{
    // ------------------------------------------------- asignacion vencida

    public function test_an_expired_role_assignment_does_not_grant_permissions(): void
    {
        $complex = $this->makeComplex('Torres');

        [$member, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $person = $this->makePerson($complex);

        // El rol de administrador se asigna con una vigencia ya vencida.
        $this->assignRole($membership, SystemRole::COMPLEX_ADMIN, [
            'valid_from' => now()->subDays(10),
            'valid_until' => now()->subDay(),
        ]);

        $this->actingAs($member)
            ->getJson('/api/v1/core/property-units')
            ->assertForbidden();

        $this->actingAs($member)
            ->getJson('/api/v1/core/people')
            ->assertForbidden();

        $this->actingAs($member)
            ->getJson("/api/v1/core/people/{$person->uuid}")
            ->assertForbidden();
    }

    public function test_a_role_assignment_that_is_not_in_force_yet_does_not_grant_permissions(): void
    {
        $complex = $this->makeComplex('Torres');

        [$member, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        // La asignacion todavia no ha empezado.
        $this->assignRole($membership, SystemRole::COMPLEX_ADMIN, [
            'valid_from' => now()->addDay(),
            'valid_until' => now()->addDays(10),
        ]);

        $this->actingAs($member)
            ->getJson('/api/v1/core/property-units')
            ->assertForbidden();

        $this->actingAs($member)
            ->getJson('/api/v1/core/people')
            ->assertForbidden();
    }

    public function test_an_expired_role_assignment_is_not_reported_as_a_permission(): void
    {
        $complex = $this->makeComplex('Torres');

        [$member, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->assignRole($membership, SystemRole::COMPLEX_ADMIN, [
            'valid_from' => now()->subDays(10),
            'valid_until' => now()->subDay(),
        ]);

        $permissions = PermissionService::getUserPermissions($member, $complex->id);

        $this->assertFalse($permissions->contains('unit.view'));
        $this->assertFalse($permissions->contains('person.view'));
        $this->assertTrue($permissions->isEmpty());
    }

    // ------------------------------------------------ asignacion vigente

    public function test_a_role_assignment_in_force_grants_the_permissions(): void
    {
        $complex = $this->makeComplex('Torres');

        [$member, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $person = $this->makePerson($complex);

        $this->assignRole($membership, SystemRole::COMPLEX_ADMIN, [
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ]);

        $this->actingAs($member)
            ->getJson('/api/v1/core/property-units')
            ->assertOk();

        $this->actingAs($member)
            ->getJson('/api/v1/core/people')
            ->assertOk();

        $this->actingAs($member)
            ->getJson("/api/v1/core/people/{$person->uuid}")
            ->assertOk();
    }

    // --------------------------------------------------- rol de otro conjunto

    public function test_a_role_of_another_complex_does_not_grant_permissions(): void
    {
        $complex = $this->makeComplex('Torres');
        $other = $this->makeComplex('Otro conjunto');

        [$member, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);
        $person = $this->makePerson($complex);

        // Un rol del otro conjunto, con permisos de lectura de personas y
        // unidades, asignado a la membresia de este conjunto.
        $foreignRole = Role::factory()->complexLevel($other)->create(['key' => 'rol_ajeno']);

        foreach (['unit.view', 'person.view'] as $permissionKey) {
            RolePermission::create([
                'role_id' => $foreignRole->id,
                'permission_key' => $permissionKey,
            ]);
        }

        $this->assignRole($membership, $foreignRole, [
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ]);

        $this->actingAs($member)
            ->getJson('/api/v1/core/property-units')
            ->assertForbidden();

        $this->actingAs($member)
            ->getJson('/api/v1/core/people')
            ->assertForbidden();

        $this->actingAs($member)
            ->getJson("/api/v1/core/people/{$person->uuid}")
            ->assertForbidden();
    }

    // ------------------------------------- una sola fuente de verdad

    public function test_the_service_and_the_policy_resolve_the_permission_the_same_way(): void
    {
        $complex = $this->makeComplex('Torres');

        [$member, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        // Con la asignacion vencida, ni el servicio ni la policy conceden.
        $this->assignRole($membership, SystemRole::COMPLEX_ADMIN, [
            'valid_from' => now()->subDays(10),
            'valid_until' => now()->subDay(),
        ]);

        $this->assertFalse(PermissionService::has($member, ComplexPermission::UNIT_VIEW, $complex->id));
        $this->assertFalse(PermissionService::userHasPermissionInComplex($member, $complex->id, 'unit.view'));
        $this->assertFalse(ScopedPermission::has($member, $complex->id, ComplexPermission::UNIT_VIEW));

        $this->actingAs($member);

        $this->assertFalse(Gate::allows('viewAny', [PropertyUnit::class, $complex->id]));
        $this->assertFalse(Gate::allows('viewAny', [Person::class, $complex->id]));

        // Y con la asignacion vigente, los cuatro conceden.
        $this->assignRole($membership, SystemRole::COMPLEX_ADMIN, [
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ]);

        $this->assertTrue(PermissionService::has($member, ComplexPermission::UNIT_VIEW, $complex->id));
        $this->assertTrue(PermissionService::userHasPermissionInComplex($member, $complex->id, 'unit.view'));
        $this->assertTrue(ScopedPermission::has($member, $complex->id, ComplexPermission::UNIT_VIEW));
        $this->assertTrue(Gate::allows('viewAny', [PropertyUnit::class, $complex->id]));
        $this->assertTrue(Gate::allows('viewAny', [Person::class, $complex->id]));
    }

    // ------------------------------------------------------------- helpers

    /**
     * Asigna un rol a la membresia con la vigencia indicada.
     *
     * @param  array<string, mixed>  $validity
     */
    private function assignRole(
        ResidentialComplexMembership $membership,
        SystemRole|Role $role,
        array $validity = []
    ): void {
        $roleId = $role instanceof Role
            ? $role->id
            : Role::where('key', $role->value)->firstOrFail()->id;

        $membership->roles()->sync([$roleId => $validity]);
    }
}
