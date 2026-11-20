<?php

namespace App\Models;

use App\Enums\OccupancyStatus;
use App\Enums\OwnershipStatus;
use App\Enums\PropertyUnitStatus;
use App\Enums\PropertyUnitType;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PropertyUnit extends Model
{
    use BelongsToComplex, HasFactory;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'building_id',
        'building_floor_id',
        'unit_type',
        'number',
        'internal_code',
        'area_m2',
        'coefficient',
        'status',
        'notes',
        'settings',
    ];

    protected $casts = [
        'unit_type' => PropertyUnitType::class,
        'status' => PropertyUnitStatus::class,
        'area_m2' => 'decimal:2',
        'coefficient' => 'decimal:6',
        'settings' => 'array',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (PropertyUnit $unit) {
            if (empty($unit->uuid)) {
                $unit->uuid = (string) Str::uuid();
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
     * Relations
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function buildingFloor(): BelongsTo
    {
        return $this->belongsTo(BuildingFloor::class);
    }

    /**
     * Extensión 1:1 opcional (solo para unit_type = apartment).
     */
    public function apartment(): HasOne
    {
        return $this->hasOne(Apartment::class);
    }

    /**
     * Historial de titularidad de la unidad (spec §4.3).
     */
    public function ownerships(): HasMany
    {
        return $this->hasMany(PropertyUnitOwnership::class);
    }

    /**
     * Arrendamientos de la unidad (spec §4.5).
     */
    public function leases(): HasMany
    {
        return $this->hasMany(UnitLease::class);
    }

    /**
     * Ocupaciones de la unidad (spec §4.4).
     */
    public function occupancies(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class);
    }

    /**
     * Empleos de la unidad como empleador (spec §4.6).
     */
    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class, 'employer_property_unit_id');
    }

    /**
     * Autorizaciones de gestion sobre la unidad (spec §4.8).
     */
    public function authorizations(): HasMany
    {
        return $this->hasMany(PropertyUnitAuthorization::class);
    }

    /**
     * Scope: active units
     */
    public function scopeActive($query)
    {
        return $query->where('status', PropertyUnitStatus::ACTIVE->value);
    }

    /**
     * Las unidades que le alcanzan a un actor (spec §6 "Alcance").
     *
     * El alcance no se deduce del permiso: una unidad le alcanza al actor
     * cuando tiene una titularidad vigente (§4.3), una ocupacion vigente
     * (§4.4) o una autorizacion vigente (§4.8, RN-P15) sobre ella.
     *
     * @param  Builder<PropertyUnit>  $query
     * @return Builder<PropertyUnit>
     */
    public function scopeForActor(Builder $query, User $user, int $complexId): Builder
    {
        // La titularidad y la ocupacion se guardan por dia de calendario
        // (columnas `date`), asi que su vigencia se compara por dia y no por
        // instante: el ultimo dia de una titularidad vigente sigue
        // contando. Las autorizaciones, en cambio, guardan instantes y las
        // resuelve su propio scope `effective` (RN-P15).
        $today = Carbon::today();

        return $query->forComplex($complexId)->where(function (Builder $units) use ($user, $complexId, $today) {
            $units->whereHas('ownerships', function (Builder $ownership) use ($user, $complexId, $today) {
                $ownership->where('residential_complex_id', $complexId)
                    ->where('status', OwnershipStatus::ACTIVE->value)
                    ->where('valid_from', '<=', $today)
                    ->where(fn (Builder $query) => $query->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', $today))
                    ->whereHas('person', fn (Builder $person) => $person->where('user_id', $user->id));
            })->orWhereHas('occupancies', function (Builder $occupancy) use ($user, $complexId, $today) {
                $occupancy->where('residential_complex_id', $complexId)
                    ->where('status', OccupancyStatus::ACTIVE->value)
                    ->where('valid_from', '<=', $today)
                    ->where(fn (Builder $query) => $query->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', $today))
                    ->whereHas('person', fn (Builder $person) => $person->where('user_id', $user->id));
            })->orWhereHas('authorizations', function (Builder $authorization) use ($user, $complexId) {
                $authorization->where('residential_complex_id', $complexId)
                    ->effective()
                    ->where(function (Builder $query) use ($user) {
                        $query->where('user_id', $user->id)
                            ->orWhereHas('person', fn (Builder $person) => $person->where('user_id', $user->id));
                    });
            });
        });
    }

    /**
     * Scope: by type
     */
    public function scopeOfType($query, string|PropertyUnitType $type)
    {
        $value = $type instanceof PropertyUnitType ? $type->value : $type;

        return $query->where('unit_type', $value);
    }
}
