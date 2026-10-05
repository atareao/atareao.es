# Tasks

> **Nota inicial:** el repositorio **no tiene framework de tests ni build tools**. El JS del editor de bloques se escribe en JS nativo con `wp.element.createElement` (sin JSX/webpack). La verificación combina análisis estático (`just php-lint`, `just phpcs`, `node --check`) y E2E manual en wp-admin (editor clásico, editor de bloques, lista, quick edit y bulk edit).
>
> **Estado (rama `feature/post-type-switcher`):** implementación y verificación estática completadas; revisión de seguridad (0 hallazgos explotables) y code review del JS (sin bloqueantes) superadas. La **E2E manual en producción** (wp-admin y editor de bloques) queda pendiente de ejecución por el usuario.

## 1. Phase 0 — Caracterización y baseline

- [x] 1.1 Documentar en `design.md` §Context el contrato funcional del plugin original (10 puntos: tipos conmutables, editor de bloques, editor clásico, columna, quick/bulk edit, AJAX, override al guardar, memoria del tipo, páginas permitidas y hooks). **Verificación:** el §Context describe los 10 puntos y sus nombres exactos de hook/meta/nonce; `openspec validate post-type-switcher --strict` válido. **Evidencia:** `design.md` §Context describe los 10 puntos del contrato; `openspec validate post-type-switcher --strict` válido.
- [ ] 1.2 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el par errores/warnings del baseline y se anota en esta tarea.

## 2. Clase `PostTypeSwitcher` y tipos conmutables

- [x] 2.1 Crear `wp-content/plugins/atareao-functionality/includes/class-post-type-switcher.php` con `namespace Atareao;`, guarda `ABSPATH` e `init()` idempotente que solo engancha hooks. **Verificación:** `just php-lint` sin errores; `just phpcs` del fichero sin errores nuevos. **Evidencia:** `just php-lint` 0 errores; `just phpcs` del fichero: 0 errores (1 warning esperado `PSR1.Files.SideEffects`).
- [x] 2.2 Registrar en `atareao-functionality.php` con `require_once` + `\Atareao\PostTypeSwitcher::init();`. **Verificación:** `rg -n "class-post-type-switcher|PostTypeSwitcher::init" atareao-functionality.php` → 2 coincidencias; `just php-lint` sin errores. **Evidencia:** registro presente (`require_once` + `PostTypeSwitcher::init()`); `just php-lint` 0 errores.
- [ ] 2.3 Implementar los tipos conmutables: `get_post_types(['public' => true, 'show_ui' => true])`, exclusión de `attachment`, filtrado por `publish_posts` del usuario y forzado del tipo actual. **Verificación:** E2E manual — el `select` muestra los CPTs del sitio (menos `attachment`) y siempre el tipo actual.
- [ ] 2.4 Exponer los filtros `pts_post_type_filter` (args) y `pts_get_post_types_filter` (lista resultante) e implementar `is_allowed_page()` con `is_blog_admin()` o AJAX `inline-save`/`post_type_switcher`, `$pagenow` en `['post.php','edit.php','admin-ajax.php']` y filtro `pts_allowed_pages`. **Verificación:** E2E manual — enganchar `pts_get_post_types_filter` altera la lista; fuera de las páginas permitidas no se engancha nada.

## 3. Columna «Type»

- [ ] 3.1 Añadir la columna `post_type` («Type») con `manage_{$name}_posts_columns` para los tipos conmutables. **Verificación:** E2E manual — la columna aparece en la lista de cada CPT.
- [ ] 3.2 Pintar el nombre singular dentro de `<span data-post-type="…">` con `manage_{$name}_posts_custom_column`. **Verificación:** E2E manual — inspeccionar el HTML y comprobar el atributo `data-post-type`.
- [ ] 3.3 Ocultarla por defecto con `default_hidden_columns` (filtro por `$name`). **Verificación:** E2E manual — la columna no se ve al abrir la lista por primera vez.

