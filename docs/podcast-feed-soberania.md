# podcast-feed-soberania — Feed de podcast completo, compatible con Anchor y con transcripciones

## Qué es

Deja el feed del podcast de WordPress (`GET https://atareao.es/podcast/feed/`) **completo, válido y con
la identidad de episodio de Anchor**: publica todo el archivo (no 100 items), rellena los metadatos de
canal y de episodio que exigen los directorios, emite el **mismo `<guid>`** que el episodio homólogo de
Anchor y publica la transcripción como **texto indexable** en la página del episodio.

El objetivo es **preparar el terreno**, no migrar: hoy el feed que consumen Apple, Spotify, iVoox,
Amazon Music, Pocket Casts y Podnews es el de Anchor (`itunes.apple.com/lookup?id=1437197202` →
`https://anchor.fm/s/5a5b39c/podcast/rss`, 850 items) y el feed de WordPress **no lo consume ningún
directorio**. Este change hace que el feed propio pueda sustituirlo un día **sin duplicar los 849
episodios** en los directorios. **No se migra nada** en este change: no se emite
`<itunes:new-feed-url>` ni se redirige el feed.

El change OpenSpec vive en `openspec/changes/podcast-feed-soberania/` (`proposal.md`, `design.md`,
`tasks.md` y los deltas en `specs/`).

## Ficheros implicados

- `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php` (`\Atareao\PodcastFeed`) —
  feed completo, metadatos de canal y de episodio, `<guid>`, `<podcast:transcript>`,
  `<podcast:chapters>` y el helper de transcripción en página.
- `wp-content/plugins/atareao-functionality/includes/class-podcast-guid.php` (`\Atareao\PodcastGuid`) —
  mapeo determinista del guid desde el RSS de Anchor y comando WP-CLI.
- `wp-content/themes/atareao-theme/single-podcast.php` — publica el texto del SRT en la página del
  episodio.
- `tests/podcast-feed-soberania/test_feed_complete.php` y
  `tests/podcast-feed-soberania/test_guid_mapping.php` (+ `fixtures/`) — arneses del contrato.
- `.justfile` — receta `test-feed-soberania` (ejecutada también por `just test`).
- `wp-content/plugins/atareao-functionality/README.md` — sección «Feed del podcast» con el contrato.

## Cómo funciona

Toda la lógica está acotada al feed del podcast con el guardián
`PodcastFeed::isPodcastFeed()` (`is_feed() && is_post_type_archive('podcast')`): fuera de
`/podcast/feed/` no se toca nada. El feed principal `/feed/` y los feeds de otros tipos de contenido
quedan intactos.

### Feed completo

El techo de 100 items viene de la opción de core `posts_per_rss`. El filtro `option_posts_per_rss`
(`PodcastFeed::feedLimit()`) lo levanta **solo** dentro del feed del podcast (`max($value, 100000)`).
El orden es el de WordPress (fecha descendente), que coincide con el de Anchor.

### Compatibilidad con Anchor

- **GUID del episodio**: `get_the_guid` se filtra (`PodcastFeed::filterGuid()` →
  `PodcastFeed::itemGuid()`) y el `<guid isPermaLink="false">` pasa a ser el **UUID de Anchor** resuelto
  y persistido. La única fuente de verdad es el metadato `anchor-guid` del episodio.
- **GUID del programa**: `podcast:guid` es un UUIDv5 **estable** derivado de la URL del feed
  (`PodcastFeed::programGuid()`), que no cambia entre reconstrucciones ni versiones del plugin.
- **Canal**: se añaden `itunes:author`, `itunes:type` (por defecto `episodic`), `itunes:explicit`,
  `itunes:category`, `itunes:owner` (nombre y correo), `itunes:image` (portada absoluta `https`),
  `<copyright>` (titular + año) y `podcast:locked`. El `<title>`, `<link>`, `<description>`,
  `<language>` y `atom:link` los emite el núcleo; el módulo no los duplica.
- **Episodio**: `itunes:duration` (del metadato `duration` o del heredado `_duration`, o derivada del
  audio), `itunes:image` (imagen propia `episode-image` con **respaldo a la portada del programa**) y
  `<description>` con las **notas completas** (`post_content`, no el extracto truncado).
