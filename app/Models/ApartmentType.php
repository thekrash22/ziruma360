<?php

namespace App\Models;

use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApartmentType extends Model
{
    use HasFactory, BelongsToComplex;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'code',
        'name',
        'area_m2',
        'coefficient',
        'features',
        'status',
    ];

    protected $casts = [
        'area_m2' => 'decimal:2',
        'coefficient' => 'decimal:6',
        'features' => 'array',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (ApartmentType $type) {
            if (empty($type->uuid)) {
                $type->uuid = (string) Str::uuid();
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
    public function residentialComplex(): BelongsTo
    {
        return $this->belongsTo(ResidentialComplex::class);
    }

    public function apartments(): HasMany
    {
        return $this->hasMany(Apartment::class);
    }

    /**
     * Scope: active types
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get a feature value.
     */
    public function getFeature(string $key, mixed $default = null): mixed
    {
        return data_get($this->features, $key, $default);
    }
}
