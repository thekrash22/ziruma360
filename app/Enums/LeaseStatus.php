<?php

namespace App\Enums;

/**
 * Estados de un arrendamiento (unit_leases).
 *
 * Ver spec §4.5: `active`, `ended`, `terminated`.
 * RN-P10: al terminar el arrendamiento se cierra la ocupación y las
 * delegaciones asociadas.
 */
enum LeaseStatus: string
{
    case ACTIVE = 'active';
    case ENDED = 'ended';
    case TERMINATED = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Vigente',
            self::ENDED => 'Finalizado',
            self::TERMINATED => 'Rescindido',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isFinished(): bool
    {
        return $this === self::ENDED || $this === self::TERMINATED;
    }
}
