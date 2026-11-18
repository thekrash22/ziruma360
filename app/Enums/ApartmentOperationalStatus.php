<?php

namespace App\Enums;

/**
 * Estado operativo de un apartamento.
 *
 * Ver spec §3.6: occupied, vacant, under_works, inactive.
 */
enum ApartmentOperationalStatus: string
{
    case OCCUPIED = 'occupied';
    case VACANT = 'vacant';
    case UNDER_WORKS = 'under_works';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::OCCUPIED => 'Ocupado',
            self::VACANT => 'Vacante',
            self::UNDER_WORKS => 'En obras',
            self::INACTIVE => 'Inactivo',
        };
    }

    public function isOccupied(): bool
    {
        return $this === self::OCCUPIED;
    }
}
