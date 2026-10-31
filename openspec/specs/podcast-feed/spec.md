# podcast-feed Specification

## Purpose
El contrato del feed de podcast que publica el sitio: los namespaces `podcast:` e `itunes:` y los
metadatos de canal que exigen los directorios, cada episodio con su `<enclosure>` y su numeración, la
transcripción SRT asociada al episodio, las condiciones de alcance y validez del feed, y la tolerancia de
los callbacks de emisión a la convención de llamada del núcleo de WordPress (que entrega la cadena vacía a
una acción disparada sin argumentos).

## Requirements

### Requirement: Namespace y metadatos del feed del podcast

El sistema SHALL servir el feed del podcast en `GET /podcast/feed/` con `Content-Type:
application/rss+xml; charset=UTF-8` y un cuerpo XML **bien formado**. El elemento raíz `<rss>` SHALL
declarar el namespace `podcast` con el valor `https://podcastindex.org/namespace/1.0` y el namespace
`itunes` con el valor `http://www.itunes.com/dtds/podcast-1.0.dtd`.

El `<channel>` SHALL declarar `<title>`, `<link>`, `<description>`, `<language>`, `atom:link` con
`rel="self"` y `type="application/rss+xml"`, `itunes:author`, `itunes:explicit` con un valor booleano
explícito, `itunes:type` (con `serial` como valor por defecto y configurable), al menos un
`itunes:category`, `itunes:owner` con nombre y correo, `itunes:image` con la URL **absoluta** de la
portada del programa, `podcast:locked` con valor `yes` y el correo del propietario, y un
**`podcast:guid`** estable.

El `podcast:guid` SHALL derivarse de forma **determinista** de la identidad del programa y SHALL NOT
cambiar entre reconstrucciones del feed ni entre versiones del plugin: un directorio que ya registró
el programa dejaría de reconocerlo si su guid cambiara. Los valores configurables SHALL proceder de
ajustes del sitio con un valor por defecto razonable.

#### Scenario: El feed declara los namespaces de podcasting

- **WHEN** se parsea el elemento raíz del feed del podcast
- **THEN** declara el namespace `podcast` con el valor `https://podcastindex.org/namespace/1.0` y el namespace `itunes`

#### Scenario: El canal declara los metadatos que exigen los directorios

- **WHEN** se inspecciona el `<channel>`
- **THEN** declara título, descripción, idioma, autor, tipo, al menos una categoría, propietario con nombre y correo, la portada y el enlace propio del feed

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

### Requirement: Episodio con audio y numeración

Cada `<item>` de un podcast **publicado y público** SHALL declarar un **`<enclosure>`** con `url` igual al valor del metadato `mp3-url` del episodio, `type` igual al media type del audio (`audio/mpeg` para un fichero `.mp3`) y `length` igual al tamaño del fichero en bytes. El tamaño SHALL resolverse en este orden: **(1)** el adjunto local cuando la URL resuelve a un adjunto de la biblioteca legible; **(2)** el tamaño declarado por el servidor al pedir la URL con una petición `HEAD` que **sigue las redirecciones**; **(3)** el total derivado de una petición `GET` con `Range: bytes=0-0`, leyendo el total del `Content-Range`; **(4)** el valor neutro `0` cuando ninguna de las anteriores lo determine. Cuando el tamaño se resuelva por red, el resultado SHALL **cachearse** junto al episodio para no repetir la petición en cada composición del feed: un acierto con caducidad larga y un fallo con caducidad corta, de modo que un tamaño no determinable se reintente más tarde. Cada composición del feed SHALL respetar un **presupuesto de red acotado** —un tiempo máximo por composición, un número acotado de saltos de redirección por petición y un tamaño máximo de cuerpo descargado en la reserva por rango—, de forma que los episodios no resueltos a tiempo emitan `0` y se resuelvan en composiciones posteriores. Toda petición SHALL dirigirse **solo** a una URL `http`/`https` pública: SHALL validarse **cada salto** de redirección y SHALL rechazarse cualquier URL cuyo host sea privado, reservado, loopback o link-local (guarda SSRF), además de habilitar el rechazo de URLs inseguras del propio cliente HTTP. Un error de red —`is_wp_error`, timeout, código inesperado o ausencia de longitud— SHALL NOT romper el feed: el `<enclosure>` SHALL emitirse con `length` igual a `0` y el episodio SHALL seguir siendo suscribible. El `<enclosure>` SHALL emitirse **una sola vez** por episodio y SHALL NOT duplicar el que el núcleo pudiera generar.

