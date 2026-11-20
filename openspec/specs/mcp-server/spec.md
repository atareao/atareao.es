# mcp-server Specification

## Purpose
Esta capability define y endurece el **servicio público de consulta MCP** que `atareao-functionality` expone por REST en `atareao/v1/mcp` (`\WP_REST_Server::CREATABLE`, JSON-RPC 2.0, herramientas `get_latest_posts`, `get_post` y `search_posts`). El objetivo explícito es **que cualquiera pueda consultar el blog vía MCP sin credenciales**: las tres herramientas son de solo lectura y exponen únicamente contenido ya publicado. Para que el servicio sea usable y sostenible, la capability cubre el descubrimiento (`initialize`/`tools/list`) para clientes MCP genéricos, la documentación pública del endpoint, un rate limiting razonable y explícito que no estorbe el uso legítimo, límites de paginación y de tamaño de respuesta, CORS coherente con un servicio público de solo lectura y la garantía de no exponer nunca contenido no público. Corrige además la fuga de posts protegidos por contraseña —`get_post` respeta `post_password_required()` y no aplica el filtro `the_content` sobre el contenido crudo—, alinea la visibilidad de los listados con los posts públicos, valida los argumentos y devuelve errores genéricos que no revelan información interna. Cualquier herramienta **futura con efectos** exigirá autenticación (Application Password/`Bearer`) y capacidad. La ruta REST, los nombres de las herramientas, el meta de descubrimiento y el transporte JSON-RPC se conservan; el sitio público no cambia.

## Requirements

### Requirement: Acceso público de solo lectura al endpoint

El endpoint `atareao/v1/mcp` SHALL conservar su ruta, su método `CREATABLE`, el transporte JSON-RPC 2.0, el meta de descubrimiento `rel="mcp-server"` y los nombres de las herramientas existentes, y SHALL poder invocarse **sin credenciales**: es un servicio público de consulta deliberadamente abierto para que cualquiera consulte el blog. El catálogo de herramientas SHALL estar formado exclusivamente por herramientas de **solo consulta** —`get_latest_posts`, `get_post`, `search_posts`, `get_post_by_slug`, `list_posts` y `list_terms`— y **todas** SHALL devolver únicamente contenido público ya publicado. Añadir una herramienta al catálogo SHALL NOT relajar el invariante: cualquier herramienta nueva SHALL ser de solo lectura y pública, y el conjunto SHALL seguir sin exponer datos internos. El endpoint SHALL NOT ejecutar ninguna acción con efectos (escritura, administración o acceso a datos internos). Cualquier herramienta futura que sí tenga efectos SHALL exigir autenticación (Application Password / `Bearer` sobre HTTPS) y la capacidad adecuada, y SHALL rechazar las peticiones anónimas. Una herramienta nueva SHALL añadirse sin renombrar ni eliminar las existentes.

#### Scenario: Invocación anónima de las tres herramientas de consulta

- **WHEN** se envía un POST anónimo a `atareao/v1/mcp` invocando cualquiera de las herramientas de consulta del catálogo
- **THEN** el sistema atiende la petición sin exigir credenciales y devuelve únicamente contenido público

#### Scenario: Las herramientas de consulta no tienen efectos

- **WHEN** un cliente anónimo ejecuta cualquiera de las herramientas
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

#### Scenario: Las herramientas nuevas no alteran el contrato de las existentes

- **WHEN** se compara el catálogo con el anterior a este cambio
- **THEN** `get_latest_posts`, `get_post` y `search_posts` conservan su nombre y su comportamiento, y las herramientas nuevas son de solo lectura

### Requirement: Descubrimiento e interoperabilidad MCP

El endpoint SHALL responder a `initialize` con `protocolVersion`, `serverInfo` (nombre y versión del servidor) y `capabilities` que declaren el soporte de herramientas, de modo que un cliente MCP genérico pueda integrarse **sin configuración especial**. `tools/list` SHALL describir **todas** las herramientas del catálogo (`get_latest_posts`, `get_post`, `search_posts`, `get_post_by_slug`, `list_posts` y `list_terms`) con su `name`, su `description`, su `inputSchema` (tipo `object` con las propiedades, los tipos, las restricciones y los campos requeridos de cada herramienta) y su carácter de **solo lectura**, de forma que el cliente sepa que ninguna de ellas produce efectos. La descripción de cada herramienta SHALL indicar qué devuelve, el carácter público de lo devuelto y, cuando aplique, los límites de paginación, de modo que un cliente pueda distinguir sin ambigüedad cuándo usar cada una. El orden de las herramientas en `tools/list` SHALL ser determinista.

