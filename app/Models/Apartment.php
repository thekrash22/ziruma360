<?php

namespace App\Models;

use App\Enums\ApartmentOperationalStatus;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Especialización 1:1 de una property_unit (spec §3.6).
 *
 * La unidad base debe ser de tipo apartment (TEC-S01, garantizado por trigger
 * en base de datos).
 */
class Apartment extends Model
{
    use HasFactory, BelongsToComplex;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'property_unit_id',
        'apartment_type_id',
        'bedrooms',
        'bathrooms',
        'operational_status',
        'short_term_rental_enabled',
        'features',
    ];

    protected $casts = [
        'operational_status' => ApartmentOperationalStatus::class,
        'short_term_rental_enabled' => 'boolean',
        'features' => 'array',
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Apartment $apartment) {
            if (empty($apartment->uuid)) {
                $apartment->uuid = (string) Str::uuid();
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

    public function propertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class);
    }

    public function apartmentType(): BelongsTo
    {
        return $this->belongsTo(ApartmentType::class);
    }
}
