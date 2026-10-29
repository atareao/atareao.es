# PLAN — Change `mcp-interop-fixes`: detección de era MCP y cabecera `Allow` del preflight

> **Para quien ejecute:** tareas autocontenidas y verificables una a una. Implementar en orden: RED → GREEN → REFACTOR → verificación final.

**Objetivo:** corregir los dos defectos de interoperabilidad del transporte MCP que un **cliente real** (OpenCode 2.1.4) sufre contra `https://atareao.es/wp-json/atareao/v1/mcp`: (A) el discriminador de era rechaza con `HTTP 400 -32602` todos los `tools/call` de un cliente legacy, y (B) la cabecera `Allow` del preflight `OPTIONS` es incoherente con el CORS y con el `405` del propio endpoint.

**Restricción transversal:** ningún ciclo de este plan edita ficheros directamente desde el orquestador. Toda escritura se delega a un subagente; toda verificación se hace por CLI y por lectura del fichero real.

---

## 1. Los dos defectos (origen: E2E con el cliente real en producción)

### Defecto A — detección de era rota (impacto alto)

Cuerpo literal capturado del cliente real:

```json
{"method":"tools/call","params":{"name":"get_latest_posts","arguments":{"limit":1},"_meta":{"ai.opencode/sessionID":"ses_…","progressToken":2}},"jsonrpc":"2.0","id":2}
```

`isModernRequest()` decidía la era moderna con `isset($body['params']['_meta'])`. Pero `_meta` es un campo **general** del protocolo (`progressToken` existe en todas las eras), así que la petición legacy entraba en el handler moderno, no encontraba `io.modelcontextprotocol/protocolVersion` ni `clientCapabilities` y se rechazaba con `-32602` y `HTTP 400`. El handshake funcionaba porque `initialize` y `tools/list` no llevan `_meta`: el cliente listaba las tres herramientas y **no podía invocar ninguna**.

**Corrección:** era moderna **si y solo si** `params._meta` contiene `io.modelcontextprotocol/protocolVersion` **o** la petición presenta la cabecera `Mcp-Method`; `initialize` sigue seleccionando legacy.

### Defecto B — cabecera `Allow` del preflight (impacto bajo)

`OPTIONS /mcp` y `OPTIONS /wp-json/atareao/v1/mcp` respondían `200` con `Access-Control-Allow-Methods: POST, OPTIONS` pero con `Allow: POST`. El `Allow` lo construye WordPress core (`rest_send_allow_header`, hookeado en `rest_post_dispatch` con prioridad 10) a partir de los métodos **declarados en la ruta**, que solo registra `CREATABLE` (POST); el preflight ni siquiera llega al callback del plugin porque `rest_handle_options_request` lo cortocircuita en `rest_pre_dispatch`. El `405` de `GET`/`DELETE` del propio endpoint sí anunciaba `Allow: POST, OPTIONS`.

**Corrección:** filtro propio en `rest_post_dispatch` con prioridad 20 que fija `Allow: POST, OPTIONS` en la respuesta del preflight de la ruta pública MCP.

---

## 2. Contratos (spec)

`openspec/changes/mcp-interop-fixes/specs/mcp-server/spec.md` — dos requisitos **MODIFIED**, con headers copiados **literalmente** de `openspec/specs/mcp-server/spec.md`:

- **`CORS para la consulta anónima`**: la respuesta de preflight `OPTIONS` SHALL incluir `Allow` con los métodos soportados (`POST, OPTIONS`), coherente con `Access-Control-Allow-Methods` y con el `405` de `GET`/`DELETE`. Escenario nuevo: «El preflight declara los métodos permitidos».
- **`Conformidad dual-era de Streamable HTTP`**: la era moderna se selecciona si y solo si `params._meta` contiene `io.modelcontextprotocol/protocolVersion` o la petición presenta la cabecera `Mcp-Method`; la mera presencia de `params._meta` SHALL NOT seleccionar la era moderna. Escenarios nuevos: «Cliente legacy con `params._meta` genérico» y «La cabecera `Mcp-Method` selecciona la era moderna».

