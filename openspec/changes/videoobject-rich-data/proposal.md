# Proposal: VideoObject completo (embedUrl, duration y miniatura 16:9)

## Why

Search Console reporta en `https://atareao.es/` **1 problema no crítico** de *Datos estructurados de Vídeos*: «Se debe especificar "contentUrl" o "embedUrl"». La entidad `VideoObject` declara `url`, `name`, `uploadDate` y, cuando existen, `description` y `thumbnailUrl`, pero **ninguna** de las dos propiedades que Google exige para confirmar el vídeo. Aflora ahora porque #214/#240 hicieron que `VideoObject` pasara de 36 a **843** fichas (issue #255).

Aprovechando el arreglo, se completa la entidad con dos propiedades **recomendadas** que hoy faltan y que sí influyen en cómo Google muestra el vídeo: la **duración** (ya conocida y normalizada en el feed, #194) y una **miniatura 16:9 de alta resolución** (hoy se declara `hqdefault.jpg`, 4:3 con barras negras).

## What Changes

1. `resolveVideo()` devuelve campos **aditivos**: `embed` (URL de reproductor incrustable), `thumbnail_hd` (mejor miniatura disponible) y `duration` (duración ISO 8601 o `''`). Los campos `url`/`id`/`thumbnail`/`source` **no cambian** (la fachada visible sigue usando `thumbnail`).
2. `appendVideoObject()` añade a la entidad:
   - **`embedUrl`** — YouTube `https://www.youtube-nocookie.com/embed/<id>`; Vimeo `https://player.vimeo.com/video/<id>`.
   - **`duration`** — ISO 8601 (`PT22M4S`), tomada de la **duración canónica del episodio** (meta `duration`/`_duration` vía `PodcastFeed`). Si no hay duración o es inválida, **se omite** (sin adivinar).
   - **`thumbnailUrl`** — se emite la mejor miniatura disponible (**`maxresdefault.jpg` → `hq720.jpg` → `hqdefault.jpg`**), resuelta con una **sonda HTTP cacheada, con tiempo límite y reserva garantizada**: ante cualquier error, tiempo de espera agotado o caché vacía, se emite `hqdefault.jpg` y la página **nunca** falla ni se bloquea.
3. **No** se añade `contentUrl` (no existe fichero de medios directo para vídeo alojado en YouTube/Vimeo).
4. No cambia la **existencia o ausencia** de `VideoObject`, ni la resolución contenido→metadato, ni la fachada visible, ni el feed.

## Capabilities

### New Capabilities

_(ninguna)_

### Modified Capabilities

- `seo`: se **añaden** requirements sobre la URL de reproducción incrustable (`embedUrl`), la duración ISO 8601 y la miniatura de máxima resolución con reserva garantizada.

## Impact

- **Código:** `wp-content/plugins/atareao-functionality/includes/class-seo.php` (`resolveVideo`, `appendVideoObject` + helpers puros); `includes/class-podcast-feed.php` (accesor público de duración, aditivo); arneses `tests/structured-data/`, `tests/youtube-video-fallback/`, `tests/episode-video-player/` y `tests/test-valet.php`; `tests/wp-stubs.php` (mock FIFO de `wp_remote_head`).
- **Spec:** `openspec/specs/seo/spec.md` — 3 requirements ADDED.
- **Producción:** purgar la caché `fastcgi` de las fichas tras el deploy (el grafo va en el HTML cacheado).
- **No cambia:** feed, reproductor/fachada visible, pipeline de transcripciones ni otros tipos de datos estructurados.

## Open Questions (resueltas)

1. ~~Dominio del embed~~ → **`youtube-nocookie.com`**, por coincidir con el `<iframe>` real (Google define `embedUrl` como «the URL of the video player itself… the `src` attribute of an `<iframe>`»).
2. ~~¿Ampliar?~~ → **Sí**: `duration` + miniatura 16:9.
3. **Abierto:** la sonda de miniatura introduce una petición HTTP (cacheada 30 días, tiempo límite 2 s). Alternativa más simple —emitir `maxresdefault.jpg` sin comprobar— se descarta porque una miniatura 404 generaría un aviso de Search Console nuevo, justo lo contrario del objetivo.
