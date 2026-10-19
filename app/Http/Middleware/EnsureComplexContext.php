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
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for unauthenticated users or web routes without complex context
        if (!$request->user()) {
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
            session()->put('url.intended', $request->fullUrl());
            session()->put('complex_selection_required', true);
        }

        return $next($request);
    }
}
