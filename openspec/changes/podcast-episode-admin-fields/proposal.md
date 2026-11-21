# Change: Campos multimedia del episodio en el editor

## Why

El issue #248 pedía UI para ajustes de canal y metadatos por episodio. El inventario del 2026-11-21 muestra que los **ajustes de canal ya están implementados** (`includes/class-theme-options.php` registra las opciones y `includes/class-settings.php:76-88` las expone en `Ajustes → Tema`), así que esa mitad del issue queda cerrada sin cambios de código.

Lo que sigue faltando es la edición de los dos metadatos por episodio que el feed ya consume pero que hoy solo se pueden escribir por WP-CLI:

- `episode-image` (`PodcastFeed::EPISODE_IMAGE_META`): ID de adjunto de imagen propio del episodio; `episodeImageUrl()` cae a `atareao_podcast_cover` cuando no existe.
- `podcast-chapters` (`PodcastFeed::CHAPTERS_META`): ID de adjunto JSON de capítulos; el feed emite `podcast:chapters` solo si el adjunto es accesible.

Sin UI, cualquier cambio exige WP-CLI y es propenso a errores (IDs no verificados, MIME incorrecto).

## What Changes

- Metabox `Multimedia del episodio` en el editor del post type `podcast`, con dos selectores de medios (biblioteca de WordPress): imagen del episodio y documento JSON de capítulos.
- Guardado con nonce propio, comprobación de capacidad `edit_post`, comprobación de post type y exclusión de autosave/revisiones.
- Validación en servidor de ambos adjuntos (MIME y, para capítulos, estructura JSON con array `chapters`). Un valor inválido no se guarda y produce un aviso de administración.
- Registro de ambos metadatos con `register_post_meta` (integer, single, sin exposición REST).
- Sin cambios en el feed: los contratos de `episode-image` y `podcast-chapters` se mantienen.

## Impact

- Afecta: `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php`, nuevo asset `assets/js/podcast-media.js` (enqueue solo en pantallas de edición de `podcast`), tests nuevos en `tests/podcast-episode-admin-fields/`, `docs/podcast-episode-admin-fields.md`.
- No afecta: feed (`class-podcast-feed.php`), ajustes de canal, caché ni producción mientras no se despliegue.
- Riesgo: superficie de admin (XSS/CSRF). Mitigación: nonce, `current_user_can`, `absint`, escapado en render y validación de MIME.
- Issue: #248.