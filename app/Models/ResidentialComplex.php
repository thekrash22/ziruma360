<?php

namespace App\Models;

use App\Enums\ComplexModule;
use App\Enums\MembershipStatus;
use App\Enums\ResidentialComplexStatus;
use App\Models\Concerns\Auditable;
use App\Settings\ComplexSettingsCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ResidentialComplex extends Model
{
    use Auditable, HasFactory;

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
     * Las políticas del conjunto no son JSON libre (spec §1.4): se validan
     * contra el catálogo antes de persistirse.
     *
     * @param  mixed  $value
     */
    public function setSettingsAttribute($value): void
    {
        $settings = is_array($value)
            ? $value
            : (json_decode((string) $value, true) ?: []);

        ComplexSettingsCatalog::validate($settings);

        $this->attributes['settings'] = json_encode($settings, JSON_UNESCAPED_UNICODE);
    }

    /**
     * El conjunto es su propio contenedor de auditoría: su traza se borra
     * con él (FK en cascada), así que el borrado no deja registro.
     */
    public function auditRecordsDeletion(): bool
    {
        return false;
    }

    /**
     * El conjunto auditado es el propio conjunto.
     */
    public function auditComplexId(): ?int
    {
        return $this->id === null ? null : (int) $this->id;
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
     * Precondiciones de activacion (ACE-C03, spec S68).
     *
     * Un conjunto no pasa a `active` sin: (a) un administrador del conjunto
     * (membresia activa con rol `complex_admin` y asignacion de rol
     * vigente), (b) al menos un edificio o una unidad, y (c) las politicas
     * minimas completas (los cinco grupos del catalogo cerrado de §1.4).
     *
     * Devuelve una lista de textos legibles, uno por precondicion faltante:
     * vacia significa que el conjunto puede activarse.
     *
     * @return list<string>
     */
    public function activationBlockers(): array
    {
        $blockers = [];

        if (! $this->hasActiveComplexAdmin()) {
            $blockers[] = 'Falta un administrador del conjunto (membresía activa con rol complex_admin y asignación de rol vigente).';
        }

        if (! $this->hasPhysicalStructure()) {
            $blockers[] = 'Falta estructura física: el conjunto no tiene edificios ni unidades.';
        }

        $missingGroups = $this->missingPolicyGroups();

        if ($missingGroups !== []) {
            $blockers[] = 'Faltan las políticas mínimas del conjunto: grupos ausentes: '.implode(', ', $missingGroups).'.';
        }

        return $blockers;
    }

    /**
     * El conjunto tiene un administrador vigente.
     *
     * Misma semantica que `User::isComplexAdmin()`: la membresia esta
     * activa y la asignacion del rol `complex_admin` esta vigente
     * (`membership_roles.valid_from` y `valid_until`).
     */
    private function hasActiveComplexAdmin(): bool
    {
        return $this->memberships()
            ->where('status', MembershipStatus::ACTIVE->value)
            ->whereHas('roles', function (Builder $query) {
                $query->where('key', 'complex_admin')
                    ->where(fn (Builder $roles) => $roles
                        ->whereNull('membership_roles.valid_from')
                        ->orWhere('membership_roles.valid_from', '<=', now()))
                    ->where(fn (Builder $roles) => $roles
                        ->whereNull('membership_roles.valid_until')
                        ->orWhere('membership_roles.valid_until', '>=', now()));
            })
            ->exists();
    }

    /**
     * El conjunto tiene estructura fisica: al menos un edificio o una unidad.
     *
     * Las consultas ignoran el ambito global: la precondicion se evalua
     * sobre el conjunto, no sobre el conjunto activo de la sesion.
     */
    private function hasPhysicalStructure(): bool
    {
        return Building::withoutComplexScope()
            ->where('residential_complex_id', $this->id)
            ->exists()
            || PropertyUnit::withoutComplexScope()
                ->where('residential_complex_id', $this->id)
                ->exists();
    }

    /**
     * Grupos de politica del catalogo que el conjunto todavia no define.
     *
     * Un grupo presente pero vacio no define ninguna politica: no cuenta
     * como definido.
     *
     * @return list<string>
     */
    private function missingPolicyGroups(): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return array_values(array_filter(
            ComplexSettingsCatalog::groups(),
            fn (string $group): bool => ! isset($settings[$group])
                || ! is_array($settings[$group])
                || $settings[$group] === []
        ));
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
