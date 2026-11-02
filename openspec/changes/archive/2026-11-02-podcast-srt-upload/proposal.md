# Proposal: Subida manual de transcripciones SRT

## Why

**Medido en producción (2026-11-02, WordPress 7.1.4).** Un `.srt` válido no se puede subir desde la
biblioteca de medios: WordPress responde con el error de tipo de archivo no permitido y no crea el
adjunto. El fallo es determinista y se reprodujo con un SRT mínimo de 44 B (`/tmp/prueba.srt`).

La causa no es un permiso ni la configuración del servidor —nginx no filtra la extensión y el rol
administrador tiene `upload_files`— sino **el detector real de tipo de WordPress**:

```
finfo_file()        → 'application/x-subrip'
wp_get_mime_types() → 'txt|asc|c|cc|h|srt' => 'text/plain'
wp_check_filetype_and_ext('/tmp/prueba.srt', 'prueba.srt')
  → array( 'ext' => false, 'type' => false, 'proper_filename' => false )
```

`wp_check_filetype_and_ext()` compara el tipo declarado (`text/plain`) con el real
(`application/x-subrip`); al no coincidir y no estar el real entre los tipos permitidos devuelve
`ext = false`, y `_wp_handle_upload()` aborta con «Lo siento, no tienes permiso para subir este tipo de
archivo».

Por eso la vía automática sí funciona y la manual no: el pipeline (`scripts/podcast-transcripts/wp-attach.php`)
escribe el adjunto con `wp_upload_bits()` + `wp_insert_attachment()` y fija a mano `application/x-subrip`,
saltándose la validación. Prueba de que ese es el tipo correcto: **834 adjuntos** de transcripción en
producción son `application/x-subrip` y **ninguno** es `text/plain`; además es el tipo que el feed declara
(`PodcastFeed::TRANSCRIPT_MEDIA_TYPE`).

Consecuencia: la caja «Transcripción (SRT)» ofrece «subir uno nuevo» y esa acción **nunca puede
funcionar** en este servidor. El operador no tiene forma de adjuntar un SRT corregido sin pasar por el
pipeline o por WP-CLI, y el README del plugin documenta una capacidad que no existe.

## What Changes

- **Registrar `.srt` como `application/x-subrip` en `upload_mimes`, al principio del mapa.** No basta con
  añadirlo al final: `wp_check_filetype()` recorre los patrones y devuelve **el primero** que casa, y core
  ya trae `txt|asc|c|cc|h|srt => text/plain`. Medido: añadido al final el resultado sigue siendo
  `text/plain` y `wp_check_filetype_and_ext()` sigue devolviendo `false`; antepuesto devuelve
  `ext = srt`, `type = application/x-subrip`.
- **El tipo no se duplica en el código**: el filtro usa `PodcastFeed::TRANSCRIPT_MEDIA_TYPE`, la misma
  constante que emite el feed.
- **El selector del metabox deja de esconder SRT válidos**: el `wp.media` del editor de podcast filtra
  hoy `library: { type: 'application' }`; pasa a aceptar `application/x-subrip` y `text/plain`, de modo
  que un SRT subido con cualquiera de los dos tipos (según lo que detecte libmagic en cada host) se puede
  elegir. Sin este cambio, un `.srt` que el servidor aceptara como `text/plain` se subiría y luego no
  aparecería en la lista.
- **Módulo nuevo y registro**: `includes/class-srt-upload.php` (`\Atareao\SrtUpload`), enganchado en
  `atareao-functionality.php`, con su versión subida y el README actualizado.

Queda **fuera de alcance**: `.vtt` y otros formatos de subtítulos, el contrato del feed (sigue siendo ID
de adjunto + `application/x-subrip`), el pipeline batch, el validador de SubRip y el re-etiquetado de
adjuntos antiguos (no hay ninguno con `text/plain`).

## Capabilities

### New Capabilities

- `podcast-srt-upload`: fija que un `.srt` se pueda subir a la biblioteca de medios y que el selector de
  transcripción lo muestre, sin tocar el contrato del feed.

## Impact

- **Ficheros:**
  - `wp-content/plugins/atareao-functionality/includes/class-srt-upload.php` — módulo nuevo: filtro
    `upload_mimes`.
  - `wp-content/plugins/atareao-functionality/atareao-functionality.php` — `require_once`, `init()` y
    versión.
  - `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php` — filtro de tipos del
    `wp.media` del selector de SRT.
  - `wp-content/plugins/atareao-functionality/README.md` — la vía manual deja de ser una promesa.
  - `tests/podcast-srt-upload/test_srt_upload.php` — arnés nuevo.
  - `.justfile` — receta `test-srt-upload`.
- **Sin cambios** en el feed, en la meta `transcript-srt`, en el pipeline ni en nginx.
- **Producción:** al desplegar, el tipo del adjunto pasa a `application/x-subrip`, el mismo que ya tienen
  los 834 adjuntos existentes; no hay migración.

## Decisiones tomadas y alternativas descartadas

- **Anteponer en `upload_mimes`**, no añadir al final. Es la diferencia medida entre que el SRT se acepte
  o no, por la semántica de «primer patrón que casa» de `wp_check_filetype()`.
- **No filtrar `wp_check_filetype_and_ext`** para forzar el tipo: sería un parche en la validación en vez
  de en el registro; el tipo quedaría fuera de `get_allowed_mime_types()` y el resto de consumidores
  (biblioteca, REST, `wp_check_filetype()`) seguiría viendo `text/plain`.
- **No re-etiquetar los adjuntos existentes**: los 834 ya son `application/x-subrip`; no hay nada que
  migrar.
- **El selector acepta también `text/plain`** porque la detección real depende de la base de datos de
  libmagic del host: es exactamente la variable que provocó este fallo.

## Limitaciones y deuda aceptada (no bloqueantes)

- **El selector acepta `.txt`** al incluir `text/plain` (hoy ya acepta cualquier `application/*`). El
  guardado sigue normalizando a ID y el feed solo exige que el fichero sea legible; endurecer la
  validación del adjunto (extensión/mime) merece su propio change.
- **`.vtt` no se toca**: el feed declara SubRip y el pipeline produce SubRip.
