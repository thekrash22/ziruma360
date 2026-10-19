<?php

namespace App\Enums;

/**
 * Catálogo de permisos del sistema.
 * Cada permiso representa una acción específica que puede ser granted a un rol.
 */
enum ComplexPermission: string
{
    // Complex management
    case COMPLEX_VIEW = 'complex.view';
    case COMPLEX_EDIT = 'complex.edit';
    case COMPLEX_MANAGE_MEMBERS = 'complex.manage_members';
    case COMPLEX_MANAGE_SETTINGS = 'complex.manage_settings';
    case COMPLEX_VIEW_AUDIT = 'complex.view_audit';

    // Physical structure
    case BUILDING_VIEW = 'building.view';
    case BUILDING_CREATE = 'building.create';
    case BUILDING_EDIT = 'building.edit';
    case BUILDING_DELETE = 'building.delete';

    case FLOOR_VIEW = 'floor.view';
    case FLOOR_CREATE = 'floor.create';
    case FLOOR_EDIT = 'floor.edit';
    case FLOOR_DELETE = 'floor.delete';

    case UNIT_VIEW = 'unit.view';
    case UNIT_CREATE = 'unit.create';
    case UNIT_EDIT = 'unit.edit';
    case UNIT_DELETE = 'unit.delete';
    case UNIT_MANAGE = 'unit.manage'; // Manage own units (owner/tenant)

    // People management
    case PERSON_VIEW = 'person.view';
    case PERSON_VIEW_MINIMAL = 'person.view_minimal'; // Portería
    case PERSON_CREATE = 'person.create';
    case PERSON_EDIT = 'person.edit';
    case PERSON_DELETE = 'person.delete';

    // Ownership & occupancy
    case OWNERSHIP_VIEW = 'ownership.view';
    case OWNERSHIP_MANAGE = 'ownership.manage';
    case OCCUPANCY_VIEW = 'occupancy.view';
    case OCCUPANCY_MANAGE = 'occupancy.manage';

    // Employment
    case EMPLOYMENT_VIEW = 'employment.view';
    case EMPLOYMENT_MANAGE_COMPLEX = 'employment.manage_complex';
    case EMPLOYMENT_MANAGE_UNIT = 'employment.manage_unit';

    // Short-term rental (host module)
    case STAY_VIEW = 'stay.view';
    case STAY_CREATE = 'stay.create';
    case STAY_MANAGE = 'stay.manage';
    case STAY_APPROVE = 'stay.approve';
    case STAY_CHECKIN = 'stay.checkin';

    // Access & parking
    case ACCESS_REGISTER = 'access.register';
    case ACCESS_VIEW = 'access.view';
    case PARKING_MANAGE = 'parking.manage';
    case PARKING_ASSIGN = 'parking.assign';

    public function label(): string
    {
        return match ($this) {
            self::COMPLEX_VIEW => 'Ver conjunto',
            self::COMPLEX_EDIT => 'Editar conjunto',
            self::COMPLEX_MANAGE_MEMBERS => 'Gestionar miembros',
            self::COMPLEX_MANAGE_SETTINGS => 'Gestionar configuración',
            self::COMPLEX_VIEW_AUDIT => 'Ver auditoría',
            self::BUILDING_VIEW => 'Ver edificios',
            self::BUILDING_CREATE => 'Crear edificios',
            self::BUILDING_EDIT => 'Editar edificios',
            self::BUILDING_DELETE => 'Eliminar edificios',
            self::FLOOR_VIEW => 'Ver pisos',
            self::FLOOR_CREATE => 'Crear pisos',
            self::FLOOR_EDIT => 'Editar pisos',
            self::FLOOR_DELETE => 'Eliminar pisos',
            self::UNIT_VIEW => 'Ver unidades',
            self::UNIT_CREATE => 'Crear unidades',
            self::UNIT_EDIT => 'Editar unidades',
            self::UNIT_DELETE => 'Eliminar unidades',
            self::UNIT_MANAGE => 'Gestionar sus unidades',
            self::PERSON_VIEW => 'Ver personas',
            self::PERSON_VIEW_MINIMAL => 'Ver datos mínimos de personas',
            self::PERSON_CREATE => 'Crear personas',
            self::PERSON_EDIT => 'Editar personas',
            self::PERSON_DELETE => 'Eliminar personas',
            self::OWNERSHIP_VIEW => 'Ver titularidades',
            self::OWNERSHIP_MANAGE => 'Gestionar titularidades',
            self::OCCUPANCY_VIEW => 'Ver ocupaciones',
            self::OCCUPANCY_MANAGE => 'Gestionar ocupaciones',
            self::EMPLOYMENT_VIEW => 'Ver empleados',
            self::EMPLOYMENT_MANAGE_COMPLEX => 'Gestionar empleados del conjunto',
            self::EMPLOYMENT_MANAGE_UNIT => 'Gestionar empleados de la unidad',
            self::STAY_VIEW => 'Ver estancias',
            self::STAY_CREATE => 'Crear estancias',
            self::STAY_MANAGE => 'Gestionar estancias',
            self::STAY_APPROVE => 'Aprobar estancias',
            self::STAY_CHECKIN => 'Registrar check-in',
            self::ACCESS_REGISTER => 'Registrar accesos',
            self::ACCESS_VIEW => 'Ver accesos',
            self::PARKING_MANAGE => 'Gestionar parqueaderos',
            self::PARKING_ASSIGN => 'Asignar parqueaderos',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::COMPLEX_VIEW, self::COMPLEX_EDIT, self::COMPLEX_MANAGE_MEMBERS,
            self::COMPLEX_MANAGE_SETTINGS, self::COMPLEX_VIEW_AUDIT => 'Conjunto',
            self::BUILDING_VIEW, self::BUILDING_CREATE, self::BUILDING_EDIT, self::BUILDING_DELETE,
            self::FLOOR_VIEW, self::FLOOR_CREATE, self::FLOOR_EDIT, self::FLOOR_DELETE,
            self::UNIT_VIEW, self::UNIT_CREATE, self::UNIT_EDIT, self::UNIT_DELETE, self::UNIT_MANAGE => 'Estructura física',
            self::PERSON_VIEW, self::PERSON_VIEW_MINIMAL, self::PERSON_CREATE,
            self::PERSON_EDIT, self::PERSON_DELETE => 'Personas',
            self::OWNERSHIP_VIEW, self::OWNERSHIP_MANAGE,
            self::OCCUPANCY_VIEW, self::OCCUPANCY_MANAGE => 'Titularidad y ocupación',
            self::EMPLOYMENT_VIEW, self::EMPLOYMENT_MANAGE_COMPLEX, self::EMPLOYMENT_MANAGE_UNIT => 'Empleados',
            self::STAY_VIEW, self::STAY_CREATE, self::STAY_MANAGE, self::STAY_APPROVE, self::STAY_CHECKIN => 'Rentas cortas',
            self::ACCESS_REGISTER, self::ACCESS_VIEW, self::PARKING_MANAGE, self::PARKING_ASSIGN => 'Acceso y parqueo',
        };
    }
}
