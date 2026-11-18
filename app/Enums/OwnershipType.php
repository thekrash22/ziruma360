<?php

namespace App\Enums;

/**
 * Tipos de titularidad sobre una unidad (property_unit_ownerships).
 *
 * Ver spec §4.3: owner, co_owner, usufructuary, other.
 */
enum OwnershipType: string
{
    case OWNER = 'owner';
    case CO_OWNER = 'co_owner';
    case USFRUCTUARY = 'usufructuary';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Propietario',
            self::CO_OWNER => 'Copropietario',
            self::USFRUCTUARY => 'Usufructuario',
            self::OTHER => 'Otro',
        };
    }
}
