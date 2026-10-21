<?php

namespace App\Enums;

enum BuildingType: string
{
    case TOWER = 'tower';
    case BLOCK = 'block';
    case HOUSE_CLUSTER = 'house_cluster';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TOWER => 'Torre',
            self::BLOCK => 'Bloque',
            self::HOUSE_CLUSTER => 'Conjunto de casas',
            self::OTHER => 'Otro',
        };
    }
}
