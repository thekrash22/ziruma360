<?php

namespace Tests\Feature\Core;

use App\Enums\FloorType;
use App\Enums\LeaseStatus;
use App\Enums\MembershipStatus;
use App\Enums\OccupancyType;
use App\Enums\SystemRole;
use App\Enums\TipoDocumento;
use App\Models\Apartment;
use App\Models\ApartmentType;
use App\Models\AuditLog;
use App\Models\Building;
use App\Models\BuildingFloor;
use App\Models\CommonArea;
use App\Models\CommonPropertyExclusiveUseAssignment;
use App\Models\Employment;
use App\Models\MembershipRole;
use App\Models\ParkingFacility;
use App\Models\ParkingSpace;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitAuthorization;
use App\Models\PropertyUnitOwnership;
use App\Models\PropertyUnitRelation;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\UnitLease;
use App\Models\UnitOccupancy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;

/**
 * Audit coverage of the seven domains (specs S97, S98, S99 and S104 a/b/f).
 *
 * The seven audited domains are: the complex itself (its policies live in
 * `settings`), the memberships, the roles, the ownership, the occupants and
 * the physical structure.
 */
class AuditDomainCoverageTest extends CoreTestCase
{
    /**
     * The last audit trail of an entity type and action.
     */
    private function trail(string $entityType, string $action = 'created'): ?AuditLog
    {
        return AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('action', $action)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * A complex with an administrator acting as the current user.
     *
     * @return array{0: User, 1: ResidentialComplex}
     */
    private function complexWithAdmin(string $name = 'Torres'): array
    {
        $complex = $this->makeComplex($name);

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin);

        return [$admin, $complex];
    }

    /*
    |--------------------------------------------------------------------------
    | S104 (a): one write per domain leaves a trail with actor, instant and
    | the before/after values; a deletion leaves a `deleted` trail.
    |--------------------------------------------------------------------------
    */

