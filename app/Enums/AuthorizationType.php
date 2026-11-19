<?php

namespace App\Enums;

/**
 * Tipos de autorización de gestión (property_unit_authorizations).
 *
 * Ver spec §4.8: `manager`, `host`, `delegate`, … El spec enumera esos
 * tres y deja abierta la lista con "…", así que el dominio queda cerrado
 * en los tres tipos nombrados, igual que `ownership_type`,
 * `occupancy_type` y `employment_type`.
 */
enum AuthorizationType: string
{
    case MANAGER = 'manager';
    case HOST = 'host';
    case DELEGATE = 'delegate';

    public function label(): string
    {
        return match ($this) {
            self::MANAGER => 'Administrador de la unidad',
            self::HOST => 'Anfitrión',
            self::DELEGATE => 'Delegado',
        };
    }
}
