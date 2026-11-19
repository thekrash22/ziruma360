<?php

namespace App\Enums;

/**
 * Estados de un empleo (employments).
 *
 * El spec §4.6 no enumera los valores; se adoptan `active`, `inactive` y
 * `ended` por consistencia con las titularidades y las ocupaciones.
 */
enum EmploymentStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ENDED = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Vigente',
            self::INACTIVE => 'Inactivo',
            self::ENDED => 'Finalizado',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isFinished(): bool
    {
        return $this === self::INACTIVE || $this === self::ENDED;
    }
}
