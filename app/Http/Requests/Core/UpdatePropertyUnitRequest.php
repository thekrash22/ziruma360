<?php

namespace App\Http\Requests\Core;

use App\Enums\PropertyUnitStatus;
use App\Models\PropertyUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Actualizacion de una unidad (spec 3.5).
 *
 * El permiso y el alcance los decide la Policy; aqui solo se validan los
 * datos. Los campos que el actor puede tocar se filtran en el controlador
 * (el propietario solo tiene "datos limitados").
 *
 * La unidad se resuelve dentro del conjunto activo verificado: un
 * identificador de otro conjunto no se resuelve y responde 404, sin filtrar
 * su existencia (ACE-C01).
 */
class UpdatePropertyUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = self::resolveUnit($this);

        return Gate::allows('update', $unit);
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

    /**
     * Resuelve la unidad dentro del conjunto activo.
     *
     * La superficie `/api/v1/core/*` resuelve la unidad con el middleware
     * `ResolvePropertyUnit` (que deja el modelo en los atributos de la
     * peticion); el alias legacy `/api/v1/units/{unidad}` no lo tiene, asi
     * que se resuelve aqui con el mismo criterio: solo dentro del conjunto
     * activo y por `uuid`.
     */
    public static function resolveUnit(Request $request): PropertyUnit
    {
        $unit = $request->attributes->get('property_unit');

        if ($unit instanceof PropertyUnit) {
            return $unit;
        }

        $value = $request->route('unidad') ?? $request->route('propertyUnit');

        if (! is_string($value) || ! Str::isUuid($value)) {
            abort(404, 'Unidad no encontrada.');
        }

        $complexId = (int) $request->attributes->get('complex_id');

        $unit = PropertyUnit::query()
            ->forComplex($complexId)
            ->where('uuid', $value)
            ->first();

        abort_if($unit === null, 404, 'Unidad no encontrada.');

        $request->attributes->set('property_unit', $unit);

        return $unit;
    }
}
