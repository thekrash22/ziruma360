<?php

namespace Database\Factories;

use App\Enums\LeaseStatus;
use App\Enums\PropertyUnitType;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use App\Models\UnitLease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitLease>
 */
class UnitLeaseFactory extends Factory
{
    protected $model = UnitLease::class;

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
            'tenant_person_id' => null,
            'landlord_person_id' => null,
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
            'status' => LeaseStatus::ACTIVE,
            'document_reference' => null,
            'delegated_permissions' => null,
        ];
    }

    /**
     * Guarantee a valid default state: the unit and the tenant always belong
     * to the same complex as the lease (ACE-C02).
     *
     * The dependency rows are created in afterMaking on purpose: the lease
     * references an existing unit and person at insert time (the foreign keys
     * are NOT NULL), so the ids have to exist before the insert.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (UnitLease $lease) {
            if (empty($lease->property_unit_id)) {
                $lease->property_unit_id = PropertyUnit::factory()
                    ->state(fn (array $attributes) => [
                        'residential_complex_id' => $lease->residential_complex_id,
                        'unit_type' => PropertyUnitType::APARTMENT,
                    ])
                    ->create()
                    ->id;
            }

            if (empty($lease->tenant_person_id)) {
                $lease->tenant_person_id = Person::factory()
                    ->state(fn (array $attributes) => [
                        'residential_complex_id' => $lease->residential_complex_id,
                    ])
                    ->create()
                    ->id;
            }
        });
    }

    /**
     * Lease of a given complex (unit and tenant of that complex).
     */
    public function forComplex(ResidentialComplex|int $complex): static
    {
        $complexId = $complex instanceof ResidentialComplex ? $complex->id : $complex;

        return $this->state(fn (array $attributes) => [
            'residential_complex_id' => $complexId,
        ]);
    }

    /**
     * Lease with a landlord (the owner who rents out the unit).
     */
    public function withLandlord(Person|int $landlord): static
    {
        return $this->state(fn (array $attributes) => [
            'landlord_person_id' => $landlord instanceof Person ? $landlord->id : $landlord,
        ]);
    }

    /**
     * Lease with delegated permissions (JSONB validated).
     */
    public function withDelegatedPermissions(array $permissions): static
    {
        return $this->state(fn (array $attributes) => [
            'delegated_permissions' => $permissions,
        ]);
    }

    /**
     * Lease that already finished (RN-P10).
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaseStatus::ENDED,
        ]);
    }

    /**
     * Lease that was terminated (RN-P10).
     */
    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaseStatus::TERMINATED,
        ]);
    }
}
