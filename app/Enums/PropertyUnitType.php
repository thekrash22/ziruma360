<?php

namespace App\Enums;

/**
 * Tipos de unidad inmobiliaria (property_units).
 *
 * Ver spec §3.5: apartment, private_parking, storage, commercial_unit, other.
 */
enum PropertyUnitType: string
{
    case APARTMENT = 'apartment';
    case PRIVATE_PARKING = 'private_parking';
    case STORAGE = 'storage';
    case COMMERCIAL_UNIT = 'commercial_unit';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::APARTMENT => 'Apartamento',
            self::PRIVATE_PARKING => 'Parqueadero privado',
            self::STORAGE => 'Depósito',
            self::COMMERCIAL_UNIT => 'Unidad comercial',
            self::OTHER => 'Otra',
        };
    }

    /**
     * Indica si el tipo admite extensión 1:1 como apartamento (S12 / TEC-S01).
     */
    public function admitsApartmentExtension(): bool
    {
        return $this === self::APARTMENT;
    }
}
