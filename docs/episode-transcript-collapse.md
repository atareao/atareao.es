# episode-transcript-collapse — Transcripción plegada en un desplegable nativo

## Qué es

Presenta la **transcripción del episodio plegada** por defecto en un elemento desplegable nativo
(`<details>` con su `<summary>` «Transcripción»). El texto **sigue en el HTML servido** (indexable por
los buscadores), pero deja de ser un muro de texto que domina la ficha del episodio (p. ej. el episodio
850 publicaba 236 párrafos seguidos). No se oculta el contenido: es el navegador quien pliega un
`<details>` cerrado.

Sin JavaScript: el desplegable es nativo, operable por teclado y sin coste de render.

El change OpenSpec vive en `openspec/changes/episode-transcript-collapse/` (`proposal.md`, `design.md`,
`tasks.md` y el delta en `specs/podcast-transcript-page/spec.md`). Issue de entrada: **#246**.

## Ficheros implicados

- `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php` — `PodcastFeed::transcriptHtml()`
  pasa de envolver el texto en `<section><h2>` a `<details><summary>`, conservando clases y `aria-label`.
- `wp-content/themes/atareao-theme/css/custom-post-types.css` — estilos de `.episode-transcript` y su
  `<summary>` (sección añadida al final).
- `wp-content/themes/atareao-theme/single-podcast.php` — consume `transcriptHtml()` sin cambios (mismo
  contrato de llamada).
- `tests/episode-transcript-collapse/test_transcript_collapse.php` — arnés nuevo del contrato plegado.
- `tests/podcast-feed-soberania/test_feed_complete.php` — aserción del envoltorio actualizada de
  `<section` a `<details` (el requisito `podcast-transcript-page` queda **MODIFIED** por este change).
- `.justfile` — receta `test-episode-transcript-collapse` (integrada en `just test`).

## Cómo funciona

`PodcastFeed::transcriptHtml()` lee el texto del adjunto SRT referenciado por el metadato `transcript-srt`
(la única fuente de verdad, la misma que declara el feed) y devuelve:

```html
<details class="episode-transcript" aria-label="Transcripción del episodio">
  <summary class="episode-transcript__title">Transcripción</summary>
  <p>…</p>
  <p>…</p>
</details>
```

- **Plegado por defecto**: no se emite el atributo `open`.
- **Contrato estable**: se conservan la clase `episode-transcript`, la clase `episode-transcript__title`
  y el `aria-label` de la sección. El `<h2>` interno pasa a ser el `<summary>` (un `<summary>` no debe
  anidar encabezados que descuadren el esquema del documento).
- **Sin transcripción accesible**: devuelve cadena vacía → `single-podcast.php` no imprime nada.
- **Sin transcripción en tiempo de ejecución**: no se descarga audio ni se reconoce voz; solo se lee el
  `.srt` existente.

### Estilo

`.episode-transcript` (borde, radio y superficie del tema) y `.episode-transcript__title` (marcador
propio `▸` que rota con `[open]`, `cursor: pointer`, foco visible con `:focus-visible`). Los párrafos
llevan `max-width: 68ch` de lectura y `line-height: 1.7`. Sin dependencias externas.

**Decisión de encolado**: los estilos se añaden a `css/custom-post-types.css`, que el tema ya encola en
`is_singular('podcast')` (exactamente donde se usa la transcripción). Se evita crear un fichero y un
`wp_enqueue_style` nuevos: vía menos invasiva y sin coste en el resto del sitio.

## Variables y configuración

Ninguna nueva. La transcripción depende del metadato de episodio `transcript-srt` (ID de adjunto SRT),
ya existente.

## Cómo habilitar y desplegar

1. Desplegar el **plugin** (`class-podcast-feed.php`) y el **tema** (`css/custom-post-types.css`) con el
   mecanismo habitual del repo (`just deploy-prod`), según `scripts/deploy-to-prod.sh`.
2. Verificar en un episodio con transcripción que la transcripción aparece **plegada** y que el texto
   sigue presente en el HTML (ver código fuente, no solo en el `.srt` enlazado).
3. Cerrar el issue **#246**.

Aditivo y reversible: revertir el fichero del plugin y el CSS vuelve al `<section>` anterior.

## Decisiones

- **D1 — `<details>/<summary>` nativo, sin JS**: accesible y operable por teclado de forma nativa, sin
  coste de render y sin ocultar el texto a los buscadores.
- **D2 — Se conservan clases, `aria-label` y contrato de llamada**: no se rompe CSS ni
  `single-podcast.php`; el `<h2>` interno se sustituye por el `<summary>`.
- **D3 — Párrafos sin tocar**: el troceado del SRT se corrige en el issue **#247** (post-proceso del
  texto). Aquí solo cambia la presentación.
- **D4 — CSS en `custom-post-types.css`**: reutiliza el encolado existente de la ficha del episodio.
- **D5 — Arnés propio** (`tests/episode-transcript-collapse/`) y actualización de la aserción del
  envoltorio en el arnés de `podcast-feed-soberania` por el cambio de contrato.

## Follow-ups

- **#247 — post-proceso del SRT** (puntuación, mayúsculas y troceado): fuera de alcance aquí.
- **Archivado del change**: `episode-transcript-collapse` **MODIFICA** el requisito de
  `podcast-feed-soberania`; no se puede archivar hasta que `podcast-feed-soberania` se archive primero.
- **Verificación en producción** (dependiente de despliegue): plegado real y presencia del texto en el
  HTML servido de un episodio con transcripción.
