<?php

namespace App\Policies;

use App\Enums\ComplexPermission;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\PermissionService;

/**
 * Alcance sobre las unidades (spec §1.3, SEG-C02, §6 "Alcance").
 *
 * El permiso decide si la operacion existe; el alcance decide sobre que
 * recursos se puede hacer. El administrador del conjunto administra todas
 * sus unidades; el propietario solo las suyas.
 */
class PropertyUnitPolicy
{
    public function viewAny(User $user, int $complexId): bool
    {
        return PermissionService::has($user, ComplexPermission::UNIT_VIEW, $complexId);
    }

    public function view(User $user, PropertyUnit $unit): bool
    {
        $complexId = (int) $unit->residential_complex_id;

        if (! $this->viewAny($user, $complexId)) {
            return false;
        }

        return $this->managesWholeComplex($user, $complexId) || $this->withinScope($user, $unit);
    }

    public function update(User $user, PropertyUnit $unit): bool
    {
        $complexId = (int) $unit->residential_complex_id;

        if ($this->managesWholeComplex($user, $complexId)) {
            return true;
        }

        if (! PermissionService::has($user, ComplexPermission::UNIT_MANAGE, $complexId)) {
            return false;
        }

        return $this->withinScope($user, $unit);
    }

    /**
     * Traspaso de titularidad de la unidad.
     *
     * En la matriz de permisos el propietario no gestiona la titularidad:
     * solo la solicita, asi que el permiso de titularidad es el que manda
     * y, ademas, la unidad tiene que estar al alcance del actor.
     */
    public function transfer(User $user, PropertyUnit $unit): bool
    {
        $complexId = (int) $unit->residential_complex_id;

        if (! PermissionService::has($user, ComplexPermission::OWNERSHIP_MANAGE, $complexId)) {
            return false;
        }

        return $this->managesWholeComplex($user, $complexId) || $this->withinScope($user, $unit);
    }

    /**
     * El administrador del conjunto administra todas sus unidades.
     */
    public function managesWholeComplex(User $user, int $complexId): bool
    {
        return PermissionService::has($user, ComplexPermission::UNIT_EDIT, $complexId);
    }

    /**
     * La unidad le alcanza al actor cuando tiene una titularidad vigente,
     * una ocupacion vigente o una autorizacion vigente sobre ella.
     */
    public function withinScope(User $user, PropertyUnit $unit): bool
    {
        $complexId = (int) $unit->residential_complex_id;

        return PropertyUnit::query()
            ->forComplex($complexId)
            ->forActor($user, $complexId)
            ->whereKey($unit->getKey())
            ->exists();
    }
}
