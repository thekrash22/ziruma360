<?php

namespace App\Models;

use App\Enums\OccupancyStatus;
use App\Enums\OccupancyType;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Ocupación de una unidad: quién habita o usa la unidad de forma continua
 * (spec §4.4), distinto de la titularidad (§4.3) y de las visitas.
 *
 * RN-P07: una unidad puede tener varios residentes y solo uno es contacto
 * principal vigente. RN-P08: un propietario no es automáticamente residente:
 * la ocupación se registra aparte. RN-P09: la ocupación con lease_id es la
 * del arrendatario del arrendamiento.
 */
class UnitOccupancy extends Model
{
    use BelongsToComplex, HasFactory;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'property_unit_id',
        'person_id',
        'occupancy_type',
        'is_primary_contact',
        'lease_id',
        'valid_from',
        'valid_until',
        'status',
    ];

    protected $casts = [
        'occupancy_type' => OccupancyType::class,
        'status' => OccupancyStatus::class,
        'is_primary_contact' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (UnitOccupancy $occupancy) {
            if (empty($occupancy->uuid)) {
                $occupancy->uuid = (string) Str::uuid();
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Arrendamiento del que proviene la ocupación (RN-P09), si lo hay.
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(UnitLease::class, 'lease_id');
    }
}
