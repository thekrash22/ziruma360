<?php

namespace App\Http\Controllers\Api\Core;

use App\Enums\ComplexPermission;
use App\Http\Requests\Core\UpdatePropertyUnitRequest;
use App\Http\Resources\PropertyUnitResource;
use App\Models\PropertyUnit;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Unidades del conjunto (spec §3.5, §6 "Alcance").
 */
class PropertyUnitController extends CoreController
{
    /**
     * Campos que puede modificar el administrador del conjunto.
     */
    private const ADMIN_FIELDS = ['internal_code', 'area_m2', 'coefficient', 'status', 'notes'];

    /**
     * Campos que puede modificar el propietario de la unidad ("datos
     * limitados" en la matriz de permisos).
     */
    private const OWNER_FIELDS = ['notes'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $complexId = $this->complexId($request);
        $user = $request->user();

        abort_unless(
            PermissionService::has($user, ComplexPermission::UNIT_VIEW, $complexId),
            403,
            'No tienes permiso para consultar las unidades del conjunto.'
        );

        $query = PropertyUnit::query()->forComplex($complexId);

        // El administrador del conjunto ve todas sus unidades; el resto ve
        // solo las que le alcanzan por su alcance.
        if (! PermissionService::has($user, ComplexPermission::UNIT_EDIT, $complexId)) {
            $query = $query->forActor($user, $complexId);
        }

        $units = $query->orderBy('number')->paginate($this->perPage($request));

        return PropertyUnitResource::collection($units);
    }

    public function show(Request $request): PropertyUnitResource
    {
        /** @var PropertyUnit $unit */
        $unit = $request->attributes->get('property_unit');

        abort_unless(Gate::allows('view', $unit), 403, 'No tienes permiso para consultar esta unidad.');

        return new PropertyUnitResource($unit);
    }

    public function update(UpdatePropertyUnitRequest $request): PropertyUnitResource
    {
        /** @var PropertyUnit $unit */
        $unit = $request->attributes->get('property_unit');
        $payload = $request->validated();

        $forbidden = array_diff(array_keys($payload), $this->updatableFields($request));

        abort_if(
            $forbidden !== [],
            422,
            'No puedes modificar estos campos: '.implode(', ', $forbidden).'.'
        );

        $unit->fill($payload)->save();

        return new PropertyUnitResource($unit->refresh());
    }

    /**
     * Campos que el actor puede modificar en esta unidad.
     *
     * @return list<string>
     */
    protected function updatableFields(Request $request): array
    {
        $complexId = $this->complexId($request);

        if (PermissionService::has($request->user(), ComplexPermission::UNIT_EDIT, $complexId)) {
            return self::ADMIN_FIELDS;
        }

        return self::OWNER_FIELDS;
    }

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 25), 1), 100);
    }
}
