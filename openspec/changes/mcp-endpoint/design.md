# Design: Servicio público de consulta MCP

## Context

`wp-content/plugins/atareao-functionality/includes/class-mcp.php` implementa un **servidor MCP** mínimo sobre la REST API de WordPress. La clase `\Atareao\MCP` engancha `rest_api_init` → `registerRoutes()` y `wp_head` → `addDiscoveryMeta()`, y publica un único endpoint que habla **JSON-RPC 2.0** con tres herramientas de solo lectura:

| Elemento | Evidencia | Descripción |
|---|---|---|
| Ruta | `class-mcp.php:38-46` | `register_rest_route('atareao/v1', '/mcp', ['methods' => CREATABLE, 'callback' => handleRequest, 'permission_callback' => '__return_true'])` |
| Descubrimiento | `class-mcp.php:28-31` | `<link rel="mcp-server" type="application/json" href="…/atareao/v1/mcp">` en `wp_head` |
| Transporte | `class-mcp.php:55-77` | JSON-RPC 2.0: `tools/list`, `tools/call`; errores `-32700`/`-32601`/`-32000` |
| Herramientas | `class-mcp.php:84-126` | `get_latest_posts`, `get_post`, `search_posts` |
| Listados | `class-mcp.php:168-188`, `208-227` | `WP_Query` con `post_status => 'publish'` y `posts_per_page` fijo (5 y 10) |
| Detalle | `class-mcp.php:193-203`, `232-254` | `get_post()` comprueba solo `'publish' !== $post->post_status`; `formatPost()` aplica `apply_filters('the_content', $post->post_content)` |

**Defecto 1 — fuga de posts con contraseña.** `getPost()` valida únicamente el estado `publish` (`class-mcp.php:197`) y `formatPost(true)` toma `$post->post_content` **crudo** y le aplica `apply_filters('the_content', …)` (`class-mcp.php:245`). En ningún punto se llama a `post_password_required($post)`. Resultado: un `tools/call` anónimo a `get_post` con el `id` de una entrada protegida devuelve su contenido completo.

**Defecto 2 — servicio público al que le faltan piezas.** El `permission_callback` es `__return_true` (`class-mcp.php:44`) y la lectura pública es deliberada y correcta, pero hoy el servicio no es usable ni sostenible: **no hay rate limiting**, ni límites de respuesta, ni documentación pública, ni un `initialize`/`tools/list` pensado para clientes genéricos, ni CORS explícito. Además los argumentos no se validan: `id` se normaliza con `intval()` (`class-mcp.php:148`) y `query` llega sin cota a `WP_Query` (`class-mcp.php:214`).

**Defecto 3 — errores reveladores y estados HTTP incorrectos.** `errorResponse()` devuelve siempre HTTP `200` (`class-mcp.php:285`) incluso para errores, y `getPost()` distingue implícitamente entre «no existe» y «no publicable» con el mismo mensaje, pero el fallo de contraseña pasaría desapercibido. Los errores de red/BD podrían aflorar mensajes internos.

**Restricciones del repo.** WordPress 8.3 / PHP 8.3, PSR12, **sin framework de tests ni build tools**: la verificación es `just php-lint` + `just phpcs` (baseline), un **arnés externo de stubs** en `/tmp` (no versionado) y E2E manual en producción. No se renombra ni se borra ninguna opción, ruta REST ni hook. La implementación solo arranca tras la aprobación de este change.

## Goals / Non-Goals

**Goals**

- Cumplir el objetivo **«que cualquiera pueda consultar el blog vía MCP»**: un servicio público de consulta usable y sostenible, no un endpoint interno.
- Mantener el endpoint MCP como **lectura pública de solo consulta**, con **rate limiting por IP** y sin exponer nunca contenido no público.
- Cerrar la fuga de contenido protegido corrigiendo `get_post` (`post_password_required()`), no cerrando la puerta.
- Alinear la visibilidad de `get_latest_posts`/`search_posts` con la política de posts públicos.
- Permitir que un cliente MCP genérico se integre **sin configuración especial**: `initialize`/`tools/list` correctos y documentación pública en el `README.md`.
- Ofrecer CORS coherente con un servicio público de solo lectura (consulta anónima desde cualquier origen).
- Validar los argumentos y acotar la paginación y el **tamaño de respuesta** para que el endpoint no sea un vector de agotamiento ni permita volcar el blog entero.
- Errores genéricos, con el estado HTTP correcto, que no revelen información interna.
- Conservar intactos la ruta, las herramientas, el meta de descubrimiento y el sitio público.

