# Design: Hub de Ajustes «Atareao»

## Context

El plugin `atareao-functionality` expone hoy **cuatro paneles de configuración repartidos** entre dos menús de wp-admin. El inventario verificado a 2026-10-03 es:

| Hoy | Página | Slug | Módulo / fichero | Render |
|---|---|---|---|---|
| Ajustes | Matrix API | `atareao-matrix-config` | `\Atareao\MatrixConfig` — `includes/class-matrix-config.php` | `renderConfigPage()` |
| Ajustes | PocketID Login | `pocketid-login` | `\Atareao\PocketIDLogin` — `includes/class-pocketid-login.php` | `renderSettingsPage()` |
| Ajustes | Analítica | `atareao-analytics` | `\Atareao\Analytics` — `includes/class-analytics.php` | `renderSettingsPage()` |
| **Apariencia** | Theme Options | `atareao-theme-options` | `\Atareao\ThemeOptions` — `includes/class-theme-options.php` (`add_theme_page`) | `renderOptionsPage()` |

Los cuatro métodos de render son `public static`. Cada módulo los registra desde su `init()` en `admin_menu`. Nada más en el plugin registra pantallas de ajustes: `class-metaboxes.php` y `class-post-types.php` solo enganchan assets en pantallas de edición de CPT. El bootstrap es `atareao-functionality.php` (`require_once` en L25-41 + `\Atareao\X::init()` en L45-59).

Detalles funcionales que condicionan el diseño:

- **Matrix** guarda por POST contra sí misma con nonce `atareao_matrix_config` (campos `atareao_matrix_url`, `atareao_matrix_token`, `atareao_matrix_room`) y tiene un botón «Enviar mensaje de prueba».
- **PocketID** guarda por POST contra sí misma con nonce `atareao_pocketid_config`, con botón «Probar conexión» y el campo secret con patrón «dejar en blanco para conservar el actual».
- **Analítica** guarda en `admin_init` (`maybeSaveSettings`, nonce, transient `atareao_analytics_notice`) y tiene la acción de importación del plugin legado; `maybeSnapshotLegacySettings` también cuelga de `admin_init`.
- **Tema** usa la **Settings API**: `register_setting('atareao_options_group', …)` en `admin_init` para `atareao_social_{youtube,ivoox,spotify,apple,telegram,x,mastodon,github,linkedin}` (`esc_url_raw`), `atareao_podcast_feed` (`esc_url_raw`), `atareao_opengist_server` y `atareao_opengist_username` (`esc_url_raw` / `sanitize_text_field`, ambos con `show_in_rest => true` y `default => ''`). Su formulario es `<form method="post" action="options.php">` + `settings_fields('atareao_options_group')` + `submit_button()`.

Restricciones del repo: **no existe framework de tests ni build tools**; la verificación es `just php-lint`, `just phpcs`, un arnés externo de stubs y E2E manual en wp-admin. PSR12, PHP 8.3. Regla de separación: la funcionalidad va en el plugin, el tema es solo presentación.

## Goals / Non-Goals

**Goals**

- Un único punto de entrada «Atareao» en el menú Ajustes, con cuatro pestañas navegables por URL y sin JavaScript.
- Que el hub sea el único dueño del registro de la página y del envoltorio visual (`.wrap`/`<h1>`), delegando el contenido en los módulos.
- Conservar **intacto** el comportamiento de cada módulo: opciones, nonces, capability checks, saneado, avisos y destinos de formulario.
- Que la pestaña «Tema» guarde por `options.php` y vuelva correctamente al hub mostrando el aviso (`settings_errors()`).
- Caracterizar (Phase 0) `MatrixConfig` y `ThemeOptions`, que no tenían spec.

**Non-Goals**

- No renombrar ni borrar ninguna opción.
- No cambiar el HTML público de la analítica, el flujo de login/logout, las notificaciones Matrix ni el microsite `/tools/`.
- No introducir redirecciones, slugs ocultos ni aliases de compatibilidad para las URLs antiguas.
- No unificar formularios ni grupos de opciones.
- No tocar el tema, ni la CSP, ni el pipeline de release.

## Decisions

### Decisión 1: Cuatro pestañas con orden, etiquetas y slugs fijos

El hub tendrá cuatro pestañas, en este orden y con estas etiquetas/slugs: `matrix` → «Matrix», `pocketid` → «PocketID», `umami` → «Umami», `tema` → «Tema».

**Consecuencias:** el orden es determinista y la whitelist es una estructura fija del hub (`tab => etiqueta`). Las etiquetas visibles evitan jerga técnica («Umami» sustituye a «Analítica»; «Tema» a «Theme Options») y el slug `tema` es coherente con la interfaz en español.

