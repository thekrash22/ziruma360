<?php

namespace App\Http\Controllers\Web\Core;

use App\Enums\ComplexPermission;
use App\Enums\PropertyUnitStatus;
use App\Enums\PropertyUnitType;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingFloor;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitOwnership;
use App\Models\UnitLease;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Policies\PropertyUnitPolicy;
use App\Services\ActiveComplexResolver;
use App\Services\PermissionService;
use Closure;
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
 * Unidades del conjunto en la UI web (spec S38, §3.5, §6 "Alcance").
 *
 * La UI web se sirve sobre el dominio nuevo (`property_units`), no sobre el
 * pivot legacy `persona_unidad`: las relaciones de la unidad se muestran
 * tipadas (titularidad, ocupacion y arrendamiento).
 *
 * El conjunto activo lo resuelve el middleware `ResolveActiveComplex` en el
 * servidor, contra las membresias activas del actor; nunca se toma del
 * cliente. Un recurso de otro conjunto no se resuelve (404) y no se filtra
 * su existencia (ACE-C01).
 */
class PropertyUnitController extends Controller
{
    /**
     * Campos que el administrador del conjunto puede modificar.
     */
    private const ADMIN_FIELDS = [
        'number',
        'unit_type',
        'status',
        'building_id',
        'building_floor_id',
        'internal_code',
        'area_m2',
        'coefficient',
        'notes',
    ];

    /**
     * Campos que el propietario de la unidad puede modificar ("datos
     * limitados" en la matriz de permisos).
     */
    private const OWNER_FIELDS = ['notes'];

    /**
     * Listado de unidades del conjunto activo.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $complexId = $this->complexId($request);

        abort_unless(
            $this->policy()->viewAny($user, $complexId),
            403,
            'No tienes permiso para consultar las unidades del conjunto.'
        );

        $query = PropertyUnit::query()
            ->forComplex($complexId)
            ->with(['building', 'buildingFloor']);

        // El administrador del conjunto ve todas sus unidades; el resto ve
        // solo las que le alcanzan por titularidad, ocupacion o
        // autorizacion vigente (spec §6 "Alcance").
        if (! PermissionService::has($user, ComplexPermission::UNIT_EDIT, $complexId)) {
            $query = $query->forActor($user, $complexId);
        }

        $units = $query
            ->when($request->input('search'), fn ($query, $search) => $query->where('number', 'like', "%{$search}%"))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('unit_type'), fn ($query, $type) => $query->where('unit_type', $type))
            ->orderBy('number')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $units->getCollection()->transform(fn (PropertyUnit $unit): array => $this->summary($unit));

        return Inertia::render('Units/Index', [
            'units' => $units,
            'filters' => $request->only(['search', 'status', 'unit_type']),
            'unitTypes' => $this->unitTypes(),
            'statuses' => $this->statuses(),
            'canDelete' => PermissionService::has($user, ComplexPermission::UNIT_DELETE, $complexId),
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
            PermissionService::has($user, ComplexPermission::UNIT_CREATE, $complexId),
            403,
            'No tienes permiso para crear unidades en este conjunto.'
        );

        return Inertia::render('Units/Create', [
            'unitTypes' => $this->unitTypes(),
            'statuses' => $this->statuses(),
            'buildings' => $this->buildings($complexId),
        ]);
    }

    /**
     * Alta de una unidad en el conjunto activo.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $complexId = $this->complexId($request);

        abort_unless(
            PermissionService::has($user, ComplexPermission::UNIT_CREATE, $complexId),
            403,
            'No tienes permiso para crear unidades en este conjunto.'
        );

        try {
            $validated = $this->validateUnit($request, $complexId);
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        }

        PropertyUnit::create(array_merge($validated, [
            'residential_complex_id' => $complexId,
            'status' => $validated['status'] ?? PropertyUnitStatus::ACTIVE->value,
        ]));

        return redirect()->route('units.index')
            ->with('success', 'Unidad creada exitosamente.');
    }

    /**
     * Detalle de la unidad con sus relaciones tipadas.
     */
    public function show(Request $request, string $uuid): Response
    {
        $user = $request->user();
        $complexId = $this->complexId($request);
        $unit = $this->resolveUnit($complexId, $uuid);

        abort_unless(Gate::allows('view', $unit), 403, 'No tienes permiso para consultar esta unidad.');

        return Inertia::render('Units/Show', [
            'unit' => $this->detail($unit),
        ] + $this->flashProps($request));
    }

    /**
     * Formulario de edicion.
     */
    public function edit(Request $request, string $uuid): Response
    {
        $user = $request->user();
        $complexId = $this->complexId($request);
        $unit = $this->resolveUnit($complexId, $uuid);

        abort_unless(Gate::allows('update', $unit), 403, 'No tienes permiso para editar esta unidad.');

        return Inertia::render('Units/Edit', [
            'unit' => $this->summary($unit),
            'unitTypes' => $this->unitTypes(),
            'statuses' => $this->statuses(),
            'buildings' => $this->buildings($complexId),
            'editableFields' => $this->updatableFields($request),
        ]);
    }

