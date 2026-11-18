# Ziruma360 Core — Feature Document

## Especificación base
`/docs/Ziruma360_Core_Administracion_Conjunto.md`

---

## Specs

**S1.** Login funcional con usuarios globales de Laravel (Breeze/Fortify).

**S2.** Tabla `residential_complexes` con campos: id/uuid, name, code, legal_identifier, address, city, state, country_code, timezone, currency_code, status (enum: draft/active/suspended/archived), settings (JSONB), timestamps. Code inmutable cuando status=active.

**S3.** Tabla `residential_complex_memberships`: residential_complex_id, user_id, status (invited/active/suspended/revoked), invited_by, invited_at, joined_at, last_active_at. UNIQUE(residential_complex_id, user_id).

**S4.** Tabla `roles`: residential_complex_id (nullable=system), key, name, is_system, is_editable. Roles de sistema sembrados: platform_admin, complex_admin, doorman, owner, co_owner, resident, tenant, host.

**S5.** Tabla `role_permissions`: role_id, permission_key.

**S6.** Tabla `membership_roles`: membership_id, role_id, valid_from, valid_until.

**S7.** Trait `BelongsToComplex` con global scope que filtra por conjunto activo en sesión.

**S8.** Tabla `audit_logs`: residential_complex_id, actor_user_id, action, entity_type, entity_id, before_data, after_data, occurred_at, correlation_id.

**S9.** Resolución de conjunto activo en servidor (middleware), nunca confiar en ID del cliente.

**S10.** Pruebas Pest: aislamiento multi-tenant (usuario A no accede a datos B), membresías activas/suspendidas, permisos de roles.

**S11.** `property_units` completa (§3.5 del spec): `unit_type` (`apartment`, `private_parking`, `storage`, `commercial_unit`, `other`), unicidad `UNIQUE NULLS NOT DISTINCT (residential_complex_id, building_id, unit_type, number)` (PostgreSQL 15+, cubre `building_id` nulo), coherencia `building_floor_id` ↔ `building_id` por FK compuesta, `status` (`active`, `inactive`, `under_review`), desactivación en vez de borrado.

**S12.** `apartments` (§3.6 del spec): extensión 1:1 de `property_units` (`property_unit_id` UNIQUE + FK compuesta con el conjunto), `apartment_type_id` nullable, `bedrooms`, `bathrooms`, `operational_status` (`occupied`, `vacant`, `under_works`, `inactive`), `short_term_rental_enabled` `false` por defecto, `features` JSONB. **TEC-S01:** la unidad base debe ser `unit_type = apartment`, garantizado a nivel BD.

**S13.** Pruebas de `property_units` y `apartments`: unicidad con `building_id` nulo, FK compuesta piso↔edificio, apartamento solo sobre unidad tipo `apartment`, apartamento 1:1 y del mismo conjunto, aislamiento multi-tenant.

---

## Tasks

| ID | Spec | Route | Commit |
|---|---|---|---|
| T1 | S1-S2 | Commit dbab9b3 | Commit dbab9b3 |
| T2 | S3-S4 | Commit 303cfbf | Commit 303cfbf |
| T3 | S5-S6 | Commits `1e2f3a4` (factories) + `5b6c7d8` (tests) | Commits `1e2f3a4` + `5b6c7d8` |
| T4 | S7-S9 | Commit 0e41c0c | Commit 0e41c0c |
| T5 | S10 | Commit 034e40e | Commit 034e40e |
| T6 | modules | Commit 9c896e7 | Commit 9c896e7 |
| T7 | Estructura física | Commit 41c62ed | Commit 41c62ed |
| T8 | S11-S13 | Worker | Commit 6d66975 |

---

## Tasks Pendientes (según spec docs)

| ID | Descripción | Depende | Prioridad |
|---|---|---|---|
| T6 | Tabla `residential_complex_modules` (módulos habilitados por conjunto) | T1 | ✅ Done |
| T7 | Estructura física: `buildings`, `building_floors`, `apartment_types` | T6 | ✅ Done |
| T8 | `property_units` (genérica) + `apartments` (especialización) | T7 | ✅ Done (commit 6d66975) |
| T9 | `property_unit_relations` (anexos, parqueaderos) | T8 | Media |
| T10 | `common_areas` + `common_property_exclusive_use_assignments` | T7 | Media |
| T11 | `parking_facilities` + `parking_spaces` | T7 | Media |
| T12 | `property_unit_ownerships` (titularidad con historial) | T8 | Alta |
| T13 | `unit_leases` (arrendamientos) + `unit_occupancies` (ocupantes) | T12 | Alta |
| T14 | `employments` (empleados: conjunto + unidad) | T12 | Alta |
| T15 | `property_unit_authorizations` (autorizaciones de gestión) | T12 | Media |
| T16 | Tests Pest para nueva estructura física y relaciones | T7-T15 | Alta |

