# mcp-server Specification

## Purpose
Esta capability define y endurece el **servicio público de consulta MCP** que `atareao-functionality` expone por REST en `atareao/v1/mcp` (`\WP_REST_Server::CREATABLE`, JSON-RPC 2.0, herramientas `get_latest_posts`, `get_post` y `search_posts`). El objetivo explícito es **que cualquiera pueda consultar el blog vía MCP sin credenciales**: las tres herramientas son de solo lectura y exponen únicamente contenido ya publicado. Para que el servicio sea usable y sostenible, la capability cubre el descubrimiento (`initialize`/`tools/list`) para clientes MCP genéricos, la documentación pública del endpoint, un rate limiting razonable y explícito que no estorbe el uso legítimo, límites de paginación y de tamaño de respuesta, CORS coherente con un servicio público de solo lectura y la garantía de no exponer nunca contenido no público. Corrige además la fuga de posts protegidos por contraseña —`get_post` respeta `post_password_required()` y no aplica el filtro `the_content` sobre el contenido crudo—, alinea la visibilidad de los listados con los posts públicos, valida los argumentos y devuelve errores genéricos que no revelan información interna. Cualquier herramienta **futura con efectos** exigirá autenticación (Application Password/`Bearer`) y capacidad. La ruta REST, los nombres de las herramientas, el meta de descubrimiento y el transporte JSON-RPC se conservan; el sitio público no cambia.

## Requirements

### Requirement: Acceso público de solo lectura al endpoint

El endpoint `atareao/v1/mcp` SHALL conservar su ruta, su método `CREATABLE`, el transporte JSON-RPC 2.0, los nombres de las herramientas y el meta de descubrimiento `rel="mcp-server"`, y SHALL poder invocarse **sin credenciales**: es un servicio público de consulta deliberadamente abierto para que cualquiera consulte el blog. Las tres herramientas (`get_latest_posts`, `get_post` y `search_posts`) SHALL ser de **solo consulta** y SHALL devolver únicamente contenido público ya publicado. El endpoint SHALL NOT ejecutar ninguna acción con efectos (escritura, administración o acceso a datos internos). Cualquier herramienta futura que sí tenga efectos SHALL exigir autenticación (Application Password / `Bearer` sobre HTTPS) y la capacidad adecuada, y SHALL rechazar las peticiones anónimas.

#### Scenario: Invocación anónima de las tres herramientas de consulta

- **WHEN** se envía un POST anónimo a `atareao/v1/mcp` invocando `get_latest_posts`, `get_post` o `search_posts`
- **THEN** el sistema atiende la petición sin exigir credenciales y devuelve únicamente contenido público

#### Scenario: Las herramientas de consulta no tienen efectos

- **WHEN** un cliente anónimo ejecuta cualquiera de las tres herramientas
- **THEN** el sistema no crea, modifica ni borra ningún recurso del sitio

#### Scenario: Post protegido por contraseña

- **WHEN** se solicita `get_post` con el `id` de un post protegido por contraseña, con o sin credencial
- **THEN** el sistema no devuelve su contenido y responde el error genérico «Post not found»

#### Scenario: Herramienta futura con efectos

- **WHEN** en el futuro se añade una herramienta que escribe, administra o accede a datos internos y se invoca sin credenciales
- **THEN** el sistema rechaza la petición anónima y exige autenticación y capacidad

#### Scenario: Abuso masivo anónimo

- **WHEN** una misma IP realiza un número elevado de invocaciones anónimas en la ventana
- **THEN** el sistema frena el abuso con el rate limiting en lugar de servir todas las peticiones

### Requirement: Descubrimiento e interoperabilidad MCP

El endpoint SHALL responder a `initialize` con `protocolVersion`, `serverInfo` (nombre y versión del servidor) y `capabilities` que declaren el soporte de herramientas, de modo que un cliente MCP genérico pueda integrarse **sin configuración especial**. `tools/list` SHALL describir las tres herramientas con su `name`, su `description`, su `inputSchema` (tipo `object` con las propiedades y los campos requeridos) y su carácter de **solo lectura**, de forma que el cliente sepa que ninguna de ellas produce efectos. La descripción de cada herramienta SHALL indicar que devuelve contenido público ya publicado.

