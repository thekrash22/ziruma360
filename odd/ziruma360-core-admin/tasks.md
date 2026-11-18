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
- Commit: b0bcb09 (alcance) + bfb8446 (people) + 11a6ace (titularidades) + 7a5ef4d (corrección RN-P04 concurrente / RN-P05 inmutable) + 5ea5650 (corrección RN-P04 por solapamiento / RN-P05 campos de auditoría) + e0b66ae (cierre documental) + b05e535 (harness PostgreSQL) + f3c2544 (migraciones sin ramas SQLite) + 25acd7b (404 ante UUID inválido) + f79586c (RN-P04 como máximo instantáneo, CHECK de dominio, tests de concurrencia y bloqueo de UPDATE) + 2584134 (cierre de los hallazgos de la revisión nativa)
- Verificación: suite completa en PostgreSQL 18.3 real **186 pasan, 0 skip, 442 assertions** sobre la base de pruebas dedicada `z360_test`; verificación independiente `gentle-ai-verify` (S14/S15/S16 PASS con salvedad) con ocho hallazgos F1-F8, corregidos los aplicables; base de desarrollo migrada, alineada y sin datos de prueba
- Decisión PostgreSQL-only: el proyecto es exclusivamente PostgreSQL (decisión del usuario); se retiraron las ramas SQLite de las cuatro migraciones nuevas y la suite dejó de correr sobre SQLite en memoria
- Revisión nativa: lineage `review-e0a0385f4eac0b57` y sucesor `review-f4a03f11445fdda4` (dos rondas de hallazgos CRITICAL corregidas y verificadas; slot de corrección inejecutable por defecto del proveedor). Línea fresca `review-facb23b1bdc35ac5` sobre el slice de T12 (21 archivos, 2.324 líneas, tier alto): el lente de riesgo se admitió y el de fiabilidad fue rechazado cinco veces en admisión por defectos de forma del resultado del reviewer (sin mutar el repositorio) hasta ser admitido; cerró en `correction_required` con un CRITICAL (`R3-SQLITE-MIGRATION-REGRESSION`) que **contradice la decisión PostgreSQL-only del usuario y no se corrige**, y un no bloqueante (`R3-CONCURRENCY-OUTCOME-UNTESTED`) corregido en `2584134`, junto con `R3-RN-P05-CREATED-AT` (ahora `created_at` es inmutable), `R3-TEST-DB-RACE` y `R3-FACTORY-MAKE-PERSISTS-DEPENDENCIES`
- Segunda revisión nativa (slice de correcciones): lineage `review-a7b83fb7c72aff95`, escaló a stop terminal (`native_stop_required`) por `R3-CLEANUP-DELETE-ORDER` (causalidad desconocida) y el no bloqueante `R3-CONCURRENCY-BLOCK-NOT-PROVED`; ambos corregidos en `884cec2` (limpieza en orden de dependencias explícito y espera determinista de la contención real por `pg_stat_activity`)
- Tercera revisión nativa: lineage `review-1f0c20b03bb865ab` (candidato `8839e4c..HEAD`) **APROBADA** con la autoridad quemada y sin hallazgos bloqueantes; el primer intento de captura falló por una caída de red del proveedor sin mutar el repositorio y el slot reofrecido se relanzó con éxito
- Deuda abierta: proceso controlado de desactivación de un conjunto con historial (la FK `RESTRICT` responde con error de base de datos, no con mensaje de negocio); eficacia del bloqueo de RN-P04 bajo `REPEATABLE READ`/`SERIALIZABLE`; `notes` mutable y fuera del spec; RN-P01 y SEG-P01 diferidas