**Alternativa descartada:** usar las etiquetas antiguas («Analítica», «Theme Options»). Mantendría la nomenclatura interna pero perdería coherencia con el nuevo hub en español.

### Decisión 2: Punto de entrada único

Se registra una sola página con `add_options_page(__('Atareao'), __('Atareao'), 'manage_options', 'atareao-settings', …)`, con etiqueta de menú «Atareao» y título de página «Ajustes de Atareao».

**Consecuencias:** el menú Ajustes muestra una sola entrada del plugin. La entrada «Theme Options» desaparece de Apariencia. La ruta canónica es `options-general.php?page=atareao-settings&tab=<slug>`.

**Alternativa descartada:** dejar la página bajo Apariencia o crear una página de nivel superior propia. Ajustes es el lugar natural para configuración y evita un menú de primer nivel para cuatro pestañas.

### Decisión 3: Pestañas por URL, sin JS

La pestaña activa se resuelve leyendo `tab` de `$_GET` y comparándolo contra una **whitelist**. Se usa la barra de core (`nav-tab-wrapper`/`nav-tab`/`nav-tab-active`), se envuelve en `<nav class="nav-tab-wrapper" aria-label="…">` y el enlace activo lleva `aria-current="page"`. Si `tab` falta o no es conocido, la pestaña activa es la primera (`matrix`).

**Consecuencias:** la navegación funciona sin JavaScript, es enlazable y accesible. La whitelist impide que un `tab` arbitrario seleccione contenido no previsto.

**Alternativa descartada:** pestañas con JavaScript (ocultar/mostrar paneles). Requiere JS, rompe el enlace directo a una pestaña y necesita `wp_enqueue_script` para algo que el core ya resuelve con enlaces.

### Decisión 4: Refactor limpio; el hub es el único dueño del registro y del envoltorio

El hub registra la página, pinta `.wrap` + `<h1>` y delega el contenido llamando a `MatrixConfig::renderConfigPage()`, `PocketIDLogin::renderSettingsPage()`, `Analytics::renderSettingsPage()` y `ThemeOptions::renderOptionsPage()`. Los cuatro módulos **pierden** su `add_options_page`/`add_theme_page` (y su `add_action('admin_menu', …)`) y su `<div class="wrap">` + `<h1>`. **Nada más cambia en ellos**: conservan nonces, capability checks, saneado, avisos y sus formularios siguen apuntando a donde apuntan hoy.

**Consecuencias:** un único envoltorio, sin anidamientos. Si un módulo conservara su `.wrap`, WordPress lo renderizaría dentro del del hub y el HTML quedaría anidado (aunque WordPress tolera `.wrap` repetidos, el resultado es inconsistente y descuadra el layout). Los renders siguen siendo `public static` y son invocables desde el hub.

**Alternativa descartada:** que cada módulo conserve su envoltorio y el hub lo omita. Rompe la separación: el hub no podría garantizar un único `<h1>` ni la estructura de pestañas.

### Decisión 5: Sin redirecciones de compatibilidad

Las cuatro URLs antiguas dejan de existir. No se registran slugs ocultos, redirecciones ni aliases.

**Consecuencias:** comportamiento explícito y sin deuda. Quien tuviera un bookmark verá que la página ya no existe en ese slug; la ruta nueva es `options-general.php?page=atareao-settings&tab=<slug>`. Solo afecta a wp-admin (administradores).

**Alternativa descartada:** registrar las páginas antiguas con `remove_submenu_page()` para conservar el slug y redirigir. Añade registros invisibles, código de compatibilidad y dos fuentes de verdad para el mismo formulario. El usuario descartó explícitamente la compatibilidad.

### Decisión 6: La pestaña «Tema» declara `_wp_http_referer` y usa `settings_errors()`

Al ser el único módulo que guarda por `options.php` (que redirige a `_wp_http_referer` + `settings-updated=true`), su formulario debe declarar un `_wp_http_referer` explícito con la URL de su pestaña **después** de `settings_fields()` (`settings_fields()` ya emite el `_wp_http_referer` por defecto, que apunta a la página actual; se refuerza/sobrescribe con el de la pestaña para garantizar la vuelta al hub). La pestaña debe llamar a `settings_errors()` para que el aviso de guardado se muestre al volver.

**Consecuencias:** hoy el guardado de Theme Options rebota a la página anterior; con el hub eso dejaría al usuario fuera del hub. Declarar el referer y pintar `settings_errors()` es un **arreglo**, no una regresión. Los otros tres módulos no usan `options.php` y conservan su vuelta por POST a sí mismos.

**Alternativa descartada:** dejar el `_wp_http_referer` por defecto de `settings_fields()`. Apuntaría a la URL actual del request, que puede no incluir `tab=tema` ni el slug del hub, y el usuario volvería a una pantalla sin pestañas o con la pestaña equivocada.

