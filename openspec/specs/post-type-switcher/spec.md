# post-type-switcher Specification

## Purpose
Esta capability internaliza en `atareao-functionality` el plugin externo «Post Type Switcher» para poder desinstalarlo con paridad funcional. Permite cambiar el tipo de un post desde el editor de bloques, el editor clásico, la edición rápida y la edición masiva, con columna «Type», endpoint AJAX propio, cambio de tipo al guardar y memoria del tipo original/anterior, conservando hooks, meta, nonces, parámetros y acción AJAX del original.

## Requirements

### Requirement: Tipos conmutables y permisos

El sistema SHALL calcular los tipos conmutables con `get_post_types(['public' => true, 'show_ui' => true])`, SHALL excluir `attachment` y SHALL ofrecer únicamente los tipos para los que el usuario actual tiene la capacidad `publish_posts`. El sistema SHALL forzar en la lista el tipo actual del post aunque no cumpla los criterios anteriores. Para conmutar, el sistema SHALL exigir `current_user_can('edit_post', $post_id)` y la capacidad `publish_posts` del tipo destino. El sistema SHALL exponer el filtro `pts_post_type_filter` sobre los argumentos de `get_post_types` y el filtro `pts_get_post_types_filter` sobre la lista resultante. Los tipos que el usuario no puede publicar SHALL NOT ofrecerse.

#### Scenario: Listado de tipos conmutables

- **WHEN** un usuario con capacidad de publicación abre la lista de tipos conmutables
- **THEN** el sistema devuelve los tipos públicos con `show_ui` activo y, al menos, el tipo actual del post

#### Scenario: `attachment` excluido

- **WHEN** el sistema calcula los tipos conmutables
- **THEN** `attachment` no aparece en la lista aunque sea público y tenga `show_ui`

#### Scenario: Tipos sin permiso omitidos

- **WHEN** el usuario actual no tiene la capacidad `publish_posts` de un tipo
- **THEN** ese tipo no se ofrece como destino de conmutación

#### Scenario: Tipo actual forzado

- **WHEN** el tipo actual del post no cumple los criterios de visibilidad o de permiso
- **THEN** el sistema lo incluye igualmente en la lista para representar el tipo de partida

### Requirement: Conmutación desde el editor de bloques

El sistema SHALL registrar con `wp.plugins.registerPlugin` un plugin que renderice `wp.editor.PluginPostStatusInfo` con una fila «Post Type» y un `Dropdown` de `wp.components` cuyo contenido SHALL ser un fieldset con un radio por cada tipo disponible y el tipo actual marcado. Al elegir un tipo distinto, el sistema SHALL solicitar confirmación con `window.confirm("Are you sure you want to change this from a '%s' to a '%s'?")` y, si el usuario acepta, SHALL navegar a la URL de cambio (admin-ajax `action=post_type_switcher` + nonce + `post_id`). El asset del editor de bloques SHALL NOT encolarse si el usuario no puede publicar el tipo actual o no hay tipos conmutables.

#### Scenario: Panel visible en el estado de la entrada

- **WHEN** el usuario con permiso abre el editor de bloques de un post con tipos conmutables
- **THEN** el sistema muestra la fila «Post Type» dentro de `PluginPostStatusInfo` con el desplegable y el tipo actual marcado

#### Scenario: Confirmación aceptada

- **WHEN** el usuario elige otro tipo y acepta el `window.confirm`
- **THEN** el sistema navega a la URL de cambio con `action=post_type_switcher`, el nonce y el `post_id`

#### Scenario: Confirmación cancelada

- **WHEN** el usuario elige otro tipo y cancela el `window.confirm`
- **THEN** el sistema no navega y mantiene el tipo actual

#### Scenario: Sin permisos o sin tipos conmutables

- **WHEN** el usuario no puede publicar el tipo actual o no hay ningún tipo conmutable
- **THEN** el sistema no encola el asset del editor de bloques y no muestra la fila «Post Type»

