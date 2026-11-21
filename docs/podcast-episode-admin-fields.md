# podcast-episode-admin-fields — Campos multimedia del episodio en el editor

## Qué es y qué problema resuelve

El issue **#248** pedía UI para los ajustes de canal y para los metadatos por episodio. El
inventario del 2026-11-21 mostró que **la mitad de canal ya estaba implementada**: las opciones
viven en `includes/class-theme-options.php` y se pintan en `Ajustes → Tema` desde
`includes/class-settings.php`. No hacía falta tocar nada ahí.

Lo que faltaba de verdad es la edición de los dos metadatos que el feed **ya consumía** pero que
solo se podían escribir por WP-CLI:

- `episode-image` (`PodcastFeed::EPISODE_IMAGE_META`): ID de adjunto con la imagen propia del
  episodio. Si no existe, `PodcastFeed::episodeImageUrl()` cae a `atareao_podcast_cover`.
- `podcast-chapters` (`PodcastFeed::CHAPTERS_META`): ID del adjunto JSON con los capítulos. El feed
  emite `<podcast:chapters>` solo si ese adjunto es accesible.

Este change añade el metabox **«Multimedia del episodio»** al post type `podcast`, con dos
selectores de la biblioteca de medios y validación en servidor. **No toca el feed**: los contratos
de ambas claves se mantienen.

## Ficheros implicados

| Fichero (ruta relativa al repo) | Papel |
|---|---|
| `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php` | Metabox, enqueue, guardado (`saveEpisodeMedia()`), validadores (`isValidEpisodeImage()`, `isValidChaptersDocument()`), `register_post_meta` y avisos (`renderEpisodeMediaNotices()`). |
| `wp-content/plugins/atareao-functionality/assets/js/podcast-media.js` | Asset nuevo: abre `wp.media`, escribe el ID en el campo oculto y pinta la vista previa. |
| `tests/podcast-episode-admin-fields/test_admin_fields.php` | Arnés de caracterización/regresión: guardado, validación, registro de metadatos y render. |
| `.justfile` | Recipe `just test-episode-admin-fields` (podman, mismo patrón que el resto de arneses). |

## Cómo funciona

- **Constantes públicas** en `Atareao\Metaboxes`: `EPISODE_IMAGE_FIELD` (`atareao_episode_image`),
  `CHAPTERS_FIELD` (`atareao_podcast_chapters`), `MEDIA_NONCE_FIELD`
  (`atareao_podcast_media_nonce`) y `MEDIA_NONCE_ACTION` (`atareao_podcast_media`). Sirven de
  contrato para el formulario y los tests.
- **Nonce con ámbito por episodio**: la acción real es `MEDIA_NONCE_ACTION . '_' . $post_id`
  (`mediaNonceAction()`). Un nonce emitido para un episodio no vale para otro.
- **Orden de comprobaciones** en `saveEpisodeMedia()` (vía `episodeMediaPreconditionError()`):
  1. `DOING_AUTOSAVE`, `wp_is_post_autosave()` o `wp_is_post_revision()` → se aborta sin tocar metas.
  2. Nonce vacío o inválido → se aborta y se deja **su propio aviso** («la sesión ha caducado»),
     distinto del de un ID inválido.
  3. `get_post_type() !== 'podcast'` → se aborta.
  4. `current_user_can('edit_post', $post_id)` → se aborta si falta permiso.

  Superadas las cuatro, cada campo: vacío ⇒ **limpia** el metadato; no numérico ⇒ rechazo; adjunto
  que no pasa su validador ⇒ rechazo **conservando el valor anterior**.
- **Validación de la imagen**: el adjunto debe existir (no papelera), tener MIME en
  `png/jpg/jpeg/gif/webp` y **extensión coherente** (`wp_check_filetype`). **SVG queda fuera**: un
  MIME declarado a mano no cuela un SVG renombrado.
