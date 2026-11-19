<?php

namespace App\Http\Requests\Core;

use App\Enums\PropertyUnitStatus;
use App\Models\PropertyUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Actualizacion de una unidad (spec 3.5).
 *
 * El permiso y el alcance los decide la Policy; aqui solo se validan los
 * datos. Los campos que el actor puede tocar se filtran en el controlador
 * (el propietario solo tiene "datos limitados").
 */
class UpdatePropertyUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->attributes->get('property_unit');

        return $unit instanceof PropertyUnit && Gate::allows('update', $unit);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'internal_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'area_m2' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'coefficient' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
            'status' => ['sometimes', Rule::enum(PropertyUnitStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
