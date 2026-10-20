<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResidentialComplexResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'legal_identifier' => $this->legal_identifier,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country_code' => $this->country_code,
            'timezone' => $this->timezone,
            'currency_code' => $this->currency_code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'settings' => $this->settings ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
