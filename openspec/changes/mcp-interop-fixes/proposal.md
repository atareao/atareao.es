# Proposal: Interoperabilidad del transporte MCP — detección de era y cabecera `Allow` del preflight

## Why

El E2E en producción del change `mcp-discovery` (archivado) conectó un **cliente MCP real** (OpenCode 2.1.4) al servidor `atareao` y capturó su tráfico literal en el nginx de producción. El resultado revela dos defectos que el cliente real sí sufre y que ninguna prueba sintética había detectado:

1. **Detección de era rota para clientes legacy (impacto alto).** El cliente real negocia la **era legacy**: envía `MCP-Protocol-Version: 2024-11-05`, **no** envía `Mcp-Method` y su `params._meta` contiene únicamente claves generales del protocolo (`progressToken`, más una clave propia del cliente), no los metadatos de la era moderna. Cuerpo literal capturado:

   ```json
   {"method":"tools/call","params":{"name":"get_latest_posts","arguments":{"limit":1},"_meta":{"ai.opencode/sessionID":"ses_…","progressToken":2}},"jsonrpc":"2.0","id":2}
   ```

   La implementación decide la era moderna con `isset($body['params']['_meta'])` (`class-mcp.php`, `isModernRequest()`). Como `_meta` es un campo **general** del protocolo —`_meta.progressToken` existe en todas las eras—, la petición legacy entra en el handler moderno, no encuentra `io.modelcontextprotocol/protocolVersion` ni `io.modelcontextprotocol/clientCapabilities` y se rechaza con `-32602` (Invalid params) y **HTTP 400**. Reproducido de forma determinista:

   ```
   HTTP 400
   {"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params"},"id":2}
   ```

   Consecuencia: el servidor MCP público anuncia tres herramientas, el cliente las lista (`tools/list` → 200) y **ninguna se puede invocar** (`tools/call` → 400 en todas). El handshake se atendía bien porque `initialize` y `tools/list` no llevan `_meta`.

2. **Cabecera `Allow` incoherente en el preflight (impacto bajo).** `OPTIONS /mcp` y `OPTIONS /wp-json/atareao/v1/mcp` responden `200` con `Access-Control-Allow-Methods: POST, OPTIONS` pero con `Allow: POST`. El `Allow` lo construye WordPress core (`rest_send_allow_header`, `wp-includes/rest-api.php`) concatenando los métodos **declarados en la ruta**, y la ruta solo registra `WP_REST_Server::CREATABLE` (POST); el preflight ni siquiera llega a nuestro callback porque `rest_handle_options_request` lo cortocircuita en `rest_pre_dispatch`. Nuestro propio `405` de `GET /mcp` sí emite `Allow: POST, OPTIONS` (`class-mcp.php`), así que el mismo endpoint declara cosas distintas según el método.

## What Changes

- **Precisar el discriminador de era** en el requisito de conformidad dual-era y en `isModernRequest()`: una petición es de la **era moderna** si y solo si declara metadatos de protocolo por petición —`params._meta` contiene `io.modelcontextprotocol/protocolVersion`, **o** la petición presenta la cabecera `Mcp-Method`—; en cualquier otro caso es **legacy**. La mera presencia de `params._meta` SHALL NOT seleccionar la era moderna.
- **Completar la cabecera `Allow`** de la respuesta de preflight `OPTIONS`: SHALL incluir `POST, OPTIONS`, coherente con `Access-Control-Allow-Methods` y con el `405` de `GET`/`DELETE`.
- Los mensajes CORS, los métodos con efectos, el cuerpo del preflight, el rate limiting, la visibilidad del contenido y la validación de argumentos **no cambian**.

## Capabilities

### Modified Capabilities

- `mcp-server`: se modifica el requisito **Conformidad dual-era de Streamable HTTP** (discriminador de era explícito) y el requisito **CORS para la consulta anónima** (cabecera `Allow` del preflight).

## Impact

- **Archivos a modificar (solo en la fase de implementación, tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-mcp.php` — `isModernRequest()` y un filtro `rest_post_dispatch` (prioridad 20, posterior a `rest_send_allow_header` que va en 10) para la cabecera `Allow`.
  - `tests/test-valet.php` — aserciones de los escenarios nuevos (arnés procedural con los stubs de `tests/wp-stubs.php`).
  - `tests/wp-stubs.php` — stubs mínimos de consulta de entradas (`WP_Post`, `WP_Query`, `get_post_types`, `get_the_title`, `get_the_excerpt`, `get_post_meta`, `get_object_taxonomies`) y `get_permalink` tolerante a objetos, para que la ruta `get_latest_posts` se ejecute de verdad en el arnés.
- **Sin cambios** en las rutas, en las herramientas, en el CORS, en la tarjeta de servidor ni en el catálogo AI.
