<?php

namespace App\Policies;

use App\Enums\ComplexPermission;
use App\Models\ResidentialComplex;
use App\Models\User;
use App\Services\PermissionService;

/**
 * Politicas propias del conjunto (spec §1.4, matriz de permisos).
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
}
