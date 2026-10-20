<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnidadRequest;
use App\Http\Requests\UpdateUnidadRequest;
use App\Models\ResidentialComplex;
use App\Models\Unidad;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class UnidadController extends Controller
{
    /**
     * Display a listing of units.
     */
    public function index(Request $request): Response
    {
        $query = Unidad::with('residentialComplex');

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
        $unidades = $query->paginate($perPage)->withQueryString();

        // Get complexes for filter dropdown
        $complexes = ResidentialComplex::orderBy('name')->get(['id', 'uuid', 'name', 'code']);

        return Inertia::render('Units/Index', [
            'unidades' => $unidades,
            'complexes' => $complexes,
            'filters' => $request->only(['search', 'status', 'torre', 'residential_complex_id', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new unit.
     */
    public function create(Request $request): Response
    {
        $complexes = ResidentialComplex::orderBy('name')->get(['id', 'uuid', 'name', 'code']);
        $selectedComplex = null;

        if ($request->has('complex')) {
            $selectedComplex = ResidentialComplex::where('uuid', $request->input('complex'))->first();
        }

        return Inertia::render('Units/Create', [
            'complexes' => $complexes,
            'selectedComplex' => $selectedComplex,
        ]);
    }

    /**
     * Store a newly created unit.
     */
    public function store(StoreUnidadRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['status'] = $validated['status'] ?? 'draft';

        $unidad = Unidad::create($validated);

        return redirect()->route('units.index', ['residential_complex_id' => $unidad->residential_complex_id])
            ->with('success', 'Unidad creada exitosamente.');
    }

    /**
     * Display the specified unit.
     */
    public function show(Unidad $unidad): Response
    {
        $unidad->load(['residentialComplex', 'personas']);

        return Inertia::render('Units/Show', [
            'unidad' => $unidad,
        ]);
    }

    /**
     * Show the form for editing the specified unit.
     */
    public function edit(Unidad $unidad): Response
    {
        $complexes = ResidentialComplex::orderBy('name')->get(['id', 'uuid', 'name', 'code']);

        return Inertia::render('Units/Edit', [
            'unidad' => $unidad,
            'complexes' => $complexes,
        ]);
    }

    /**
     * Update the specified unit.
     */
    public function update(UpdateUnidadRequest $request, Unidad $unidad): RedirectResponse
    {
        $validated = $request->validated();
        $unidad->update($validated);

        return redirect()->route('units.index', ['residential_complex_id' => $unidad->residential_complex_id])
            ->with('success', 'Unidad actualizada exitosamente.');
    }

    /**
     * Remove the specified unit.
     */
    public function destroy(Unidad $unidad): RedirectResponse
    {
        // Check for active relationships
        if ($unidad->personasRelaciones()->active()->exists()) {
            return redirect()->back()->with('error', 'No se puede eliminar una unidad con relaciones activas.');
        }

        $complexId = $unidad->residential_complex_id;
        $unidad->delete();

        return redirect()->route('units.index', ['residential_complex_id' => $complexId])
            ->with('success', 'Unidad eliminada exitosamente.');
    }
}
