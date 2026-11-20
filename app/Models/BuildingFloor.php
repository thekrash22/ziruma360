<?php

namespace App\Models;

use App\Enums\FloorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BuildingFloor extends Model
{
    use HasFactory;

    protected $table = 'building_floors';

    protected $fillable = [
        'uuid',
        'building_id',
        'floor_number',
        'floor_type',
        'name',
        'status',
    ];

    protected $casts = [
        'floor_type' => FloorType::class,
        'floor_number' => 'integer',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (BuildingFloor $floor) {
            if (empty($floor->uuid)) {
                $floor->uuid = (string) Str::uuid();
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
     * Get display name (name or floor number).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? "Piso {$this->floor_number}";
    }

    /**
     * Check if this is a basement floor.
     */
    public function isBasement(): bool
    {
        return $this->floor_number < 0;
    }

    /**
     * Relations
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class);
    }

    /**
     * Scope: residential floors only
     */
    public function scopeResidential($query)
    {
        return $query->where('floor_type', FloorType::RESIDENTIAL);
    }

    /**
     * Scope: above ground
     */
    public function scopeAboveGround($query)
    {
        return $query->where('floor_number', '>=', 0);
    }

    /**
     * Scope: basements only
     */
    public function scopeBasements($query)
    {
        return $query->where('floor_number', '<', 0);
    }

    /**
     * Scope to query floors within a specific complex.
     */
    public function scopeForComplex($query, ResidentialComplex|int $complex)
    {
        $complexId = $complex instanceof ResidentialComplex ? $complex->id : $complex;

        return $query->whereHas('building', function ($q) use ($complexId) {
            $q->where('residential_complex_id', $complexId);
        });
    }
}
