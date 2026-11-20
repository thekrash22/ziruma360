<?php

namespace App\Models;

use App\Enums\ComplexModule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentialComplexModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'residential_complex_id',
        'module_key',
        'enabled',
        'settings',
    ];

    protected $casts = [
        'module_key' => ComplexModule::class,
        'enabled' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the parent residential complex.
     */
    public function residentialComplex(): BelongsTo
    {
        return $this->belongsTo(ResidentialComplex::class);
    }

    /**
     * Check if the module is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable the module.
     */
    public function enable(): void
    {
        $this->update(['enabled' => true]);
    }

    /**
     * Disable the module.
     */
    public function disable(): void
    {
        $this->update(['enabled' => false]);
    }

    /**
     * Get a setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Update a specific setting.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * Scope: only enabled modules.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: by module key.
     */
    public function scopeByModule($query, ComplexModule|string $module)
    {
        $key = $module instanceof ComplexModule ? $module->value : $module;

        return $query->where('module_key', $key);
    }
}
