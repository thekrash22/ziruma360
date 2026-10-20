<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use App\Http\Resources\PersonaResource;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonaController extends Controller
{
    /**
     * Display a listing of people.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Persona::query();

        // Filter by document type
        if ($request->has('tipo_documento')) {
            $query->where('tipo_documento', $request->input('tipo_documento'));
        }

        // Filter by has user account
        if ($request->has('has_user')) {
            $hasUser = filter_var($request->input('has_user'), FILTER_VALIDATE_BOOLEAN);
            if ($hasUser) {
                $query->whereNotNull('user_id');
            } else {
                $query->whereNull('user_id');
            }
        }

        // Search by name or document number
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->search($search);
        }

        // Order by
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = $request->input('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        // Paginate
        $perPage = min($request->input('per_page', 15), 100);
        $personas = $query->paginate($perPage);

        return PersonaResource::collection($personas);
    }

    /**
     * Store a newly created person.
     */
    public function store(StorePersonaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $persona = Persona::create($validated);

        return (new PersonaResource($persona))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified person.
     */
    public function show(Persona $persona): PersonaResource
    {
        $persona->load(['unidades', 'user']);

        return new PersonaResource($persona);
    }

    /**
     * Update the specified person.
     */
    public function update(UpdatePersonaRequest $request, Persona $persona): PersonaResource
    {
        $validated = $request->validated();
        $persona->update($validated);

        return new PersonaResource($persona);
    }

    /**
     * Remove the specified person.
     */
    public function destroy(Persona $persona): JsonResponse
    {
        // Check for active unit relationships
        if ($persona->unidadesRelaciones()->active()->exists()) {
            abort(422, 'No se puede eliminar una persona con relaciones activas a unidades.');
        }

        $persona->delete();

        return response()->json([
            'message' => 'Persona eliminada exitosamente.',
        ]);
    }
}
