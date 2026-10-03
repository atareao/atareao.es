# Proposal: Endurecimiento de REST, metaboxes y bloques

## Why

La auditoría de seguridad de `atareao-functionality` (2026-10-03) agrupa cinco hallazgos de severidad baja/informativa que no cierran una vulnerabilidad crítica pero sí desvían contratos de seguridad ya documentados o dejan defensas en profundidad incompletas:

1. **FR-06 (LOW).** `register_rest_field('podcast', 'all_metadata', get_callback => get_post_meta($id))` (`includes/class-metaboxes.php:39-59`) devuelve **todos** los metadatos del podcast, incluidas claves protegidas (`_genesis_description`, `_edit_lock`, `_thumbnail_id`, etc.). `GET /wp-json/wp/v2/podcast/<id>` sin autenticar las divulga. **Alcance:** solo se corrige `all_metadata`; el campo `metadata` y `registerMetaFields()` (`class-metaboxes.php:89-195`) quedan **explícitamente fuera de alcance** y no se activan (ver «Fuera de alcance»).
2. **FR-07 (LOW).** `ajaxGetNextNumeroCapitulo` (`includes/class-metaboxes.php:523-538`) exige `edit_posts` pero **no verifica nonce**: un usuario con esa capacidad puede inducir la petición cross-site (CSRF de lectura) contra la acción `atareao_get_next_numero_capitulo`.
3. **SEC-BE-001 (LOW).** `isServerAllowed()` (`includes/class-opengist-block.php:197`) omite la comprobación de puerto cuando la entrada permitida no declara puerto (`$allowed['port'] === null`), aceptando cualquier puerto del host permitido. Desvía el contrato «mismo esquema y host, puerto incluido cuando se especifique» de la capability `opengist-block`.
4. **SEC-BE-002 (INFO).** `atareao_opengist_allowed_hosts` se registra con `show_in_rest => true` sobre `admin_init` (`includes/class-theme-options.php:22,64-72`). En peticiones REST `admin_init` no se dispara, así que la opción no queda realmente expuesta en `/wp/v2/settings`: un bug funcional (el editor no lee sus defaults) con apariencia de contrato de seguridad incumplido.
5. **TB-05 (INFO).** `$audio_url` procedente de `get_post_meta($podcast_id, 'mp3-url', true)` (`includes/class-podcast-block.php:97-98`) se imprime en el atributo `src` sin `esc_url()` en el punto de salida (`includes/class-podcast-block.php:117`). Aunque el meta se sanea al guardar con `esc_url_raw`, falta la defensa en profundidad al emitir.

## What Changes

- **FR-06 — Exposición REST acotada de metadatos de podcast.** El campo REST `all_metadata` deja de volcar todos los metadatos: nunca devuelve claves protegidas (prefijo `_`) ni claves internas, y la respuesta se limita a un conjunto curado de metadatos públicos del podcast (`mp3-url`, `number`, `season`, `post_views_count`). La defensa es la **curación de claves**; no se declara `auth_callback` porque `register_rest_field()` de core no lo consume y sería un no-op (falso contrato). El campo `metadata` y `registerMetaFields()` quedan fuera de alcance y **no se activan**.
- **FR-07 — Nonce en el AJAX de número de capítulo.** `ajaxGetNextNumeroCapitulo` verifica un nonce ligado a la acción antes de hacer ningún trabajo, además de la capacidad `edit_posts`; el editor deja de funcionar sin un nonce válido. Se conservan el nombre del hook, la acción y el contrato de respuesta (`{ next }` / error).
- **SEC-BE-001 — Coincidencia de puerto en la lista blanca de OpenGist.** `isServerAllowed()` exige que el puerto coincida: una entrada permitida que no declara puerto no autoriza URLs con puerto explícito, y una entrada con puerto exige ese mismo puerto. Se conservan la lista `atareao_opengist_allowed_hosts`, el esquema y el host.
- **SEC-BE-002 — Registro efectivo de `show_in_rest`.** Las opciones con `show_in_rest => true` se registran en un hook que también se ejecuta durante las peticiones REST (p. ej. `init`), de modo que la opción declarada como REST lo esté de verdad; o, si no se desea exponerla, se retira `show_in_rest`. Los nombres, el saneado y los defaults no cambian.
- **TB-05 — Escape en la salida del bloque de podcast.** La URL de audio del bloque `atareao/podcast` se escapa con `esc_url()` en el punto de emisión del atributo `src`, con independencia de que proceda de un atributo del bloque o del meta `mp3-url`. El HTML de un bloque legítimo no cambia.
- **Compatibilidad.** No se renombra ni se borra ningún hook, opción, campo REST, nombre de acción ni atributo de bloque. Los canales y contenidos que ya funcionan siguen funcionando; solo se restringe lo indebido.