#### Scenario: Respuesta a `initialize`

- **WHEN** un cliente MCP genérico envía `initialize`
- **THEN** el sistema responde con `protocolVersion`, `serverInfo` y `capabilities` que declaran el soporte de herramientas

#### Scenario: `tools/list` describe las herramientas

- **WHEN** un cliente envía `tools/list`
- **THEN** el sistema devuelve las tres herramientas con su `name`, `description`, `inputSchema` y su carácter de solo lectura

#### Scenario: Integración sin configuración especial

- **WHEN** un cliente MCP genérico se conecta al endpoint sin ajustes previos
- **THEN** descubre las herramientas y puede invocarlas directamente contra el blog

#### Scenario: El esquema de argumentos es válido

- **WHEN** un cliente valida el `inputSchema` de cada herramienta
- **THEN** el esquema es un objeto JSON válido con las propiedades y los campos requeridos correctos

### Requirement: Rate limiting por IP

El sistema SHALL aplicar un rate limiting por **IP de origen** con un contador de **ventana fija de 60 segundos** y un tope por defecto de **60 peticiones por minuto** (ajustable por filtro/constante, sin editar el código para un valor razonable), sobre un transient de WordPress cuya clave sea un hash de la IP (sin almacenar la dirección en claro). El tope SHALL ser suficientemente holgado para un cliente MCP consultando el blog y SHALL frenar el scraping masivo. Al superar el tope, el sistema SHALL responder **HTTP 429** con la cabecera `Retry-After` y un error JSON-RPC genérico, **sin** ejecutar la herramienta solicitada. El sistema SHALL NOT confiar en cabeceras de proxy (`X-Forwarded-For` u otras) para determinar la IP salvo configuración explícita, y la respuesta de límite SHALL NOT revelar información interna.

#### Scenario: Dentro del límite

- **WHEN** una IP no ha superado el tope de peticiones de la ventana
- **THEN** el sistema atiende la petición con normalidad

#### Scenario: Uso legítimo de un cliente MCP

- **WHEN** un cliente MCP consulta el blog con una cadencia por debajo de 60 peticiones por minuto
- **THEN** el sistema atiende todas sus peticiones sin bloquearlo

#### Scenario: Superación del límite

- **WHEN** una IP supera el tope de peticiones permitidas en la ventana
- **THEN** el sistema responde HTTP 429 con `Retry-After` y no ejecuta la herramienta

#### Scenario: Contadores por IP aislados

- **WHEN** una IP ha agotado su límite y otra IP distinta realiza una petición
- **THEN** la segunda IP no se ve afectada por el contador de la primera

#### Scenario: Cabeceras de proxy no confiables

- **WHEN** una petición intenta variar su IP de origen manipulando cabeceras de proxy no configuradas
- **THEN** el sistema usa la IP que ve el servidor y no la cabecera manipulada para el límite

### Requirement: Respeto de contraseñas y visibilidad de posts

`get_post` SHALL devolver contenido únicamente de posts con `post_status` igual a `publish`, con `post_password` vacío y para los que `post_password_required($post)` sea falso. Si el post no existe, no está publicado o requiere contraseña, el sistema SHALL responder un **error genérico idéntico** («Post not found») sin distinguir el motivo y SHALL NOT devolver su contenido. El sistema SHALL NOT aplicar `apply_filters('the_content', …)` sobre `$post->post_content` crudo: SHALL obtener el contenido con `get_post_field('post_content', $post)` o `get_the_content()`, y SHALL aplicar los filtros de presentación únicamente después de confirmar que el post no requiere contraseña. `get_latest_posts` y `search_posts` SHALL excluir los posts protegidos por contraseña (`post_password => ''`) y SHALL restringirse a tipos de contenido públicos. El endpoint SHALL NOT exponer datos internos: ni borradores, ni contenido privado, ni posts protegidos, ni metadatos internos (campos privados, notas, revisiones), ni rutas del servidor, ni datos de usuarios (correos, roles o capacidades).

#### Scenario: Post protegido por contraseña