## 4. Metabox del editor clásico + JS/CSS inline

- [ ] 4.1 Enganchar `post_submitbox_misc_actions` y pintar «Post Type:», el tipo actual, el enlace «Edit», el `select` `pts_post_type`, los botones «OK»/«Cancel» y `wp_nonce_field('post-type-selector', 'pts-nonce-select')`. **Verificación:** E2E manual — el bloque aparece en el editor clásico y el nonce viaja en el formulario.
- [ ] 4.2 Añadir el JS/CSS inline para mostrar/ocultar la selección del tipo (equivalente al original). **Verificación:** E2E manual — el enlace «Edit» muestra el `select` y «Cancel» lo oculta.
- [ ] 4.3 Conservar el nombre exacto `pts_post_type` como `name` del `select`. **Verificación:** `grep -n "pts_post_type" class-post-type-switcher.php` presente.

## 5. Edición rápida y masiva + `quickedit.js`

- [ ] 5.1 Enganchar `quick_edit_custom_box` y `bulk_edit_custom_box` (columna `post_type`) con el `select` y el nonce; en bulk, la opción «— No Change —» (`value="-1"`). **Verificación:** E2E manual — quick edit muestra el `select` con el tipo actual; bulk edit muestra la opción «— No Change —».
- [ ] 5.2 Crear `assets/js/post-type-switcher-quickedit.js` (jQuery) que mueva las cajas a su fila y parchee `inlineEditPost.edit` para preseleccionar el tipo actual leído de `data-post-type`. **Verificación:** `node --check assets/js/post-type-switcher-quickedit.js` sin errores; E2E manual — al abrir quick edit el `select` viene con el tipo correcto.
- [ ] 5.3 Encolar el script jQuery solo en `edit.php`. **Verificación:** E2E manual — el script se carga en `edit.php` y no en otras pantallas.

## 6. Editor de bloques (enqueue + localize y JS)

- [ ] 6.1 Encargar el asset del editor de bloques solo si el usuario puede publicar el tipo actual y hay tipos conmutables; localizar la lista de tipos, el tipo actual, el nonce y la URL de cambio. **Verificación:** E2E manual — en un post sin tipos conmutables el asset no se encola.
- [x] 6.2 Crear `assets/js/post-type-switcher-block.js` en JS nativo: `wp.plugins.registerPlugin`, `wp.editor.PluginPostStatusInfo`, fila «Post Type», `Dropdown` de `wp.components` con fieldset de radios y tipo actual marcado. **Verificación:** `node --check assets/js/post-type-switcher-block.js` sin errores. **Evidencia:** `node --check assets/js/post-type-switcher-block.js` → OK (sin errores).
- [ ] 6.3 Implementar la confirmación (`window.confirm` con el mensaje exacto del original) y la navegación a la URL de cambio (admin-ajax `action=post_type_switcher` + nonce + `post_id`). **Verificación:** E2E manual — aceptar navega; cancelar no.
- [ ] 6.4 Añadir el CSS del selector en `assets/css/post-type-switcher.css` o inline como el original. **Verificación:** E2E manual — el desplegable se ve correctamente.

## 7. Endpoint AJAX

- [ ] 7.1 Registrar `wp_ajax_post_type_switcher` y leer `$_GET` (`post_id`, `pts_post_type`, `pts-nonce-select`) con `wp_die('Missing data.')` si falta dato. **Verificación:** E2E manual — una petición sin datos termina en «Missing data.».
- [ ] 7.2 Verificar nonce `post-type-selector`, `current_user_can('edit_post', $post_id)` y `publish_posts` del tipo destino; terminar con `wp_die` si falla. **Verificación:** E2E manual — nonce inválido y usuario sin permiso reciben `wp_die`.
- [ ] 7.3 En el camino válido: cambiar el tipo, disparar `do_action('post_type_after_switch', $nuevo, $anterior, $post_id)` y `wp_safe_redirect(get_edit_post_link($post_id, 'raw'))`. **Verificación:** E2E manual — el navegador acaba en la pantalla de edición del post con el tipo ya cambiado.

