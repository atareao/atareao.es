# Design: VideoObject completo

## Contexto

`appendVideoObject()` construye la entidad a partir de `embeddedVideo()` → `resolveVideo()`. Google define `embedUrl` como la URL **del reproductor** («the `src` attribute of an `<iframe>`»), no la página; `url` describe la página y no sustituye a `contentUrl`/`embedUrl`. La duración ya es dato canónico del episodio (`PodcastFeed::DURATION_META`= `duration`, legado `_duration`, normalizado a `HH:MM:SS` por `normalizeDuration()` en #194).

## Decisión 1 — `embedUrl`

| Origen | `embed` |
|---|---|
| YouTube (`id` no vacío) | `https://www.youtube-nocookie.com/embed/<id>` |
| Vimeo (id numérico extraíble de `url`) | `https://player.vimeo.com/video/<id>` |
| Sin identificador | `''` (no se emite `embedUrl`) |

Coincide con el `<iframe>` que sirve el reproductor (#240): URL declarada y URL cargada son la misma familia. Se descarta `contentUrl` (no hay fichero de medios; declararlo con la página del vídeo sería falso).

## Decisión 2 — `duration` ISO 8601

- Fuente única: **`PodcastFeed`** gana un accesor público `episodeDuration(int $postId): string` que devuelve la duración normalizada `HH:MM:SS` (`''` si no hay dato válido), reutilizando `normalizeDuration()`/`durationMeta()`.
- `SEO` convierte a ISO 8601 con una **función pura** `iso8601Duration('HH:MM:SS')` → `PT#H#M#S` (omitiendo componentes a cero: `00:22:04` → `PT22M4S`; `01:02:03` → `PT1H2M3S`).
- Duración ausente, `00:00:00` o malformada → **propiedad omitida** (sin adivinar), coherente con la omisión del `<itunes:duration>`.
- La duración declarada es la del **episodio** (audio), que es la que ya conocemos; Google no la verifica contra el vídeo. Documentado.

## Decisión 3 — Miniatura 16:9 con reserva garantizada

Los candidatos de `i.ytimg.com` en orden de preferencia:

| Candidato | Tamaño | Notas |
|---|---|---|
| `maxresdefault.jpg` | 1280×720, 16:9 | Mejor para rich results; **no siempre existe** (404 en subidas SD) |
| `hq720.jpg` | 1280×720, 16:9 | Existe en la mayoría; no garantizado |
| `hqdefault.jpg` | 480×360, **4:3** | **Siempre existe** (reserva) |

Resolución: `SEO::youtubeThumbnail($id)` prueba los candidatos con **`wp_remote_head`** (tiempo límite **2 s**) y **cachea el elegido en un transient** (clave `atareao_yt_thumb_<id>`, caducidad **30 días**). Ante error, tiempo agotado, código HTTP distinto de 200/204 u host no alcanzable → **`hqdefault.jpg`**. La función **nunca** lanza, nunca bloquea la composición y es **idempotente**.

Coste: una sola comprobación por vídeo y por periodo de 30 días (el transient sobrevive a la purga del `fastcgi_cache`); el resto de veces, lectura de la caché.

Para los tests, `tests/wp-stubs.php` gana un **mock FIFO de `wp_remote_head`** con el mismo patrón que el de `wp_remote_get`.

## Lo que NO cambia

La fachada visible (`appendEpisodeVideoPlayer`) sigue usando la miniatura 4:3 `hqdefault.jpg` (`thumbnail`), cuyo CSS está ajustado a esa proporción. Solo el **grafo** usa la miniatura HD.

## Alternativas descartadas

- **`contentUrl` apuntando a la página del vídeo:** semánticamente falso.
- **`maxresdefault.jpg` fijo:** un 404 por vídeo SD generaría un aviso nuevo en Search Console.
- **Cambiar la miniatura de la fachada a 16:9:** exigiría rehacer el CSS del reproductor; fuera de alcance.
- **Poblar la miniatura desde la API de YouTube (backfill):** correcto pero acopla el cambio a red, clave y cuota sin aportar mejor resultado que la sonda cacheada.
