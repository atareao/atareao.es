# podcast-feed Delta

## Purpose

Acotar el trabajo de la composición del feed por dos vías: (a) el presupuesto de red deja de
comprobarse solo entre episodios y limita **cada llamada en vuelo**; y (b) el tamaño **cacheado** de un
episodio se consulta **antes** de cualquier resolución (local o remota), de modo que una caché llena
evite el caro lookup de adjunto local (`attachment_url_to_postid`) y toda petición de red.

## MODIFIED Requirements

### Requirement: Episodio con audio y numeración

Cada `<item>` de un podcast **publicado y público** SHALL declarar un **`<enclosure>`** con `url` igual al valor del metadato `mp3-url` del episodio, `type` igual al media type del audio (`audio/mpeg` para un fichero `.mp3`) y `length` igual al tamaño del fichero en bytes. El tamaño SHALL resolverse en este orden: **(1)** el tamaño **cacheado y vigente** del episodio, consultado **primero** cuando la llamada identifica el episodio, de modo que un acierto de caché evite todo trabajo posterior (ni lookup de adjunto local ni petición de red); **(2)** el adjunto local cuando la URL resuelve a un adjunto de la biblioteca legible; **(3)** el tamaño declarado por el servidor al pedir la URL con una petición `HEAD` que **sigue las redirecciones**; **(4)** el total derivado de una petición `GET` con `Range: bytes=0-0`, leyendo el total del `Content-Range`; **(5)** el valor neutro `0` cuando ninguna de las anteriores lo determine. Todo tamaño resuelto —**local o remoto**— SHALL **cachearse** junto al episodio para no repetir el trabajo en cada composición del feed: un acierto con caducidad larga y un fallo con caducidad corta, de modo que un tamaño no determinable se reintente más tarde. Cada composición del feed SHALL respetar un **presupuesto de red acotado** —un tiempo máximo por composición, un número acotado de saltos de redirección por petición y un tamaño máximo de cuerpo descargado en la reserva por rango—, de forma que los episodios no resueltos a tiempo emitan `0` y se resuelvan en composiciones posteriores. El presupuesto SHALL acotar **cada llamada en vuelo**, no solo la decisión entre episodios: la primera petición remota de la composición SHALL sellar una **fecha límite** igual al instante de inicio más el presupuesto, cada petición HTTP SHALL limitar su `timeout` al tiempo restante hasta esa fecha límite (sin exceder el timeout por petición), la cadena de redirecciones SHALL cortarse al alcanzarla y SHALL NOT iniciarse ninguna petición remota una vez agotada; así una única resolución remota SHALL NOT poder bloquear la composición más allá del presupuesto. Toda petición SHALL dirigirse **solo** a una URL `http`/`https` pública: SHALL validarse **cada salto** de redirección y SHALL rechazarse cualquier URL cuyo host sea privado, reservado, loopback o link-local (guarda SSRF), además de habilitar el rechazo de URLs inseguras del propio cliente HTTP. Un error de red —`is_wp_error`, timeout, código inesperado o ausencia de longitud— SHALL NOT romper el feed: el `<enclosure>` SHALL emitirse con `length` igual a `0` y el episodio SHALL seguir siendo suscribible. El `<enclosure>` SHALL emitirse **una sola vez** por episodio y SHALL NOT duplicar el que el núcleo pudiera generar.

Cada `<item>` SHALL declarar la numeración del episodio cuando exista: `itunes:episode` con el valor del metadato `number`, `itunes:season` con el valor del metadato `season`, y los equivalentes `podcast:episode` y `podcast:season` del namespace de Podcasting 2.0. SHALL declarar `itunes:duration` cuando la duración se conozca.

Un podcast **sin `mp3-url`** SHALL NOT declarar `<enclosure>` ni elementos de episodio —no es un episodio reproducible— y SHALL NOT romper el XML del feed.

#### Scenario: El episodio declara su audio

- **WHEN** un podcast publicado tiene el metadato `mp3-url` y su tamaño puede resolverse
- **THEN** su `<item>` declara un `<enclosure>` con esa URL, el media type del audio y el tamaño del fichero

#### Scenario: Un tamaño cacheado evita el trabajo local y de red

- **WHEN** el tamaño de un episodio está cacheado y vigente y se compone el feed
- **THEN** la composición emite ese tamaño **sin** consultar la base de datos buscando un adjunto local (`attachment_url_to_postid`) **ni** realizar peticiones de red para ese episodio

#### Scenario: Tamaño resuelto desde el adjunto local

- **WHEN** la URL del audio resuelve a un adjunto legible de la biblioteca y no hay tamaño cacheado
- **THEN** `length` es el tamaño del fichero local en bytes, no se realiza ninguna petición de red y el resultado se cachea junto al episodio

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

#### Scenario: La fecha límite acota una única resolución remota

- **WHEN** la resolución de un único episodio encadena redirecciones y reservas que, con el timeout por petición sin acotar, excederían el presupuesto de red de la composición
- **THEN** el tiempo total de esa resolución respeta la fecha límite sellada por el presupuesto (cada petición se limita al tiempo restante y no se inicia ninguna tras agotarse), de modo que la composición termina en un tiempo acotado y las resoluciones restantes quedan para composiciones posteriores

#### Scenario: Una composición con caché llena no bloquea

- **WHEN** se compone el feed con muchos episodios cuyo tamaño ya está cacheado
- **THEN** el tiempo total de la composición es del orden de la lectura de los metadatos (no escala con una consulta de adjunto local por episodio) y no se realiza ninguna petición de red
