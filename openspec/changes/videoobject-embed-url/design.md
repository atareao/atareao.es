# Design: `embedUrl` en el VideoObject

## Contexto

`appendVideoObject()` construye la entidad a partir de `embeddedVideo()` → `resolveVideo()`. Hoy la entidad declara `url` (la página de reproducción del vídeo), que Google **no** acepta como sustituto de `contentUrl`/`embedUrl`: `url` describe la página que contiene el vídeo; `embedUrl` describe **el reproductor** del vídeo.

## Decisión

- Se enriquece el retorno de `resolveVideo()` con `embed` (**aditivo**, mismo patrón que `id`/`source` en #214/#240).
- `appendVideoObject()` copia `embed` a `embedUrl` solo si no está vacío.
- Se **descarta** `contentUrl`: exigiría un fichero de medios directo (`.mp4`), que no existe para vídeo alojado en YouTube/Vimeo; declararlo con la página del vídeo sería incorrecto y podría generar otro aviso.

## URL del reproductor

| Origen | `embed` |
|---|---|
| YouTube (`id` no vacío) | `https://www.youtube-nocookie.com/embed/<id>` |
| Vimeo (id numérico extraíble de `url`) | `https://player.vimeo.com/video/<id>` |
| Sin identificador | `''` (no se emite `embedUrl`) |

El dominio `youtube-nocookie.com` es una URL de reproductor válida y coincide con el `<iframe>` que sirve el reproductor del episodio (#240), de modo que la URL declarada y la que el visitante carga son la misma familia.

## Alternativas descartadas

- **`contentUrl` apuntando a la página del vídeo:** semánticamente incorrecto (no es un fichero de medios).
- **Emitir ambos (`contentUrl` + `embedUrl`):** `contentUrl` sería falso; añadiría ruido y un posible aviso nuevo.
- **Usar `url` como `embedUrl`:** `url` es la página, no el reproductor.
