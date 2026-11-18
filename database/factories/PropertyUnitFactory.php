<?php

namespace Database\Factories;

use App\Enums\PropertyUnitStatus;
use App\Enums\PropertyUnitType;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropertyUnit>
 */
class PropertyUnitFactory extends Factory
{
    protected $model = PropertyUnit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'residential_complex_id' => ResidentialComplex::factory(),
            'building_id' => null,
            'building_floor_id' => null,
            'unit_type' => PropertyUnitType::APARTMENT,
            'number' => strtoupper(fake()->unique()->bothify('U-####')),
            'internal_code' => null,
            'area_m2' => fake()->randomFloat(2, 30, 300),
            'coefficient' => fake()->randomFloat(6, 0.001, 0.1),
            'status' => PropertyUnitStatus::ACTIVE,
            'notes' => null,
            'settings' => null,
        ];
    }

    /**
     * Set the unit type.
     */
    public function ofType(PropertyUnitType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_type' => $type,
        ]);
    }

    /**
     * Unit without building (e.g. private parking in an outdoor lot).
     */
    public function withoutBuilding(): static
    {
        return $this->state(fn (array $attributes) => [
            'building_id' => null,
            'building_floor_id' => null,
        ]);
    }

    /**
     * Inactive unit (RN-S04: units are deactivated, not deleted).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PropertyUnitStatus::INACTIVE,
        ]);
    }
}
