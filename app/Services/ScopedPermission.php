<?php

namespace App\Services;

use App\Enums\ComplexPermission;
use App\Enums\MembershipStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Permiso de conjunto resuelto con alcance explicito.
 *
 * Una sola fuente de verdad para "este actor tiene este permiso en este
 * conjunto": una membresia **activa** de ese conjunto, un rol del propio
 * conjunto o del catalogo de sistema, una asignacion de rol **vigente**
 * (`membership_roles.valid_from` / `valid_until`) y el permiso en el
 * catalogo del rol.
 *
 * Una asignacion vencida o pendiente, un rol de otro conjunto o una
 * membresia que no esta activa no conceden nada.
 *
 * Vive aqui y no duplicado dentro de cada policy porque dos copias de una
 * regla de autorizacion divergen: la usa el nucleo (`ResidentialComplexPolicy`)
 * y la superficie de membresias (`MembershipPolicy`).
 */
final class ScopedPermission
{
    public static function has(User $user, int $complexId, ComplexPermission $permission): bool
    {
        $membership = $user->memberships()
            ->where('residential_complex_id', $complexId)
            ->where('status', MembershipStatus::ACTIVE->value)
            ->first();

        if (! $membership) {
            return false;
        }

        return $membership->roles()
            ->where(fn (Builder $query) => $query
                ->whereNull('roles.residential_complex_id')
                ->orWhere('roles.residential_complex_id', $complexId))
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
