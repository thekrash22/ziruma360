<?php

namespace App\Http\Controllers\Api\Core;

use App\Enums\ComplexPermission;
use App\Enums\PropertyUnitStatus;
use App\Http\Requests\Core\StorePropertyUnitRequest;
use App\Http\Requests\Core\UpdatePropertyUnitRequest;
use App\Http\Resources\PropertyUnitResource;
use App\Models\PropertyUnit;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Unidades del conjunto (spec §3.5, §6 "Alcance").
 *
 * Esta controlador sirve tanto a la superficie `/api/v1/core/*` como al
 * alias legacy `/api/v1/units` (S39): los paths y los nombres de ruta se
 * conservan y solo cambia el dominio que atendian.
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
        $unit = UpdatePropertyUnitRequest::resolveUnit($request);

        abort_unless(Gate::allows('view', $unit), 403, 'No tienes permiso para consultar esta unidad.');

        return new PropertyUnitResource($this->withRelations($unit));
    }

    /**
     * Alta de unidad en el conjunto activo (S39).
     *
     * El conjunto lo decide el servidor: nunca se toma del cuerpo de la
     * peticion ni de un encabezado del cliente (S9).
     */
    public function store(StorePropertyUnitRequest $request): JsonResponse
    {
        $unit = new PropertyUnit();
        $unit->fill($request->validated());
        $unit->residential_complex_id = $this->complexId($request);
        $unit->save();

        return (new PropertyUnitResource($this->withRelations($unit)))
            ->response()
            ->setStatusCode(201);
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

        return new PropertyUnitResource($this->withRelations($unit->refresh()));
    }

    /**
     * Baja de unidad (S39).
     *
     * RN-S04/RN-S02: en el dominio nuevo la baja es una DESACTIVACION, no
     * un borrado. La unidad pasa a `status = inactive` y la fila (con su
     * historial de titularidad, ocupaciones y arrendamientos) se conserva.
     */
    public function destroy(Request $request): JsonResponse
    {
        $unit = UpdatePropertyUnitRequest::resolveUnit($request);

        abort_unless(
            PermissionService::has(
                $request->user(),
                ComplexPermission::UNIT_DELETE,
                $this->complexId($request)
            ),
            403,
            'No tienes permiso para desactivar las unidades del conjunto.'
        );

        $unit->status = PropertyUnitStatus::INACTIVE;
        $unit->save();

        return (new PropertyUnitResource($this->withRelations($unit->refresh())))->response();
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

    /**
     * Carga las relaciones que expone el recurso (edificio y piso).
     */
    protected function withRelations(PropertyUnit $unit): PropertyUnit
    {
        return $unit->loadMissing(['building', 'buildingFloor']);
    }
}
