<?php

namespace Database\Factories;

use App\Enums\PersonStatus;
use App\Enums\TipoDocumento;
use App\Models\Person;
use App\Models\ResidentialComplex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'residential_complex_id' => ResidentialComplex::factory(),
            'user_id' => null,
            'full_name' => fake()->name(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_type' => fake()->randomElement(TipoDocumento::cases()),
            'document_number' => fake()->unique()->numerify('##########'),
            'phone' => fake()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'status' => PersonStatus::ACTIVE,
            'data_consent_at' => null,
            'data_consent_ref' => null,
            'notes' => null,
        ];
    }

    /**
     * Person of a given complex.
     */
    public function forComplex(ResidentialComplex|int $complex): static
    {
        $complexId = $complex instanceof ResidentialComplex ? $complex->id : $complex;

        return $this->state(fn (array $attributes) => [
            'residential_complex_id' => $complexId,
        ]);
    }

    /**
     * Person without access account (RN-P06: ser titular no da acceso).
     */
    public function withoutUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    /**
     * Person with a given document (RN-P02).
     */
    public function withDocument(TipoDocumento|string $documentType, string $documentNumber): static
    {
        $type = $documentType instanceof TipoDocumento ? $documentType->value : $documentType;

        return $this->state(fn (array $attributes) => [
            'document_type' => $type,
            'document_number' => $documentNumber,
        ]);
    }

    /**
     * Inactive person (RN-S04: se desactiva, no se borra).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PersonStatus::INACTIVE,
        ]);
    }
}
