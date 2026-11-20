<?php

namespace Database\Factories;

use App\Enums\OwnershipStatus;
use App\Enums\OwnershipType;
use App\Enums\PropertyUnitType;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitOwnership;
use App\Models\ResidentialComplex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyUnitOwnership>
 */
class PropertyUnitOwnershipFactory extends Factory
{
    protected $model = PropertyUnitOwnership::class;

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
            'ownership_type' => OwnershipType::OWNER,
            'percentage' => 100,
            'valid_from' => now()->toDateString(),
            'valid_until' => null,
            'status' => OwnershipStatus::ACTIVE,
            'document_reference' => null,
            'notes' => null,
        ];
    }

    /**
     * Guarantee a valid default state: the unit and the person always belong
     * to the same complex as the ownership (ACE-C02).
     *
     * The dependency rows are created in afterMaking on purpose: the
     * ownership row references an existing unit and person at insert time
     * (the foreign keys are NOT NULL), so the ids have to exist before the
     * insert. The deliberate trade-off is that make() also persists those two
     * dependencies; tests that only need an in-memory model must pass the
     * ids explicitly.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PropertyUnitOwnership $ownership) {
            if (empty($ownership->property_unit_id)) {
                $ownership->property_unit_id = PropertyUnit::factory()
                    ->state(fn (array $attributes) => [
                        'residential_complex_id' => $ownership->residential_complex_id,
                        'unit_type' => PropertyUnitType::APARTMENT,
                    ])
                    ->create()
                    ->id;
            }

            if (empty($ownership->person_id)) {
                $ownership->person_id = Person::factory()
                    ->state(fn (array $attributes) => [
                        'residential_complex_id' => $ownership->residential_complex_id,
                    ])
                    ->create()
                    ->id;
            }
        });
    }

    /**
     * Ownership of a given complex (unit and person of that complex).
     */
    public function forComplex(ResidentialComplex|int $complex): static
    {
        $complexId = $complex instanceof ResidentialComplex ? $complex->id : $complex;

        return $this->state(fn (array $attributes) => [
            'residential_complex_id' => $complexId,
        ]);
    }

    /**
     * Ownership of a given type.
     */
    public function ofType(OwnershipType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'ownership_type' => $type,
        ]);
    }

    /**
     * Closed ownership (RN-P05: se cierra el registro vigente).
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OwnershipStatus::CLOSED,
        ]);
    }

    /**
     * Ownership with a given percentage.
     */
    public function withPercentage(float $percentage): static
    {
        return $this->state(fn (array $attributes) => [
            'percentage' => $percentage,
        ]);
    }
}
