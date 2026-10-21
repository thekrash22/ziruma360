<?php

namespace App\Enums;

enum BuildingStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case UNDER_CONSTRUCTION = 'under_construction';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activo',
            self::INACTIVE => 'Inactivo',
            self::UNDER_CONSTRUCTION => 'En construcción',
        };
    }

    public function isWritable(): bool
    {
        return $this === self::ACTIVE;
    }
}