- **WHEN** se solicita `get_post` con el `id` de un post publicado que tiene `post_password` no vacío
- **THEN** el sistema no devuelve su contenido y responde el error genérico «Post not found»

#### Scenario: Indistinguible de un post inexistente

- **WHEN** se compara la respuesta de `get_post` para un post protegido y para un `id` inexistente
- **THEN** ambas respuestas son idénticas y no permiten deducir la existencia del post protegido

#### Scenario: Post público sin contraseña

- **WHEN** se solicita `get_post` con el `id` de un post publicado sin contraseña
- **THEN** el sistema devuelve su contenido obtenido con `get_post_field`/`get_the_content` y filtrado, sin requerir contraseña

#### Scenario: No se filtra el contenido crudo de un post protegido

- **WHEN** la petición apunta a un post que requiere contraseña
- **THEN** el sistema no invoca el filtro `the_content` sobre su contenido crudo ni ejecuta shortcodes o embeds sobre él

#### Scenario: Los listados excluyen contenido no visible

- **WHEN** `get_latest_posts` o `search_posts` recorren el contenido
- **THEN** no aparecen posts protegidos por contraseña ni tipos de contenido no públicos

#### Scenario: Sin datos internos en la respuesta

- **WHEN** se inspecciona la respuesta de cualquier herramienta
- **THEN** no aparecen borradores, contenido privado, metadatos internos, rutas del servidor ni datos de usuarios

### Requirement: Validación de argumentos y límites de respuesta

El registro de la ruta SHALL declarar `args` con `validate_callback` y `sanitize_callback` para `jsonrpc`, `method` y `params`. Cada herramienta SHALL validar sus argumentos antes de ejecutar cualquier consulta: `id` SHALL ser un entero positivo; `query` SHALL ser una cadena no vacía con longitud máxima; `page` y `per_page` (y cualquier `limit`) SHALL ser enteros dentro de un rango acotado. Un argumento inválido SHALL responder JSON-RPC `-32602` (invalid params) **sin** ejecutar la consulta. `search_posts` SHALL admitir paginación con un **tope máximo** de página y de tamaño, y ninguna herramienta SHALL devolver más resultados ni un cuerpo mayor que el **tamaño máximo de respuesta**, de modo que no se pueda volcar el blog entero en una sola petición.

#### Scenario: JSON-RPC inválido

- **WHEN** el cuerpo no es JSON válido o no declara `jsonrpc: "2.0"`
- **THEN** el sistema responde `-32700` (parse error) y no ejecuta ninguna herramienta

#### Scenario: Identificador no válido

- **WHEN** `get_post` recibe un `id` no entero, negativo o cero
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: Consulta vacía o excesiva

- **WHEN** `search_posts` recibe una `query` vacía o que supera la longitud máxima permitida
- **THEN** el sistema responde `-32602` sin ejecutar la búsqueda

#### Scenario: Paginación fuera de rango

- **WHEN** `search_posts` recibe `page` o `per_page` no enteros o fuera del rango permitido
- **THEN** el sistema los rechaza o los acota al rango válido y nunca supera el tope de resultados

#### Scenario: Tope de resultados

- **WHEN** `search_posts` coincidiría con más resultados que el máximo permitido
- **THEN** el sistema devuelve como máximo ese tope y no agota recursos

#### Scenario: Tamaño máximo de respuesta

- **WHEN** una petición intentaría devolver un cuerpo mayor que el tamaño máximo permitido
- **THEN** el sistema recorta la respuesta al tope (o responde un error accionable) y nunca vuelca el blog entero

### Requirement: CORS para la consulta anónima

Al ser un servicio público de solo lectura, el endpoint SHALL responder con cabeceras CORS que permitan la consulta anónima **desde cualquier origen** para el método de lectura (`POST`), sin exigir credenciales: `Access-Control-Allow-Origin` SHALL admitir cualquier origen y `Access-Control-Allow-Credentials` SHALL NOT habilitarse (no hay credenciales que enviar). El endpoint SHALL resolver la petición de preflight `OPTIONS` con los métodos permitidos (`POST`, `OPTIONS`) y con las cabeceras permitidas **mínimas y necesarias**, que SHALL incluir al menos `Content-Type`, `Accept`, `MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name` y `Authorization`, y SHALL NOT permitir métodos con efectos. Si en el futuro se decidiera restringir el origen, la restricción SHALL quedar escrita y razonada antes de aplicarse.

