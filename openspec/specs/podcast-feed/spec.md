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
conforme a Apple (**`true`** o **`false`**), `itunes:type` (con `serial` como valor por defecto y
configurable), al menos un `itunes:category`, `itunes:owner` con nombre y correo, `itunes:image` con
la URL **absoluta** de la portada del programa, `podcast:locked` con valor `yes` y el correo del
propietario, y un **`podcast:guid`** estable.

El valor de `itunes:explicit` SHALL derivarse del ajuste `atareao_podcast_explicit`, cuyo valor por
defecto SHALL ser `false`. La emisión SHALL normalizar los valores heredados para no romper ajustes ya
guardados: `yes` SHALL emitirse como `true`; `no` y `clean` SHALL emitirse como `false`; cualquier
otro valor SHALL emitirse como `false`. El elemento SHALL NOT contener nunca `no`, `yes`, `clean` ni
una cadena vacía.

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

#### Scenario: El valor por defecto de itunes:explicit es válido para Apple

- **WHEN** el ajuste `atareao_podcast_explicit` no está definido y se inspecciona `itunes:explicit`
- **THEN** su valor es `false`

#### Scenario: Los valores heredados se normalizan

- **WHEN** el ajuste vale `yes`, `no`, `clean` o cualquier otro valor
- **THEN** `itunes:explicit` se emite como `true` para `yes` y como `false` para el resto

#### Scenario: El feed completo pasa el validador

- **WHEN** se valida el feed del podcast con el W3C Feed Validator
- **THEN** no aparece el error «itunes:explicit must be "true" or "false"»

#### Scenario: El feed principal no lleva el tag

- **WHEN** se inspecciona el feed principal `/feed/`
- **THEN** no contiene ningún elemento `itunes:explicit`

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

La ruta del feed del podcast SHALL ser alcanzable por clientes HTTP. El proxy de producción SHALL NOT
aplicar el filtro por User-Agent que cierre la conexión (`444`), retirado el 2026-11-13 porque un
User-Agent se falsea trivialmente y solo ocultaba el feed a los agregadores y validadores que lo
consultan.

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

- **WHEN** un cliente HTTP solicita el feed del podcast
- **THEN** el proxy inverso lo sirve con su código real y no responde `444`

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

### Requirement: Emisión literal del guid del episodio

Dentro del feed del podcast, el `<guid>` de cada `<item>` SHALL ser el **valor persistido** del episodio
(el metadato `anchor-guid`, o el guid estable propio cuando no haya par) **verbatim** y escapado
únicamente para XML: un UUID desnudo SHALL emitirse como UUID desnudo —p. ej.
`773cd246-f771-4edb-9760-33dd3c775836`— y SHALL NOT convertirse en una URL ni recibir un esquema
antepuesto. El sistema SHALL NOT aplicar al guid el escape propio de las URL: el núcleo registra
`add_filter( 'the_guid', 'esc_url' )`, y `esc_url()` antepone `http://` a cualquier cadena sin esquema,
de modo que el guid resuelto SHALL volver a fijarse **después** de ese escape. Fuera del feed del
podcast, el valor de entrada del filtro SHALL conservarse intacto.

El contrato de integración SHALL ser:

- Hook: `add_filter( 'the_guid', <callback>, <prioridad>, 2 )` con `<prioridad>` **estrictamente mayor
  que 10** (posterior a `esc_url`), de forma que el callback se ejecute **después** del escape del
  núcleo y pueda deshacer su efecto.
- Callback: `callback( string $guid, int $postId ): string` — recibe el guid ya escapado por el núcleo
  y el identificador del post; devuelve `string`.
- Condición de aplicación: el callback SHALL actuar **solo** cuando la petición sea el feed del podcast
  (`is_feed() && is_post_type_archive('podcast')`); en cualquier otro contexto SHALL devolver `$guid`
  sin modificarlo.
- Escape de salida: `esc_html` (escape para XML), **nunca** `esc_url`.

Un guid que ya sea una URL (`https://anchor.fm/…`) SHALL emitirse igualmente **verbatim**, sin
re-escaparse ni normalizarse, para no alterar los guids que hoy ya coinciden con Anchor.

#### Scenario: El guid desnudo se emite sin esquema

- **WHEN** un episodio del feed tiene el metadato `anchor-guid` con el UUID desnudo `773cd246-f771-4edb-9760-33dd3c775836`
- **THEN** el `<guid>` del item vale exactamente `773cd246-f771-4edb-9760-33dd3c775836`, sin `http://` ni `https://` antepuesto

