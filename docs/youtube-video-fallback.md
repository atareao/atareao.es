# youtube-video-fallback — Respaldo del vídeo del episodio

## Qué es y qué problema resuelve

Cada episodio del podcast tiene su propio vídeo en YouTube, pero solo **36 de las 850** páginas de
episodio lo enlazaban y declaraban la entidad `VideoObject` de datos estructurados. La causa estaba
localizada: `\Atareao\SEO::embeddedVideo()` obtenía el vídeo **únicamente** escaneando el
`post_content` en busca de un `<iframe>` de YouTube/Vimeo. Los episodios que **no** incrustan el
reproductor en el cuerpo —la gran mayoría— no exponían su vídeo aunque existiera.

Esta feature añade un **respaldo declarativo por metadato** (`youtube_url`): cuando el contenido no
declara vídeo, la resolución lo busca en el metadato del post. Con el mapeo ya verificado
(**843 de 850** episodios), hasta **843** páginas pasan a enlazar su vídeo y a declarar
`VideoObject`, base de la visibilidad en buscadores de vídeo y de la coherencia entre la ficha del
episodio y su vídeo. Los **7** episodios que quedan sin vídeo son un residuo del mapeo, documentado
más abajo.

El cambio es **aditivo**: sin el metadato, el comportamiento es exactamente el de antes (cero
regresión).

## Ficheros implicados

| Fichero (ruta relativa al repo) | Papel |
|---|---|
| `wp-content/plugins/atareao-functionality/includes/class-seo.php` | Declara la clave `SEO::YOUTUBE_URL_META = 'youtube_url'`; contiene el punto único de resolución `SEO::embeddedVideo()` y su privado `SEO::resolveVideo()`, además de `youtubeVideoId()` / `embeddedVideoUrl()`. Consumidores: `appendEpisodeVideoLink()` (enlace al final del contenido) y `appendVideoObject()` (entidad `VideoObject`). |
| `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php` | Registra el metadato `youtube_url` sobre el CPT `podcast` con `register_post_meta()` (único, `string`, protegido). |
| `wp-content/plugins/atareao-functionality/includes/class-youtube-video.php` | `\Atareao\YoutubeVideo`: `backfill()` (motor idempotente) y `cliBackfill()` (comando WP-CLI). |
| `tests/youtube-video-fallback/test_youtube_video.php` | Arnés del contrato de resolución (contenido → metadato, miniatura, casos nulos). |
| `tests/youtube-video-fallback/test_backfill.php` | Arnés del comando de backfill (idempotencia, `--dry-run`, `--force`, `post_id` inexistente). |
| `tests/youtube-video-fallback/fixtures/mapping.tsv` | TSV de ejemplo para los tests. |
| `openspec/changes/youtube-video-fallback/` | Change OpenSpec: `proposal.md`, `design.md` y el delta `specs/seo/spec.md`. |

## Cómo funciona

La resolución vive en **un único camino** para que el enlace de final de episodio y el
`VideoObject` nunca se contradigan:

```
appendVideoObject()      ─┐
appendEpisodeVideoLink() ─┴─► SEO::embeddedVideo($post) ─► SEO::resolveVideo($post)
```

`embeddedVideo( int|\WP_Post|null $post ): array{url:string,thumbnail:string}|null` devuelve la URL
canónica y la miniatura, o `null` si no hay vídeo. `resolveVideo()` prueba **en dos pasos**:

1. **Contenido** (`post_content`), extrayendo el identificador de un embed de YouTube
   (`youtube.com/embed/`, `youtube.com/watch?v=`, `youtu.be/`) o una URL de Vimeo. Es el
   comportamiento histórico, **sin cambios**.
2. Solo si el contenido no declara vídeo, el **metadato `youtube_url`** del post, siempre que su
   valor sea una URL de YouTube válida de la que se pueda extraer un identificador.

La **miniatura** se deriva siempre del identificador de YouTube
(`https://i.ytimg.com/vi/<id>/hqdefault.jpg`). Un vídeo de Vimeo sin miniatura conocida devuelve
miniatura vacía.

**Precedencia:** el contenido **gana** al metadato. Si el autor incrustó el reproductor, se respeta
(es el caso explícito y ya en producción); el metadato solo actúa de respaldo. Esto garantiza **cero
regresión** en los 36 episodios que ya funcionaban.

**Consumidores:**

