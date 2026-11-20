<?php

namespace App\Policies;

use App\Enums\ComplexPermission;
use App\Models\ResidentialComplex;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Politicas propias del conjunto (spec §1.4, matriz de permisos).
 *
 * `view`, `update` y `archive` resuelven el alcance del actor en el
 * servidor: un miembro activo del conjunto o el administrador de plataforma.
 * `create` es un permiso de plataforma: exige `complex.create` en un rol del
 * catalogo de sistema sostenido por una membresia activa.
 *
 * El alcance del rol se resuelve aqui y no por delegacion ciega: una
 * asignacion de rol vencida o pendiente, y un rol que pertenece a otro
 * conjunto, no conceden ningun permiso sobre este conjunto.
 */
class ResidentialComplexPolicy
{
    public function viewSettings(User $user, ResidentialComplex $complex): bool
    {
        return $this->scopedPermission($user, $complex, ComplexPermission::COMPLEX_VIEW);
    }

    public function updateSettings(User $user, ResidentialComplex $complex): bool
    {
        return $this->scopedPermission($user, $complex, ComplexPermission::COMPLEX_MANAGE_SETTINGS);
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
            || $this->scopedPermission($user, $complex, ComplexPermission::COMPLEX_MANAGE_SETTINGS);
    }

    /**
     * Edicion de los atributos del conjunto.
     */
    public function update(User $user, ResidentialComplex $complex): bool
    {
        return $user->isPlatformAdmin()
            || $this->scopedPermission($user, $complex, ComplexPermission::COMPLEX_EDIT);
    }

    /**
     * Escritura de los datos de gobierno del conjunto (`settings` y
     * `legal_identifier`).
     *
     * Editar el conjunto no habilita a reescribir su gobierno: esa escritura
     * exige su propio permiso, igual que la lectura.
     */
    public function updateGovernance(User $user, ResidentialComplex $complex): bool
    {
        return $user->isPlatformAdmin()
            || $this->scopedPermission($user, $complex, ComplexPermission::COMPLEX_MANAGE_SETTINGS);
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
     *
     * La asignacion del rol de plataforma debe estar vigente: una asignacion
     * vencida o pendiente no habilita el alta.
     */
    public function create(User $user): bool
    {
        return $user->activeMemberships()
            ->whereHas('roles', function ($query) {
                $query->whereNull('roles.residential_complex_id')
                    ->where(fn (Builder $roles) => $roles
                        ->whereNull('membership_roles.valid_from')
                        ->orWhere('membership_roles.valid_from', '<=', now()))
                    ->where(fn (Builder $roles) => $roles
                        ->whereNull('membership_roles.valid_until')
                        ->orWhere('membership_roles.valid_until', '>=', now()))
                    ->whereHas('permissions', function ($permissions) {
                        $permissions->where('permission_key', ComplexPermission::COMPLEX_CREATE->value);
                    });
            })
            ->exists();
    }

    /**
     * Permiso de conjunto resuelto con alcance explicito.
     *
     * Solo cuenta la asignacion vigente de un rol del propio conjunto (o del
     * catalogo de sistema) sobre una membresia activa de ese conjunto: una
     * asignacion vencida o pendiente, y un rol de otro conjunto, no
     * conceden nada.
     */
    private function scopedPermission(User $user, ResidentialComplex $complex, ComplexPermission $permission): bool
    {
        $membership = $user->memberships()
            ->where('residential_complex_id', $complex->id)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return false;
        }

        return $membership->roles()
            ->where(fn (Builder $query) => $query
                ->whereNull('roles.residential_complex_id')
                ->orWhere('roles.residential_complex_id', $complex->id))
            ->where(fn (Builder $query) => $query
                ->whereNull('membership_roles.valid_from')
                ->orWhere('membership_roles.valid_from', '<=', now()))
            ->where(fn (Builder $query) => $query
                ->whereNull('membership_roles.valid_until')
                ->orWhere('membership_roles.valid_until', '>=', now()))
            ->whereHas('permissions', fn (Builder $query) => $query->where('permission_key', $permission->value))
            ->exists();
    }
}
