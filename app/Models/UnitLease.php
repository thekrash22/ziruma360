<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Arrendamiento de largo plazo (vivienda) de una unidad (spec §4.5).
 *
 * RN-P09: al crearse el arrendamiento se crea o vincula la ocupación
 * `tenant_resident` del arrendatario. RN-P10: al terminar el arrendamiento
 * se cierra la ocupación asociada y se revocan las delegaciones del
 * arrendamiento.
 */
class UnitLease extends Model
{
    use BelongsToComplex, HasFactory;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'property_unit_id',
        'tenant_person_id',
        'landlord_person_id',
        'starts_on',
        'ends_on',
        'status',
        'document_reference',
        'delegated_permissions',
    ];

    protected $casts = [
        'status' => LeaseStatus::class,
        'delegated_permissions' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (UnitLease $lease) {
            if (empty($lease->uuid)) {
                $lease->uuid = (string) Str::uuid();
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
    public function propertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class);
    }

    public function tenantPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'tenant_person_id');
    }

    public function landlordPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'landlord_person_id');
    }

    /**
     * Ocupaciones vinculadas al arrendamiento (RN-P09).
     */
    public function occupancies(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class, 'lease_id');
    }
}
