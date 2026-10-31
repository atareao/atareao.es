# Podcast Feed Delta

## Purpose

Resolver el tamaño real del audio remoto que hoy sale como `0` en los 100 episodios del feed: el orden de resolución (adjunto local → `HEAD` con redirecciones → `GET` con `Range` → `0`), la caché del tamaño en el metadato del episodio, el presupuesto de red acotado por composición, la guarda SSRF y la garantía de que un fallo de red nunca rompe el feed.

## MODIFIED Requirements

### Requirement: Episodio con audio y numeración

Cada `<item>` de un podcast **publicado y público** SHALL declarar un **`<enclosure>`** con `url` igual al valor del metadato `mp3-url` del episodio, `type` igual al media type del audio (`audio/mpeg` para un fichero `.mp3`) y `length` igual al tamaño del fichero en bytes. El tamaño SHALL resolverse en este orden: **(1)** el adjunto local cuando la URL resuelve a un adjunto de la biblioteca legible; **(2)** el tamaño declarado por el servidor al pedir la URL con una petición `HEAD` que **sigue las redirecciones**; **(3)** el total derivado de una petición `GET` con `Range: bytes=0-0`, leyendo el total del `Content-Range`; **(4)** el valor neutro `0` cuando ninguna de las anteriores lo determine. Cuando el tamaño se resuelva por red, el resultado SHALL **cachearse** junto al episodio para no repetir la petición en cada composición del feed: un acierto con caducidad larga y un fallo con caducidad corta, de modo que un tamaño no determinable se reintente más tarde. Cada composición del feed SHALL respetar un **presupuesto de red acotado** —un tiempo máximo por composición y un número acotado de saltos de redirección por petición—, de forma que los episodios no resueltos a tiempo emitan `0` y se resuelvan en composiciones posteriores. Toda petición SHALL dirigirse **solo** a una URL `http`/`https` pública: SHALL validarse **cada salto** de redirección y SHALL rechazarse cualquier URL cuyo host sea privado, reservado, loopback o link-local (guarda SSRF), además de habilitar el rechazo de URLs inseguras del propio cliente HTTP. Un error de red —`is_wp_error`, timeout, código inesperado o ausencia de longitud— SHALL NOT romper el feed: el `<enclosure>` SHALL emitirse con `length` igual a `0` y el episodio SHALL seguir siendo suscribible. El `<enclosure>` SHALL emitirse **una sola vez** por episodio y SHALL NOT duplicar el que el núcleo pudiera generar.

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