#### Scenario: Respuesta a `initialize`

- **WHEN** un cliente MCP genérico envía `initialize`
- **THEN** el sistema responde con `protocolVersion`, `serverInfo` y `capabilities` que declaran el soporte de herramientas

#### Scenario: `tools/list` describe las herramientas

- **WHEN** un cliente envía `tools/list`
- **THEN** el sistema devuelve las seis herramientas con su `name`, `description`, `inputSchema` y su carácter de solo lectura

#### Scenario: Integración sin configuración especial

- **WHEN** un cliente MCP genérico se conecta al endpoint sin ajustes previos
- **THEN** descubre las herramientas y puede invocarlas directamente contra el blog

#### Scenario: El esquema de argumentos es válido

- **WHEN** un cliente valida el `inputSchema` de cada herramienta
- **THEN** el esquema es un objeto JSON válido con las propiedades y los campos requeridos correctos

#### Scenario: Los `enum` anunciados coinciden con las listas permitidas

- **WHEN** se comparan los `enum` de `post_type` y de `taxonomy` anunciados en `tools/list` con las listas permitidas del servidor
- **THEN** coinciden exactamente, sin valores de más ni de menos

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

`get_post` y `get_post_by_slug` SHALL devolver contenido únicamente de posts con `post_status` igual a `publish`, con `post_password` vacío y para los que `post_password_required($post)` sea falso. Si el post no existe, no está publicado o requiere contraseña, el sistema SHALL responder un **error genérico idéntico** («Post not found») sin distinguir el motivo y SHALL NOT devolver su contenido. El sistema SHALL NOT aplicar `apply_filters('the_content', …)` sobre `$post->post_content` crudo: SHALL obtener el contenido con `get_post_field('post_content', $post)` o `get_the_content()`, y SHALL aplicar los filtros de presentación únicamente después de confirmar que el post no requiere contraseña. `get_latest_posts`, `search_posts`, `list_posts` y `list_terms` SHALL excluir los posts protegidos por contraseña (`post_password => ''`), SHALL restringirse a tipos de contenido públicos y SHALL contar o listar únicamente entradas públicas. El endpoint SHALL NOT exponer datos internos: ni borradores, ni contenido privado, ni posts protegidos, ni metadatos internos (campos privados, notas, revisiones), ni rutas del servidor, ni datos de usuarios (correos, roles o capacidades).

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

- **WHEN** `get_latest_posts`, `search_posts`, `list_posts` o `list_terms` recorren el contenido
- **THEN** no aparecen posts protegidos por contraseña ni tipos de contenido no públicos

#### Scenario: Sin datos internos en la respuesta

- **WHEN** se inspecciona la respuesta de cualquier herramienta
- **THEN** no aparecen borradores, contenido privado, metadatos internos, rutas del servidor ni datos de usuarios

#### Scenario: `get_post_by_slug` no revela un post protegido

- **WHEN** se solicita `get_post_by_slug` con el slug de un post publicado que tiene `post_password` no vacío
- **THEN** el sistema responde el error genérico «Post not found» y no devuelve su contenido

#### Scenario: Indistinguible también para `get_post_by_slug`

- **WHEN** se compara la respuesta de `get_post_by_slug` para un post protegido y para un slug inexistente
- **THEN** ambas respuestas son idénticas y no permiten deducir la existencia del post protegido

#### Scenario: El conteo de términos solo cuenta entradas públicas

- **WHEN** un término tiene entradas no publicadas o protegidas por contraseña
- **THEN** el conteo de `list_terms` no las incluye

### Requirement: Validación de argumentos y límites de respuesta

El registro de la ruta SHALL declarar `args` con `validate_callback` y `sanitize_callback` para `jsonrpc`, `method` y `params`. Cada herramienta SHALL validar sus argumentos antes de ejecutar cualquier consulta: `id` SHALL ser un entero positivo; `query` SHALL ser una cadena no vacía con longitud máxima; `slug` SHALL ser una cadena no vacía con longitud máxima; `taxonomy`, `post_type`, `orderby` y `order` SHALL validarse contra sus listas permitidas; y `page`, `per_page`, `limit` y cualquier otro entero de acotación SHALL ser enteros dentro de un rango acotado. Un argumento inválido SHALL responder JSON-RPC `-32602` (invalid params) **sin** ejecutar la consulta. `search_posts`, `list_posts` y `list_terms` SHALL admitir paginación con un **tope máximo** de página y de tamaño, y ninguna herramienta SHALL devolver más resultados ni un cuerpo mayor que el **tamaño máximo de respuesta**, de modo que no se pueda volcar el blog entero en una sola petición.

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

