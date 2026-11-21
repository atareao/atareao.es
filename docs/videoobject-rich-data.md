# videoobject-rich-data — VideoObject completo (embedUrl, duration y miniatura 16:9)

## Qué es y qué problema resuelve

Search Console emitió un aviso **no crítico** sobre las páginas de episodio: «Se debe especificar
"contentUrl" o "embedUrl"». La entidad `VideoObject` que emite `\Atareao\SEO::appendVideoObject()`
declaraba `name`, `description`, `uploadDate`, `thumbnailUrl`… pero **no** el campo que Google usa
para vincular el vídeo: la URL del reproductor.

Esta feature completa el `VideoObject` con tres campos **aditivos** —`embedUrl`, `duration` (ISO
8601) y `thumbnailUrl` en 16:9— para que la ficha de vídeo sea válida y rica. El cambio es aditivo:
donde antes se emitía un `VideoObject` incompleto, ahora se emite completo, sin alterar la fachada
visible del reproductor (su CSS está ajustado al 4:3).

## Ficheros implicados

| Fichero (ruta relativa al repo) | Papel |
|---|---|
| `wp-content/plugins/atareao-functionality/includes/class-seo.php` | `resolveVideo()` devuelve ahora los campos aditivos `embed`, `thumbnail_hd` y `duration`; `appendVideoObject()` emite `embedUrl`, `duration` y `thumbnailUrl`. |
| `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php` | Nuevo accesor público `PodcastFeed::episodeDuration()`; fuente de la `duration` ISO 8601. |

## Cómo funciona

- **`embedUrl`**: `https://www.youtube-nocookie.com/embed/<id>` para YouTube y
  `https://player.vimeo.com/video/<id>` para Vimeo. Se usa el dominio `nocookie` por privacidad.
- **`duration`**: ISO 8601 (`PT22M4S`) a partir de `PodcastFeed::episodeDuration()`.
- **`thumbnailUrl` 16:9**: se resuelve por sonda `HEAD` contra la miniatura de mayor resolución, en
  cascada `maxresdefault` → `hq720` → reserva `hqdefault`. La sonda está **cacheada 30 días** (transient)
  y acotada con **tiempo límite de 2 s** para no penalizar el render si el CDN no responde.
- **`contentUrl` NO se emite** (decisión: el `embedUrl` es el campo que Google pide; `contentUrl`
  apuntaría al fichero de vídeo, que no controlamos).

## Configuración y variables

No introduce opciones de usuario. La caché de la miniatura vive en un *transient* de WordPress
(30 días); no requiere configuración.

## Cómo habilitar / desplegar

Es cambio de plugin: **requiere deploy** y **purga de `fastcgi_cache`**.

1. Deploy de ficheros (`just deploy-prod atareao <local>=<rel>`) de:
   `atareao-functionality.php`, `includes/class-seo.php`, `includes/class-podcast-feed.php`.
2. Purga por cabecera `X-Cache-Purge` (secreto `ATAREAO_PURGE_SECRET`) de portada, `/podcast/`,
   feeds y las páginas de episodio.
3. Revalidar el aviso en **Search Console**.

## Decisiones

- Se emite `embedUrl` en vez de `contentUrl`.
- La sonda de miniatura va cacheada (30 días) y con `timeout` de 2 s (ambos fijados por test con
  evidencia de mutación).
- El guardia `class_exists('\Atareao\PodcastFeed')` nunca se activa en producción (orden de
  `require_once`, sin autoloader): es defensa de test, no camino real.

## Follow-ups

- Vigilar en Search Console que el aviso desaparece tras la revalidación.
