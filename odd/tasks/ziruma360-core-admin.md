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

---

## Tasks Pendientes (según spec docs)

| ID | Descripción | Depende | Prioridad |
|---|---|---|---|
| T6 | Tabla `residential_complex_modules` (módulos habilitados por conjunto) | T1 | ✅ Done |
| T7 | Estructura física: `buildings`, `building_floors`, `apartment_types` | T6 | ✅ Done |
| T8 | `property_units` (genérica) + `apartments` (especialización) | T7 | Alta |
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
