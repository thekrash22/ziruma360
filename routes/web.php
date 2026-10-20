<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\PersonaController;
use App\Http\Controllers\Web\UnidadController;
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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Units CRUD
    Route::get('/units', [UnidadController::class, 'index'])->name('units.index');
    Route::get('/units/create', [UnidadController::class, 'create'])->name('units.create');
    Route::post('/units', [UnidadController::class, 'store'])->name('units.store');
    Route::get('/units/{uuid}', [UnidadController::class, 'show'])->name('units.show');
    Route::get('/units/{uuid}/edit', [UnidadController::class, 'edit'])->name('units.edit');
    Route::put('/units/{uuid}', [UnidadController::class, 'update'])->name('units.update');
    Route::delete('/units/{uuid}', [UnidadController::class, 'destroy'])->name('units.destroy');

    // People CRUD
    Route::get('/people', [PersonaController::class, 'index'])->name('people.index');
    Route::get('/people/create', [PersonaController::class, 'create'])->name('people.create');
    Route::post('/people', [PersonaController::class, 'store'])->name('people.store');
    Route::get('/people/{uuid}', [PersonaController::class, 'show'])->name('people.show');
    Route::get('/people/{uuid}/edit', [PersonaController::class, 'edit'])->name('people.edit');
    Route::put('/people/{uuid}', [PersonaController::class, 'update'])->name('people.update');
    Route::delete('/people/{uuid}', [PersonaController::class, 'destroy'])->name('people.destroy');
});

require __DIR__.'/auth.php';
