<?php

namespace App\Enums;

/**
 * Estado de una instalacion de parqueadero comunitario (parking_facilities).
 *
 * Una instalacion se desactiva, no se borra: sus espacios numerados y el
 * historial deben conservarse.
 */
enum ParkingFacilityStatus: string
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
}
