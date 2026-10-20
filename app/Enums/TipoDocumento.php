<?php

namespace App\Enums;

enum TipoDocumento: string
{
    case CC = 'CC'; // Cédula de Ciudadanía
    case CE = 'CE'; // Cédula de Extranjería
    case TI = 'TI'; // Tarjeta de Identidad
    case PASAPORTE = 'PASAPORTE';
    case NIT = 'NIT'; // Número de Identificación Tributaria
    case PEP = 'PEP'; // Permiso Especial de Permanencia
    case PPT = 'PPT'; // Permiso Protección Temporal

    public function label(): string
    {
        return match ($this) {
            self::CC => 'Cédula de Ciudadanía',
            self::CE => 'Cédula de Extranjería',
            self::TI => 'Tarjeta de Identidad',
            self::PASAPORTE => 'Pasaporte',
            self::NIT => 'NIT',
            self::PEP => 'Permiso Especial de Permanencia',
            self::PPT => 'Permiso Protección Temporal',
        };
    }
}