- `appendEpisodeVideoLink()` añade el enlace `.episode-video-link` ("Ver el vídeo de este
  episodio") al final del contenido en el singular de un episodio.
- `appendVideoObject()` añade la entidad `VideoObject` al grafo de datos estructurados (nombre,
  descripción, miniatura, fecha de subida y URL del vídeo).

Un episodio **sin** vídeo en el contenido ni en el metadato no declara `VideoObject` ni añade
enlace.

## El metadato `youtube_url`

- **Clave:** `youtube_url` (constante `SEO::YOUTUBE_URL_META`).
- **Tipo:** cadena, valor **único** (`single = true`).
- **Ámbito:** CPT `podcast`, el único tipo declarado en `SEO::PODCAST_EPISODE_TYPES`.
- **Protección:** **no público**, expuesto en REST solo para lectura autenticada
  (`show_in_rest` con `auth_callback` que exige `current_user_can('edit_posts')`).
- **Saneado:** `sanitize_callback` que exige esquema `http(s)` (rechaza por ejemplo `ftp:`) y pasa
  el valor por `esc_url_raw()`. Un valor inválido se guarda como cadena vacía.
- **Escritura:** se hace por WP-CLI (`update_post_meta` en el backfill); no necesita REST público.

## Comando de backfill

```
wp atareao youtube-video backfill --file=<ruta> [--dry-run] [--force]
```

Lee un TSV de mapeo episodio↔vídeo con **cabecera** y, por cada fila, resuelve el `post_id`,
construye `https://www.youtube.com/watch?v=<videoId>` y escribe el metadato `youtube_url`. Si la
celda del `videoId` ya trae una URL `http(s)`, se usa tal cual. Solo se leen las columnas `post_id`
y `videoId`; el resto (`slug`, `fecha_ep`, `num_video`, `fecha_video`, `titulo`) se ignora.

Opciones y semántica:

- **Idempotente:** si el valor actual ya coincide con el calculado, la fila cuenta como *sin
  cambios* y no se escribe nada. Una segunda ejecución reporta cero actualizaciones.
- `--dry-run`: informa de lo que haría (con los cambios) **sin escribir**.
- `--force`: reescribe aunque ya exista un valor previo (para corregir un mapeo erróneo). Sin
  `--force`, una fila con valor previo distinto cuenta como *protegida* y no se toca.
- **Filas inexistentes:** un `post_id` que no corresponde a un post existente se **avisa y se
  omite**, nunca aborta la pasada. Un `videoId` vacío cuenta como *sin vídeo*.
- Si el fichero no se puede leer o no tiene cabecera `post_id`/`videoId`, el comando termina con
  error y no escribe nada.

Salida: número de filas, actualizados (o "habría actualizado" en `--dry-run`), sin cambios,
protegidos, sin vídeo e inexistentes.

**Ejemplo real** con el mapeo de 843 filas:

```
wp atareao youtube-video backfill --file=/data/visibilidad/data/review/mapping-episodios-youtube-2026-11-21.tsv
```

El fichero `mapping-episodios-youtube-2026-11-21.tsv` tiene cabecera
`post_id  slug  fecha_ep  videoId  num_video  fecha_video  titulo` y **843 filas de datos**. El
mapeo se resuelve **fuera** del sitio, en el repositorio `atareao/visibilidad`; el sitio **no llama
a la API de YouTube**, por lo que no necesita clave ni cuota: solo recibe el TSV y rellena el
metadato por `post_id`.

## Cómo habilitar / desplegar

El cambio es aditivo y reversible; no hay migración de datos. Secuencia:

1. **Merge** de la rama `feature/youtube-video-fallback` a `development` (la release a `main` por
   el flujo habitual).
2. **Release** y **deploy de ficheros** del plugin `atareao-functionality`.
3. **Ejecutar el backfill** con el mapeo vigente (ver ejemplo arriba). Se recomienda un `--dry-run`
   previo para revisar el número de cambios.
4. **Verificar en producción:** en la página de un episodio sin embed en el cuerpo, comprobar que
   aparece el enlace `.episode-video-link` y que el HTML incluye la entidad `VideoObject` con la
   URL del vídeo y su miniatura.

**Deshacer** el backfill es borrar el metadato, que no altera el contenido:

```
wp post meta delete <post_id> youtube_url
```

Tras borrarlo, la página vuelve al comportamiento previo (sin vídeo si tampoco estaba embebido).

## Decisiones

Resumen de `openspec/changes/youtube-video-fallback/design.md`:

- **URL, no `videoId` ni `<iframe>`.** El metadato guarda la URL de YouTube: alinea con los campos
  de URL ya existentes (`download_url`, `repository_url`), es legible y editable a mano, deja la
  extracción del identificador y la miniatura en un único punto (`youtubeVideoId()`) y no
  introduce HTML en la base de datos. Descartado: guardar solo el `videoId`, incrustar el
  `<iframe>` en `post_content` o usar ACF (el proyecto no lo usa).
- **El contenido gana; el metadato es el respaldo.** Resolución en dos pasos con un único camino
  (`resolveVideo()`), reutilizado por el enlace y por `VideoObject`, para que no diverjan y para
  garantizar cero regresión. El caso Vimeo del contenido se conserva; el metadato solo admite
  YouTube en este change.
- **Metadato protegido y validado.** `register_post_meta` con `single`, `type=string`,
  `show_in_rest` con lectura autenticada y saneado `http(s)`.
- **El mapeo vive en `visibilidad`, no en `atareao.es`.** El sitio solo recibe el TSV; no llama a
  la API de YouTube (sin clave ni cuota). El comando resuelve por `post_id`, es idempotente y
  admite `--dry-run` / `--force`.
- **Reversibilidad.** Deshacer el backfill es borrar el metadato, sin tocar el contenido.

## Follow-ups

- **Actualización periódica del mapeo** con la API de YouTube (cron que consulte novedades y
  rellene `youtube_url`). Fuera de alcance en este change.
- **Revisión manual de los 7 episodios sin vídeo** del residuo del mapeo (3 previos a 2018, 1
  entrevista de 2022 y 3 con el título reformulado), si se desea cubrirlos.
- **Incrustar el vídeo en la página** (no solo enlazarlo) es una decisión de producto aparte,
  fuera de alcance.

## Referencias

- Issue: **#214**.
- Change OpenSpec: `openspec/changes/youtube-video-fallback/` (`proposal.md`, `design.md`,
  `specs/seo/spec.md`).
- Mapeo de datos: `/data/visibilidad/data/review/mapping-episodios-youtube-2026-11-21.tsv`
  (repositorio `atareao/visibilidad`).
