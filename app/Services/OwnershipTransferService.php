<?php

namespace App\Services;

use App\Enums\OwnershipStatus;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitOwnership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Traspaso de titularidad (spec §4.3, RN-P05, §6 "Personas").
 *
 * La titularidad no se edita en sitio: se cierra el registro vigente y se
 * crea otro. El historial se conserva completo y ambos movimientos quedan
 * auditados por el trait Auditable.
 */
class OwnershipTransferService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function transfer(PropertyUnit $unit, Person $newOwner, array $attributes): PropertyUnitOwnership
    {
        return DB::transaction(function () use ($unit, $newOwner, $attributes) {
            // Se serializa por unidad: el trigger de RN-P04 suma los
            // porcentajes vigentes bloqueando la fila de la unidad.
            $locked = PropertyUnit::query()
                ->withoutGlobalScope('complex')
                ->whereKey($unit->getKey())
                ->lockForUpdate()
                ->first();

            abort_if($locked === null, 404, 'Unidad no encontrada.');

            foreach ($locked->ownerships()->where('status', OwnershipStatus::ACTIVE->value)->get() as $ownership) {
                $ownership->update([
                    'status' => OwnershipStatus::CLOSED->value,
                    'valid_until' => $this->closingDate($ownership),
                ]);
            }

            return $locked->ownerships()->create([
                'residential_complex_id' => $locked->residential_complex_id,
                'person_id' => $newOwner->id,
                'ownership_type' => $attributes['ownership_type'],
                'percentage' => $attributes['percentage'] ?? null,
                'valid_from' => $attributes['valid_from'] ?? Carbon::today()->toDateString(),
                'valid_until' => $attributes['valid_until'] ?? null,
                'status' => OwnershipStatus::ACTIVE->value,
                'document_reference' => $attributes['document_reference'] ?? null,
            ]);
        });
    }

    /**
     * Fecha de cierre de una titularidad vigente.
     *
     * Una titularidad que empieza en el futuro no puede cerrarse con una
     * fecha anterior a su inicio (el CHECK de vigencia lo rechaza).
     */
    protected function closingDate(PropertyUnitOwnership $ownership): string
    {
        $today = Carbon::today();

        if ($ownership->valid_from !== null && $ownership->valid_from->isFuture()) {
            return $ownership->valid_from->toDateString();
        }

        return $today->toDateString();
    }
}
