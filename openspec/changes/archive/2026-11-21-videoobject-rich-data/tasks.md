# Tasks: VideoObject completo (embedUrl, duration, miniatura 16:9)

## RED
- [ ] Test: `resolveVideo()` devuelve `embed` (YouTube nocookie / Vimeo player) y `duration` ISO 8601.
- [ ] Test: `iso8601Duration('00:22:04')` = `PT22M4S`; `'01:02:03'` = `PT1H2M3S`; `''`/`'00:00:00'`/malformado = `''`.
- [ ] Test: `appendVideoObject()` declara `embedUrl` (mismo `<id>` que `url`) y `duration`.
- [ ] Test: sin duración, NO se declara `duration`.
- [ ] Test: `youtubeThumbnail()` con `wp_remote_head` mockeado → `maxresdefault` si 200; `hq720` si maxres 404 y hq720 200; `hqdefault` si todo falla/error/tiempo agotado.
- [ ] Test: la sonda se cachea (segunda llamada sin petición).
- [ ] Test: `thumbnailUrl` apunta al mismo `<id>` que `url`/`embedUrl`; sin `contentUrl`.
- [ ] Test: la fachada visible sigue con `hqdefault.jpg` (sin regresión).
- [ ] Test: un único `VideoObject` y resto del grafo intacto (sin regresión).
- [ ] Ejecutar los arneses afectados y confirmar el fallo de los nuevos.

## GREEN
- [ ] `PodcastFeed::episodeDuration()` público (aditivo).
- [ ] `SEO::iso8601Duration()` puro; `SEO::youtubeThumbnail()` con sonda cacheada y reserva.
- [ ] `resolveVideo()` con `embed`, `duration`, `thumbnail_hd` (aditivos).
- [ ] `appendVideoObject()` escribe `embedUrl`, `duration` y `thumbnailUrl` HD.
- [ ] Arneses en verde; `just php-lint` limpio.

## REFACTOR
- [ ] Re-ejecutar arneses afectados + `just test`.

## RELEASE Y DESPLIEGUE
- [ ] Release → deploy del plugin a producción → purga de `fastcgi_cache`.
- [ ] Verificar en producción (`?cb=`, `x-cache-status: BYPASS`): `embedUrl` + `duration` + miniatura HD en una ficha de metadato y en una de contenido.
- [ ] Revalidar en Search Console y cerrar #255.
- [ ] `docs/videoobject-rich-data.md` y actualización de `HANDOFF.md`.

## ARCHIVE
- [ ] `openspec archive videoobject-rich-data`.
