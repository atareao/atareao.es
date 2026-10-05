# Design: Internalización de Post Type Switcher

## Context

El sitio atareao.es usa hoy el plugin externo **Post Type Switcher 4.0.1** (John James Jacoby / Triple J Software) para cambiar el tipo de un post. El plugin cubre tres superficies: el editor de bloques, el editor clásico y la lista de posts (columna «Type», edición rápida y edición masiva), más un endpoint AJAX y un override al guardar. El código caracterizado vive fuera del repo, en el paquete descargado del plugin.

**Contrato funcional del original a paridad:**

1. **Tipos conmutables**: `get_post_types(['public' => true, 'show_ui' => true])`, se excluye `attachment`; solo se ofrecen los tipos para los que el usuario actual tiene `cap->publish_posts`. El tipo actual del post se fuerza en la lista aunque no cumpla los criterios.
2. **Editor de bloques**: `wp.plugins.registerPlugin` con `PluginPostStatusInfo` que pinta una fila «Post Type» y un `Dropdown` de `wp.components` con un fieldset de radios; el actual marcado. Al elegir otro tipo: `window.confirm("Are you sure you want to change this from a '%s' to a '%s'?")`; si acepta, navega a la `changeUrl` (admin-ajax `action=post_type_switcher` + nonce + `post_id`). El asset no se encola si el usuario no puede publicar el tipo actual o no hay tipos conmutables.
3. **Editor clásico**: bloque en `post_submitbox_misc_actions` con «Post Type:», el tipo actual, enlace «Edit», `select` `pts_post_type`, botones «OK»/«Cancel» y `wp_nonce_field('post-type-selector', 'pts-nonce-select')`; JS/CSS inline para mostrar/ocultar.
4. **Columna «Type»**: `manage_{$name}_posts_columns` añade `post_type` («Type»); `manage_{$name}_posts_custom_column` pinta el nombre singular dentro de `<span data-post-type="…">`; oculta por defecto con `default_hidden_columns`.
5. **Quick Edit / Bulk Edit**: `quick_edit_custom_box` y `bulk_edit_custom_box` (columna `post_type`) añaden el `select` y el nonce. El de bulk incluye «— No Change —» (`value="-1"`). Script jQuery `quickedit.js` en `edit.php` que mueve las cajas y parchea `inlineEditPost.edit` para preseleccionar el tipo actual leído de `data-post-type`.
6. **AJAX**: `wp_ajax_post_type_switcher`, usa `$_GET` (`post_id`, `pts_post_type`, `pts-nonce-select`). Verifica nonce `post-type-selector`, `current_user_can('edit_post', $post_id)` y `cap->publish_posts` del destino; si falta dato → `wp_die('Missing data.')`; si falla la verificación → `wp_die`; si ok → cambia el tipo, dispara `do_action('post_type_after_switch', $nuevo, $anterior, $post_id)` y `wp_safe_redirect(get_edit_post_link($post_id, 'raw'))`.
7. **Override al guardar**: filtros `wp_insert_post_data` y `wp_insert_attachment_data`. Requiere `$_REQUEST['pts_post_type']` y `$_REQUEST['pts-nonce-select']`; `post_ID` no vacío; tipo destino existe; distinto del actual; `$post_id === $postarr['ID']`; `current_user_can('edit_post', $postarr['ID'])`; `current_user_can(cap->publish_posts)`; nonce válido; no autosave; no revisión. Si todo pasa, fija `$data['post_type']` y dispara `post_type_after_switch`.
8. **Memoria del tipo**: `set_post_type($id, $type)` guarda `pts_original_type` (solo la primera vez) y `pts_previous_type` (el inmediatamente anterior); borra `pts_original_type` cuando el post vuelve al tipo original y `pts_previous_type` cuando vuelve al anterior.
9. **Páginas permitidas**: `is_allowed_page()` = `is_blog_admin()` o (AJAX con action `inline-save`|`post_type_switcher`), y `$pagenow` en `['post.php', 'edit.php', 'admin-ajax.php']` (filtro `pts_allowed_pages`).
10. **Filtros/acciones a conservar**: `pts_post_type_filter` (args de `get_post_types`), `pts_get_post_types_filter` (lista resultante), `pts_allowed_pages` y la acción `post_type_switcher` (disparada al terminar la inicialización de admin). Se conservan también las meta `pts_original_type`/`pts_previous_type`, el nonce `post-type-selector`/`pts-nonce-select`, los parámetros GET `pts_post_type`/`post_id` y la acción AJAX `post_type_switcher`.

**Estado y restricciones del repo:**

- El plugin `atareao-functionality` registra 7 CPTs (`post`, `page`, `tutorial`, `capitulo`, `aplicacion`, `podcast`, `software`) en `includes/class-post-types.php`.
- Patrón de clases: `namespace Atareao;`, guarda `if (!defined('ABSPATH')) { exit; }`, `public static function init()` idempotente y registro con `require_once` en `atareao-functionality.php`.
- **No hay build tools ni tests.** El JS del editor de bloques se escribe en JS nativo con `wp.element.createElement` (sin JSX/webpack). La verificación es `just php-lint`, `just phpcs`, `node --check` y E2E manual.
- PSR12, PHP 8.3, WordPress 6.0+. La funcionalidad va en el **plugin**; el tema es solo presentación.

