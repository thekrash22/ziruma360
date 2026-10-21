<?php

namespace App\Models;

use App\Enums\BuildingStatus;
use App\Models\Concerns\BelongsToComplex;
use App\Enums\BuildingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Building extends Model
{
    use HasFactory, BelongsToComplex;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'name',
        'code',
        'building_type',
        'floor_count',
        'apartments_per_floor_reference',
        'has_elevators',
        'elevator_count',
        'status',
        'metadata',
    ];

    protected $casts = [
        'building_type' => BuildingType::class,
        'status' => BuildingStatus::class,
        'has_elevators' => 'boolean',
        'floor_count' => 'integer',
        'apartments_per_floor_reference' => 'integer',
        'elevator_count' => 'integer',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Building $building) {
            if (empty($building->uuid)) {
                $building->uuid = (string) Str::uuid();
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
     * Check if the building is writable.
     */
    public function isWritable(): bool
    {
        return $this->status->isWritable();
    }

    /**
     * Relations
     */
    public function residentialComplex(): BelongsTo
    {
        return $this->belongsTo(ResidentialComplex::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(BuildingFloor::class)->orderBy('floor_number');
    }

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class);
    }

    /**
     * Scope: active buildings
     */
    public function scopeActive($query)
    {
        return $query->where('status', BuildingStatus::ACTIVE);
    }

    /**
     * Scope: by type
     */
    public function scopeOfType($query, BuildingType $type)
    {
        return $query->where('building_type', $type);
    }

    /**
     * Get residential floors only.
     */
    public function getResidentialFloors(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->floors()
            ->where('floor_type', 'residential')
            ->get();
    }
}
