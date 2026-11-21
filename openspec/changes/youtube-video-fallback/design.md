# Design: Enlace y datos estructurados del vídeo de cada episodio

## Context

`\Atareao\SEO::embeddedVideo()` (en `includes/class-seo.php`) es hoy la única fuente de verdad del
vídeo de un post, y sólo mira `post_content`. La cadena actual es:

```
appendVideoObject()  ─┐
appendEpisodeVideoLink() ─┴─► embeddedVideo() ─► embeddedVideoUrl(post_content) ─► (youtubeVideoId | vimeo)
```

No existe ningún metadato que declare el vídeo de un episodio. El mapeo episodio↔vídeo se obtuvo
**fuera** del sitio (repositorio `atareao/visibilidad`, `data/review/mapping-episodios-youtube-2026-11-21.tsv`:
843/850 filas con `post_id`, `videoId`, `fecha`, `titulo`).

## Decisión 1 — Dónde vive el dato: metadato `youtube_url` (no ID suelto)

Se guarda la **URL** de YouTube, no el `videoId` ni el `<iframe>`:

- Alinea con los metadatos existentes del proyecto (`download_url`, `repository_url`): un campo de
  URL, legible y editable a mano en el admin.
- Deja la extracción del identificador y la miniatura en un único punto (`youtubeVideoId`), ya
  probado contra embeds reales.
- No introduce HTML en la base de datos (evita almacenar `<iframe>` como contenido de metadato).

Alternativas descartadas: guardar sólo el `videoId` (menos autoexplicativo, obliga a construir la
URL en cada lectura); incrustar el `<iframe>` en `post_content` (invasivo, altera el contenido y el
feed); usar un campo ACF (el proyecto no usa ACF; los metaboxes son propios).

## Decisión 2 — Resolución en dos pasos con un único camino

Se introduce una resolución con **precedencia contenido → metadato** y **una sola** función que la
implementa; `appendVideoObject` y `appendEpisodeVideoLink` la reutilizan. Así el enlace de final de
episodio y el `VideoObject` nunca se contradicen.

Orden deliberado: el **contenido gana** al metadato. Si un autor incrusta el reproductor en el
cuerpo (comportamiento más explícito y ya en producción), se respeta; el metadato es el **respaldo**
para el caso mayoritario (sin embed). Esto garantiza **cero regresión** en los 36 episodios que ya
funcionan.

Pseudo-flujo:

```
resolveVideo( post ):
    html = post_content
    id = youtubeVideoId(html)                 # paso 1
    if id == '':
        url = get_post_meta(post,'youtube_url')   # paso 2
        id = youtubeVideoId(url)
        if id == '': return null              # ni contenido ni metadato
    return { url: watch?v=<id>, thumbnail: i.ytimg.com/vi/<id>/hqdefault.jpg }
```

El caso Vimeo del contenido se conserva (paso 1); el metadato sólo admite YouTube en este change.

## Decisión 3 — Metadato no expuesto por REST y validado

`register_post_meta` para los tipos de episodio, con `single=true`, `type=string`,
`show_in_rest` **desactivado** y `sanitize_callback` que valide `http(s)`. El metadato **no se
expone por la API REST**: WordPress no aplica `auth_callback` en las lecturas REST, de modo que
activar `show_in_rest` lo dejaría legible por un anónimo; no se declara `auth_callback`, que daría
falsa seguridad. El metadato solo se rellena por WP-CLI (`update_post_meta`), nunca por REST.

## Decisión 4 — El mapeo vive en `visibilidad`, no en `atareao.es`

El `atareao.es` recibe un fichero TSV con `post_id\tvideoId` (o `post_id\tslug\tyoutube_url`). El
sitio no llama a la API de YouTube: no necesita la key ni cuota. El comando WP-CLI:

```
wp atareao youtube-video backfill --file=/ruta/mapping.tsv [--dry-run] [--force]
```

- Resuelve cada fila por `post_id`; escribe `youtube_url = https://www.youtube.com/watch?v=<videoId>`.
- **Idempotente**: sin cambios si el valor ya coincide. `--dry-run` informa sin escribir.
- `--force` reescribe aunque exista valor previo (para corregir un mapeo).
- Construye la URL desde el `videoId` (identificador suelto válido `[A-Za-z0-9_-]{6,}`) o extrae el
  identificador de una URL de YouTube; una celda que no sea un identificador de YouTube válido no se
  escribe: se cuenta como `sin_video` y se avisa.

## Decisión 5 — Reversibilidad y despliegue

El cambio es **aditivo**: sin el metadato, el comportamiento es el actual. El despliegue es:
merge → release → deploy de ficheros → **backfill** → verificación en producción. Deshacer el
backfill es borrar el metadato (`wp post meta delete`), que no altera contenido.

## Follow-ups (fuera de alcance)

- Actualización periódica del mapeo (cron que consulta la API y escribe `youtube_url` de novedades).
- Los **7 episodios sin vídeo** del residuo del mapeo (3 previos a 2018, 1 entrevista 2022, 3 con
  título reformulado) se revisan a mano si se desea cubrirlos.
- Mostrar el vídeo embebido **en la página** (no sólo enlazarlo) es una decisión de producto aparte.
