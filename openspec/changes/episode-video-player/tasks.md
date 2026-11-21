# Tasks: Reproductor del vídeo del episodio incrustado (fachada + youtube-nocookie)

## 1. Resolución del vídeo: `id` y `source` (TDD)

- [x] 1.1 RED — Test: `embeddedVideo()` con embed en contenido → `source === 'content'`, `id` = id del contenido.
- [x] 1.2 RED — Test: `embeddedVideo()` con `youtube_url` (sin embed) → `source === 'meta'`, `id` = id del metadato.
- [x] 1.3 GREEN — `resolveVideo()` devuelve `array{url,id,thumbnail,source}`; Vimeo devuelve `id === ''`.
- [x] 1.4 Verificar no-regresión de `appendVideoObject()` (solo usa `url`/`thumbnail`).

## 2. Fachada al final del contenido (TDD)

- [x] 2.1 RED — Test: episodio sin embed + metadato → contenido incluye `.episode-video` con `data-video-id`, miniatura y `<a>` de respaldo a `watch?v=<id>`.
- [x] 2.2 RED — Test: episodio con embed en contenido → NO se añade `.episode-video` (no duplicar).
- [x] 2.3 RED — Test: episodio sin embed ni metadato → NO se añade fachada.
- [x] 2.4 RED — Test: página no singular (listado) → NO se añade fachada.
- [x] 2.5 GREEN — `appendEpisodeVideoPlayer()` emite la fachada (anchor + miniatura + play + texto accesible) solo con `source === 'meta'`; se engancha a `the_content` prioridad 20.
- [x] 2.6 Limpiar el texto/`aria` y el `alt` (imagen decorativa + texto accesible).

## 3. Assets de la fachada

- [x] 3.1 Nuevo `assets/css/episode-video.css` (contenedor `aspect-ratio`, miniatura, botón play).
- [x] 3.2 Nuevo `assets/js/episode-video.js` (clic → sustituye `<a>` por `<iframe>` de `youtube-nocookie.com`).
- [x] 3.3 RED — Test: en singular con vídeo de metadato se encolan ambos assets; en listado/portada/sin vídeo, no.
- [x] 3.4 GREEN — `registerAssets()`/`enqueue` condicional en `wp_enqueue_scripts`, versionado con `ATAREAO_PLUGIN_VERSION`.

## 4. CSP de producción

- [x] 4.1 Añadir `https://i.ytimg.com` a `img-src` en `nginx/pro/wp.conf` (no tocar `frame-src`: ya admite `https:`).
- [x] 4.2 RED — Test: `nginx/pro/wp.conf` declara `i.ytimg.com` en `img-src`.

## 5. Cierre

- [x] 5.1 Actualizar `tests/youtube-video-fallback/test_youtube_video.php` (`.episode-video-link` → `.episode-video`).
- [x] 5.2 `just test` completo EXIT 0; `just php-lint-changed` EXIT 0; phpcs PSR12 sin errores.
- [x] 5.3 `openspec validate episode-video-player --strict` válido.
- [ ] 5.4 Doc `docs/episode-video-player.md` (ficheros, funcionamiento, assets, CSP, despliegue, reversibilidad).
- [ ] 5.5 Release → deploy (ficheros del plugin + `nginx/pro/wp.conf` + recarga nginx) → verificación en producción → cierre del issue.
