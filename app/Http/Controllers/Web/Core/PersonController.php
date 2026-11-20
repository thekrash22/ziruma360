<?php

namespace App\Http\Controllers\Web\Core;

use App\Enums\ComplexPermission;
use App\Enums\PersonStatus;
use App\Enums\TipoDocumento;
use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PropertyUnitOwnership;
use App\Models\UnitLease;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Policies\PersonPolicy;
use App\Services\ActiveComplexResolver;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personas del conjunto en la UI web (spec S38, §4.2, §6 "Privacidad").
 *
 * La ficha de una persona se sirve sobre el dominio nuevo (`people`), con las
 * relaciones tipadas (titularidad, ocupacion y arrendamiento) en lugar del
 * pivot legacy `persona_unidad`.
 *
 * El documento de identidad se muestra enmascarado (solo los ultimos cuatro
 * caracteres) salvo que el actor tenga el permiso explicito
 * `person.view_document` (SEG-P01). Ese permiso gobierna ademas la ESCRITURA
 * del documento: el documento de identidad es el par (`document_type`,
 * `document_number`) y escribir cualquiera de los dos campos (o anularlos en
 * una persona que ya tiene documento) exige el permiso, con el mismo
 * mensaje y el mismo 403 que la API.
 */
class PersonController extends Controller
{
    /**
     * Listado de personas del conjunto activo.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $complexId = $this->complexId($request);
        $policy = $this->policy();

        abort_unless(
            $policy->viewAny($user, $complexId),
            403,
            'No tienes permiso para consultar las personas del conjunto.'
        );

        $full = $policy->canViewFull($user, $complexId);

        $people = Person::query()
            ->forComplex($complexId)
            ->with([
                'ownerships.propertyUnit',
                'occupancies.propertyUnit',
                'leases.propertyUnit',
            ])
            ->when($request->input('search'), fn ($query, $search) => $query->where('full_name', 'like', "%{$search}%"))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('full_name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $people->getCollection()->transform(fn (Person $person): array => $full
            ? $this->summary($person, $user, $complexId)
            : $this->minimal($person));

        return Inertia::render('People/Index', [
            'people' => $people,
            'filters' => $request->only(['search', 'status']),
            'statuses' => $this->statuses(),
            'canViewDocument' => PermissionService::has($user, ComplexPermission::PERSON_VIEW_DOCUMENT, $complexId),
            'canDelete' => PermissionService::has($user, ComplexPermission::PERSON_DELETE, $complexId),
        ] + $this->flashProps($request));
    }

    /**
     * Formulario de alta.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $complexId = $this->complexId($request);

        abort_unless(
            PermissionService::has($user, ComplexPermission::PERSON_CREATE, $complexId),
            403,
            'No tienes permiso para crear personas en este conjunto.'
        );

        return Inertia::render('People/Create', [
            'documentTypes' => $this->documentTypes(),
            'statuses' => $this->statuses(),
            'canViewDocument' => PermissionService::has($user, ComplexPermission::PERSON_VIEW_DOCUMENT, $complexId),
        ]);
    }

    /**
     * Alta de una persona en el conjunto activo.
     *
     * RN-P02: antes de crear se busca por documento; si ya existe una persona
     * con ese documento en el conjunto, se reutiliza ese registro en lugar
     * de duplicar la informacion.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $complexId = $this->complexId($request);

        abort_unless(
            PermissionService::has($user, ComplexPermission::PERSON_CREATE, $complexId),
            403,
            'No tienes permiso para crear personas en este conjunto.'
        );

        // Guarda de escritura del documento de identidad (SEG-P01): sin el
        // permiso explicito no se escribe un documento, y la peticion que
        // trae uno se rechaza con 403 sin crear nada.
        $this->assertCanWriteIdentityDocument($request, $user, $complexId, null);

        try {
            $validated = $this->validatePerson($request, $complexId, null, true);
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        }

        $existing = $this->findByDocument($complexId, $validated);

        if ($existing !== null) {
            return redirect()->route('people.show', $existing->uuid)
                ->with('success', 'Ya existe una persona con ese documento: se reutilizo su ficha.');
        }

        $person = Person::create(array_merge($validated, [
            'residential_complex_id' => $complexId,
            'status' => $validated['status'] ?? PersonStatus::ACTIVE->value,
        ]));

        return redirect()->route('people.index')
            ->with('success', 'Persona creada exitosamente.');
    }

    /**
     * Detalle de la persona con sus relaciones tipadas.
     */
    public function show(Request $request, string $uuid): Response
    {
        $user = $request->user();
        $complexId = $this->complexId($request);
        $person = $this->resolvePerson($complexId, $uuid);

        abort_unless(Gate::allows('view', $person), 403, 'No tienes permiso para consultar esta persona.');

        $policy = $this->policy();

        $person->loadMissing([
            'ownerships.propertyUnit',
            'occupancies.propertyUnit',
            'leases.propertyUnit',
        ]);

        $payload = $policy->canViewFull($user, $complexId)
            ? $this->detail($person, $user, $complexId)
            : $this->minimal($person);

        return Inertia::render('People/Show', [
            'person' => $payload,
        ] + $this->flashProps($request));
    }

