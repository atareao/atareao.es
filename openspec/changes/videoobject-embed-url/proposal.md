# Proposal: `embedUrl` en el VideoObject

## Why

Search Console reporta en `https://atareao.es/` **1 problema no crítico** de *Datos estructurados de Vídeos*: «Se debe especificar "contentUrl" o "embedUrl"». El grafo declara la entidad `VideoObject` con `url`, `name`, `uploadDate` y, cuando existen, `description` y `thumbnailUrl`, pero **ninguna** de las dos propiedades que Google exige para confirmar el vídeo. El aviso aflora ahora porque #214/#240 hicieron que `VideoObject` pasara de 36 a **843** fichas (issue #255).

## What Changes

1. `resolveVideo()` (`class-seo.php`) devuelve un campo **aditivo** `embed`: la URL de un reproductor incrustable del mismo vídeo. Los campos `url`/`id`/`thumbnail`/`source` no cambian.
2. `appendVideoObject()` añade **`embedUrl`** a la entidad cuando `embed` no está vacío:
   - **YouTube:** `https://www.youtube-nocookie.com/embed/<id>` (coherente con el reproductor de #240).
   - **Vimeo:** `https://player.vimeo.com/video/<id>` cuando el identificador numérico sea extraíble.
3. **No** se añade `contentUrl`: para vídeo alojado en YouTube/Vimeo no existe fichero de medios directo.
4. No cambia la **existencia o ausencia** de `VideoObject`, ni la resolución contenido→metadato, ni el reproductor, ni el feed.

## Capabilities

### New Capabilities

_(ninguna)_

### Modified Capabilities

- `seo`: se **añade** un requirement sobre la URL de reproducción incrustable (`embedUrl`) de la entidad `VideoObject`.

## Impact

- **Código:** `wp-content/plugins/atareao-functionality/includes/class-seo.php` (`resolveVideo`, `appendVideoObject`); arneses `tests/structured-data/` y `tests/test-valet.php`; doc del plugin.
- **Spec:** `openspec/specs/seo/spec.md` — requirement ADDED.
- **Producción:** purgar la caché `fastcgi` de las fichas tras el deploy (el grafo va en el HTML cacheado).
- **No cambia:** feed, reproductor, pipeline de transcripciones, licencias ni otros tipos de datos estructurados.

## Open Questions (a resolver en la aprobación)

1. **Dominio del embed:** `youtube-nocookie.com` (propuesto: coherente con el reproductor de #240 y mejor privacidad) frente a `youtube.com` (forma de los ejemplos de Google). Ambos son URLs de reproductor válidas; si GSC mantuviera el aviso, el cambio es una línea.
2. **¿Aprovechar para añadir `duration` al `VideoObject`** (criterio de vídeo enriquecido)? Fuera del alcance propuesto; se puede abrir como issue aparte.
