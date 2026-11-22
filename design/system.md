# Design system — Ziruma360 (TailAdmin / Tailwind v4)

Sincronizado desde las fuentes reales del proyecto. Los valores de esta tabla son los
**encontrados en el código**; cada fila declara su archivo de origen. No hay tokens
inventados: lo que no aparece aquí no existe todavía en el tema.

- Fuentes: `resources/css/admin.css` (entry), `resources/css/tailwind-admin.css` (tema TailAdmin),
  `templete-ui/src/css/style.css` (template original), `vendor/laravel/breeze/stubs/**/tailwind.config.js`.
- Aplicación: Tailwind v4 con `@theme` (sin `tailwind.config.js`). El entry `resources/css/admin.css`
  importa `tailwindcss`, activa `@tailwindcss/forms`, declara la variante `dark` contra `.dark`
  y fija `@source` a `resources/js/**/*.vue`, `resources/js/**/*.ts` y `resources/views/**/*.blade.php`.
- Referencia práctica: las pantallas existentes (`resources/js/Pages/Buildings/*`,
  `resources/js/Pages/Units/*`, `resources/js/Pages/People/*`) son el patrón a copiar.

## Colores — marca

| Token | Valor | Uso | Fuente |
|---|---|---|---|
| `--color-brand-25` | `#f2f7ff` | Fondo de marca muy claro | `resources/css/tailwind-admin.css:43` |
| `--color-brand-50` | `#ecf3ff` | Fondo de marca claro | `resources/css/tailwind-admin.css:44` |
| `--color-brand-100` | `#dde9ff` | Hover sobre fondo claro | `resources/css/tailwind-admin.css:45` |
| `--color-brand-200` | `#c2d6ff` | Borde de marca claro | `resources/css/tailwind-admin.css:46` |
| `--color-brand-300` | `#9cb9ff` | Marca deshabilitada | `resources/css/tailwind-admin.css:47` |
| `--color-brand-400` | `#7592ff` | Marca hover claro | `resources/css/tailwind-admin.css:48` |
| `--color-brand-500` | `#465fff` | **Acción primaria** (botón, foco, enlace) | `resources/css/tailwind-admin.css:49` |
| `--color-brand-600` | `#3641f5` | Hover de la acción primaria | `resources/css/tailwind-admin.css:50` |
| `--color-brand-700` | `#2a31d8` | Acción primaria presionada | `resources/css/tailwind-admin.css:51` |
| `--color-brand-800` | `#252dae` | Marca sobre fondo oscuro | `resources/css/tailwind-admin.css:52` |
| `--color-brand-900` | `#262e89` | Marca sobre fondo oscuro | `resources/css/tailwind-admin.css:53` |
| `--color-brand-950` | `#161950` | Marca más oscura | `resources/css/tailwind-admin.css:54` |

## Colores — neutros

| Token | Valor | Uso | Fuente |
|---|---|---|---|
| `--color-white` | `#ffffff` | Superficie | `resources/css/tailwind-admin.css:40` |
| `--color-black` | `#101828` | Negro de marca | `resources/css/tailwind-admin.css:41` |
| `--color-gray-25` | `#fcfcfd` | Fondo de página | `resources/css/tailwind-admin.css:69` |
| `--color-gray-50` | `#f9fafb` | Fondo de cabecera de tabla, hover de fila | `resources/css/tailwind-admin.css:70` |
| `--color-gray-100` | `#f2f4f7` | Separador de fila (`divide-gray-100`) | `resources/css/tailwind-admin.css:71` |
| `--color-gray-200` | `#e4e7ec` | Borde de tabla y de tarjeta | `resources/css/tailwind-admin.css:72` |
| `--color-gray-300` | `#d0d5dd` | Borde de input | `resources/css/tailwind-admin.css:73` |
| `--color-gray-400` | `#98a2b3` | Texto secundario fuerte, placeholder | `resources/css/tailwind-admin.css:74` |
| `--color-gray-500` | `#667085` | **Texto secundario** | `resources/css/tailwind-admin.css:75` |
| `--color-gray-600` | `#475467` | Texto de celda | `resources/css/tailwind-admin.css:76` |
| `--color-gray-700` | `#344054` | Texto fuerte | `resources/css/tailwind-admin.css:77` |
| `--color-gray-800` | `#1d2939` | **Texto de título** | `resources/css/tailwind-admin.css:78` |
| `--color-gray-900` | `#101828` | Texto principal en modo claro | `resources/css/tailwind-admin.css:79` |
| `--color-gray-950` | `#0c111d` | Negro más profundo | `resources/css/tailwind-admin.css:80` |
| `--color-gray-dark` | `#1a2231` | Superficie oscura | `resources/css/tailwind-admin.css:81` |

