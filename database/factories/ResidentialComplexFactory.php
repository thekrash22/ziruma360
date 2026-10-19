<?php

namespace Database\Factories;

use App\Enums\ResidentialComplexStatus;
use App\Models\ResidentialComplex;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ResidentialComplex>
 */
class ResidentialComplexFactory extends Factory
{
    protected $model = ResidentialComplex::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company() . ' Residencial';

        return [
            'uuid' => fake()->uuid(),
            'name' => $name,
            'code' => Str::slug($name) . '-' . fake()->unique()->randomNumber(4),
            'legal_identifier' => fake()->numerify('##########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country_code' => 'CO',
            'timezone' => 'America/Bogota',
            'currency_code' => 'COP',
            'status' => ResidentialComplexStatus::DRAFT,
            'settings' => [],
        ];
    }

    /**
     * Indicate that the complex is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ResidentialComplexStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the complex is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ResidentialComplexStatus::DRAFT,
        ]);
    }
}
