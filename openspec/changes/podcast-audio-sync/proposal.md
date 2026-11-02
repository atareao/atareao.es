# Proposal: Autorrelleno del enlace de audio por episodio desde el RSS del origen

## Why

**Medido en producción (2026-11-01), no supuesto** — el detalle está en «Contexto medido»: de los
**838** episodios publicados, **3 se publican sin `<enclosure>`** y **1 publica un `<enclosure>` que no es
una URL** (el ATA 552, con la cadena literal `"15"`). Son dos grietas vivas que los oyentes ya están
viendo.

**El enlace de audio se copia a mano, y ya se está copiando mal.** El audio no vive en el sitio: vive en
**Spotify for Podcasters** (antes Anchor), show `5a5b39c`, cuyo RSS público es un índice completo y estable
de los 844 episodios. WordPress es un espejo manual de ese índice, fiel en **833 de 834** episodios, y esas
dos grietas se pueden reparar de forma determinista con la clave de audio correcta —la identidad del
fichero, no el número— sin adivinar y sin tocar nada que ya esté bien.

## Contexto medido: por qué la clave es la identidad del fichero

```
identidades de audio distintas en el origen        : 844 de 844  (cero colisiones)
episodios de WordPress con audio                   : 834
  de ellos, con la MISMA identidad en el origen    : 833   ← el copiado a mano es fiel
  con identidad distinta                           : 1     (ATA 3: audio recodificado)
episodios sin audio utilizable                     : 4     (3 sin url + ATA 552 con "15")
```

El `itunes:episode` del propio origen **no** es utilizable como clave: marca el ATA 829 como 825 y el
ATA 712 como 713, y tiene 19 números repetidos. La identidad del fichero, en cambio, es única en los
844 items. Por eso es la clave primaria y el número es solo el respaldo.

## What Changes

- **Un módulo del plugin** (`\Atareao\PodcastAudio`) que **rellena o repara** la meta `mp3-url` de cada
  episodio publicado desde el **RSS público del origen** (sin credenciales, siempre disponible).
- **Emparejamiento por identidad de audio, no por número.** La clave primaria es la identidad del fichero
  (el UUID del `.mp3` en CloudFront, único en 844/844 items): es determinista y no depende de la
  numeración, que en el origen está corrupta (el `itunes:episode` de Anchor no coincide con el número del
  título en 19 casos, y hay números duplicados). El número deducido del título es la **clave de respaldo**,
  y solo se usa cuando no hay identidad (audio vacío o inválido); si es ambiguo, **no se escribe y se
  reporta**.
- **Nunca se sobrescribe un audio válido.** Un `mp3-url` que ya es una URL absoluta `http(s)` **no se
  toca**, ni siquiera con una opción del comando: no habrá ninguna. La divergencia se reporta con los dos
  valores. Se distingue el caso «no hay URL» (vacío o valor que no es URL, como el `"15"` del ATA 552)
  —que sí se repara— del caso «hay URL y apunta a otro fichero» (ATA 3, audio recodificado), que **no se
  corrige desde aquí**: es una decisión del titular, y se documenta.
- **Un comando WP-CLI** (`wp atareao podcast-audio-sync`, en la línea del ya existente
  `wp atareao podcast-audio-length`) que resuelve el histórico completo en una pasada, con `--dry-run`,
  `--limit`, `--missing` y `--format`, y recuento final por clase (reparados / ya correctos / divergentes /
  ambiguos / sin par / fallidos). Idempotente: la segunda pasada no escribe nada.
- **Sincronización al publicar.** Al publicarse un episodio, se intenta resolver *ese* episodio en la misma
  petición (una descarga acotada del origen: 915 KB comprimidos, **0,18 s** medidos). Un fallo de red o un
  episodio que aún no está en el origen **no rompe la publicación** y no escribe nada.

## Fuera de alcance, por decisión del titular (2026-11-02)

**La URL pública de Spotify y su enlace en la ficha del episodio no entran en este change.** El titular
decidió no crear la app de desarrollador que harían falta. Se conserva aquí el conocimiento medido para no
perderlo ni volver a investigarlo:

| Se quería | Veredicto | Prueba |
|---|---|---|
| Subir un episodio por API | **No existe** | Ningún endpoint de subida en la referencia de la Web API |
| Subir transcripciones por API | **No existe** | El flujo es manual en `creators.spotify.com` |
| Derivar la URL pública del `<link>` del RSS | **No** (hipótesis refutada) | Ver abajo |

El `<link>` del RSS del origen es
`https://podcasters.spotify.com/pod/show/atareao-con-linux/episodes/<slug>-e3ra5d1` y termina en un token
de 7 caracteres. **Ese token no es un ID de Spotify.** Prueba: el oEmbed de episodios responde **200 con
JSON** para IDs reales de otros podcasts (`5wpLryBEOWyxaMPUUSAQqQ`, `257sC2OwsIs9wvs77jUY6t`,
`4cKxa8soECaCgLw3gHjxNu` — base62 de 22 caracteres) y **504** para `e3ra5d1`, en las tres formas de URL
probadas; el oEmbed del show (`2v0fC8PyeeUTQDD67I0mKW`) sí funciona, así que el endpoint está vivo y el
problema es el identificador. Conclusión: la URL pública exigiría la Web API oficial (`Get Show Episodes`,
que sobrevivió a la purga de febrero de 2026), la cual necesita una app de desarrollador y, desde el
**11 de febrero de 2026**, **cuenta Premium** para el modo desarrollo. Si algún día se aborda, este párrafo
es el punto de partida.

