<?php

namespace App\Enums;

/**
 * Estado de un espacio numerado de parqueadero (parking_spaces).
 *
 * El estado de ocupacion o reserva no vive aqui: la logica de reservas,
 * disponibilidad por intervalo y concurrencia pertenece al modulo de rentas
 * cortas (spec 3.10).
 */
enum ParkingSpaceStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activo',
            self::INACTIVE => 'Inactivo',
        };
    }
}