#### Scenario: El guid ya-URL se emite verbatim

- **WHEN** un episodio del feed tiene el metadato `anchor-guid` con una URL `https://anchor.fm/…`
- **THEN** el `<guid>` del item es esa URL exacta, sin re-escaparse ni normalizarse

#### Scenario: El guid emitido coincide con el de Anchor

- **WHEN** se compara el `<guid>` de un item del feed de WordPress con el `<guid>` de su item homólogo en Anchor
- **THEN** ambos valores son idénticos carácter a carácter, con o sin esquema en el origen

#### Scenario: La corrección es posterior al escape del núcleo

- **WHEN** el núcleo aplica `esc_url` al guid y lo convierte en `http://<uuid>`
- **THEN** el filtro del feed vuelve a fijar el guid persistido, de modo que el `<guid>` final no lleva el prefijo `http://`

#### Scenario: Fuera del feed del podcast no hay efecto

- **WHEN** se solicita el feed principal `/feed/` o cualquier otra superficie que pase por `the_guid`
- **THEN** el valor de entrada del filtro se devuelve sin modificar y ningún guid ajeno al podcast cambia

#### Scenario: El guid sigue siendo un identificador estable

- **WHEN** se compone el feed del podcast dos veces con el mismo metadato `anchor-guid`
- **THEN** el `<guid>` de cada episodio es idéntico en ambas composiciones

### Requirement: Duración del episodio en el feed

Cada `<item>` de un episodio publicado y público SHALL declarar **como máximo un**
`<itunes:duration>` y SHALL declararlo **solo** cuando la duración persistida sea interpretable con la
semántica de Apple: `HH:MM:SS`, `MM:SS` o un entero de segundos. El valor emitido SHALL escaparse para
XML; el sistema SHALL NOT emitir un valor vacío, malformado o fuera de rango.

**Fuente de verdad y espejo.** La duración SHALL persistirse en el metadato canónico `duration`
(`PodcastFeed::DURATION_META`). El metadato heredado `_duration`
(`PodcastFeed::LEGACY_DURATION_META`) —que el tema pinta en la ficha y en la tarjeta del episodio—
SHALL ser un **espejo exacto** del canónico. Todo proceso que escriba la duración —incluido el
importador de episodios— SHALL escribir **ambos** metas con el **mismo valor** y SHALL hacerlo a través
de un **único punto de escritura**, `PodcastDuration::writeDuration( int $postId, string $canonical ):
bool`; SHALL NOT existir ningún otro camino de escritura de la duración. La emisión SHALL leer el
canónico y SHALL usar `_duration` **solo** como respaldo transitorio cuando el canónico esté vacío:
cuando ambos existan y difieran, SHALL prevalecer el canónico y el backfill SHALL igualarlos.

**Normalización de la emisión.** `PodcastFeed::normalizeDuration( string $raw ): string` SHALL devolver
el valor a emitir o la cadena vacía, con este contrato:

- `HH:MM:SS` con horas de uno o más dígitos y minutos y segundos de **exactamente dos dígitos** en
  `00–59` → el valor **literal**, sin relleno, recorte ni reformateo.
- `MM:SS` con minutos y segundos de **exactamente dos dígitos** en `00–59` → el valor **literal**
  (formato admitido por Apple).
- **entero no negativo** (`^[0-9]+$`) → el valor **literal**, interpretado como **segundos** (formato
  admitido por Apple); SHALL NOT convertirse a `HH:MM:SS`, multiplicarse ni dividirse.
- tras `trim()` de los espacios exteriores, **cualquier otro valor** —vacío, no numérico, con signo, con
  espacios internos, con más de tres componentes, o con minutos o segundos fuera de `00–59`— →
  **cadena vacía**, y el tag SHALL omitirse.

La normalización SHALL NOT multiplicar, dividir, redondear ni reinterpretar el valor: está **prohibido**
deducir que un entero o un `MM:SS` significa minutos. La corrección del dato corresponde al backfill,
que toma el valor de Anchor.

**Backfill desde Anchor.** El sistema SHALL ofrecer el subcomando
`wp atareao podcast-duration-backfill [--dry-run] [--limit=<n>] [--source=<ruta>]`:

