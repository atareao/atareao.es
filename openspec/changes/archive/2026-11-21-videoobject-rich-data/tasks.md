# Tasks: VideoObject completo (embedUrl, duration, miniatura 16:9)

## RED
- [x] Test: `resolveVideo()` devuelve `embed` (YouTube nocookie / Vimeo player) y `duration` ISO 8601.
- [x] Test: `iso8601Duration('00:22:04')` = `PT22M4S`; `'01:02:03'` = `PT1H2M3S`; `''`/`'00:00:00'`/malformado = `''`.
- [x] Test: `appendVideoObject()` declara `embedUrl` (mismo `<id>` que `url`) y `duration`.
- [x] Test: sin duración, NO se declara `duration`.
- [x] Test: `youtubeThumbnail()` con `wp_remote_head` mockeado → `maxresdefault` si 200; `hq720` si maxres 404 y hq720 200; `hqdefault` si todo falla/error/tiempo agotado.
- [x] Test: la sonda se cachea (segunda llamada sin petición).
- [x] Test: `thumbnailUrl` apunta al mismo `<id>` que `url`/`embedUrl`; sin `contentUrl`.
- [x] Test: la fachada visible sigue con `hqdefault.jpg` (sin regresión).
- [x] Test: un único `VideoObject` y resto del grafo intacto (sin regresión).
- [x] Ejecutar los arneses afectados y confirmar el fallo de los nuevos.

## GREEN
- [x] `PodcastFeed::episodeDuration()` público (aditivo).
- [x] `SEO::iso8601Duration()` puro; `SEO::youtubeThumbnail()` con sonda cacheada y reserva.
- [x] `resolveVideo()` con `embed`, `duration`, `thumbnail_hd` (aditivos).
- [x] `appendVideoObject()` escribe `embedUrl`, `duration` y `thumbnailUrl` HD.
- [x] Arneses en verde; `just php-lint` limpio.

## REFACTOR
- [x] Re-ejecutar arneses afectados + `just test`.

## RELEASE Y DESPLIEGUE
- [x] Release → deploy del plugin a producción → purga de `fastcgi_cache`.
- [x] Verificar en producción (`?cb=`, `x-cache-status: BYPASS`): `embedUrl` + `duration` + miniatura HD en una ficha de metadato y en una de contenido.
- [ ] Revalidar en Search Console y cerrar #255.
- [x] `docs/videoobject-rich-data.md` y actualización de `HANDOFF.md`.

## ARCHIVE
- [x] `openspec archive videoobject-rich-data`.
