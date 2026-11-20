<?php

namespace App\Http\Controllers\Api;

use App\Enums\ResidentialComplexStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResidentialComplexRequest;
use App\Http\Requests\UpdateResidentialComplexRequest;
use App\Http\Resources\ResidentialComplexResource;
use App\Models\ResidentialComplex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * API de conjuntos residenciales.
 *
 * La superficie exige sesion y `auth`; el alcance del actor se resuelve en
 * el servidor contra sus membresias activas y cada conjunto fuera de ese
 * alcance responde 404. La baja archiva el conjunto: nunca borra en cascada
 * los datos del conjunto.
 */
class ResidentialComplexController extends Controller
{
    /**
     * Columnas por las que se puede ordenar el listado.
     */
    private const ORDERABLE = ['created_at', 'updated_at', 'name', 'code', 'status'];

    /**
     * Display a listing of buildings.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        abort_if($user === null, 401, 'No autenticado.');

        $filters = Validator::make(
            $request->only(['status', 'search', 'order_by', 'order_dir', 'per_page']),
            [
                'status' => ['sometimes', new Enum(ResidentialComplexStatus::class)],
                'search' => ['sometimes', 'string', 'max:255'],
                'order_by' => ['sometimes', Rule::in(self::ORDERABLE)],
                'order_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]
        )->validate();

        $query = ResidentialComplex::query();

        if (! $user->isPlatformAdmin()) {
            $complexIds = $user->complexes()->distinct()->pluck('residential_complexes.id');

            abort_if($complexIds->isEmpty(), 403, 'No perteneces a ningún conjunto.');

            $query->whereIn('id', $complexIds);
        }

        // Filter by status if provided
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Search by name or code (case-insensitive)
        if (isset($filters['search'])) {
            $search = mb_strtolower($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(name) like ?', ['%'.$search.'%'])
                    ->orWhereRaw('lower(code) like ?', ['%'.$search.'%']);
            });
        }

        // Order by
        $query->orderBy($filters['order_by'] ?? 'created_at', $filters['order_dir'] ?? 'desc');

        // Paginate
        $complexes = $query->paginate((int) ($filters['per_page'] ?? 15));

        return ResidentialComplexResource::collection($complexes);
    }

    /**
     * Store a newly created building.
     */
    public function store(StoreResidentialComplexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Set defaults if not provided
        $validated['country_code'] = $validated['country_code'] ?? 'CO';
        $validated['timezone'] = $validated['timezone'] ?? 'America/Bogota';
        $validated['currency_code'] = $validated['currency_code'] ?? 'COP';
        $validated['status'] = ResidentialComplexStatus::DRAFT;

        $complex = ResidentialComplex::create($validated);

        return (new ResidentialComplexResource($complex))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified building.
     */
    public function show(Request $request): ResidentialComplexResource
    {
        $complex = $this->complex($request);

        abort_unless(
            Gate::allows('view', $complex),
            403,
            'No tienes permiso para consultar el conjunto.'
        );

        return new ResidentialComplexResource($complex);
    }

    /**
     * Update the specified building.
     *
     * El estado del conjunto es un atributo de plataforma (spec S65): solo la
     * plataforma lo cambia, y solo por las transiciones validas de la
     * tabla de estados (spec S67). Un conjunto no activable responde 422
     * con la lista de lo que le falta (spec S68, ACE-C03).
     */
    public function update(UpdateResidentialComplexRequest $request): ResidentialComplexResource
    {
        $complex = $this->complex($request);

        $validated = $request->validated();

        $target = $this->targetStatus($validated);

        // Un conjunto suspendido o archivado no es escribible: lo unico que
        // admite es un cambio real de estado (la reactivacion por la
        // plataforma). Repetir su propio estado tampoco es una escritura
        // admitida.
        if (! $complex->isWritable() && ! $this->isStatusChange($target, $complex)) {
            abort(422, 'No se puede editar un conjunto suspendido o archivado.');
        }

        if ($target !== null && $target !== $complex->status) {
            abort_unless(
                $complex->status->canTransitionTo($target),
                422,
                sprintf('No se puede pasar de «%s» a «%s».', $complex->status->value, $target->value)
            );

            if ($target === ResidentialComplexStatus::ACTIVE) {
                $blockers = $complex->activationBlockers();

                abort_if(
                    $blockers !== [],
                    422,
                    'No se puede activar el conjunto. '.implode(' ', $blockers)
                );
            }
        }

        // Check if code can be changed
        if ($request->has('code') && ! $complex->canChangeCode()) {
            abort(422, 'No se puede cambiar el código de un conjunto que no está en estado borrador.');
        }

        $complex->update($validated);

        return new ResidentialComplexResource($complex->fresh());
    }

    /**
     * Estado destino de la peticion, si el payload propone uno.
     *
     * @param  array<string, mixed>  $validated
     */
    private function targetStatus(array $validated): ?ResidentialComplexStatus
    {
        if (! array_key_exists('status', $validated)) {
            return null;
        }

        $value = $validated['status'];

        if ($value instanceof ResidentialComplexStatus) {
            return $value;
        }

        return is_string($value) ? ResidentialComplexStatus::tryFrom($value) : null;
    }

    /**
     * El payload propone un cambio real de estado.
     */
    private function isStatusChange(?ResidentialComplexStatus $target, ResidentialComplex $complex): bool
    {
        return $target !== null && $target !== $complex->status;
    }

    /**
     * Archive the specified building.
     *
     * La baja nunca borra: el conjunto pasa al estado terminal `archived` y
     * sus datos (personas, unidades, titularidades, arrendamientos y
     * auditoria) se conservan.
     */
    public function destroy(Request $request): ResidentialComplexResource
    {
        $complex = $this->complex($request);

        abort_unless(
            Gate::allows('archive', $complex),
            403,
            'No tienes permiso para archivar el conjunto.'
        );

        abort_unless(
            $complex->isWritable(),
            422,
            'No se puede archivar un conjunto suspendido o archivado.'
        );

        $complex->update(['status' => ResidentialComplexStatus::ARCHIVED]);

        return new ResidentialComplexResource($complex->fresh());
    }

    /**
     * Conjunto ya resuelto y verificado por el middleware.
     */
    private function complex(Request $request): ResidentialComplex
    {
        $complex = $request->attributes->get('residential_complex');

        abort_if(! $complex instanceof ResidentialComplex, 404, 'Conjunto no encontrado.');

        return $complex;
    }
}