## Colores — semánticos

| Token | Valor | Uso | Fuente |
|---|---|---|---|
| `--color-success-500` | `#12b76a` | Éxito, estado activo | `resources/css/tailwind-admin.css:102` |
| `--color-error-500` | `#f04438` | Error, estado inválido | `resources/css/tailwind-admin.css:115` |
| `--color-warning-500` | `#f79009` | Advertencia, estado pendiente | `resources/css/tailwind-admin.css:128` |

Cada familia semántica tiene sus escalas 25-950 en el mismo archivo
(`success` 96-107, `error` 109-120, `warning` 122-133).

## Tipografía

| Token | Valor | Uso | Fuente |
|---|---|---|---|
| `--font-outfit` | `Outfit, sans-serif` | **Única familia** del proyecto | `resources/css/tailwind-admin.css:7` |
| `--text-title-2xl` | `72px` / `90px` | Portada | `resources/css/tailwind-admin.css:19` |
| `--text-title-xl` | `60px` / `72px` | Título de página grande | `resources/css/tailwind-admin.css:22` |
| `--text-title-lg` | `48px` / `60px` | Título de sección | `resources/css/tailwind-admin.css:25` |
| `--text-title-md` | `36px` / `44px` | **Título de página** (el que usan las pantallas) | `resources/css/tailwind-admin.css:28` |
| `--text-title-sm` | `30px` / `38px` | Título de tarjeta | `resources/css/tailwind-admin.css:31` |
| `--text-title-xs` | `24px` / `32px` | Título pequeño | `resources/css/tailwind-admin.css:34` |
| `--text-theme-xl` | `20px` / `30px` | Subtítulo | `resources/css/tailwind-admin.css:37` |
| `--text-theme-sm` | `14px` / `20px` | **Cuerpo** (el tamaño `text-sm` de las pantallas) | `resources/css/tailwind-admin.css:40` |
| `--text-theme-xs` | `12px` / `18px` | Metadato, etiqueta de tabla | `resources/css/tailwind-admin.css:43` |

## Radios y sombras

| Token | Valor | Uso | Fuente |
|---|---|---|---|
| `--shadow-theme-xs` | `0px 1px 2px 0px rgba(16, 24, 40, 0.05)` | Sombra mínima | `resources/css/tailwind-admin.css:148` |
| `--shadow-theme-sm` | `0px 1px 3px 0px rgba(16, 24, 40, 0.1), 0px 1px 2px 0px rgba(16, 24, 40, 0.06)` | **Tarjeta y tabla** (`shadow-sm`) | `resources/css/tailwind-admin.css:145` |
| `--shadow-theme-md` | `0px 4px 8px -2px rgba(16, 24, 40, 0.1), 0px 2px 4px -2px rgba(16, 24, 40, 0.06)` | Elemento elevado | `resources/css/tailwind-admin.css:139` |
| `--shadow-theme-lg` | `0px 12px 16px -4px rgba(16, 24, 40, 0.08), 0px 4px 6px -2px rgba(16, 24, 40, 0.03)` | Modal, panel | `resources/css/tailwind-admin.css:142` |
| `--shadow-theme-xl` | `0px 20px 24px -4px rgba(16, 24, 40, 0.08), 0px 8px 8px -4px rgba(16, 24, 40, 0.03)` | Superficie-modal | `resources/css/tailwind-admin.css:149` |
| `--shadow-focus-ring` | `0px 0px 0px 4px rgba(70, 95, 255, 0.12)` | **Foco de input** | `resources/css/tailwind-admin.css:153` |
| `--fc-classic-border` | `var(--color-gray-200)` | Borde clásico de tabla | `resources/css/tailwind-admin.css:869` |
| radio de arrastre | `0.75rem` | Radio de la ficha arrastrable | `resources/css/tailwind-admin.css:791` |