**Non-Goals**

- No se exige credencial a las tres herramientas actuales de consulta: el endpoint de lectura de contenido público sigue siendo anónimo.
- No se cambia el transporte JSON-RPC 2.0 ni los nombres de las herramientas.
- No se añaden nuevas herramientas ni se eliminan las existentes.
- No se toca el sitio público, `/tools/`, la analítica, el login ni Matrix.
- No se renombra ni se borra ninguna opción, ruta REST ni hook.
- No se introducen dependencias, build tools ni framework de tests.

## Decisions

### Decisión 1: Lectura pública por diseño; la defensa va en el filtrado, no en la puerta

El endpoint `atareao/v1/mcp` **es un servicio público de consulta por diseño**: el objetivo es que **cualquiera pueda consultar el blog vía MCP**, y las tres herramientas (`get_latest_posts`, `get_post` y `search_posts`) se pueden invocar **sin credenciales** y son de **solo consulta**. El `permission_callback` se mantiene deliberadamente abierto para este canal de integración, porque las herramientas solo exponen **contenido ya publicado en la web**. La causa real del hallazgo de seguridad no era la ausencia de autenticación, sino que `get_post` aplicaba `apply_filters('the_content', $post->post_content)` sobre el contenido **crudo** sin llamar a `post_password_required()`: el fallo está en la **selección y filtrado del contenido**, no en la puerta. Por eso la defensa se coloca donde corresponde: no se devuelve nunca contenido no público (borradores, privados, tipos no públicos, posts protegidos por contraseña) y se mantiene el rate limiting como control de abuso.

**Por qué esta opción y no cerrar el endpoint.** Es la decisión del responsable del proyecto: el valor de un servidor MCP es precisamente ser consumible por integraciones con IA sin fricción de credenciales cuando solo lee contenido público. Exigir autenticación para leer lo mismo que ya sirve el HTML no añade confidencialidad real —el contenido es público— y sí rompe el caso de uso. El riesgo a cerrar es la **exposición de contenido no público**, y eso se resuelve en la lógica de visibilidad (Decisión 4) y no con una puerta. El abuso de recursos se resuelve con el **rate limiting por IP** (Decisión 3). Si el contenido fuera sensible o las herramientas tuvieran efectos, el razonamiento sería el contrario y exigirían credencial.

**Consecuencias:** el canal sigue abierto y usable por clientes anónimos; la garantía de «solo público» pasa a ser una propiedad verificable y testeable de las herramientas, no una suposición. La meta de descubrimiento `rel="mcp-server"` sigue publicándose igual.

**Alternativa descartada:** autenticación obligatoria en todas las peticiones. Rompe el caso de uso de lectura pública sin aportar confidencialidad (el contenido ya es público) y desplaza la defensa desde el punto correcto —el filtrado del contenido— a una puerta genérica.

### Decisión 2: La credencial se reserva a herramientas futuras con efectos

Mientras las herramientas sean de **solo consulta** sobre contenido público, el endpoint no exige credencial. Cualquier **herramienta futura con efectos** (escritura, administración o acceso a datos internos) SHALL exigir autenticación (Application Password / `Bearer` sobre HTTPS) y la capacidad adecuada, y SHALL rechazar las peticiones anónimas. Esta frontera queda escrita como requirement para que la puerta se abra solo cuando el riesgo lo justifique, sin reabrir el debate sobre las tres herramientas actuales.

**Consecuencias:** la política de acceso es explícita y evolutiva: pública para lectura de lo público, autenticada para lo que escribe o accede a datos internos. La verificación comprueba además que ninguna de las tres herramientas actuales tiene efectos.

