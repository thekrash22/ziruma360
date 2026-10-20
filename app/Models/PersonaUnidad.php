<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaUnidad extends Model
{
    use HasFactory;

    protected $table = 'persona_unidad';

    protected $fillable = [
        'persona_id',
        'unidad_id',
        'tipo_relacion',
        'fecha_inicio',
        'fecha_fin',
        'es_principal',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'es_principal' => 'boolean',
    ];

    /**
     * Relations
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /**
     * Check if the relationship is currently active.
     */
    public function isActive(): bool
    {
        $today = now()->toDateString();
        
        if ($this->fecha_fin && $this->fecha_fin < $today) {
            return false;
        }

        return true;
    }

    /**
     * Scope: active relationships
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('fecha_fin')
              ->orWhere('fecha_fin', '>=', now()->toDateString());
        });
    }
}
