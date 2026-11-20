# Proposal: Paridad exacta del guid del podcast y del archivo completo

## Why

El change `podcast-feed-soberania` dejó el feed de WordPress con el **archivo completo**, con los
metadatos de episodio completos y con un `<guid>` mapeado desde Anchor. La verificación contra el feed
real de producción revela **dos defectos residuales** que rompen la paridad exacta con el origen
(issue **atareao/atareao.es#190**):

1. **El `<guid>` emitido no es el persistido.** El metadato `anchor-guid` guarda el valor verbatim de
   Anchor (un UUID desnudo, p. ej. `773cd246-f771-4edb-9760-33dd3c775836`), pero el feed emite
   `http://773cd246-…`. La causa está en el núcleo: WordPress registra
   `add_filter( 'the_guid', 'esc_url' )` (`wp-includes/default-filters.php:271`) y en esta versión de
   WP `esc_url()` **antepone `http://`** a cualquier cadena sin esquema. `the_guid()` aplica ese filtro
   *después* de `get_the_guid()`, así que el valor ya resuelto por `filterGuid()` vuelve a ser
   escapado como URL. Comparación cruda de los dos feeds: **844 de 849** guids difieren **solo** por el
   prefijo; los 5 guids que ya eran URL (`https://anchor.fm/…`) coinciden exactos.
2. **Falta un episodio.** Anchor declara **850** items y WordPress sirve **849**. La numeración de
   WordPress (meta `number`) recorre 1–850 con **un único hueco**: el **386**. El episodio
   `ATA386 Aventuras y desventuras con Docker y las actualizaciones automáticas` existe en Anchor pero
   no tiene post en WordPress, así que el archivo completo no es tal.

Ambos son **defectos de paridad con el origen**: un directorio que ya conoce los episodios por su guid
de Anchor no los reconoce si el guid cambia, y un episodio ausente es audiencia perdida. Es el momento
de cerrarlos porque el feed de WordPress ya publica el archivo completo y ya persiste el guid mapeado:
solo falta emitirlo **literal** e importar el episodio que falta.

## What Changes

- **Emisión literal del guid**: un filtro propio sobre `the_guid` con prioridad **posterior** a
  `esc_url` (prioridad > 10) que, **solo** dentro del feed del podcast, devuelve el guid persistido
  escapado para XML (`esc_html`), **no** como URL (`esc_url`). Fuera del feed, el valor de entrada se
  conserva intacto. Resultado: los 849 items emiten el mismo guid que Anchor (UUID desnudo o URL,
  verbatim).
- **Importador idempotente del episodio ausente**: un subcomando WP-CLI
  `wp atareao podcast-guid-import-missing [--dry-run]` que lee el RSS de Anchor, detecta los episodios
  sin homólogo en WordPress (por guid y por número) y crea el post `podcast` con sus metadatos
  (`number`, `season`, `duration`, `mp3-url`, `anchor-guid`). Con `--dry-run` no escribe nada; una
  segunda pasada inserta **0**.
- **Sin cambios de producto**: no se migra el feed, no se toca `<itunes:new-feed-url>`, no se altera
  el feed principal `/feed/` ni la identidad ya persistida de los episodios existentes.

## Capabilities

### New Capabilities

- `podcast-guid`: el **importador** del episodio ausente del origen — un subcomando WP-CLI
  idempotente que reconcilia el archivo de WordPress con el RSS de Anchor creando los episodios que
  falten, con sus metadatos, sin duplicar los existentes.

### Modified Capabilities

- `podcast-feed`: la **emisión del `<guid>`** pasa a ser literal (el valor persistido, escapado para
  XML) en lugar de quedar convertido en URL por el escape del núcleo.

## Impact

- **Ficheros previstos** (solo tras aprobación):
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php` — nuevo callback del
    filtro `the_guid` a prioridad > 10, acotado a `isPodcastFeed()`.
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-guid.php` — nuevo subcomando
    `wp atareao podcast-guid-import-missing` con `--dry-run`, registrado junto a `podcast-guid-map`.
  - `tests/` — arneses de paridad de guid (emisión literal) e importador (detección, creación,
    idempotencia, `--dry-run`).
- **Depende de** `podcast-feed-soberania` (no archivado aún): de él heredan el archivo completo, el
  metadato `anchor-guid` y el mapeo determinista. El delta de `podcast-feed` de este change se escribe
  como **ADDED** porque el requisito de emisión del guid del episodio todavía no vive en
  `openspec/specs/podcast-feed/spec.md`.
- **Sin cambios** en el feed principal `/feed/`, en las URLs del feed, en los adjuntos y en los
  guids ya persistidos (solo cambia **cómo se emiten**).

## Preguntas abiertas (a resolver antes de implementar)

1. **Formato del metadato `duration`**: Anchor declara `00:22:04` (hh:mm:ss) para el 386, pero un
   episodio hermano (ID 16216) tiene `duration=01:07` (mm:ss). ¿Se guarda el valor de Anchor
   **verbatim**, se **normaliza** al formato del sitio (`mm:ss` cuando < 1 h) o se adopta otro? La
   decisión cambia el valor escrito por el importador y el escenario de la spec.
2. **Estado de publicación del episodio importado**: ¿se crea como `publish` (visible y en el feed de
   inmediato, coherente con Anchor) o como `draft` para revisión manual antes de publicarlo?
3. **Autor y términos del post importado**: ¿qué autor y qué términos de taxonomía (categoría/serie)
   recibe el episodio 386? ¿Se heredan del episodio hermano 385 o se dejan sin asignar?
4. **Fuente del RSS de Anchor**: ¿se reutiliza la URL ya configurada por `PodcastGuid` (con su caché de
   índice) o el subcomando acepta una URL/fichero como argumento opcional para pruebas?

## Fuera de alcance (follow-ups)

- **Reimportar** episodios existentes o corregir sus metadatos: el importador solo **crea** los que
  faltan.
- **Backfill de transcripciones** del episodio importado: el 386 no tiene transcript en Anchor.
- **La migración efectiva** del feed y el `<itunes:new-feed-url>`: siguen fuera, como en
  `podcast-feed-soberania`.
