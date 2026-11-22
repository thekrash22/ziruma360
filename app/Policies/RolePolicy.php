<?php

namespace App\Policies;

use App\Enums\ComplexPermission;
use App\Models\Role;
use App\Models\User;
use App\Services\ScopedPermission;

/**
 * Politicas de los roles del conjunto (specs S83-S87).
 *
 * La escritura de la superficie de roles la exige `complex.manage_members`
 * dentro del conjunto: una membresia activa con un rol del catalogo (de
 * sistema o del propio conjunto) que tenga ese permiso.
 *
 * El alcance se resuelve con `ScopedPermission`, la unica fuente de verdad
 * del permiso con alcance: una asignacion de rol vencida o todavia no
 * vigente no concede nada.
 *
 * El alcance del rol sobre el que se opera lo resuelve la peticion: un rol
 * de otro conjunto no se resuelve (404) y un rol del sistema se resuelve
 * pero no se puede editar (403).
 */
class RolePolicy
{
    /**
     * Listado de los roles del conjunto.
     */
    public function viewAny(User $user, int $complexId): bool
    {
        return $this->scopedPermission($user, $complexId, ComplexPermission::COMPLEX_MANAGE_MEMBERS);
    }

    /**
     * Alta de un rol propio del conjunto.
     */
    public function create(User $user, int $complexId): bool
    {
        return $this->scopedPermission($user, $complexId, ComplexPermission::COMPLEX_MANAGE_MEMBERS);
    }

    /**
     * Edicion de un rol del conjunto.
     */
    public function update(User $user, Role $role, int $complexId): bool
    {
        return $this->scopedPermission($user, $complexId, ComplexPermission::COMPLEX_MANAGE_MEMBERS);
    }

    /**
     * Permiso de conjunto resuelto con alcance explicito.
     */
    private function scopedPermission(User $user, int $complexId, ComplexPermission $permission): bool
    {
        return ScopedPermission::has($user, $complexId, $permission);
    }
}
