<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Requests\Core\StoreOwnershipRequest;
use App\Http\Resources\OwnershipResource;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitOwnership;
use App\Services\OwnershipTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Historial de titularidad de una unidad (spec §4.3).
 */
class OwnershipController extends CoreController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var PropertyUnit $unit */
        $unit = $request->attributes->get('property_unit');

        // La unidad tiene que estar al alcance del actor y el actor tiene
        // que tener el permiso de titularidad: el permiso sin alcance no
        // abre el historial de unidades ajenas.
        abort_unless(
            Gate::allows('view', $unit),
            403,
            'No tienes acceso a esta unidad.'
        );

        abort_unless(
            Gate::allows('viewAny', [PropertyUnitOwnership::class]),
            403,
            'No tienes permiso para consultar la titularidad de las unidades.'
        );

        $ownerships = $unit->ownerships()
            ->with('person')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get();

        return OwnershipResource::collection($ownerships);
    }

    public function store(StoreOwnershipRequest $request, OwnershipTransferService $transfers): JsonResponse
    {
        /** @var PropertyUnit $unit */
        $unit = $request->attributes->get('property_unit');

        $person = $this->resolvePersonByUuid($request, (string) $request->validated('person_uuid'));

        abort_if(
            $person === null,
            422,
            'La persona no pertenece al conjunto: no se puede registrar la titularidad.'
        );

        $ownership = $transfers->transfer($unit, $person, $request->validated());

        return (new OwnershipResource($ownership->load('person')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * La persona se busca dentro del conjunto activo: una persona de otro
     * conjunto no se resuelve (y no se filtra su existencia).
     */
    protected function resolvePersonByUuid(Request $request, string $uuid): ?Person
    {
        return Person::query()
            ->forComplex($this->complexId($request))
            ->where('uuid', $uuid)
            ->first();
    }
}
