<?php

namespace App\Enums;

/**
 * Tipos de area comun (common_areas).
 *
 * Ver spec §3.8: pool, social_hall, bbq, gym, playground, court, terrace,
 * parking, other.
 */
enum CommonAreaType: string
{
    case POOL = 'pool';
    case SOCIAL_HALL = 'social_hall';
    case BBQ = 'bbq';
    case GYM = 'gym';
    case PLAYGROUND = 'playground';
    case COURT = 'court';
    case TERRACE = 'terrace';
    case PARKING = 'parking';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::POOL => 'Piscina',
            self::SOCIAL_HALL => 'Salón social',
            self::BBQ => 'BBQ',
            self::GYM => 'Gimnasio',
            self::PLAYGROUND => 'Juegos infantiles',
            self::COURT => 'Cancha',
            self::TERRACE => 'Terraza',
            self::PARKING => 'Parqueadero',
            self::OTHER => 'Otra',
        };
    }
}
