<?php

namespace App\Enums;

/**
 * Estados de una ocupación (unit_occupancies).
 *
 * El spec §4.4 no enumera los valores de `status`; se adoptan `active` y
 * `closed` por consistencia con las titularidades (§4.3): una ocupación
 * vigente es la que está activa, y es la única que puede ser contacto
 * principal (RN-P07).
 */
enum OccupancyStatus: string
{
    case ACTIVE = 'active';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Vigente',
            self::CLOSED => 'Cerrada',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
