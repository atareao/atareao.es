# podcast-feed Delta

## MODIFIED Requirements

### Requirement: Namespace y metadatos del feed del podcast

El sistema SHALL servir el feed del podcast en `GET /podcast/feed/` con `Content-Type:
application/rss+xml; charset=UTF-8` y un cuerpo XML **bien formado**. El elemento raíz `<rss>` SHALL
declarar el namespace `podcast` con el valor `https://podcastindex.org/namespace/1.0` y el namespace
`itunes` con el valor `http://www.itunes.com/dtds/podcast-1.0.dtd`.

El `<channel>` SHALL declarar `<title>`, `<link>`, `<description>`, `<language>`, `atom:link` con
`rel="self"` y `type="application/rss+xml"`, `itunes:author`, `itunes:explicit` con un valor booleano
explícito, `itunes:type` (con `episodic` como valor por defecto y configurable), **`<copyright>`** con
el titular del programa y el año, al menos un `itunes:category` de la **taxonomía de Apple**,
`itunes:owner` con **nombre y correo no vacíos**, `itunes:image` con la URL **absoluta** de la portada
del programa, `podcast:locked` con valor `yes` y el correo del propietario, y un **`podcast:guid`**
estable.

El `podcast:guid` SHALL derivarse de forma **determinista** de la identidad del programa y SHALL NOT
cambiar entre reconstrucciones del feed ni entre versiones del plugin: un directorio que ya registró
el programa dejaría de reconocerlo si su guid cambiara. Los valores configurables SHALL proceder de
ajustes del sitio con un valor por defecto razonable.

#### Scenario: El feed declara los namespaces de podcasting

- **WHEN** se parsea el elemento raíz del feed del podcast
- **THEN** declara el namespace `podcast` con el valor `https://podcastindex.org/namespace/1.0` y el namespace `itunes`

#### Scenario: El canal declara los metadatos que exigen los directorios

- **WHEN** se inspecciona el `<channel>`
- **THEN** declara título, descripción, idioma, autor, tipo, copyright, al menos una categoría, propietario con nombre y correo, la portada y el enlace propio del feed

#### Scenario: El canal declara el copyright

- **WHEN** se inspecciona el `<channel>`
- **THEN** declara `<copyright>` con el titular del programa y el año en curso

#### Scenario: El tipo por defecto es episodic

- **WHEN** el ajuste del tipo de programa no se ha configurado
- **THEN** `itunes:type` vale `episodic` y no `serial`

#### Scenario: El propietario declara nombre y correo

- **WHEN** se inspecciona `itunes:owner`
- **THEN** declara `itunes:name` e `itunes:email` con valores no vacíos

#### Scenario: La portada es una URL absoluta

- **WHEN** se inspecciona `itunes:image`
- **THEN** su URL es absoluta y usa `https`

#### Scenario: El feed está bloqueado con propietario

- **WHEN** se inspecciona `podcast:locked`
- **THEN** su valor es `yes` y declara el correo del propietario

#### Scenario: El guid del programa es estable

- **WHEN** se compone el feed dos veces con la misma identidad de programa
- **THEN** el valor de `podcast:guid` es idéntico en ambas composiciones

#### Scenario: El feed es XML bien formado

- **WHEN** se valida el cuerpo del feed como XML
- **THEN** es bien formado y sus elementos con prefijo usan namespaces declarados

#### Scenario: El media type declara el conjunto de caracteres una sola vez

- **WHEN** se inspecciona la cabecera `Content-Type` con la que se sirve el feed
- **THEN** declara `application/rss+xml` y **una sola** aparición de `charset`, porque el núcleo ya añade el suyo

## ADDED Requirements

### Requirement: Archivo completo del podcast

El feed del podcast SHALL publicar **todas** las entradas del CPT `podcast` publicadas y públicas
—**849** en el archivo actual—, sin el techo de **100** que impone `posts_per_rss`. El límite de items
SHALL levantarse **solo** en el feed del podcast: el feed principal `/feed/` y los feeds ligados al
archivo de otros tipos de contenido SHALL conservar su límite. El orden de los items SHALL ser el de
WordPress (fecha descendente), que coincide con el del feed de Anchor.

#### Scenario: El feed publica el archivo completo

- **WHEN** existen 849 entradas publicadas y públicas del CPT `podcast` y se solicita el feed del podcast
- **THEN** el cuerpo declara 849 `<item>` y no 100

#### Scenario: El techo de 100 no aplica al feed del podcast

- **WHEN** `posts_per_rss` vale 100 y se compone el feed del podcast
- **THEN** el número de items es el del archivo completo, no 100

#### Scenario: El feed principal conserva su límite

- **WHEN** se solicita el feed principal `/feed/`
- **THEN** sigue limitado por `posts_per_rss` como antes de este change

#### Scenario: El orden es cronológico descendente

- **WHEN** se comparan las fechas de los items del feed del podcast
- **THEN** están ordenados de más reciente a más antiguo, igual que en el feed de Anchor

