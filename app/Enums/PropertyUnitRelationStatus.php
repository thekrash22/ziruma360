<?php

namespace App\Enums;

/**
 * Estado de una relacion entre unidades (property_unit_relations).
 *
 * La relacion se desactiva, no se borra, para conservar el historial.
 */
enum PropertyUnitRelationStatus: string
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