Sin cambios en rutas, herramientas, CORS, tarjeta de servidor ni catálogo AI. El rate limiting, la visibilidad del contenido y la validación de argumentos se mantienen.

---

## 3. Tareas y estado

### Fase 1-2 — RED → GREEN (hecho)

| Tarea | Estado | Evidencia |
|---|---|---|
| 1.1 RED escenario legacy `_meta` genérico | ✅ | `just test` → `723 passed / 6 failed`, los 6 solo en los escenarios nuevos |
| 1.2 RED cabecera `Mcp-Method` selecciona moderna | ✅ | ídem |
| 1.3 GREEN discriminador en `isModernRequest()` | ✅ | `class-mcp.php:496` |
| 1.4 HARNESS stubs de consulta de entradas + fixture | ✅ | `tests/wp-stubs.php`: `WP_Post`, `WP_Query`, `get_post_types`, `get_the_title`, `get_the_excerpt`, `get_post_meta`, `get_object_taxonomies`, `get_permalink` tolerante a objetos |
| 1.5 HARNESS aislamiento de la primera disyuntiva | ✅ | 4 aserciones nuevas en `tests/test-valet.php` |
| 2.1 RED escenario del preflight | ✅ | ídem 1.1 |
| 2.2 GREEN `sendAllowHeader()` + `add_filter(rest_post_dispatch, …, 20, 3)` | ✅ | `class-mcp.php:126`, `:192` |
| 2.3 HARNESS `\Atareao\MCP::init()` explícito en el escenario | ✅ | el arnés nunca dispara `init` |

### Fase 3 — REFACTOR y análisis estático (hecho)

| Tarea | Estado | Evidencia |
|---|---|---|
| 3.1 `php-lint` + `phpcs` sin violaciones nuevas | ✅ | `phpcs --standard=PSR12` sobre `class-mcp.php`: `0 errores / 5 warnings` **idénticos** a `HEAD` |
| 3.2 Sin código muerto ni rutas alternativas | ✅ | `isModernRequest` solo en `:496`/`:423`; `Allow` solo en `:209` (filtro) y `:290` (405) |
| 3.3 Docblock de `sendAllowHeader()` + doble `WP_REST_Response::header()` fiel a core | ✅ | core `wp-includes/class-wp-http-response.php`: sin `$replace` **concatena** `', '` (habría dado `Allow: POST, POST, OPTIONS`) |

### Fase 4 — Verificación final

| Tarea | Estado | Resultado |
|---|---|---|
| 4.1 Arnés completo en verde | ✅ | **`Total passed: 736, Total failed: 0`** |
| 4.2 `openspec validate mcp-interop-fixes --strict` | ✅ | valid |
| 4.3 No regresión de los escenarios dual-era | ✅ | `-32602`, `-32022`, `-32020`, `202`, `-32601`, `405` siguen alcanzables y en verde |
| 4.4 E2E en producción | ⏳ | requiere despliegue |

### Fase 5 — Entrega

| Tarea | Estado |
|---|---|
| 5.1 PR por gitflow a `development` | ⏳ pendiente de autorización |
| 5.2 Marcar tareas y archivar el change | ⏳ posterior a la E2E |

---

## 4. Grafo de cambios

```
tests/test-valet.php  (170 inserciones, 0 borrados)
  ├── escenario «Cliente legacy con params._meta generico»  (fixture $mock_posts + 4 aserciones)
  ├── escenario «La cabecera Mcp-Method selecciona la era moderna»
  ├── escenario «Los metadatos de protocolo en _meta seleccionan la era moderna»  (aisla la 1.ª disyuntiva)
  └── escenario «El preflight declara los metodos permitidos»  (\Atareao\MCP::init() explícito)

tests/wp-stubs.php  (323 inserciones, 4 borrados: solo docblocks)
  ├── WP_Post, WP_Query
  ├── get_post_types, get_the_title, get_the_excerpt, get_post_meta, get_object_taxonomies
  ├── get_permalink: rama para objeto (comportamiento con entero, intacto)
  └── WP_REST_Response::header(): alineado con core ($replace = true, concatenación ', ')

wp-content/plugins/atareao-functionality/includes/class-mcp.php  (64 inserciones, 5 borrados)
  ├── init(): add_filter('rest_post_dispatch', [__CLASS__, 'sendAllowHeader'], 20, 3)
  ├── sendAllowHeader($response, $server, $request)
  └── isModernRequest($method, $body, $request = null): discriminador nuevo
```

