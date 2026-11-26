<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A record of the audit trail (specs S100 and S101).
 *
 * It exposes who did it (`actor`), what it was (`action`), which record it
 * touched (`entity_type` and `entity_id`), when it happened (`occurred_at`),
 * the correlation id of the request and the diff (`before_data` and
 * `after_data`).
 *
 * The actor is exposed by id and name only: the trail never publishes the
 * contact data of the account.
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'correlation_id' => $this->correlation_id,
            'actor' => $this->actor === null ? null : [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ],
            'before_data' => $this->before_data,
            'after_data' => $this->after_data,
        ];
    }
}