**Alternativa descartada:** dejar la política sin especificar y decidir caso por caso al añadir herramientas. Invita a exponer por descuido una herramienta con efectos; la regla «efectos ⇒ credencial» la evita.

### Decisión 3: Rate limiting por IP con transients

El rate limiting se aplica al recibir la petición (antes de ejecutar la herramienta) con un contador de **ventana fija de 60 segundos** por **IP de origen hasheada** (`hash('sha256', $ip)`), persistido en un transient de WordPress con TTL igual a la ventana. El tope por defecto es de **60 peticiones por minuto** y es ajustable por filtro/constante, de modo que se pueda subir o bajar sin editar el cuerpo del código. Ese valor es holgado para un cliente MCP consultando el blog (una consulta interactiva rara vez supera unas pocas peticiones por minuto) y suficiente para frenar el scraping masivo. Superado el tope se responde **HTTP 429** con cabecera `Retry-After` y un error JSON-RPC genérico, sin ejecutar la herramienta. La IP se toma del valor que el servidor ve como cliente y **no** se confía en `X-Forwarded-For` salvo configuración explícita, para que un atacante no falsee su clave rotando cabeceras.

**Consecuencias:** acota el scraping y el consumo de `WP_Query` por cliente en un canal que es público por diseño; el uso de transients evita tablas nuevas y respeta el ciclo de vida de WordPress. El hasheo de la IP evita almacenar direcciones en claro.

**Alternativa descartada:** limitar solo por usuario autenticado. El endpoint es anónimo, así que no habría clave de usuario; el límite por IP es la unidad natural en un canal abierto.

### Decisión 4: Respetar `post_password_required()` y no aplicar `the_content` sobre el crudo

`get_post` SHALL:
1. Obtener el post y verificar `post_status === 'publish'` **y** `post_password === ''` **y** que `post_password_required($post)` sea falso.
2. Si el post no existe, no está publicado o requiere contraseña, devolver un **error genérico idéntico** («Post not found»), sin distinguir el motivo.
3. Obtener el contenido con `get_post_field('post_content', $post)` (o `get_the_content()`), **no** con `apply_filters('the_content', $post->post_content)` sobre el crudo; los filtros de presentación solo se aplican una vez confirmado que el post no requiere contraseña.

**Por qué `post_password_required()`.** Es la función canónica de WordPress para decidir si un visitante puede ver el contenido de un post protegido: comprueba la cookie de contraseña y el propio campo `post_password`. Llamarla es la única forma de garantizar que la lógica de visibilidad del endpoint coincide con la del tema. Además, **no** se debe aplicar el filtro `the_content` a un post protegido: ejecutaría filtros, shortcodes y oEmbed sobre contenido que no debe exponerse. Verificar primero y filtrar después cierra la fuga por los dos extremos.

**Consecuencias:** el endpoint deja de ser una puerta trasera al contenido protegido y su política de visibilidad coincide con la del sitio público.

**Alternativa descartada:** limitarse a comprobar `post_password === ''`. No contempla la cookie de acceso y puede divergir del comportamiento del tema; `post_password_required()` es la comprobación correcta.

### Decisión 5: Visibilidad coherente en los listados

`get_latest_posts` y `search_posts` añaden `'post_password' => ''` a su `WP_Query` y se restringen a **tipos públicos** (`get_post_types(['public' => true])`) para no listar contenido protegido ni tipos no públicos. `get_latest_posts` conserva su tope de 5 y `search_posts` su tope base de 10, ahora con paginación acotada.

**Consecuencias:** la enumeración no revela títulos ni extractos de posts protegidos; el comportamiento observable para contenido público no cambia.

**Alternativa descartada:** filtrar solo en `get_post`. Los listados seguirían revelando la existencia y metadatos de posts protegidos.

### Decisión 6: Validación de argumentos, paginación acotada y tamaño máximo de respuesta