    /**
     * Formulario de edicion.
     */
    public function edit(Request $request, string $uuid): Response
    {
        $user = $request->user();
        $complexId = $this->complexId($request);
        $person = $this->resolvePerson($complexId, $uuid);

        abort_unless(
            $this->canManage($user, $person),
            403,
            'No tienes permiso para editar esta persona.'
        );

        return Inertia::render('People/Edit', [
            'person' => array_merge(
                $this->summary($person, $user, $complexId),
                ['notes' => $person->notes]
            ),
            'documentTypes' => $this->documentTypes(),
            'statuses' => $this->statuses(),
            'canViewDocument' => PermissionService::has($user, ComplexPermission::PERSON_VIEW_DOCUMENT, $complexId),
        ] + $this->flashProps($request));
    }

    /**
     * Actualizacion de la persona.
     */
    public function update(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $complexId = $this->complexId($request);
        $person = $this->resolvePerson($complexId, $uuid);

        abort_unless(
            $this->canManage($user, $person),
            403,
            'No tienes permiso para editar esta persona.'
        );

        // Guarda de escritura del documento de identidad (SEG-P01).
        $this->assertCanWriteIdentityDocument($request, $user, $complexId, $person);

        try {
            $validated = $this->validatePerson($request, $complexId, $person);
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        }

        // Sin el permiso explicito el documento de identidad no se modifica
        // (SEG-P01): la UI no lo expone en claro y una peticion que escribe
        // el documento ya fue rechazada con 403 mas arriba.
        if (! PermissionService::has($user, ComplexPermission::PERSON_VIEW_DOCUMENT, $complexId)) {
            unset($validated['document_type'], $validated['document_number']);
        }

        $person->fill($validated)->save();

        return redirect()->route('people.index')
            ->with('success', 'Persona actualizada exitosamente.');
    }

    /**
     * Baja controlada: la persona se desactiva, no se borra (RN-S04/RN-S02).
     */
    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        $user = $request->user();
        $complexId = $this->complexId($request);
        $person = $this->resolvePerson($complexId, $uuid);

        abort_unless(
            PermissionService::has($user, ComplexPermission::PERSON_DELETE, $complexId),
            403,
            'No tienes permiso para desactivar esta persona.'
        );

        $person->update(['status' => PersonStatus::INACTIVE->value]);

