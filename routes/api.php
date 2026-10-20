<?php

use App\Http\Controllers\Api\PersonaController;
use App\Http\Controllers\Api\ResidentialComplexController;
use App\Http\Controllers\Api\UnidadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    // Buildings (Residential Complexes) CRUD
    // Buildings (Residential Complexes) CRUD
    Route::apiResource('buildings', ResidentialComplexController::class)
        ->parameters(['buildings' => 'building'])
        ->names([
            'index' => 'api.buildings.index',
            'store' => 'api.buildings.store',
            'show' => 'api.buildings.show',
            'update' => 'api.buildings.update',
            'destroy' => 'api.buildings.destroy',
        ]);

    // Units CRUD
    Route::apiResource('units', UnidadController::class)
        ->parameters(['units' => 'unidad'])
        ->names([
            'index' => 'api.units.index',
            'store' => 'api.units.store',
            'show' => 'api.units.show',
            'update' => 'api.units.update',
            'destroy' => 'api.units.destroy',
        ]);

    // People CRUD
    Route::apiResource('people', PersonaController::class)
        ->parameters(['people' => 'persona'])
        ->names([
            'index' => 'api.people.index',
            'store' => 'api.people.store',
            'show' => 'api.people.show',
            'update' => 'api.people.update',
            'destroy' => 'api.people.destroy',
        ]);
});