### Requirement: Conmutación desde el editor clásico, edición rápida y masiva

El sistema SHALL añadir en `post_submitbox_misc_actions` una etiqueta «Post Type:», el nombre del tipo actual, un enlace «Edit», un `select` llamado `pts_post_type`, los botones «OK»/«Cancel» y `wp_nonce_field('post-type-selector', 'pts-nonce-select')`, con JS/CSS inline para mostrar y ocultar la selección. El sistema SHALL añadir la columna `post_type` con etiqueta «Type» en `manage_{$name}_posts_columns`, SHALL pintar el nombre singular del tipo dentro de `<span data-post-type="…">` en `manage_{$name}_posts_custom_column` y SHALL ocultarla por defecto con `default_hidden_columns`. El sistema SHALL añadir el `select` y el nonce en `quick_edit_custom_box` y `bulk_edit_custom_box` para la columna `post_type`; el de edición masiva SHALL incluir la opción «— No Change —» con `value="-1"`. En `edit.php` el sistema SHALL encolar un script jQuery que mueva las cajas a su fila y parchee `inlineEditPost.edit` para preseleccionar el tipo actual leído de `data-post-type`.

#### Scenario: Metabox en el editor clásico

- **WHEN** el usuario con permiso abre el editor clásico de un post
- **THEN** el sistema muestra el bloque «Post Type:» en `post_submitbox_misc_actions` con el tipo actual, el `select` y los botones «OK»/«Cancel» y el nonce

#### Scenario: Columna «Type» oculta por defecto

- **WHEN** el usuario abre la lista de posts
- **THEN** existe la columna «Type», se pinta el nombre del tipo dentro de `<span data-post-type="…">` y la columna está oculta por defecto

#### Scenario: Edición rápida

- **WHEN** el usuario abre la edición rápida de una fila
- **THEN** el sistema mueve la caja del `select` a esa fila y preselecciona el tipo actual leído de `data-post-type`

#### Scenario: Edición masiva sin cambio

- **WHEN** el usuario abre la edición masiva
- **THEN** el `select` incluye la opción «— No Change —» con `value="-1"` para no modificar el tipo

#### Scenario: Envío del tipo desde la edición rápida

- **WHEN** el usuario cambia el tipo en la edición rápida y guarda
- **THEN** el `select` `pts_post_type` viaja con el nonce `pts-nonce-select` y el guardado aplica el cambio

### Requirement: Endpoint AJAX de cambio de tipo

El sistema SHALL registrar la acción `wp_ajax_post_type_switcher`. El endpoint SHALL leer `$_GET` (`post_id`, `pts_post_type`, `pts-nonce-select`) y SHALL verificar el nonce `post-type-selector`, `current_user_can('edit_post', $post_id)` y la capacidad `publish_posts` del tipo destino. Si falta alguno de los datos SHALL terminar con `wp_die('Missing data.')`; si falla alguna verificación SHALL terminar con `wp_die`. Si todo es válido SHALL cambiar el tipo del post, SHALL disparar `do_action('post_type_after_switch', $nuevo, $anterior, $post_id)` y SHALL redirigir con `wp_safe_redirect(get_edit_post_link($post_id, 'raw'))`. El tipo destino SHALL existir.

#### Scenario: Cambio válido con redirección

- **WHEN** llega una petición AJAX con `post_id`, `pts_post_type` y un nonce válido, y el usuario puede editar el post y publicar el tipo destino
- **THEN** el sistema cambia el tipo, dispara `post_type_after_switch` y redirige a la URL de edición del post

#### Scenario: Nonce inválido

- **WHEN** la petición AJAX lleva un nonce que no verifica
- **THEN** el sistema termina con `wp_die` y no cambia el tipo

#### Scenario: Sin permiso

