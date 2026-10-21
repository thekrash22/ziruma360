<?php

namespace App\Enums;

/**
 * Catálogo de módulos disponibles del sistema.
 * Cada módulo representa una funcionalidad que puede ser habilitada por conjunto.
 */
enum ComplexModule: string
{
    // Core modules (siempre habilitados)
    case CORE_STRUCTURE = 'core.structure';         // Edificios, pisos, unidades
    case CORE_PEOPLE = 'core.people';             // Personas y relaciones

    // Optional modules
    case SHORT_TERM_RENTAL = 'short_term_rental';  // Rentas cortas / estancias
    case RESERVATIONS = 'reservations';            // Reservas de áreas comunes
    case PARKING_MANAGEMENT = 'parking';          // Gestión de parqueaderos
    case ACCESS_CONTROL = 'access_control';        // Control de acceso / portería

    public function label(): string
    {
        return match ($this) {
            self::CORE_STRUCTURE => 'Estructura física',
            self::CORE_PEOPLE => 'Personas y relaciones',
            self::SHORT_TERM_RENTAL => 'Rentas cortas',
            self::RESERVATIONS => 'Reservas',
            self::PARKING_MANAGEMENT => 'Gestión de parqueaderos',
            self::ACCESS_CONTROL => 'Control de acceso',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CORE_STRUCTURE => 'Edificios, pisos, apartamentos y áreas comunes',
            self::CORE_PEOPLE => 'Propietarios, residentes, empleados y visitantes',
            self::SHORT_TERM_RENTAL => 'Estancias cortas, check-in/out, vehículos',
            self::RESERVATIONS => 'Reserva de áreas comunes del conjunto',
            self::PARKING_MANAGEMENT => 'Parqueaderos comunitarios y asignaciones',
            self::ACCESS_CONTROL => 'Registro de accesos y portería',
        };
    }

    public function isCore(): bool
    {
        return match ($this) {
            self::CORE_STRUCTURE, self::CORE_PEOPLE => true,
            default => false,
        };
    }

    public function defaultSettings(): array
    {
        return match ($this) {
            self::SHORT_TERM_RENTAL => [
                'approval_required' => true,
                'max_stay_days' => 30,
                'checkin_start_hour' => 14,
                'checkin_end_hour' => 21,
            ],
            self::RESERVATIONS => [
                'advance_booking_days' => 7,
                'max_duration_hours' => 4,
            ],
            self::PARKING_MANAGEMENT => [
                'allow_overnight' => true,
                'max_consecutive_days' => 30,
            ],
            self::ACCESS_CONTROL => [
                'require_photo' => false,
                'require_vehicle_plate' => true,
            ],
            default => [],
        };
    }
}