Cada `<item>` SHALL declarar la numeración del episodio cuando exista: `itunes:episode` con el valor del metadato `number`, `itunes:season` con el valor del metadato `season`, y los equivalentes `podcast:episode` y `podcast:season` del namespace de Podcasting 2.0. SHALL declarar `itunes:duration` cuando la duración se conozca.

Un podcast **sin `mp3-url`** SHALL NOT declarar `<enclosure>` ni elementos de episodio —no es un episodio reproducible— y SHALL NOT romper el XML del feed.

#### Scenario: El episodio declara su audio

- **WHEN** un podcast publicado tiene el metadato `mp3-url` y su tamaño puede resolverse
- **THEN** su `<item>` declara un `<enclosure>` con esa URL, el media type del audio y el tamaño del fichero

#### Scenario: Tamaño resuelto desde el adjunto local

- **WHEN** la URL del audio resuelve a un adjunto legible de la biblioteca
- **THEN** `length` es el tamaño del fichero local en bytes y no se realiza ninguna petición de red

#### Scenario: Tamaño resuelto con `HEAD` siguiendo redirecciones

- **WHEN** la URL del audio es remota y responde a `HEAD` con `Content-Length` tras una o más redirecciones
- **THEN** `length` es el tamaño declarado y el resultado se cachea junto al episodio

#### Scenario: Tamaño resuelto con `Range` cuando `HEAD` no lo declara

- **WHEN** el `HEAD` no declara una longitud utilizable
- **THEN** se emite una petición `GET` con `Range: bytes=0-0` y `length` es el total del `Content-Range`

#### Scenario: La reserva acota el cuerpo descargado

- **WHEN** la reserva `Range` recibe una respuesta cuyo cuerpo podría exceder lo pedido
- **THEN** la petición declara un tamaño máximo de descarga, de modo que el cuerpo nunca se carga entero en memoria y el total se lee del `Content-Range`

#### Scenario: Un fallo de red no rompe el feed

- **WHEN** la resolución remota falla (error de red, timeout o código inesperado)
- **THEN** el `<enclosure>` se emite con `length` igual a `0` y el feed sigue siendo XML válido

#### Scenario: La resolución remota se cachea y no se repite

- **WHEN** el tamaño de un episodio ya está resuelto en su metadato y vigente
- **THEN** una nueva composición del feed NO realiza ninguna petición de red para ese episodio

#### Scenario: Se rechaza un host interno (guarda SSRF)

- **WHEN** la URL del audio —o un salto de su redirección— apunta a un host privado, reservado, loopback o link-local, o usa un esquema distinto de `http`/`https`
- **THEN** no se contacta ese host y `length` es `0`

#### Scenario: Un único enclosure por episodio

- **WHEN** se inspecciona el `<item>` de un episodio
- **THEN** contiene exactamente un elemento `<enclosure>`

#### Scenario: El episodio declara su numeración

- **WHEN** un podcast tiene los metadatos `number` y `season`
- **THEN** su `<item>` declara `itunes:episode`/`itunes:season` y `podcast:episode`/`podcast:season` con esos valores

#### Scenario: Sin audio no se declara un episodio

- **WHEN** un podcast publicado no tiene el metadato `mp3-url`
- **THEN** su `<item>` no declara `<enclosure>` ni elementos de episodio y el feed sigue siendo XML válido

#### Scenario: Tamaño desconocido no rompe el feed

- **WHEN** no se puede determinar el tamaño del fichero de audio por ninguna de las vías
- **THEN** el `<enclosure>` se emite con `length` igual a `0` y el episodio sigue siendo suscribible

### Requirement: Transcripción SRT del episodio

Cuando un episodio tenga una **transcripción** asociada, su `<item>` SHALL declarar un
**`<podcast:transcript>`** con `url` igual a la URL **absoluta** del fichero SRT, `type` igual al media
type de SubRip (`application/x-subrip`) y `language` igual al idioma del programa. El fichero SHALL
ser un adjunto de la biblioteca de medios y el sistema SHALL NOT declarar una transcripción cuyo
fichero no exista o no sea accesible públicamente.

La **asociación** entre el episodio y su transcripción SHALL establecerse con un metadato del episodio,
editable desde la ficha del podcast, que sea su única fuente de verdad. La URL declarada SHALL ser
accesible **sin autenticación** y **alcanzable por User-Agents de cliente HTTP**, porque quien descarga
una transcripción es una máquina (un validador de directorios, un reproductor o un indexador) y no un
navegador.

