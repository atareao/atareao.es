# Proposal: Duración real del episodio en el feed del podcast

## Why

El feed del podcast publica **850 items** y solo **7** declaran una duración correcta. La medición del
2026-11-20 sobre `https://atareao.es/podcast/feed/` (issue **atareao/atareao.es#194**) da:

- **82 items sin `<itunes:duration>`** — nunca se rellenó el metadato.
- **768 items con el tag**, repartidos en tres formatos: **476 `HH:MM`**, **285 minutos sueltos**
  (`18`, `23`…) y **7 `HH:MM:SS`**.
- **761 de los 768** llevan un valor **60 veces más corto** que la duración real.

La causa es semántica, no de formato. El sitio guarda la duración en **minutos** (heredado de la
sincronización antigua) o en un `MM:SS` heredado, y `durationMeta()`
(`includes/class-podcast-feed.php:476`) lo entrega tal cual; la emisión lo escribe **verbatim**
(`:494-496`). Pero Apple interpreta `00:22` como **22 segundos** y un entero como **segundos**: un
episodio de 22 minutos se anuncia como 22 segundos. El feed de Anchor —la fuente real, configurada en
`atareao_podcast_feed` = `https://anchor.fm/s/5a5b39c/podcast/rss`— declara **850/850** con
`HH:MM:SS`.

Casos verificados: el episodio **16628** emite `00:22` mientras Anchor declara `00:22:11`; el **17764**
emite `20` mientras Anchor declara `00:20:37`; y hay un `12:20` anómalo cuyo homólogo en Anchor es
`00:20:16` (el valor heredado no es ni siquiera un múltiplo exacto). El problema es **peor de lo que
describía el issue**, que solo contaba los 82 sin tag.

No hay atajo por sondeo de audio: no hay `ffprobe` ni `ffmpeg` en `atareao`, `wpcli` ni
`wordpress-nginx-1`, y 850 audios son ≈ 18,5 GB. La única fuente fiable y barata es el RSS de Anchor,
que ya se usa para el guid.

## What Changes

- **Meta canónico único y espejo.** La duración vive en `duration` (`PodcastFeed::DURATION_META`) y se
  **espeja** en `_duration` (`LEGACY_DURATION_META`), que es lo que pinta el tema en la ficha y en la
  tarjeta del episodio. Ambos se escriben **siempre juntos**, con el mismo valor, desde un **único
  punto de escritura** (`PodcastDuration::writeDuration()`), incluido el importador de episodios.
- **Normalización en la emisión.** `PodcastFeed::normalizeDuration()` acepta solo las formas que Apple
  entiende —`HH:MM:SS`, `MM:SS` y entero de segundos— y las emite **literales**; cualquier valor
  vacío, malformado o con componentes fuera de rango **omite el tag**. Nunca se emite basura y nunca
  se multiplica ni se reinterpreta un valor ambiguo: la verdad la pone el backfill.
- **Backfill idempotente desde Anchor.** Subcomando
  `wp atareao podcast-duration-backfill [--dry-run] [--limit=<n>] [--source=<ruta>]` que empareja por
  `anchor-guid`, escribe el `HH:MM:SS` de Anchor en el canónico y su espejo **solo si difiere**, y
  reporta `actualizados / sin_cambios / sin_correspondencia / ilegibles / pendientes`. Una segunda
  pasada real debe dar **0 actualizados**.
- **Verificación en producción.** 850/850 items con `HH:MM:SS` y 10 episodios al azar comparados con
  Anchor.

## Capabilities

### Modified Capabilities

- `podcast-feed`: la emisión de `itunes:duration` deja de ser verbatim y pasa a estar normalizada, con
  una fuente de verdad única y espejada. El delta se declara como **ADDED** (requisito propio
  «Duración del episodio en el feed») porque el requisito de duración no existe hoy como tal en
  `openspec/specs/podcast-feed/spec.md`: solo hay una frase suelta dentro de «Episodio con audio y
  numeración», que este requisito **refina sin contradecir** (sigue diciendo que se declara cuando la
  duración se conozca).

## Impact

- **Qué cambia en el feed servido**: los 850 items pasan a declarar `HH:MM:SS` idéntico al de Anchor;
  los 82 que hoy no declaran nada pasan a declararlo; desaparecen los `HH:MM`, los minutos sueltos y
  cualquier valor malformado. No cambia el número de items, ni su orden, ni sus guids, ni sus
  enclosures, ni el feed principal `/feed/`.
- **Ficheros previstos** (solo tras aprobación):
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php` — `normalizeDuration()`
    y la emisión normalizada del tag.
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-duration.php` (nuevo) —
    `writeDuration()`, el emparejamiento con Anchor y el subcomando WP-CLI.
  - `wp-content/plugins/atareao-functionality/atareao-functionality.php` — `require_once` y
    `PodcastDuration::init()`.
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-guid.php` — el importador delega
    la escritura de la duración en el punto único.
  - `tests/podcast-duration/` + `.justfile` — arneses de emisión y de backfill.
  - `.github/workflows/ci.yml` — los arneses nuevos entran en el job `lint` (PHP sin contenedor).
  - `docs/podcast-duration-backfill.md` y README del plugin.
- **Sin cambios** en audio, adjuntos, `mp3-url`, títulos, fechas, numeración, guids ni en la migración
  del feed.

## Decisiones tomadas

1. **ADDED, no MODIFIED.** Un MODIFIED del requisito «Namespace y metadatos del feed del podcast»
   exigiría reproducir *todos* sus escenarios vigentes y además el de duración vive en otro requisito;
   el validador ya falló una vez por eso en este repositorio. Se añade un requisito propio y se deja
   intacto el texto vigente.
2. **Entero de segundos: literal.** Apple admite el entero como segundos, así que se emite tal cual
   (`1331`). Convertirlo a `HH:MM:SS` no aportaría nada y sugeriría una interpretación que el sistema
   no tiene por qué hacer; multiplicarlo o leerlo como minutos está prohibido por contrato. Ojo: hoy
   Apple ya lo lee como segundos, así que la normalización **no empeora** el feed; el error de los
   minutos existe desde siempre y lo corrige el backfill.
3. **Respaldo de emparejamiento: número de episodio.** Primero `anchor-guid` exacto; si falta o no
   casa, el número deducido del título de Anchor (`ATA <n>`) contra el metadato `number`, y **solo si
   hay un único candidato**. Ambos lados ya existen: `PodcastGuid::parseOriginFeed()` parsea `guid`,
   `duration` y `number`, y `PodcastAudio::episodeNumberFromTitle()` deduce el número.
4. **Módulo nuevo `PodcastDuration`** en `includes/class-podcast-duration.php`, con el patrón de
   `class-podcast-guid.php`: `init()` registra el subcomando solo si `WP_CLI` está definido, la lógica
   vive en un método público testeable (`backfill()`) y el CLI solo traduce flags y reporta.
5. **`--limit=<n>` acota actualizaciones**, no items evaluados: permite aplicar el backfill por tandas
   sin dejar el archivo a medias y reporta como `pendientes` lo que el tope dejó fuera.
6. **`_duration` sigue leyéndose como respaldo transitorio** cuando el canónico está vacío, para no
   perder el tag de un episodio que aún no se ha reconciliado. Cuando ambos existan, manda el canónico
   y el backfill los iguala; el respaldo queda como deuda a retirar cuando la verificación demuestre
   0 episodios dependientes de él.

## Fuera de alcance (no-goals)

- **Sondear el audio** con `ffprobe`/`ffmpeg` para derivar la duración: descartado por disponibilidad y
  coste (≈ 18,5 GB).
- **Reescalar heurísticamente** los valores heredados (multiplicar por 60 los enteros o los `MM:SS`):
  el `12:20` anómalo demuestra que la heurística se equivoca. La verdad viene de Anchor.
- **Tocar** el audio, el orden, los guids, los enclosures, la numeración o la migración del feed.
- **Corregir a mano** un episodio que Anchor no permita emparejar: se reporta y se deja como está.
- **Cambiar el feed principal** `/feed/` ni la ficha del episodio más allá del valor espejado.

## Riesgos

- **Dependencia de Anchor.** El backfill necesita el RSS de Anchor accesible y con `HH:MM:SS`. Si
  cambia de formato o de URL, el comando reporta `ilegibles`/error y **no escribe**; el feed sigue
  sirviendo lo que hubiera. Mitigación: `--source` con un fichero descargado y validación previa con
  `normalizeDuration()`.
- **Espejo de metas.** Escribir `duration` sin `_duration` deja la ficha del episodio con la duración
  vieja (es el defecto que produjo los 82). Mitigación: un único punto de escritura y un test que
  compara ambos metas.
- **Ambigüedad del dato heredado.** Un `MM:SS` como `12:20` es sintácticamente válido y se emitirá tal
  cual hasta que el backfill lo corrija. No se adivina. Mitigación: el backfill compara con Anchor y
  sobrescribe; la verificación exige 850/850 `HH:MM:SS`.
- **Ventana entre despliegue y backfill.** Durante esos minutos el feed sigue con los valores viejos
  (la normalización no los mejora ni los empeora). Mitigación: ejecutar el backfill en la misma
  ventana del despliegue, con `--dry-run` previo y verificación inmediata.
- **Episodios sin correspondencia.** Si alguno no empareja, conserva su valor actual y se reporta; no
  se inventa una duración.