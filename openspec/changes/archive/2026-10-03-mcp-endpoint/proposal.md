# Proposal: Servicio público de consulta MCP

## Why

El plugin `atareao-functionality` expone un **servidor MCP** por REST en `atareao/v1/mcp` (`wp-content/plugins/atareao-functionality/includes/class-mcp.php:38-45`) que habla JSON-RPC 2.0 y ofrece tres herramientas (`get_latest_posts`, `get_post`, `search_posts`). El objetivo del responsable del proyecto es **que cualquiera pueda consultar el blog vía MCP**: es un **servicio público de consulta por diseño**, no un endpoint interno. El endpoint se registra con `'permission_callback' => '__return_true'` y `\WP_REST_Server::CREATABLE`, y esa apertura es correcta mientras solo devuelva contenido ya publicado. Para que el servicio sea usable y sostenible hoy le faltan piezas (documentación, descubrimiento, rate limiting, límites de respuesta, CORS) y arrastra un defecto verificado línea a línea:

1. **Fuga de posts protegidos por contraseña.** `getPost()` solo comprueba `'publish' !== $post->post_status` (`class-mcp.php:197`) y `formatPost()` aplica `apply_filters('the_content', $post->post_content)` sobre el **contenido crudo** (`class-mcp.php:245`), **sin llamar nunca a `post_password_required()`**. Un POST anónimo con `{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_post","arguments":{"id":<id>}}}` devuelve el contenido íntegro de un post protegido por contraseña.
2. **Servicio público sin control de abuso ni de respuesta.** Sin rate limiting ni límites, `search_posts` (`class-mcp.php:208-227`, `posts_per_page => 10` fijo) y `get_latest_posts` (`class-mcp.php:168-188`) permiten enumerar y cosechar sin freno todo el contenido público, y no validan los argumentos: un `id` no entero se fuerza con `intval()` (`class-mcp.php:148`) y `query` se pasa tal cual a `WP_Query` (`class-mcp.php:214`).

## What Changes

- **Servicio público de consulta.** Las tres herramientas (`get_latest_posts`, `get_post`, `search_posts`) **siguen siendo públicas, sin autenticación**, porque el objetivo es que cualquiera consulte el blog vía MCP; solo exponen contenido ya publicado. La ruta REST `atareao/v1/mcp`, el método `CREATABLE`, el meta de descubrimiento `rel="mcp-server"` y los nombres de las herramientas **no cambian**.
- **Descubrimiento para clientes MCP genéricos.** `initialize` responde con `protocolVersion`, `serverInfo` y `capabilities`, y `tools/list` describe las tres herramientas con `name`, `description`, `inputSchema` y su carácter de solo lectura, de modo que un cliente MCP se integre **sin configuración especial**.
- **Documentación pública.** El `README.md` del plugin documenta la URL (`/wp-json/atareao/v1/mcp`), el protocolo (JSON-RPC 2.0), las tres herramientas de consulta con sus argumentos y un **ejemplo de llamada** copiable sin credenciales.
- **Rate limiting razonable y explícito.** Ventana fija de 60 segundos y tope por defecto de **60 peticiones/minuto por IP** (ajustable), holgado para un cliente MCP legítimo consultando el blog y suficiente para frenar el scraping; respuesta **429** con `Retry-After` y sin filtrar información interna.
- **Límites de respuesta.** `search_posts` con paginación acotada (tope de página y de tamaño) y un **tamaño máximo de respuesta**: no se puede volcar el blog entero en una sola petición.
- **CORS coherente con un servicio público.** La consulta anónima se permite **desde cualquier origen** para el método de lectura (`POST`), sin `Access-Control-Allow-Credentials`, con preflight `OPTIONS` resuelto y sin métodos con efectos. Si se decidiera restringir, quedaría escrito y razonado.
- **Corrección de `get_post`.** Respetar `post_password_required($post)`: un post protegido por contraseña **nunca** devuelve contenido (ni con credencial ni sin ella) y responde con el **mismo error genérico** que un post inexistente. Filtrar por `post_status => 'publish'` **y** `post_password => ''`, y obtener el contenido con `get_post_field('post_content', $post)`/`get_the_content()` en lugar de aplicar el filtro `the_content` sobre el crudo; los filtros solo se aplican tras confirmar que el post no requiere contraseña.
- **Sin datos internos.** `get_latest_posts` y `search_posts` excluyen los posts protegidos por contraseña (`post_password => ''`) y se restringen a tipos públicos; el endpoint no expone borradores, contenido privado, metadatos internos, rutas del servidor ni datos de usuarios.
- **Credencial reservada a herramientas futuras con efectos.** Si en el futuro se añaden herramientas de escritura, administración o acceso a datos internos, **sí** requerirán credencial (Application Password/`Bearer`) y capacidad, y rechazarán las peticiones anónimas. Las tres herramientas actuales de consulta no la exigen.
- **Validación de `args`.** `register_rest_route` declara `args` con `validate_callback`/`sanitize_callback` para `jsonrpc`, `method` y `params`; cada herramienta valida sus argumentos (`id` entero positivo; `query` no vacío y con longitud máxima; `page`/`per_page` acotados). Argumento inválido → error JSON-RPC `-32602` sin ejecutar la consulta.
- **Errores que no revelan información interna.** Respuestas genéricas y códigos JSON-RPC estándar; nada de rutas de fichero, errores de base de datos, trazas ni la existencia de un recurso. El detalle técnico se registra en servidor, sin credenciales.
- **Sin tocar el sitio público.** No se renombra ni se borra ninguna opción, ruta REST ni hook; la ruta, las herramientas y el meta de descubrimiento se conservan. El sitio público (HTML, `/tools/`, analítica, login) no cambia.

## Capabilities

### New Capabilities

- `mcp-server`: servicio público de consulta MCP por REST (herramientas de consulta sin credencial), descubrimiento (`initialize`/`tools/list`) para clientes genéricos, documentación pública, rate limiting por IP, respeto de contraseñas y visibilidad de posts, CORS y límites de respuesta, regla de credencial para herramientas futuras con efectos, y errores que no revelan información interna.

### Modified Capabilities

Ninguna.

## Impact

- **Archivos a modificar (solo en la fase de implementación, tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-mcp.php` (descubrimiento, rate limiting, CORS, corrección de `get_post`/visibilidad, validación y límites de respuesta; mantiene el acceso público de las herramientas de consulta).
  - `wp-content/plugins/atareao-functionality/README.md` (documentación pública de la URL, el protocolo, las herramientas y un ejemplo de llamada).
  - Nuevo (spec al archivar): `openspec/specs/mcp-server/spec.md` a partir del delta `specs/mcp-server/spec.md`.
- **Rutas y contratos que NO se tocan:** `atareao/v1/mcp` (`CREATABLE`), los nombres `get_latest_posts`/`get_post`/`search_posts`, el meta `rel="mcp-server"` de `wp_head` y JSON-RPC 2.0 como transporte. No se renombra ni se borra ninguna opción ni hook.
- **Compatibilidad:** los clientes MCP legítimos (integraciones con IA) siguen consumiendo las tres herramientas de consulta **sin credenciales**. Solo una eventual herramienta futura con efectos exigiría Application Password/`Bearer`, y eso se documenta como regla.
- **No cambia:** el sitio público, el microsite `/tools/`, la analítica, el login/logout ni las notificaciones Matrix.
- **Dependencias:** ninguna nueva (funciones nativas de WordPress: `get_post_field`, `post_password_required`, transients y el sistema de autenticación REST).
- **Compatibilidad técnica:** PHP 8.3, PSR12, WordPress 6.0+.
