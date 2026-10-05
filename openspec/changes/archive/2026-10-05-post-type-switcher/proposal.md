# Proposal: Internalización de Post Type Switcher

## Why

El sitio atareao.es usa hoy el plugin externo **Post Type Switcher 4.0.1** (John James Jacoby / Triple J Software) para cambiar el tipo de un post desde el editor (bloques y clásico), la lista (edición rápida y masiva) y una columna «Type». Es una funcionalidad pequeña, estable y directamente relacionada con los CPTs que ya registra `atareao-functionality` (`post`, `page`, `tutorial`, `capitulo`, `aplicacion`, `podcast`, `software`, en `includes/class-post-types.php`). Mantener un plugin de terceros para esta única responsabilidad añade una dependencia externa que hay que actualizar y auditar por separado, y desaprovecha la separación ya establecida del repo (toda la funcionalidad en el plugin; el tema solo presentación).

El objetivo es **internalizar el plugin en `atareao-functionality` con paridad funcional** para poder desinstalar el externo. No hay tests ni build tools en el repo: el JS del editor de bloques se escribe en JS nativo con `wp.element.createElement` (sin JSX/webpack). La internalización debe conservar hooks, meta, nonces, parámetros y acción AJAX **palabra por palabra**, para no romper personalizaciones ni perder el historial de meta (`pts_original_type` / `pts_previous_type`).

## What Changes

- Nueva clase `\Atareao\PostTypeSwitcher` en `wp-content/plugins/atareao-functionality/includes/class-post-type-switcher.php`, con el patrón del repo (`namespace Atareao;`, guarda `ABSPATH`, `init()` idempotente) y registro con `require_once` + `PostTypeSwitcher::init()` en `atareao-functionality.php`.
- Tipos conmutables: `get_post_types(['public' => true, 'show_ui' => true])` menos `attachment`, filtrados por la capacidad `publish_posts` del usuario actual; el tipo actual del post se fuerza en la lista aunque no cumpla los criterios.
- Editor de bloques: plugin registrado con `wp.plugins.registerPlugin` que renderiza `wp.editor.PluginPostStatusInfo` con una fila «Post Type» y un `Dropdown` de `wp.components` con un fieldset de radios; al cambiar de tipo, `window.confirm` y navegación a la URL de cambio (admin-ajax `action=post_type_switcher` + nonce + `post_id`). El asset no se encola si el usuario no puede publicar el tipo actual o no hay tipos conmutables.
- Editor clásico: bloque en `post_submitbox_misc_actions` («Post Type:», tipo actual, enlace «Edit», `select` `pts_post_type`, botones «OK»/«Cancel» y `wp_nonce_field('post-type-selector', 'pts-nonce-select')`), con JS/CSS inline para mostrar/ocultar.
- Columna «Type»: `manage_{$name}_posts_columns` con etiqueta «Type», `manage_{$name}_posts_custom_column` (nombre singular dentro de `<span data-post-type="…">`) y oculta por defecto con `default_hidden_columns`.
- Quick Edit y Bulk Edit: `quick_edit_custom_box` / `bulk_edit_custom_box` con el `select` y el nonce; el de bulk con la opción «— No Change —» (`value="-1"`); script jQuery `assets/js/post-type-switcher-quickedit.js` en `edit.php` que mueve las cajas y parchea `inlineEditPost.edit` para preseleccionar el tipo actual leído de `data-post-type`.
- AJAX: acción `wp_ajax_post_type_switcher` que usa `$_GET` (`post_id`, `pts_post_type`, `pts-nonce-select`), verifica nonce `post-type-selector`, `current_user_can('edit_post', $post_id)` y la capacidad de publicar el tipo destino; si falta dato → `wp_die('Missing data.')`; si falla la verificación → `wp_die`; si va bien → cambia el tipo, dispara `do_action('post_type_after_switch', …)` y `wp_safe_redirect(get_edit_post_link($post_id, 'raw'))`.
- Override al guardar: filtros `wp_insert_post_data` y `wp_insert_attachment_data` con las salvaguardas del original (nonce, permisos, tipo destino existe y difiere, `post_ID` coincide, no autosave, no revisión).
- Memoria del tipo: `set_post_type($id, $type)` guarda `pts_original_type` (la primera vez) y `pts_previous_type` (el inmediatamente anterior), y los borra al revertir.
- Páginas permitidas: `is_allowed_page()` = `is_blog_admin()` o (AJAX con action `inline-save`|`post_type_switcher`), y `$pagenow` en `['post.php', 'edit.php', 'admin-ajax.php']` (filtro `pts_allowed_pages`).
- Paridad de hooks/acciones: `pts_post_type_filter`, `pts_get_post_types_filter`, `pts_allowed_pages` y la acción `post_type_switcher` disparada al terminar la inicialización de admin.
- Bump **menor** de `ATAREAO_PLUGIN_VERSION` en `atareao-functionality.php`. Si este change y `search-replace-block-editor` se fusionan juntos, la versión final la fija el primero en mergear.
- Fuera de alcance: soporte de `attachment`, WPML (`wpml_sync_type`), página de opciones, build de JS y rediseño del editor.

## Capabilities

### New Capabilities

- `post-type-switcher`: conmutación del tipo de un post desde el editor de bloques, el editor clásico, la edición rápida y la edición masiva, con columna «Type», endpoint AJAX propio, cambio de tipo al guardar, memoria del tipo original/anterior y conservación de los hooks, meta, nonces y parámetros del plugin original.

### Modified Capabilities

Ninguna. No se modifica el comportamiento observable de ninguna capability existente.

## Impact

- **Archivos**:
  - Nuevo: `wp-content/plugins/atareao-functionality/includes/class-post-type-switcher.php` (clase `\Atareao\PostTypeSwitcher`).
  - Nuevo: `wp-content/plugins/atareao-functionality/assets/js/post-type-switcher-block.js` (editor de bloques, JS nativo).
  - Nuevo: `wp-content/plugins/atareao-functionality/assets/js/post-type-switcher-quickedit.js` (jQuery, edición rápida/masiva).
  - Nuevo: `wp-content/plugins/atareao-functionality/assets/css/post-type-switcher.css` (o estilos inline como el original).
  - Modificado: `wp-content/plugins/atareao-functionality/atareao-functionality.php` (`require_once` + `PostTypeSwitcher::init()` + bump de `ATAREAO_PLUGIN_VERSION`).
  - Nuevo (spec): `openspec/specs/post-type-switcher/spec.md` vía el delta `specs/post-type-switcher/spec.md`.
- **Paridad drop-in**: se conservan los nombres de hooks (`pts_post_type_filter`, `pts_get_post_types_filter`, `pts_allowed_pages`, `post_type_switcher`, `post_type_after_switch`), las meta `pts_original_type`/`pts_previous_type`, el nonce `post-type-selector`/`pts-nonce-select`, los parámetros GET `pts_post_type`/`post_id` y la acción AJAX `post_type_switcher`.
- **Desinstalación**: el plugin externo deja de ser necesario y puede desinstalarse tras el despliegue.
- **Sin renombrar ni borrar**: no se renombra ni se borra ninguna meta ni hook; el historial de tipo existente se conserva.
- **Sin dependencias nuevas ni build tools**: JS/CSS nativos, editados a mano; sin JSX ni webpack.
- **Compatibilidad**: PHP 8.3, PSR12, WordPress 6.0+.
- **Verificación**: `just php-lint`, `just phpcs` (sin empeorar baseline), `node --check` de los JS, E2E manual en wp-admin/editor de bloques y `openspec validate post-type-switcher --strict`.
