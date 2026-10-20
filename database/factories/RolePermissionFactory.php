<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RolePermission>
 */
class RolePermissionFactory extends Factory
{
    protected $model = RolePermission::class;

    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'permission_key' => $this->faker->randomElement([
                'unit.view',
                'unit.create',
                'person.view',
                'person.edit',
                'stay.view',
                'stay.create',
            ]),
        ];
    }

    public function forRole(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $role->id,
        ]);
    }
}