    public function test_a_membership_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
            'user_id' => User::factory()->create()->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $trail = $this->trail(ResidentialComplexMembership::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $membership->id, $trail->entity_id);
        $this->assertNotNull($trail->occurred_at);
        $this->assertNull($trail->before_data);
        $this->assertSame('active', $trail->after_data['status']);
    }

    public function test_a_complex_role_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $role = Role::factory()->complexLevel($complex)->create([
            'key' => 'porter_'.uniqid(),
            'name' => 'Porter '.uniqid(),
        ]);

        $trail = $this->trail(Role::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $role->id, $trail->entity_id);
        $this->assertSame($role->key, $trail->after_data['key']);
    }

    public function test_a_role_assignment_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
            'user_id' => User::factory()->create()->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::query()->where('key', SystemRole::DOORMAN->value)->firstOrFail();

        $assignment = MembershipRole::factory()
            ->forMembership($membership)
            ->forRole($role)
            ->create();

        $trail = $this->trail(MembershipRole::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $assignment->id, $trail->entity_id);
        $this->assertSame($role->id, $trail->after_data['role_id']);
    }

    public function test_a_role_assignment_through_the_pivot_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
            'user_id' => User::factory()->create()->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::query()->where('key', SystemRole::RESIDENT->value)->firstOrFail();

        $membership->roles()->attach($role->id);

        $trail = $this->trail(MembershipRole::class);

        $this->assertNotNull($trail);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame($role->id, $trail->after_data['role_id']);

        // The trail identifies the assignment: the id of the row written
        // through the pivot reaches the model.
        $assignmentId = (int) DB::table('membership_roles')
            ->where('membership_id', $membership->id)
            ->value('id');

        $this->assertSame((string) $assignmentId, $trail->entity_id);
    }

    public function test_updating_the_validity_of_an_assignment_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
            'user_id' => User::factory()->create()->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::query()->where('key', SystemRole::TENANT->value)->firstOrFail();

        $membership->roles()->attach($role->id, ['valid_from' => now()->subDay()]);

        $membership->roles()->sync([$role->id => ['valid_until' => now()->addMonth()]]);

        $trail = $this->trail(MembershipRole::class, 'updated');

        $this->assertNotNull($trail);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertArrayHasKey('valid_until', $trail->after_data);
    }

    public function test_detaching_a_role_from_a_membership_leaves_a_deleted_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
            'user_id' => User::factory()->create()->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::query()->where('key', SystemRole::HOST->value)->firstOrFail();

        $membership->roles()->attach($role->id);

        $membership->roles()->detach($role->id);

        $trail = $this->trail(MembershipRole::class, 'deleted');

        $this->assertNotNull($trail);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame($role->id, $trail->before_data['role_id']);
    }

    public function test_a_mass_delete_of_role_assignments_leaves_a_deleted_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
            'user_id' => User::factory()->create()->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::query()->where('key', SystemRole::TENANT->value)->firstOrFail();

        $membership->roles()->attach($role->id);

        MembershipRole::query()->where('membership_id', $membership->id)->delete();

        $trail = $this->trail(MembershipRole::class, 'deleted');

        $this->assertNotNull($trail);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame($role->id, $trail->before_data['role_id']);
    }

    public function test_deleting_a_membership_leaves_a_deleted_trail_for_its_role_assignments(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
            'user_id' => User::factory()->create()->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::query()->where('key', SystemRole::TENANT->value)->firstOrFail();

        $membership->roles()->attach($role->id);

        $membership->delete();

        $trail = $this->trail(MembershipRole::class, 'deleted');

        $this->assertNotNull($trail);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame($role->id, $trail->before_data['role_id']);
    }

    public function test_replacing_the_permissions_of_a_role_leaves_a_deleted_trail_for_the_removed_ones(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $role = Role::factory()->complexLevel($complex)->create([
            'key' => 'auditor_'.uniqid(),
            'name' => 'Auditor '.uniqid(),
        ]);

        RolePermission::factory()->forRole($role)->create([
            'permission_key' => 'unit.view',
        ]);

        $role->permissions()->delete();

        $trail = $this->trail(RolePermission::class, 'deleted');

        $this->assertNotNull($trail);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame('unit.view', $trail->before_data['permission_key']);
    }

    public function test_a_role_permission_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $role = Role::factory()->complexLevel($complex)->create([
            'key' => 'auditor_'.uniqid(),
            'name' => 'Auditor '.uniqid(),
        ]);

        $permission = RolePermission::factory()->forRole($role)->create([
            'permission_key' => 'unit.view',
        ]);

        $trail = $this->trail(RolePermission::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $permission->id, $trail->entity_id);
        $this->assertSame('unit.view', $trail->after_data['permission_key']);
    }

    public function test_an_authorization_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $authorization = PropertyUnitAuthorization::factory()
            ->forComplex($complex)
            ->create();

        $trail = $this->trail(PropertyUnitAuthorization::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $authorization->id, $trail->entity_id);
        $this->assertSame($authorization->property_unit_id, $trail->after_data['property_unit_id']);
    }

    public function test_a_person_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $person = Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Perez',
        ]);

        $trail = $this->trail(Person::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $person->id, $trail->entity_id);
        $this->assertSame('Ana Perez', $trail->after_data['full_name']);
    }

    public function test_an_occupancy_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $occupancy = UnitOccupancy::factory()
            ->forComplex($complex)
            ->ofType(OccupancyType::OWNER_RESIDENT)
            ->create();

        $trail = $this->trail(UnitOccupancy::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $occupancy->id, $trail->entity_id);
        $this->assertSame($occupancy->property_unit_id, $trail->after_data['property_unit_id']);
    }

    public function test_a_lease_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $lease = UnitLease::factory()
            ->forComplex($complex)
            ->create(['status' => LeaseStatus::ACTIVE]);

        $trail = $this->trail(UnitLease::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $lease->id, $trail->entity_id);
        $this->assertSame($lease->tenant_person_id, $trail->after_data['tenant_person_id']);
    }

    public function test_a_building_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $building = Building::factory()->create([
            'residential_complex_id' => $complex->id,
        ]);

        $trail = $this->trail(Building::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $building->id, $trail->entity_id);
        $this->assertSame($building->name, $trail->after_data['name']);
    }

    public function test_a_building_floor_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $building = Building::factory()->create([
            'residential_complex_id' => $complex->id,
        ]);

        $floor = BuildingFloor::factory()->create([
            'building_id' => $building->id,
        ]);

        $trail = $this->trail(BuildingFloor::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $floor->id, $trail->entity_id);
        $this->assertSame($building->id, $trail->after_data['building_id']);
    }

    public function test_an_apartment_type_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $type = ApartmentType::factory()->create([
            'residential_complex_id' => $complex->id,
        ]);

        $trail = $this->trail(ApartmentType::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $type->id, $trail->entity_id);
        $this->assertSame($type->code, $trail->after_data['code']);
    }

    public function test_an_apartment_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $apartment = Apartment::factory()->create([
            'residential_complex_id' => $complex->id,
        ]);

        $trail = $this->trail(Apartment::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $apartment->id, $trail->entity_id);
        $this->assertSame($apartment->property_unit_id, $trail->after_data['property_unit_id']);
    }

    public function test_a_property_unit_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $unit = PropertyUnit::factory()->create([
            'residential_complex_id' => $complex->id,
        ]);

        $trail = $this->trail(PropertyUnit::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $unit->id, $trail->entity_id);
        $this->assertSame($unit->number, $trail->after_data['number']);
    }

    public function test_a_common_area_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $area = CommonArea::create([
            'residential_complex_id' => $complex->id,
            'name' => 'Salon social',
            'code' => 'SALON-'.uniqid(),
            'area_type' => 'social_hall',
            'status' => 'active',
        ]);

        $trail = $this->trail(CommonArea::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $area->id, $trail->entity_id);
        $this->assertSame('Salon social', $trail->after_data['name']);
    }

    public function test_a_parking_facility_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $facility = ParkingFacility::create([
            'residential_complex_id' => $complex->id,
            'name' => 'Parqueadero central',
            'facility_type' => 'underground',
            'total_capacity' => 40,
            'operational_capacity' => 35,
            'assignment_mode' => 'manual',
            'status' => 'active',
        ]);

        $trail = $this->trail(ParkingFacility::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $facility->id, $trail->entity_id);
        $this->assertSame('Parqueadero central', $trail->after_data['name']);
    }

    public function test_a_parking_space_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $facility = ParkingFacility::create([
            'residential_complex_id' => $complex->id,
            'name' => 'Parqueadero central',
            'facility_type' => 'underground',
            'total_capacity' => 40,
            'operational_capacity' => 35,
            'assignment_mode' => 'manual',
            'status' => 'active',
        ]);

        $space = ParkingSpace::create([
            'residential_complex_id' => $complex->id,
            'parking_facility_id' => $facility->id,
            'number' => 'P-'.uniqid(),
            'status' => 'active',
        ]);

        $trail = $this->trail(ParkingSpace::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $space->id, $trail->entity_id);
        $this->assertSame($facility->id, $trail->after_data['parking_facility_id']);
    }

    public function test_an_update_leaves_the_before_and_after_values(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $person = Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Perez',
        ]);

        $person->update(['full_name' => 'Ana Maria Perez']);

        $trail = $this->trail(Person::class, 'updated');

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame('Ana Perez', $trail->before_data['full_name']);
        $this->assertSame('Ana Maria Perez', $trail->after_data['full_name']);
    }

    public function test_a_deletion_leaves_a_deleted_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $area = CommonArea::create([
            'residential_complex_id' => $complex->id,
            'name' => 'Gimnasio',
            'code' => 'GYM-'.uniqid(),
            'area_type' => 'gym',
            'status' => 'active',
        ]);

        $area->delete();

        $trail = $this->trail(CommonArea::class, 'deleted');

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $area->id, $trail->entity_id);
        $this->assertNotNull($trail->before_data);
        $this->assertNull($trail->after_data);
    }

    /*
    |--------------------------------------------------------------------------
    | S97: the platform object rule.
    |--------------------------------------------------------------------------
    */

    public function test_a_catalog_role_is_not_audited_and_the_write_does_not_fail(): void
    {
        $role = Role::factory()->create([
            'key' => 'catalog_'.uniqid(),
        ]);

        $this->assertNull($role->residential_complex_id);
        $this->assertNull($this->trail(Role::class));
    }

    public function test_a_permission_of_a_catalog_role_is_not_audited_and_the_write_does_not_fail(): void
    {
        $role = Role::factory()->create();

        $permission = RolePermission::factory()->forRole($role)->create([
            'permission_key' => 'unit.view',
        ]);

        $this->assertNull($permission->residential_complex_id);
        $this->assertNull($this->trail(RolePermission::class));
    }

    public function test_a_building_floor_that_cannot_resolve_its_complex_fails_visibly(): void
    {
        $this->expectException(LogicException::class);

        BuildingFloor::create([
            'building_id' => 987654,
            'floor_number' => 1,
            'floor_type' => FloorType::RESIDENTIAL,
        ]);
    }

    public function test_a_role_assignment_that_cannot_resolve_its_complex_fails_visibly(): void
    {
        $role = Role::query()->firstOrFail();

        $this->expectException(LogicException::class);

        MembershipRole::create([
            'membership_id' => 987654,
            'role_id' => $role->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | S99: sensitive data never reaches the snapshots.
    |--------------------------------------------------------------------------
    */

    public function test_the_person_snapshot_does_not_contain_the_document_number(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        Person::factory()->forComplex($complex)->create([
            'document_type' => TipoDocumento::CC,
            'document_number' => '1010101010',
            'data_consent_ref' => 'CONSENT-REF-1',
        ]);

        $trail = $this->trail(Person::class);

        $this->assertNotNull($trail);

        $encoded = json_encode($trail->after_data);

        $this->assertStringNotContainsString('1010101010', $encoded);
        $this->assertStringNotContainsString('CONSENT-REF-1', $encoded);
        $this->assertSame('[redacted]', $trail->after_data['document_number']);
        $this->assertSame('[redacted]', $trail->after_data['data_consent_ref']);
    }

    public function test_the_change_structure_stays_readable_when_a_sensitive_field_changes(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $person = Person::factory()->forComplex($complex)->create([
            'document_type' => TipoDocumento::CC,
            'document_number' => '2020202020',
        ]);

        $person->update(['document_number' => '3030303030']);

        $trail = $this->trail(Person::class, 'updated');

        $this->assertNotNull($trail);
        $this->assertArrayHasKey('document_number', $trail->before_data);
        $this->assertArrayHasKey('document_number', $trail->after_data);
        $this->assertSame('[redacted]', $trail->before_data['document_number']);
        $this->assertSame('[redacted]', $trail->after_data['document_number']);
        $this->assertStringNotContainsString('3030303030', json_encode($trail->after_data));
    }

    public function test_the_ownership_snapshot_does_not_contain_the_document_reference(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $unit = $this->makeUnit($complex);
        $person = $this->makePerson($complex, null, 'Titular');

        $this->giveOwnership($complex, $unit, $person, [
            'document_reference' => 'Escritura 12345',
        ]);

        $trail = $this->trail(PropertyUnitOwnership::class);

        $this->assertNotNull($trail);
        $this->assertStringNotContainsString('Escritura 12345', json_encode($trail->after_data));
        $this->assertSame('[redacted]', $trail->after_data['document_reference']);
    }

    public function test_the_lease_snapshot_does_not_contain_the_tenant_document_reference(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        UnitLease::factory()->forComplex($complex)->create([
            'document_reference' => 'Contrato 98765',
        ]);

        $trail = $this->trail(UnitLease::class);

        $this->assertNotNull($trail);
        $this->assertStringNotContainsString('Contrato 98765', json_encode($trail->after_data));
        $this->assertSame('[redacted]', $trail->after_data['document_reference']);
    }

    /*
    |--------------------------------------------------------------------------
    | S104 (f): the migration, the backfill and the model that completes the
    | column on every new row.
    |--------------------------------------------------------------------------
    */

    public function test_the_migration_adds_the_complex_column_to_the_three_tables(): void
    {
        $this->assertTrue(Schema::hasColumn('building_floors', 'residential_complex_id'));
        $this->assertTrue(Schema::hasColumn('membership_roles', 'residential_complex_id'));
        $this->assertTrue(Schema::hasColumn('role_permissions', 'residential_complex_id'));

        foreach (['building_floors', 'membership_roles', 'role_permissions'] as $table) {
            $indexes = DB::select(
                'select indexname from pg_indexes where tablename = ?',
                [$table]
            );

            $this->assertContains(
                $table.'_residential_complex_id_index',
                array_column($indexes, 'indexname'),
                "The complex index of {$table} is missing."
            );
        }
    }

    public function test_new_rows_get_the_complex_from_the_model(): void
    {
        [, $complex] = $this->complexWithAdmin();

        $building = Building::factory()->create([
            'residential_complex_id' => $complex->id,
        ]);

        $floor = BuildingFloor::factory()->create(['building_id' => $building->id]);

        $this->assertSame($complex->id, (int) $floor->residential_complex_id);

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
        ]);

        $role = Role::factory()->complexLevel($complex)->create([
            'key' => 'policia_'.uniqid(),
            'name' => 'Policia '.uniqid(),
        ]);

        $assignment = MembershipRole::factory()
            ->forMembership($membership)
            ->forRole($role)
            ->create();

        $this->assertSame($complex->id, (int) $assignment->residential_complex_id);

        $permission = RolePermission::factory()->forRole($role)->create([
            'permission_key' => 'unit.view',
        ]);

        $this->assertSame($complex->id, (int) $permission->residential_complex_id);
    }

    public function test_the_backfill_fills_the_complex_from_the_relation(): void
    {
        [, $complex] = $this->complexWithAdmin();

        $building = Building::factory()->create(['residential_complex_id' => $complex->id]);
        $floor = BuildingFloor::factory()->create(['building_id' => $building->id]);

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
        ]);

        $role = Role::factory()->complexLevel($complex)->create([
            'key' => 'auditor_'.uniqid(),
            'name' => 'Auditor '.uniqid(),
        ]);

        $assignment = MembershipRole::factory()
            ->forMembership($membership)
            ->forRole($role)
            ->create();

        $permission = RolePermission::factory()->forRole($role)->create([
            'permission_key' => 'unit.view',
        ]);

        // Rows written before the migration (or outside the model) have no
        // complex: the backfill has to resolve it from the relation.
        DB::table('building_floors')->where('id', $floor->id)->update(['residential_complex_id' => null]);
        DB::table('membership_roles')->where('id', $assignment->id)->update(['residential_complex_id' => null]);
        DB::table('role_permissions')->where('id', $permission->id)->update(['residential_complex_id' => null]);

        $this->artisan('z360:audit-backfill')->assertSuccessful();

        $this->assertSame(
            $complex->id,
            (int) DB::table('building_floors')->where('id', $floor->id)->value('residential_complex_id')
        );
        $this->assertSame(
            $complex->id,
            (int) DB::table('membership_roles')->where('id', $assignment->id)->value('residential_complex_id')
        );
        $this->assertSame(
            $complex->id,
            (int) DB::table('role_permissions')->where('id', $permission->id)->value('residential_complex_id')
        );
    }

    public function test_the_backfill_leaves_the_catalog_permissions_without_complex(): void
    {
        $role = Role::factory()->create();

        $permission = RolePermission::factory()->forRole($role)->create([
            'permission_key' => 'unit.view',
        ]);

        $this->artisan('z360:audit-backfill')->assertSuccessful();

        $this->assertNull(
            DB::table('role_permissions')->where('id', $permission->id)->value('residential_complex_id')
        );
    }

    public function test_the_backfill_command_lists_the_orphans_it_cannot_resolve(): void
    {
        // The foreign key is dropped to simulate a row whose relation does
        // not exist anymore: that is the only way an orphan can appear.
        DB::statement('ALTER TABLE building_floors DROP CONSTRAINT building_floors_building_id_foreign');

        DB::table('building_floors')->insert([
            'uuid' => (string) Str::uuid(),
            'building_id' => 987654,
            'floor_number' => 77,
            'floor_type' => 'residential',
            'name' => null,
            'status' => 'active',
            'residential_complex_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orphanId = (int) DB::table('building_floors')->where('building_id', 987654)->value('id');

        $this->artisan('z360:audit-backfill')
            ->expectsOutputToContain('building_floors: id='.$orphanId)
            ->assertFailed();

        $this->assertNull(
            DB::table('building_floors')->where('id', $orphanId)->value('residential_complex_id')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The domains that were missing from the first delivery: the employments
    | and the physical structure that is not a building (the relations between
    | units and the exclusive use assignments).
    |--------------------------------------------------------------------------
    */

    public function test_an_employment_write_leaves_an_audit_trail(): void
    {
        [$admin, $complex] = $this->complexWithAdmin();

        $person = Person::factory()->create(['residential_complex_id' => $complex->id]);

        $employment = Employment::factory()->create([
            'residential_complex_id' => $complex->id,
            'person_id' => $person->id,
            'employment_type' => 'complex_staff',
            'position' => 'Portero',
        ]);

        $trail = $this->trail(Employment::class);

        $this->assertNotNull($trail);
        $this->assertSame($admin->id, (int) $trail->actor_user_id);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $employment->id, $trail->entity_id);
    }

    public function test_a_property_unit_relation_write_leaves_an_audit_trail(): void
    {
        [, $complex] = $this->complexWithAdmin();

        $source = PropertyUnit::factory()->create(['residential_complex_id' => $complex->id]);
        $target = PropertyUnit::factory()->create(['residential_complex_id' => $complex->id]);

        $relation = PropertyUnitRelation::create([
            'residential_complex_id' => $complex->id,
            'source_property_unit_id' => $source->id,
            'target_property_unit_id' => $target->id,
            'relation_type' => 'annex',
            'status' => 'active',
        ]);

        $trail = $this->trail(PropertyUnitRelation::class);

        $this->assertNotNull($trail);
        $this->assertSame($complex->id, (int) $trail->residential_complex_id);
        $this->assertSame((string) $relation->id, $trail->entity_id);
    }

    public function test_an_exclusive_use_assignment_leaves_a_redacted_trail(): void
    {
        [, $complex] = $this->complexWithAdmin();

        $area = CommonArea::create([
            'residential_complex_id' => $complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-'.uniqid(),
            'area_type' => 'other',
            'status' => 'active',
        ]);

        $unit = PropertyUnit::factory()->create(['residential_complex_id' => $complex->id]);

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'valid_until' => '2027-01-01',
            'document_reference' => 'ACTA-2026-001',
            'status' => 'active',
        ]);

        $trail = $this->trail(CommonPropertyExclusiveUseAssignment::class);

        $this->assertNotNull($trail);
        $this->assertSame((string) $assignment->id, $trail->entity_id);
        $this->assertSame('[redacted]', $trail->after_data['document_reference']);
        $this->assertStringNotContainsString('ACTA-2026-001', (string) json_encode($trail->after_data));
    }

    public function test_deleting_a_complex_does_not_break_the_audit_trail(): void
    {
        [, $complex] = $this->complexWithAdmin();

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
        ]);

        $role = Role::query()->where('key', SystemRole::DOORMAN->value)->firstOrFail();
        $membership->roles()->attach($role->id);

        // The trail of the complex goes with it: the cascade must not fail.
        $complex->delete();

        $this->assertDatabaseMissing('residential_complex_memberships', ['id' => $membership->id]);
    }
}