Los radios de utilidad (`rounded-lg`, `rounded-full`, `rounded-sm`) son los de Tailwind v4: el
tema no los sobrescribe, así que se usan tal cual.

## Puntos de corte

| Token | Valor | Fuente |
|---|---|---|
| `--breakpoint-2xsm` | `375px` | `resources/css/tailwind-admin.css:10` |
| `--breakpoint-xsm` | `425px` | `resources/css/tailwind-admin.css:11` |
| `--breakpoint-sm` | `640px` | `resources/css/tailwind-admin.css:13` |
| `--breakpoint-md` | `768px` | `resources/css/tailwind-admin.css:14` |
| `--breakpoint-lg` | `1024px` | `resources/css/tailwind-admin.css:15` |
| `--breakpoint-xl` | `1280px` | `resources/css/tailwind-admin.css:16` |
| `--breakpoint-2xl` | `1536px` | `resources/css/tailwind-admin.css:17` |

## Reglas de componente

Las clases que arman las pantallas están en los propios SFC; estas son las que ya usan y hay
que replicar (fuente: `resources/js/Pages/Buildings/Index.vue`):

| Elemento | Regla | Fuente |
|---|---|---|
| Layout de página | `<AppLayout>` + `<Head title="…">` de Inertia | `resources/js/Pages/Buildings/Index.vue:1-3` |
| Título de página | `text-title-md font-semibold text-gray-800 dark:text-white` | `resources/js/Pages/Buildings/Index.vue:88` |
| Subtítulo | `mt-1 text-sm text-gray-500 dark:text-gray-400` | `resources/js/Pages/Buildings/Index.vue:91` |
| Botón primario | `inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600` | `resources/js/Pages/Buildings/Index.vue:97` |
| Input | `w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white` | `resources/js/Pages/Buildings/Index.vue:123` |
| Tarjeta / tabla | `overflow-hidden rounded-sm border border-stroke bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900` | `resources/js/Pages/Buildings/Index.vue:159` |
| Cabecera de tabla | `px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400` | `resources/js/Pages/Buildings/Index.vue:164` |
| Fila | `divide-y divide-gray-100 dark:divide-gray-800` + `hover:bg-gray-50 dark:hover:bg-gray-800/50` | `resources/js/Pages/Buildings/Index.vue:184` |
| Badge de estado | `rounded-full px-2.5 py-0.5 text-xs font-medium` + color por estado | `resources/js/Pages/Buildings/Index.vue:208` |
| Aviso de éxito | `mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-gray-400` | `resources/js/Pages/Buildings/Index.vue:107` |
| Aviso de error | `mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-gray-400` | `resources/js/Pages/Buildings/Index.vue:110` |

Menú lateral (`resources/js/Components/Sidebar.vue`): grupos con `title` e `items` de
`{ label, route, icon }`; un item con `route: ''` es un enlace vacío (hoy los grupos
"Próximamente" lo usan).

## Self-check

Antes de dar por buena una pantalla nueva:

1. Solo se usan tokens de esta tabla (o los de Tailwind v4 no sobrescritos: `rounded-*`,
   `px/py/gap`).
2. Cada color de texto tiene su par `dark:` (`text-gray-500 dark:text-gray-400`).
3. Cada acción primaria usa `bg-brand-500` con su `hover:bg-brand-600`.
4. Los inputs llevan `focus:border-brand-500 focus:ring-1 focus:ring-brand-500`.
5. Las tablas usan el bloque de cabecera y el de fila de arriba, con `divide-y`.
6. Los estados vacíos y de error tienen un mensaje, no una tabla en blanco.
