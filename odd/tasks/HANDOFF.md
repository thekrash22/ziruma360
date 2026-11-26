# Handoff — core Ziruma360 (continuidad entre sesiones)

Documento de traspaso. Se escribe para que una sesion nueva pueda arrancar con el estado
completo sin depender de la conversacion anterior.

## 1. Donde esta el estado

- Rama de integracion: **`stg`** (commit `abbcc2a`; merges de los PR #24 `fix-doc-structure`,
  #25 `version-spec`, #26 `memberships-fixes` y #27 `handoff-t22c`).
- Rama de trabajo: **`feat/complex-tenant-root`** (T23), pusheada y esperando el PR manual a `stg`.
- Arbol limpio. Sin commits sin pushear.
- Suite completa en verde contra PostgreSQL real: **652 pruebas / 2.034 assertions**
  (`docker compose exec -T app php artisan test`). Pint limpio sobre 245 archivos.
- **Feature doc (el contrato de todos los work units)**: `odd/tasks/ziruma360-core-admin.md`
  (specs S1-S89, Log L1-L79, tabla de Tasks y de pendientes). Espejo en
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
| T22c | Roles propios del conjunto (RN-C03): `GET/POST/PUT /api/v1/core/roles`, con la `key` inmutable, el `name` unico por conjunto, los permisos del catalogo cerrado y la baja por desactivacion | `review-52865f31707e648b` | PASS 10/10 |
| T23 | Capa del tenant root: `/api/v1/complexes` (la ruta vieja desaparece sin alias), tres pantallas web de conjuntos, el conjunto activo siempre en la cabecera, el bootstrap por consola y el dashboard con datos reales | `review-28b92ea23e099ffc` + `review-d298105e26709f1d` | PASS 10/10 |

Ademas: el feature doc quedo **reparado** (mi resolucion del merge habia perdido los
encabezados de seccion) y el **spec del core quedo versionado**. T22c sumo la superficie de
roles del conjunto (`RolePolicy`, `RoleResource`, las dos requests y la migracion
`is_active`), con la baja por desactivacion: `ScopedPermission` y `MembershipResource`
filtran el rol inactivo, de modo que deja de conceder permisos de inmediato.

## 3. Proxima unidad: T22d (consulta de auditoria)

**Alcance: la auditoria (§1.6).** Dos frentes: `complex.view_audit` esta sembrado **sin
puerta de entrada** (ningun endpoint lo exige todavia), y el trait `Auditable` solo lo usan
`residential_complexes` y `property_unit_ownerships`, mientras §1.6 exige traza en los
siete dominios: membresias, roles, politicas, titularidad, ocupantes y estructura fisica.

Decisiones a tomar al abrirla (las del padre, por defecto):

1. La puerta de la consulta es la matriz de §1.3: `complex.view_audit` lo exige el
   administrador del conjunto (no el portero ni el resident).
2. La traza se extiende con el trait `Auditable` que ya existe (no un mecanismo nuevo):
   `ResidentialComplexMembership`, `Role` y los dominios que falten.
3. El alcance de la consulta es el conjunto activo: no hay consulta global entre conjuntos.
4. TDD con RED observado, suite completa en verde y verificacion independiente obligatoria
   (`Risk: item 3`: cambia el contrato de superficie y agrega el historico a varios dominios).

**Specs a escribir**: S90 en adelante, en la seccion `## Specs` del feature doc, mas la fila
de la tabla de Tasks y la entrada de Log. Leer primero el feature doc hasta `## Log`
(L78-L79) para no repetir lo ya decidido.

**Deuda que deja T22c** (arranca con esto a la vista): un rol desactivado puede seguir
asignado a una membresia; no concede nada (lo filtra `ScopedPermission`), pero no hay puerta
que lo impida.

**Deuda que deja T23** (arranca con esto a la vista):

1. `z360:create-complex` puede promover a plataforma una cuenta **existente** que solo se nombra
   por correo (`R1-bootstrap-privilege-escalation` de la ronda nativa, refutado pero registrado):
   el comando da el rol de plataforma a la cuenta que le pidan, exista o no.
2. El comando imprime el **detalle crudo de la excepcion** ante un fallo de base
   (`R1-command-secret-disclosure`, informativo): puede filtrar fragmentos de SQL a la consola.
   Es trabajo posterior, no una correccion de T23.
3. El conjunto se crea con `address` y `city` **vacios** (las columnas son NOT NULL y el
   contrato del comando no las pide): editables mientras el conjunto este en `draft`.
4. No hay pantallas de **politicas** ni de **modulos** (el detalle muestra los modulos ya
   sembrados, pero no se pueden habilitar ni desactivar).
5. El panel lateral sigue anunciando "Proximamente" en areas que no existen.

**Como arrancar el sistema** (para validar la vista con datos reales, no de fabrica):

```sh
docker compose exec -T app php artisan z360:create-complex \
  --name="Altos del Parque" --code="altos-del-parque" \
  --admin-email="admin@altosdelparque.test" --admin-name="Ana Administracion" \
  --admin-password="z360-admin-local-2026"
```

Crea el conjunto en `draft`, el administrador, la membresia activa y el rol `platform_admin`.
Es idempotente por `code`: si el conjunto ya existe, no crea nada y devuelve codigo 1.

## 4. Playbook operativo (lo que costo aprender)

1. **Ritmo de entrega**: rama `feat/<nombre>` desde `stg`, PR manual con base `stg`. Si la
   unidad depende de otra que todavia no esta mergeada, la rama va **encadenada** sobre esa y el
   PR tambien encadenado. Nunca acumular sobre `main`.
2. **Guard de RDD**: bloquea el `git push` si el rango commiteado no esta assessed. El assess
   va con `baseRef` (**sha de 40 caracteres**) + `committedOnly: true`. Ademas **bloquea los
   comandos compuestos**: commit y push van en llamadas separadas.
3. **La ronda nativa no corre sobre el diff acumulado del core** (`lens_context_budget_exceeded`):
   cada ronda se acota a su work unit con su `baseRef`. Si el delta es solo documental, el
   proveedor lo clasifica `passive` / `acknowledged_predecessor_passive_delta` y no procede ronda.
4. **Para la captura**, el `baseRef` tiene que ser el **base tree que emite el propio STATUS**
   (no el sha del commit base): con el sha da `selector-mismatch`. El START, en cambio, acepta el
   sha del commit base, y un sha abreviado da `base-ref-unresolvable`.
5. **Conflicto de merge en el feature doc**: resolver **por seccion**, no por hunk de conflicto, y
   **verificar la estructura** (los encabezados `## ...` y los separadores `---`) contra la version
   buena. Un hunk a mano puede perder los encabezados, y el doc es el contrato que leen los workers.
   Verificar ademas que no falte ninguna entrada (specs, logs, filas) frente a las dos fuentes.
6. **En el doc, nunca escapes de backtick**: escribir codigos de backtick escapados dentro de
   un heredoc deja el backslash literal y rompe el markdown. Se detecta contando la
   secuencia backslash+backtick (ver el punto 13).
7. **Handoff del worker**: que apunte al documento y no repita el contrato. Un handoff largo hizo
   que un worker agotara su cuota de tokens y muriera **dejando las pruebas escritas pero sin
   implementar**: es un fallo recuperable, se relanza con el handoff mas corto.
8. **Verificacion independiente** (`gentle-ai-verify`): 9-10 puntos, con **mutation testing** sobre
   una copia bajo `/tmp` (nunca en el repo) y **diferencial** contra el commit base cuando el
   cambio altera comportamiento observable. Su hallazgo tipico —"algo funciona pero no esta
   protegido por ninguna prueba"— se cierra con una prueba propia (pasó en T22a y T22b).
9. **Comandos**: suite `docker compose exec -T app php artisan test` (PostgreSQL `z360_test`; la
   base de desarrollo se deja migrada y sin datos), focal con `--filter`, Pint con
   `docker compose exec -T app ./vendor/bin/pint`.
10. **Permisos con alcance**: `ScopedPermission` es la **unica** fuente de verdad (membresia
    activa + rol del conjunto o del catalogo + asignacion vigente + permiso en el catalogo).
    `PermissionService` y las policies delegan. No reimplementar esa consulta en ningun sitio.
11. **La ronda nativa hay que acotarla**: el `START` por defecto apunta a `main` y el
    candidato sale como el diff acumulado del core (`lens_context_budget_exceeded`). El `START`
    acepta el sha de 40 del commit base con `committedOnly: true` (para una unidad, el de
    `stg`) y ahi el candidato es solo la unidad.
12. **Un lente por ronda en este host**: el `START` se rechaza con `lenses-invalid` si se piden
    dos o mas; con uno solo (`risk`) pasa. Los cuatro lentes del store del perfil no llegan al
    proveedor: el store del prefijo es el que manda.
13. **Contar los escapes de backtick**: el `grep -c` de bash no distingue el backslash literal y
    cuenta lineas con backtick. Para contar de verdad:
    `python3 -c "print(open(f).read().count(chr(92)+chr(96)))"`.
14. **El commit documental de cierre no necesita otra ronda**: el guard de RDD vuelve a avisar
    que hay un candidato sin revisar (es el mismo codigo con el delta del cierre), pero el
    proveedor lo clasifica `acknowledged_predecessor_passive_delta` y **detiene** la ronda: el
    predecesor ya aprobado con la autoridad quemada mas un delta solo documental no se revisa.
    Es el cierre esperado, no un pendiente.

## 5. Deudas abiertas

- **T22d**: consulta de auditoria (`complex.view_audit` sembrado sin puerta) y extender
  `Auditable` a los siete dominios de §1.6.
- Un rol desactivado puede seguir asignado a una membresia: no concede nada (lo filtra
  `ScopedPermission`), pero no hay puerta que lo impida.
- `isDuplicatedMembership` (`app/Http/Controllers/Api/Core/MembershipController.php:114`) detecta
  el duplicado por SQLSTATE **y** por el nombre de la tabla: un `UNIQUE` futuro en esa tabla
  heredaria el mismo mensaje de "membresia duplicada".
- Los lentes de revision nativa **no tienen modelo por defecto**: hay que asignarlo en la
  configuracion del host. El store del perfil (`~/.pi/gentle-ai/models.json`) y
  `subagents.json` ya quedaron con las cuatro entradas, pero el store del prefijo
  (`install/config/models.json`, que es el que consulta el proveedor) solo tiene el de
  `review-risk`: con dos o mas lentes el `START` se rechaza con `lenses-invalid`.
- El `/docs` ya no esta ignorado, pero solo esta incluido el spec (ver la nota de la seccion 1).
