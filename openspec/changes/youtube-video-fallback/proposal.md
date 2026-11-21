# Proposal: Enlace y datos estructurados del vídeo de cada episodio (respaldo en metadato)

## Why

Solo **36 de las 850** páginas de episodio enlazan su vídeo de YouTube y declaran la entidad
`VideoObject`. La causa está localizada: `\Atareao\SEO::embeddedVideo()` obtiene el vídeo
**únicamente** escaneando `post_content` en busca de un `<iframe>` de YouTube/Vimeo. Los episodios
que **no** incrustan el reproductor en el cuerpo —la gran mayoría— no exponen su vídeo, aunque
exista y sea el propio del episodio.

El mapeo episodio↔`videoId` ya está resuelto y verificado: **843 de 850** episodios
(fichero de datos `mapping-episodios-youtube-2026-11-21.tsv`, obtenido con la API de YouTube sin
OAuth). Falta un canal declarativo en WordPress que ese mapeo pueda rellenar y que la generación de
SEO/datos estructurados lea.

Esta capacidad es la base de la visibilidad en buscadores de vídeo (schema `VideoObject` en
resultados enriquecidos) y de la coherencia entre la ficha del episodio y su vídeo.

## What Changes

- **Nuevo metadato `youtube_url`** en el CPT `podcast` (y tipos de episodio), protegido,
  de valor único, con validación de que apunta a YouTube y expuesto para lectura autenticada.
- **Resolución de vídeo en dos pasos** en `\Atareao\SEO::embeddedVideo()`: (1) contenido embebido
  (comportamiento actual, sin regresión) y (2) **respaldo** en el metadato `youtube_url` del post.
  La miniatura se deriva del `videoId`.
- **Un único camino de resolución** (`resolveVideo()`), del que dependen tanto el enlace al final
  del contenido (`appendEpisodeVideoLink`) como la entidad `VideoObject` del grafo
  (`appendVideoObject`), para que no diverjan.
- **Comando WP-CLI de backfill** `wp atareao youtube-video backfill --file=<tsv>` que lee el mapeo
  y escribe el `youtube_url` (idempotente; `--dry-run` y `--force`).
- **Sin migración de datos** en el despliegue: la resolución es aditiva; un episodio sin metadato
  se comporta como hoy.

## Non-goals

- **No** se incrusta el reproductor en el cuerpo del episodio ni se toca el feed del podcast.
- **No** se descargan ni alojan vídeos; sólo se enlazan y se describen.
- **No** se reenumera ni corrige el número de los vídeos mal titulados: el cruce por título lo
  neutraliza.
- **No** se automatiza el backfill periódico en este change (queda como follow-up: cron/actualización
  del mapeo con la API).

## Impact

- **Capacidad (spec):** `seo` (nueva requerimiento). Efecto colateral deseado en los datos
  estructurados (`VideoObject`) y en el enlace de final de episodio.
- **Ficheros:**
  - `wp-content/plugins/atareao-functionality/includes/class-seo.php` — resolución y fallback.
  - `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php` — registro del metadato.
  - `wp-content/plugins/atareao-functionality/includes/class-*.php` (WP-CLI) — comando de backfill.
  - `tests/` — pruebas de la resolución y del fallback.
- **Datos:** hasta **843** episodios ganan enlace de vídeo y `VideoObject` (hoy 36). 7 episodios
  quedan sin vídeo (residuo del mapeo, documentado).
- **Riesgo:** bajo. Comportamiento aditivo; sin el metadato, no cambia nada.
