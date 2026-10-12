# Design: Cerrar las fugas de la frontera tema/plugin

## Context

El repo tiene un tema (`atareao-theme`, ~8.5k LOC) y un plugin (`atareao-functionality`, ~24.5k LOC), versionados en lockstep y publicados como dos zips. La regla declarada es que la funcionalidad vive en el plugin y el tema es presentación.

La frontera está bien en su mayor parte: donde el tema toca lógica lo hace como adaptador fino y guardado hacia una clase del plugin (`\Atareao\CommentSecurity::processAjaxComment()`, `\Atareao\Metaboxes::getViewsHtml()` con `class_exists` + `method_exists`, `wp_style_is(...)` antes de encolar un handle del plugin). Lo que se cierra aquí son tres fugas concretas.

### Estado verificado de los filtros de imagen (producción, 2026-10-12)

Volcado real de `$wp_filter` vía WP-CLI:

| Filtro | prio 10 | prio 15 | prio 20 |
| --- | --- | --- | --- |
| `wp_calculate_image_sizes` | `atareao_theme_custom_srcset_sizes` (tema), `Atareao\SEO::heroImageSizes` (plugin) | — | — |
| `wp_get_attachment_image_attributes` | `atareao_theme_add_lazy_loading` (tema) | `Atareao\SEO::fixEmptyAlt` | `Atareao\SEO::optimizeLoading` |
| `jpeg_quality` / `wp_editor_set_quality` | `atareao_theme_jpeg_quality` (tema) | — | — |
| `big_image_size_threshold` | `atareao_theme_big_image_threshold` (tema) | — | — |

**El orden importa y es contraintuitivo.** El tema registra sus filtros al cargar `functions.php`; el plugin los registra en `init`, porque su bootstrap hace `add_action('init', 'atareao_functionality_init')` y `SEO::init()` se invoca desde ahí. Por tanto el tema queda **antes** en la cola y el plugin **después**, a la misma prioridad.

Consecuencia: en `wp_calculate_image_sizes` el callback del plugin se ejecuta el último y **gana** en páginas singulares, mientras que en los listados devuelve la entrada sin tocar y **gana el tema**. El comportamiento efectivo hoy es:

- `is_singular()` → `(max-width: 768px) 100vw, 1200px` (del plugin)
- resto → `(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw` (del tema)

La rama singular del tema es **código muerto**. Y en `wp_get_attachment_image_attributes`, el `loading="lazy"` que pone el tema (10) es retirado por `optimizeLoading` (20) en la primera imagen de una página singular o la portada: funciona, pero por coreografía implícita entre dos artefactos.

## Decisions

### D1 — Preservar el comportamiento efectivo, no el aparente

Este repo no tiene tests: la verificación es manual. Cualquier reubicación debe reproducir lo que **realmente** ocurre hoy, no lo que el código parece querer hacer. La consolidación de `wp_calculate_image_sizes` reproduce el híbrido de la tabla anterior en un **único** callback de `\Atareao\SEO`, y la rama singular del tema se descarta por muerta. Cualquier cambio de política —por ejemplo adoptar la política de `heroImageSizes` también en listados— queda **fuera** de este change y sería un change propio con su justificación de rendimiento.

### D2 — Un solo callback por filtro

Tras el change, cada uno de los cinco filtros de imagen tiene **un único** callback, en `\Atareao\SEO`. Las prioridades relativas dentro de `wp_get_attachment_image_attributes` (10 → lazy, 15 → alt, 20 → fetchpriority) se conservan **explícitas y documentadas en un solo fichero**, para que la coreografía deje de depender de qué codebase registró primero.

### D3 — El tema conserva adaptadores finos, no lógica

`atareao_get_seo_description()` permanece en el tema como adaptador: delega en `\Atareao\SEO::getDescription()` con guarda `class_exists` y, si el plugin no está, devuelve el extracto. Es exactamente el patrón ya establecido por `atareao_theme_post_views()`. La alternativa —editar los 6 puntos de llamada de las plantillas— se descarta por churn sin beneficio y por no haber tests que cubran la regresión.

### D4 — El feed se mueve; el listado del blog no

`atareao_theme_main_feed_all_post_types()` compone el **RSS**, que es una salida de datos consumida por terceros (lectores, podcatchers), no una vista. Se mueve a `\Atareao\PostTypes`. En cambio `atareao_theme_tutorial_posts_per_page()` y `atareao_theme_blog_all_post_types()` deciden *cómo se ve* el listado (cuántos por página, qué tipos aparecen) y **se quedan en el tema**: son política de presentación.

### D5 — `SEO::init()` sigue enganchado a `init`

No se cambia el punto de registro del plugin. Todos los filtros siguen registrándose en `init`, que es anterior a cualquier renderizado, subida o edición de imagen. Mover el registro a la carga del fichero no aportaría nada y rompería la convención del repo.

### D6 — Sin bump de versión

`ATAREAO_PLUGIN_VERSION`, el header `Version:` y el tema los mantiene el pipeline de release en lockstep con `.vampus.yml`. Bumpear aquí repetiría el fallo del release v1.14.0.

## Risks / Trade-offs

- **Sin tests.** Toda la verificación es manual. Mitigación: volcado del grafo de filtros antes y después (debe dar un callback por filtro, en el plugin) y **diff del HTML renderizado** de una página singular, de un archivo de CPT y de `/feed/`, comparando los atributos `sizes`, `loading` y `fetchpriority` de las imágenes. Si el diff está vacío, el comportamiento se preservó.
- **`atareao_get_seo_description()` es una función pública del tema.** Mantener el adaptador con el mismo nombre evita romper cualquier llamada externa (tema hijo, snippet).
- **`atareao_theme_image_sizes()` sigue en el tema** y ejecuta `update_option()` en cada `after_setup_theme`. WordPress cortocircuita cuando el valor no cambia, así que no hay escritura en BD, pero es un olor: candidato a `add_image_size()` o a una migración puntual. **Fuera de alcance aquí**, anotado como observación.
- **El resto de `class-seo.php`** (rel prev/next, hreflang, filtros de título de TSF, exclusión del sitemap) **sigue sin spec**. Este change solo documenta la resolución de la descripción. Una caracterización completa del módulo SEO sería un change aparte.
- **P3 es el más discutible.** Mover la composición del feed cambia de dueño una conducta visible (el RSS). El diff de HTML no cubre el feed: la verificación incluye comparar `/feed/` antes y después.

## Migration Plan

1. Caracterizar (Phase 0): volcar el grafo de filtros y capturar la salida de referencia (singular + archivo de CPT + `/feed/`).
2. Mover P1, P2 y P3 al plugin; retirar los hooks y las funciones del tema.
3. Re-volcar el grafo de filtros y re-capturar la salida; el diff debe estar vacío.
4. `just php-lint`, `just phpcs` (sin empeorar el baseline), `openspec validate --strict`.
5. PR por gitflow a `development`. Sin migración de datos ni de configuración, y sin cambio de UI.

Sin rollback especial: el change es reversible revirtiendo el commit.
