<?php

use App\Http\Controllers\Api\Core\ComplexSettingsController;
use App\Http\Controllers\Api\Core\OwnershipController;
use App\Http\Controllers\Api\Core\PersonController;
use App\Http\Controllers\Api\Core\PropertyUnitController;
use App\Http\Controllers\Api\ResidentialComplexController;
use App\Http\Middleware\ResolveActiveComplex;
use App\Http\Middleware\ResolvePropertyUnit;
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
    //
    // DEUDA (fuera del alcance de T17): este grupo sigue sin middleware de
    // sesion, sin `auth` y sin resolucion del conjunto activo, de modo que
    // expone datos sin autenticar. Queda para un work unit propio.
    Route::apiResource('buildings', ResidentialComplexController::class)
        ->parameters(['buildings' => 'building'])
        ->names([
            'index' => 'api.buildings.index',
            'store' => 'api.buildings.store',
            'show' => 'api.buildings.show',
            'update' => 'api.buildings.update',
            'destroy' => 'api.buildings.destroy',
        ]);
});

/*
|--------------------------------------------------------------------------
| API Legacy re-apuntada al dominio nuevo (S39, work unit T17)
|--------------------------------------------------------------------------
|
| `/api/v1/units` y `/api/v1/people` se re-apuntan a `property_units` y
| `people`. Se conservan los paths, los nombres de ruta (`api.units.*`,
| `api.people.*`) y los nombres de parametro de URL (`unidad`, `persona`):
| es un re-apuntado, no un rename.
|
| El middleware es el mismo que `/api/v1/core/*`: sesion, `auth` y
| resolucion del conjunto activo en el servidor contra las membresias
| activas del actor (S9). Antes de este cambio estas rutas exponian datos
| sin autenticar.
|
*/

Route::prefix('v1')
    ->middleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        'auth',
        ResolveActiveComplex::class,
    ])
    ->group(function (): void {
        // Units CRUD (dominio nuevo: property_units)
        Route::apiResource('units', PropertyUnitController::class)
            ->parameters(['units' => 'unidad'])
            ->names([
                'index' => 'api.units.index',
                'store' => 'api.units.store',
                'show' => 'api.units.show',
                'update' => 'api.units.update',
                'destroy' => 'api.units.destroy',
            ]);

        // People CRUD (dominio nuevo: people)
        Route::apiResource('people', PersonController::class)
            ->parameters(['people' => 'persona'])
            ->names([
                'index' => 'api.people.index',
                'store' => 'api.people.store',
                'show' => 'api.people.show',
                'update' => 'api.people.update',
                'destroy' => 'api.people.destroy',
            ]);
    });

/*
|--------------------------------------------------------------------------
| Core API
|--------------------------------------------------------------------------
|
| Superficie del dominio nuevo: personas, unidades, titularidad y politicas
| del conjunto. El conjunto activo se resuelve en el servidor contra las
| membresias activas del actor (S9) y cada recurso se busca dentro de ese
| conjunto (ACE-C01), de modo que un recurso de otro conjunto responde 404.
|
| La superficie legacy (/api/v1/units, /api/v1/people) ya esta re-apuntada al
| dominio nuevo en T17; estas rutas de `/api/v1/core/*` siguen igual.
|
*/

Route::prefix('v1/core')
    ->middleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        'auth',
        ResolveActiveComplex::class,
    ])
    ->group(function (): void {
        Route::get('property-units', [PropertyUnitController::class, 'index'])
            ->name('api.core.property-units.index');

        Route::get('property-units/{propertyUnit}', [PropertyUnitController::class, 'show'])
            ->middleware(ResolvePropertyUnit::class)
            ->name('api.core.property-units.show');

        Route::patch('property-units/{propertyUnit}', [PropertyUnitController::class, 'update'])
            ->middleware(ResolvePropertyUnit::class)
            ->name('api.core.property-units.update');

        Route::get('property-units/{propertyUnit}/ownerships', [OwnershipController::class, 'index'])
            ->middleware(ResolvePropertyUnit::class)
            ->name('api.core.ownerships.index');

        Route::post('property-units/{propertyUnit}/ownerships', [OwnershipController::class, 'store'])
            ->middleware(ResolvePropertyUnit::class)
            ->name('api.core.ownerships.store');

        Route::get('people', [PersonController::class, 'index'])
            ->name('api.core.people.index');

        Route::get('people/{person}', [PersonController::class, 'show'])
            ->name('api.core.people.show');

        Route::get('complex/settings', [ComplexSettingsController::class, 'show'])
            ->name('api.core.settings.show');

        Route::patch('complex/settings', [ComplexSettingsController::class, 'update'])
            ->name('api.core.settings.update');
    });
