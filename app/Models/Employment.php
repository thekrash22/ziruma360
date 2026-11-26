<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Empleo (spec §4.6).
 *
 * Persona que trabaja para el conjunto (`complex_staff`), para una unidad
 * (`unit_domestic`) o como contratista con acceso recurrente
 * (`contractor`).
 *
 * RN-P11: tener empleo no implica tener cuenta de acceso, ni al revés. Si
 * el empleo autoriza acceso al sistema, la persona necesita cuenta
 * (`people.user_id`) y una membresía activa en el conjunto; esa garantía
 * vive en la base de datos.
 */
class Employment extends Model
{
    use Auditable, BelongsToComplex, HasFactory;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'person_id',
        'employer_property_unit_id',
        'employment_type',
        'position',
        'schedule',
        'valid_from',
        'valid_until',
        'status',
        'access_authorized',
    ];

    protected $casts = [
        'employment_type' => EmploymentType::class,
        'status' => EmploymentStatus::class,
        'schedule' => 'array',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'access_authorized' => 'boolean',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Employment $employment) {
            if (empty($employment->uuid)) {
                $employment->uuid = (string) Str::uuid();
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
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Unidad empleadora (nula para el personal del conjunto).
     */
    public function employerPropertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'employer_property_unit_id');
    }
}
