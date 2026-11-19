<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Proyeccion minima de una persona (spec 6 "Privacidad").
 *
 * Es lo que ve porteria: con quien tiene que validar un acceso, nada mas.
 * Los datos personales (documento, contacto, nacimiento y notas) no se
 * exponen.
 */
class PersonMinimalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'full_name' => $this->full_name,
            'status' => $this->status?->value,
        ];
    }
}