## 8. Override al guardar + `set_post_type`/meta

- [ ] 8.1 Enganchar `wp_insert_post_data` y `wp_insert_attachment_data` con todas las salvaguardas: `$_REQUEST['pts_post_type']` y `$_REQUEST['pts-nonce-select']`, `post_ID` no vacío, tipo destino existe y difiere, `$post_id === $postarr['ID']`, `current_user_can('edit_post', $postarr['ID'])`, `publish_posts` del destino, nonce válido, no autosave y no revisión. **Verificación:** E2E manual — el guardado clásico cambia el tipo; autosave, revisión, tipo inexistente y nonce inválido no lo cambian.
- [ ] 8.2 Fijar `$data['post_type']` y disparar `post_type_after_switch` solo cuando se cumplen todas las condiciones. **Verificación:** E2E manual — la acción `post_type_after_switch` recibe el tipo nuevo, el anterior y el `post_id`.
- [ ] 8.3 Implementar `set_post_type($id, $type)` con las meta `pts_original_type` (solo la primera vez) y `pts_previous_type` (el inmediatamente anterior), y borrarlas al revertir. **Verificación:** E2E manual + WP-CLI — `get_post_meta` muestra las dos claves tras dos cambios y desaparecen al revertir.
- [ ] 8.4 Disparar la acción `post_type_switcher` al terminar la inicialización de admin. **Verificación:** E2E manual — enganchar la acción la ve ejecutarse en una página permitida.

## 9. Verificación

- [x] 9.1 Análisis estático PHP. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) sin empeorar el baseline de 1.2 (se espera solo el warning `PSR1.Files.SideEffects` de la guarda `ABSPATH`). **Evidencia:** `just php-lint` 0 errores; `just phpcs` del fichero nuevo y del principal: 0 errores (solo warnings `PSR1.Files.SideEffects` preexistentes).
- [x] 9.2 Análisis estático JS. **Verificación:** `node --check` de `assets/js/post-type-switcher-block.js` y `assets/js/post-type-switcher-quickedit.js` sin errores. **Evidencia:** `node --check` de ambos JS → OK.
- [ ] 9.3 E2E manual del editor clásico. **Verificación:** metabox, cambio por el `select`, guardado y persistencia; reversion al tipo original y borrado de meta.
- [ ] 9.4 E2E manual del editor de bloques. **Verificación:** fila «Post Type», confirmación aceptada/cancelada, navegación a la URL de cambio, panel ausente sin permisos/tipos.
- [ ] 9.5 E2E manual de la lista. **Verificación:** columna «Type» oculta por defecto, quick edit con preselección y bulk edit con «— No Change —».
- [ ] 9.6 E2E manual del endpoint AJAX y del override al guardar. **Verificación:** cambio válido + redirect; nonce inválido y sin permiso → `wp_die`; datos incompletos → `Missing data.`; guardado con salvaguardas.
- [x] 9.7 Spec. **Verificación:** `openspec validate post-type-switcher --strict` sin hallazgos. **Evidencia:** `openspec validate post-type-switcher --strict` → *Change 'post-type-switcher' is valid*.

## 10. Entrega

- [x] 10.1 Bump menor de `ATAREAO_PLUGIN_VERSION` en `atareao-functionality.php` (si se fusiona con `search-replace-block-editor`, la versión final la fija el primero en mergear). **Verificación:** la constante queda en una versión menor superior; `just php-lint` sin errores. **Evidencia:** `ATAREAO_PLUGIN_VERSION` = 1.15.0; `just php-lint` 0 errores.
- [ ] 10.2 PR por gitflow de `feature/post-type-switcher` a `development` con commits convencionales. **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio.
- [ ] 10.3 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive post-type-switcher` aplica el delta (crea `openspec/specs/post-type-switcher/spec.md`); `openspec list` ya no muestra el change activo.
