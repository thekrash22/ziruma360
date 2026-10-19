<?php

namespace App\Models;

use App\Enums\ResidentialComplexStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ResidentialComplex extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'legal_identifier',
        'address',
        'city',
        'state',
        'country_code',
        'timezone',
        'currency_code',
        'status',
        'settings',
    ];

    protected $casts = [
        'status' => ResidentialComplexStatus::class,
        'settings' => 'array',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (ResidentialComplex $complex) {
            if (empty($complex->uuid)) {
                $complex->uuid = (string) Str::uuid();
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
     * Check if the code can be changed.
     */
    public function canChangeCode(): bool
    {
        return $this->status === ResidentialComplexStatus::DRAFT;
    }

    /**
     * Check if the complex is writable (not suspended or archived).
     */
    public function isWritable(): bool
    {
        return $this->status->isWritable();
    }

    /**
     * Relations
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ResidentialComplexMembership::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
