# Tasks: Servicio público de consulta MCP

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación del change combina análisis estático (`just php-lint`, `just phpcs`), un **arnés de stubs externo** que vive solo en `/tmp/opencode/mcp-harness/` (fuera del repo y no versionado) y E2E manual en producción. El arnés no forma parte del commit ni del árbol. La implementación arranca **solo tras la aprobación del usuario**. Ninguna tarea renombra ni borra opciones, rutas REST ni hooks. Objetivo rector: **que cualquiera pueda consultar el blog vía MCP**.

## 1. Phase 0 — Línea base y caracterización

- [ ] 1.1 Documentar la caracterización del endpoint actual en `design.md` §Context (ruta, herramientas, transporte, `permission_callback` público, `get_post` sin `post_password_required()`, `apply_filters('the_content', …)` sobre el crudo y `posts_per_page` fijo) con evidencia fichero:línea de `class-mcp.php`. **Verificación:** cada afirmación cita una línea existente; `openspec validate mcp-endpoint --strict` válido.
- [ ] 1.2 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el baseline y se anota el par errores/warnings. **Evidencia esperada:** baseline (2026-10-03) theme+plugin: 752 errores / 426 warnings; `just php-lint` → 0 errores.
- [ ] 1.3 Registrar en el arnés externo los stubs mínimos de WordPress (`register_rest_route`, `get_post`, `post_password_required`, `get_post_field`, `get_the_content`, `get_post_types`, `WP_Query`, `get_transient`/`set_transient`, `wp_json_encode`, `apply_filters`, `rest_send_cors_headers`) con contadores de llamadas. **Verificación:** el arnés ejecuta una petición trivial contra el stub y devuelve `FAIL=0` con los contadores a cero. **Evidencia esperada:** `TOTAL=N PASS=N FAIL=0`; no se versiona.

## 2. Servicio público de solo lectura (sin autenticación)

- [ ] 2.1 Confirmar que el `permission_callback` sigue siendo público (`__return_true`) de forma deliberada, sin añadir autenticación a las herramientas de consulta. **Verificación:** `rg -n "permission_callback" class-mcp.php` → sigue `__return_true`; `just php-lint` sin errores. **Evidencia esperada:** arnés A1 (invocación anónima atendida).
- [ ] 2.2 Verificar que las tres herramientas (`get_latest_posts`, `get_post`, `search_posts`) son de solo consulta y no producen efectos. **Verificación:** arnés — una invocación anónima de cada herramienta no crea/modifica/borra recursos. **Evidencia esperada:** arnés A2.
- [ ] 2.3 Dejar escrito y comprobado el requirement de futuro: cualquier herramienta con efectos exigirá credencial (Application Password/`Bearer`) y capacidad, y rechazará la petición anónima. **Verificación:** arnés — una herramienta simulada con efectos rechaza la invocación anónima con 401/403. **Evidencia esperada:** arnés A3.
- [ ] 2.4 Confirmar que la ruta `atareao/v1/mcp`, el método `CREATABLE`, los nombres de las herramientas y el meta `rel="mcp-server"` no cambian. **Verificación:** `rg -n "atareao/v1|/mcp|get_latest_posts|get_post|search_posts|mcp-server" class-mcp.php` conserva las cadenas. **Evidencia esperada:** arnés A4.
- [ ] 2.5 Confirmar que ninguna respuesta expone datos internos: ni borradores, ni privados, ni metadatos internos, ni rutas del servidor, ni datos de usuarios. **Verificación:** arnés — inspección del cuerpo de las respuestas. **Evidencia esperada:** arnés A5.

## 3. Descubrimiento e interoperabilidad MCP

- [ ] 3.1 Implementar `initialize` con `protocolVersion`, `serverInfo` y `capabilities` que declaren el soporte de herramientas. **Verificación:** arnés — un cliente genérico recibe esos campos. **Evidencia esperada:** arnés D1.
- [ ] 3.2 Enriquecer `tools/list`: cada herramienta con `name`, `description`, `inputSchema` válido y su carácter de solo lectura, indicando que devuelve contenido público. **Verificación:** arnés — el `inputSchema` es un objeto JSON válido y las descripciones indican solo lectura. **Evidencia esperada:** arnés D2 y D3.
- [ ] 3.3 Confirmar que un cliente MCP genérico puede integrarse sin configuración especial. **Verificación:** arnés/E2E — flujo `initialize` → `tools/list` → `tools/call` completo. **Evidencia esperada:** arnés D4.

## 4. Rate limiting por IP

