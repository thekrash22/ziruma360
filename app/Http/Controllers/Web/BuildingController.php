<?php

namespace App\Http\Controllers\Web;

use App\Enums\BuildingStatus;
use App\Enums\BuildingType;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingFloor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Validator;

class BuildingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $buildings = Building::with(['floors'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->when($request->building_type, function ($query, $type) {
                $query->where('building_type', $type);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        // Transform for Vue
        $buildings->getCollection()->transform(function ($building) {
            return [
                'id' => $building->id,
                'uuid' => $building->uuid,
                'name' => $building->name,
                'code' => $building->code,
                'building_type' => $building->building_type->value,
                'building_type_label' => $building->building_type->label(),
                'floor_count' => $building->floor_count,
                'has_elevators' => $building->has_elevators,
                'elevator_count' => $building->elevator_count,
                'status' => $building->status->value,
                'status_label' => $building->status->label(),
                'floors_count' => $building->floors->count(),
                'created_at' => $building->created_at->toISOString(),
            ];
        });

        return Inertia::render('Buildings/Index', [
            'buildings' => $buildings,
            'filters' => $request->only(['search', 'building_type', 'status']),
            'buildingTypes' => array_map(fn($t) => ['value' => $t->value, 'label' => $t->label()], BuildingType::cases()),
            'statuses' => array_map(fn($s) => ['value' => $s->value, 'label' => $s->label()], BuildingStatus::cases()),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Buildings/Create', [
            'buildingTypes' => array_map(fn($t) => ['value' => $t->value, 'label' => $t->label()], BuildingType::cases()),
            'statuses' => array_map(fn($s) => ['value' => $s->value, 'label' => $s->label()], BuildingStatus::cases()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'building_type' => 'required|string',
            'floor_count' => 'nullable|integer|min:1|max:100',
            'apartments_per_floor_reference' => 'nullable|integer|min:1|max:50',
            'has_elevators' => 'boolean',
            'elevator_count' => 'nullable|integer|min:1|max:20',
            'status' => 'string',
            'metadata' => 'nullable|array',
        ]);

        // Validate elevator constraint
        if ($validated['has_elevators'] ?? false) {
            $validator = Validator::make($validated, [
                'elevator_count' => 'required|integer|min:1',
            ]);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        Building::create($validated);

        return redirect()->route('buildings.index')
            ->with('success', 'Edificio creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Building $building): Response
    {
        $building->load(['floors' => function ($query) {
            $query->orderBy('floor_number');
        }]);

        return Inertia::render('Buildings/Show', [
            'building' => [
                'id' => $building->id,
                'uuid' => $building->uuid,
                'name' => $building->name,
                'code' => $building->code,
                'building_type' => $building->building_type->value,
                'building_type_label' => $building->building_type->label(),
                'floor_count' => $building->floor_count,
                'apartments_per_floor_reference' => $building->apartments_per_floor_reference,
                'has_elevators' => $building->has_elevators,
                'elevator_count' => $building->elevator_count,
                'status' => $building->status->value,
                'status_label' => $building->status->label(),
                'created_at' => $building->created_at->toISOString(),
                'updated_at' => $building->updated_at->toISOString(),
                'floors' => $building->floors->map(fn($floor) => [
                    'id' => $floor->id,
                    'uuid' => $floor->uuid,
                    'floor_number' => $floor->floor_number,
                    'floor_type' => $floor->floor_type->value,
                    'floor_type_label' => $floor->floor_type->label(),
                    'name' => $floor->name,
                    'display_name' => $floor->display_name,
                    'status' => $floor->status,
                    'units_count' => $floor->units->count(),
                ]),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Building $building): Response
    {
        return Inertia::render('Buildings/Edit', [
            'building' => [
                'id' => $building->id,
                'uuid' => $building->uuid,
                'name' => $building->name,
                'code' => $building->code,
                'building_type' => $building->building_type->value,
                'floor_count' => $building->floor_count,
                'apartments_per_floor_reference' => $building->apartments_per_floor_reference,
                'has_elevators' => $building->has_elevators,
                'elevator_count' => $building->elevator_count,
                'status' => $building->status->value,
            ],
            'buildingTypes' => array_map(fn($t) => ['value' => $t->value, 'label' => $t->label()], BuildingType::cases()),
            'statuses' => array_map(fn($s) => ['value' => $s->value, 'label' => $s->label()], BuildingStatus::cases()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Building $building): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'building_type' => 'required|string',
            'floor_count' => 'nullable|integer|min:1|max:100',
            'apartments_per_floor_reference' => 'nullable|integer|min:1|max:50',
            'has_elevators' => 'boolean',
            'elevator_count' => 'nullable|integer|min:1|max:20',
            'status' => 'string',
            'metadata' => 'nullable|array',
        ]);

        // Validate elevator constraint
        if ($validated['has_elevators'] ?? false) {
            $validator = Validator::make($validated, [
                'elevator_count' => 'required|integer|min:1',
            ]);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        $building->update($validated);

        return redirect()->route('buildings.show', $building->uuid)
            ->with('success', 'Edificio actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Building $building): RedirectResponse
    {
        // Soft delete by setting status to inactive
        $building->update(['status' => BuildingStatus::INACTIVE->value]);

        return redirect()->route('buildings.index')
            ->with('success', 'Edificio desactivado exitosamente.');
    }

    /**
     * Generate floors for a building.
     */
    public function generateFloors(Request $request, Building $building): RedirectResponse
    {
        $validated = $request->validate([
            'start_floor' => 'required|integer|min:-10|max:100',
            'end_floor' => 'required|integer|gte:start_floor|max:100',
            'basement_floors' => 'nullable|integer|min:0|max:10',
        ]);

        $floors = [];
        $floorNumber = $validated['start_floor'];

        // Generate basement floors first (negative numbers)
        $basementCount = $validated['basement_floors'] ?? 0;
        for ($i = 1; $i <= $basementCount; $i++) {
            $floors[] = [
                'building_id' => $building->id,
                'floor_number' => -$i,
                'floor_type' => 'basement',
                'name' => "Sótano {$i}",
                'status' => 'active',
            ];
        }

        // Generate floors from start to end
        for ($f = $validated['start_floor']; $f <= $validated['end_floor']; $f++) {
            if ($f === 0) {
                continue; // Skip ground floor (usually)
            }
            $floors[] = [
                'building_id' => $building->id,
                'floor_number' => $f,
                'floor_type' => 'residential',
                'name' => $f > 0 ? "Piso {$f}" : null,
                'status' => 'active',
            ];
        }

        BuildingFloor::insert($floors);

        return redirect()->route('buildings.show', $building->uuid)
            ->with('success', count($floors) . ' pisos creados exitosamente.');
    }
}