    /**
     * Actualizacion de la unidad.
     */
    public function update(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $complexId = $this->complexId($request);
        $unit = $this->resolveUnit($complexId, $uuid);

        abort_unless(Gate::allows('update', $unit), 403, 'No tienes permiso para editar esta unidad.');

        try {
            $validated = $this->validateUnit($request, $complexId, $unit);
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        }

        $forbidden = array_diff(array_keys($validated), $this->updatableFields($request));

        abort_if(
            $forbidden !== [],
            422,
            'No puedes modificar estos campos: '.implode(', ', $forbidden).'.'
        );

        $unit->fill($validated)->save();

        return redirect()->route('units.index')
            ->with('success', 'Unidad actualizada exitosamente.');
    }

    /**
     * Baja controlada: la unidad se desactiva, no se borra (RN-S04/RN-S02).
     */
    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        $user = $request->user();
        $complexId = $this->complexId($request);
        $unit = $this->resolveUnit($complexId, $uuid);

        abort_unless(
            $this->canDeactivate($user, $unit),
            403,
            'No tienes permiso para desactivar esta unidad.'
        );

        $unit->update(['status' => PropertyUnitStatus::INACTIVE->value]);

        return redirect()->route('units.index')
            ->with('success', 'Unidad desactivada exitosamente.');
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

    protected function policy(): PropertyUnitPolicy
    {
        return app(PropertyUnitPolicy::class);
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
     * La unidad dentro del conjunto activo; si no existe, 404 (ACE-C01).
     */
    protected function resolveUnit(int $complexId, string $value): PropertyUnit
    {
        abort_if(! Str::isUuid($value), 404, 'Unidad no encontrada.');

        $unit = PropertyUnit::query()
            ->forComplex($complexId)
            ->where('uuid', $value)
            ->first();

        abort_if($unit === null, 404, 'Unidad no encontrada.');

        return $unit;
    }

    /**
     * Campos que el actor puede modificar en una unidad.
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

    /**
     * Desactivar exige el permiso de baja y el alcance sobre la unidad.
     */
    protected function canDeactivate(User $user, PropertyUnit $unit): bool
    {
        $complexId = (int) $unit->residential_complex_id;

        if (! PermissionService::has($user, ComplexPermission::UNIT_DELETE, $complexId)) {
            return false;
        }

        $policy = $this->policy();

        return $policy->managesWholeComplex($user, $complexId) || $policy->withinScope($user, $unit);
    }

    /**
     * Validacion de los campos reales del dominio (`property_units`).
     *
     * @return array<string, mixed>
     */
    protected function validateUnit(Request $request, int $complexId, ?PropertyUnit $unit = null): array
    {
        $validated = $request->validate([
            'number' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($request, $complexId, $unit): void {
                    if (! $this->numberIsAvailable($complexId, $request, (string) $value, $unit)) {
                        $fail('Ya existe una unidad con ese numero en el conjunto.');
                    }
                },
            ],
            'unit_type' => ['required', Rule::in(array_column(PropertyUnitType::cases(), 'value'))],
            'status' => ['nullable', Rule::in(array_column(PropertyUnitStatus::cases(), 'value'))],
            'building_id' => [
                'nullable',
                'integer',
                Rule::exists('buildings', 'id')->where('residential_complex_id', $complexId),
            ],
            'building_floor_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($request, $complexId): void {
                    if ($value === null) {
                        return;
                    }

                    $floor = BuildingFloor::find($value);

                    if ($floor === null) {
                        $fail('El piso seleccionado no existe.');

                        return;
                    }

                    $buildingId = $request->input('building_id');

                    if ($buildingId === null) {
                        $fail('El piso seleccionado exige un edificio.');

                        return;
                    }

                    $building = Building::query()
                        ->forComplex($complexId)
                        ->find($buildingId);

                    if ($building === null || (int) $floor->building_id !== (int) $building->id) {
                        $fail('El piso seleccionado no pertenece al edificio.');
                    }
                },
            ],
            'internal_code' => ['nullable', 'string', 'max:255'],
            'area_m2' => ['nullable', 'numeric', 'min:0'],
            'coefficient' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return array_merge($validated, [
            'building_id' => $validated['building_id'] ?? null,
            'building_floor_id' => $validated['building_floor_id'] ?? null,
        ]);
    }

    /**
     * Unicidad del numero por (conjunto, edificio, tipo); el motor trata los
     * nulos como distintos solo si no se usa `NULLS NOT DISTINCT`, asi que la
     * comprobacion es explicita.
     */
    protected function numberIsAvailable(int $complexId, Request $request, string $number, ?PropertyUnit $unit): bool
    {
        $unitType = $request->input('unit_type');

        if (! is_string($unitType) || $unitType === '') {
            return true;
        }

        $buildingId = $request->input('building_id');

        return ! PropertyUnit::query()
            ->withoutGlobalScope('complex')
            ->where('residential_complex_id', $complexId)
            ->where('unit_type', $unitType)
            ->where('number', $number)
            ->when(
                $buildingId === null,
                fn ($query) => $query->whereNull('building_id'),
                fn ($query) => $query->where('building_id', $buildingId)
            )
            ->when($unit !== null, fn ($query) => $query->whereKeyNot($unit->getKey()))
            ->exists();
    }