- [ ] 4.1 Implementar un contador de **ventana fija de 60 segundos** por IP hasheada con transients (`get_transient`/`set_transient`), TTL igual a la ventana y **tope por defecto de 60 peticiones/minuto** ajustable por filtro/constante. **Verificación:** arnés — por debajo del tope atiende; al superarlo devuelve 429 con `Retry-After`; el contador de una IP no afecta a otra. **Evidencia esperada:** arnés R1, R2 y R3.
- [ ] 4.2 Comprobar que el tope no estorba el uso legítimo: un cliente MCP por debajo de 60/min no se bloquea, y el valor es ajustable sin editar el cuerpo del código. **Verificación:** arnés — cadencia legítima pasa; el filtro/constante cambia el tope. **Evidencia esperada:** arnés R4 y R5.
- [ ] 4.3 No confiar en cabeceras de proxy (`X-Forwarded-For` u otras) para determinar la IP salvo configuración explícita, y no almacenar la IP en claro. **Verificación:** arnés — manipular `X-Forwarded-For` no cambia la clave del contador; la clave es un hash. **Evidencia esperada:** arnés R6.
- [ ] 4.4 No ejecutar la herramienta cuando se responde por límite, y no revelar información interna en el error de límite. **Verificación:** arnés — el contador de consultas no aumenta tras un 429; el mensaje no contiene detalles internos. **Evidencia esperada:** arnés R2 y R7.

## 5. Documentación pública del endpoint

- [ ] 5.1 Documentar en el `README.md` del plugin la **URL** (`/wp-json/atareao/v1/mcp`), el **protocolo** (JSON-RPC 2.0) y las **tres herramientas** de consulta con sus argumentos. **Verificación:** `rg -n "/wp-json/atareao/v1/mcp|JSON-RPC|get_latest_posts|get_post|search_posts" README.md` muestra la sección. **Evidencia esperada:** sección con URL, protocolo y herramientas.
- [ ] 5.2 Añadir un **ejemplo de llamada** (`curl` con `tools/list` o `search_posts`) copiable y funcional sin credenciales. **Verificación:** el ejemplo se ejecuta tal cual contra el endpoint y devuelve una respuesta correcta. **Evidencia esperada:** ejemplo documentado y probado en E2E.
- [ ] 5.3 Documentar el carácter de solo lectura, el rate limiting (60/min) y los límites de paginación/tamaño. **Verificación:** revisión del `README.md`. **Evidencia esperada:** sección con carácter público y límites.

## 6. Corrección de `get_post` y visibilidad

- [ ] 6.1 En `getPost()`, exigir `post_status === 'publish'`, `post_password === ''` y `post_password_required($post) === false`; si falla cualquiera, devolver el error genérico «Post not found». **Verificación:** arnés — un post protegido no devuelve contenido y su respuesta es idéntica a la de un `id` inexistente. **Evidencia esperada:** arnés P1, P2 y P3.
- [ ] 6.2 Obtener el contenido con `get_post_field('post_content', $post)` o `get_the_content()` y aplicar los filtros de presentación solo tras confirmar que no requiere contraseña; eliminar `apply_filters('the_content', $post->post_content)` sobre el crudo. **Verificación:** arnés — no se invoca `apply_filters('the_content', …)` sobre un post protegido; un post público devuelve contenido filtrado. **Evidencia esperada:** arnés P4 y P5.
- [ ] 6.3 Añadir `post_password => ''` y restringir a tipos públicos (`get_post_types(['public' => true])`) en `get_latest_posts` y `search_posts`. **Verificación:** arnés — los listados no incluyen posts protegidos ni tipos no públicos y sí los públicos. **Evidencia esperada:** arnés P6 y P7.

## 7. Validación de argumentos y límites de respuesta

- [ ] 7.1 Declarar `args` en `register_rest_route` con `validate_callback`/`sanitize_callback` para `jsonrpc`, `method` y `params`. **Verificación:** arnés — JSON inválido o `jsonrpc` distinto de `2.0` → `-32700`; la ruta registra los `args`. **Evidencia esperada:** arnés V1 y V2.
- [ ] 7.2 Validar los argumentos de cada herramienta antes de consultar: `id` entero positivo; `query` no vacía y con longitud máxima; `page`/`per_page`/`limit` enteros dentro de rango; argumento inválido → `-32602` sin consulta. **Verificación:** arnés — `id` no entero/negativo, `query` vacía o excesiva → `-32602` y cero consultas. **Evidencia esperada:** arnés V3, V4 y V5.
- [ ] 7.3 Añadir paginación acotada a `search_posts` (`page`/`per_page` con tope de página y de tamaño) y limitar `get_latest_posts` (`limit` acotado); nunca devolver más resultados que el tope. **Verificación:** arnés — un `per_page` desmesurado se acota o rechaza. **Evidencia esperada:** arnés V6 y V7.
- [ ] 7.4 Imponer un **tamaño máximo de respuesta**: si el cuerpo excediera el tope, recortarlo (o responder un error accionable); nunca volcar el blog entero. **Verificación:** arnés — una respuesta sobredimensionada se recorta al tope. **Evidencia esperada:** arnés V8.

## 8. CORS para la consulta anónima

- [ ] 8.1 Emitir cabeceras CORS que permitan la consulta anónima desde cualquier origen para `POST`, con `Access-Control-Allow-Origin` abierto y **sin** `Access-Control-Allow-Credentials`. **Verificación:** arnés/E2E — la respuesta admite el origen y no habilita credenciales. **Evidencia esperada:** arnés C1.
- [ ] 8.2 Resolver el preflight `OPTIONS` con los métodos `POST`/`OPTIONS` y las cabeceras mínimas, sin permitir métodos con efectos. **Verificación:** arnés/E2E — el preflight responde con esos métodos. **Evidencia esperada:** arnés C2.

