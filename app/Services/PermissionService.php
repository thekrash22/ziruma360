<?php

namespace App\Services;

use App\Enums\ComplexPermission;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Servicio para verificar permisos de usuarios en el contexto del conjunto activo.
 */
class PermissionService
{
    /**
     * Check if the current user (or specified user) has a permission.
     */
    public static function has(User $user, string|ComplexPermission $permission, ?int $complexId = null): bool
    {
        $complexId ??= ComplexContext::getCurrentComplexId();

        if ($complexId === null) {
            return false;
        }

        $permissionKey = $permission instanceof ComplexPermission
            ? $permission->value
            : $permission;

        return self::userHasPermissionInComplex($user, $complexId, $permissionKey);
    }

    /**
     * Check if user has permission in a specific complex.
     */
    public static function userHasPermissionInComplex(User $user, int $complexId, string $permissionKey): bool
    {
        // Get user's membership in this complex
        $membership = $user->memberships()
            ->where('residential_complex_id', $complexId)
            ->where('status', 'active')
            ->with(['roles.permissions'])
            ->first();

        if (!$membership) {
            return false;
        }

        // Check if any of the user's roles have this permission
        return $membership->roles->contains(function ($role) use ($permissionKey) {
            return $role->permissions->contains('permission_key', $permissionKey);
        });
    }

    /**
     * Get all permissions for a user in a complex.
     */
    public static function getUserPermissions(User $user, ?int $complexId = null): Collection
    {
        $complexId ??= ComplexContext::getCurrentComplexId();

        if ($complexId === null) {
            return collect();
        }

        $membership = $user->memberships()
            ->where('residential_complex_id', $complexId)
            ->where('status', 'active')
            ->with(['roles.permissions'])
            ->first();

        if (!$membership) {
            return collect();
        }

        return $membership->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('permission_key')
            ->unique()
            ->values();
    }

    /**
     * Check if user is admin of the current complex.
     */
    public static function isAdmin(User $user, ?int $complexId = null): bool
    {
        return self::has($user, ComplexPermission::COMPLEX_EDIT, $complexId);
    }

    /**
     * Check if user can manage members in the current complex.
     */
    public static function canManageMembers(User $user, ?int $complexId = null): bool
    {
        return self::has($user, ComplexPermission::COMPLEX_MANAGE_MEMBERS, $complexId);
    }

    /**
     * Gate for authorization in controllers/policies.
     */
    public static function authorize(User $user, string|ComplexPermission $permission, ?int $complexId = null): void
    {
        if (!self::has($user, $permission, $complexId)) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
    }
}
