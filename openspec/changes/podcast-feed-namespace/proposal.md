# podcast-feed-namespace

## Why

El feed del podcast se sirve hoy en `/podcast/feed/` como un **RSS 2.0 genérico de WordPress**: sin el
namespace de Podcasting 2.0, sin los elementos `itunes:` que exigen los directorios, sin `<enclosure>`
—el audio vive en el metadato `mp3-url`, que el feed **no publica**— y sin transcripciones. Para un
lector de podcasts o un directorio, ese feed **no es un podcast**: no hay episodios, porque no hay
audio adjunto, y sin audio no hay nada que suscribir ni que indexar.

Y hay material que nadie puede ver: los **SRT** que produce el pipeline de transcripción del podcast
son hoy la única pieza pública del programa que no tiene ninguna vía en la web. Un reproductor que
quisiera mostrar subtítulos, o un buscador que quisiera leer el texto hablado, no encuentra nada.

## What Changes

- El feed declara los namespaces **`podcast:`** (Podcasting 2.0) e **`itunes:`**, y los **metadatos de
  canal** que exigen los directorios (título, descripción, idioma, autor, portada, categoría,
  propietario, `podcast:locked`, `podcast:guid`).
- Cada episodio declara su **`<enclosure>`** a partir del metadato `mp3-url`, con su tipo y su tamaño,
  más la **numeración** (`itunes:episode`/`itunes:season`, `podcast:episode`/`podcast:season`) y la
  **duración** cuando se conozca.
- Cada episodio con **transcripción** declara **`<podcast:transcript>`** apuntando a su **SRT**.
- Se habilita **adjuntar el SRT** al episodio por **dos vías** —elegir o subir el adjunto
  desde la ficha del podcast, y automatizarlo desde el pipeline— sobre un **único contrato**:
  un adjunto de la biblioteca de medios y un metadato del episodio.
- El feed del podcast queda **exento del bloqueo por User-Agent** del proxy inverso.
- El **feed principal `/feed/` no cambia**.

### Decisiones de alcance

**Se extiende el feed existente `/podcast/feed/`, no se crea uno nuevo.** Es la URL que ya anuncia el
pie de página del tema y la que un suscriptor podría tener guardada: cambiar de dirección rompería a
quien ya la usa. La extensión se hace declarando los namespaces en el elemento raíz y añadiendo los
elementos de canal y de episodio en el punto que el núcleo de WordPress expone para ello.

**Las dos vías de carga del SRT, con una sola fuente de verdad.** El SRT es un adjunto de la
biblioteca de medios y se asocia al episodio por su metadato; **ese metadato es el único origen** de lo
que el feed publica, de modo que da igual cómo haya llegado el fichero. Se admiten y documentan **las
dos vías**: (a) **manual** —elegir un adjunto existente o subirlo desde la ficha del episodio— y
(b) **automatizada** —el pipeline sube el SRT y escribe el metadato—. El change fija el contrato y
ambas vías; cuál se use en cada episodio no cambia el feed.

**El feed principal no se toca.** La composición del feed principal (entradas y CPT públicos) está
definida por la capability `main-feed` y permanece igual; los elementos de podcast se emiten **solo**
en el feed del archivo de `podcast`, de modo que un feed de propósito general no arrastre vocabulario
de podcasting.

**Se declara `itunes:type` como `serial`.** El programa tiene temporadas y episodios numerados, que es
exactamente el caso en que un directorio debe listarlos en orden narrativo y no por fecha; el valor es
configurable pero el valor por defecto no es arbitrario.

**Fuera de alcance:** capítulos (`podcast:chapters`), `podcast:person`, `podcast:funding`,
`podcast:value`, las variantes VTT/JSON de transcripción y la sincronización con directorios.

## Capabilities

### New Capabilities

- `podcast-feed`: el contrato del feed de podcast —namespaces, metadatos de canal, episodios con audio
  y numeración, transcripciones SRT y las condiciones de alcance y validez del feed.

### Modified Capabilities

*(ninguna)*

## Impact

- **Spec:** nueva capability `podcast-feed` — 4 requisitos ADDED.
- **Código:** `wp-content/plugins/atareao-functionality/includes/` — un módulo del feed de podcast
  (namespaces, canal y episodios sobre el feed del archivo de `podcast`), el metadato de la
  transcripción y su caja en la ficha del episodio (`class-metaboxes.php`), los ajustes de portada y
  datos del programa (`class-theme-options.php`) y, para la vía automatizada, la receta del pipeline
  que sube el SRT y escribe el metadato.
- **Config:** `nginx/pro/nginx.conf` (exención de la ruta del feed en el mapa de rutas de máquina).
- **Tests:** `tests/test-valet.php`, `tests/wp-stubs.php`.
- **Sin dependencias nuevas. Sin cambios de esquema de datos. Sin migración.**