`register_rest_route` declara `args` con `validate_callback`/`sanitize_callback` para `jsonrpc` (`'2.0'`), `method` (string) y `params` (objeto). En `callTool`, cada herramienta valida sus argumentos: `id` entero **positivo**; `query` string **no vacío** y con longitud máxima; `page`/`per_page` enteros dentro de rango. `search_posts` acepta `page`/`per_page` con un **tope máximo** (página y tamaño acotados) y `get_latest_posts` admite un `limit` acotado. Además, ninguna herramienta SHALL devolver un cuerpo mayor que un **tamaño máximo de respuesta**: si con los topes de paginación el cuerpo aún excediera ese tamaño, se recorta (o se responde un error accionable). Un argumento inválido responde JSON-RPC `-32602` (invalid params) **sin** ejecutar la consulta.

**Consecuencias:** desaparece el `intval()` silencioso sobre entradas no enteras y se cierran el agotamiento por paginación ilimitada y el volcado del blog entero en una sola petición; el coste por petición queda acotado por arriba.

**Alternativa descartada:** confiar en el `args` de `register_rest_route` para los argumentos anidados de cada herramienta. Los `params` son un objeto JSON libre; la validación por herramienta es la que conoce su esquema. También se descarta no poner tope de tamaño: aun con paginación acotada, un extracto o un post largo podría producir respuestas desmesuradas.

### Decisión 7: Errores genéricos y sin información interna

Los errores usan códigos JSON-RPC estándar y mensajes **genéricos** (sin rutas, nombres de tabla, trazas, IDs internos ni la existencia concreta de un recurso), y respetan el estado HTTP correcto: **429** para el rate limit y **401/403** cuando en el futuro una herramienta con efectos rechace una petición sin credencial o sin capacidad. El detalle técnico se registra en servidor (sin credenciales). Un post inexistente y uno protegido por contraseña producen respuestas **indistinguibles**.

**Consecuencias:** no se filtra información explotable por mensajes de error; la depuración se apoya en el log del servidor.

**Alternativa descartada:** reutilizar los mensajes actuales con HTTP 200. Mantiene el estado incorrecto y arriesga fuga de detalles internos.

### Decisión 8: Descubrimiento para clientes genéricos y documentación pública

`initialize` responde con `protocolVersion`, `serverInfo` y `capabilities` que declaran el soporte de herramientas; `tools/list` describe cada herramienta con `name`, `description`, `inputSchema` y su carácter de **solo lectura**, indicando que devuelven contenido público ya publicado. El `README.md` del plugin documenta la **URL** (`/wp-json/atareao/v1/mcp`), el **protocolo** (JSON-RPC 2.0), las **tres herramientas** con sus argumentos y un **ejemplo de llamada** (`curl`) copiable sin credenciales, además del rate limiting y los límites.

**Por qué.** El objetivo es que cualquiera consulte el blog: sin un `initialize`/`tools/list` correcto y sin documentación, un cliente MCP genérico no puede integrarse «sin configuración especial» y el servicio queda inutilizable en la práctica. El coste de documentar es mínimo y convierte el endpoint en un servicio consumible de verdad.

**Consecuencias:** un cliente MCP genérico descubre las herramientas y las invoca directamente; una persona puede probar el endpoint con un `curl` del README. La documentación forma parte de la definición de «hecho» del change.

**Alternativa descartada:** dar por supuesto que el cliente «ya sabrá» las herramientas y omitir la documentación. Contradice el objetivo de servicio público y deja el endpoint sin entrada para quien no conoce su esquema.

### Decisión 9: CORS abierto para la consulta anónima de solo lectura

El endpoint responde con cabeceras CORS que permiten la consulta anónima **desde cualquier origen** para el método de lectura (`POST`): `Access-Control-Allow-Origin` admite cualquier origen y `Access-Control-Allow-Credentials` **no** se habilita (no hay credenciales que enviar). El preflight `OPTIONS` se resuelve con los métodos `POST`/`OPTIONS` y las cabeceras mínimas (por ejemplo `Content-Type`), sin permitir métodos con efectos. WordPress ya emite CORS en la REST API; el requirement asegura que nuestra ruta no lo rompe.

**Por qué.** Un servicio público de consulta debe poder invocarse desde una página o una herramienta de navegador de cualquier origen. Como no hay cookies ni credenciales, permitir `*` no introduce riesgo de sesión; el contenido es público y de solo lectura. Restringirlo complicaría la integración sin proteger nada.