---

## Log

**L1.** Usuario: "vamos a iniciar un proyecto nuevo usando gentle-ai, en la carpeta /docs tengo un md que tiene de que va el proyecto, que en este caso es para un conjunto residencial, con un tema de rentas cortas."

**L2.** Decisiones confirmadas: D-01 (visitantes unificados con people), D-06 (permisos en código), D-09 (carga masiva día uno).

**L3.** Reglas de desarrollo: 1) Docker commands con `docker compose exec app`, 2) SOLID + DI con Services/Actions/DTOs, 3) Template TailAdmin de `templete-ui/` adaptado a Vue 3 + Inertia, 4) Ramas `feat/<nombre>` para cada feature, usuario hace PR.

**L4.** Rama feat/ui-template completada (d0e111a): UI TailAdmin adaptada, 30 tests pasando.

**L5.** Tarea T6 completada: residential_complex_modules. Enum ComplexModule con 6 módulos, observer para seeding automático, 15 tests pasando (commit 9c896e7).

**L6.** Tarea T7 completada: Estructura física. Building, BuildingFloor, ApartmentType con enums y BelongsToComplex. 20 tests pasando (commit 41c62ed).

**L7.** CRUD de edificios conectado a la UI (commit 66400bc): BuildingController + páginas Vue Index/Create/Show/Edit, rutas en web.php y Sidebar con solo rutas funcionales. Ese commit arrastró sin querer el modelo `PropertyUnit` y su migración, que pertenecen a T8.

**L8.** Tarea T8 completada (commit 6d66975). `property_units` con unicidad `UNIQUE NULLS NOT DISTINCT (residential_complex_id, building_id, unit_type, number)`, FK compuesta `(building_id, building_floor_id)` a `building_floors` y `apartments` como extensión 1:1 con FK compuesta al conjunto y trigger TEC-S01.

Evidencia T8:
- Suite completa en SQLite: 132 pasan, 1 skip, 365 assertions.
- Archivos T8 contra PostgreSQL 18.3 real (BD scratch): 27 pasan.
- Sondas SQL directas sobre la BD de desarrollo (Postgres 18.3) para el DDL que SQLite no cubre: unicidad con `building_id` nulo → viola la constraint; piso de otro edificio → viola la FK compuesta; apartamento sobre unidad `private_parking` → el trigger TEC-S01 aborta; segundo apartamento sobre la misma unidad → viola la unicidad 1:1; apartamento y unidad de conjuntos distintos → viola la FK compuesta.
- La BD de desarrollo quedó migrada y sin datos de prueba.

Desviaciones y decisiones tomadas en T8:
1. `ON DELETE SET NULL` en la FK compuesta anulaba también `building_id` y dejaba la cascada de borrado de un edificio en estado inconsistente (violación del CHECK). Se cambió a `ON DELETE RESTRICT` tanto en la FK compuesta como en `property_units.building_id` → buildings: un edificio o un piso con unidades asociadas no se borra, se desactiva (RN-S02/RN-S04). Cubierto por 3 tests nuevos.
2. Se descartó el bloque que reaplicaba constraints de S11 sobre esquemas legacy: la rama nunca se mergeó, así que la BD de desarrollo se realineó con `migrate:rollback --batch=5` + `migrate` y queda una sola fuente de verdad en las migraciones.
3. `PropertyUnit::apartments()` (HasMany a una clase inexistente) se reemplaza por `apartment()` (HasOne), coherente con la extensión 1:1 del spec.

Pendientes que surgieron de T8 (no incluidos en el alcance de T8):
- `ResidentialComplexApiTest::test_returns_404_for_nonexistent_building` falla solo en PostgreSQL: la columna `uuid` es de tipo nativo y una cadena no-uuid produce 500 en vez de 404. Es previo a T8 (viene de T1/T7) y queda para un arreglo de validación de uuid.
- El `BuildingController::destroy` borra el edificio sin más: con unidades asociadas ahora falla por FK. Falta el proceso controlado de desactivación previsto en RN-S02.

## Delivery

- Rama de feature: `feat/physical-structure`. Límite de slice registrado: 3.985 líneas en 7 commits desde `origin/main` (módulos 522, estructura física 992, UI de edificios 1.389, T8 1.092). Cada work unit supera el corte de ~400 líneas, así que la entrega debe ir en PRs encadenados/apilados (un PR por work unit: T6-T7, UI de edificios, T8) en lugar de un PR único. La creación de los PRs es decisión del usuario.

