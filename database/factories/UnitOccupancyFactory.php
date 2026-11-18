<?php

namespace Database\Factories;

use App\Enums\OccupancyStatus;
use App\Enums\OccupancyType;
use App\Enums\PropertyUnitType;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use App\Models\UnitLease;
use App\Models\UnitOccupancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnitOccupancy>
 */
class UnitOccupancyFactory extends Factory
{
    protected $model = UnitOccupancy::class;

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
            'person_id' => null,
            'occupancy_type' => OccupancyType::OTHER_RESIDENT,
            'is_primary_contact' => false,
            'lease_id' => null,
            'valid_from' => now()->toDateString(),
            'valid_until' => null,
            'status' => OccupancyStatus::ACTIVE,
        ];
    }

    /**
     * Guarantee a valid default state: the unit and the person always belong
     * to the same complex as the occupancy (ACE-C02).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (UnitOccupancy $occupancy) {
            if (empty($occupancy->property_unit_id)) {
                $occupancy->property_unit_id = PropertyUnit::factory()
                    ->state(fn (array $attributes) => [
                        'residential_complex_id' => $occupancy->residential_complex_id,
                        'unit_type' => PropertyUnitType::APARTMENT,
                    ])
                    ->create()
                    ->id;
            }

            if (empty($occupancy->person_id)) {
                $occupancy->person_id = Person::factory()
                    ->state(fn (array $attributes) => [
                        'residential_complex_id' => $occupancy->residential_complex_id,
                    ])
                    ->create()
                    ->id;
            }
        });
    }

    /**
     * Occupancy of a given complex (unit and person of that complex).
     */
    public function forComplex(ResidentialComplex|int $complex): static
    {
        $complexId = $complex instanceof ResidentialComplex ? $complex->id : $complex;

        return $this->state(fn (array $attributes) => [
            'residential_complex_id' => $complexId,
        ]);
    }

    /**
     * Occupancy of a given type.
     */
    public function ofType(OccupancyType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'occupancy_type' => $type,
        ]);
    }

    /**
     * Occupancy that is the primary contact of the unit (RN-P07).
     */
    public function primaryContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary_contact' => true,
        ]);
    }

    /**
     * Closed occupancy.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OccupancyStatus::CLOSED,
        ]);
    }

    /**
     * Occupancy that comes from a lease (RN-P09): it is the occupancy of the
     * tenant, in the leased unit.
     */
    public function forLease(UnitLease $lease): static
    {
        return $this->state(fn (array $attributes) => [
            'residential_complex_id' => $lease->residential_complex_id,
            'property_unit_id' => $lease->property_unit_id,
            'person_id' => $lease->tenant_person_id,
            'occupancy_type' => OccupancyType::TENANT_RESIDENT,
            'lease_id' => $lease->id,
            'valid_from' => $lease->starts_on,
        ]);
    }
}
