<?php

namespace App\Models;

use App\Enums\OwnershipStatus;
use App\Enums\OwnershipType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Titularidad sobre una unidad (spec §4.3).
 *
 * Historial de cambios de dueño: RN-P05 la titularidad no se edita en sitio,
 * se cierra el registro vigente y se crea uno nuevo. RN-P04 la suma de los
 * porcentajes de las titularidades vigentes no puede superar 100.
 */
class PropertyUnitOwnership extends Model
{
    // La titularidad se audita: RN-C04 y el caso de §6 "Cambio de titularidad
    // genera historial y auditoría".
    use Auditable, BelongsToComplex, HasFactory;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'property_unit_id',
        'person_id',
        'ownership_type',
        'percentage',
        'valid_from',
        'valid_until',
        'status',
        'document_reference',
        'notes',
    ];

    protected $casts = [
        'ownership_type' => OwnershipType::class,
        'status' => OwnershipStatus::class,
        'percentage' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (PropertyUnitOwnership $ownership) {
            if (empty($ownership->uuid)) {
                $ownership->uuid = (string) Str::uuid();
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
     * The attributes that never reach an audit snapshot (spec S99).
     *
     * The document that supports the ownership is a documentary reference:
     * the trail keeps the structure of the change, never the value.
     *
     * @return list<string>
     */
    public function auditSensitiveAttributes(): array
    {
        return ['document_reference'];
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
}
