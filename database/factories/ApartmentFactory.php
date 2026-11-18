<?php

namespace Database\Factories;

use App\Enums\ApartmentOperationalStatus;
use App\Enums\PropertyUnitType;
use App\Models\Apartment;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Apartment>
 */
class ApartmentFactory extends Factory
{
    protected $model = Apartment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'residential_complex_id' => ResidentialComplex::factory(),
            'property_unit_id' => null,
            'apartment_type_id' => null,
            'bedrooms' => fake()->numberBetween(1, 5),
            'bathrooms' => fake()->numberBetween(1, 3),
            'operational_status' => ApartmentOperationalStatus::VACANT,
            'short_term_rental_enabled' => false,
            'features' => null,
        ];
    }

    /**
     * Guarantee a valid default state: an apartment always hangs from a
     * property unit of the same complex and of type apartment (TEC-S01).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Apartment $apartment) {
            if (empty($apartment->property_unit_id)) {
                $apartment->property_unit_id = PropertyUnit::factory()
                    ->state(fn (array $attributes) => [
                        'residential_complex_id' => $apartment->residential_complex_id,
                        'unit_type' => PropertyUnitType::APARTMENT,
                    ])
                    ->create()
                    ->id;
            }
        });
    }

    /**
     * Occupied apartment.
     */
    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'operational_status' => ApartmentOperationalStatus::OCCUPIED,
        ]);
    }

    /**
     * Apartment with short term rental enabled.
     */
    public function shortTermRental(): static
    {
        return $this->state(fn (array $attributes) => [
            'short_term_rental_enabled' => true,
        ]);
    }
}
