<?php

namespace Database\Factories;

use App\Enums\BuildingStatus;
use App\Enums\BuildingType;
use App\Models\Building;
use App\Models\ResidentialComplex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(BuildingType::cases());
        $name = match ($type) {
            BuildingType::TOWER => 'Torre '.fake()->numberBetween(1, 10),
            BuildingType::BLOCK => 'Bloque '.fake()->randomLetter(),
            BuildingType::HOUSE_CLUSTER => 'Conjunto '.fake()->numberBetween(1, 5),
            default => 'Edificio '.fake()->numberBetween(1, 20),
        };

        return [
            'residential_complex_id' => ResidentialComplex::factory(),
            'name' => $name,
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'building_type' => $type,
            'floor_count' => fake()->numberBetween(3, 25),
            'apartments_per_floor_reference' => fake()->numberBetween(2, 8),
            'has_elevators' => fake()->boolean(70),
            'elevator_count' => null,
            'status' => BuildingStatus::ACTIVE,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the building has elevators.
     */
    public function withElevators(int $count = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'has_elevators' => true,
            'elevator_count' => $count,
        ]);
    }

    /**
     * Indicate that the building is a tower.
     */
    public function tower(): static
    {
        return $this->state(fn (array $attributes) => [
            'building_type' => BuildingType::TOWER,
        ]);
    }

    /**
     * Indicate that the building is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BuildingStatus::INACTIVE,
        ]);
    }
}
