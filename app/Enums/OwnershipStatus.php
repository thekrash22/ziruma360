<?php

namespace App\Enums;

/**
 * Estados de una titularidad (property_unit_ownerships).
 *
 * Ver spec §4.3: historial de cambios de dueño.
 * RN-P05: la titularidad no se edita en sitio, se cierra y se crea una nueva.
 */
enum OwnershipStatus: string
{
    case ACTIVE = 'active';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Vigente',
            self::CLOSED => 'Cerrada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
