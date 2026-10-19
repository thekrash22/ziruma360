<?php

namespace App\Enums;

enum SystemRole: string
{
    // Platform level
    case PLATFORM_ADMIN = 'platform_admin';

    // Complex level
    case COMPLEX_ADMIN = 'complex_admin';
    case DOORMAN = 'doorman';

    // Property level
    case OWNER = 'owner';
    case CO_OWNER = 'co_owner';
    case RESIDENT = 'resident';
    case TENANT = 'tenant';

    // Short-term rental
    case HOST = 'host';

    public function label(): string
    {
        return match ($this) {
            self::PLATFORM_ADMIN => 'Administrador de plataforma',
            self::COMPLEX_ADMIN => 'Administrador del conjunto',
            self::DOORMAN => 'Portero',
            self::OWNER => 'Propietario',
            self::CO_OWNER => 'Copropietario',
            self::RESIDENT => 'Residente',
            self::TENANT => 'Arrendatario',
            self::HOST => 'Anfitrión',
        };
    }

    public function isSystem(): bool
    {
        return true;
    }

    public function isPlatformLevel(): bool
    {
        return $this === self::PLATFORM_ADMIN;
    }

    public function isComplexLevel(): bool
    {
        return in_array($this, [
            self::COMPLEX_ADMIN,
            self::DOORMAN,
        ]);
    }

    public function defaultScope(): string
    {
        return match ($this) {
            self::PLATFORM_ADMIN => 'platform',
            self::COMPLEX_ADMIN, self::DOORMAN => 'complex',
            self::OWNER, self::CO_OWNER, self::RESIDENT, self::TENANT, self::HOST => 'property',
        };
    }
}