- **Capítulos**: `<podcast:chapters>` con tipo `application/json+chapters` cuando el episodio
  referencia un documento accesible en el metadato `podcast-chapters` (contrato sin productor hoy).

### Transcripción en la página

La transcripción es un **adjunto SRT** de la biblioteca de medios y el episodio la referencia con el
metadato `transcript-srt` (**ID de adjunto**, entero). El feed emite `<podcast:transcript>` desde ese
mismo adjunto cuando es accesible, y `single-podcast.php` publica el **texto** como HTML indexable
(`<section class="episode-transcript">`) llamando a `PodcastFeed::transcriptHtml()`. No se descarga
audio ni se ejecuta reconocimiento de voz: solo se lee el `.srt` ya existente. Sin transcripción
accesible no se emite el elemento ni se muestra la sección.

### Mapeo de GUIDs

`PodcastGuid::mapEpisodes()` recorre los episodios candidatos y, para cada uno sin `anchor-guid`:

1. Descarga y parsea el RSS de Anchor (índice cacheado en transient; el origen por defecto es
   `https://anchor.fm/s/5a5b39c/podcast/rss`).
2. Empareja por la **identidad del fichero de audio** (`basename` del `mp3-url`, la misma clave que usa
   `PodcastAudio`) y, como **respaldo**, por el número deducido del título.
3. Persiste el UUID resuelto en el metadato `anchor-guid` del episodio.

Es **idempotente** (un episodio ya resuelto se cuenta como `ok` y no se reescribe) y **no inventa**
guid: un episodio sin par queda sin mapear y se reporta; el feed le asigna entonces un UUIDv5 propio y
estable. El informe devuelve los contadores `mapped`, `ok`, `unmatched`, `failed` y `remaining`.

## Variables y configuración

### Opciones de WordPress (ajustes del programa, pestaña Tema)

| Opción | Elemento del feed | Valor por defecto |
|---|---|---|
| `atareao_podcast_feed` | URL canónica del feed (`atom:link`, base del `podcast:guid`) | feed del archivo (`/podcast/feed/`) |
| `atareao_podcast_cover` | `itunes:image/@href` | — (solo si es una URL `https` absoluta) |
| `atareao_podcast_author` | `itunes:author` | nombre del sitio |
| `atareao_podcast_language` | `<language>` y `podcast:transcript/@language` | idioma del sitio |
| `atareao_podcast_category` | `itunes:category/@text` | `Technology` |
| `atareao_podcast_owner_name` | `itunes:owner/itunes:name` y `<copyright>` | nombre del sitio |
| `atareao_podcast_owner_email` | `itunes:owner/itunes:email` y `podcast:locked/@owner` | correo del administrador |
| `atareao_podcast_type` | `itunes:type` (`serial`/`episodic`) | `episodic` |
| `atareao_podcast_explicit` | `itunes:explicit` (`no`/`yes`/`clean`) | `no` |

Metadatos de episodio relevantes: `mp3-url` (audio), `anchor-guid` (guid resuelto), `duration` (o el
heredado `_duration`), `episode-image` (portada propia), `number`, `season`, `podcast-chapters` y
`transcript-srt`.

### WP-CLI

- **Mapeo de GUIDs** (`PodcastGuid::mapEpisodes()`):

  ```bash
  wp atareao podcast-guid-map               # resuelve y persiste todo el archivo
  wp atareao podcast-guid-map --dry-run     # ensayo: no escribe ninguna meta
  wp atareao podcast-guid-map --limit=100   # procesa solo los primeros 100 candidatos
  ```

  Salida: `Mapeados`, `Ya resueltos`, `Sin par` y `Fallidos`. Idempotente.

- **Duración del audio** (rellena el tamaño/duración del audio para `itunes:duration`):

  ```bash
  wp atareao podcast-audio-length
  ```

## Cómo habilitar y desplegar

El cambio es código del plugin/tema bajo `wp-content/` (único directorio trackeado; no hay build tools).
El feed se sirve en la **misma URL** `/podcast/feed/`: no hay redirección ni `<itunes:new-feed-url>`.

