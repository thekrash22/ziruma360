<?php

namespace App\Enums;

/**
 * Estados de una autorización de gestión (property_unit_authorizations).
 *
 * Solo hay dos: `active` y `revoked`. La caducidad no cambia el estado:
 * se deriva de `valid_from` y `valid_until`, porque RN-P15 exige verificar
 * la vigencia en el momento de la operación y no solo al crear. Una
 * autorización vencida conserva su registro y su historial; simplemente no
 * concede permisos.
 */
enum AuthorizationStatus: string
{
    case ACTIVE = 'active';
    case REVOKED = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Vigente',
            self::REVOKED => 'Revocada',
        };
    }
}
