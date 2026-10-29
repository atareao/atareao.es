# Tasks — Interoperabilidad del transporte MCP

## 1. Detección de era (RED → GREEN)

- [x] 1.1 RED — Añadir a `tests/test-valet.php` la aserción del escenario «Cliente legacy con `params._meta` genérico»: petición `tools/call` con `params._meta` = `{"progressToken":2}` (sin `Mcp-Method` y sin metadatos de protocolo) → exige `200` con resultado y **no** `-32602`. **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 1.2 RED — Añadir la aserción del escenario «La cabecera `Mcp-Method` selecciona la era moderna»: petición con cabecera `Mcp-Method` y sin `params._meta` de protocolo → exige `-32602` con HTTP `400`. **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 1.3 GREEN — Cambiar `isModernRequest()` en `wp-content/plugins/atareao-functionality/includes/class-mcp.php`: era moderna si y solo si `params._meta` contiene `io.modelcontextprotocol/protocolVersion` o la petición presenta la cabecera `Mcp-Method`; `initialize` sigue seleccionando legacy. **Verificación:** `just test` 100% verde.
- [x] 1.4 HARNESS — stubs mínimos de consulta de entradas en `tests/wp-stubs.php` (`WP_Post`, `WP_Query`, `get_post_types`, `get_the_title`, `get_the_excerpt`, `get_post_meta`, `get_object_taxonomies`, `get_permalink` tolerante a objetos) y fixture de entradas en el escenario legacy; **Verificación:** `just test` con la herramienta devolviendo la entrada de la fixture.
- [x] 1.5 HARNESS — aislar la primera disyuntiva del discriminador (`_meta.protocolVersion` sin cabecera `Mcp-Method`) y su complemento (`_meta` sin la clave de protocolo ⇒ legacy) para cerrar el punto ciego de mutación; **Verificación:** `just test` con las 4 aserciones nuevas en PASS.

## 2. Cabecera `Allow` del preflight (RED → GREEN)

- [x] 2.1 RED — Añadir la aserción del escenario «El preflight declara los métodos permitidos»: el preflight `OPTIONS` responde con `Allow` que incluye `POST` y `OPTIONS`. **Verificación:** `just test` falla solo en la aserción nueva.
- [x] 2.2 GREEN — Implementar el filtro `rest_post_dispatch` (prioridad 20) que fija `Allow: POST, OPTIONS` en la respuesta del preflight de la ruta pública MCP, sin alterar el cuerpo, el CORS ni las rutas ajenas. **Verificación:** `just test` 100% verde.
- [x] 2.3 HARNESS — inicialización explícita de `\Atareao\MCP::init()` en el escenario del preflight porque el arnés nunca dispara `init`; **Verificación:** la aserción `has_filter('rest_post_dispatch', …)` en verde y el resto del fichero sin cambios.

## 3. Refactor y análisis estático

- [x] 3.1 `cargo`-equivalente del proyecto: `just php-lint` sin errores y `just phpcs` sin violaciones nuevas. **Verificación:** salida literal de ambos.
- [x] 3.2 Comprobar que no hay código muerto ni rutas alternativas: ninguna otra parte del plugin decide la era ni emite `Allow`. **Verificación:** `rg -n "isModernRequest|'Allow'" wp-content/plugins/atareao-functionality/`.
- [x] 3.3 Corregir el docblock de `sendAllowHeader()` (semántica real de `WP_REST_Response::header()`: concatenación con `', '` cuando no se pide reemplazo) y alinear el doble `WP_REST_Response::header()` de `tests/wp-stubs.php` con core; **Verificación:** `just test` sin regresiones y docblock contrastado con `wp-includes/class-wp-http-response.php`.

## 4. Verificación final

- [x] 4.1 Arnés procedural completo en verde. **Verificación:** `just test` con el recuento de aserciones a favor y 0 fallos.
- [x] 4.2 `openspec validate mcp-interop-fixes --strict` sin hallazgos.
- [x] 4.3 No regresión de los escenarios existentes del requisito dual-era: petición moderna servida, metadatos ausentes (`-32602`), versión no soportada (`-32022`), cabecera ausente (`-32020`), notificación `202`, método no implementado `-32601`, `GET`/`DELETE` `405`. **Verificación:** aserciones del arnés.
- [ ] 4.4 E2E en producción tras el despliegue: `POST /wp-json/atareao/v1/mcp` con el cuerpo legacy del cliente real (con `_meta.progressToken`) devuelve `200`; `OPTIONS /mcp` devuelve `Allow: POST, OPTIONS`; y el cliente MCP real invoca una herramienta con éxito. **Verificación:** salida literal de `curl` y log del cliente.

## 5. Entrega

- [ ] 5.1 PR por gitflow con commits convencionales (gitmoji) a `development`.
- [ ] 5.2 Marcar las tareas completadas y archivar el change, verificando que los headers del delta coinciden literalmente con los de `openspec/specs/mcp-server/spec.md`.

## 6. Decisiones y limitaciones conocidas

- **`isset` frente a «contiene la clave» (LOW, aceptado):** el requisito se lee literalmente como «`params._meta` contiene la clave `io.modelcontextprotocol/protocolVersion`». La implementación usa `isset()`, de modo que una clave presente con valor `null` se trata como ausente y la petición cae en la era **legacy** (que funciona). Se acepta así a propósito: es una petición malformada que ningún cliente conocido emite, la era legacy es la resiliente, y el propio `handleModernRequest()` interpreta igual el valor `null` (lo considera metadato ausente). Cambiarlo por `array_key_exists()` haría que una petición malformada recibiera `-32602` en vez de funcionar.
- **`has_filter('rest_post_dispatch', …)` (INFO):** el escenario inicializa explícitamente `\Atareao\MCP::init()` porque el arnés nunca dispara la acción `init`. La aserción es sensible (eliminar el `add_filter` de `MCP::init()` la pone en rojo) pero mide el cuerpo de `init()`, no el cableado `add_action('init', 'atareao_functionality_init')` del bootstrap.
- **`sendAllowHeader()` (INFO):** el escenario llama al callback directamente. Que core lo invoque en el `OPTIONS` real, con prioridad posterior a `rest_send_allow_header`, solo se comprueba en la E2E de producción (tarea 4.4).