### Decisión 7: Phase 0 obligatoria para `matrix-notifications` y `theme-options`

`MatrixConfig` y `ThemeOptions` no tienen spec y se les va a tocar el código (se les quita el registro y el envoltorio). Este mismo change incluye su **caracterización as-is** en dos capabilities nuevas: `matrix-notifications` (opciones, guardado por POST, contrato de `sendMatrixMessage()`, mensaje de prueba y notificación de comentarios) y `theme-options` (grupo `atareao_options_group`, saneado, `show_in_rest`, defaults y formulario).

**Consecuencias:** la línea base queda documentada antes del refactor, tal como exige el protocolo SDD+TDD del repositorio. En ambas, la UI se describe remitiendo al hub (pestañas `matrix` y `tema`) en lugar de afirmar una ubicación que este change elimina.

**Alternativa descartada:** asumir que el refactor de ubicación no cambia comportamiento y no caracterizarlas. Sería precisamente el caso que Phase 0 cubre: se modifica código sin spec previa.

### Decisión 8: Sin cambios en el sitio público

No se renombra ni se borra ninguna opción, no cambia el HTML emitido por la analítica, ni el flujo de login/logout, ni el envío de notificaciones Matrix, ni el microsite `/tools/`. Todo el cambio es de wp-admin.

**Consecuencias:** máximo aislamiento del riesgo; la única superficie pública que podría verse afectada (analítica, contacto, comentarios, login) no se toca porque los módulos conservan su lógica intacta. Los hooks de front (`wp_footer`, `comment_post`, `login_init`, etc.) no se modifican.

**Alternativa descartada:** agrupar en este change una limpieza o unificación de la lógica de los módulos. Aumentaría el blast radius sin relación con el objetivo (consolidar la navegación de wp-admin).

### Decisión 9: Reversa de la decisión del change `umami-analytics` que descartaba el hub

El change archivado `umami-analytics` (`design.md`, Decisión 3, líneas ~81-85) descartó meter la analítica en «Atareao Theme Options» porque no existía un hub y se compartiría el grupo de opciones de otro módulo. **Ahora sí existe un hub y cada pestaña conserva su propio formulario/grupo/POST**, de modo que aquella objeción queda resuelta: la analítica no comparte `settings_fields` con Tema, sigue guardando por POST en `admin_init` con su nonce y solo cambia su punto de entrada visual.

**Consecuencias:** se deja constancia explícita de la reversa. El diseño actual satisface el motivo original de la objeción (independencia de opciones y de flujo de guardado) sin renunciar a la consolidación de la navegación.

**Alternativa descartada:** mantener la analítica fuera del hub por coherencia con la decisión archivada. La decisión archivada era condicional a la ausencia de hub; revertirla cuando la condición cambia es lo correcto y queda documentado.

## Alternatives discarded (resumen)

- **Hub por encima con `remove_submenu_page()`**: registrar las cuatro páginas antiguas y quitarlas del menú para reutilizar el slug. Descartada: mantiene registros invisibles y código de compatibilidad, y deja dos caminos para el mismo formulario.
- **Pestañas con JavaScript**: descartada por depender de JS, romper el enlace directo y exigir enqueue para lo que el core resuelve.
- **Formulario único unificado**: un solo `<form>`/`settings_fields`/nonce para las cuatro pestañas. Descartada porque rompe nonces, grupos y destinos distintos (Matrix/PocketID a sí mismas, Umami en `admin_init`, Tema a `options.php`) y mezclaría responsabilidades.
- **Redirecciones de compatibilidad**: descartada explícitamente por el usuario; añade deuda y dos fuentes de verdad.
- **Dejar Theme Options en Apariencia**: descartada; mantiene la dispersión que motiva el change y deja la configuración de presentación separada del resto de la funcionalidad.

## Risks / Trade-offs

- **[Anidamiento de envoltorios si un módulo no elimina su `.wrap`]** → El hub pinta `.wrap` + `<h1>` y los módulos dejan de hacerlo. Mitigado con la verificación explícita de que cada render no imprime `.wrap` ni `<h1>` (tarea 3.x y E2E 7.x).
- **[Pestaña «Tema» que no vuelve al hub]** → `options.php` redirige al `_wp_http_referer`. Mitigado declarando el referer de la pestaña y llamando a `settings_errors()` (Decisión 6), con una comprobación dedicada en el arnés y en el E2E.
- **[Ruptura de bookmarks a las URLs antiguas]** → Decisión explícita (5). Solo afecta a administradores; la ruta nueva es enlazable.
- **[Doble registro si algún módulo conserva su `admin_menu`]** → El refactor elimina el `add_action` y el `add_options_page`/`add_theme_page`. Verificado por arnés (que no se registren las páginas antiguas) y por E2E (menú con una sola entrada, sin «Theme Options»).
- **[`check_admin_referer` y capability checks eliminados por accidente en el refactor]** → Se conservan palabra por palabra en los cuatro módulos; el arnés verifica que cada vía de guardado sigue exigiendo `manage_options` y su nonce.
- **[Verificación sin framework de tests]** → No hay tests automatizados en el repo. La verificación es estática (`just php-lint`, `just phpcs`), con arnés externo de stubs y E2E manual en wp-admin. Riesgo de regresión asumido y acotado por la naturaleza del cambio (navegación de wp-admin).