---

## 5. Auditoría (fase de auditoría del ciclo)

Auditoría de solo lectura sobre el diff completo. Resultado: **0 hallazgos altos, 0 medios, 1 bajo, 2 informativos.** Nada impide archivar.

- **Bajo — `isset` frente a «contiene la clave»:** `params._meta` con `protocolVersion` presente y valor `null` cae en legacy en vez de moderna. Aceptado a propósito (petición malformada, ningún cliente conocido la emite; la era legacy es la resiliente y `handleModernRequest()` interpreta igual el `null`). No explotable: el rate limiting, la validación de argumentos y la visibilidad del contenido son idénticos en ambas eras.
- **INFO — `has_filter`:** mide el cuerpo de `MCP::init()`, no el `add_action('init', 'atareao_functionality_init')` del bootstrap. Es sensible al `add_filter` de producción, que es lo que se quería fijar.
- **INFO — `sendAllowHeader()`:** el escenario llama al callback directamente; el paso real por core (prioridad 20 después de `rest_send_allow_header`) solo se comprueba en la E2E de producción.

Puntos ciegos de mutación detectados y **cerrados**: la primera disyuntiva del discriminador (`_meta.protocolVersion` sin `Mcp-Method`) no estaba aislada por ninguna aserción; ahora lo está (tarea 1.5).

---

## 6. Pendiente

1. **E2E en producción (tarea 4.4)** tras el despliegue: `POST /wp-json/atareao/v1/mcp` con el cuerpo legacy del cliente real (con `_meta.progressToken`) → `200`; `OPTIONS /mcp` → `Allow: POST, OPTIONS`; invocación de una herramienta desde el cliente MCP real. La verificación en vuelo de la era moderna contra producción queda pendiente de decisión del usuario (añadir `versionNegotiation: {"mode":"auto"}` a la configuración del cliente).
2. **Gitflow (tarea 5.1)**: rama `fix/mcp-interop-fixes`, commits convencionales con gitmoji, PR a `development`. Sin merge sin autorización explícita.
3. **Archivar (tarea 5.2)**: con los headers del delta coincidiendo literalmente con `openspec/specs/mcp-server/spec.md`.
4. **Flecos de la rebanada anterior**: `/tmp/nginx.conf.patched-final` sigue en el servidor de producción; el backup `nginx.conf.bak-20261029192207` no es un rollback válido por sí solo.

---

## 7. Estado del árbol de trabajo (sin commitear)

```
 M PLAN.md
 M openspec/changes/archive/2026-10-29-mcp-discovery/tasks.md
 M tests/test-valet.php
 M tests/wp-stubs.php
 M wp-content/plugins/atareao-functionality/includes/class-mcp.php
?? nginx/pro/
?? openspec/changes/mcp-interop-fixes/
```

Rama `development` == `origin/development` == `a17d528`. Sin commits nuevos. `feature/mcp-discovery` y `chore/archive-mcp-discovery` siguen existiendo y no se borran sin autorización.

---

## 8. Comandos de verificación

```bash
just test          # arnés procedural: 736 passed / 0 failed
just php-lint      # sintaxis de tema + plugin
just phpcs         # PSR-12 (violaciones preexistentes ajenas a este change)
just php '-l tests/test-valet.php'
just php '-l tests/wp-stubs.php'
openspec validate mcp-interop-fixes --strict
```
