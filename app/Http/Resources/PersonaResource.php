<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonaResource extends JsonResource
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
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'nombre_completo' => $this->nombre_completo,
            'nombre_corto' => $this->nombre_corto,
            'tipo_documento' => $this->tipo_documento->value,
            'tipo_documento_label' => $this->tipo_documento->label(),
            'numero_documento' => $this->numero_documento,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'telefono_alternativo' => $this->telefono_alternativo,
            'has_user_account' => $this->hasUserAccount(),
            'user_id' => $this->user_id,
            'notas' => $this->notas,
            'settings' => $this->settings ?? [],
            'unidades' => $this->whenLoaded('unidades', function () {
                return UnidadResource::collection($this->unidades);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