        return redirect()->route('people.index')
            ->with('success', 'Persona desactivada exitosamente.');
    }

    // ==================== Helpers ====================

    /**
     * El conjunto activo verificado por el middleware; si el middleware no
     * esta presente, se resuelve en el servidor con el mismo servicio.
     */
    protected function complexId(Request $request): int
    {
        $complexId = $request->attributes->get('complex_id');

        if ($complexId === null) {
            $complexId = app(ActiveComplexResolver::class)->resolve($request);
        }

        return (int) $complexId;
    }

    protected function policy(): PersonPolicy
    {
        return app(PersonPolicy::class);
    }

    /**
     * Mensajes de una operacion anterior (redirect con flash).
     *
     * @return array{flash: array<string, string>}
     */
    protected function flashProps(Request $request): array
    {
        return [
            'flash' => array_filter([
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]),
        ];
    }

    /**
     * Fallo de validacion.
     *
     * En una peticion Inertia el error vuelve como redirect con la bolsa de
     * errores (flujo estandar de Inertia, la consume el formulario); en el
     * resto de peticiones la respuesta es 422 con el detalle de los errores.
     */
    protected function validationFailure(Request $request, ValidationException $exception): JsonResponse
    {
        if ($request->header('X-Inertia') !== null) {
            throw $exception;
        }

        return response()->json([
            'message' => $exception->getMessage(),
            'errors' => $exception->errors(),
        ], 422);
    }

    /**
     * La persona dentro del conjunto activo; si no existe, 404 (ACE-C01).
     */
    protected function resolvePerson(int $complexId, string $value): Person
    {
        abort_if(! Str::isUuid($value), 404, 'Persona no encontrada.');

        $person = Person::query()
            ->forComplex($complexId)
            ->where('uuid', $value)
            ->first();

        abort_if($person === null, 404, 'Persona no encontrada.');

        return $person;
    }

    /**
     * Guarda de escritura del documento de identidad (SEG-P01).
     *
     * El documento de identidad es el par (`document_type`, `document_number`):
     * escribir cualquiera de los dos campos exige el permiso explicito
     * `person.view_document`, el mismo que lo expone en claro. Sin el, la
     * peticion se rechaza con 403 y no se persiste nada. Anular cualquiera de
     * los dos campos en una persona que ya tiene documento tambien es una
     * escritura (un borrado) y se rechaza con el mismo 403 y el mismo
     * mensaje que la API.
     */
    protected function assertCanWriteIdentityDocument(
        Request $request,
        User $user,
        int $complexId,
        ?Person $person = null
    ): void {
        if (! $this->writesIdentityDocument($request, $person)) {
            return;
        }

        abort_unless(
            PermissionService::has($user, ComplexPermission::PERSON_VIEW_DOCUMENT, $complexId),
            403,
            'No tienes permiso para escribir el documento de identidad.'
        );
    }

    /**
     * Si la peticion escribe un documento de identidad.
     *
     * Es una escritura cuando alguno de los dos campos del par viene con
     * valor. Un valor nulo o vacio tambien es una escritura (borrado) si la
     * persona almacenada tiene un documento; si no lo tiene, no hay
     * escritura y el campo se ignora.
     */
    protected function writesIdentityDocument(Request $request, ?Person $person = null): bool
    {
        $typePresent = $request->has('document_type');
        $numberPresent = $request->has('document_number');

        if (! $typePresent && ! $numberPresent) {
            return false;
        }

        if ($this->documentFieldHasValue($request->input('document_type'))
            || $this->documentFieldHasValue($request->input('document_number'))) {
            return true;
        }

        if ($person === null) {
            return false;
        }

        return $this->documentFieldHasValue($person->document_type?->value)
            || $this->documentFieldHasValue($person->document_number);
    }

    /**
     * Un valor de documento cuenta cuando no es nulo ni una cadena vacia.
     */
    protected function documentFieldHasValue(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return is_int($value) || is_float($value);
    }

    /**
     * Editar exige el permiso de edicion y poder ver la ficha.
     */
    protected function canManage(User $user, Person $person): bool
    {
        $complexId = (int) $person->residential_complex_id;

        return PermissionService::has($user, ComplexPermission::PERSON_EDIT, $complexId)
            && Gate::allows('view', $person);
    }

    /**
     * Validacion de los campos reales del dominio (`people`).
     *
     * @return array<string, mixed>
     */
    protected function validatePerson(Request $request, int $complexId, ?Person $person = null, bool $allowDuplicate = false): array
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', Rule::in(array_column(TipoDocumento::cases(), 'value'))],
            'document_number' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'status' => ['nullable', Rule::in(array_column(PersonStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->assertIdentityDocumentIsAtomic($request, $person);

        $duplicate = $this->findByDocument($complexId, $validated, $person);

        if ($duplicate !== null && ! $allowDuplicate) {
            throw ValidationException::withMessages([
                'document_number' => 'Ya existe una persona con ese documento en el conjunto.',
            ]);
        }

        return $validated;
    }

    /**
     * El documento de identidad es atomico (RN-P02, T17): tipo y numero van
     * juntos, porque es el par el que identifica a la persona.
     *
     * Se valida con los valores EFECTIVOS: lo que trae la peticion y, para
     * el campo que no viene, lo que la persona ya tiene almacenado. Asi el
     * error llega como 422 y no como un error del CHECK de la base.
     */
    protected function assertIdentityDocumentIsAtomic(Request $request, ?Person $person = null): void
    {
        $type = $request->has('document_type')
            ? $request->input('document_type')
            : $person?->document_type?->value;

        $number = $request->has('document_number')
            ? $request->input('document_number')
            : $person?->document_number;

        $hasType = $this->documentFieldHasValue($type);
        $hasNumber = $this->documentFieldHasValue($number);

        $errors = [];

        // Atomicidad del documento: tipo y numero van juntos. Cuando la
        // peticion anula uno de los dos, el otro tiene que anularse tambien
        // (el documento se borra entero o no se borra).
        if ($hasType && ! $hasNumber) {
            if ($request->has('document_number')) {
                $errors['document_type'] = 'Para dejar el documento de identidad vacio, anula tambien el tipo de documento.';
            } else {
                $errors['document_number'] = 'El numero de documento es obligatorio cuando se informa el tipo de documento.';
            }
        }

        if ($hasNumber && ! $hasType) {
            if ($request->has('document_type')) {
                $errors['document_number'] = 'Para dejar el documento de identidad vacio, anula tambien el numero de documento.';
            } else {
                $errors['document_type'] = 'El tipo de documento es obligatorio cuando se informa el numero de documento.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Persona con el mismo documento en el conjunto (RN-P02).
     *
     * @param  array<string, mixed>  $data
     */
    protected function findByDocument(int $complexId, array $data, ?Person $person = null): ?Person
    {
        $type = $data['document_type'] ?? null;
        $number = $data['document_number'] ?? null;

        if (! is_string($type) || $type === '' || ! is_string($number) || $number === '') {
            return null;
        }

        $query = Person::query()
            ->withoutGlobalScope('complex')
            ->where('residential_complex_id', $complexId)
            ->where('document_type', $type)
            ->where('document_number', $number);

        if ($person !== null) {
            $query->whereKeyNot($person->getKey());
        }

        return $query->first();
    }

    /**
     * Proyeccion completa de la persona (permiso `person.view`).
     *
     * @return array<string, mixed>
     */
    protected function summary(Person $person, User $user, int $complexId): array
    {
        return [
            'uuid' => $person->uuid,
            'full_name' => $person->full_name,
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'document_type' => $person->document_type?->value,
            'document_type_label' => $person->document_type?->label(),
            'document_number' => $this->documentNumber($person, $user, $complexId),
            'phone' => $person->phone,
            'email' => $person->email,
            'birth_date' => $person->birth_date?->toDateString(),
            'status' => $person->status?->value,
            'status_label' => $person->status?->label(),
            'units' => $this->unitsReached($person),
        ];
    }

    /**
     * Proyeccion minima de porteria (permiso `person.view_minimal`).
     *
     * @return array<string, mixed>
     */
    protected function minimal(Person $person): array
    {
        return [
            'uuid' => $person->uuid,
            'full_name' => $person->full_name,
            'status' => $person->status?->value,
            'status_label' => $person->status?->label(),
        ];
    }

    /**
     * Proyeccion de la persona con sus relaciones tipadas.
     *
     * @return array<string, mixed>
     */
    protected function detail(Person $person, User $user, int $complexId): array
    {
        return array_merge($this->summary($person, $user, $complexId), [
            'notes' => $person->notes,
            'created_at' => $person->created_at?->toIso8601String(),
            'updated_at' => $person->updated_at?->toIso8601String(),
            'ownerships' => $person->ownerships->map(fn (PropertyUnitOwnership $ownership): array => [
                'uuid' => $ownership->uuid,
                'unit_uuid' => $ownership->propertyUnit?->uuid,
                'unit_number' => $ownership->propertyUnit?->number,
                'ownership_type' => $ownership->ownership_type?->value,
                'ownership_type_label' => $ownership->ownership_type?->label(),
                'percentage' => $ownership->percentage,
                'valid_from' => $ownership->valid_from?->toDateString(),
                'valid_until' => $ownership->valid_until?->toDateString(),
                'status' => $ownership->status?->value,
            ])->values()->all(),
            'occupancies' => $person->occupancies->map(fn (UnitOccupancy $occupancy): array => [
                'uuid' => $occupancy->uuid,
                'unit_uuid' => $occupancy->propertyUnit?->uuid,
                'unit_number' => $occupancy->propertyUnit?->number,
                'occupancy_type' => $occupancy->occupancy_type?->value,
                'occupancy_type_label' => $occupancy->occupancy_type?->label(),
                'is_primary_contact' => $occupancy->is_primary_contact,
                'valid_from' => $occupancy->valid_from?->toDateString(),
                'valid_until' => $occupancy->valid_until?->toDateString(),
                'status' => $occupancy->status?->value,
            ])->values()->all(),
            'leases' => $person->leases->map(fn (UnitLease $lease): array => [
                'uuid' => $lease->uuid,
                'unit_uuid' => $lease->propertyUnit?->uuid,
                'unit_number' => $lease->propertyUnit?->number,
                'tenant_name' => $lease->tenantPerson?->full_name,
                'landlord_name' => $lease->landlordPerson?->full_name,
                'starts_on' => $lease->starts_on?->toDateString(),
                'ends_on' => $lease->ends_on?->toDateString(),
                'status' => $lease->status?->value,
                'status_label' => $lease->status?->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Las unidades que alcanza la persona, con la relacion tipada que lo
     * une a cada una.
     *
     * @return list<array<string, mixed>>
     */
    protected function unitsReached(Person $person): array
    {
        $units = [];

        foreach ($person->ownerships as $ownership) {
            $unit = $ownership->propertyUnit;

            if ($unit === null) {
                continue;
            }

            $units[] = [
                'uuid' => $unit->uuid,
                'number' => $unit->number,
                'relation' => $ownership->ownership_type?->value,
                'relation_label' => $ownership->ownership_type?->label(),
            ];
        }

        foreach ($person->occupancies as $occupancy) {
            $unit = $occupancy->propertyUnit;

            if ($unit === null) {
                continue;
            }

            $units[] = [
                'uuid' => $unit->uuid,
                'number' => $unit->number,
                'relation' => $occupancy->occupancy_type?->value,
                'relation_label' => $occupancy->occupancy_type?->label(),
            ];
        }

        foreach ($person->leases as $lease) {
            $unit = $lease->propertyUnit;

            if ($unit === null) {
                continue;
            }

            $units[] = [
                'uuid' => $unit->uuid,
                'number' => $unit->number,
                'relation' => 'tenant',
                'relation_label' => 'Arrendatario',
            ];
        }

        return $units;
    }

    /**
     * El documento en claro solo con permiso explicito; si no, enmascarado.
     */
    protected function documentNumber(Person $person, User $user, int $complexId): ?string
    {
        $number = $person->document_number;

        if ($number === null) {
            return null;
        }

        if (PermissionService::has($user, ComplexPermission::PERSON_VIEW_DOCUMENT, $complexId)) {
            return $number;
        }

        return $this->mask($number);
    }

    /**
     * Enmascara el documento dejando visibles los ultimos cuatro caracteres.
     */
    protected function mask(string $number): string
    {
        if (strlen($number) <= 4) {
            return str_repeat('*', strlen($number));
        }

        return str_repeat('*', strlen($number) - 4).substr($number, -4);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function documentTypes(): array
    {
        return array_map(
            fn (TipoDocumento $type): array => ['value' => $type->value, 'label' => $type->label()],
            TipoDocumento::cases()
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function statuses(): array
    {
        return array_map(
            fn (PersonStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            PersonStatus::cases()
        );
    }

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 1), 100);
    }
}
