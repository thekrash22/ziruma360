# Handoff — core Ziruma360 (continuidad entre sesiones)

Documento de traspaso. Se escribe para que una sesion nueva pueda arrancar con el estado
completo sin depender de la conversacion anterior.

## 1. Donde esta el estado

- Rama de integracion: **`stg`** (commit `97c6c76`; merges de los PR #24 `fix-doc-structure`,
  #25 `version-spec` y #26 `memberships-fixes`).
- Arbol limpio. Sin commits sin pushear ni ramas pendientes (locales y remotas borradas).
- Suite completa en verde contra PostgreSQL real: **592 pruebas / 1.658 assertions**
  (`docker compose exec -T app php artisan test`). Pint limpio sobre 228 archivos.
- **Feature doc (el contrato de todos los work units)**: `odd/tasks/ziruma360-core-admin.md`
  (specs S1-S82, Log L1-L76, tabla de Tasks y de pendientes). Espejo en
  `odd/ziruma360-core-admin/tasks.md`.
- **El spec del core ya esta versionado**: `docs/Ziruma360_Core_Administracion_Conjunto.md`
  (antes vivia en `/docs`, fuera del repo). Ojo: el `.gitignore` ignora `/docs/*` y re-incluye
  solo ese archivo; si se agrega otro documento a `docs/`, hay que incluirlo explicitamente.
- El entorno no tiene `gh`/`hub`/`glab` ni token de API: **los PR se crean a mano** desde
  `https://github.com/thekrash22/ziruma360/pull/new/<rama>`.

## 2. Lo que se cerro (todo con verificacion independiente y ronda nativa aprobada)

| Work unit | Que resuelve | Ronda nativa | Verificacion |
|---|---|---|---|
| T20 | El estado del conjunto es de plataforma: transiciones validas, precondiciones de activacion (ACE-C03), alta siempre en `draft`, la reactivacion no arrastra otros atributos | `review-b53a16e08ca9e10d` | PASS 6/6 |
| T21 | Matriz §1.3 verificable: las seis celdas sin respaldo, la plataforma sin permisos de mas y un contrato fila×columna por rol | `review-a6279ba9f46fdb9e` | PASS 8/8 |
| T22a | Superficie de membresias del conjunto: seis endpoints en `/api/v1/core/members` (listar, invitar, aceptar, suspender, revocar, roles) | `review-be2fd9644104f15d` | PASS 9/9 |
| T22b | Vigencia real del permiso (`PermissionService` delega en `ScopedPermission`) y atomicidad de la invitacion | `review-41e69d28d3e3d17f` | PASS 9/9 |

Ademas: el feature doc quedo **reparado** (mi resolucion del merge habia perdido los
encabezados de seccion) y el **spec del core quedo versionado**.

## 3. Proxima unidad: T22c (decidida por el usuario)

**Alcance: roles propios del conjunto (RN-C03).** El area de membresias quedo a medias: hoy se
pueden asignar roles del catalogo a una membresia, pero **no se pueden crear roles propios**
del conjunto.

Decisiones a tomar al abrirla (las del padre, por defecto):

1. Los roles de sistema (`is_system`) **no se editan ni se borran**; un rol propio del conjunto
   si, pero su `key` es **inmutable** (es el identificador estable, como el `code` del conjunto:
   solo cambia en `draft`). `name` unico por conjunto.
2. Los permisos de un rol propio salen **del catalogo cerrado** (`ComplexPermission`): una clave
   desconocida se rechaza con 422.
3. Permiso de la escritura: `complex.manage_members` (ya sembrado y ya con puerta de entrada
   desde T22a). **No hacen falta claves nuevas en el catalogo.**
4. Superficie propuesta: `GET /api/v1/core/roles` (sistema + propios del conjunto),
   `POST /api/v1/core/roles`, `PUT /api/v1/core/roles/{role}`. Sin borrado (un rol se desactiva,
   no se elimina) y sin pantalla todavia.
5. Aislamiento: un rol de otro conjunto no se ve ni se modifica (404/422, nunca se filtra).
6. TDD con RED observado, suite completa en verde y verificacion independiente obligatoria
   (`Risk: item 2` y `item 3`: cambia el alcance efectivo de los roles del conjunto).

**Specs a escribir**: S83 en adelante, en la seccion \`## Specs\` del feature doc, mas la fila de
la tabla de Tasks y la entrada de Log. Leer primero el feature doc hasta \`## Log\` (L67-L76) para
no repetir lo ya decidido.

**Despues**: T22d (consulta de auditoria: \`complex.view_audit\` esta sembrado **sin puerta**;
y extender el trait \`Auditable\` a los siete dominios que §1.6 exige: membresias, roles, politicas,
titularidad, ocupantes y estructura fisica — hoy solo hay traza en \`property_unit_ownerships\` y
\`residential_complexes\`).

## 4. Playbook operativo (lo que costo aprender)

1. **Ritmo de entrega**: rama \`feat/<nombre>\` desde \`stg\`, PR manual con base \`stg\`. Si la
   unidad depende de otra que todavia no esta mergeada, la rama va **encadenada** sobre esa y el
   PR tambien encadenado. Nunca acumular sobre \`main\`.
2. **Guard de RDD**: bloquea el \`git push\` si el rango commiteado no esta assessed. El assess
   va con \`baseRef\` (**sha de 40 caracteres**) + \`committedOnly: true\`. Ademas **bloquea los
   comandos compuestos**: commit y push van en llamadas separadas.
3. **La ronda nativa no corre sobre el diff acumulado del core** (\`lens_context_budget_exceeded\`):
   cada ronda se acota a su work unit con su \`baseRef\`. Si el delta es solo documental, el
   proveedor lo clasifica \`passive\` / \`acknowledged_predecessor_passive_delta\` y no procede ronda.
4. **Para la captura**, el \`baseRef\` tiene que ser el **base tree que emite el propio STATUS**
   (no el sha del commit base): con el sha da \`selector-mismatch\`. El START, en cambio, acepta el
   sha del commit base, y un sha abreviado da \`base-ref-unresolvable\`.
5. **Conflicto de merge en el feature doc**: resolver **por seccion**, no por hunk de conflicto, y
   **verificar la estructura** (los encabezados \`## ...\` y los separadores \`---\`) contra la version
   buena. Un hunk a mano puede perder los encabezados, y el doc es el contrato que leen los workers.
   Verificar ademas que no falte ninguna entrada (specs, logs, filas) frente a las dos fuentes.
6. **En el doc, nunca escapes de backtick**: escribir \`\\\`\`\` dentro de un heredoc deja el
   backslash literal y rompe el markdown. Se detecta con \`grep -c '\\\\\\\`'\`.
7. **Handoff del worker**: que apunte al documento y no repita el contrato. Un handoff largo hizo
   que un worker agotara su cuota de tokens y muriera **dejando las pruebas escritas pero sin
   implementar**: es un fallo recuperable, se relanza con el handoff mas corto.
8. **Verificacion independiente** (\`gentle-ai-verify\`): 9-10 puntos, con **mutation testing** sobre
   una copia bajo \`/tmp\` (nunca en el repo) y **diferencial** contra el commit base cuando el
   cambio altera comportamiento observable. Su hallazgo tipico —"algo funciona pero no esta
   protegido por ninguna prueba"— se cierra con una prueba propia (pasó en T22a y T22b).
9. **Comandos**: suite \`docker compose exec -T app php artisan test\` (PostgreSQL \`z360_test\`; la
   base de desarrollo se deja migrada y sin datos), focal con \`--filter\`, Pint con
   \`docker compose exec -T app ./vendor/bin/pint\`.
10. **Permisos con alcance**: \`ScopedPermission\` es la **unica** fuente de verdad (membresia
    activa + rol del conjunto o del catalogo + asignacion vigente + permiso en el catalogo).
    \`PermissionService\` y las policies delegan. No reimplementar esa consulta en ningun sitio.

## 5. Deudas abiertas

- **T22d**: consulta de auditoria (\`complex.view_audit\` sembrado sin puerta) y extender
  \`Auditable\` a los siete dominios de §1.6.
- \`isDuplicatedMembership\` (\`app/Http/Controllers/Api/Core/MembershipController.php:114\`) detecta
  el duplicado por SQLSTATE **y** por el nombre de la tabla: un \`UNIQUE\` futuro en esa tabla
  heredaria el mismo mensaje de "membresia duplicada".
- Los lentes de revision nativa (\`review-risk\`, \`review-reliability\`) **no tienen modelo por
  defecto**: hay que asignarlo en la configuracion del host. El store del perfil
  (\`~/.pi/gentle-ai/models.json\`) y \`subagents.json\` ya quedaron con las cuatro entradas; el
  store del prefijo (\`install/config/models.json\`, que es el que consulta el proveedor) lo
  asigno el usuario a mano. Sin eso **ninguna** ronda nativa puede correr.
- El \`/docs\` ya no esta ignorado, pero solo esta incluido el spec (ver la nota de la seccion 1).