- **WHEN** `search_posts`, `list_posts` o `list_terms` reciben `page` o `per_page` no enteros o fuera del rango permitido
- **THEN** el sistema los rechaza o los acota al rango válido y nunca supera el tope de resultados

#### Scenario: Tope de resultados

- **WHEN** una consulta coincidiría con más resultados que el máximo permitido
- **THEN** el sistema devuelve como máximo ese tope y no agota recursos

#### Scenario: Tamaño máximo de respuesta

- **WHEN** una petición intentaría devolver un cuerpo mayor que el tamaño máximo permitido
- **THEN** el sistema recorta la respuesta al tope (o responde un error accionable) y nunca vuelca el blog entero

#### Scenario: Argumento inválido en las herramientas nuevas

- **WHEN** se invoca `get_post_by_slug` con un `slug` vacío o excesivo, o `list_posts` con un `taxonomy` fuera de la lista permitida
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: `term` sin `taxonomy`

- **WHEN** se invoca `list_posts` con `term` pero sin `taxonomy`
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

### Requirement: CORS para la consulta anónima

Al ser un servicio público de solo lectura, el endpoint SHALL responder con cabeceras CORS que permitan la consulta anónima **desde cualquier origen** para el método de lectura (`POST`), sin exigir credenciales: `Access-Control-Allow-Origin` SHALL admitir cualquier origen y `Access-Control-Allow-Credentials` SHALL NOT habilitarse (no hay credenciales que enviar). El endpoint SHALL resolver la petición de preflight `OPTIONS` con los métodos permitidos (`POST`, `OPTIONS`) y con las cabeceras permitidas **mínimas y necesarias**, que SHALL incluir al menos `Content-Type`, `Accept`, `MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name` y `Authorization`, y SHALL NOT permitir métodos con efectos. Si en el futuro se decidiera restringir el origen, la restricción SHALL quedar escrita y razonada antes de aplicarse. La respuesta de preflight `OPTIONS` SHALL incluir además la cabecera `Allow` con los métodos soportados por el recurso (`POST, OPTIONS`), coherente con `Access-Control-Allow-Methods` y con el `405` que el endpoint devuelve a `GET` y `DELETE`.

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

#### Scenario: El preflight declara los métodos permitidos

- **WHEN** llega una petición de preflight `OPTIONS`
- **THEN** la respuesta incluye `Allow: POST, OPTIONS`, coherente con `Access-Control-Allow-Methods`

### Requirement: Documentación pública del endpoint

El `README.md` del plugin SHALL documentar el endpoint como servicio público de consulta: la **URL** (`/wp-json/atareao/v1/mcp`, con el alias limpio `/mcp`), el **protocolo** (JSON-RPC 2.0), las **herramientas** de consulta disponibles (`get_latest_posts`, `get_post`, `search_posts`, `get_post_by_slug`, `list_posts` y `list_terms`) con sus argumentos, y un **ejemplo de llamada** funcional (por ejemplo `curl` con `tools/list` o `list_posts`) que cualquiera pueda copiar y ejecutar sin credenciales. La documentación SHALL indicar el carácter de solo lectura, el rate limiting y los límites de paginación/tamaño.

#### Scenario: La documentación describe el endpoint

- **WHEN** una persona consulta el `README.md` del plugin
- **THEN** encuentra la URL, el protocolo, las seis herramientas y un ejemplo de llamada

#### Scenario: Ejemplo de llamada sin credenciales

- **WHEN** una persona copia y ejecuta el ejemplo de llamada del `README.md`
- **THEN** obtiene una respuesta correcta del endpoint sin configurar credenciales

#### Scenario: La documentación lista las seis herramientas

- **WHEN** se lee el `README.md` del plugin
- **THEN** aparecen `get_latest_posts`, `get_post`, `search_posts`, `get_post_by_slug`, `list_posts` y `list_terms` con sus argumentos

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