- **Validación de capítulos**: adjunto existente, MIME `application/json` o `text/plain`, fichero
  legible, **tamaño ≤ 1 MB** (se comprueba con `filesize()` *antes* de leer) y JSON con un **array
  `chapters`**.
- **Avisos**: `add_settings_error()` no se pinta fuera de una página de ajustes, así que cada rechazo
  se encola en un **transient por usuario** (`queueEpisodeMediaNotice()`) que
  `renderEpisodeMediaNotices()` vuelca en `admin_notices` y borra al mostrarlo.
- **Render**: metabox `normal`/`default`, `wp_nonce_field()` con la acción por episodio, campo oculto
  con el ID (`absint`) y vista previa escapada (`esc_attr`, `esc_html`, `wp_get_attachment_image`).

## Metadatos y feed

| Clave | Tipo | Uso en el feed |
|---|---|---|
| `episode-image` | entero (ID de adjunto) | `episodeImageUrl()`; fallback a `atareao_podcast_cover`. |
| `podcast-chapters` | entero (ID de adjunto) | `<podcast:chapters>` **solo** si `chaptersUrl()` resuelve a un adjunto accesible. |

Ambas se registran con `register_post_meta('podcast', …)`: `type=integer`, `single=true`,
`sanitize_callback=absint` y `auth_callback` con `edit_posts`. **Sin cambios en
`class-podcast-feed.php`.**

## Configuración y variables

No introduce opciones nuevas. La única configuración relacionada sigue siendo
`atareao_podcast_cover`, ya existente en `Ajustes → Tema`. Los avisos usan un transient por usuario
con TTL corto; no requiere configuración.

## Cómo habilitar / desplegar

Es cambio de plugin: **requiere deploy** y **purga de `fastcgi_cache`**.

1. Deploy por ficheros (`just deploy-prod atareao <local>=<rel>`) de:
   `includes/class-metaboxes.php` y `assets/js/podcast-media.js` (**asset nuevo**: hay que subirlo,
   no basta con el PHP).
2. Purga de las páginas de episodio y de `/podcast/` y los feeds si se quiere ver el efecto de
   inmediato (el feed no cambia de código, solo de datos al rellenar los metas).
3. Verificar en el editor de un episodio que aparece el metabox y que un guardado con un adjunto no
   válido deja aviso y conserva el valor anterior.

## Decisiones

- **Sin `show_in_rest`** en los metas: la lectura pública de un metadato no pasa por `auth_callback`,
  así que exponerlos en REST revelaría IDs internos de la biblioteca.
- **Rechazo no destructivo**: un valor inválido no borra lo que había; se conserva el metadato previo
  y se avisa. Así un JSON mal subido no deja el feed sin capítulos.
- **Capítulos como adjunto JSON**, no editor inline: el feed ya consume un adjunto y validar un
  documento es más simple y auditable que parsear texto libre desde el formulario.
- **Nonce por episodio** en vez de uno global: limita la reutilización de un nonce capturado a una
  sola entrada.

## Limitaciones y follow-ups

- El metabox es **clásico**. En el editor de bloques aparece en la zona de compatibilidad
  («advanced»), verificado en la pantalla real; si el sitio migra a edición 100 % basada en bloques
  habría que pasar a un plugin sidebar.
- No hay editor de capítulos inline: se sube el JSON a la biblioteca y se selecciona.
- Un ID huérfano se muestra como adjunto no encontrado (aviso en el metabox), pero no se limpia
  automáticamente; limpiar el campo sí lo borra.

## Verificación

- Arnés `just test-episode-admin-fields`: **104 PASS**.
- Suite completa `just test`: **1304** en verde.
- `just php-lint`: limpio.
- Auditoría de seguridad del change: sin hallazgos críticos (nonce por episodio, `edit_post`,
  exclusión de autosave/revisión, whitelist sin SVG, límite de 1 MB).

## Referencias

- Issue: **#248**.
- Change OpenSpec: `openspec/changes/podcast-episode-admin-fields/`.
- Spec de contrato del feed: `openspec/specs/podcast-feed/spec.md`.