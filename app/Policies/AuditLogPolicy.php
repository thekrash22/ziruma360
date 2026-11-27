<?php

namespace App\Policies;

use App\Enums\ComplexPermission;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ScopedPermission;

/**
 * Read access to the audit trail (specs S100 and S101).
 *
 * Reading the trail of a complex requires `complex.view_audit` inside that
 * complex: the platform and the complex admin have it, nobody else does.
 *
 * The scope of the permission is the complex of the trail, the same one the
 * surface filters by: a trail that belongs to another complex is not read.
 */
class AuditLogPolicy
{
    /**
     * Read the trail of a complex.
     */
    public function viewAny(User $user, int $complexId): bool
    {
        return ScopedPermission::has($user, $complexId, ComplexPermission::COMPLEX_VIEW_AUDIT);
    }

    /**
     * Read one record of the trail.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->viewAny($user, (int) $auditLog->residential_complex_id);
    }
}
