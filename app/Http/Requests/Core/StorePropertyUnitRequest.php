<?php

namespace App\Http\Requests\Core;

use App\Enums\ComplexPermission;
use App\Enums\PropertyUnitStatus;
use App\Enums\PropertyUnitType;
use App\Models\Building;
use App\Models\BuildingFloor;
use App\Models\PropertyUnit;
use App\Services\PermissionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de una unidad (spec §3.5, S39).
 *
 * El conjunto activo lo resuelve el servidor (S9): el cliente nunca lo
 * envia. El permiso de alta lo decide el catalogo (`unit.create`).
 */
class StorePropertyUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PermissionService::has(
            $this->user(),
            ComplexPermission::UNIT_CREATE,
            $this->complexId()
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:64'],
            'unit_type' => ['required', Rule::enum(PropertyUnitType::class)],
            'building_id' => ['nullable', 'integer'],
            'building_floor_id' => ['nullable', 'integer'],
            'internal_code' => ['nullable', 'string', 'max:64'],
            'area_m2' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'coefficient' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'status' => ['nullable', Rule::enum(PropertyUnitStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Coherencia con el dominio: el edificio y el piso tienen que pertenecer
     * al conjunto activo, el piso tiene que ser del edificio indicado
     * (FK compuesta) y el numero de unidad es unico por conjunto, edificio
     * y tipo (UNIQUE NULLS NOT DISTINCT).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $complexId = $this->complexId();
            $buildingId = $this->input('building_id');
            $floorId = $this->input('building_floor_id');

            if ($buildingId !== null) {
                $belongs = Building::withoutGlobalScope('complex')
                    ->whereKey((int) $buildingId)
                    ->where('residential_complex_id', $complexId)
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('building_id', 'El edificio no pertenece al conjunto activo.');
                }
            }

            if ($floorId !== null) {
                $floor = BuildingFloor::withoutGlobalScope('complex')->find((int) $floorId);

                if ($floor === null) {
                    $validator->errors()->add('building_floor_id', 'El piso no existe.');
                } elseif ($buildingId === null) {
                    $validator->errors()->add('building_floor_id', 'Indica el edificio al que pertenece el piso.');
                } elseif ((int) $floor->building_id !== (int) $buildingId) {
                    $validator->errors()->add('building_floor_id', 'El piso no pertenece al edificio indicado.');
                }
            }

            $number = $this->input('number');
            $type = $this->input('unit_type');

            if (is_string($number) && is_string($type)) {
                $duplicate = PropertyUnit::withoutGlobalScope('complex')
                    ->where('residential_complex_id', $complexId)
                    ->where('unit_type', $type)
                    ->where('number', $number)
                    ->when(
                        $buildingId === null,
                        fn ($query) => $query->whereNull('building_id'),
                        fn ($query) => $query->where('building_id', (int) $buildingId)
                    )
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('number', 'Ya existe una unidad con ese numero en el conjunto.');
                }
            }
        });
    }

    /**
     * El conjunto activo verificado por el middleware.
     */
    protected function complexId(): int
    {
        return (int) $this->attributes->get('complex_id');
    }
}
