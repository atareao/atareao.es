# Proposal: Reproductor del vídeo del episodio incrustado (fachada + youtube-nocookie)

## Why

Tras **#214**, cada episodio con vídeo conocido **enlaza** su vídeo al final de las notas
(`«Ver el vídeo de este episodio»`, `.episode-video-link`), pero el vídeo **no se ve** en la página:
hay que salir a YouTube para verlo. El usuario espera **ver** el vídeo en la propia ficha del
episodio.

La base ya está puesta: **843 de 850** episodios tienen el metadato `youtube_url` resolviendo su
identificador de YouTube. Este cambio convierte ese dato en un **reproductor incrustado** en las
páginas que hoy solo enlazan, multiplicando el valor de #214.

Issue de entrada: **#240**.

## What Changes

- **Fachada del vídeo** al final del contenido del episodio (donde hoy está el enlace):
  miniatura + botón de reproducción. **No carga YouTube al abrir**; solo carga el `<iframe>` al
  pulsar (rendimiento / Core Web Vitals). Sin JavaScript, la fachada **sigue siendo un enlace
  funcional** a la URL canónica del vídeo (accesibilidad y respaldo).
- **Privacidad mejorada**: el `<iframe>` se carga desde `www.youtube-nocookie.com`.
- **`\Atareao\SEO::resolveVideo()`** amplía su retorno con el **identificador** (`id`) y el **origen**
  (`source`: `content` | `meta`) del vídeo, para que la fachada conozca el id y para no duplicar el
  reproductor cuando el cuerpo ya lo incrusta. El resto de consumidores (`VideoObject`) no cambian
  de comportamiento.
- **`appendEpisodeVideoLink` → `appendEpisodeVideoPlayer`**: emite la fachada en lugar del enlace de
  texto. La fachada se añade **solo cuando el vídeo proviene del metadato** (el cuerpo no lo
  incrusta); si el cuerpo ya incrusta el vídeo, no se añade nada al final (evita un reproductor
  duplicado).
- **Assets nuevos** del plugin (`assets/css/episode-video.css`, `assets/js/episode-video.js`),
  registrados con `ATAREAO_PLUGIN_VERSION` y **encolados solo** en el singular de un episodio con
  vídeo de metadato.
- **CSP de producción** (`nginx/pro/wp.conf`): añadir `https://i.ytimg.com` a `img-src` (la miniatura
  hoy quedaría **bloqueada**); `frame-src` ya admite `https:` y cubre el origen del iframe.
- **Tests** (arnés PHP existente) del markup, del enganche de assets, del no-duplicado y de la CSP.

## Impact

- **Código:** `includes/class-seo.php` (resolución + emisión), `atareao-functionality.php` (registro
  de assets si procede), `assets/css/episode-video.css` (nuevo), `assets/js/episode-video.js` (nuevo).
- **Infra versionada:** `nginx/pro/wp.conf` (una línea de `img-src`).
- **Tests:** `tests/youtube-video-fallback/test_youtube_video.php` (actualizar) y
  `tests/episode-video-player/` (nuevo).
- **Despliegue:** ficheros del plugin + `nginx/pro/wp.conf` con recarga de nginx. Aditivo y
  reversible (retirar la emisión de la fachada).

## Open Question (a resolver antes de la implementación)

¿La fachada sustituye al enlace **en todos** los episodios con vídeo (incluidos los ~36 que ya
incrustan el reproductor en el cuerpo, donde hoy hay un enlace redundante) o **solo** donde el
cuerpo no incrusta el vídeo? La propuesta recomienda **solo donde no hay embed en el cuerpo**: evita
un reproductor duplicado y es la mejora evidente. La decisión cambia el escenario «episodio con el
vídeo embebido en el contenido».
