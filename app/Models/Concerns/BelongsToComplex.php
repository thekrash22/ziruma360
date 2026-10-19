<?php

namespace App\Models\Concerns;

use App\Models\ResidentialComplex;
use App\Services\ComplexContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait para modelos que pertenecen a un conjunto residencial.
 * Añade global scope que filtra automáticamente por el conjunto activo.
 */
trait BelongsToComplex
{
    /**
     * Boot the trait.
     */
    public static function bootBelongsToComplex(): void
    {
        // Solo aplicar el global scope si estamos en un contexto web/API
        // y hay un conjunto activo en la sesión
        static::addGlobalScope('complex', function (Builder $builder) {
            if (app()->runningInConsole() && !app()->runningUnitTests()) {
                return;
            }

            $complexId = ComplexContext::getCurrentComplexId();
            if ($complexId !== null) {
                $builder->where('residential_complex_id', $complexId);
            }
        });

        // Automatically set residential_complex_id on creating
        static::creating(function ($model) {
            if (empty($model->residential_complex_id)) {
                $complexId = ComplexContext::getCurrentComplexId();
                if ($complexId !== null) {
                    $model->residential_complex_id = $complexId;
                }
            }
        });
    }

    /**
     * Initialize the trait - for models that use Fillable attribute.
     */
    public function initializeBelongsToComplex(): void
    {
        // Add residential_complex_id to fillable if not already present
        if (!in_array('residential_complex_id', $this->fillable ?? [])) {
            $this->fillable[] = 'residential_complex_id';
        }
    }

    /**
     * Get the complex this model belongs to.
     */
    public function residentialComplex(): BelongsTo
    {
        return $this->belongsTo(ResidentialComplex::class);
    }

    /**
     * Scope to query without the complex filter (use with caution).
     */
    public function scopeWithoutComplexScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('complex');
    }

    /**
     * Scope to query within a specific complex.
     */
    public function scopeInComplex(Builder $query, int $complexId): Builder
    {
        return $query->withoutGlobalScope('complex')
            ->where('residential_complex_id', $complexId);
    }
}
