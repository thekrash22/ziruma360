<?php

namespace App\Enums;

/**
 * Estado de un area comun (common_areas).
 *
 * Un area comun se desactiva, no se borra: las asignaciones de uso exclusivo
 * y el historial deben conservarse.
 */
enum CommonAreaStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case MAINTENANCE = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activa',
            self::INACTIVE => 'Inactiva',
            self::MAINTENANCE => 'En mantenimiento',
        };
    }
}
