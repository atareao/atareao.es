# Proposal: Cerrar las fugas de la frontera tema/plugin

## Why

El repo declara una regla clara —«toda la funcionalidad y la lógica de negocio van en el plugin; el tema es solo presentación»— y la cumple en su mayor parte. Pero tres comportamientos se saltan la frontera, y uno ya produce un fallo real.

El volcado de `$wp_filter` en producción (2026-10-12) lo deja a la vista: en `wp_calculate_image_sizes` hay **dos callbacks, uno en cada codebase, a la misma prioridad (10)**, y el resultado efectivo es un **híbrido que depende del orden de registro**. Como el tema registra al cargar `functions.php` y el plugin lo hace en `init` (posterior), en páginas singulares gana el plugin y en los listados gana el tema. La rama singular del tema es código muerto y nadie lo sabe. Lo mismo pasa con `wp_get_attachment_image_attributes`, donde el `loading="lazy"` del tema y su retirada por el plugin forman una coreografía implícita entre dos artefactos.

Además, la descripción SEO vive en el tema mientras el SEO vive en el plugin, y la composición del feed principal —una salida de datos, no una vista— también vive en el tema.

## What Changes

- **P1 — Optimización de imágenes (una sola fuente de verdad).** Los cuatro hooks de imagen del tema (`wp_get_attachment_image_attributes`, `wp_calculate_image_sizes`, `jpeg_quality` + `wp_editor_set_quality`, `big_image_size_threshold`) se mueven a `\Atareao\SEO`, donde ya viven `fixEmptyAlt`, `optimizeLoading` y `heroImageSizes`. Se **preserva el comportamiento efectivo actual** (híbrido incluido) y se elimina la rama muerta.
- **P2 — Descripción SEO al plugin.** La lógica de `atareao_get_seo_description()` se mueve a `\Atareao\SEO::getDescription()`. El tema conserva un **adaptador fino y guardado** con el mismo nombre (patrón ya usado por `atareao_theme_post_views()`), de modo que las 6 plantillas no cambian.
- **P3 — Composición del feed principal al plugin.** `atareao_theme_get_main_feed_post_types()` y `atareao_theme_main_feed_all_post_types()` se mueven a `\Atareao\PostTypes`.
- **Lo que NO se mueve, a propósito:** `atareao_theme_tutorial_posts_per_page()` y `atareao_theme_blog_all_post_types()` (política de visualización), el adaptador AJAX de comentarios y `atareao_theme_post_views()` (ya son adaptadores finos sobre clases del plugin), y todo lo que es presentación pura.
- **Sin bump de versión.** `ATAREAO_PLUGIN_VERSION` lo mantiene el pipeline de release en lockstep con `.vampus.yml`.

## Capabilities

### New Capabilities

- `image-handling`: la política de imágenes del sitio (lazy loading, `fetchpriority`, política de `sizes`, calidad JPEG, umbral de imagen grande y alt de respaldo), propiedad del plugin.
- `seo`: la resolución de la descripción SEO de un post desde el plugin SEO activo, con caché estática y fallback al extracto.
- `main-feed`: la composición del feed principal (entradas y CPT públicos, sin páginas ni adjuntos, sin pisar los feeds de archivo ni los de comentarios).

### Modified Capabilities

Ninguna. Las tres conductas no estaban documentadas en ninguna spec: se caracterizan aquí (Phase 0) y pasan a ser propiedad del plugin.

## Impact

- **Modificados**: `wp-content/plugins/atareao-functionality/includes/class-seo.php` (4 hooks + `getDescription()`), `includes/class-post-types.php` (2 hooks de feed), `wp-content/themes/atareao-theme/functions.php` (se eliminan 6 hooks y 4 funciones; `atareao_get_seo_description()` pasa a adaptador).
- **Nuevos (spec)**: `openspec/specs/image-handling/spec.md`, `openspec/specs/seo/spec.md` y `openspec/specs/main-feed/spec.md`, vía los deltas de este change.
- **Sin migración de datos, sin cambios de configuración, sin cambios de UI.**
