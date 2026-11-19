<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unidad del conjunto (spec 3.5).
 */
class PropertyUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'number' => $this->number,
            'unit_type' => $this->unit_type?->value,
            'status' => $this->status?->value,
            'internal_code' => $this->internal_code,
            'area_m2' => $this->area_m2,
            'coefficient' => $this->coefficient,
            'notes' => $this->notes,
            'building_id' => $this->building_id,
            'building_floor_id' => $this->building_floor_id,
        ];
    }
}
