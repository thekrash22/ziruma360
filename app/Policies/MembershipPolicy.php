<?php

namespace App\Policies;

use App\Enums\ComplexPermission;
use App\Models\ResidentialComplexMembership;
use App\Models\User;
use App\Services\ScopedPermission;

/**
 * Politicas de las membresias del conjunto (specs S73-S77).
 *
 * La escritura de la superficie de membresias la exige `complex.manage_members`
 * dentro del conjunto: una membresia activa con un rol del catalogo (de
 * sistema o del propio conjunto) que tenga ese permiso.
 *
 * El alcance se resuelve con la misma semantica de vigencia que ya usan las
 * policies del conjunto (`scopedPermission`): una asignacion de rol vencida o
 * todavia no vigente no concede nada, y un rol de otro conjunto tampoco.
 *
 * La aceptacion de la propia invitacion es la excepcion: la hace el invitado
 * (S77), que todavia no tiene una membresia activa y, por tanto, ningun
 * permiso en el conjunto.
 */
class MembershipPolicy
{
    /**
     * Listado de las membresias del conjunto.
     */
    public function viewAny(User $user, int $complexId): bool
    {
        return $this->scopedPermission($user, $complexId, ComplexPermission::COMPLEX_MANAGE_MEMBERS);
    }

    /**
     * Consulta de una membresia concreta.
     */
    public function view(User $user, ResidentialComplexMembership $membership): bool
    {
        return $this->scopedPermission(
            $user,
            (int) $membership->residential_complex_id,
            ComplexPermission::COMPLEX_MANAGE_MEMBERS
        );
    }

    /**
     * Invitacion de una persona del conjunto.
     */
    public function invite(User $user, int $complexId): bool
    {
        return $this->scopedPermission($user, $complexId, ComplexPermission::COMPLEX_MANAGE_MEMBERS);
    }

    /**
     * Aceptacion de la invitacion: la hace el propio invitado o un
     * administrador del conjunto.
     */
    public function activate(User $user, ResidentialComplexMembership $membership): bool
    {
        if ((int) $membership->user_id === (int) $user->id) {
            return true;
        }

        return $this->scopedPermission(
            $user,
            (int) $membership->residential_complex_id,
            ComplexPermission::COMPLEX_MANAGE_MEMBERS
        );
    }

    /**
     * Suspension de una membresia activa.
     */
    public function suspend(User $user, ResidentialComplexMembership $membership): bool
    {
        return $this->scopedPermission(
            $user,
            (int) $membership->residential_complex_id,
            ComplexPermission::COMPLEX_MANAGE_MEMBERS
        );
    }

    /**
     * Revocacion de una membresia.
     */
    public function revoke(User $user, ResidentialComplexMembership $membership): bool
    {
        return $this->scopedPermission(
            $user,
            (int) $membership->residential_complex_id,
            ComplexPermission::COMPLEX_MANAGE_MEMBERS
        );
    }

    /**
     * Asignacion (reemplazo) del conjunto de roles de una membresia.
     */
    public function assignRoles(User $user, ResidentialComplexMembership $membership): bool
    {
        return $this->scopedPermission(
            $user,
            (int) $membership->residential_complex_id,
            ComplexPermission::COMPLEX_MANAGE_MEMBERS
        );
    }

    /**
     * Permiso de conjunto resuelto con alcance explicito.
     *
     * Solo cuenta la asignacion vigente de un rol del propio conjunto (o del
     * catalogo de sistema) sobre una membresia activa de ese conjunto: una
     * asignacion vencida o pendiente, y un rol de otro conjunto, no conceden
     * nada.
     */
    private function scopedPermission(User $user, int $complexId, ComplexPermission $permission): bool
    {
        return ScopedPermission::has($user, $complexId, $permission);
    }
}