    /**
     * Proyeccion de la unidad para el listado.
     *
     * @return array<string, mixed>
     */
    protected function summary(PropertyUnit $unit): array
    {
        return [
            'uuid' => $unit->uuid,
            'number' => $unit->number,
            'unit_type' => $unit->unit_type?->value,
            'unit_type_label' => $unit->unit_type?->label(),
            'status' => $unit->status?->value,
            'status_label' => $unit->status?->label(),
            'internal_code' => $unit->internal_code,
            'area_m2' => $unit->area_m2,
            'building_id' => $unit->building_id,
            'building_floor_id' => $unit->building_floor_id,
            'building' => $unit->building === null ? null : [
                'uuid' => $unit->building->uuid,
                'name' => $unit->building->name,
                'code' => $unit->building->code,
            ],
            'building_floor' => $unit->buildingFloor === null ? null : [
                'uuid' => $unit->buildingFloor->uuid,
                'floor_number' => $unit->buildingFloor->floor_number,
                'name' => $unit->buildingFloor->name,
                'display_name' => $unit->buildingFloor->display_name,
            ],
            'created_at' => $unit->created_at?->toIso8601String(),
        ];
    }

    /**
     * Proyeccion de la unidad con sus relaciones tipadas.
     *
     * @return array<string, mixed>
     */
    protected function detail(PropertyUnit $unit): array
    {
        $unit->loadMissing(['ownerships.person', 'occupancies.person', 'leases.tenantPerson', 'leases.landlordPerson']);

        return array_merge($this->summary($unit), [
            'coefficient' => $unit->coefficient,
            'notes' => $unit->notes,
            'updated_at' => $unit->updated_at?->toIso8601String(),
            'ownerships' => $unit->ownerships->map(fn (PropertyUnitOwnership $ownership): array => [
                'uuid' => $ownership->uuid,
                'person_uuid' => $ownership->person?->uuid,
                'person_name' => $ownership->person?->full_name,
                'ownership_type' => $ownership->ownership_type?->value,
                'ownership_type_label' => $ownership->ownership_type?->label(),
                'percentage' => $ownership->percentage,
                'valid_from' => $ownership->valid_from?->toDateString(),
                'valid_until' => $ownership->valid_until?->toDateString(),
                'status' => $ownership->status?->value,
            ])->values()->all(),
            'occupancies' => $unit->occupancies->map(fn (UnitOccupancy $occupancy): array => [
                'uuid' => $occupancy->uuid,
                'person_uuid' => $occupancy->person?->uuid,
                'person_name' => $occupancy->person?->full_name,
                'occupancy_type' => $occupancy->occupancy_type?->value,
                'occupancy_type_label' => $occupancy->occupancy_type?->label(),
                'is_primary_contact' => $occupancy->is_primary_contact,
                'valid_from' => $occupancy->valid_from?->toDateString(),
                'valid_until' => $occupancy->valid_until?->toDateString(),
                'status' => $occupancy->status?->value,
            ])->values()->all(),
            'leases' => $unit->leases->map(fn (UnitLease $lease): array => [
                'uuid' => $lease->uuid,
                'tenant_uuid' => $lease->tenantPerson?->uuid,
                'tenant_name' => $lease->tenantPerson?->full_name,
                'landlord_uuid' => $lease->landlordPerson?->uuid,
                'landlord_name' => $lease->landlordPerson?->full_name,
                'starts_on' => $lease->starts_on?->toDateString(),
                'ends_on' => $lease->ends_on?->toDateString(),
                'status' => $lease->status?->value,
                'status_label' => $lease->status?->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Edificios del conjunto con sus pisos (para el formulario).
     *
     * @return list<array<string, mixed>>
     */
    protected function buildings(int $complexId): array
    {
        return Building::query()
            ->forComplex($complexId)
            ->with(['floors' => fn ($query) => $query->orderBy('floor_number')])
            ->orderBy('name')
            ->get()
            ->map(fn (Building $building): array => [
                'id' => $building->id,
                'uuid' => $building->uuid,
                'name' => $building->name,
                'code' => $building->code,
                'floors' => $building->floors->map(fn (BuildingFloor $floor): array => [
                    'id' => $floor->id,
                    'uuid' => $floor->uuid,
                    'floor_number' => $floor->floor_number,
                    'name' => $floor->name,
                    'display_name' => $floor->display_name,
                ])->values()->all(),
            ])->values()->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function unitTypes(): array
    {
        return array_map(
            fn (PropertyUnitType $type): array => ['value' => $type->value, 'label' => $type->label()],
            PropertyUnitType::cases()
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function statuses(): array
    {
        return array_map(
            fn (PropertyUnitStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            PropertyUnitStatus::cases()
        );
    }

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 1), 100);
    }
}