- **WHEN** el usuario no puede editar el post o no puede publicar el tipo destino
- **THEN** el sistema termina con `wp_die` y no cambia el tipo

#### Scenario: Datos incompletos

- **WHEN** falta `post_id`, `pts_post_type` o `pts-nonce-select`
- **THEN** el sistema termina con `wp_die('Missing data.')`

### Requirement: Cambio de tipo al guardar

El sistema SHALL enganchar el filtro `wp_insert_post_data` y el filtro `wp_insert_attachment_data`. El override SHALL exigir la presencia de `$_REQUEST['pts_post_type']` y `$_REQUEST['pts-nonce-select']`, que `post_ID` no esté vacío, que el tipo destino exista, que sea distinto del actual, que `$post_id === $postarr['ID']`, que `current_user_can('edit_post', $postarr['ID'])` y `current_user_can(cap->publish_posts)` del destino, que el nonce sea válido y que no se trate de un autosave ni de una revisión. Si se cumplen todas las condiciones, el sistema SHALL fijar `$data['post_type']` y SHALL disparar `post_type_after_switch`; en caso contrario SHALL NOT modificar los datos.

#### Scenario: El guardado cambia el tipo

- **WHEN** el editor clásico envía `pts_post_type` con un nonce válido y un tipo destino correcto
- **THEN** el sistema fija el nuevo tipo en los datos del post y dispara `post_type_after_switch`

#### Scenario: Autosave o revisión ignorados

- **WHEN** el guardado es un autosave o una revisión
- **THEN** el sistema no modifica el tipo del post

#### Scenario: Tipo destino inexistente

- **WHEN** `pts_post_type` apunta a un tipo que no existe
- **THEN** el sistema no modifica el tipo del post

#### Scenario: Sin permiso o nonce inválido

- **WHEN** el usuario no puede editar el post o publicar el tipo destino, o el nonce no verifica
- **THEN** el sistema no modifica el tipo del post

### Requirement: Memoria del tipo original/anterior, filtros y compatibilidad

El sistema SHALL implementar `set_post_type($id, $type)` de forma que guarde la meta `pts_original_type` solo la primera vez y la meta `pts_previous_type` con el tipo inmediatamente anterior; SHALL borrar `pts_original_type` cuando el post vuelve al tipo original y `pts_previous_type` cuando vuelve al tipo anterior. El sistema SHALL exponer los filtros `pts_post_type_filter`, `pts_get_post_types_filter` y `pts_allowed_pages`, y SHALL disparar la acción `post_type_switcher` al terminar la inicialización de admin. `is_allowed_page()` SHALL evaluarse como `is_blog_admin()` o (AJAX con action `inline-save`|`post_type_switcher`) con `$pagenow` en `['post.php', 'edit.php', 'admin-ajax.php']`. El sistema SHALL conservar el nonce `post-type-selector`/`pts-nonce-select`, las meta `pts_original_type`/`pts_previous_type`, los parámetros GET `pts_post_type`/`post_id` y la acción AJAX `post_type_switcher`.

#### Scenario: Stash del tipo original y anterior

- **WHEN** un post cambia de tipo por primera vez y después vuelve a cambiar
- **THEN** `pts_original_type` conserva el tipo inicial y `pts_previous_type` el inmediatamente anterior

#### Scenario: Borrado al revertir

- **WHEN** el post vuelve al tipo original
- **THEN** el sistema borra `pts_original_type` y, al volver al tipo anterior, borra `pts_previous_type`

#### Scenario: Filtros de personalización

- **WHEN** un tercero engancha `pts_post_type_filter`, `pts_get_post_types_filter` o `pts_allowed_pages`
- **THEN** el sistema aplica esos filtros sobre los argumentos, la lista de tipos y las páginas permitidas

#### Scenario: Acción de inicialización

- **WHEN** termina la inicialización de admin en una página permitida
- **THEN** el sistema dispara la acción `post_type_switcher`