#### Scenario: El episodio declara su transcripción

- **WHEN** un podcast publicado tiene un SRT asociado
- **THEN** su `<item>` declara `<podcast:transcript>` con la URL absoluta del SRT, `type` `application/x-subrip` y el idioma del programa

#### Scenario: Sin transcripción no se declara ninguna

- **WHEN** un podcast publicado no tiene transcripción asociada
- **THEN** su `<item>` no declara `<podcast:transcript>`

#### Scenario: No se declara una transcripción inexistente

- **WHEN** el metadato de transcripción apunta a un fichero que no existe o no es accesible
- **THEN** el sistema no declara `<podcast:transcript>` para ese episodio

#### Scenario: La transcripción es alcanzable por máquinas

- **WHEN** un cliente con User-Agent de cliente HTTP solicita la URL del SRT sin sesión
- **THEN** el sistema sirve el fichero y no cierra la conexión

### Requirement: Alcance y no interferencia del feed

El feed del podcast SHALL incluir únicamente podcasts **publicados y públicos**, ordenados por fecha
descendente, y SHALL NOT exponer borradores, contenido privado, contenido protegido por contraseña ni
datos internos (rutas del servidor, metadatos con prefijo `_`, datos de usuario). El **feed principal**
(`/feed/`), los feeds ligados al archivo de otros tipos de contenido y los feeds de comentarios SHALL
permanecer **inalterados** por este change.

La ruta del feed del podcast SHALL estar **exenta del bloqueo por User-Agent** del proxy inverso,
porque quien consume un feed de podcast es una máquina y el cierre de conexión es, para ella,
indistinguible de «este feed no existe».

#### Scenario: Solo podcasts publicados y públicos

- **WHEN** existen borradores, podcasts privados o protegidos por contraseña
- **THEN** no aparecen en el feed del podcast

#### Scenario: El feed principal no cambia

- **WHEN** se solicita el feed principal `/feed/`
- **THEN** su composición y sus elementos son los mismos que antes de este change, sin vocabulario de podcasting

#### Scenario: Sin datos internos

- **WHEN** se inspecciona el feed del podcast
- **THEN** no aparecen rutas del servidor, metadatos internos ni datos de usuario

#### Scenario: El feed es alcanzable por clientes HTTP

- **WHEN** un cliente con User-Agent de cliente HTTP (`curl`, `wget`, `python-requests`) solicita el feed del podcast
- **THEN** el proxy inverso lo sirve y no cierra la conexión

### Requirement: Tolerancia de los callbacks a la convención de llamada del núcleo

Todo callback que el módulo registre en un punto de extensión del núcleo SHALL **tolerar la convención de
llamada de `do_action()`**. Cuando una acción se dispara **sin argumentos** —es el caso de `rss2_ns`,
`rss2_head` y `rss2_item` en la plantilla del feed—, el núcleo rellena la lista de argumentos con una
**cadena vacía** (`wp-includes/plugin.php`), de modo que el callback recibe `''` aunque la acción no
declare ningún parámetro. El sistema SHALL NOT permitir que ese valor provoque un **error de tipo**: SHALL
normalizarlo y SHALL seguir emitiendo el episodio que el bucle del feed tiene en curso. Cada callback SHALL
declarar además el **número de argumentos que de verdad acepta**, para que el núcleo no le entregue
valores que no espera.

Un error de tipo en un callback de emisión SHALL NOT truncar el documento: el feed SHALL seguir siendo
XML bien formado y SHALL declarar **todos** los episodios públicos que le correspondan.

#### Scenario: La acción sin argumentos no rompe la emisión del episodio

- **WHEN** se dispara `rss2_item` sin argumentos, como hace el núcleo
- **THEN** el callback no produce ningún error y emite el `<item>` del episodio en curso

#### Scenario: La lista de argumentos vacía del núcleo llega como cadena vacía

- **WHEN** el núcleo dispara una acción del feed sin argumentos
- **THEN** el callback la recibe como una cadena vacía y la normaliza en vez de fallar

#### Scenario: Un tercero puede disparar la acción con el identificador del episodio

- **WHEN** se dispara `rss2_item` con el identificador entero de un episodio publicado
- **THEN** se emiten los elementos de ese episodio

#### Scenario: El feed declara todos los episodios públicos

- **WHEN** hay varios episodios publicados y públicos y se solicita el feed
- **THEN** el cuerpo declara un `<item>` completo por episodio y cierra `</channel>` y `</rss>`
