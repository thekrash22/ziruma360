<?php

namespace App\Enums;

/**
 * Estado de una asignacion de uso exclusivo de un bien comun
 * (common_property_exclusive_use_assignments).
 *
 * La asignacion se cierra (ended) o se revoca (revoked), nunca se borra:
 * la separacion entre uso exclusivo y titularidad legal exige conservar el
 * historial (RN-S06).
 */
enum ExclusiveUseAssignmentStatus: string
{
    case ACTIVE = 'active';
    case ENDED = 'ended';
    case REVOKED = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activa',
            self::ENDED => 'Finalizada',
            self::REVOKED => 'Revocada',
        };
    }
}
