# Proposal: Feed de podcast completo, compatible con Anchor y con transcripciones

## Why

El feed que publica WordPress (`https://atareao.es/podcast/feed/`) **no es el que consumen los
directorios**. Apple, Spotify, iVoox, Amazon Music, Pocket Casts y Podnews leen el feed de **Anchor**
(`itunes.apple.com/lookup?id=1437197202` → `https://anchor.fm/s/5a5b39c/podcast/rss`, 850 items). El
feed de WordPress publica **100** de las **849** entradas publicadas del CPT `podcast` (el resto las
corta `posts_per_rss = 100`), sus `<guid>` son permalinks de WordPress en vez de los UUID de Anchor, y
sus metadatos están incompletos: `itunes:duration` en 32/100, `itunes:image` en 0/100, descripciones
truncadas (~136 caracteres frente a ~1.500 de mediana en Anchor), `<itunes:type>` serial, sin
`copyright`, sin `podcast:transcript` visible ni `podcast:chapters`.

Mientras el programa dependa del feed de Anchor, **el archivo completo y la identidad de los
episodios no son nuestros**: el día que Spotify cierre Anchor o cambie su política, la audiencia se
pierde. El lock-in real, además, es **el audio**: los `<enclosure>` apuntan al CDN de Anchor
(`anchor.fm/s/5a5b39c/podcast/play/…` → `d3ctxlq1ktw2nl.cloudfront.net`), no a ficheros propios.

Este change **prepara el terreno**: deja el feed de WordPress completo, válido, con la misma identidad
de episodio que Anchor y con las transcripciones publicadas. **No migra nada todavía.**

## What Changes

- **Feed completo**: el feed del podcast publica las **849** entradas publicadas y públicas (sin el
  techo de 100), en el mismo orden cronológico descendente que Anchor.
- **Metadatos de canal completos**: `<itunes:type>episodic</itunes:type>` (por defecto), `<copyright>`
  con el titular, `itunes:owner` con nombre y correo no vacíos y categorías de la taxonomía de Apple.
- **Metadatos de episodio completos**: `itunes:duration` en el 100 % de los items, `itunes:image` por
  episodio y `<description>` con las notas completas (no el extracto truncado).
- **GUID compatibles con Anchor**: cada item declara `<guid isPermaLink="false">` con el **mismo UUID**
  que el episodio correspondiente en Anchor, mediante un mapeo determinista y verificable
  (emparejado por identidad del fichero de audio, la clave ya usada por `podcast-audio-sync`).
- **Transcripciones publicadas**: cada episodio con SRT emite `<podcast:transcript>` (la maquinaria ya
  existe) y su página muestra la transcripción como **texto indexable**; los capítulos
  (`<podcast:chapters>`) quedan definidos en el contrato pero **sin productor** en este change.
- **Sin migrar**: **no** se emite `<itunes:new-feed-url>` ni se redirige el feed. `design.md` documenta
  el procedimiento exacto (301 de Spotify for Podcasters + flujo de «cambio de proveedor» de Apple)
  para el día que se decida.
- **Estrategia de audio documentada** (`design.md`): autoalojado vs objetos + CDN vs hosting de podcast
  dedicado, con el tamaño real del archivo medido (muestra de 40 enclosures: media 21,8 MB; archivo
  completo ≈ 18,5 GB) y el impacto en ancho de banda.

## Capabilities

### New Capabilities

- `podcast-transcript-page`: la transcripción del episodio se publica como **texto indexable** en su
  página, además de enlazarse desde el feed.

### Modified Capabilities

- `podcast-feed`: el requisito del **canal** gana `copyright`, tipo `episodic` por defecto y
  propietario/categorías completos; se añaden el **archivo completo**, los **metadatos completos de
  episodio** (duración, imagen, notas), el **guid compatible con el origen**, los **capítulos** y la
  **ausencia de `<itunes:new-feed-url>`**.

## Impact

- **Ficheros previstos** (solo tras aprobación):
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php` — límite del feed,
    metadatos de canal y de episodio, guid, capítulos.
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-guid.php` (nuevo) — mapeo de GUID
    desde el RSS de Anchor reutilizando la identidad de audio.
  - `wp-content/plugins/atareao-functionality/includes/class-theme-options.php` — `copyright`, tipo y
    categorías.
  - `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php` — imagen de portada y
    capítulos por episodio.
  - `wp-content/themes/atareao-theme/single-podcast.php` — texto de la transcripción.
  - `tests/podcast-feed-soberania/` y `.justfile` — arneses de paridad item a item y de guid.
- **Sin cambios** en el feed principal `/feed/`, en las URLs del feed ni en los adjuntos existentes.
- **Sin `<itunes:new-feed-url>`**: el feed de WordPress sigue sin redirigir a nadie.

## Fuera de alcance (follow-ups)

- **La migración efectiva** del feed (301 y cambio de proveedor en Apple): se documenta, no se ejecuta.
- **La producción de capítulos**: la maquinaria de transcripción no genera documentos de capítulos.
- **La reescritura de `<enclosure>`** al audio autoalojado: la estrategia se decide y se costea, pero
  no se migra el audio en este change.
- **Cambiar la URL del feed** o retirar el feed de WordPress.
