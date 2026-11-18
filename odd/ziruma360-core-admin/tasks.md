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
- Commit: 6d66975 + 75f5085

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

## T12: people + property_unit_ownerships
- Status: done
- Specs: S14, S15, S16
- Dependencias: T8
- Rama: feat/ownerships
- Commit: b0bcb09 (alcance) + bfb8446 (people) + 11a6ace (titularidades) + 7a5ef4d (corrección RN-P04 concurrente / RN-P05 inmutable) + 5ea5650 (corrección RN-P04 por solapamiento / RN-P05 campos de auditoría)
- Verificación: SQLite 179 pasan + 2 skip (434 assertions); PostgreSQL 18.3 real 46 tests (67 assertions) sobre base temporal; sondas SQL sobre la BD de desarrollo; base scratch eliminada y BD de desarrollo migrada sin datos de prueba
- Revisión nativa: lineage `review-e0a0385f4eac0b57` y sucesor `review-f4a03f11445fdda4`, dos rondas de hallazgos CRITICAL (carrera de RN-P04, inmutabilidad de RN-P05, dependencia temporal de RN-P04, campos de auditoría mutables) corregidas y verificadas; el slot de captura del plan de corrección quedó inejecutable por un defecto del proveedor (`capture-binding-rejected / stale`), con `mutation_performed: false` y sin mutar el repositorio. Lineage abierto para inspección del mantenedor.
- Deuda abierta: proceso controlado de desactivación de un conjunto con historial (la FK `RESTRICT` responde con error de base de datos, no con mensaje de negocio)

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

## T17: Retirar el modelo legacy y migrar sus rutas
- Status: pending
- Dependencias: T12
- Prioridad: Alta
- Nota: `unidades`, `personas` y `persona_unidad` siguen en uso por las rutas web `/units`, `/people` y la API `/api/v1/units`, `/api/v1/people`.
