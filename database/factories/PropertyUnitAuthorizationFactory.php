<?php

namespace Database\Factories;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationType;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitAuthorization;
use App\Models\ResidentialComplex;
use App\Models\UnitLease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyUnitAuthorization>
 */
class PropertyUnitAuthorizationFactory extends Factory
{
    protected $model = PropertyUnitAuthorization::class;

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
            'user_id' => null,
            'lease_id' => null,
            'authorization_type' => AuthorizationType::DELEGATE,
            'scope' => [],
            'valid_from' => now()->toDateString(),
            'valid_until' => null,
            'status' => AuthorizationStatus::ACTIVE,
        ];
    }

    /**
     * Garantiza un estado valido por defecto: la unidad y la persona
     * pertenecen al mismo conjunto que la autorizacion (ACE-C02).
     *
     * La unidad y la persona se crean en afterMaking a proposito: la
     * autorizacion las referencia en el momento del insert (las FK son NOT
     * NULL), asi que los ids tienen que existir antes.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PropertyUnitAuthorization $authorization) {
            if (empty($authorization->property_unit_id)) {
                $authorization->property_unit_id = PropertyUnit::factory()
                    ->state(fn (array $attributes) => [
                        'residential_complex_id' => $authorization->residential_complex_id,
                    ])
                    ->create()
                    ->id;
            }

            if (empty($authorization->person_id)) {
                $authorization->person_id = Person::factory()
                    ->state(fn (array $attributes) => [
                        'residential_complex_id' => $authorization->residential_complex_id,
                    ])
                    ->create()
                    ->id;
            }
        });
    }

    /**
     * Autorizacion de un conjunto concreto.
     */
    public function forComplex(ResidentialComplex|int $complex): static
    {
        $complexId = $complex instanceof ResidentialComplex ? $complex->id : $complex;

        return $this->state(fn (array $attributes) => [
            'residential_complex_id' => $complexId,
        ]);
    }

    /**
     * Autorizacion sobre una unidad concreta.
     */
    public function forUnit(PropertyUnit|int $unit): static
    {
        return $this->state(fn (array $attributes) => [
            'property_unit_id' => $unit instanceof PropertyUnit ? $unit->id : $unit,
        ]);
    }

    /**
     * Autorizacion para una persona concreta.
     */
    public function forPerson(Person|int $person): static
    {
        return $this->state(fn (array $attributes) => [
            'person_id' => $person instanceof Person ? $person->id : $person,
        ]);
    }

    /**
     * Autorizacion dependiente de un arrendamiento.
     */
    public function forLease(UnitLease|int $lease): static
    {
        return $this->state(fn (array $attributes) => [
            'lease_id' => $lease instanceof UnitLease ? $lease->id : $lease,
        ]);
    }

    /**
     * Autorizacion con permisos concedidos.
     */
    public function withScope(array $scope): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => $scope,
        ]);
    }

    /**
     * Autorizacion de administrador de la unidad.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'authorization_type' => AuthorizationType::MANAGER,
        ]);
    }

    /**
     * Autorizacion revocada.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AuthorizationStatus::REVOKED,
            'revoked_at' => now(),
        ]);
    }
}
