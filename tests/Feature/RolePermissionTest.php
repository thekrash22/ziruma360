<?php

namespace Tests\Feature;

use App\Enums\ComplexPermission;
use App\Enums\SystemRole;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\ComplexContext;
use App\Services\PermissionService;
use Carbon\Carbon;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ComplexContext::clear();
        $this->seed(SystemRoleSeeder::class);
    }

    // ─────────────────────────────────────────────────────────────
    // S5: role_permissions
    // ─────────────────────────────────────────────────────────────

    public function test_system_roles_are_seeded_with_permissions(): void
    {
        // Platform admin gets all permissions
        $platformAdmin = Role::where('key', SystemRole::PLATFORM_ADMIN->value)->firstOrFail();
        $this->assertTrue($platformAdmin->is_system);
        $this->assertFalse($platformAdmin->canBeEdited());
        $this->assertGreaterThan(0, $platformAdmin->permissions()->count());
        $this->assertTrue($platformAdmin->hasPermission(ComplexPermission::UNIT_DELETE));
        $this->assertTrue($platformAdmin->hasPermission(ComplexPermission::STAY_APPROVE));
    }

    public function test_doorman_has_minimal_permissions(): void
    {
        $doorman = Role::where('key', SystemRole::DOORMAN->value)->firstOrFail();
        $this->assertTrue($doorman->hasPermission(ComplexPermission::PERSON_VIEW_MINIMAL));
        $this->assertFalse($doorman->hasPermission(ComplexPermission::UNIT_EDIT));
        $this->assertFalse($doorman->hasPermission(ComplexPermission::STAY_CREATE));
    }

    public function test_host_can_manage_stays(): void
    {
        $host = Role::where('key', SystemRole::HOST->value)->firstOrFail();
        $this->assertTrue($host->hasPermission(ComplexPermission::STAY_VIEW));
        $this->assertTrue($host->hasPermission(ComplexPermission::STAY_CREATE));
        $this->assertTrue($host->hasPermission(ComplexPermission::STAY_MANAGE));
        $this->assertTrue($host->hasPermission(ComplexPermission::STAY_CHECKIN));
        $this->assertFalse($host->hasPermission(ComplexPermission::UNIT_EDIT));
    }

    public function test_role_grant_and_revoke_permission(): void
    {
        $role = Role::factory()->create();
        $this->assertFalse($role->hasPermission(ComplexPermission::UNIT_CREATE));

        $role->grantPermission(ComplexPermission::UNIT_CREATE);
        $this->assertTrue($role->hasPermission(ComplexPermission::UNIT_CREATE));

        $role->revokePermission(ComplexPermission::UNIT_CREATE);
        $this->assertFalse($role->hasPermission(ComplexPermission::UNIT_CREATE));
    }

    public function test_permission_key_is_unique_per_role(): void
    {
        $role = Role::factory()->create();
        RolePermission::create(['role_id' => $role->id, 'permission_key' => 'unit.view']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        RolePermission::create(['role_id' => $role->id, 'permission_key' => 'unit.view']);
    }

    public function test_permission_key_can_be_shared_across_roles(): void
    {
        $role1 = Role::factory()->create();
        $role2 = Role::factory()->create();

        RolePermission::create(['role_id' => $role1->id, 'permission_key' => 'unit.view']);
        RolePermission::create(['role_id' => $role2->id, 'permission_key' => 'unit.view']);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role1->id,
            'permission_key' => 'unit.view',
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role2->id,
            'permission_key' => 'unit.view',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // S6: membership_roles (temporal validity)
    // ─────────────────────────────────────────────────────────────

    public function test_membership_role_valid_now(): void
    {
        $membershipRole = \Database\Factories\MembershipRoleFactory::new()->create([
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ]);

        $this->assertTrue($membershipRole->isValid());
        $this->assertFalse($membershipRole->isExpired());
        $this->assertFalse($membershipRole->isPending());
    }

    public function test_membership_role_expired(): void
    {
        $membershipRole = \Database\Factories\MembershipRoleFactory::new()->create([
            'valid_from' => now()->subDays(10),
            'valid_until' => now()->subDay(),
        ]);

        $this->assertFalse($membershipRole->isValid());
        $this->assertTrue($membershipRole->isExpired());
        $this->assertFalse($membershipRole->isPending());
    }

    public function test_membership_role_pending(): void
    {
        $membershipRole = \Database\Factories\MembershipRoleFactory::new()->create([
            'valid_from' => now()->addDay(),
            'valid_until' => null,
        ]);

        $this->assertFalse($membershipRole->isValid());
        $this->assertFalse($membershipRole->isExpired());
        $this->assertTrue($membershipRole->isPending());
    }

    public function test_membership_role_no_date_restrictions(): void
    {
        $membershipRole = \Database\Factories\MembershipRoleFactory::new()->create([
            'valid_from' => null,
            'valid_until' => null,
        ]);

        $this->assertTrue($membershipRole->isValid());
        $this->assertFalse($membershipRole->isExpired());
        $this->assertFalse($membershipRole->isPending());
    }

    public function test_membership_role_valid_from_only_future(): void
    {
        $membershipRole = \Database\Factories\MembershipRoleFactory::new()->create([
            'valid_from' => now()->addHour(),
            'valid_until' => null,
        ]);

        $this->assertFalse($membershipRole->isValid(now()->subSecond()));
        $this->assertTrue($membershipRole->isValid(now()->addHour()));
    }

    public function test_membership_can_have_multiple_roles(): void
    {
        $membership = ResidentialComplexMembership::factory()->create();
        $role1 = Role::factory()->create();
        $role2 = Role::factory()->create();

        $membership->roles()->attach($role1->id);
        $membership->roles()->attach($role2->id);

        $this->assertEquals(2, $membership->fresh()->roles()->count());
    }

    public function test_membership_role_unique_per_membership_and_role(): void
    {
        $membership = ResidentialComplexMembership::factory()->create();
        $role = Role::factory()->create();

        \Database\Factories\MembershipRoleFactory::new()->create([
            'membership_id' => $membership->id,
            'role_id' => $role->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \Database\Factories\MembershipRoleFactory::new()->create([
            'membership_id' => $membership->id,
            'role_id' => $role->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PermissionService integration
    // ─────────────────────────────────────────────────────────────

    public function test_permission_service_has_permission(): void
    {
        $complex = ResidentialComplex::factory()->create();
        $user = User::factory()->create();
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => 'active',
        ]);

        $adminRole = Role::where('key', SystemRole::COMPLEX_ADMIN->value)->first();
        $membership->roles()->attach($adminRole->id);

        ComplexContext::setCurrentComplexId($complex->id);

        $this->assertTrue(PermissionService::has($user, ComplexPermission::UNIT_EDIT));
        $this->assertTrue(PermissionService::has($user, ComplexPermission::PERSON_VIEW));
        $this->assertTrue(PermissionService::has($user, ComplexPermission::STAY_APPROVE)); // complex admin tiene approve
    }

    public function test_permission_service_no_membership_returns_false(): void
    {
        $complex = ResidentialComplex::factory()->create();
        $user = User::factory()->create(); // sin membresía

        ComplexContext::setCurrentComplexId($complex->id);

        $this->assertFalse(PermissionService::has($user, ComplexPermission::COMPLEX_VIEW));
    }

    public function test_permission_service_no_active_complex_returns_false(): void
    {
        $user = User::factory()->create();
        ComplexContext::clear();

        $this->assertFalse(PermissionService::has($user, ComplexPermission::COMPLEX_VIEW));
    }

    public function test_permission_service_is_admin(): void
    {
        $complex = ResidentialComplex::factory()->create();
        $user = User::factory()->create();
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => 'active',
        ]);

        $adminRole = Role::where('key', SystemRole::COMPLEX_ADMIN->value)->first();
        $membership->roles()->attach($adminRole->id);

        ComplexContext::setCurrentComplexId($complex->id);

        $this->assertTrue(PermissionService::isAdmin($user));
    }

    public function test_permission_service_can_manage_members(): void
    {
        $complex = ResidentialComplex::factory()->create();
        $user = User::factory()->create();
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => 'active',
        ]);

        $adminRole = Role::where('key', SystemRole::COMPLEX_ADMIN->value)->first();
        $membership->roles()->attach($adminRole->id);

        ComplexContext::setCurrentComplexId($complex->id);

        $this->assertTrue(PermissionService::canManageMembers($user));
    }

    public function test_permission_service_get_user_permissions(): void
    {
        $complex = ResidentialComplex::factory()->create();
        $user = User::factory()->create();
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => 'active',
        ]);

        $doormanRole = Role::where('key', SystemRole::DOORMAN->value)->first();
        $membership->roles()->attach($doormanRole->id);

        ComplexContext::setCurrentComplexId($complex->id);

        $permissions = PermissionService::getUserPermissions($user);
        $this->assertTrue($permissions->contains('access.register'));
        $this->assertTrue($permissions->contains('person.view_minimal'));
        $this->assertFalse($permissions->contains('unit.edit'));
    }

    public function test_permission_service_with_string_permission(): void
    {
        $complex = ResidentialComplex::factory()->create();
        $user = User::factory()->create();
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => 'active',
        ]);

        $adminRole = Role::where('key', SystemRole::COMPLEX_ADMIN->value)->first();
        $membership->roles()->attach($adminRole->id);

        ComplexContext::setCurrentComplexId($complex->id);

        // Usando string en vez de enum
        $this->assertTrue(PermissionService::has($user, 'unit.edit'));
        $this->assertTrue(PermissionService::has($user, 'unit.delete')); // complex admin sí tiene delete
    }
}
