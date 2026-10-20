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

---

## Log

**L1.** Usuario: "vamos a iniciar un proyecto nuevo usando gentle-ai, en la carpeta /docs tengo un md que tiene de que va el proyecto, que en este caso es para un conjunto residencial, con un tema de rentas cortas."

**L2.** Decisiones confirmadas: D-01 (visitantes unificados con people), D-06 (permisos en código), D-09 (carga masiva día uno).

**L3.** Reglas de desarrollo: 1) Docker commands con `docker compose exec app`, 2) SOLID + DI con Services/Actions/DTOs, 3) Template TailAdmin de `templete-ui/` adaptado a Vue 3 + Inertia, 4) Ramas `feat/<nombre>` para cada feature, usuario hace PR.

**L4.** Rama feat/ui-template completada (d0e111a): UI TailAdmin adaptada, 30 tests pasando.
