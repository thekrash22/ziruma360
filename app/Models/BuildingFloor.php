<?php

namespace App\Models;

use App\Enums\FloorType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class BuildingFloor extends Model
{
    // The physical structure is audited (spec §1.6).
    use Auditable, HasFactory;

    protected $table = 'building_floors';

    protected $fillable = [
        'uuid',
        'residential_complex_id',
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

            // The complex of the floor is the complex of its building: the
            // model always completes the column so the trail always has a
            // complex (spec S98).
            $floor->residential_complex_id = $floor->resolveComplexId();

            if ($floor->residential_complex_id === null) {
                throw new LogicException(
                    'A building floor cannot be written without a resolvable complex: its audit trail would be lost silently.'
                );
            }
        });
    }

    /**
     * The complex of the building the floor belongs to.
     *
     * The building is read straight from the table: the floor can be written
     * outside the scope of the complex of its building.
     */
    public function resolveComplexId(): ?int
    {
        if ($this->building_id === null) {
            return null;
        }

        $complexId = DB::table('buildings')
            ->where('id', $this->building_id)
            ->value('residential_complex_id');

        return $complexId === null ? null : (int) $complexId;
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
