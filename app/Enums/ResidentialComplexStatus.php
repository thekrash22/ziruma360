<?php

namespace App\Enums;

enum ResidentialComplexStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::ACTIVE => 'Activo',
            self::SUSPENDED => 'Suspendido',
            self::ARCHIVED => 'Archivado',
        };
    }

    public function isWritable(): bool
    {
        return $this !== self::SUSPENDED && $this !== self::ARCHIVED;
    }
}