**Consecuencias:** cualquier origen puede consultar el endpoint; no se exponen credenciales y no se habilitan métodos con efectos. Si en el futuro el servicio se restringiera (por ejemplo, si aparecen herramientas con efectos), la restricción se escribirá y razonará antes de aplicarla.

**Alternativa descartada:** CORS restrictivo por defecto. Frustra el objetivo de servicio público y añade configuración para el cliente sin ganancia de seguridad, dado que no hay credenciales.

## Risks / Trade-offs

- **[Canal de lectura público]** → El endpoint es consumible sin credenciales por diseño; un actor abusivo podría cosechar el contenido publicado. Mitigado porque solo expone contenido **ya público** (igual que el HTML) con el rate limiting por IP (Decisión 3); no hay contenido sensible detrás.
- **[CORS abierto a cualquier origen]** → Permitir `*` en un servicio público de solo lectura no añade riesgo de sesión porque no se envían credenciales. Mitigado no habilitando `Access-Control-Allow-Credentials` y no permitiendo métodos con efectos (Decisión 9).
- **[Respuestas desmesuradas]** → Aun con paginación acotada, un post largo o un extracto podrían producir cuerpos grandes. Mitigado con el tamaño máximo de respuesta (Decisión 6).
- **[Documentación desactualizada]** → Si las herramientas cambian y el README no, el servicio se vuelve confuso. Mitigado considerando la documentación como parte de «hecho» del change y verificándola en el arnés/E2E (Decisión 8).
- **[Herramienta futura con efectos expuesta por descuido]** → Mitigado con el requirement explícito de que toda herramienta con efectos exija credencial y capacidad (Decisión 2).
- **[Rate limiting por IP detrás de nginx]** → El contenedor WordPress ve la IP de nginx, no la del cliente. Mitigado no confiando en cabeceras de proxy salvo configuración explícita y documentándolo; si se necesita la IP real, se configura de forma consciente.
- **[Falsos positivos del rate limit en clientes legítimos intensivos]** → Una integración puede superar el tope. Mitigado con un tope razonable y `Retry-After` para que el cliente reintente; el límite es configurable.
- **[Regresión en los listados por el filtro de tipos públicos]** → Restringir a tipos públicos podría excluir algún tipo que antes aparecía. Mitigado verificando con el arnés que las entradas, páginas y tipos públicos del sitio siguen apareciendo; el objetivo es excluir lo no público.
- **[Verificación sin framework de tests]** → No hay tests automatizados en el repo. La verificación es estática (`just php-lint`, `just phpcs`), con un **arnés externo de stubs** en `/tmp` (no versionado) y E2E manual en producción.

## Migration Plan

1. Implementar el servicio público en `class-mcp.php` tras la aprobación del change (descubrimiento, rate limiting, CORS, corrección de `get_post`/visibilidad, validación y límites de respuesta, errores), **sin** añadir autenticación a las herramientas de consulta.
2. Documentar el endpoint en el `README.md` (URL, protocolo, herramientas y ejemplo de llamada).
3. Verificar con el arnés externo de stubs: `initialize`/`tools/list`, invocación anónima de las tres herramientas con solo contenido público, post protegido sin contenido con y sin credencial, rate limit 429 (60/min), CORS, validación de args y límites de respuesta, errores genéricos.
4. Ejecutar el análisis estático (`just php-lint`, `just phpcs`) sin empeorar el baseline.
5. E2E en producción: `initialize`/`tools/list` con un cliente MCP genérico; POST anónimo a las tres herramientas → 200 con solo contenido público; ejemplo del README ejecutado tal cual; `get_post` de un post público correcto; `get_post` de un post protegido → error genérico indistinguible del inexistente (con y sin credencial); superar el rate limit → 429 con `Retry-After`; consulta desde otro origen con CORS correcto; comprobar que el sitio público no cambia.
6. Desplegar y comprobar que la documentación del `README.md` coincide con el comportamiento real.

**Rollback:** revertir el commit que modifica `class-mcp.php`. La ruta, las herramientas y el meta no cambian, así que un rollback restaura el comportamiento anterior sin migraciones de datos.