1. **Desplegar** el plugin y el tema con el mecanismo habitual del repo (p. ej. `just deploy-prod`),
   según el flujo de despliegue de `scripts/deploy-to-prod.sh`.
2. **(Opcional) Rellenar la duración** de los episodios que aún no la tengan:
   `wp atareao podcast-audio-length`.
3. **Ejecutar el mapeo de GUIDs** de todo el archivo, con un ensayo primero:
   `wp atareao podcast-guid-map --dry-run` y, si el informe cuadra, `wp atareao podcast-guid-map`.
4. **Solo después**, anunciar/publicar el feed a los directorios.

> **ORDEN CRÍTICO: el mapeo de GUIDs (`wp atareao podcast-guid-map`) debe estar completo ANTES de
> anunciar el feed.** Si el feed se anuncia con los GUIDs sin mapear, cada item conserva el guid por
> defecto y los directorios que ya conocen los UUID de Anchor verían **todo el archivo como episodios
> nuevos** (duplicando los 849 episodios). El mapeo es requisito previo, no un paso de limpieza
> posterior.

Este change **se detiene antes de la migración**: no emite `<itunes:new-feed-url>` ni pide
redirecciones. La migración efectiva (D6 del `design.md`) va aparte y se decide con el titular.

## Decisiones

- **D1 — Feed completo sin tocar el resto de feeds**: se levanta el techo de 100 con un filtro acotado
  al feed del podcast, no subiendo `posts_per_rss` globalmente (cambiaría el feed principal).
- **D2 — Rellenar datos, no reescribir el contrato**: `itunes:duration` al 100 %, `itunes:image` por
  episodio con respaldo a la portada del programa y `<description>` con las notas completas.
- **D3 — GUID compatible por mapeo determinista y persistido**: identidad del audio como clave primaria
  y número como respaldo; el UUID resuelto se persiste en el episodio (no se recalcula en cada
  composición). Descartada la derivación sintética del permalink: no coincidiría con Anchor y
  duplicaría los episodios.
- **D4 — Capítulos: contrato sí, productor no**: se define `<podcast:chapters>`, pero no se produce
  ningún documento de capítulos en este change.
- **D5 — Estrategia de audio**: con el tamaño medido (~18,5 GB de archivo, ~2,3 GB/año) se recomienda
  **objetos + CDN** (S3/R2/B2 + Cloudflare) frente al autoalojado en el VPS o un hosting de podcast
  dedicado. El audio sigue hoy en el CDN de Anchor: es la deuda aceptada (el lock-in real).
- **D6 — Migración futura documentada, no ejecutada**: verificación del feed, redirección 301 de Spotify
  for Podcasters, «cambio de proveedor» de Apple y cierre de Anchor. `<itunes:new-feed-url>` solo
  **después** de decidir la migración.

## Follow-ups

- **Migración efectiva del feed** (301 de Spotify for Podcasters + «cambio de proveedor» de Apple):
  documentada en `design.md` (D6), no ejecutada.
- **Producción de capítulos** (`<podcast:chapters>`): contrato definido, sin productor; la maquinaria de
  transcripción no genera documentos de capítulos.
- **Reescritura de `<enclosure>` al audio autoalojado** (estrategia D5, recomendada objetos + CDN): no se
  migra el audio en este change.
- **UI de ajustes/metaboxes** (tarea 2.7): los ajustes se leen con `get_option` y valor por defecto;
  `episode-image` y `podcast-chapters` se escriben por WP-CLI. Falta la UI en `class-theme-options.php` /
  `class-metaboxes.php`.
- **Cobertura de transcripciones**: verificar qué episodios publicados carecen de `transcript-srt` y
  completarlos con el pipeline existente (`scripts/podcast-transcripts/`).
- **Verificación en producción** (dependiente de despliegue): paridad item a item de los 849 episodios
  contra Anchor, validadores de feed (W3C / Podcast Feed Validator), comprobación de que el feed
  principal no cambia y medición del tamaño/composición del feed completo con la caché de front.
- **Cambiar la URL del feed** o retirar el feed de WordPress: fuera de alcance.
