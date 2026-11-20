<?php

namespace App\Policies;

use App\Enums\ComplexPermission;
use App\Models\ResidentialComplex;
use App\Models\User;
use App\Services\PermissionService;

/**
 * Politicas propias del conjunto (spec §1.4, matriz de permisos).
 *
 * `view`, `update` y `archive` resuelven el alcance del actor en el
 * servidor: un miembro activo del conjunto o el administrador de plataforma.
 * `create` es un permiso de plataforma: exige `complex.create` en un rol del
 * catalogo de sistema sostenido por una membresia activa.
 */
class ResidentialComplexPolicy
{
    public function viewSettings(User $user, ResidentialComplex $complex): bool
    {
        return PermissionService::has($user, ComplexPermission::COMPLEX_VIEW, (int) $complex->id);
    }

    public function updateSettings(User $user, ResidentialComplex $complex): bool
    {
        return PermissionService::has($user, ComplexPermission::COMPLEX_MANAGE_SETTINGS, (int) $complex->id);
    }

    /**
     * Lectura de la ficha del conjunto: la exige ser miembro activo.
     */
    public function view(User $user, ResidentialComplex $complex): bool
    {
        return $user->isPlatformAdmin() || $user->isMemberOf((int) $complex->id);
    }

    /**
     * Datos de gobierno del conjunto (`settings` y `legal_identifier`).
     *
     * Solo se exponen a quien administra la configuracion del conjunto o a
     * la plataforma.
     */
    public function viewGovernance(User $user, ResidentialComplex $complex): bool
    {
        return $user->isPlatformAdmin()
            || PermissionService::has($user, ComplexPermission::COMPLEX_MANAGE_SETTINGS, (int) $complex->id);
    }

    /**
     * Edicion de los atributos del conjunto.
     */
    public function update(User $user, ResidentialComplex $complex): bool
    {
        return $user->isPlatformAdmin()
            || PermissionService::has($user, ComplexPermission::COMPLEX_EDIT, (int) $complex->id);
    }

    /**
     * Baja del conjunto: se archiva, nunca se borra.
     */
    public function archive(User $user, ResidentialComplex $complex): bool
    {
        return $this->update($user, $complex);
    }

    /**
     * Alta de conjuntos: permiso de plataforma.
     */
    public function create(User $user): bool
    {
        return $user->activeMemberships()
            ->whereHas('roles', function ($query) {
                $query->whereNull('residential_complex_id')
                    ->whereHas('permissions', function ($permissions) {
                        $permissions->where('permission_key', ComplexPermission::COMPLEX_CREATE->value);
                    });
            })
            ->exists();
    }
}
