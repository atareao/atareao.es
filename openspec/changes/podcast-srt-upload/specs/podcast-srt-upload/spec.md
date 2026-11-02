# podcast-srt-upload Delta

## ADDED Requirements

### Requirement: Subida de `.srt` a la biblioteca de medios

El sitio SHALL registrar la extensión `srt` con el tipo `application/x-subrip` mediante el filtro `upload_mimes`, de forma que `get_allowed_mime_types()` y `wp_check_filetype()` resuelvan un `.srt` a ese tipo. El registro SHALL anteponerse al mapa de core, porque `wp_check_filetype()` devuelve el **primer** patrón que casa y core ya declara `txt|asc|c|cc|h|srt => text/plain`. El tipo SHALL proceder de `PodcastFeed::TRANSCRIPT_MEDIA_TYPE`, única fuente de verdad, y SHALL NOT duplicarse como literal en el módulo de subida.

Como consecuencia, `wp_check_filetype_and_ext()` SHALL devolver `ext = 'srt'` y `type = 'application/x-subrip'` para un SRT cuyo tipo real detectado sea `application/x-subrip`, en lugar de `false`, que es lo que hoy aborta la subida.

#### Scenario: Un SRT válido pasa la validación de subida

- **WHEN** se valida un `.srt` cuyo tipo real detectado es `application/x-subrip`
- **THEN** `wp_check_filetype_and_ext()` devuelve `ext = srt` y `type = application/x-subrip`, no `false`

#### Scenario: El tipo registrado gana al de core

- **WHEN** se resuelve el tipo de un `.srt` con `wp_check_filetype()`
- **THEN** devuelve `application/x-subrip` y no `text/plain`

#### Scenario: Los tipos de core no cambian

- **WHEN** se resuelve el tipo de un `.txt`
- **THEN** sigue siendo `text/plain`

#### Scenario: La subida exige la capacidad de siempre

- **WHEN** un usuario sin `upload_files` intenta subir un `.srt`
- **THEN** la subida se rechaza como cualquier otro adjunto

### Requirement: El selector de transcripción muestra los SRT

El selector `wp.media` del metabox de transcripción SHALL admitir adjuntos de tipo `application/x-subrip` y `text/plain`, para que un SRT sea seleccionable con independencia de cuál de los dos tipos le haya asignado el host. El selector SHALL seguir guardando en el campo oculto el **ID** del adjunto elegido, y el guardado SHALL seguir normalizando el valor con `PodcastFeed::normalizeTranscriptAttachment()`.

#### Scenario: Un SRT con el tipo del pipeline es seleccionable

- **WHEN** la biblioteca contiene un adjunto `application/x-subrip` y se abre el selector
- **THEN** el adjunto aparece y se puede elegir

#### Scenario: Un SRT con tipo `text/plain` es seleccionable

- **WHEN** la biblioteca contiene un adjunto `text/plain` correspondiente a un `.srt`
- **THEN** el adjunto aparece y se puede elegir

#### Scenario: Elegir un SRT escribe su ID

- **WHEN** se elige un adjunto en el selector
- **THEN** el campo `transcript_srt` recibe el ID del adjunto y el guardado lo normaliza como hasta ahora

### Requirement: El contrato del feed no cambia

La subida manual SHALL producir exactamente el mismo estado que la automatizada: un adjunto legible y la meta `transcript-srt` con su ID. El feed SHALL seguir declarando `<podcast:transcript type="application/x-subrip">` y SHALL NOT cambiar por este cambio.

#### Scenario: Tras subir y adjuntar, el feed declara la transcripción

- **WHEN** un episodio publicado tiene `transcript-srt` apuntando al adjunto subido a mano
- **THEN** el feed declara `<podcast:transcript>` con `type="application/x-subrip"` y la URL del adjunto
