<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case INVITED = 'invited';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case REVOKED = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::INVITED => 'Invitado',
            self::ACTIVE => 'Activo',
            self::SUSPENDED => 'Suspendido',
            self::REVOKED => 'Revocado',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canAccess(): bool
    {
        return $this === self::ACTIVE;
    }
}
