# MCP Server Delta

## Purpose

Esta capability define y endurece el **servicio público de consulta MCP** que `atareao-functionality` expone por REST en `atareao/v1/mcp` (`\WP_REST_Server::CREATABLE`, JSON-RPC 2.0, herramientas `get_latest_posts`, `get_post` y `search_posts`). El objetivo explícito es **que cualquiera pueda consultar el blog vía MCP sin credenciales**: las tres herramientas son de solo lectura y exponen únicamente contenido ya publicado. Para que el servicio sea usable y sostenible, la capability cubre el descubrimiento (`initialize`/`tools/list`) para clientes MCP genéricos, la documentación pública del endpoint, un rate limiting razonable y explícito que no estorbe el uso legítimo, límites de paginación y de tamaño de respuesta, CORS coherente con un servicio público de solo lectura y la garantía de no exponer nunca contenido no público. Corrige además la fuga de posts protegidos por contraseña —`get_post` respeta `post_password_required()` y no aplica el filtro `the_content` sobre el contenido crudo—, alinea la visibilidad de los listados con los posts públicos, valida los argumentos y devuelve errores genéricos que no revelan información interna. Cualquier herramienta **futura con efectos** exigirá autenticación (Application Password/`Bearer`) y capacidad. La ruta REST, los nombres de las herramientas, el meta de descubrimiento y el transporte JSON-RPC se conservan; el sitio público no cambia.

## ADDED Requirements

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

Al ser un servicio público de solo lectura, el endpoint SHALL responder con cabeceras CORS que permitan la consulta anónima **desde cualquier origen** para el método de lectura (`POST`), sin exigir credenciales: `Access-Control-Allow-Origin` SHALL admitir cualquier origen y `Access-Control-Allow-Credentials` SHALL NOT habilitarse (no hay credenciales que enviar). El endpoint SHALL resolver la petición de preflight `OPTIONS` con los métodos permitidos (`POST`, `OPTIONS`) y las cabeceras permitidas mínimas (por ejemplo `Content-Type`), y SHALL NOT permitir métodos con efectos. Si en el futuro se decidiera restringir el origen, la restricción SHALL quedar escrita y razonada antes de aplicarse.

#### Scenario: Consulta anónima desde un origen distinto

- **WHEN** un cliente anónimo consulta el endpoint desde un origen distinto
- **THEN** la respuesta incluye `Access-Control-Allow-Origin` que admite ese origen y no habilita credenciales

#### Scenario: Preflight `OPTIONS`

- **WHEN** el navegador envía una petición de preflight `OPTIONS`
- **THEN** el sistema responde con los métodos permitidos (`POST`, `OPTIONS`) y las cabeceras mínimas necesarias

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