`search_posts`, `get_latest_posts`, `list_posts` y `list_terms` SHALL aceptar un argumento opcional `post_type` que restrinja sus resultados a uno de los tipos de contenido del **dominio** del blog: `post`, `tutorial`, `capitulo`, `aplicacion`, `podcast` y `software`. La validación del argumento **explícito** SHALL realizarse contra una **lista permitida fija** con esos seis tipos, y SHALL NOT depender de «cualquier tipo público» del runtime. Si el argumento está **ausente** (o es `null`), el comportamiento SHALL conservarse: la consulta abarca todos los tipos públicos, como antes del cambio. Si el valor no pertenece a la lista permitida —incluido el tipo inexistente `application` y el tipo público no perteneciente al dominio `page`—, el sistema SHALL responder JSON-RPC `-32602` (invalid params) **sin** ejecutar la consulta. El `enum` de `post_type` que el servidor anuncia en el `inputSchema` de **todas** las herramientas de consulta (`tools/list`) SHALL ser **esa misma lista permitida**, de modo que el contrato publicado coincida con la validación.

#### Scenario: Filtro válido por tipo de contenido

- **WHEN** un cliente invoca `search_posts` con `post_type` igual a `podcast`
- **THEN** el sistema devuelve únicamente entradas de tipo `podcast`

#### Scenario: Tipo de contenido no permitido

- **WHEN** un cliente invoca una herramienta de consulta con un `post_type` que no es uno de los tipos del dominio (por ejemplo `application`)
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: Tipo público fuera del dominio no permitido

- **WHEN** un cliente invoca una herramienta de consulta con `post_type` igual a `page` (tipo público pero fuera del dominio)
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: `post_type` ausente se comporta como hasta ahora

- **WHEN** un cliente invoca `search_posts` o `get_latest_posts` sin `post_type`
- **THEN** el sistema consulta todos los tipos públicos como antes del cambio

#### Scenario: El esquema anunciado coincide con la lista permitida

- **WHEN** un cliente envía `tools/list`
- **THEN** el `enum` de `post_type` anunciado incluye exactamente los seis tipos del dominio y ningún otro

#### Scenario: Filtrado válido en las herramientas nuevas

- **WHEN** se invoca `list_posts` o `list_terms` con `post_type` igual a `podcast`
- **THEN** el sistema restringe los resultados a ese tipo

### Requirement: Exposición de metas públicas de CPT en las respuestas

Las herramientas que devuelven entradas (`get_latest_posts`, `get_post`, `get_post_by_slug`, `search_posts` y `list_posts`) SHALL incluir en el resultado las **metas públicas** del CPT correspondiente: para `podcast`, `mp3-url`, `number` y `season`; para `capitulo`, `numero-capitulo` y `tutorial-id`; y `post_views_count` cuando exista. Las respuestas SHALL incluir también las **taxonomías públicas** asociadas a la entrada. `get_post_by_slug` y `list_posts` SHALL devolver entradas con **el mismo formato** que `get_post` y `get_latest_posts`. El sistema SHALL NOT exponer metadatos internos (claves con prefijo `_`, como `_download_url`, `_repository_url` y `_version`) ni datos de usuario.

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

#### Scenario: Las herramientas nuevas devuelven el mismo formato

- **WHEN** se compara una entrada devuelta por `get_post_by_slug` o por `list_posts` con la que devolvería `get_post`
- **THEN** el formato de la entrada es el mismo, con las mismas metas y taxonomías públicas

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

### Requirement: Selección de era y conformidad del transporte Streamable HTTP

