<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Titularidad de una unidad (spec 4.3).
 */
class OwnershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'property_unit_uuid' => $this->property_unit?->uuid,
            'person' => [
                'uuid' => $this->person?->uuid,
                'full_name' => $this->person?->full_name,
            ],
            'ownership_type' => $this->ownership_type?->value,
            'percentage' => $this->percentage,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'status' => $this->status?->value,
            'document_reference' => $this->document_reference,
        ];
    }
}
