<?php

namespace App\Enums;

/**
 * Tipos de relacion entre unidades (property_unit_relations).
 *
 * Ver spec §3.7: annex, use_assignment, adjacent, …
 */
enum PropertyUnitRelationType: string
{
    case ANNEX = 'annex';
    case USE_ASSIGNMENT = 'use_assignment';
    case ADJACENT = 'adjacent';

    public function label(): string
    {
        return match ($this) {
            self::ANNEX => 'Anexo',
            self::USE_ASSIGNMENT => 'Uso asignado',
            self::ADJACENT => 'Adjacent',
        };
    }
}
