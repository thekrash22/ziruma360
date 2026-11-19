<?php

namespace App\Enums;

/**
 * Tipos de empleo (employments).
 *
 * Ver spec §4.6: `complex_staff` (portero, administración, aseo,
 * mantenimiento del conjunto), `unit_domestic` (empleada doméstica,
 * cuidador, conductor de un apartamento) y `contractor` (proveedor o
 * contratista con acceso recurrente).
 */
enum EmploymentType: string
{
    case COMPLEX_STAFF = 'complex_staff';
    case UNIT_DOMESTIC = 'unit_domestic';
    case CONTRACTOR = 'contractor';

    public function label(): string
    {
        return match ($this) {
            self::COMPLEX_STAFF => 'Personal del conjunto',
            self::UNIT_DOMESTIC => 'Empleado doméstico',
            self::CONTRACTOR => 'Contratista',
        };
    }

    /**
     * El tipo exige una unidad empleadora: el empleado doméstico trabaja
     * para una unidad. El personal del conjunto no la tiene y el
     * contratista admite ambas formas.
     */
    public function requiresEmployerUnit(): bool
    {
        return $this === self::UNIT_DOMESTIC;
    }

    /**
     * El tipo no admite unidad empleadora: es el personal del conjunto.
     */
    public function forbidsEmployerUnit(): bool
    {
        return $this === self::COMPLEX_STAFF;
    }
}
