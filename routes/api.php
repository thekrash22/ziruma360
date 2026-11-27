<?php

use App\Http\Controllers\Api\Core\AuditLogController;
use App\Http\Controllers\Api\Core\ComplexController;
use App\Http\Controllers\Api\Core\ComplexSettingsController;
use App\Http\Controllers\Api\Core\MembershipController;
use App\Http\Controllers\Api\Core\OwnershipController;
use App\Http\Controllers\Api\Core\PersonController;
use App\Http\Controllers\Api\Core\PropertyUnitController;
use App\Http\Controllers\Api\Core\RoleController;
use App\Http\Middleware\ResolveActiveComplex;
use App\Http\Middleware\ResolvePropertyUnit;
use App\Http\Middleware\ResolveResidentialComplex;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
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

/*
|--------------------------------------------------------------------------
| Conjuntos residenciales
|--------------------------------------------------------------------------
|
| `/api/v1/complexes` expone los conjuntos residenciales (`ResidentialComplex`).
| El conjunto es el tenant: tiene superficie propia y nombre propio (S90), y
| `/api/v1/buildings` queda libre para la API de edificios (T24). La
| superficie exige sesion, cookies, CSRF y `auth`: el alcance del actor se
| resuelve en el servidor contra sus membresias activas (o el catalogo
| completo para la plataforma) y un conjunto fuera de ese alcance responde
| 404, sin filtrar su existencia. La baja archiva el conjunto: nunca borra en
| cascada sus datos.
|
| El conjunto activo se elige de forma explicita (S93):
| `POST /api/v1/complexes/{complex}/select` lo fija en la sesion. Solo se
| puede elegir un conjunto del que el actor es miembro activo.
|
| El parametro de ruta de la ficha es `complex`, como el recurso: el
| conjunto no se llama `building` en ningun sitio de su propia superficie.
|
*/

Route::prefix('v1')
    ->middleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ValidateCsrfToken::class,
        'auth',
    ])
    ->group(function (): void {
        Route::get('complexes', [ComplexController::class, 'index'])
            ->name('api.complexes.index');

        Route::post('complexes', [ComplexController::class, 'store'])
            ->name('api.complexes.store');

        Route::post('complexes/{complex}/select', [ComplexController::class, 'select'])
            ->name('api.complexes.select');

        Route::get('complexes/{complex}', [ComplexController::class, 'show'])
            ->middleware(ResolveResidentialComplex::class)
            ->name('api.complexes.show');

        Route::match(['put', 'patch'], 'complexes/{complex}', [ComplexController::class, 'update'])
            ->middleware(ResolveResidentialComplex::class)
            ->name('api.complexes.update');

        Route::delete('complexes/{complex}', [ComplexController::class, 'destroy'])
            ->middleware(ResolveResidentialComplex::class)
            ->name('api.complexes.destroy');
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
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ValidateCsrfToken::class,
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
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ValidateCsrfToken::class,
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

        // Membresias del conjunto (S73-S77).
        Route::get('members', [MembershipController::class, 'index'])
            ->name('api.core.members.index');

        Route::post('members', [MembershipController::class, 'store'])
            ->name('api.core.members.store');

        Route::post('members/{membership}/suspend', [MembershipController::class, 'suspend'])
            ->whereNumber('membership')
            ->name('api.core.members.suspend');

        Route::post('members/{membership}/revoke', [MembershipController::class, 'revoke'])
            ->whereNumber('membership')
            ->name('api.core.members.revoke');

        Route::put('members/{membership}/roles', [MembershipController::class, 'roles'])
            ->whereNumber('membership')
            ->name('api.core.members.roles');

        // Roles del conjunto (S83-S88).
        Route::get('roles', [RoleController::class, 'index'])
            ->name('api.core.roles.index');

        Route::post('roles', [RoleController::class, 'store'])
            ->name('api.core.roles.store');

        Route::put('roles/{role}', [RoleController::class, 'update'])
            ->whereNumber('role')
            ->name('api.core.roles.update');

        // Audit trail of the complex (S100-S101). The surface is read only:
        // there is no route to change or delete a trail.
        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->name('api.core.audit-logs.index');

        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])
            ->whereNumber('auditLog')
            ->name('api.core.audit-logs.show');
    });

/*
|--------------------------------------------------------------------------
| Core API - aceptacion de la invitacion
|--------------------------------------------------------------------------
|
| La aceptacion de la propia invitacion la hace el invitado (S77), que
| todavia NO tiene una membresia activa: el middleware `ResolveActiveComplex`
| (que deduce el conjunto de las membresias activas del actor) le negaria el
| contexto. Por eso esta ruta no lo lleva: el conjunto lo da la propia
| invitacion, y una invitacion de otro conjunto responde 404.
|
*/

Route::prefix('v1/core')
    ->middleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ValidateCsrfToken::class,
        'auth',
    ])
    ->group(function (): void {
        Route::post('members/{membership}/activate', [MembershipController::class, 'activate'])
            ->whereNumber('membership')
            ->name('api.core.members.activate');
    });
