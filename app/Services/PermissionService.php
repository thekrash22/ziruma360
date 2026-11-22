<?php

namespace App\Services;

use App\Enums\ComplexPermission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Servicio para verificar permisos de usuarios en el contexto del conjunto activo.
 *
 * No mantiene su propia consulta: delega en `ScopedPermission`, que es la
 * unica fuente de verdad del permiso con alcance (membresia activa, rol del
 * conjunto o del catalogo y asignacion vigente). Dos copias de la regla
 * divergen, asi que el servicio y las policies resuelven lo mismo.
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
        return ScopedPermission::hasKey($user, $complexId, $permissionKey);
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

        return ScopedPermission::permissionKeys($user, $complexId);
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
        if (! self::has($user, $permission, $complexId)) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
    }
}
