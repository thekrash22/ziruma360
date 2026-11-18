<?php

namespace App\Models;

use App\Enums\PropertyUnitStatus;
use App\Enums\PropertyUnitType;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class PropertyUnit extends Model
{
    use HasFactory, BelongsToComplex;

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
     * Scope: active units
     */
    public function scopeActive($query)
    {
        return $query->where('status', PropertyUnitStatus::ACTIVE->value);
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
