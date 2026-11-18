<?php

namespace App\Enums;

enum FloorType: string
{
    case RESIDENTIAL = 'residential';
    case BASEMENT = 'basement';
    case PARKING = 'parking';
    case TERRACE = 'terrace';
    case COMMERCIAL = 'commercial';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RESIDENTIAL => 'Residencial',
            self::BASEMENT => 'Sótano',
            self::PARKING => 'Parqueadero',
            self::TERRACE => 'Terraza',
            self::COMMERCIAL => 'Comercial',
            self::OTHER => 'Otro',
        };
    }

    public function isResidential(): bool
    {
        return $this === self::RESIDENTIAL;
    }
}
