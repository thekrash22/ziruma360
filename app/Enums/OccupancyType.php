<?php

namespace App\Enums;

/**
 * Tipos de ocupación (unit_occupancies).
 *
 * Ver spec §4.4: `owner_resident`, `tenant_resident`, `family_member`,
 * `other_resident`.
 */
enum OccupancyType: string
{
    case OWNER_RESIDENT = 'owner_resident';
    case TENANT_RESIDENT = 'tenant_resident';
    case FAMILY_MEMBER = 'family_member';
    case OTHER_RESIDENT = 'other_resident';

    public function label(): string
    {
        return match ($this) {
            self::OWNER_RESIDENT => 'Propietario residente',
            self::TENANT_RESIDENT => 'Arrendatario residente',
            self::FAMILY_MEMBER => 'Familiar',
            self::OTHER_RESIDENT => 'Otro residente',
        };
    }

    /**
     * Único tipo que puede vincularse a un arrendamiento (RN-P09).
     */
    public function isLinkedToLease(): bool
    {
        return $this === self::TENANT_RESIDENT;
    }
}
