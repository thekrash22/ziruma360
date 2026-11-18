<?php

namespace App\Enums;

/**
 * Estados de una unidad inmobiliaria (property_units).
 *
 * Ver spec §3.5: active, inactive, under_review.
 * RN-S04: las unidades se desactivan, no se borran.
 */
enum PropertyUnitStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case UNDER_REVIEW = 'under_review';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activa',
            self::INACTIVE => 'Inactiva',
            self::UNDER_REVIEW => 'En revisión',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