El servidor SHALL seleccionar la era por la forma de la petición: una petición con `initialize` selecciona la **era legacy**; una petición selecciona la **era moderna** si y solo si `params._meta` contiene la clave `io.modelcontextprotocol/protocolVersion`. Las cabeceras `Mcp-Method` y `MCP-Protocol-Version` SHALL NOT seleccionar la era: el SDK oficial de MCP las envía también en el dialecto 2025 posterior al `initialize`, de modo que tomarlas por un marcador de era envía a los clientes legacy reales a la era moderna —que exige metadatos por petición y responde `-32602`—; una petición que presente `Mcp-Method` sin metadatos de protocolo SHALL servirse por la **era legacy**. La mera presencia de `params._meta` SHALL NOT seleccionar la era moderna, porque `_meta` es un campo general del protocolo que también envían los clientes legacy (por ejemplo `_meta.progressToken`). En la **era moderna** el servidor SHALL: aceptar `POST` con un único mensaje JSON-RPC (petición o notificación); leer de `params._meta` (el campo general `_meta` situado dentro de `params`) el campo `io.modelcontextprotocol/protocolVersion`, obligatorio, y el campo `io.modelcontextprotocol/clientCapabilities`, opcional (si falta se trata como un objeto vacío), y, si falta `protocolVersion`, servir la petición por la era legacy sin responder `-32602`; rechazar una versión no soportada con `UnsupportedProtocolVersionError` (`-32022`) y HTTP `400`, listando en `data.supported` las versiones soportadas y en `data.requested` la solicitada; validar las cabeceras `MCP-Protocol-Version` (obligatoria y coincidente con `_meta`), `Mcp-Method` (obligatoria y coincidente con `method`) y `Mcp-Name` (obligatoria en `tools/call` y coincidente con `params.name`), respondiendo `HeaderMismatch` (`-32020`) y HTTP `400` si alguna falta o no coincide —comparando nombres de cabecera sin distinguir mayúsculas y decodificando los valores que usen el sentinel Base64 antes de comparar—; incluir `resultType` con valor `"complete"` en todo resultado; incluir `_meta.io.modelcontextprotocol/serverInfo` en todo resultado; implementar `server/discover` devolviendo en el resultado `supportedVersions` (las versiones soportadas), `capabilities`, `ttlMs` y `cacheScope`, y la identidad del servidor en `_meta.io.modelcontextprotocol/serverInfo` sin duplicarla en el nivel superior del resultado; incluir `ttlMs` y `cacheScope` en los resultados de `tools/list` y determinismo en el orden de las herramientas; responder `202 Accepted` sin cuerpo ante una notificación aceptada; responder `-32601` con HTTP `404` ante un método no implementado; y responder `405` a `GET`/`DELETE`. En la **era legacy** el servidor SHALL seguir respondiendo `initialize` con `protocolVersion`, `serverInfo` y `capabilities`, negociando la versión (la mejor versión soportada no superior a la ofrecida por el cliente; si el cliente no la indica, la más baja soportada) y manteniendo `tools/list` y `tools/call`; SHALL aceptar `notifications/initialized` y, en general, SHALL responder `202 Accepted` sin cuerpo ante cualquier mensaje sin `id` (notificación), sin emitir ningún error JSON-RPC —una notificación no puede recibir respuesta—. La validación de los metadatos obligatorios y de las cabeceras de la era moderna SHALL preceder a la aceptación del mensaje: una notificación moderna que presente cabeceras obligatorias ausentes o discordantes SHALL recibir el error correspondiente (`-32020`) y no `202 Accepted`. El servidor SHALL NOT crear sesiones ni emitir `Mcp-Session-Id`. Las versiones de protocolo soportadas y anunciadas SHALL salir de una **única fuente de verdad**. El rate limiting, la visibilidad del contenido y la validación de argumentos SHALL mantenerse sin cambios.

#### Scenario: Petición moderna servida

- **WHEN** un cliente envía una petición con `_meta` de protocolo, las cabeceras obligatorias y una versión soportada
- **THEN** el sistema la atiende y devuelve un resultado con `resultType` y `serverInfo`

#### Scenario: Metadatos obligatorios ausentes

- **WHEN** una petición omite `io.modelcontextprotocol/protocolVersion` en `params._meta`
- **THEN** el sistema la atiende por la era legacy y responde `200` con el resultado del método (o `202` si es una notificación), sin responder `-32602`

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

#### Scenario: Cliente legacy con `params._meta` genérico

- **WHEN** una petición legacy —sin `io.modelcontextprotocol/protocolVersion` en `params._meta`— incluye `params._meta` con claves ajenas al protocolo (por ejemplo `progressToken`)
- **THEN** el sistema la atiende por la **era legacy** y responde `200` con el resultado del método, sin responder `-32602`

#### Scenario: La cabecera `Mcp-Method` no selecciona la era moderna

- **WHEN** una petición presenta la cabecera `Mcp-Method` sin `io.modelcontextprotocol/protocolVersion` en `params._meta`
- **THEN** el sistema la atiende por la **era legacy** y responde `200` con el resultado del método (o `202` si es una notificación), y nunca `-32602`

#### Scenario: Cliente del SDK oficial tras negociar 2025-06-18