## Capabilities

### Added Capabilities

- `podcast-audio-sync`: el autorrelleno y la reparación de `mp3-url` desde el RSS del origen, sus reglas de
  emparejamiento y de no sobrescritura, el comportamiento del comando masivo, el gancho de publicación y el
  aislamiento de fallos. Se especifica como capacidad propia porque su contrato (qué se escribe, cuándo y
  desde qué fuente) es verificable con independencia del feed y del resto del plugin.

## Impact

- **Ficheros a crear (solo tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-audio.php` — el módulo: parseo del
    RSS del origen, identidad de audio, índice, motor de decisión puro, gancho de publicación y comando
    WP-CLI.
  - `tests/audio-sync/test_podcast_audio.php` — arnés procedural (patrón del proyecto: dobles en memoria
    de `wp_remote_get`/`wp_remote_retrieve_*`, transients y `WP_Post`, con `tests/wp-stubs.php`),
    **offline** y determinista, con una muestra recortada del RSS real como fixture.
  - `tests/audio-sync/fixtures/origin-rss-sample.xml` — fixture con los casos que importan: `ATA 844`
    (normal), `ATA839` sin espacio, `ATA T01E50 - ...` (el formato antiguo), `705 - ...` (número fuera del
    patrón), `T01E36 ...`, un par duplicado 667, un item sin número y el `"15"`.
- **Ficheros a modificar:**
  - `wp-content/plugins/atareao-functionality/atareao-functionality.php` — `require_once` +
    `PodcastAudio::init()` en el bootstrap.
  - `.justfile` — receta `just test-audio` que ejecuta el arnés nuevo (patrón de `just test-transcripts`).
- **Sin cambios en el runtime del feed**: `class-podcast-feed.php` no se toca. El `<enclosure>` sigue
  saliendo de `mp3-url`, así que la reparación del ATA 552 mejora el feed sin tocar su código, y los 833
  episodios ya correctos **no cambian de enclosure**.
- **Sin credenciales ni secretos nuevos:** este change no necesita ninguna configuración de entorno.
- **No se toca** `class-metaboxes.php` (`mp3-url` ya está registrada), ni `class-mcp.php`, ni
  `single-podcast.php`: son superficie de la mitad de Spotify, que queda fuera.

## Decisiones tomadas y alternativas descartadas

- **Identidad de audio como clave primaria** (y no el número): 844 identidades únicas en 844 items, y 833
  de 834 episodios casan. El `itunes:episode` del origen **no** es fiable (el ATA 829 va marcado como 825,
  el ATA 712 como 713) y hay 19 números repetidos. Medido, no estimado.
- **El número deducido del título** (`ATA 839`, `ATA839`, `ATA T01E50`, `705 - ...`, `T01E36`) cubre 832
  de 835 números de WordPress; los 3 restantes (4, 32, 666) se resuelven o se reportan: 666 casa por
  identidad (en el origen va titulado 667).
- **Ni tarea programada ni cron**: `quadlets/atareao-wordpress.container:22` fija `DISABLE_WP_CRON=true`,
  así que un evento diferido **no se ejecutaría**. El trabajo se hace en la petición de publicación (coste
  medido: 0,18 s y 915 KB comprimidos) o a mano por WP-CLI.
- **Petición condicional y caché**: el origen envía `ETag` y responde **304** al `If-None-Match` (medido),
  así que el índice se cachea y se revalida: una ejecución completa hace **una** descarga.
- **Descartado: derivar la URL pública del RSS** (refutado arriba), **rastrear la web del reproductor**
  (renderizado en JS y contra los términos de uso), **usar el token anónimo interno del reproductor** (no
  documentado, frágil y justo lo que Spotify cerró en febrero), **un endpoint REST propio** (el comando
  cubre el caso y no añade superficie pública) y **publicar el enlace de audio en el feed** (fuera de
  alcance: el feed no cambia).
- **Descartada la opción `--fix-mp3`** que sí existía en el borrador anterior: el titular decidió que este
  comando **no sobrescriba nunca** un `mp3-url` válido. La única divergencia real (ATA 3) queda reportada
  para siempre y se corrige, si acaso, a mano.

## Limitaciones y deuda aceptada (no bloqueantes)

- **Números ambiguos** (667 y 3 en el origen): se reportan y no se escriben.
- **Los episodios 4 y 32 no existen en el origen**; los 3 episodios sin `number` no se pueden emparejar.
  Los cinco se reportan como «sin par»: no se inventa nada.
- **El ATA 552 es la prueba de la reparación**: hoy publica `<enclosure url="15">` y debe pasar a publicar
  la URL del origen. Es el caso E2E obligatorio de la verificación.
- **`Content-Type` del `.srt`**: nginx sirve los `.srt` como `application/octet-stream` mientras el feed
  declara `application/x-subrip`. Deuda heredada del change anterior, ajena a este.
- **CI no ejecuta las suites de pruebas** (`.github/workflows/ci.yml` solo hace `php -l` y el test de
  bump). Añadirlas a CI es una mejora independiente, no parte de este change.
