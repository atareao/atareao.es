# MCP Server Delta

## Purpose

Este delta **amplía la superficie de herramientas** del servidor MCP público de tres a seis, para que un agente no solo consulte lo más reciente, una entrada por ID o una búsqueda de texto, sino que pueda **navegar** el archivo estructurado del blog: buscar una entrada **por slug**, **listar** con filtros y **paginación**, y **recorrer las taxonomías** con el número de entradas por término. Lo motiva una auditoría externa (ficha de Glama `es.atareao/blog`) que puntúa el catálogo en 3,7/5 y señala como dimensiones flojas «Tool Count» y «Completeness» por la ausencia de un listado paginado, de navegación por taxonomía y de búsqueda por slug.

No cambia nada del servicio salvo su catálogo de herramientas: se conservan la ruta REST legacy, el endpoint limpio `/mcp`, el transporte JSON-RPC 2.0, el rate limiting por IP, el CORS anónimo, la validación de argumentos y los invariantes de **solo lectura** y **visibilidad pública**. Las tres herramientas existentes (`get_latest_posts`, `get_post`, `search_posts`) **conservan nombre y comportamiento**; solo se mejora su descripción publicada.

## MODIFIED Requirements

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

## ADDED Requirements

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
