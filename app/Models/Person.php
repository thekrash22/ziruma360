<?php

namespace App\Models;

use App\Enums\PersonStatus;
use App\Enums\TipoDocumento;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Persona (spec §4.2).
 *
 * Identidad por conjunto residencial: la misma persona puede ser propietaria
 * de una unidad, residente en otra y visitante en el futuro. `user_id` es
 * nullable porque Plenty de personas no tienen cuenta de acceso.
 */
class Person extends Model
{
    use HasFactory, BelongsToComplex;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'user_id',
        'full_name',
        'first_name',
        'last_name',
        'document_type',
        'document_number',
        'phone',
        'email',
        'birth_date',
        'status',
        'data_consent_at',
        'data_consent_ref',
        'notes',
    ];

    protected $casts = [
        'document_type' => TipoDocumento::class,
        'status' => PersonStatus::class,
        'birth_date' => 'date',
        'data_consent_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Person $person) {
            if (empty($person->uuid)) {
                $person->uuid = (string) Str::uuid();
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(PropertyUnitOwnership::class);
    }

    /**
     * Arrendamientos en los que la persona es arrendataria (spec §4.5).
     */
    public function leases(): HasMany
    {
        return $this->hasMany(UnitLease::class, 'tenant_person_id');
    }

    /**
     * Ocupaciones de la persona (spec §4.4).
     */
    public function occupancies(): HasMany
    {
        return $this->hasMany(UnitOccupancy::class);
    }

    /**
     * Empleos de la persona (spec §4.6).
     */
    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }

    /**
     * Scope: personas con un documento concreto (tipo + número).
     *
     * RN-P02: antes de crear una persona se busca por documento; si existe
     * en el conjunto, se reutiliza (sin duplicar información).
     */
    public function scopeForDocument(Builder $query, TipoDocumento|string $documentType, string $documentNumber): Builder
    {
        $type = $documentType instanceof TipoDocumento ? $documentType->value : $documentType;

        return $query->where('document_type', $type)
            ->where('document_number', $documentNumber);
    }

    /**
     * Busca una persona por documento dentro de un conjunto (RN-P02).
     *
     * RN-P02 (deduplicación por documento): antes de crear una persona se
     * busca por tipo y número de documento en el conjunto; si ya existe, se
     * reutiliza ese registro en lugar de duplicar la información.
     */
    public static function findByDocument(
        ResidentialComplex|int $complex,
        TipoDocumento|string $documentType,
        string $documentNumber
    ): ?self {
        $complexId = $complex instanceof ResidentialComplex ? $complex->id : $complex;
        $type = $documentType instanceof TipoDocumento ? $documentType->value : $documentType;

        return static::query()
            ->withoutGlobalScope('complex')
            ->where('residential_complex_id', $complexId)
            ->where('document_type', $type)
            ->where('document_number', $documentNumber)
            ->first();
    }
}