- **WHEN** un cliente envía `initialize` con una versión que se negocia a `2025-06-18` y después envía `notifications/initialized` —sin `params`— con las cabeceras `MCP-Protocol-Version: 2025-06-18` y `Mcp-Method: notifications/initialized`
- **THEN** el sistema la acepta con `202 Accepted` sin cuerpo y la sesión de trabajo continúa: `tools/list` con esas mismas cabeceras responde `200` con las herramientas

#### Scenario: Notificación en la era legacy

- **WHEN** una petición legacy contiene un mensaje sin `id` (por ejemplo `notifications/initialized`)
- **THEN** el sistema responde `202 Accepted` sin cuerpo y SHALL NOT responder ningún error JSON-RPC

#### Scenario: Notificación moderna con metadatos incompletos

- **WHEN** una petición moderna (con `io.modelcontextprotocol/protocolVersion` en `params._meta`) contiene una notificación (sin `id`) que omite `io.modelcontextprotocol/clientCapabilities`
- **THEN** el sistema responde `202 Accepted` sin cuerpo, porque `clientCapabilities` es opcional

#### Scenario: Capacidades del cliente opcionales

- **WHEN** una petición moderna declara `io.modelcontextprotocol/protocolVersion` en `params._meta` pero omite `io.modelcontextprotocol/clientCapabilities`
- **THEN** el sistema la atiende y responde `200` con el resultado del método, sin responder `-32602`

### Requirement: Lista permitida de taxonomías

El sistema SHALL validar el argumento `taxonomy` de `list_terms` contra una **lista permitida fija**, independiente del runtime, formada por las taxonomías públicas del dominio: `category`, `post_tag`, `tutorial_category`, `tutorial_tag`, `aplicacion_category`, `software_category`, `difficulty` y `platform`. El `enum` anunciado en el `inputSchema` SHALL ser **esa misma lista**. Una taxonomía que no pertenezca a la lista —incluida cualquier taxonomía interna o registrada por terceros— SHALL responder JSON-RPC `-32602` sin ejecutar la consulta. El sistema SHALL NOT exponer taxonomías internas ni términos de contenido no público.

#### Scenario: Taxonomía permitida

- **WHEN** se invoca `list_terms` con `taxonomy` igual a `difficulty`
- **THEN** el sistema devuelve los términos de esa taxonomía con su conteo de entradas públicas

#### Scenario: Taxonomía fuera de la lista

- **WHEN** se invoca `list_terms` con una taxonomía que no pertenece a la lista permitida
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: El `enum` de taxonomía coincide con la lista permitida

- **WHEN** se compara el `enum` de `taxonomy` anunciado en `tools/list` con la lista permitida del servidor
- **THEN** coinciden exactamente

### Requirement: Consulta de una entrada por slug (`get_post_by_slug`)

El sistema SHALL exponer una herramienta de solo lectura `get_post_by_slug` que recupere **una** entrada por su `slug`. El argumento `slug` SHALL ser obligatorio, de tipo cadena no vacía con longitud máxima acotada; `post_type` SHALL ser opcional y validarse contra la lista permitida. La herramienta SHALL devolver **el mismo formato de entrada** que `get_post` y SHALL aplicar **idénticas** reglas de visibilidad: solo entradas con `post_status` `publish`, `post_password` vacío, `post_password_required()` falso y tipo público permitido. Si no existe ninguna entrada pública con ese slug —o existe pero no es pública—, el sistema SHALL responder el **mismo error genérico** que `get_post` («Post not found») sin distinguir el motivo. Si más de una entrada pública comparte el slug dentro del alcance permitido, el sistema SHALL devolver **la más reciente** por fecha de publicación. La herramienta SHALL NOT exponer metadatos internos ni datos de usuario.

#### Scenario: Recupera una entrada publicada por su slug

- **WHEN** se invoca `get_post_by_slug` con el slug de una entrada publicada y pública
- **THEN** el sistema devuelve esa entrada con el mismo formato y reglas de visibilidad que `get_post`

#### Scenario: Slug inexistente

- **WHEN** se invoca `get_post_by_slug` con un slug que no corresponde a ninguna entrada pública
- **THEN** el sistema responde el error genérico «Post not found»

#### Scenario: Slug colisionado devuelve la más reciente

- **WHEN** el slug coincide con más de una entrada pública dentro del alcance permitido
- **THEN** el sistema devuelve la más reciente por fecha de publicación

