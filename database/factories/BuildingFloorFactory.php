<?php

namespace Database\Factories;

use App\Enums\FloorType;
use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BuildingFloor>
 */
class BuildingFloorFactory extends Factory
{
    protected $model = \App\Models\BuildingFloor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'floor_number' => fake()->numberBetween(-3, 20),
            'floor_type' => FloorType::RESIDENTIAL,
            'name' => null,
            'status' => 'active',
        ];
    }

    /**
     * Set floor number with type.
     */
    public function floorNumber(int $number): static
    {
        $type = $number < 0 ? FloorType::BASEMENT : FloorType::RESIDENTIAL;

        return $this->state(fn (array $attributes) => [
            'floor_number' => $number,
            'floor_type' => $type,
        ]);
    }

    /**
     * Basement floor.
     */
    public function basement(): static
    {
        return $this->state(fn (array $attributes) => [
            'floor_number' => -1,
            'floor_type' => FloorType::BASEMENT,
            'name' => 'Sótano',
        ]);
    }

    /**
     * Terrace floor.
     */
    public function terrace(): static
    {
        return $this->state(fn (array $attributes) => [
            'floor_number' => 99,
            'floor_type' => FloorType::TERRACE,
            'name' => 'Terraza',
        ]);
    }

    /**
     * Parking floor.
     */
    public function parking(): static
    {
        return $this->state(fn (array $attributes) => [
            'floor_number' => -1,
            'floor_type' => FloorType::PARKING,
            'name' => 'Parqueadero',
        ]);
    }
}
