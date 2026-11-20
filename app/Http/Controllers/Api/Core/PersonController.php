<?php

namespace App\Http\Controllers\Api\Core;

use App\Enums\ComplexPermission;
use App\Enums\PersonStatus;
use App\Enums\TipoDocumento;
use App\Http\Requests\Core\StorePersonRequest;
use App\Http\Requests\Core\UpdatePersonRequest;
use App\Http\Resources\PersonMinimalResource;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Personas del conjunto (spec §4.2, §6 "Privacidad").
 *
 * La proyeccion depende del permiso: person.view da la ficha completa y
 * person.view_minimal solo los datos minimos de porteria.
 *
 * Esta controlador sirve tanto a la superficie `/api/v1/core/*` como al
 * alias legacy `/api/v1/people` (S39).
 *
 * La guarda de escritura del documento de identidad (SEG-P01) vive en los
 * `FormRequest` (`StorePersonRequest` y `UpdatePersonRequest`): el
 * documento de identidad es el par (`document_type`, `document_number`) y
 * escribir cualquiera de los dos exige el permiso `person.view_document`,
 * con un 403 y el mismo mensaje que la superficie web. La atomicidad del
 * par (tipo y numero juntos, o ninguno) se valida tambien en los
 * `FormRequest`, de modo que el error llega como 422 y no como un error del
 * CHECK de la base.
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

    public function show(Request $request): PersonResource|PersonMinimalResource
    {
        $complexId = $this->complexId($request);
        $person = UpdatePersonRequest::resolvePerson($request);

        abort_unless(Gate::allows('view', $person), 403, 'No tienes permiso para consultar esta persona.');

        return PermissionService::has($request->user(), ComplexPermission::PERSON_VIEW, $complexId)
            ? new PersonResource($person)
            : new PersonMinimalResource($person);
    }

    /**
     * Alta de persona en el conjunto activo (S39).
     *
     * El conjunto lo decide el servidor: nunca se toma del cuerpo de la
     * peticion ni de un encabezado del cliente (S9).
     *
     * RN-P02: antes de crear se busca por documento y, si ya existe una
     * persona con ese documento en el conjunto, se reutiliza en lugar de
     * duplicar la informacion.
     */
    public function store(StorePersonRequest $request): JsonResponse
    {
        $complexId = $this->complexId($request);
        $data = $request->validated();

        $existing = $this->findByDocument($complexId, $data);

        if ($existing !== null) {
            return (new PersonResource($existing))->response();
        }

        $person = new Person;
        $person->fill($data);
        $person->residential_complex_id = $complexId;
        $person->save();

        return (new PersonResource($person))->response()->setStatusCode(201);
    }

    public function update(UpdatePersonRequest $request): PersonResource
    {
        /** @var Person $person */
        $person = $request->attributes->get('person');

        $person->fill($request->validated())->save();

        return new PersonResource($person->refresh());
    }

    /**
     * Baja de persona (S39).
     *
     * RN-S04/RN-S02: en el dominio nuevo la baja es una DESACTIVACION, no
     * un borrado. La persona pasa a `status = inactive` y la fila (con su
     * historial de titularidades, ocupaciones y arrendamientos) se conserva.
     */
    public function destroy(Request $request): JsonResponse
    {
        $person = UpdatePersonRequest::resolvePerson($request);

        abort_unless(
            PermissionService::has(
                $request->user(),
                ComplexPermission::PERSON_DELETE,
                $this->complexId($request)
            ),
            403,
            'No tienes permiso para desactivar las personas del conjunto.'
        );

        $person->status = PersonStatus::INACTIVE;
        $person->save();

        return (new PersonResource($person->refresh()))->response();
    }

    /**
     * RN-P02: la persona con ese documento en el conjunto, si existe.
     *
     * @param  array<string, mixed>  $data
     */
    protected function findByDocument(int $complexId, array $data): ?Person
    {
        $type = $data['document_type'] ?? null;
        $number = $data['document_number'] ?? null;

        if (! is_string($type) || ! is_string($number) || $type === '' || $number === '') {
            return null;
        }

        return Person::findByDocument(
            $complexId,
            TipoDocumento::from($type),
            $number
        );
    }
}