## Verification

> El repositorio **no tiene framework de tests** ni build tools. La verificación combina análisis estático, un **arnés externo de stubs** que vive solo en `/tmp/opencode/mcp-harness/` (fuera del repo y no versionado) y E2E manual en producción. La implementación arranca **solo tras la aprobación del usuario**.

- **Lint**: `just php-lint` → 0 errores.
- **phpcs**: `just phpcs` (theme+plugin) sin empeorar el baseline. Baseline medido (2026-10-03) en theme+plugin: **752 errores / 426 warnings**. Objetivo de delta **+0 errores** (se espera el warning `PSR1.Files.SideEffects` ya presente por la guarda `ABSPATH`).
- **Arnés externo de stubs** (`/tmp/opencode/mcp-harness/`, no versionado): define de forma controlable las funciones de WordPress usadas (`register_rest_route`, `get_post`, `post_password_required`, `get_post_field`, `get_the_content`, `get_post_types`, `WP_Query`, `get_transient`/`set_transient`, `wp_json_encode`, `apply_filters`, etc.) y comprueba:
  1. **Lectura pública**: una invocación anónima de `get_latest_posts`, `get_post` y `search_posts` se atiende sin credenciales y devuelve solo contenido público; las tres herramientas no producen efectos.
  2. **Descubrimiento**: `initialize` devuelve `protocolVersion`, `serverInfo` y `capabilities`; `tools/list` describe las tres herramientas con `inputSchema` y carácter de solo lectura; un cliente genérico puede integrarse.
  3. **Rate limiting**: por debajo de 60/min atiende; superado responde 429 con `Retry-After`; el contador de una IP no afecta a otra; el tope es ajustable.
  4. **CORS**: la respuesta admite cualquier origen y no habilita credenciales; el preflight `OPTIONS` resuelve `POST`/`OPTIONS`.
  5. **Contraseña**: un post protegido por contraseña no devuelve contenido ni con credencial ni sin ella y su error es indistinguible del de un post inexistente; un post publicado sin contraseña devuelve su contenido filtrado; no se llama a `apply_filters('the_content', …)` sobre un post protegido.
  6. **Visibilidad y datos internos**: `get_latest_posts` y `search_posts` excluyen posts protegidos y tipos no públicos; la respuesta no contiene borradores, metadatos internos, rutas del servidor ni datos de usuarios.
  7. **Validación y límites**: `id` no entero/negativo → `-32602` sin consulta; `query` vacía o excesiva → `-32602`; `per_page`/`page` fuera de rango acotados o rechazados; `search_posts` no devuelve más del tope ni un cuerpo mayor que el tamaño máximo.
  8. **Errores**: los mensajes no contienen rutas, trazas, errores de BD ni la existencia del recurso; los fallos internos solo se registran en servidor.
  9. **Documentación**: el `README.md` contiene URL, protocolo, las tres herramientas y un ejemplo de llamada sin credenciales.
- **E2E manual en producción**: `initialize`/`tools/list` con un cliente MCP genérico; POST anónimo a las tres herramientas → 200 con solo contenido público; ejemplo del `README.md` ejecutado tal cual; 429 al superar el límite por IP; `get_post` de un post público correcto y de uno protegido con error genérico (con y sin credencial); CORS correcto desde otro origen; el sitio público (HTML, `/tools/`, analítica, login, Matrix) no cambia.
- **No-regresión**: la ruta `atareao/v1/mcp`, los nombres de las herramientas, el meta `rel="mcp-server"` y el transporte JSON-RPC se conservan; ninguna opción, ruta REST ni hook se renombra o borra.
- `openspec validate mcp-endpoint --strict` sin hallazgos.

## Open Questions

Ninguna. El carácter de servicio público de consulta («que cualquiera consulte el blog vía MCP»), el descubrimiento y la documentación pública, el rate limiting por IP (60/min ajustable), los límites de respuesta, CORS abierto para lectura, la corrección de `get_post` con `post_password_required()`, la visibilidad de los listados y la regla de credencial para herramientas futuras con efectos quedan resueltas arriba.
