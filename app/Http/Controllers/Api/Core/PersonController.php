<?php

namespace App\Http\Controllers\Api\Core;

use App\Enums\ComplexPermission;
use App\Http\Resources\PersonMinimalResource;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Personas del conjunto (spec §4.2, §6 "Privacidad").
 *
 * La proyeccion depende del permiso: person.view da la ficha completa y
 * person.view_minimal solo los datos minimos de porteria.
 */
class PersonController extends CoreController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $complexId = $this->complexId($request);
        $user = $request->user();

        $full = PermissionService::has($user, ComplexPermission::PERSON_VIEW, $complexId);
        $minimal = PermissionService::has($user, ComplexPermission::PERSON_VIEW_MINIMAL, $complexId);

        abort_unless(
            $full || $minimal,
            403,
            'No tienes permiso para consultar las personas del conjunto.'
        );

        $people = Person::query()
            ->forComplex($complexId)
            ->orderBy('full_name')
            ->paginate(min(max((int) $request->input('per_page', 25), 1), 100));

        return $full
            ? PersonResource::collection($people)
            : PersonMinimalResource::collection($people);
    }

    public function show(Request $request, string $person): PersonResource|PersonMinimalResource
    {
        $complexId = $this->complexId($request);
        $person = $this->resolvePerson($request, $person);

        abort_unless(Gate::allows('view', $person), 403, 'No tienes permiso para consultar esta persona.');

        return PermissionService::has($request->user(), ComplexPermission::PERSON_VIEW, $complexId)
            ? new PersonResource($person)
            : new PersonMinimalResource($person);
    }
}
