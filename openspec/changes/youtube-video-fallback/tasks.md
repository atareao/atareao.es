# Tasks: Enlace y datos estructurados del vídeo de cada episodio

## 1. Registro del metadato

- [x] 1.1 Registrar `youtube_url` (single, string, protegido, `auth_callback` restrictivo,
  `sanitize_callback` de esquema `http(s)`) sobre los tipos de episodio.
- [x] 1.2 Test: el metadato está registrado para `podcast` y NO es público sin autenticación.

## 2. Resolución del vídeo con respaldo (TDD)

- [x] 2.1 RED — Test: episodio sin embed en contenido y con `youtube_url` → `embeddedVideo()`
  devuelve URL canónica + miniatura del id.
- [x] 2.2 RED — Test: episodio con embed en contenido → sin regresión (contenido gana).
- [x] 2.3 RED — Test: episodio sin embed ni metadato → `null`.
- [x] 2.4 RED — Test: `youtube_url` con valor no-YouTube → se ignora (fallback no tomado).
- [x] 2.5 RED — Test: post inexistente → `null` sin error fatal.
- [x] 2.6 GREEN — Implementar `resolveVideo()` (contenido → metadato) y hacer que
  `embeddedVideo()` lo use; extender `youtubeVideoId` para aceptar una URL suelta.
- [x] 2.7 REFACTOR — Unificar el camino; `appendVideoObject` y `appendEpisodeVideoLink` consumen
  la misma resolución. `clippy`/linter y suite en verde.

## 3. Enlace y datos estructurados (TDD)

- [x] 3.1 RED — Test: `appendEpisodeVideoLink` añade `.episode-video-link` con respaldo en metadato.
- [x] 3.2 RED — Test: `appendVideoObject` emite `VideoObject` con nombre, descripción, miniatura,
  fecha de subida y URL desde el metadato.
- [x] 3.3 GREEN — Implementar lo mínimo para pasar 3.1 y 3.2.
- [x] 3.4 Test de no-regresión: sin metadato y sin embed → ni enlace ni `VideoObject`.

## 4. Comando WP-CLI de backfill

- [x] 4.1 RED — Test: `wp atareao youtube-video backfill --file=<tsv> --dry-run` informa sin escribir.
- [x] 4.2 RED — Test: con `--file` real escribe `youtube_url` por `post_id`; idempotente en 2ª pasada.
- [x] 4.3 RED — Test: `--force` reescribe un valor previo; fila con `post_id` inexistente se reporta.
- [x] 4.4 GREEN — Implementar el comando.

## 5. Validación y cierre

- [x] 5.1 `openspec validate youtube-video-fallback --strict` sin hallazgos.
- [x] 5.2 Suite completa en verde + linter limpio.
- [x] 5.3 Documentar en `docs/youtube-video-fallback.md` (qué, ficheros, comando, deploy, follow-ups).
- [ ] 5.4 Ciclo de release: merge → PR a `development` → PR a `main` → release → **deploy** de
  ficheros → **backfill** del mapeo (843 filas) → verificación en producción → cerrar #214.
