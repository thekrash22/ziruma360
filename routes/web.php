<?php

use App\Enums\MembershipStatus;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\BuildingController;
use App\Http\Controllers\Web\ComplexController;
use App\Http\Controllers\Web\Core\PersonController;
use App\Http\Controllers\Web\Core\PropertyUnitController;
use App\Http\Middleware\ResolveActiveComplex;
use App\Models\Building;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplexMembership;
use App\Services\ComplexContext;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

/*
| Dashboard: los conteos reales del conjunto activo (spec S95).
|
| El dashboard dejo de ser mock: en vez de las cifras de ejemplo del
| template muestra los conteos reales del conjunto activo (edificios,
| unidades, personas y miembros activos). Cuando no hay conjunto activo no
| inventa cifras: explica que hay que elegir uno.
*/
Route::get('/dashboard', function () {
    $complex = ComplexContext::getCurrentComplex();

    $stats = $complex === null ? null : [
        'buildings' => Building::forComplex($complex->id)->count(),
        'units' => PropertyUnit::forComplex($complex->id)->count(),
        'people' => Person::forComplex($complex->id)->count(),
        'members' => ResidentialComplexMembership::query()
            ->where('residential_complex_id', $complex->id)
            ->where('status', MembershipStatus::ACTIVE->value)
            ->count(),
    ];

    return Inertia::render('Dashboard', [
        'stats' => $stats,
        'needsComplex' => $complex === null,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Buildings CRUD
    Route::get('/buildings', [BuildingController::class, 'index'])->name('buildings.index');
    Route::get('/buildings/create', [BuildingController::class, 'create'])->name('buildings.create');
    Route::post('/buildings', [BuildingController::class, 'store'])->name('buildings.store');
    Route::get('/buildings/{uuid}', [BuildingController::class, 'show'])->name('buildings.show');
    Route::get('/buildings/{uuid}/edit', [BuildingController::class, 'edit'])->name('buildings.edit');
    Route::put('/buildings/{uuid}', [BuildingController::class, 'update'])->name('buildings.update');
    Route::delete('/buildings/{uuid}', [BuildingController::class, 'destroy'])->name('buildings.destroy');
    Route::post('/buildings/{uuid}/floors/generate', [BuildingController::class, 'generateFloors'])->name('buildings.floors.generate');

    /*
    | Complexes: la UI del tenant root (spec S94).
    |
    | El conjunto tiene nombre propio en la web. Estas rutas no pasan por
    | `ResolveActiveComplex`: la pantalla de conjuntos muestra los conjuntos
    | del actor, no solo el activo, y por ahi se elige el conjunto activo
    | (`POST /complexes/{complex}/select`).
    */
    Route::get('/complexes', [ComplexController::class, 'index'])->name('complexes.index');
    Route::get('/complexes/create', [ComplexController::class, 'create'])->name('complexes.create');
    Route::post('/complexes', [ComplexController::class, 'store'])->name('complexes.store');
    Route::get('/complexes/{complex}', [ComplexController::class, 'show'])->name('complexes.show');
    Route::post('/complexes/{complex}/select', [ComplexController::class, 'select'])->name('complexes.select');

    /*
    | Units CRUD: dominio nuevo (property_units).
    |
    | El conjunto activo se resuelve en el servidor contra las membresias
    | activas del actor (S9); nunca se toma del cliente. Los controllers
    | legacy siguen vivos hasta que la unidad de retiro los elimine.
    */
    Route::middleware(ResolveActiveComplex::class)->group(function (): void {
        Route::get('/units', [PropertyUnitController::class, 'index'])->name('units.index');
        Route::get('/units/create', [PropertyUnitController::class, 'create'])->name('units.create');
        Route::post('/units', [PropertyUnitController::class, 'store'])->name('units.store');
        Route::get('/units/{uuid}', [PropertyUnitController::class, 'show'])->name('units.show');
        Route::get('/units/{uuid}/edit', [PropertyUnitController::class, 'edit'])->name('units.edit');
        Route::put('/units/{uuid}', [PropertyUnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{uuid}', [PropertyUnitController::class, 'destroy'])->name('units.destroy');
    });

    /*
    | People CRUD: dominio nuevo (people).
    |
    | Mismo criterio de conjunto activo que las unidades.
    */
    Route::middleware(ResolveActiveComplex::class)->group(function (): void {
        Route::get('/people', [PersonController::class, 'index'])->name('people.index');
        Route::get('/people/create', [PersonController::class, 'create'])->name('people.create');
        Route::post('/people', [PersonController::class, 'store'])->name('people.store');
        Route::get('/people/{uuid}', [PersonController::class, 'show'])->name('people.show');
        Route::get('/people/{uuid}/edit', [PersonController::class, 'edit'])->name('people.edit');
        Route::put('/people/{uuid}', [PersonController::class, 'update'])->name('people.update');
        Route::delete('/people/{uuid}', [PersonController::class, 'destroy'])->name('people.destroy');
    });
});

require __DIR__.'/auth.php';
