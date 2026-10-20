<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnidadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'residential_complex_id' => $this->residential_complex_id,
            'residential_complex' => $this->whenLoaded('residentialComplex', function () {
                return [
                    'uuid' => $this->residentialComplex->uuid,
                    'name' => $this->residentialComplex->name,
                    'code' => $this->residentialComplex->code,
                ];
            }),
            'numero' => $this->numero,
            'piso' => $this->piso,
            'torre' => $this->torre,
            'tipo' => $this->tipo,
            'area_m2' => $this->area_m2,
            'habitaciones' => $this->habitaciones,
            'banos' => $this->banos,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'settings' => $this->settings ?? [],
            'personas' => $this->whenLoaded('personas', function () {
                return PersonaResource::collection($this->personas);
            }),
            'responsable' => $this->whenLoaded('personas', function () {
                $responsable = $this->getResponsable();
                return $responsable ? new PersonaResource($responsable) : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