## Goals / Non-Goals

**Goals:**

- Internalizar Post Type Switcher en `atareao-functionality` con **paridad funcional** para poder desinstalar el plugin externo.
- Conservar **palabra por palabra** hooks, meta, nonces, parámetros GET y la acción AJAX, de modo que la integración sea drop-in y no se pierda el historial de meta.
- Cubrir las tres superficies del original: editor de bloques, editor clásico y lista (columna, edición rápida y masiva).
- Escribir el JS sin build tools, con el estilo del repo (`wp.element.createElement`).
- Verificar con análisis estático y E2E manual, sin introducir un framework de tests.

**Non-Goals:**

- **WPML fuera de alcance**: el sitio no es multilingüe; no se porta `wpml_sync_type` ni la integración con WPML (se documenta la decisión).
- No se da soporte al tipo `attachment` (excluido del listado de tipos conmutables, igual que el original).
- No se introduce build tools (webpack/JSX) ni se vendoriza el bundle compilado del plugin.
- No se crea página de opciones (el plugin original no tiene).
- No se rediseña el editor ni se añaden capacidades nuevas más allá de la paridad.
- No se renombra ninguna meta ni hook a prefijo `atareao_`.
- No se modifica el sitio público.

## Decisions

### Decisión 1: Capability nueva `post-type-switcher` + clase `\Atareao\PostTypeSwitcher`

La funcionalidad se modela como capability nueva `post-type-switcher` y se implementa en `wp-content/plugins/atareao-functionality/includes/class-post-type-switcher.php` con el patrón del repo (`namespace Atareao;`, guarda `ABSPATH`, `init()` idempotente) y registro con `require_once` + `PostTypeSwitcher::init()` en `atareao-functionality.php`. La clase agrupa todos los hooks de admin.

**Consecuencias:** la funcionalidad queda versionada con el plugin; el tema permanece como presentación. Al archivar se crea `openspec/specs/post-type-switcher/spec.md`.

**Alternativa descartada:** mantener el plugin externo. Es una dependencia de terceros para una responsabilidad que ya pertenece al plugin del sitio.

### Decisión 2: JS nativo sin build (editor de bloques y edición rápida)

- `assets/js/post-type-switcher-block.js`: editor de bloques con `wp.element.createElement`, `wp.components`, `wp.plugins` y `wp.editor.PluginPostStatusInfo`.
- `assets/js/post-type-switcher-quickedit.js`: jQuery, con el patrón del original (mover cajas y parchear `inlineEditPost.edit`).
- CSS en `assets/css/post-type-switcher.css` o inline como el original.

**Consecuencias:** se respeta la restricción «no build tools» del repo; el JS es editable a mano y verificable con `node --check`.

**Alternativa descartada:** vendorizar el bundle compilado del plugin. Añade un artefacto opaco y difícil de mantener.

### Decisión 3: Paridad drop-in conservando hooks, meta, nonces y parámetros

Se conservan los nombres exactos: filtros `pts_post_type_filter`, `pts_get_post_types_filter`, `pts_allowed_pages`; acciones `post_type_switcher` y `post_type_after_switch`; meta `pts_original_type`/`pts_previous_type`; nonce `post-type-selector`/`pts-nonce-select`; parámetros GET `pts_post_type`/`post_id`; acción AJAX `post_type_switcher`.

**Consecuencias:** las personalizaciones existentes y el historial de meta siguen funcionando; el cambio es indistinguible para el resto del sitio.

**Alternativa descartada:** renombrar todo a prefijo `atareao_`. Rompería personalizaciones y perdería el historial de meta (`pts_original_type`/`pts_previous_type` ya almacenado).

### Decisión 4: Sin página de opciones

La funcionalidad no necesita configuración: los tipos se derivan de los CPTs registrados y de las capacidades del usuario. No se crea página de ajustes.

**Consecuencias:** cero superficie de configuración; el hub de ajustes no se toca.

**Alternativa descartada:** añadir una pestaña de ajustes. El plugin original no la tiene; añadirla sería ruido sin requisitos.

### Decisión 5: WPML fuera de alcance

No se porta `wpml_sync_type` ni la integración con WPML. El sitio no es multilingüe y no hay usuarios con `caps` de traducción.

**Consecuencias:** se documenta la decisión en este design y se deja constancia de que, si algún día se necesita, habría que portar ese hook aparte.

**Alternativa descartada:** portar el hook «por si acaso». Código muerto sin caso de uso.

### Decisión 6: Conmutación en el editor de bloques con `PluginPostStatusInfo` + `Dropdown` + radios

