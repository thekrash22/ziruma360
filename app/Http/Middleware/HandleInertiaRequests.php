<?php

namespace App\Http\Middleware;

use App\Services\ComplexCatalog;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * El conjunto activo se comparte con la interfaz (spec S94): la
     * cabecera lo muestra siempre y ofrece el cambio cuando el actor tiene
     * mas de uno.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            // El conjunto se resuelve al renderizar la pagina: para entonces
            // el middleware ya resolvio el contexto en el servidor.
            'complex' => fn (): ?array => ComplexCatalog::active($user),
            'complexes' => fn (): array => $user === null ? [] : ComplexCatalog::catalog($user),
            // Los permisos del actor en el conjunto activo: el menu decide
            // con ellos que entradas mostrar (spec S103).
            'permissions' => fn (): array => $user === null ? [] : PermissionService::getUserPermissions($user)->all(),
        ];
    }
}
