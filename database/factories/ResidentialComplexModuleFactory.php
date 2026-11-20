<?php

namespace Database\Factories;

use App\Enums\ComplexModule;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentialComplexModule>
 */
class ResidentialComplexModuleFactory extends Factory
{
    protected $model = ResidentialComplexModule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->randomElement(ComplexModule::cases());

        return [
            'residential_complex_id' => ResidentialComplex::factory(),
            'module_key' => $module->value,
            'enabled' => $module->isCore(),
            'settings' => $module->isCore() ? $module->defaultSettings() : null,
        ];
    }

    /**
     * Indicate that the module is enabled.
     */
    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => true,
        ]);
    }

    /**
     * Indicate that the module is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    /**
     * Set a specific module type.
     */
    public function ofType(ComplexModule $module): static
    {
        return $this->state(fn (array $attributes) => [
            'module_key' => $module->value,
            'enabled' => $module->isCore(),
            'settings' => $module->isCore() ? $module->defaultSettings() : null,
        ]);
    }
}