#### Scenario: Slug obligatorio

- **WHEN** se invoca `get_post_by_slug` sin `slug`, o con un slug vacío o de longitud excesiva
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

### Requirement: Listado paginado y filtrado (`list_posts`)

El sistema SHALL exponer una herramienta de solo lectura `list_posts` que liste entradas públicas con **paginación explícita** y **filtros**. Los argumentos SHALL ser: `post_type` (opcional, uno de los tipos permitidos), `taxonomy` (opcional, de la lista permitida de taxonomías) junto con `term` (slug del término, obligatorio si `taxonomy` está presente), `author` (slug del autor, opcional), `after` y `before` (fechas ISO 8601, opcionales, para acotar por fecha de publicación), `orderby` (uno de `date`, `title` o `modified`; por defecto `date`), `order` (`asc` o `desc`; por defecto `desc`), `per_page` (entero acotado, por defecto 10) y `page` (entero mayor o igual que 1, por defecto 1). La herramienta SHALL devolver únicamente entradas públicas y SHALL responder con un objeto que incluya la lista de entradas (con el **mismo formato** que las demás herramientas) y los **metadatos de paginación**: `page`, `per_page`, `total` (número total de entradas que cumplen el filtro) y `total_pages`. Un `page` o un `per_page` fuera de rango SHALL responder `-32602` sin ejecutar la consulta. La herramienta SHALL NOT exponer metadatos internos ni datos de usuario (los datos de autor expuestos SHALL limitarse a lo público: nombre y slug).

#### Scenario: Listado paginado

- **WHEN** se invoca `list_posts` con `per_page` igual a 10 y `page` igual a 1
- **THEN** el sistema devuelve las primeras 10 entradas y los metadatos `page`, `per_page`, `total` y `total_pages`

#### Scenario: Filtro por taxonomía y término

- **WHEN** se invoca `list_posts` con `taxonomy` igual a `tutorial_category` y `term` igual al slug de un término
- **THEN** el sistema devuelve solo las entradas públicas de ese término

#### Scenario: Filtro por autor

- **WHEN** se invoca `list_posts` con `author` igual al slug de un autor
- **THEN** el sistema devuelve solo las entradas públicas de ese autor

#### Scenario: Filtro por rango de fechas

- **WHEN** se invoca `list_posts` con `after` y `before`
- **THEN** el sistema devuelve solo las entradas publicadas dentro de ese rango

#### Scenario: Orden configurable

- **WHEN** se invoca `list_posts` con `orderby` igual a `title` y `order` igual a `asc`
- **THEN** el sistema devuelve las entradas ordenadas por título ascendente

### Requirement: Recorrido de taxonomías (`list_terms`)

El sistema SHALL exponer una herramienta de solo lectura `list_terms` que liste los términos de una taxonomía permitida con el **número de entradas públicas** de cada uno. El argumento `taxonomy` SHALL ser obligatorio y validarse contra la lista permitida de taxonomías; `post_type` SHALL ser opcional y validarse contra la lista permitida de tipos; y la herramienta SHALL admitir paginación (`per_page`, con un entero acotado y por defecto 50) y orden (`orderby` uno de `count` o `name`; `order` uno de `asc` o `desc`). La herramienta SHALL devolver únicamente términos de taxonomías permitidas y SHALL responder con un objeto que incluya la lista de términos —cada uno con su `name`, su `slug` y su conteo de entradas públicas— y los **metadatos de paginación**. El sistema SHALL NOT exponer taxonomías internas, términos sin contenido público cuando así se determine, ni datos internos. Un `taxonomy` ausente o fuera de la lista permitida SHALL responder `-32602` sin ejecutar la consulta.

#### Scenario: Lista términos con conteo

- **WHEN** se invoca `list_terms` con `taxonomy` igual a `tutorial_category`
- **THEN** el sistema devuelve cada término con su `name`, su `slug` y el número de entradas públicas

#### Scenario: `taxonomy` obligatorio

- **WHEN** se invoca `list_terms` sin `taxonomy`
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: Conteo solo de entradas públicas

- **WHEN** un término tiene entradas no publicadas o protegidas por contraseña
- **THEN** el conteo del término no las incluye

#### Scenario: Orden por número de entradas

- **WHEN** se invoca `list_terms` con `orderby` igual a `count` y `order` igual a `desc`
- **THEN** el sistema devuelve los términos ordenados por número de entradas públicas descendente
