# Tasks: Subida manual de transcripciones SRT

> Estado: **propuesta** (2026-11-02). Sin la aprobación del titular no se escribe código ni tests.
> Regla dura: ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Alcance: registro del tipo `srt`, filtro del selector y su arnés. Nada del feed ni del pipeline.

## 0. Contexto y decisiones previas

- [x] 0.1 Reproducir el fallo en producción con un SRT mínimo (`/tmp/prueba.srt`, 44 B):
      `wp_check_filetype_and_ext()` → `array('ext' => false, 'type' => false, 'proper_filename' => false)`
- [x] 0.2 Identificar por qué: `finfo` detecta `application/x-subrip` y core registra `.srt` como
      `text/plain`; la discrepancia hace que la validación de subida aborte
- [x] 0.3 Medir que añadir `srt => application/x-subrip` **al final** del mapa no arregla nada:
      `wp_check_filetype()` sigue devolviendo `text/plain` y la validación sigue en `false`
- [x] 0.4 Medir que **anteponerlo** sí arregla: `wp_check_filetype()` → `application/x-subrip` y
      `wp_check_filetype_and_ext()` → `ext = srt`, `type = application/x-subrip`
- [x] 0.5 Confirmar el estado de producción: 834 adjuntos `application/x-subrip`, 0 `text/plain`
- [x] 0.6 Confirmar que nginx no filtra la extensión ni el tamaño de subida no aplica (SRT de 44 B)
- [x] 0.7 Decisión: anteponer en `upload_mimes` (no filtrar la validación), reutilizar
      `PodcastFeed::TRANSCRIPT_MEDIA_TYPE` y ampliar el selector a `text/plain` como defensa

## 1. RED — pruebas que describen el contrato (antes de implementar)

- [ ] 1.1 Arnés `tests/podcast-srt-upload/test_srt_upload.php` que falla con «módulo no implementado»
      (mismo estilo que `tests/audio-sync/test_podcast_audio.php`: contador propio, `exit(0/1)`)
- [ ] 1.2 Prueba *el tipo registrado gana al de core*: el mapa devuelto por el filtro resuelve `srt` a
      `application/x-subrip` y la clave `srt` es la primera
- [ ] 1.3 Prueba *los tipos de core no cambian*: `txt` sigue resolviéndose a `text/plain`
- [ ] 1.4 Prueba *el tipo sale de la constante*: el valor es
      `PodcastFeed::TRANSCRIPT_MEDIA_TYPE` y no un literal del módulo
- [ ] 1.5 Prueba *el selector pide los dos tipos*: el script inline del metabox contiene
      `application/x-subrip` y `text/plain`
- [ ] 1.6 Ejecutar `just test-srt-upload` y pegar la evidencia RED (fallos esperados, sin fatales)

## 2. GREEN — implementación mínima

- [ ] 2.1 `includes/class-srt-upload.php`: `\Atareao\SrtUpload::init()` y
      `allowSrtMime(array $mimes): array` que antepone `srt => PodcastFeed::TRANSCRIPT_MEDIA_TYPE`
- [ ] 2.2 Registrar el módulo en `atareao-functionality.php` (`require_once` + `init()`)
- [ ] 2.3 Subir la versión del plugin y la constante `ATAREAO_PLUGIN_VERSION`
- [ ] 2.4 Cambiar el `library.type` del selector de SRT a
      `['application/x-subrip', 'text/plain']` en `class-metaboxes.php`
- [ ] 2.5 Ejecutar `just test-srt-upload`, `just php-lint` y `just test`; pegar la evidencia GREEN

## 3. REFACTOR — limpieza y estilo

- [ ] 3.1 `just phpcs` sin avisos nuevos y `just php-lint-changed` limpio
- [ ] 3.2 Comentario en el filtro explicando el «primer patrón que casa» (para que nadie lo mueva al final)
- [ ] 3.3 README del plugin: la vía manual deja de ser una promesa; documentar el tipo registrado
- [ ] 3.4 Re-ejecutar `just test-srt-upload` y `just test` tras el refactor

## 4. E2E en producción (tras desplegar)

- [ ] 4.1 En la biblioteca de medios, subir un `.srt` real y comprobar que se crea el adjunto
- [ ] 4.2 Comprobar `post_mime_type = application/x-subrip` del adjunto subido
- [ ] 4.3 Abrir «Seleccionar SRT» en un podcast y comprobar que el adjunto aparece y se puede elegir
- [ ] 4.4 Guardar el podcast y verificar que `transcript-srt` guarda el ID
- [ ] 4.5 Verificar por HTTP que el feed declara `<podcast:transcript type="application/x-subrip">`
      con la URL del adjunto nuevo
- [ ] 4.6 Limpiar el adjunto y la meta de prueba (dejar producción como estaba)

## 5. Cierre

- [ ] 5.1 Auditoría del `@rust-reviewer`/revisor PHP equivalente sobre el diff
- [ ] 5.2 PR a `development` con gitmoji y evidencia enlazada
- [ ] 5.3 `openspec archive podcast-srt-upload` una vez fusionado