## Capabilities

### New Capabilities

- `metaboxes`: contrato de seguridad del módulo `\Atareao\Metaboxes` en lo relativo a la API REST y los endpoints AJAX —exposición REST acotada del campo `all_metadata` (sin claves protegidas, lista curada) y verificación de nonce en el AJAX de número de capítulo—, conservando nombres de campo, hook, acción y contrato de respuesta. El campo `metadata` y `registerMetaFields()` quedan fuera de alcance.
- `podcast-block`: escape de salida del bloque Gutenberg `atareao/podcast` —la URL de audio, venga de un atributo o del meta `mp3-url`, se escapa con `esc_url()` al emitirse en `src`—, conservando el marcado de los bloques legítimos y el placeholder ante URL vacía.

### Modified Capabilities

- `opengist-block`: la validación del host del servidor del bloque exige además la coincidencia de puerto, de modo que una entrada permitida sin puerto no autorice cualquier puerto del host.
- `theme-options`: las opciones registradas con `show_in_rest => true` se registran en un hook que se ejecuta también en peticiones REST, para que la declaración REST sea efectiva (o se retira `show_in_rest` si no se desea exponer).

## Fuera de alcance

- **`metadata` / `registerMetaFields()`** (`includes/class-metaboxes.php:89-195`). **No se activa** en este change; el método queda definido pero **sin enganchar**. Motivo: pasa un **array** como `$post_type` a `register_post_meta()` (líneas 162-194, `array('application','software')`), y core usa ese valor como clave de array, lo que provoca un **`TypeError` fatal** («Cannot access offset of type array on array») en **cada** petición. Además, activarlo expondría metas protegidas (`_download_url`, `_repository_url`, `_version`) vía REST y abriría escritura REST de metas públicas. Se abordará en un change aparte, considerando `show_in_rest => false` para las metas con prefijo `_` y `auth_callback` para `post_views_count`.

## Impact

- **Archivos a modificar (solo en la fase de implementación, tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php` (FR-06: claves acotadas en `register_rest_field` para `all_metadata`; FR-07: nonce en `ajaxGetNextNumeroCapitulo` y en el script del editor `enqueueAdminEditScripts`). `registerMetaFields()` **no se activa** (fuera de alcance).
  - `wp-content/plugins/atareao-functionality/includes/class-opengist-block.php` (SEC-BE-001: comparación de puerto en `isServerAllowed()`).
  - `wp-content/plugins/atareao-functionality/includes/class-theme-options.php` (SEC-BE-002: hook de registro de `registerSettings()`).
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-block.php` (TB-05: `esc_url()` en la salida de `src`).
  - Nuevas specs al archivar: `openspec/specs/metaboxes/spec.md` y `openspec/specs/podcast-block/spec.md` a partir de sus deltas.
- **Contratos que NO se tocan:** el nombre del hook `wp_ajax_atareao_get_next_numero_capitulo` y su acción; los campos REST `all_metadata`, `metadata` y `seo_description`; las opciones `atareao_opengist_allowed_hosts`, `atareao_opengist_server` y `atareao_opengist_username` (nombre, saneado y default); la lista blanca y el comportamiento legítimo de `opengist-block`; el marcado del bloque `atareao/podcast`.
- **Compatibilidad:** los consumidores legítimos —editor REST autenticado, editor de bloques y gutenberg, bloques ya publicados con host permitido— siguen operando sin cambios. Solo se restringe el acceso indebido (meta protegida, CSRF, puerto no declarado).
- **Compatibilidad técnica:** PHP 8.3, PSR12, WordPress 6.0+. Sin nuevas dependencias.
- **Verificación:** sin framework de tests ni build tools. Se verifica con `just php-lint` (0 errores) + `just phpcs` (baseline 752 errores / 429 warnings, objetivo +0), un **arnés externo de stubs** en `/tmp/opencode/rest-blocks-harness/` (no versionado) y E2E manual del usuario.
