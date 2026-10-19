<?php

namespace App\Services;

use App\Models\ResidentialComplex;
use Illuminate\Support\Facades\Session;

/**
 * Servicio para gestionar el contexto del conjunto residencial activo.
 * Resuelve el conjunto activo desde la sesión del usuario.
 */
class ComplexContext
{
    private const SESSION_KEY = 'active_residential_complex_id';

    /**
     * Get the current complex ID from session.
     */
    public static function getCurrentComplexId(): ?int
    {
        return Session::get(self::SESSION_KEY);
    }

    /**
     * Set the active complex for the current session.
     */
    public static function setCurrentComplexId(?int $complexId): void
    {
        if ($complexId === null) {
            Session::forget(self::SESSION_KEY);
        } else {
            Session::put(self::SESSION_KEY, $complexId);
        }
    }

    /**
     * Get the current complex model instance.
     */
    public static function getCurrentComplex(): ?ResidentialComplex
    {
        $complexId = self::getCurrentComplexId();
        if ($complexId === null) {
            return null;
        }

        return ResidentialComplex::find($complexId);
    }

    /**
     * Check if there's an active complex in the session.
     */
    public static function hasActiveComplex(): bool
    {
        return self::getCurrentComplexId() !== null;
    }

    /**
     * Resolve the complex from the request (header, query param, or session).
     * This is used by the middleware to determine the active complex.
     */
    public static function resolveFromRequest($request): ?int
    {
        // 1. Check for explicit header (for API access)
        $headerComplexId = $request->header('X-Residential-Complex-Id');
        if ($headerComplexId !== null) {
            return (int) $headerComplexId;
        }

        // 2. Check session
        $sessionComplexId = self::getCurrentComplexId();
        if ($sessionComplexId !== null) {
            return $sessionComplexId;
        }

        // 3. Check if user has only one membership and auto-select it
        if ($request->user()) {
            $activeMemberships = $request->user()->activeMemberships;
            if ($activeMemberships->count() === 1) {
                $complexId = $activeMemberships->first()->residential_complex_id;
                self::setCurrentComplexId($complexId);
                return $complexId;
            }
        }

        return null;
    }

    /**
     * Clear the current complex context.
     */
    public static function clear(): void
    {
        self::setCurrentComplexId(null);
    }
}
