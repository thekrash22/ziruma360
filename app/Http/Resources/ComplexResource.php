<?php

namespace App\Http\Resources;

use App\Services\ComplexContext;
use Illuminate\Http\Request;

/**
 * Proyeccion de un conjunto en la superficie de conjuntos del actor.
 *
 * Es la proyeccion de `ResidentialComplexResource` mas una marca: si el
 * conjunto es el conjunto activo del actor (spec S92, el listado indica
 * cual es el conjunto activo).
 */
class ComplexResource extends ResidentialComplexResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['is_current'] = $this->isCurrentComplex();

        return $data;
    }

    /**
     * El conjunto es el conjunto activo del actor.
     */
    private function isCurrentComplex(): bool
    {
        $currentId = ComplexContext::getCurrentComplexId();

        return $currentId !== null && $currentId === (int) $this->id;
    }
}
