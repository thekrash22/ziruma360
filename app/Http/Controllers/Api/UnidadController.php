<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnidadRequest;
use App\Http\Requests\UpdateUnidadRequest;
use App\Http\Resources\UnidadResource;
use App\Models\Unidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnidadController extends Controller
{
    /**
     * Display a listing of units.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Unidad::query()->with('residentialComplex');

        // Filter by residential complex
        if ($request->has('residential_complex_id')) {
            $query->where('residential_complex_id', $request->input('residential_complex_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by tower
        if ($request->has('torre')) {
            $query->where('torre', $request->input('torre'));
        }

        // Filter by floor
        if ($request->has('piso')) {
            $query->where('piso', $request->input('piso'));
        }

        // Search by number
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(numero) like ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Order by
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = $request->input('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        // Paginate
        $perPage = min($request->input('per_page', 15), 100);
        $unidades = $query->paginate($perPage);

        return UnidadResource::collection($unidades);
    }

    /**
     * Store a newly created unit.
     */
    public function store(StoreUnidadRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Set defaults if not provided
        $validated['status'] = $validated['status'] ?? 'draft';

        $unidad = Unidad::create($validated);

        return (new UnidadResource($unidad))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified unit.
     */
    public function show(Unidad $unidad): UnidadResource
    {
        $unidad->load(['residentialComplex', 'personas']);

        return new UnidadResource($unidad);
    }

    /**
     * Update the specified unit.
     */
    public function update(UpdateUnidadRequest $request, Unidad $unidad): UnidadResource
    {
        $validated = $request->validated();
        $unidad->update($validated);

        return new UnidadResource($unidad);
    }

    /**
     * Remove the specified unit.
     */
    public function destroy(Unidad $unidad): JsonResponse
    {
        // Check for active relationships
        if ($unidad->personasRelaciones()->active()->exists()) {
            abort(422, 'No se puede eliminar una unidad con relaciones activas.');
        }

        $unidad->delete();

        return response()->json([
            'message' => 'Unidad eliminada exitosamente.',
        ]);
    }
}
