# Tasks

> **Nota inicial:** el repositorio **no tiene framework de tests ni build tools**. La verificación combina análisis estático (`just php-lint`, `just phpcs`) y comparación manual de la salida renderizada antes/después.
>
> **Estado:** pendiente de aprobación del usuario. La implementación arranca **solo tras la aprobación**.
>
> **Nota de alcance:** este change **no** bumpea `ATAREAO_PLUGIN_VERSION`: el bump lo aplica el pipeline de release en lockstep con `.vampus.yml`.
>
> **Premisa verificada (2026-10-12, producción):** el volcado de `$wp_filter` confirma que el tema registra sus filtros al cargar `functions.php` y el plugin en `init`, de modo que a igual prioridad el plugin ejecuta después. Ver `design.md` §Context.

## 1. Phase 0 — Baseline y caracterización

- [ ] 1.1 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el par errores/warnings y se anota aquí. **Evidencia:** pendiente.
- [ ] 1.2 Volcar el grafo de filtros de imagen **antes** del cambio. **Verificación:** `wp eval` lista `wp_calculate_image_sizes`, `wp_get_attachment_image_attributes`, `jpeg_quality`, `wp_editor_set_quality` y `big_image_size_threshold` con sus callbacks y prioridades, y el volcado se guarda como referencia. **Evidencia:** pendiente.
- [ ] 1.3 Capturar la salida de referencia. **Verificación:** se guardan el HTML de una página singular, el de un archivo de CPT y el de `/feed/`; se extraen los atributos `sizes`, `loading` y `fetchpriority` de todas las `<img>`. **Evidencia:** pendiente.

## 2. P1 — Optimización de imágenes en `\Atareao\SEO`

- [ ] 2.1 Mover `wp_get_attachment_image_attributes`: incorporar a `class-seo.php` el `loading="lazy"` con prioridad 10, por delante de `fixEmptyAlt` (15) y `optimizeLoading` (20). **Verificación:** el filtro queda con **un solo** callback en el plugin, con esas tres prioridades explícitas y documentadas. **Evidencia:** pendiente.
- [ ] 2.2 Mover `wp_calculate_image_sizes` reproduciendo el comportamiento efectivo verificado: `is_singular()` → `(max-width: 768px) 100vw, 1200px`; resto → `(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw`. **Verificación:** el filtro queda con **un solo** callback; `heroImageSizes` deja de existir como callback separado y la rama singular muerta del tema desaparece. **Evidencia:** pendiente.
- [ ] 2.3 Mover `jpeg_quality` + `wp_editor_set_quality` (retorno 85) y `big_image_size_threshold` (2560). **Verificación:** ambos filtros quedan con un solo callback, en el plugin. **Evidencia:** pendiente.
- [ ] 2.4 Retirar del tema las cuatro funciones y sus seis `add_filter`. **Verificación:** `rg` sobre el tema no encuentra `atareao_theme_add_lazy_loading`, `atareao_theme_custom_srcset_sizes`, `atareao_theme_jpeg_quality` ni `atareao_theme_big_image_threshold`; `just php-lint` → 0 errores. **Evidencia:** pendiente.

## 3. P2 — Descripción SEO en `\Atareao\SEO`

- [ ] 3.1 Añadir `\Atareao\SEO::getDescription($post)`, público y estático, con la lógica actual: claves `_genesis_description`, `_yoast_wpseo_metadesc`, `rank_math_description` y `_aioseo_description` en ese orden, fallback a `post_excerpt`, cadena vacía si el post no existe y caché estática por ID. **Verificación:** `just php-lint` → 0 errores. **Evidencia:** pendiente.
- [ ] 3.2 Convertir `atareao_get_seo_description()` del tema en adaptador: delega en `\Atareao\SEO::getDescription()` con guarda `class_exists` y fallback al extracto si el plugin no está. **Verificación:** las 6 plantillas siguen llamando al mismo nombre de función sin cambios; `just php-lint` → 0 errores. **Evidencia:** pendiente.

## 4. P3 — Composición del feed principal en `\Atareao\PostTypes`

- [ ] 4.1 Mover `atareao_theme_get_main_feed_post_types()` a `\Atareao\PostTypes`. **Verificación:** `just php-lint` → 0 errores. **Evidencia:** pendiente.
- [ ] 4.2 Mover `atareao_theme_main_feed_all_post_types()` y su `add_action('pre_get_posts', …)`. **Verificación:** el tema ya no registra ese hook; `rg` no encuentra ninguna de las dos funciones en el tema. **Evidencia:** pendiente.
- [ ] 4.3 Confirmar que `atareao_theme_tutorial_posts_per_page()` y `atareao_theme_blog_all_post_types()` **siguen en el tema** (política de presentación, D4). **Verificación:** `rg` las encuentra en el tema y no en el plugin. **Evidencia:** pendiente.

## 5. Verificación

- [ ] 5.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs` sin empeorar el baseline de 1.1. **Evidencia:** pendiente.
- [ ] 5.2 Re-volcado del grafo de filtros **después** del cambio. **Verificación:** cada uno de los cinco filtros tiene **un solo** callback y todos están en el plugin; no queda ningún callback del tema. **Evidencia:** pendiente.
- [ ] 5.3 Diff de la salida renderizada. **Verificación:** el diff de los atributos `sizes`, `loading` y `fetchpriority` entre la captura de 1.3 y la nueva es **vacío** en la página singular, en el archivo de CPT y en `/feed/`. Cualquier diferencia debe explicarse o revertirse. **Evidencia:** pendiente.
- [ ] 5.4 Spec. **Verificación:** `openspec validate theme-plugin-boundary --strict` sin hallazgos. **Evidencia:** pendiente.

## 6. Entrega

- [ ] 6.1 **No hay bump de versión en este change** (D6). **Verificación:** `ATAREAO_PLUGIN_VERSION` permanece en `1.16.0`; `git diff` no toca `atareao-functionality.php`. **Evidencia:** pendiente.
- [ ] 6.2 PR por gitflow de `feature/theme-plugin-boundary` a `development` con commits convencionales. **Verificación:** PR abierto/mergeado; CI `lint` en verde. **Evidencia:** pendiente.
- [ ] 6.3 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive theme-plugin-boundary` aplica los deltas; `openspec list` ya no muestra el change activo. **Evidencia:** pendiente.
