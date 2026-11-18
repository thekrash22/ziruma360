<?php

namespace App\Observers;

use App\Enums\ComplexModule;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexModule;

class ResidentialComplexObserver
{
    /**
     * Handle the ResidentialComplex "created" event.
     */
    public function created(ResidentialComplex $complex): void
    {
        $this->seedDefaultModules($complex);
    }

    /**
     * Seed default modules for a new complex.
     * Core modules are enabled by default, optional modules are disabled.
     */
    private function seedDefaultModules(ResidentialComplex $complex): void
    {
        foreach (ComplexModule::cases() as $module) {
            $isEnabled = $module->isCore();

            ResidentialComplexModule::create([
                'residential_complex_id' => $complex->id,
                'module_key' => $module->value,
                'enabled' => $isEnabled,
                'settings' => $isEnabled ? $module->defaultSettings() : null,
            ]);
        }
    }
}
