<?php

namespace App\Services;

use App\Models\ResidentialComplex;
use Illuminate\Support\Facades\Session;

/**
 * Servicio para gestionar el contexto del conjunto residencial activo.
 * Resuelve el conjunto activo desde la sesión del usuario.
 *
 * El contexto vive en la sesión y nowhere más. Un worker persistente
 * (Octane) atiende a varios usuarios en el mismo proceso: guardar el
 * conjunto activo en un estático lo sobreviviría a la petición y el ámbito
 * global de `BelongsToComplex` filtraría por el conjunto de otra persona.
 */
class ComplexContext
{
    private const SESSION_KEY = 'active_residential_complex_id';

    /**
     * Get the current complex ID from session.
     */
    public static function getCurrentComplexId(): ?int
    {
        if (! app()->bound('session')) {
            return null;
        }

        $complexId = Session::get(self::SESSION_KEY);

        return $complexId === null ? null : (int) $complexId;
    }

    /**
     * Set the active complex for the current session.
     */
    public static function setCurrentComplexId(?int $complexId): void
    {
        if (! app()->bound('session')) {
            return;
        }

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
        // 1. Check for explicit header (for API access). The header is a
        //    request from the client, so it is only honored when the user has
        //    an active membership in that complex (S9: the active complex is
        //    resolved on the server, never trusting an ID sent by the client).
        $headerComplexId = $request->header('X-Residential-Complex-Id');
        if ($headerComplexId !== null && $request->user() !== null) {
            $complexId = (int) $headerComplexId;

            if ($complexId > 0 && $request->user()->isMemberOf($complexId)) {
                return $complexId;
            }
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
        if (app()->bound('session')) {
            Session::forget(self::SESSION_KEY);
        }
    }
}
