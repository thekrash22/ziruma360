<?php

namespace App\Policies;

use App\Enums\ComplexPermission;
use App\Models\Person;
use App\Models\User;
use App\Services\PermissionService;

/**
 * Consulta de personas (spec §1.3, §6 "Privacidad").
 *
 * `person.view` da acceso a la ficha completa; `person.view_minimal` da
 * acceso a la proyeccion minima de porteria.
 */
class PersonPolicy
{
    public function viewAny(User $user, int $complexId): bool
    {
        return $this->canViewFull($user, $complexId) || $this->canViewMinimal($user, $complexId);
    }

    public function view(User $user, Person $person): bool
    {
        return $this->viewAny($user, (int) $person->residential_complex_id);
    }

    public function canViewFull(User $user, int $complexId): bool
    {
        return PermissionService::has($user, ComplexPermission::PERSON_VIEW, $complexId);
    }

    public function canViewMinimal(User $user, int $complexId): bool
    {
        return PermissionService::has($user, ComplexPermission::PERSON_VIEW_MINIMAL, $complexId);
    }
}