#### Scenario: Consulta anónima desde un origen distinto

- **WHEN** un cliente anónimo consulta el endpoint desde un origen distinto
- **THEN** la respuesta incluye `Access-Control-Allow-Origin` que admite ese origen y no habilita credenciales

#### Scenario: Preflight `OPTIONS`

- **WHEN** el navegador envía una petición de preflight `OPTIONS`
- **THEN** el sistema responde con los métodos permitidos (`POST`, `OPTIONS`) y las cabeceras mínimas necesarias, incluidas las del protocolo MCP

#### Scenario: Cabeceras del protocolo admitidas

- **WHEN** un cliente MCP envía `MCP-Protocol-Version`, `Mcp-Method` o `Mcp-Name`
- **THEN** el preflight las admite como cabeceras permitidas

#### Scenario: Sin métodos con efectos

- **WHEN** se intenta invocar el endpoint con un método que no sea de consulta
- **THEN** el sistema no permite métodos con efectos en CORS

### Requirement: Documentación pública del endpoint

El `README.md` del plugin SHALL documentar el endpoint como servicio público de consulta: la **URL** (`/wp-json/atareao/v1/mcp`), el **protocolo** (JSON-RPC 2.0), las **tres herramientas** de consulta disponibles (`get_latest_posts`, `get_post`, `search_posts`) con sus argumentos, y un **ejemplo de llamada** funcional (por ejemplo `curl` con `tools/list` o `search_posts`) que cualquiera pueda copiar y ejecutar sin credenciales. La documentación SHALL indicar el carácter de solo lectura, el rate limiting y los límites de paginación/tamaño.

#### Scenario: La documentación describe el endpoint

- **WHEN** una persona consulta el `README.md` del plugin
- **THEN** encuentra la URL, el protocolo, las tres herramientas y un ejemplo de llamada

#### Scenario: Ejemplo de llamada sin credenciales

- **WHEN** una persona copia y ejecuta el ejemplo de llamada del `README.md`
- **THEN** obtiene una respuesta correcta del endpoint sin configurar credenciales

### Requirement: Errores que no revelan información interna

