<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Proyeccion de un conjunto residencial.
 *
 * La identidad del conjunto (nombre, codigo, ubicacion y estado) es visible
 * para quien pertenece a el. Los datos de gobierno (`legal_identifier` y
 * `settings`) solo se exponen a quien administra la configuracion del
 * conjunto o a la plataforma.
 */
class ResidentialComplexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country_code' => $this->country_code,
            'timezone' => $this->timezone,
            'currency_code' => $this->currency_code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->mayViewGovernance($request)) {
            $data['legal_identifier'] = $this->legal_identifier;
            $data['settings'] = $this->settings ?? [];
        }

        return $data;
    }

    /**
     * Los datos de gobierno exigen el permiso de configuracion.
     */
    private function mayViewGovernance(Request $request): bool
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return false;
        }

        return Gate::allows('viewGovernance', $this->resource);
    }
}
