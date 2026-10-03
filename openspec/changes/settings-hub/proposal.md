# Proposal: Hub de Ajustes «Atareao»

## Why

Hoy la configuración del plugin `atareao-functionality` está repartida en cuatro pantallas: **Matrix API**, **PocketID Login** y **Analítica** bajo el menú Ajustes, y **Theme Options** bajo el menú Apariencia. Esa dispersión obliga al administrador a recordar dónde vive cada ajuste, deja la configuración de presentación (Tema) lejos del resto de la funcionalidad y mantiene cuatro registros de menú independientes dentro del plugin. Consolidar todo en un único punto de entrada «Atareao» con cuatro pestañas reduce la fricción operativa, elimina la duplicación del envoltorio de página y ofrece un lugar único y predecible para la configuración del sitio, sin tocar el sitio público.

## What Changes

- **Nueva capability `admin-settings`**: el hub de ajustes «Atareao», único punto de entrada en Ajustes con cuatro pestañas (`matrix`, `pocketid`, `umami`, `tema`), whitelist de `tab`, barra `nav-tab-wrapper` de core, `aria-current="page"` y sin JavaScript.
- **Nuevo módulo `\Atareao\Settings`** en `includes/class-settings.php`, registrado con `require_once` + `Settings::init()` como el resto de módulos. El hub registra la única página (`add_options_page`, «Atareao» / «Ajustes de Atareao», slug `atareao-settings`, `manage_options`), pinta el `.wrap`/`<h1>` y delega el contenido en los cuatro módulos.
- **Refactor de los cuatro módulos**: `MatrixConfig`, `PocketIDLogin`, `Analytics` y `ThemeOptions` pierden su `add_options_page`/`add_theme_page`, su `add_action('admin_menu', …)` y su `.wrap` + `<h1>`. Todo lo demás se conserva: nonces, comprobaciones de `manage_options`, saneado, avisos y destino de cada formulario.
- **Pestaña «Tema»**: al ser el único módulo que guarda por `options.php`, declara un `_wp_http_referer` explícito con la URL de su pestaña después de `settings_fields()` y la pestaña llama a `settings_errors()`, de modo que el guardado vuelve al hub y muestra el aviso (hoy rebota a la página anterior: es un arreglo, no una regresión).
- **Sin redirecciones de compatibilidad**: las cuatro URLs antiguas dejan de existir; no se registran slugs ocultos ni aliases. La entrada «Theme Options» desaparece de Apariencia y el menú Ajustes queda con una sola entrada del plugin.
- **Phase 0 (caracterización as-is)**: nuevas capabilities `matrix-notifications` y `theme-options` documentan el comportamiento real de `MatrixConfig` y `ThemeOptions` antes de tocar su ubicación.
- **Sin cambios en el sitio público**: no se renombra ni se borra ninguna opción, no cambia el HTML emitido por la analítica, ni el flujo de login/logout, ni el envío de notificaciones Matrix, ni el microsite `/tools/`.

## Capabilities

### New Capabilities

- `admin-settings`: punto de entrada único en Ajustes, cuatro pestañas por URL, envoltorio único y delegación del contenido en los módulos.
- `matrix-notifications`: caracterización (Phase 0) de los ajustes de Matrix y del contrato `sendMatrixMessage()`, el mensaje de prueba y la notificación de comentarios.
- `theme-options`: caracterización (Phase 0) del grupo de opciones `atareao_options_group` y de su formulario por la Settings API.

### Modified Capabilities

- `analytics`: el requirement `Panel de configuración y migración` pasa de una página propia en Ajustes a la pestaña «Umami» del hub, conservando el resto del comportamiento palabra por palabra.
- `pocketid-login`: el requirement `Settings page with connectivity test` pasa de una página propia en Ajustes a la pestaña «PocketID» del hub, conservando campos, toggle, patrón del secret, validación HTTPS y botón «Probar conexión».

## Impact

- **Capabilities afectadas (5)**: nueva `admin-settings`; nuevas de caracterización `matrix-notifications` y `theme-options`; modificadas `analytics` y `pocketid-login`.
- **Archivos**:
  - Nuevo: `wp-content/plugins/atareao-functionality/includes/class-settings.php` (hub: registro, whitelist de pestañas, envoltorio, `aria-current` y delegación).
  - Modificado: `wp-content/plugins/atareao-functionality/atareao-functionality.php` (`require_once` de `class-settings.php` + `\Atareao\Settings::init()`).
  - Modificados: los cuatro módulos (`includes/class-matrix-config.php`, `includes/class-pocketid-login.php`, `includes/class-analytics.php`, `includes/class-theme-options.php`): pierden su registro en `admin_menu` y su `.wrap`/`<h1>`.
  - Modificado: `wp-content/plugins/atareao-functionality/README.md` (actualizar las dos URLs documentadas y documentar el hub).
- **No cambia**: los nombres ni los valores de ninguna opción; el HTML público de la analítica; el flujo de login/logout; el envío de notificaciones Matrix; el microsite `/tools/`; el pipeline de release. Todo el cambio es de **wp-admin**.
- **Compatibilidad**: PHP 8.3, PSR12, WordPress 6.0+. Sin dependencias nuevas.
