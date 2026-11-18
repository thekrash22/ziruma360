<?php

namespace App\Enums;

/**
 * Estados de una persona (people).
 *
 * Ver spec §4.2: active, inactive.
 * RN-S04: las personas con historial se desactivan, no se borran.
 */
enum PersonStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activa',
            self::INACTIVE => 'Inactiva',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
