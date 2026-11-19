<?php

namespace App\Policies;

use App\Enums\ComplexPermission;
use App\Models\User;
use App\Services\ComplexContext;
use App\Services\PermissionService;

/**
 * Titularidad (spec §4.3, matriz de permisos).
 *
 * En la matriz el propietario no gestiona la titularidad: solo la solicita.
 */
class PropertyUnitOwnershipPolicy
{
    /**
     * Consulta del historial de titularidad del conjunto activo.
     *
     * El conjunto activo ya viene verificado por el middleware
     * ResolveActiveComplex: no lo elige el cliente.
     */
    public function viewAny(User $user): bool
    {
        $complexId = ComplexContext::getCurrentComplexId();

        return $complexId !== null
            && PermissionService::has($user, ComplexPermission::OWNERSHIP_VIEW, $complexId);
    }
}