## T13: unit_leases + unit_occupancies
- Status: done
- Specs: S17, S18, S19
- Dependencias: T12
- Prioridad: Alta
- Rama: feat/leases
- Commit: edfb76a (alcance) + 5e56acd (unit_leases y sus CHECK) + 6ed0d8e (unit_occupancies con los triggers RN-P07/RN-P09/RN-P10) + e8fefcd (relaciones en PropertyUnit y Person) + 8a847cf (pruebas de las dos tablas) + 984e19b (límite de slice) + 1c8c91b (cierre de la carrera con FOR SHARE y prueba de concurrencia) + 24b29e6 (cierre documental de T13)
- Verificación: suite completa en PostgreSQL 18.3 real **217 pasan, 0 skip, 511 assertions** (186 previos + 31 nuevos); sonda por SQL crudo que confirma la creación automática de la ocupación `tenant_resident` y el cierre con revocación de delegaciones; catálogo de 5 triggers y 5 funciones; base de desarrollo migrada, alineada y sin residuos
- Garantías: FK compuestas `RESTRICT` hacia `property_units`, `people` y `unit_leases` (ACE-C02), CHECK de vigencia y dominios, `UNIQUE` parcial del contacto principal vigente (RN-P07), `UNIQUE` parcial de la ocupación `tenant_resident` vigente por arrendamiento y de la ocupación vigente por persona y unidad, RN-P09 (el arrendamiento crea o vincula la ocupación del arrendatario; la ocupación con `lease_id` es de ese arrendatario y de esa unidad), RN-P10 (terminar el arrendamiento cierra la ocupación y revoca las delegaciones)
- Concurrencia: el trigger que valida la ocupación lee el estado del arrendamiento con `FOR SHARE`; la prueba de concurrencia se verificó en rojo sin el bloqueo y en verde con él
- Revisión nativa: **aprobada**. Con RDD activo, la proyección `workspace` contra `origin/main` (T8+T12+T13) excedió el presupuesto de contexto del proveedor (`lens_context_budget_exceeded`, sin autoridad creada); el slice reducido `stg..feat/leases` (2.061 líneas, 16 rutas) sí es revisable (`reviewDue: true`, riesgo nativo alto por `dangerous_sink` y `process_boundary` en la prueba de concurrencia). Lineage `review-6b5a7e65db13d8b9`, target `sha256:970b704a96fb7c0d9a4c29488956755b72f2b392f9627c7d1654b39cd5fcd9e7`, lente `review-risk`, tier alto, presupuesto de corrección de 200 líneas: el slot materializó el artefacto del revisor (1 corrida de modelo, transporte `pi_host_relay`, revisor `minimax/MiniMax-M2.7`) y el lineage **cerró en `approved`**; el reconocimiento exacto quemó la autoridad (`gentle-ai.review-acknowledged/v1`), sin hallazgos bloqueantes ni observaciones
- Diferidos con registro: la revocación de las autorizaciones dependientes (T15), la política de aprobación del arrendamiento (propuesta abierta del spec §4.5) y el vocabulario cerrado de `delegated_permissions` (el spec no lo define: se valida la forma, objeto JSON con valores booleanos)

## T14: employments (empleados: conjunto + unidad)
- Status: done
- Specs: S20, S21, S22, S23, S24
- Dependencias: T12
- Prioridad: Alta
- Rama: feat/employments (desde `feat/leases`)
- Commit: 1419752 (alcance) + 9da83c4 (tabla con la garantía de RN-P11) + c5d05cb (modelo, enums y factory) + 4768f4e (relaciones en `Person` y `PropertyUnit`) + 2d82600 (pruebas)
- Alcance: `person_id`, `employer_property_unit_id` nullable, `employment_type` (`complex_staff`, `unit_domestic`, `contractor`), `position`, `schedule` (JSONB validado), `valid_from`, `valid_until`, `status`, `access_authorized`; FK compuestas `RESTRICT` a `people` y `property_units`, CHECK de vigencia, de dominios, de `schedule` y de coherencia tipo↔empleador, RN-P11 en la base (acceso exige cuenta y membresía activa) y no duplicación de empleo activo equivalente
- Verificación: suite completa en PostgreSQL 18.3 real **239 pasan, 0 skip, 554 assertions** (217 previos + 22 nuevos); sonda por SQL crudo que confirma el alta válida y el rechazo en la base de RN-P11, de la coherencia tipo-empleador, del `schedule` no objeto y del empleo activo duplicado; catálogo de 2 triggers y 18 constraints; base de desarrollo migrada y `z360_test` sin residuos
- Slice: 997 líneas en 10 archivos y 5 commits sobre `feat/leases`; entrega en PR propio con `size:exception` (dos unidades cohesivas superan el corte de ~400 líneas)
- Revisión nativa: pendiente para este slice (el de T13 quedó aprobado en `review-6b5a7e65db13d8b9`)
- Diferidos con registro: RN-P12 (política del conjunto para el acceso recurrente del empleado doméstico: no existe la tabla de políticas), el vocabulario de `schedule` (el spec solo pide "JSONB validado") y la asignación del rol dentro de la membresía, que se valida en la aplicación

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
