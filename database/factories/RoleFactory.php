<?php

namespace Database\Factories;

use App\Enums\SystemRole;
use App\Models\ResidentialComplex;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'residential_complex_id' => null,
            'key' => 'custom_role_' . $this->faker->unique()->numberBetween(1, 9999),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'is_system' => false,
            'is_editable' => true,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
            'is_editable' => false,
        ]);
    }

    public function complexLevel(ResidentialComplex $complex): static
    {
        return $this->state(fn (array $attributes) => [
            'residential_complex_id' => $complex->id,
        ]);
    }

    public function forSystemRole(SystemRole $role): static
    {
        return $this->state(fn (array $attributes) => [
            'residential_complex_id' => null,
            'key' => $role->value,
            'name' => $role->label(),
            'is_system' => true,
            'is_editable' => false,
        ]);
    }
}
