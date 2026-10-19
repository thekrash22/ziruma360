<?php

namespace Database\Seeders;

use App\Enums\ComplexPermission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class SystemRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolesWithPermissions = $this->getRolesWithPermissions();

        foreach ($rolesWithPermissions as $roleKey => $permissions) {
            $role = Role::updateOrCreate(
                [
                    'residential_complex_id' => null,
                    'key' => $roleKey,
                ],
                [
                    'name' => SystemRole::from($roleKey)->label(),
                    'is_system' => true,
                    'is_editable' => false,
                ]
            );

            // Sync permissions
            $role->permissions()->delete();
            foreach ($permissions as $permission) {
                RolePermission::create([
                    'role_id' => $role->id,
                    'permission_key' => $permission->value,
                ]);
            }
        }
    }

    /**
     * Define which permissions each system role gets.
     */
    private function getRolesWithPermissions(): array
    {
        return [
            SystemRole::PLATFORM_ADMIN->value => [
                // Full access
                ComplexPermission::COMPLEX_VIEW,
                ComplexPermission::COMPLEX_EDIT,
                ComplexPermission::COMPLEX_MANAGE_MEMBERS,
                ComplexPermission::COMPLEX_MANAGE_SETTINGS,
                ComplexPermission::COMPLEX_VIEW_AUDIT,
                ComplexPermission::BUILDING_VIEW,
                ComplexPermission::BUILDING_CREATE,
                ComplexPermission::BUILDING_EDIT,
                ComplexPermission::BUILDING_DELETE,
                ComplexPermission::FLOOR_VIEW,
                ComplexPermission::FLOOR_CREATE,
                ComplexPermission::FLOOR_EDIT,
                ComplexPermission::FLOOR_DELETE,
                ComplexPermission::UNIT_VIEW,
                ComplexPermission::UNIT_CREATE,
                ComplexPermission::UNIT_EDIT,
                ComplexPermission::UNIT_DELETE,
                ComplexPermission::PERSON_VIEW,
                ComplexPermission::PERSON_CREATE,
                ComplexPermission::PERSON_EDIT,
                ComplexPermission::PERSON_DELETE,
                ComplexPermission::OWNERSHIP_VIEW,
                ComplexPermission::OWNERSHIP_MANAGE,
                ComplexPermission::OCCUPANCY_VIEW,
                ComplexPermission::OCCUPANCY_MANAGE,
                ComplexPermission::EMPLOYMENT_VIEW,
                ComplexPermission::EMPLOYMENT_MANAGE_COMPLEX,
                ComplexPermission::EMPLOYMENT_MANAGE_UNIT,
                ComplexPermission::STAY_VIEW,
                ComplexPermission::STAY_CREATE,
                ComplexPermission::STAY_MANAGE,
                ComplexPermission::STAY_APPROVE,
                ComplexPermission::STAY_CHECKIN,
                ComplexPermission::ACCESS_REGISTER,
                ComplexPermission::ACCESS_VIEW,
                ComplexPermission::PARKING_MANAGE,
                ComplexPermission::PARKING_ASSIGN,
            ],

            SystemRole::COMPLEX_ADMIN->value => [
                ComplexPermission::COMPLEX_VIEW,
                ComplexPermission::COMPLEX_EDIT,
                ComplexPermission::COMPLEX_MANAGE_MEMBERS,
                ComplexPermission::COMPLEX_MANAGE_SETTINGS,
                ComplexPermission::COMPLEX_VIEW_AUDIT,
                ComplexPermission::BUILDING_VIEW,
                ComplexPermission::BUILDING_CREATE,
                ComplexPermission::BUILDING_EDIT,
                ComplexPermission::BUILDING_DELETE,
                ComplexPermission::FLOOR_VIEW,
                ComplexPermission::FLOOR_CREATE,
                ComplexPermission::FLOOR_EDIT,
                ComplexPermission::FLOOR_DELETE,
                ComplexPermission::UNIT_VIEW,
                ComplexPermission::UNIT_CREATE,
                ComplexPermission::UNIT_EDIT,
                ComplexPermission::UNIT_DELETE,
                ComplexPermission::PERSON_VIEW,
                ComplexPermission::PERSON_CREATE,
                ComplexPermission::PERSON_EDIT,
                ComplexPermission::PERSON_DELETE,
                ComplexPermission::OWNERSHIP_VIEW,
                ComplexPermission::OWNERSHIP_MANAGE,
                ComplexPermission::OCCUPANCY_VIEW,
                ComplexPermission::OCCUPANCY_MANAGE,
                ComplexPermission::EMPLOYMENT_VIEW,
                ComplexPermission::EMPLOYMENT_MANAGE_COMPLEX,
                ComplexPermission::EMPLOYMENT_MANAGE_UNIT,
                ComplexPermission::STAY_VIEW,
                ComplexPermission::STAY_APPROVE,
                ComplexPermission::ACCESS_REGISTER,
                ComplexPermission::ACCESS_VIEW,
                ComplexPermission::PARKING_MANAGE,
                ComplexPermission::PARKING_ASSIGN,
            ],

            SystemRole::DOORMAN->value => [
                ComplexPermission::COMPLEX_VIEW,
                ComplexPermission::PERSON_VIEW_MINIMAL,
                ComplexPermission::ACCESS_REGISTER,
                ComplexPermission::ACCESS_VIEW,
                ComplexPermission::STAY_CHECKIN,
            ],

            SystemRole::OWNER->value => [
                ComplexPermission::COMPLEX_VIEW,
                ComplexPermission::BUILDING_VIEW,
                ComplexPermission::FLOOR_VIEW,
                ComplexPermission::UNIT_VIEW,
                ComplexPermission::UNIT_MANAGE,
                ComplexPermission::PERSON_VIEW,
                ComplexPermission::OCCUPANCY_VIEW,
                ComplexPermission::EMPLOYMENT_VIEW,
                ComplexPermission::EMPLOYMENT_MANAGE_UNIT,
                ComplexPermission::STAY_VIEW,
                ComplexPermission::STAY_CREATE,
                ComplexPermission::STAY_MANAGE,
                ComplexPermission::ACCESS_VIEW,
            ],

            SystemRole::CO_OWNER->value => [
                ComplexPermission::COMPLEX_VIEW,
                ComplexPermission::BUILDING_VIEW,
                ComplexPermission::FLOOR_VIEW,
                ComplexPermission::UNIT_VIEW,
                ComplexPermission::PERSON_VIEW,
                ComplexPermission::OCCUPANCY_VIEW,
                ComplexPermission::ACCESS_VIEW,
            ],

            SystemRole::RESIDENT->value => [
                ComplexPermission::COMPLEX_VIEW,
                ComplexPermission::BUILDING_VIEW,
                ComplexPermission::FLOOR_VIEW,
                ComplexPermission::UNIT_VIEW,
                ComplexPermission::PERSON_VIEW,
                ComplexPermission::ACCESS_VIEW,
            ],

            SystemRole::TENANT->value => [
                ComplexPermission::COMPLEX_VIEW,
                ComplexPermission::BUILDING_VIEW,
                ComplexPermission::FLOOR_VIEW,
                ComplexPermission::UNIT_VIEW,
                ComplexPermission::PERSON_VIEW,
                ComplexPermission::ACCESS_VIEW,
                ComplexPermission::EMPLOYMENT_VIEW,
                ComplexPermission::STAY_VIEW,
                ComplexPermission::STAY_CREATE,
                ComplexPermission::STAY_MANAGE,
            ],

            SystemRole::HOST->value => [
                ComplexPermission::COMPLEX_VIEW,
                ComplexPermission::UNIT_VIEW,
                ComplexPermission::STAY_VIEW,
                ComplexPermission::STAY_CREATE,
                ComplexPermission::STAY_MANAGE,
                ComplexPermission::STAY_CHECKIN,
                ComplexPermission::ACCESS_VIEW,
            ],
        ];
    }
}
