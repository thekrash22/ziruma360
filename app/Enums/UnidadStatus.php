<?php

namespace App\Enums;

enum UnidadStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case MAINTENANCE = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::ACTIVE => 'Activo',
            self::INACTIVE => 'Inactivo',
            self::MAINTENANCE => 'En mantenimiento',
        };
    }

    public function isWritable(): bool
    {
        return true; // Todas las categorías pueden editarsiempre que tengan el registro
    }
}