## Migration Plan

1. Añadir `includes/class-settings.php` (hub) y registrarlo en `atareao-functionality.php`.
2. Refactorizar los cuatro módulos: quitar su registro en `admin_menu` y su `.wrap`/`<h1>`.
3. Ajustar la pestaña «Tema» (`_wp_http_referer` + `settings_errors()`).
4. Verificar estáticamente (`just php-lint`, `just phpcs` sin empeorar) y con el arnés externo.
5. E2E manual en wp-admin: las cuatro pestañas, sus guardados y el menú.
6. No-regresión pública: tag de Umami, login/logout, notificaciones Matrix y microsite `/tools/`.

**Rollback:** revertir el commit del hub restaura los cuatro registros de página y las URLs antiguas. No hay estado ni datos implicados: ninguna opción se renombra ni se borra.

## Verification

> El repositorio **no tiene framework de tests**. La verificación combina análisis estático, un **arnés externo de stubs** que vive fuera del repo (`/tmp/opencode/settings-hub-harness/`, no versionado) y E2E manual en wp-admin. La implementación arranca **solo tras la aprobación del usuario**.

- **Lint**: `just php-lint` sin errores.
- **phpcs**: `just phpcs` sin empeorar el baseline. Baseline medido (2026-10-03) en theme+plugin: **752 errores / 425 warnings**. Objetivo de delta **+0 errores**. Se espera +1 warning por clase nueva (`PSR1.Files.SideEffects`, inherente a la guarda `ABSPATH` y presente en cada clase, p. ej. `class-matrix-config.php`); si `class-settings.php` añade exactamente ese warning, el objetivo +0 errores se cumple.
- **Arnés externo de stubs** (`/tmp/opencode/settings-hub-harness/`): define de forma controlable las funciones de WordPress usadas (`add_options_page`, `add_action`, `current_user_can`, `esc_*`, etc.), ejercita `Settings::init()` y los renders, y comprueba:
  1. **Registro de la página**: se llama `add_options_page` una sola vez con etiqueta/título «Atareao»/«Ajustes de Atareao», slug `atareao-settings` y capability `manage_options`.
  2. **Resolución de `tab`/whitelist**: `tab` válido selecciona el render del módulo correcto; `tab` ausente o desconocido cae en `matrix`; el enlace activo lleva `nav-tab-active` y `aria-current="page"`.
  3. **Delegación**: cada pestaña invoca el render público del módulo correspondiente y los cuatro renders no imprimen `.wrap` ni `<h1>`.
  4. **Payload de `sendMatrixMessage()`**: endpoint `{url}/_matrix/client/v3/rooms/{room}/send/m.room.message/{txn}`, método `PUT`, cabeceras `Authorization: Bearer <token>` y `Content-Type: application/json`, cuerpo `{"msgtype":"m.text","body":…}`, retorno `true` en 2xx y cadena en error/credenciales incompletas.
  5. **Registro de las opciones del grupo**: `register_setting('atareao_options_group', …)` para los nueve enlaces sociales, `atareao_podcast_feed`, `atareao_opengist_server` y `atareao_opengist_username`, con sus `sanitize_callback`, `show_in_rest` y `default`.
- **E2E manual en wp-admin**: abrir `options-general.php?page=atareao-settings`, recorrer las cuatro pestañas, comprobar `?tab=` válidos/desconocidos, guardar cada pestaña por su vía (Matrix, PocketID con «Probar conexión», Umami con guardado e importación, Tema con vuelta a la pestaña y aviso), verificar el menú (una sola entrada en Ajustes, sin «Theme Options» en Apariencia) y que las URLs antiguas ya no existen.
- **No-regresión pública** (front): tag de Umami en `wp_footer`, login/logout con y sin enforce, notificación de un comentario aprobado y una página del microsite `/tools/`.
- `openspec validate settings-hub --strict` sin hallazgos.

## Open Questions

Ninguna. El orden y las etiquetas de las pestañas, el punto de entrada, la resolución de `tab`, el refactor de los módulos, la ausencia de redirecciones, el arreglo de la pestaña «Tema» y la caracterización Phase 0 quedan resueltos arriba.