- **Fuente.** Sin `--source`, SHALL usar la **URL de origen resuelta** como el resto del plugin:
  `get_option('atareao_podcast_origin_rss')` (`PodcastAudio::ORIGIN_RSS_OPTION`) cuando no esté vacía
  y, si lo está, `PodcastAudio::ORIGIN_RSS_DEFAULT` (`https://anchor.fm/s/5a5b39c/podcast/rss`). Hoy
  producción tiene el ajuste **vacío** (`false`), así que el camino normal es el valor por defecto. El
  informe SHALL indicar la **URL de origen usada**. Con `--source=<ruta>` SHALL leer ese fichero local,
  SHALL NOT realizar ninguna petición de red y SHALL indicar en el informe esa ruta como origen.
- **Emparejamiento.** Primero por `anchor-guid` exacto —comparado tras `trim`— contra el `<guid>` del
  item de Anchor. Respaldo cuando el episodio no tenga `anchor-guid` o no haya coincidencia: por número
  de episodio, deducido del título de Anchor con el prefijo `ATA <n>` y comparado con el metadato
  `number`, y **solo si hay exactamente un candidato**. Sin candidato o con varios → **sin
  correspondencia**.
- **Valor.** El `<itunes:duration>` del item homólogo, validado con `normalizeDuration()`; si no valida
  → **ilegible**, y SHALL NOT escribirse. Una duración **cero** —cualquier forma que interprete **todos**
  sus componentes como `0`: `0`, `00`, `000`, `0:0`, `0:00`, `00:00`, `0:00:00`, `00:00:00` y cualquier
  variante con horas, minutos y segundos a cero, que el origen publica cuando no conoce la duración—
  SHALL considerarse **ilegible** y SHALL NOT sobrescribir un valor existente: escribirla borraría un
  dato bueno. La clasificación SHALL ser **numérica** —todos los componentes a cero— y SHALL NOT
  depender de una lista fija de formas.
- **Escritura.** La decisión SHALL tomarse sobre **ambos** metas: el episodio SHALL considerarse al día
  (`sin_cambios`) solo cuando `duration` **y** `_duration` normalizan ya al valor del origen. Si
  cualquiera de los dos difiere —incluido un espejo desincronizado con el canónico correcto— SHALL
  escribirse a través del único punto de escritura (canónico y espejo). SHALL NOT tocar `mp3-url`,
  título, fecha, `anchor-guid`, numeración, orden, adjuntos ni ningún otro metadato.
- **Informe.** SHALL reportar `actualizados`, `sin_cambios`, `sin_correspondencia`, `ilegibles` y
  `pendientes` (dejados fuera por `--limit`), y con `--dry-run` SHALL listar los cambios **sin
  escribir**, con `post_id`, valor canónico y espejo actuales y valor nuevo; si son muchos SHALL
  limitar el listado (primeras 20 entradas) y resumir el resto.
- **Límite.** `--limit=<n>` SHALL acotar el número de **actualizaciones** de la pasada; lo no aplicado
  por el tope SHALL contarse como `pendientes`.
- **Idempotencia.** Una segunda ejecución real sobre la misma fuente SHALL reportar **0 actualizados** y
  SHALL NOT modificar ningún metadato.
- **Origen ilegible.** Un origen inaccesible o con XML inválido SHALL abortar con error y SHALL NOT
  escribir nada.

#### Scenario: Una duración `HH:MM:SS` se emite tal cual

- **GIVEN** un episodio con el metadato canónico `duration` igual a `00:22:11`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` declara `<itunes:duration>00:22:11</itunes:duration>` sin reformatear

#### Scenario: Una duración `MM:SS` válida se emite tal cual

- **GIVEN** un episodio con el metadato canónico `duration` igual a `22:11`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` declara `<itunes:duration>22:11</itunes:duration>`, formato que Apple admite

#### Scenario: Un entero de segundos se emite tal cual

- **GIVEN** un episodio con el metadato canónico `duration` igual a `1331`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` declara `<itunes:duration>1331</itunes:duration>`, sin convertirlo a `00:22:11` ni multiplicarlo por 60

#### Scenario: Un componente fuera de rango omite el tag

- **GIVEN** un episodio cuyo metadato canónico `duration` vale `00:75:00`, `22:75` o `00:22:99`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` no declara `<itunes:duration>` y el feed sigue siendo XML bien formado

#### Scenario: Un valor malformado omite el tag

- **GIVEN** un episodio cuyo metadato canónico `duration` vale `abc`, `1:2:3`, `00:22:11x` o `--5`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` no declara `<itunes:duration>` y nunca se emite el valor inválido

#### Scenario: Un episodio sin dato omite el tag

- **GIVEN** un episodio sin `duration` y sin `_duration`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` no declara `<itunes:duration>` y el resto de sus elementos se emite con normalidad

#### Scenario: El backfill rellena un episodio sin duración

