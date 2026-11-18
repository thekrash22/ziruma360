# Ziruma360 Core — ODD Tasks Mirror

Espejo de `odd/tasks/ziruma360-core-admin.md`. Fuente de verdad: el feature doc.

## T1: Autenticación y residential_complexes
- Status: done
- Specs: S1, S2
- Commit: dbab9b3

## T2: Membresías y roles de sistema
- Status: done
- Specs: S3, S4
- Commit: 303cfbf

## T3: Permisos y membership_roles
- Status: done
- Specs: S5, S6
- Commits: 1e2f3a4, 5b6c7d8

## T4: Trait BelongsToComplex y contexto de conjunto activo
- Status: done
- Specs: S7, S8, S9
- Commit: 0e41c0c

## T5: Pruebas de aislamiento multi-tenant
- Status: done
- Specs: S10
- Commit: 034e40e

## T6: residential_complex_modules (módulos por conjunto)
- Status: done
- Specs: modules
- Commit: 9c896e7

## T7: Estructura física (buildings, building_floors, apartment_types)
- Status: done
- Specs: estructura física
- Commit: 41c62ed

## T8: property_units + apartments
- Status: done
- Specs: S11, S12, S13
- Dependencias: T7
- Commit: 6d66975

## T9: property_unit_relations (anexos, parqueaderos)
- Status: pending
- Dependencias: T8
- Prioridad: Media

## T10: common_areas + common_property_exclusive_use_assignments
- Status: pending
- Dependencias: T7
- Prioridad: Media

## T11: parking_facilities + parking_spaces
- Status: pending
- Dependencias: T7
- Prioridad: Media

## T12: property_unit_ownerships (titularidad con historial)
- Status: pending
- Dependencias: T8
- Prioridad: Alta

## T13: unit_leases + unit_occupancies
- Status: pending
- Dependencias: T12
- Prioridad: Alta

## T14: employments (empleados: conjunto + unidad)
- Status: pending
- Dependencias: T12
- Prioridad: Alta

## T15: property_unit_authorizations
- Status: pending
- Dependencias: T12
- Prioridad: Media

## T16: Suite Pest de estructura física y relaciones
- Status: pending
- Dependencias: T8-T15
- Prioridad: Alta