### Requirement: Metadatos completos del episodio

Cada `<item>` de un episodio publicado y público SHALL declarar `itunes:duration` —derivada del propio
audio cuando el metadato de duración falte—, `itunes:image` con la URL **absoluta** de la portada del
episodio y un `<description>` con las **notas completas** del episodio, no un extracto truncado.
Cuando el episodio no tenga portada propia, `itunes:image` SHALL usar la portada del programa, de modo
que **todos** los items declaren imagen.

#### Scenario: La duración se declara en todos los items

- **WHEN** se inspeccionan los items del feed del podcast
- **THEN** el 100 % declara `itunes:duration` con un valor no vacío

#### Scenario: La imagen del episodio es absoluta y https

- **WHEN** se inspecciona `itunes:image` de un item
- **THEN** su URL es absoluta y usa `https`

#### Scenario: Sin portada propia se usa la del programa

- **WHEN** un episodio no tiene portada propia
- **THEN** su `<item>` declara `itunes:image` con la portada del programa

#### Scenario: La descripción lleva las notas completas

- **WHEN** un episodio tiene notas completas en su contenido
- **THEN** el `<description>` del item las reproduce íntegras y no un extracto truncado

### Requirement: Guid del episodio compatible con el origen

Cada `<item>` SHALL declarar `<guid isPermaLink="false">` con el **mismo UUID** que el item homólogo del
feed de Anchor. El emparejamiento SHALL ser **determinista** —identidad del fichero de audio como clave
primaria y número deducido del título como respaldo, la misma clave que usa la sincronización de audio—
y su resultado SHALL **persistirse en el episodio** como única fuente de verdad del guid, de modo que el
guid no dependa del orden de composición del feed. El sistema SHALL NOT emitir dos items con el mismo
guid. Un episodio cuyo UUID no se resuelva SHALL conservar un guid **estable** propio y SHALL reportarse,
sin inventar un valor que no exista en el origen.

#### Scenario: Diez episodios al azar coinciden con Anchor

- **WHEN** se eligen 10 episodios al azar y se comparan con sus items homólogos de Anchor
- **THEN** el `<guid>` del feed de WordPress coincide con el de Anchor en los 10

#### Scenario: El guid no es un permalink

- **WHEN** se inspecciona el `<guid>` de un item
- **THEN** declara `isPermaLink="false"` y su valor es el UUID del episodio, no su permalink

#### Scenario: El mapeo es estable entre composiciones

- **WHEN** se compone el feed dos veces
- **THEN** el `<guid>` de cada episodio es idéntico en ambas composiciones

#### Scenario: No hay guids duplicados

- **WHEN** se inspeccionan todos los items del feed
- **THEN** no hay dos items con el mismo `<guid>`

#### Scenario: Un episodio sin par no inventa un guid

- **WHEN** un episodio no se puede emparejar con ningún item de Anchor
- **THEN** conserva un guid estable propio y el episodio se reporta, sin emitir un UUID que no exista en el origen

### Requirement: Capítulos del episodio

Cuando un episodio tenga un documento de capítulos asociado, su `<item>` SHALL declarar
**`<podcast:chapters>`** con la URL **absoluta** del documento y el tipo de medio de capítulos de
Podcasting 2.0 (`application/json+chapters`). La asociación SHALL establecerse con un metadato del
episodio, única fuente de verdad. El sistema SHALL NOT declarar capítulos cuyo documento no exista o no
sea accesible públicamente.

#### Scenario: Con capítulos se declara el elemento

- **WHEN** un episodio publicado tiene un documento de capítulos asociado y accesible
- **THEN** su `<item>` declara `<podcast:chapters>` con la URL absoluta del documento y el tipo `application/json+chapters`

#### Scenario: Sin capítulos no se declara nada

- **WHEN** un episodio publicado no tiene documento de capítulos asociado
- **THEN** su `<item>` no declara `<podcast:chapters>`

#### Scenario: No se declara un documento inexistente

- **WHEN** el metadato de capítulos apunta a un documento que no existe o no es accesible
- **THEN** el sistema no declara `<podcast:chapters>` para ese episodio

### Requirement: Ausencia de redirección de feed hasta la migración

El feed del podcast SHALL NOT declarar **`<itunes:new-feed-url>`** ni redirigir a otro feed mientras el
titular no haya decidido migrar. El feed SHALL seguir sirviéndose en la misma URL y el feed principal
`/feed/` SHALL permanecer inalterado por este change.

#### Scenario: El feed no declara un nuevo destino

- **WHEN** se inspecciona el `<channel>` del feed del podcast
- **THEN** no declara `<itunes:new-feed-url>`

#### Scenario: El feed no redirige

- **WHEN** un cliente solicita el feed del podcast
- **THEN** la respuesta es el propio feed (200) y no una redirección a otro feed

#### Scenario: El feed principal no cambia

- **WHEN** se solicita el feed principal `/feed/`
- **THEN** su composición y sus elementos son los mismos que antes de este change