Los errores del endpoint SHALL usar códigos JSON-RPC estándar y mensajes genéricos, y SHALL respetar el estado HTTP correcto: **429** cuando se supera el rate limit y **401**/**403** cuando en el futuro una herramienta con efectos rechace una petición sin credencial o sin capacidad. Los mensajes SHALL NOT contener rutas de fichero, nombres de tabla, consultas SQL, trazas de pila, identificadores internos ni la existencia concreta de un recurso. El detalle técnico de un fallo SHALL registrarse únicamente en el servidor, sin credenciales.

#### Scenario: Error interno del servidor

- **WHEN** se produce un fallo interno al atender una petición válida
- **THEN** el cliente recibe un mensaje genérico sin detalles internos y el detalle queda solo en el registro del servidor

#### Scenario: Error de autorización neutro

- **WHEN** una herramienta con efectos rechaza una petición sin credenciales o con credenciales no autorizadas
- **THEN** el mensaje de error no revela si el usuario existe ni qué comprobación concreta falló

#### Scenario: Códigos coherentes con el transporte

- **WHEN** la petición falla por superar el rate limit o porque una herramienta con efectos rechaza la falta de credencial o de capacidad
- **THEN** el sistema responde 429 (límite), 401 (sin credencial) o 403 (sin capacidad), y no un 200 uniforme

### Requirement: Filtro por tipo de contenido en las herramientas de consulta

`search_posts` y `get_latest_posts` SHALL aceptar un argumento opcional `post_type` que restrinja los resultados a uno de los tipos de contenido del **dominio** del blog: `post`, `tutorial`, `capitulo`, `aplicacion`, `podcast` y `software`. La validación del argumento **explícito** SHALL realizarse contra una **lista permitida fija** con esos seis tipos, y SHALL NOT depender de «cualquier tipo público» del runtime. Si el argumento está **ausente** (o es `null`), el comportamiento SHALL conservarse: la consulta abarca todos los tipos públicos, como antes del cambio. Si el valor no pertenece a la lista permitida —incluido el tipo inexistente `application` y el tipo público no perteneciente al dominio `page`—, el sistema SHALL responder JSON-RPC `-32602` (invalid params) **sin** ejecutar la consulta. El `enum` de `post_type` que el servidor anuncia en el `inputSchema` de las herramientas de consulta (`tools/list`) SHALL ser **esa misma lista permitida**, de modo que el contrato publicado coincida con la validación.

#### Scenario: Filtro válido por tipo de contenido

- **WHEN** un cliente invoca `search_posts` con `post_type` igual a `podcast`
- **THEN** el sistema devuelve únicamente entradas de tipo `podcast`

#### Scenario: Tipo de contenido no permitido

- **WHEN** un cliente invoca `search_posts` o `get_latest_posts` con un `post_type` que no es uno de los tipos del dominio (por ejemplo `application`)
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: Tipo público fuera del dominio no permitido

- **WHEN** un cliente invoca `search_posts` o `get_latest_posts` con `post_type` igual a `page` (tipo público pero fuera del dominio)
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: `post_type` ausente se comporta como hasta ahora

- **WHEN** un cliente invoca `search_posts` o `get_latest_posts` sin `post_type`
- **THEN** el sistema consulta todos los tipos públicos como antes del cambio

#### Scenario: El esquema anunciado coincide con la lista permitida

- **WHEN** un cliente envía `tools/list`
- **THEN** el `enum` de `post_type` anunciado incluye exactamente los seis tipos del dominio y ningún otro

### Requirement: Exposición de metas públicas de CPT en las respuestas

Las herramientas SHALL incluir en el resultado las **metas públicas** del CPT correspondiente: para `podcast`, `mp3-url`, `number` y `season`; para `capitulo`, `numero-capitulo` y `tutorial-id`; y `post_views_count` cuando exista. Las respuestas SHALL incluir también las **taxonomías públicas** asociadas a la entrada. El sistema SHALL NOT exponer metadatos internos (claves con prefijo `_`, como `_download_url`, `_repository_url` y `_version`) ni datos de usuario.

#### Scenario: Un `podcast` incluye sus metas públicas

- **WHEN** se recupera o busca un `podcast`
- **THEN** el resultado incluye `mp3-url`, `number` y `season` (cuando existan) y no incluye claves internas

#### Scenario: Un `capitulo` incluye sus metas públicas

- **WHEN** se recupera o busca un `capitulo`
- **THEN** el resultado incluye `numero-capitulo` y `tutorial-id` (cuando existan)

#### Scenario: Las taxonomías públicas se exponen

- **WHEN** una entrada tiene términos en taxonomías públicas
- **THEN** el resultado los incluye de forma legible

#### Scenario: No se exponen metas internas

- **WHEN** se recupera una `aplicacion` o un `software`
- **THEN** el resultado no incluye `_download_url`, `_repository_url` ni `_version`

#### Scenario: Sin datos de usuario

- **WHEN** se inspecciona cualquier respuesta
- **THEN** no aparecen datos de autor con información sensible (correo, roles o capacidades)

### Requirement: Endpoint limpio `/mcp` como alias del transporte

El sistema SHALL aceptar el transporte MCP en la ruta limpia `/mcp` para los métodos `POST` (mensaje JSON-RPC) y `OPTIONS` (preflight), atendiendo **exactamente la misma lógica** que la ruta REST `atareao/v1/mcp`. La ruta REST `/wp-json/atareao/v1/mcp` SHALL conservarse como ruta interna/legacy, de modo que el cliente MCP ya registrado siga funcionando. Ambas rutas SHALL servirse desde una **única implementación**, sin duplicar la lógica del transporte. Un `GET` o `DELETE` sobre `/mcp` SHALL responder `405 Method Not Allowed` —un `GET` al endpoint desnudo está reservado en Streamable HTTP y servir ahí la tarjeta está desaconsejado—. La ruta de la tarjeta se deriva por convención como `/mcp/server-card`. La ruta limpia `/mcp` SHALL ser el **único endpoint anunciado**: el meta de descubrimiento que el plugin emite en `wp_head` y la documentación pública SHALL apuntar a `/mcp`, y la ruta REST `/wp-json/atareao/v1/mcp` SHALL NOT anunciarse en ningún sitio (meta, documentación, tarjeta o catálogo), aunque siga operativa.

#### Scenario: El alias atiende peticiones POST

- **WHEN** se envía un `POST` JSON-RPC a `/mcp`
- **THEN** el sistema atiende la petición con la misma lógica que la ruta REST `atareao/v1/mcp`

#### Scenario: La ruta legacy sigue operativa

- **WHEN** se envía un `POST` JSON-RPC a `/wp-json/atareao/v1/mcp`
- **THEN** el sistema atiende la petición como antes de este cambio

#### Scenario: Una sola implementación

- **WHEN** se compara el comportamiento de `/mcp` y de `/wp-json/atareao/v1/mcp`
- **THEN** ambas rutas sirven el mismo handler y no existen dos implementaciones del transporte

#### Scenario: `GET` sobre el endpoint no devuelve la tarjeta

- **WHEN** se envía un `GET` a `/mcp`
- **THEN** el sistema responde `405 Method Not Allowed` y no sirve la tarjeta

#### Scenario: El alias no abre métodos con efectos

- **WHEN** se envía un método con efectos que no sea el `POST` del transporte
- **THEN** el sistema no lo atiende como operación del transporte MCP

#### Scenario: El endpoint anunciado es el limpio

- **WHEN** se inspeccionan el meta de descubrimiento emitido en `wp_head` y la documentación pública del plugin
- **THEN** ambos apuntan a la ruta limpia `/mcp` y ninguno apunta a la ruta REST `/wp-json/atareao/v1/mcp`

#### Scenario: La ruta legacy no se anuncia pero sigue operativa

- **WHEN** se envía un `POST` JSON-RPC a `/wp-json/atareao/v1/mcp`
- **THEN** el sistema lo atiende con normalidad, aunque esa ruta no aparezca anunciada en ningún sitio

### Requirement: Conformidad dual-era de Streamable HTTP

El servidor SHALL seleccionar la era por la forma de la petición: una petición con `initialize` selecciona la **era legacy**; una petición que declare metadatos de protocolo por petición selecciona la **era moderna**. En la **era moderna** el servidor SHALL: aceptar `POST` con un único mensaje JSON-RPC (petición o notificación); leer de `params._meta` (el campo general `_meta` situado dentro de `params`) los campos `io.modelcontextprotocol/protocolVersion` y `io.modelcontextprotocol/clientCapabilities` y rechazar la petición con `-32602` (Invalid params) y HTTP `400` si falta cualquiera de los obligatorios; rechazar una versión no soportada con `UnsupportedProtocolVersionError` (`-32022`) y HTTP `400`, listando en `data.supported` las versiones soportadas y en `data.requested` la solicitada; validar las cabeceras `MCP-Protocol-Version` (obligatoria y coincidente con `_meta`), `Mcp-Method` (obligatoria y coincidente con `method`) y `Mcp-Name` (obligatoria en `tools/call` y coincidente con `params.name`), respondiendo `HeaderMismatch` (`-32020`) y HTTP `400` si alguna falta o no coincide —comparando nombres de cabecera sin distinguir mayúsculas y decodificando los valores que usen el sentinel Base64 antes de comparar—; incluir `resultType` con valor `"complete"` en todo resultado; incluir `_meta.io.modelcontextprotocol/serverInfo` en todo resultado; implementar `server/discover` devolviendo en el resultado `supportedVersions` (las versiones soportadas), `capabilities`, `ttlMs` y `cacheScope`, y la identidad del servidor en `_meta.io.modelcontextprotocol/serverInfo` sin duplicarla en el nivel superior del resultado; incluir `ttlMs` y `cacheScope` en los resultados de `tools/list` y determinismo en el orden de las herramientas; responder `202 Accepted` sin cuerpo ante una notificación aceptada; responder `-32601` con HTTP `404` ante un método no implementado; y responder `405` a `GET`/`DELETE`. En la **era legacy** el servidor SHALL seguir respondiendo `initialize` con `protocolVersion`, `serverInfo` y `capabilities`, negociando la versión (la mejor versión soportada no superior a la ofrecida por el cliente; si el cliente no la indica, la más baja soportada) y manteniendo `tools/list` y `tools/call`. El servidor SHALL NOT crear sesiones ni emitir `Mcp-Session-Id`. Las versiones de protocolo soportadas y anunciadas SHALL salir de una **única fuente de verdad**. El rate limiting, la visibilidad del contenido y la validación de argumentos SHALL mantenerse sin cambios.

#### Scenario: Petición moderna servida

- **WHEN** un cliente envía una petición con `_meta` de protocolo, las cabeceras obligatorias y una versión soportada
- **THEN** el sistema la atiende y devuelve un resultado con `resultType` y `serverInfo`

#### Scenario: Metadatos obligatorios ausentes

- **WHEN** una petición moderna omite `io.modelcontextprotocol/protocolVersion` o `io.modelcontextprotocol/clientCapabilities`
- **THEN** el sistema responde `-32602` con HTTP `400`

#### Scenario: Versión de protocolo no soportada

- **WHEN** una petición declara una versión que el servidor no soporta
- **THEN** el sistema responde `UnsupportedProtocolVersionError` (`-32022`) con HTTP `400`, listando las versiones soportadas y la solicitada

#### Scenario: Cabecera obligatoria ausente o discordante

- **WHEN** una petición moderna omite `MCP-Protocol-Version`, `Mcp-Method` o `Mcp-Name`, o alguno no coincide con el cuerpo
- **THEN** el sistema responde `HeaderMismatch` (`-32020`) con HTTP `400`

#### Scenario: Comparación de valores codificados

- **WHEN** `Mcp-Name` llega codificado con el sentinel Base64 y, decodificado, coincide con `params.name`
- **THEN** el sistema acepta la cabecera y no responde `HeaderMismatch`

#### Scenario: `params.name` no textual

- **WHEN** una petición moderna invoca `tools/call` con un `params.name` que no es una cadena
- **THEN** el sistema responde `-32602` (Invalid params) con HTTP `400`, sin convertir el parámetro a cadena ni compararlo con la cabecera `Mcp-Name`

#### Scenario: `server/discover` conforme al esquema

- **WHEN** un cliente moderno invoca `server/discover`
- **THEN** el resultado declara las versiones soportadas en el campo `supportedVersions` (y **no** en `supportedProtocolVersions`, que es el nombre del campo de la tarjeta), declara `capabilities` e `ttlMs`/`cacheScope`, lleva `resultType` con valor `complete` y sitúa la identidad del servidor en `_meta.io.modelcontextprotocol/serverInfo`

#### Scenario: Los listados son cacheables

- **WHEN** un cliente invoca `tools/list`
- **THEN** el resultado incluye `ttlMs` y `cacheScope` además de la lista de herramientas

#### Scenario: Notificación aceptada

- **WHEN** una petición moderna contiene una notificación (sin `id`)
- **THEN** el sistema responde `202 Accepted` sin cuerpo

#### Scenario: Método no implementado

- **WHEN** una petición moderna invoca un método que el servidor no implementa
- **THEN** el sistema responde `-32601` con HTTP `404`

#### Scenario: La era legacy sigue funcionando

- **WHEN** un cliente envía `initialize` con su versión
- **THEN** el sistema responde con `protocolVersion`, `serverInfo` y `capabilities`, y las llamadas posteriores a `tools/list` y `tools/call` siguen operativas

#### Scenario: Negociación de versión en la era legacy

- **WHEN** un cliente legacy ofrece una versión soportada
- **THEN** el sistema responde una versión soportada que no supera la ofrecida; si el cliente no la indica, responde la más baja soportada

#### Scenario: Sin sesiones

- **WHEN** el sistema atiende cualquier petición del transporte
- **THEN** no crea sesión ni emite `Mcp-Session-Id`

#### Scenario: No hay dos listas de versiones

- **WHEN** se comparan las versiones usadas en la negociación y en `server/discover`
- **THEN** ambas proceden de la misma fuente y son idénticas
