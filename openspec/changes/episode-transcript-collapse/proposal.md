# Proposal: Plegar la transcripción del episodio en un desplegable

## Why

En la ficha del episodio, la transcripción es un **muro de texto**: p. ej. el episodio 850 publica
**236 `<p>`** (~23 700 caracteres de HTML) seguidas al final de la página. Domina la ficha, empuja la
navegación y la lectura, y como el SRT de Whisper viene sin puntuar, el golpe visual es aún peor.

El texto debe seguir en el HTML **indexable** (lo especifica el requisito «Transcripción visible e
indexable en la página del episodio»), así que no se trata de ocultarlo, sino de **presentarlo
plegado**.

Issue de entrada: **#246**.

## What Changes

- `\Atareao\PodcastFeed::transcriptHtml()` SHALL envolver el texto en un **`<details>`** nativo con su
  **`<summary>`** («Transcripción»), **plegado por defecto**: el texto permanece en el HTML (indexable)
  pero no domina la página.
- **Tipografía/espaciado** de los párrafos de la transcripción (CSS), y estados de foco/expansión
  accesibles.
- Contrato estable: mismas clases `episode-transcript` / `episode-transcript__title`, sin cambiar el
  contrato del feed ni la resolución del vídeo (#214/#240).

## Fuera de alcance

- La **calidad del texto** (puntuación y mayúsculas) es otro trabajo: issue **#247** (post-proceso del
  SRT en el pipeline).

## Dependencia de consolidación

El requisito vive hoy en el change **abierto** `podcast-feed-soberania` (`specs/podcast-transcript-page/`),
aún sin archivar. Este cambio **MODIFICA** ese requisito, por lo que **no se podrá archivar hasta que
`podcast-feed-soberania` se archive primero** (`openspec validate` lo advierte con un INFO). Secuencia:
consolidar `podcast-feed-soberania` → archivar `episode-transcript-collapse`.

## Impact

- **Código:** `includes/class-podcast-feed.php` (`transcriptHtml()`), CSS de la transcripción.
- **Tests:** `tests/podcast-transcripts/` o nuevo arnés de la página; el escenario «plegada».
- **Despliegue:** fichero del plugin (y CSS). Aditivo y reversible.
