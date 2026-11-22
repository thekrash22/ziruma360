<?php

namespace App\Http\Resources;

use App\Models\Role;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Membresia del conjunto (specs S73-S76).
 *
 * Expone el estado de la membresia, la cuenta que la sostiene, la traza de
 * la invitacion (`invited_by`, `invited_at`, `joined_at`) y los roles
 * **vigentes** con su vigencia. Un rol vencido o todavia no vigente no
 * concede permisos, asi que no se expone como rol en vigor.
 */
class MembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'invited_by' => $this->invited_by === null ? null : [
                'id' => $this->inviter?->id ?? (int) $this->invited_by,
                'name' => $this->inviter?->name,
            ],
            'invited_at' => $this->invited_at?->toIso8601String(),
            'joined_at' => $this->joined_at?->toIso8601String(),
            'roles' => $this->roles
                ->filter(fn (Role $role): bool => $role->is_active && $this->roleIsInForce($role))
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'key' => $role->key,
                    'name' => $role->name,
                    'valid_from' => self::moment($role->pivot->valid_from)?->toIso8601String(),
                    'valid_until' => self::moment($role->pivot->valid_until)?->toIso8601String(),
                ])
                ->values(),
        ];
    }

    /**
     * El rol esta en vigor cuando su vigencia abra y cierre admiten el ahora.
     *
     * La vigencia es la del pivote de la asignacion (`valid_from`,
     * `valid_until`), la misma que gobierna los permisos. Un rol desactivado
     * tampoco esta en vigor (spec S88).
     */
    private function roleIsInForce(Role $role): bool
    {
        $validFrom = self::moment($role->pivot->valid_from);
        $validUntil = self::moment($role->pivot->valid_until);

        if ($validFrom !== null && $validFrom->gt(now())) {
            return false;
        }

        if ($validUntil !== null && $validUntil->lt(now())) {
            return false;
        }

        return true;
    }

    /**
     * Un instante del pivote de vigencia, o null si no hay.
     */
    private static function moment(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value);
    }
}