- **GIVEN** un episodio publicado con `anchor-guid` cuyo homólogo de Anchor declara `00:22:04` y que no tiene ningún metadato de duración
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** el episodio queda con `duration` y `_duration` iguales a `00:22:04` y el informe lo cuenta en `actualizados`

#### Scenario: El backfill corrige una duración 60 veces corta

- **GIVEN** un episodio con `duration` igual a `00:22` y otro con `duration` igual a `20`, cuyos homólogos de Anchor declaran `00:22:11` y `00:20:37`
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** sus canónicos pasan a `00:22:11` y `00:20:37`, sus espejos coinciden y el informe los cuenta en `actualizados`

#### Scenario: El backfill es idempotente

- **GIVEN** una ejecución real del backfill ya aplicada sobre la misma fuente
- **WHEN** se ejecuta de nuevo `wp atareao podcast-duration-backfill`
- **THEN** el informe da `actualizados = 0`, todos los episodios emparejados quedan en `sin_cambios` y ningún metadato cambia

#### Scenario: `--dry-run` no escribe

- **GIVEN** episodios con duraciones distintas de las de Anchor
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill --dry-run`
- **THEN** lista los cambios que haría y no escribe ningún metadato: los valores de `duration` y `_duration` quedan como estaban

#### Scenario: Un episodio sin correspondencia se reporta y no se modifica

- **GIVEN** un episodio cuyo `anchor-guid` no existe en Anchor y cuyo `number` tampoco permite un único candidato
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** el informe lo cuenta en `sin_correspondencia`, conserva su `duration` y su `_duration` intactos y no inventa una duración

#### Scenario: El espejo de metas queda coherente

- **GIVEN** cualquier episodio actualizado por el backfill
- **WHEN** se comparan sus metadatos `duration` y `_duration`
- **THEN** ambos tienen el mismo valor, y el `<itunes:duration>` del feed es el del canónico

#### Scenario: El backfill corrige un espejo desincronizado

- **GIVEN** un episodio con `duration` ya correcto (`00:45:00`) y `_duration` distinto (`00:40:00`), cuyo homólogo de Anchor declara `00:45:00`
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** el informe lo cuenta en `actualizados`, `_duration` pasa a `00:45:00`, el canónico se conserva y una segunda pasada lo deja en `sin_cambios`

#### Scenario: Cualquier forma de duración cero no sobrescribe

- **GIVEN** un episodio con `duration` y `_duration` iguales a `00:44:49` cuyo homólogo de Anchor declara
  `<itunes:duration>0:00:00</itunes:duration>`, y otros episodios en la misma situación cuyos homólogos
  declaran `0`, `00`, `000`, `0:0`, `0:00`, `00:00`, `00:00:00` u `00:0`
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** el informe los cuenta a todos en `ilegibles` y conserva intactos el canónico y el espejo de
  cada uno

#### Scenario: Una duración distinta de cero sí se aplica

- **GIVEN** un episodio con `duration` y `_duration` iguales a `00:01:01` cuyo homólogo de Anchor declara
  `00:12:34`
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** ambos metas pasan a `00:12:34` y el informe lo cuenta en `actualizados`

#### Scenario: `--source` evita la red

- **GIVEN** un fichero local con el RSS de Anchor y un episodio emparejable
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill --source=<ruta>`
- **THEN** el comando usa ese fichero, no realiza peticiones de red y aplica la duración que contiene

#### Scenario: El ajuste de origen está vacío

- **GIVEN** el ajuste `atareao_podcast_origin_rss` vacío o no definido, como está hoy en producción
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill` sin `--source`
- **THEN** el comando resuelve `PodcastAudio::ORIGIN_RSS_DEFAULT`, descarga ese RSS y aplica las duraciones en vez de abortar por falta de fuente

#### Scenario: El informe indica la URL de origen

- **GIVEN** una ejecución del backfill con `--source=<ruta>` y otra sin él
- **WHEN** se inspecciona el informe de cada una
- **THEN** la primera indica la ruta del fichero y la segunda la URL resuelta —configurada o por defecto—, de modo que siempre se sepa de dónde salieron las duraciones

#### Scenario: `--limit` acota las actualizaciones

- **GIVEN** cinco episodios con duración distinta de la de Anchor
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill --limit=2`
- **THEN** actualiza como máximo dos, informa los tres restantes como `pendientes` y una pasada posterior los completa

#### Scenario: Un origen ilegible no escribe nada

- **GIVEN** un origen inaccesible o con XML inválido
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** el comando termina con error, informa la causa y no escribe ningún metadato
