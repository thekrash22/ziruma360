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

    /**
     * Transiciones de estado validas (spec S67).
     *
     * Solo se admiten `draft -> active`, `active -> suspended` y
     * `suspended -> active`. `archived` es un estado terminal: de el no se
     * sale por ninguna via.
     *
     * La tabla se consulta solo cuando el destino difiere del estado actual:
     * quedarse en el mismo estado no es una transicion.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return true;
        }

        return match ($this) {
            self::DRAFT => $target === self::ACTIVE,
            self::ACTIVE => $target === self::SUSPENDED,
            self::SUSPENDED => $target === self::ACTIVE,
            self::ARCHIVED => false,
        };
    }
}
