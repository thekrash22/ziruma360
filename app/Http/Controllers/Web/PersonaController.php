<?php

namespace App\Http\Controllers\Web;

use App\Enums\TipoDocumento;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use App\Models\Persona;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class PersonaController extends Controller
{
    /**
     * Display a listing of people.
     */
    public function index(Request $request): Response
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
        $personas = $query->paginate($perPage)->withQueryString();

        $documentTypes = TipoDocumento::cases();

        return Inertia::render('People/Index', [
            'personas' => $personas,
            'documentTypes' => $documentTypes,
            'filters' => $request->only(['search', 'tipo_documento', 'has_user', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new person.
     */
    public function create(): Response
    {
        $documentTypes = TipoDocumento::cases();

        return Inertia::render('People/Create', [
            'documentTypes' => $documentTypes,
        ]);
    }

    /**
     * Store a newly created person.
     */
    public function store(StorePersonaRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        Persona::create($validated);

        return redirect()->route('people.index')
            ->with('success', 'Persona creada exitosamente.');
    }

    /**
     * Display the specified person.
     */
    public function show(Persona $persona): Response
    {
        $persona->load(['unidades', 'user']);

        return Inertia::render('People/Show', [
            'persona' => $persona,
        ]);
    }

    /**
     * Show the form for editing the specified person.
     */
    public function edit(Persona $persona): Response
    {
        $documentTypes = TipoDocumento::cases();

        return Inertia::render('People/Edit', [
            'persona' => $persona,
            'documentTypes' => $documentTypes,
        ]);
    }

    /**
     * Update the specified person.
     */
    public function update(UpdatePersonaRequest $request, Persona $persona): RedirectResponse
    {
        $validated = $request->validated();
        $persona->update($validated);

        return redirect()->route('people.index')
            ->with('success', 'Persona actualizada exitosamente.');
    }

    /**
     * Remove the specified person.
     */
    public function destroy(Persona $persona): RedirectResponse
    {
        // Check for active unit relationships
        if ($persona->unidadesRelaciones()->active()->exists()) {
            return redirect()->back()->with('error', 'No se puede eliminar una persona con relaciones activas a unidades.');
        }

        $persona->delete();

        return redirect()->route('people.index')
            ->with('success', 'Persona eliminada exitosamente.');
    }
}