## 9. Errores sin información interna

- [ ] 9.1 Unificar errores con códigos JSON-RPC estándar y mensajes genéricos, con el código HTTP correcto: `429` para el rate limit y `401`/`403` para una futura herramienta con efectos que rechace la petición anónima o sin capacidad. **Verificación:** arnés — límite → 429; herramienta simulada con efectos y sin credencial → 401; no hay un `200` uniforme para errores. **Evidencia esperada:** arnés E1 y E2.
- [ ] 9.2 Eliminar de los mensajes rutas de fichero, consultas SQL, trazas, IDs internos y la existencia concreta de un recurso; registrar el detalle solo en servidor, sin credenciales. **Verificación:** arnés — ningún cuerpo de error expone detalles internos; el log no contiene credenciales. **Evidencia esperada:** arnés E3 y E4.

## 10. Verificación

- [ ] 10.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.2. **Evidencia esperada:** `just php-lint` → 0 errores; phpcs sin empeorar el baseline.
- [ ] 10.2 Arnés externo completo. **Verificación:** `/tmp/opencode/mcp-harness/` → `TOTAL=N PASS=N FAIL=0`, `exit=0`; cubre lectura pública anónima de las tres herramientas (solo contenido público, sin efectos, sin datos internos), descubrimiento (`initialize`/`tools/list`), rate limit (429 + `Retry-After`, aislamiento por IP, tope ajustable), CORS, contraseña (`post_password_required`, error indistinguible con y sin credencial, contenido filtrado), visibilidad de listados, validación y límites (paginación + tamaño máximo) y errores genéricos. **Evidencia esperada:** salida `TOTAL=N PASS=N FAIL=0`, exit 0.
- [ ] 10.3 Auditoría de no-regresión de contratos. **Verificación:** `rg` confirma que la ruta, los nombres de las herramientas, el meta de descubrimiento y el transporte JSON-RPC se conservan; ninguna opción, ruta REST ni hook se ha renombrado o borrado. **Evidencia esperada:** `rg` con las cadenas intactas.
- [ ] 10.4 Spec. **Verificación:** `openspec validate mcp-endpoint --strict` sin hallazgos. **Evidencia esperada:** «Change 'mcp-endpoint' is valid».

## 11. E2E en producción

- [ ] 11.1 Descubrimiento real: un cliente MCP genérico ejecuta `initialize` → `tools/list` → `tools/call` contra producción sin configuración especial. **Verificación:** llamadas manuales. **Evidencia esperada:** herramientas descubiertas y ejecutadas; pendiente (requiere producción).
- [ ] 11.2 Lectura pública real: POST anónimo a `atareao/v1/mcp` invocando `get_latest_posts`, `get_post` y `search_posts` → HTTP 200 con solo contenido público, sin credenciales, y el ejemplo del `README.md` se ejecuta tal cual. **Verificación:** peticiones manuales contra el sitio en producción. **Evidencia esperada:** 200 y resultados correctos; pendiente (requiere producción).
- [ ] 11.3 Contraseña en producción: `get_post` de una entrada con contraseña devuelve el error genérico y no su contenido, **con y sin credencial**; comparar con un `id` inexistente y comprobar que son indistinguibles. **Verificación:** petición manual con un `id` protegido y uno inexistente. **Evidencia esperada:** respuestas idénticas, sin contenido; pendiente (requiere producción).
- [ ] 11.4 Rate limiting en producción: superar el tope desde una IP → HTTP 429 con `Retry-After`; comprobar que otra IP no se ve afectada. **Verificación:** ráfaga manual de peticiones. **Evidencia esperada:** 429 + `Retry-After`; pendiente (requiere producción).
- [ ] 11.5 CORS en producción: consulta anónima desde un origen distinto con cabeceras CORS correctas. **Verificación:** petición manual con `Origin`. **Evidencia esperada:** `Access-Control-Allow-Origin` correcto; pendiente (requiere producción).
- [ ] 11.6 No-regresión del sitio público: HTML, microsite `/tools/`, analítica, login/logout y notificaciones Matrix sin cambios; el meta `rel="mcp-server"` sigue presente. **Verificación:** navegación manual y E2E. **Evidencia esperada:** sin cambios observables; pendiente (requiere producción).

## 12. Entrega

- [ ] 12.1 Comprobar que la documentación del `README.md` coincide con el comportamiento real (URL, protocolo, herramientas, ejemplo, límites). **Verificación:** revisión final frente al endpoint desplegado. **Evidencia esperada:** documentación sincronizada; pendiente.
- [ ] 12.2 PR por gitflow de `feature/mcp-endpoint` a `development` con commits convencionales (gitmoji). **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia esperada:** pendiente.
- [ ] 12.3 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive mcp-endpoint` crea `openspec/specs/mcp-server/spec.md`; `openspec list` ya no muestra el change activo. **Evidencia esperada:** pendiente.
