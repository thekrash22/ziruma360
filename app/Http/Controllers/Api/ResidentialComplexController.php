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

class ResidentialComplexController extends Controller
{
    /**
     * Display a listing of buildings.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ResidentialComplex::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by name or code (case-insensitive)
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(name) like ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('lower(code) like ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Order by
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = $request->input('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        // Paginate
        $perPage = min($request->input('per_page', 15), 100);
        $complexes = $query->paginate($perPage);

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
        $validated['status'] = $validated['status'] ?? ResidentialComplexStatus::DRAFT;

        $complex = ResidentialComplex::create($validated);

        return (new ResidentialComplexResource($complex))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified building.
     */
    public function show(ResidentialComplex $building): ResidentialComplexResource
    {
        return new ResidentialComplexResource($building);
    }

    /**
     * Update the specified building.
     */
    public function update(UpdateResidentialComplexRequest $request, ResidentialComplex $building): ResidentialComplexResource
    {
        // Check if code can be changed
        if ($request->has('code') && !$building->canChangeCode()) {
            abort(422, 'No se puede cambiar el código de un conjunto que no está en estado borrador.');
        }

        $validated = $request->validated();
        $building->update($validated);

        return new ResidentialComplexResource($building);
    }

    /**
     * Remove the specified building.
     */
    public function destroy(ResidentialComplex $building): JsonResponse
    {
        // Check if the building can be deleted
        if (!$building->isWritable()) {
            abort(422, 'No se puede eliminar un conjunto suspendido o archivado.');
        }

        // Check for active memberships
        if ($building->memberships()->where('status', 'active')->exists()) {
            abort(422, 'No se puede eliminar un conjunto con membresías activas.');
        }

        $building->delete();

        return response()->json([
            'message' => 'Conjunto eliminado exitosamente.',
        ]);
    }
}
