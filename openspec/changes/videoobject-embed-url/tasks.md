# Tasks: `embedUrl` en el VideoObject

> Gate SDD: no empezar RED hasta la aprobación del usuario.

## RED
- [ ] Test: con vídeo de YouTube (contenido y metadato) la entidad `VideoObject` declara `embedUrl` = `https://www.youtube-nocookie.com/embed/<id>`.
- [ ] Test: `embedUrl` usa el mismo `<id>` que `url` y que la miniatura.
- [ ] Test: con vídeo de Vimeo el `embedUrl` es `https://player.vimeo.com/video/<id>`.
- [ ] Test: la entidad NO declara `contentUrl`.
- [ ] Test: sin vídeo no hay `VideoObject` (sin regresión).
- [ ] Test: un único `VideoObject` y el resto del grafo intacto (sin regresión).
- [ ] Ejecutar los arneses de datos estructurados y confirmar el fallo de los nuevos.

## GREEN
- [ ] `resolveVideo()` devuelve `embed` (aditivo) para YouTube y Vimeo.
- [ ] `appendVideoObject()` escribe `embedUrl` cuando `embed` no está vacío.
- [ ] Arneses en verde; `just php-lint` limpio.

## REFACTOR
- [ ] Re-ejecutar los arneses afectados (`tests/structured-data/`, `tests/test-valet.php`) y `just test`.

## RELEASE Y DESPLIEGUE
- [ ] Release → deploy del plugin a producción → purga de `fastcgi_cache`.
- [ ] Verificar en producción (`?cb=`, `x-cache-status: BYPASS`): `VideoObject` con `embedUrl` en una ficha de metadato y en una de contenido.
- [ ] Revalidar en Search Console (el aviso debe desaparecer) y cerrar #255.
- [ ] `docs/videoobject-embed-url.md` y actualización de `HANDOFF.md`.

## ARCHIVE
- [ ] `openspec archive videoobject-embed-url`.
