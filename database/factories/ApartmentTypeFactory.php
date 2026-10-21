<?php

namespace Database\Factories;

use App\Models\ResidentialComplex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApartmentType>
 */
class ApartmentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bedrooms = fake()->numberBetween(1, 5);

        return [
            'residential_complex_id' => ResidentialComplex::factory(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => "{$bedrooms} habitaciones",
            'area_m2' => fake()->randomFloat(2, 45, 250),
            'coefficient' => fake()->randomFloat(6, 0.001, 0.1),
            'features' => [
                'bedrooms' => $bedrooms,
                'bathrooms' => fake()->numberBetween(1, $bedrooms + 2),
                'balcony' => fake()->boolean(),
                'parking' => fake()->boolean(),
                'store' => fake()->boolean(),
            ],
            'status' => 'active',
        ];
    }

    /**
     * Set type by name pattern.
     */
    public function type(string $code, string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
            'name' => $name,
        ]);
    }
}
