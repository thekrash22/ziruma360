<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Persona extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'nombre',
        'apellido',
        'tipo_documento',
        'numero_documento',
        'email',
        'telefono',
        'telefono_alternativo',
        'notas',
        'settings',
    ];

    protected $casts = [
        'tipo_documento' => TipoDocumento::class,
        'settings' => 'array',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Persona $persona) {
            if (empty($persona->uuid)) {
                $persona->uuid = (string) Str::uuid();
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
     * Get full name.
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }

    /**
     * Get short name (first name + first letter of last name).
     */
    public function getNombreCortoAttribute(): string
    {
        return "{$this->nombre} " . substr($this->apellido, 0, 1) . ".";
    }

    /**
     * Relations
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidad::class, 'persona_unidad')
            ->withPivot(['tipo_relacion', 'fecha_inicio', 'fecha_fin', 'es_principal'])
            ->withTimestamps();
    }

    public function unidadesRelaciones(): HasMany
    {
        return $this->hasMany(PersonaUnidad::class);
    }

    /**
     * Get unidades by relation type.
     */
    public function getUnidadesByRelacion(string $tipo): \Illuminate\Database\Eloquent\Collection
    {
        return $this->unidades()
            ->wherePivot('tipo_relacion', $tipo)
            ->get();
    }

    /**
     * Check if persona has a linked user account.
     */
    public function hasUserAccount(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Scope: by document type
     */
    public function scopeByTipoDocumento($query, TipoDocumento $tipo)
    {
        return $query->where('tipo_documento', $tipo);
    }

    /**
     * Scope: search by name or document number
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->whereRaw('lower(nombre) like ?', ['%' . strtolower($search) . '%'])
              ->orWhereRaw('lower(apellido) like ?', ['%' . strtolower($search) . '%'])
              ->orWhere('numero_documento', 'like', '%' . $search . '%');
        });
    }
}
