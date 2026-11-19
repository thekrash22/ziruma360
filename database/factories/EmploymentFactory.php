<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Employment;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employment>
 */
class EmploymentFactory extends Factory
{
    protected $model = Employment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'residential_complex_id' => ResidentialComplex::factory(),
            'person_id' => null,
            'employer_property_unit_id' => null,
            'employment_type' => EmploymentType::COMPLEX_STAFF,
            'position' => null,
            'schedule' => null,
            'valid_from' => now()->toDateString(),
            'valid_until' => null,
            'status' => EmploymentStatus::ACTIVE,
            'access_authorized' => false,
        ];
    }

    /**
     * Garantiza un estado valido por defecto: la persona pertenece al mismo
     * conjunto que el empleo (ACE-C02) y el tipo por defecto es el personal
     * del conjunto, que no tiene unidad empleadora.
     *
     * La persona se crea en afterMaking a proposito: el empleo la referencia
     * en el momento del insert (la FK es NOT NULL), asi que el id tiene que
     * existir antes.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Employment $employment) {
            if (empty($employment->person_id)) {
                $employment->person_id = Person::factory()
                    ->state(fn (array $attributes) => [
                        'residential_complex_id' => $employment->residential_complex_id,
                    ])
                    ->create()
                    ->id;
            }
        });
    }

    /**
     * Empleo de un conjunto concreto.
     */
    public function forComplex(ResidentialComplex|int $complex): static
    {
        $complexId = $complex instanceof ResidentialComplex ? $complex->id : $complex;

        return $this->state(fn (array $attributes) => [
            'residential_complex_id' => $complexId,
        ]);
    }

    /**
     * Empleo de una persona concreta.
     */
    public function forPerson(Person|int $person): static
    {
        return $this->state(fn (array $attributes) => [
            'person_id' => $person instanceof Person ? $person->id : $person,
        ]);
    }

    /**
     * Personal del conjunto: sin unidad empleadora.
     */
    public function complexStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::COMPLEX_STAFF,
            'employer_property_unit_id' => null,
        ]);
    }

    /**
     * Empleado domestico de una unidad.
     */
    public function forUnit(PropertyUnit|int $unit): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::UNIT_DOMESTIC,
            'employer_property_unit_id' => $unit instanceof PropertyUnit ? $unit->id : $unit,
        ]);
    }

    /**
     * Contratista con acceso recurrente.
     */
    public function contractor(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::CONTRACTOR,
        ]);
    }

    /**
     * Empleo con horario (JSONB validado: objeto).
     */
    public function withSchedule(array $schedule): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule' => $schedule,
        ]);
    }

    /**
     * Empleo finalizado.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmploymentStatus::ENDED,
        ]);
    }
}
