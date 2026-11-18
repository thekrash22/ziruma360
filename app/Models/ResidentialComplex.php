<?php

namespace App\Models;

use App\Enums\ComplexModule;
use App\Enums\ResidentialComplexStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ResidentialComplex extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'legal_identifier',
        'address',
        'city',
        'state',
        'country_code',
        'timezone',
        'currency_code',
        'status',
        'settings',
    ];

    protected $casts = [
        'status' => ResidentialComplexStatus::class,
        'settings' => 'array',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (ResidentialComplex $complex) {
            if (empty($complex->uuid)) {
                $complex->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Un identificador de ruta que no es un UUID no puede existir: se
     * responde 404 en vez de dejar que PostgreSQL rechace la consulta con un
     * error de tipo (500).
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field === 'uuid' && ! Str::isUuid((string) $value)) {
            return $query->whereRaw('1 = 0');
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    /**
     * Check if the code can be changed.
     */
    public function canChangeCode(): bool
    {
        return $this->status === ResidentialComplexStatus::DRAFT;
    }

    /**
     * Check if the complex is writable (not suspended or archived).
     */
    public function isWritable(): bool
    {
        return $this->status->isWritable();
    }

    /**
     * Relations
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ResidentialComplexMembership::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ResidentialComplexModule::class);
    }

    /**
     * Check if a specific module is enabled for this complex.
     */
    public function hasModule(ComplexModule|string $module): bool
    {
        $key = $module instanceof ComplexModule ? $module->value : $module;
        return $this->modules()
            ->byModule($key)
            ->enabled()
            ->exists();
    }

    /**
     * Get an enabled module by key.
     */
    public function getModule(ComplexModule|string $module): ?ResidentialComplexModule
    {
        $key = $module instanceof ComplexModule ? $module->value : $module;
        return $this->modules()
            ->byModule($key)
            ->first();
    }

    /**
     * Enable a module with optional settings.
     */
    public function enableModule(ComplexModule $module, array $settings = []): ResidentialComplexModule
    {
        return $this->modules()->updateOrCreate(
            ['module_key' => $module->value],
            [
                'enabled' => true,
                'settings' => array_merge($module->defaultSettings(), $settings),
            ]
        );
    }

    /**
     * Disable a module.
     */
    public function disableModule(ComplexModule $module): void
    {
        $this->modules()
            ->byModule($module->value)
            ->update(['enabled' => false]);
    }
}
