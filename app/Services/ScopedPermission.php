<?php

namespace App\Services;

use App\Enums\ComplexPermission;
use App\Enums\MembershipStatus;
use App\Models\ResidentialComplexMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

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
 * regla de autorizacion divergen: la usa el nucleo (`ResidentialComplexPolicy`),
 * la superficie de membresias (`MembershipPolicy`) y el servicio
 * (`PermissionService`, que delega en vez de mantener su propia consulta).
 */
final class ScopedPermission
{
    public static function has(User $user, int $complexId, ComplexPermission $permission): bool
    {
        return self::hasKey($user, $complexId, $permission->value);
    }

    /**
     * El permiso resuelto por su clave, con la misma semantica de vigencia
     * y alcance que `has`.
     */
    public static function hasKey(User $user, int $complexId, string $permissionKey): bool
    {
        $membership = self::membership($user, $complexId);

        if ($membership === null) {
            return false;
        }

        return self::scopedRoles($membership, $complexId)
            ->whereHas('permissions', fn (Builder $query) => $query->where('permission_key', $permissionKey))
            ->exists();
    }

    /**
     * Las claves de permiso del actor en el conjunto.
     *
     * Solo cuentan las asignaciones vigentes de los roles del propio
     * conjunto (o del catalogo de sistema) sobre su membresia activa: una
     * asignacion vencida o pendiente, y un rol de otro conjunto, no aportan
     * ningun permiso.
     *
     * @return Collection<int, string>
     */
    public static function permissionKeys(User $user, int $complexId): Collection
    {
        $membership = self::membership($user, $complexId);

        if ($membership === null) {
            return collect();
        }

        return self::scopedRoles($membership, $complexId)
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('permission_key')
            ->unique()
            ->values();
    }

    /**
     * La membresia activa del actor en el conjunto.
     */
    private static function membership(User $user, int $complexId): ?ResidentialComplexMembership
    {
        return $user->memberships()
            ->where('residential_complex_id', $complexId)
            ->where('status', MembershipStatus::ACTIVE->value)
            ->first();
    }

    /**
     * Los roles de la membresia que conceden algo: los del propio conjunto
     * (o del catalogo de sistema) con la asignacion vigente.
     */
    private static function scopedRoles(
        ResidentialComplexMembership $membership,
        int $complexId
    ): BelongsToMany {
        return $membership->roles()
            ->where(fn (Builder $query) => $query
                ->whereNull('roles.residential_complex_id')
                ->orWhere('roles.residential_complex_id', $complexId))
            ->where(fn (Builder $query) => $query
                ->whereNull('membership_roles.valid_from')
                ->orWhere('membership_roles.valid_from', '<=', now()))
            ->where(fn (Builder $query) => $query
                ->whereNull('membership_roles.valid_until')
                ->orWhere('membership_roles.valid_until', '>=', now()));
    }
}