La fila «Post Type» se renderiza dentro de `PluginPostStatusInfo` con un `Dropdown` de `wp.components` cuyo contenido es un fieldset de radios, uno por tipo disponible, con el actual marcado. Al cambiar: `window.confirm` con el mensaje exacto del original y, si acepta, navegación a la URL de cambio. El asset no se encola si no hay tipos conmutables o el usuario no puede publicar el tipo actual.

**Consecuencias:** paridad visual y de comportamiento; sin dependencias externas de React (se usa `wp.element`).

**Alternativa descartada:** un panel propio con `PluginSidebar`. Cambia la ubicación respecto al original y no aporta valor.

### Decisión 7: Endpoint AJAX con `$_GET` y `wp_safe_redirect`

`wp_ajax_post_type_switcher` lee `post_id`, `pts_post_type` y `pts-nonce-select` de `$_GET` (como el original), verifica nonce y capacidades, cambia el tipo, dispara `post_type_after_switch` y redirige con `wp_safe_redirect(get_edit_post_link($post_id, 'raw'))`. Si falta un dato → `wp_die('Missing data.')`; si falla una verificación → `wp_die`.

**Consecuencias:** paridad con el flujo del original; el navegador recarga el editor del post ya convertido.

**Alternativa descartada:** devolver JSON y resolver en JS. Rompería la paridad de la URL de cambio y complicaría el JS sin build.

### Decisión 8: Override al guardar + `set_post_type`/meta

Se enganchan `wp_insert_post_data` y `wp_insert_attachment_data` con las salvaguardas del original (nonce, permisos, tipo destino existe y difiere, `post_ID` coincide, no autosave, no revisión). La memoria del tipo se implementa en `set_post_type($id, $type)`: `pts_original_type` solo la primera vez, `pts_previous_type` con el tipo inmediatamente anterior, y borrado de cada una al revertir al tipo correspondiente.

**Consecuencias:** el cambio funciona tanto desde el editor clásico como desde el de bloques; el historial permite volver al tipo original.

**Alternativa descartada:** hacer el cambio solo desde el endpoint AJAX. El guardado del editor clásico y de bloques pasaría por alto el `select` si no se intercepta `wp_insert_post_data`.

### Decisión 9: Bump menor de `ATAREAO_PLUGIN_VERSION`

La versión del plugin sube un nivel **menor** (una nueva funcionalidad, sin ruptura). Si este change y `search-replace-block-editor` se fusionan juntos, la versión final la fija el primero en mergear.

**Consecuencias:** una sola subida de versión para ambos changes; el segundo hereda la versión ya fijada.

**Alternativa descartada:** bump en ambos changes. Generaría conflictos y una versión ficticia intermedia.

### Decisión 10: Verificación manual (sin framework de tests)

Al no haber tests en el repo, la verificación combina `just php-lint`, `just phpcs` (sin empeorar el baseline), `node --check` de los JS y E2E manual en wp-admin (editor clásico y de bloques, lista, quick/bulk edit), más `openspec validate post-type-switcher --strict`.

**Consecuencias:** la verificación es reproducible pero manual; se documenta el checklist en `tasks.md`.

**Alternativa descartada:** introducir PHPUnit/Jest. Fuera del alcance del repo y desproporcionado para el change.

## Risks / Trade-offs

- **[APIs de editor que pueden cambiar]** → `PluginPostStatusInfo`, `wp.plugins` y `wp.components` pueden cambiar entre versiones de WordPress. Mitigado comprobando en el E2E manual la versión objetivo (6.0+) y documentando la dependencia.
- **[`PluginPostStatusInfo` según versión de WP]** → El componente vive en `wp.editor` en versiones antiguas y también se expone como `wp.editPost`; hay que resolver el que exista en la versión instalada. Mitigado con una comprobación defensiva y con el E2E.
- **[Nonce repetido en cada caja de inline-edit]** → Quick Edit y Bulk Edit pintan el mismo `wp_nonce_field` por fila, con `pts-nonce-select` repetido. Mitigado porque el nonce es el mismo valor por sesión y la verificación se hace una sola vez al guardar.
- **[Sin tests → verificación manual]** → No hay framework de tests. Mitigado con `just php-lint`, `just phpcs`, `node --check` y un checklist E2E explícito.
- **[Pérdida del historial de meta si se renombraran las claves]** → Renombrar `pts_original_type`/`pts_previous_type` perdería el historial. Mitigado con la Decisión 3 (conservar los nombres).
- **[Bump de versión compartido con `search-replace-block-editor`]** → Dos changes que suben la misma constante al fusionarse. Mitigado con la Decisión 9 (la fija el primero en mergear).
- **[JS sin build puede divergir del bundle original]** → El port a `wp.element.createElement` es manual. Mitigado replicando las llamadas del original y verificando con `node --check` y E2E.
- **[WPML no soportado]** → Si el sitio se volviera multilingüe, el hook `wpml_sync_type` no estaría. Riesgo aceptado y documentado (Non-goal).
