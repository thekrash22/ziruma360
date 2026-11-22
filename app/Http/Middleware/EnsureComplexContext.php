<?php

namespace App\Http\Middleware;

use App\Services\ComplexContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureComplexContext
{
    /**
     * Handle an incoming request.
     * Resolves the active complex from session, header, or auto-selection.
     *
     * Cuando el actor tiene mas de un conjunto y todavia no ha elegido
     * uno, la aplicacion web lo lleva a la pantalla de seleccion
     * (`/complexes`): el aviso "hay que elegir un conjunto" deja de ser un
     * callejon sin salida (spec S93).
     *
     * La pantalla de seleccion no se redirige a si misma, asi que el flujo
     * no hace bucles.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for unauthenticated users or web routes without complex context
        if (! $request->user()) {
            return $next($request);
        }

        // Skip if already has complex context
        if (ComplexContext::hasActiveComplex()) {
            return $next($request);
        }

        // Try to resolve from request
        $complexId = ComplexContext::resolveFromRequest($request);

        // If no complex found and user has complexes, they need to select one
        if ($complexId === null && $request->user()->activeMemberships()->exists()) {
            // Store intended URL to redirect after selection
            if ($request->isMethod('GET')) {
                session()->put('url.intended', $request->fullUrl());
            }

            session()->put('complex_selection_required', true);

            if ($this->needsSelection($request) && ! $this->isSelectionSurface($request)) {
                return redirect()->route('complexes.index');
            }
        }

        return $next($request);
    }

    /**
     * El actor tiene mas de un conjunto y todavia no ha elegido uno.
     */
    private function needsSelection(Request $request): bool
    {
        return $request->user()->activeMemberships()->count() > 1;
    }

    /**
     * La peticion ya es la superficie de conjuntos.
     *
     * La pantalla de seleccion (y la eleccion misma) no se redirigen a si
     * mismas: sin esto el flujo seria un bucle.
     */
    private function isSelectionSurface(Request $request): bool
    {
        return $request->is('complexes', 'complexes/*');
    }
}
