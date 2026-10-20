<?php

namespace App\Models;

use App\Enums\UnidadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Unidad extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'numero',
        'piso',
        'torre',
        'tipo',
        'area_m2',
        'habitaciones',
        'banos',
        'status',
        'settings',
    ];

    protected $casts = [
        'status' => UnidadStatus::class,
        'settings' => 'array',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Unidad $unidad) {
            if (empty($unidad->uuid)) {
                $unidad->uuid = (string) Str::uuid();
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
     * Check if the unit is writable.
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

    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'persona_unidad')
            ->withPivot(['tipo_relacion', 'fecha_inicio', 'fecha_fin', 'es_principal'])
            ->withTimestamps();
    }

    public function personasRelaciones(): HasMany
    {
        return $this->hasMany(PersonaUnidad::class);
    }

    /**
     * Get personas by relation type.
     */
    public function getPersonasByRelacion(string $tipo): \Illuminate\Database\Eloquent\Collection
    {
        return $this->personas()
            ->wherePivot('tipo_relacion', $tipo)
            ->get();
    }

    /**
     * Get the primary occupant (responsable).
     */
    public function getResponsable(): ?Persona
    {
        return $this->personas()
            ->wherePivot('tipo_relacion', 'responsable')
            ->wherePivot('es_principal', true)
            ->first();
    }

    /**
     * Scope: active units
     */
    public function scopeActive($query)
    {
        return $query->where('status', UnidadStatus::ACTIVE);
    }

    /**
     * Scope: by tower
     */
    public function scopeByTorre($query, string $torre)
    {
        return $query->where('torre', $torre);
    }

    /**
     * Scope: by floor
     */
    public function scopeByPiso($query, int $piso)
    {
        return $query->where('piso', $piso);
    }
}
