# PLAN — Change `mcp-interop-fixes`: detección de era MCP y cabecera `Allow` del preflight

> **Estado: CERRADO.** El change está implementado, auditado, entregado (release **v1.18.0** desplegado en producción) y **archivado** en las specs canónicas. Todos los objetivos del plan están tildados.

**Objetivo:** corregir los dos defectos de interoperabilidad del transporte MCP que un **cliente real** (OpenCode 2.1.4) sufría contra `https://atareao.es/wp-json/atareao/v1/mcp`: (A) el discriminador de era rechazaba con `HTTP 400 -32602` todos los `tools/call` de un cliente legacy, y (B) la cabecera `Allow` del preflight `OPTIONS` era incoherente con el CORS y con el `405` del propio endpoint.

**Restricción transversal:** ningún ciclo de este plan edita ficheros directamente desde el orquestador. Toda escritura se delega a un subagente; toda verificación se hace por CLI y por lectura del fichero real.

---

## 1. Los dos defectos (origen: E2E con el cliente real en producción)

### Defecto A — detección de era rota (impacto alto)

Cuerpo literal capturado del cliente real:

```json
{"method":"tools/call","params":{"name":"get_latest_posts","arguments":{"limit":1},"_meta":{"ai.opencode/sessionID":"ses_…","progressToken":2}},"jsonrpc":"2.0","id":2}
```

`isModernRequest()` decidía la era moderna con `isset($body['params']['_meta'])`. Pero `_meta` es un campo **general** del protocolo (`progressToken` existe en todas las eras), así que la petición legacy entraba en el handler moderno, no encontraba `io.modelcontextprotocol/protocolVersion` ni `clientCapabilities` y se rechazaba con `-32602` y `HTTP 400`. El handshake funcionaba porque `initialize` y `tools/list` no llevan `_meta`: el cliente listaba las tres herramientas y **no podía invocar ninguna**.

**Corrección aplicada:** era moderna **si y solo si** `params._meta` contiene `io.modelcontextprotocol/protocolVersion` **o** la petición presenta la cabecera `Mcp-Method`; `initialize` sigue seleccionando legacy.

### Defecto B — cabecera `Allow` del preflight (impacto bajo)

`OPTIONS /mcp` y `OPTIONS /wp-json/atareao/v1/mcp` respondían `200` con `Access-Control-Allow-Methods: POST, OPTIONS` pero con `Allow: POST`. El `Allow` lo construye WordPress core (`rest_send_allow_header`, hookeado en `rest_post_dispatch` con prioridad 10) a partir de los métodos **declarados en la ruta**, que solo registra `CREATABLE` (POST); el preflight ni siquiera llega al callback del plugin porque `rest_handle_options_request` lo cortocircuita en `rest_pre_dispatch`. El `405` de `GET`/`DELETE` del propio endpoint sí anunciaba `Allow: POST, OPTIONS`.

**Corrección aplicada:** filtro propio en `rest_post_dispatch` con prioridad 20 que fija `Allow: POST, OPTIONS` en la respuesta del preflight de la ruta pública MCP.

---

## 2. Contratos (spec)

Delta archivado en `openspec/changes/archive/2026-10-29-mcp-interop-fixes/specs/mcp-server/spec.md` — dos requisitos **MODIFIED**, con headers copiados **literalmente** de `openspec/specs/mcp-server/spec.md`:

- **`CORS para la consulta anónima`**: la respuesta de preflight `OPTIONS` SHALL incluir `Allow` con los métodos soportados (`POST, OPTIONS`), coherente con `Access-Control-Allow-Methods` y con el `405` de `GET`/`DELETE`. Escenario nuevo: «El preflight declara los métodos permitidos».
- **`Conformidad dual-era de Streamable HTTP`**: la era moderna se selecciona si y solo si `params._meta` contiene `io.modelcontextprotocol/protocolVersion` o la petición presenta la cabecera `Mcp-Method`; la mera presencia de `params._meta` SHALL NOT seleccionar la era moderna. Escenarios nuevos: «Cliente legacy con `params._meta` genérico» y «La cabecera `Mcp-Method` selecciona la era moderna».

Sin cambios en rutas, herramientas, CORS, tarjeta de servidor ni catálogo AI. El rate limiting, la visibilidad del contenido y la validación de argumentos se mantienen.

---

## 3. Tareas y estado — todas ✅

### Fase 1-2 — RED → GREEN

| Tarea | Estado | Evidencia |
|---|---|---|
| 1.1 RED escenario legacy `_meta` genérico | ✅ | `just test` → `723 passed / 6 failed`, los 6 solo en los escenarios nuevos |
| 1.2 RED cabecera `Mcp-Method` selecciona moderna | ✅ | ídem |
| 1.3 GREEN discriminador en `isModernRequest()` | ✅ | `class-mcp.php:498` |
| 1.4 HARNESS stubs de consulta de entradas + fixture | ✅ | `tests/wp-stubs.php`: `WP_Post`, `WP_Query`, `get_post_types`, `get_the_title`, `get_the_excerpt`, `get_post_meta`, `get_object_taxonomies`, `get_permalink` tolerante a objetos |
| 1.5 HARNESS aislamiento de la primera disyuntiva | ✅ | 4 aserciones nuevas en `tests/test-valet.php` |
| 2.1 RED escenario del preflight | ✅ | ídem 1.1 |
| 2.2 GREEN `sendAllowHeader()` + `add_filter(rest_post_dispatch, …, 20, 3)` | ✅ | `class-mcp.php:126`, `:194` |
| 2.3 HARNESS `\Atareao\MCP::init()` explícito en el escenario | ✅ | el arnés nunca dispara `init` |

### Fase 3 — REFACTOR y análisis estático

| Tarea | Estado | Evidencia |
|---|---|---|
| 3.1 `php-lint` + `phpcs` sin violaciones nuevas | ✅ | `phpcs --standard=PSR12` sobre `class-mcp.php`: `0 errores / 5 warnings` **idénticos** a `HEAD` |
| 3.2 Sin código muerto ni rutas alternativas | ✅ | `isModernRequest` solo en su definición y un call-site; `Allow` solo en el filtro y en el `405` |
| 3.3 Docblock de `sendAllowHeader()` + doble `WP_REST_Response::header()` fiel a core | ✅ | core `wp-includes/class-wp-http-response.php`: sin `$replace` **concatena** `', '` (habría dado `Allow: POST, POST, OPTIONS`) |

### Fase 4 — Verificación final

| Tarea | Estado | Resultado |
|---|---|---|
| 4.1 Arnés completo en verde | ✅ | **`Total passed: 736, Total failed: 0`** |
| 4.2 `openspec validate mcp-interop-fixes --strict` | ✅ | valid |
| 4.3 No regresión de los escenarios dual-era | ✅ | `-32602`, `-32022`, `-32020`, `202`, `-32601`, `405` siguen alcanzables y en verde |
| 4.4 E2E en producción | ✅ | ver §6 |

### Fase 5 — Entrega

| Tarea | Estado | Resultado |
|---|---|---|
| 5.1 PR por gitflow a `development` | ✅ | PR #117 → #118 (`development → main`) → v1.18.0 → #119 → #120 |
| 5.2 Marcar tareas y archivar el change | ✅ | `openspec archive mcp-interop-fixes` → `2026-10-29-mcp-interop-fixes`; PR #121 a `development` |

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

## 5. Auditoría

Auditoría de solo lectura sobre el diff completo. Resultado: **0 hallazgos altos, 0 medios, 1 bajo, 2 informativos.** Nada impide archivar.

- **Bajo — `isset` frente a «contiene la clave»:** `params._meta` con `protocolVersion` presente y valor `null` cae en legacy en vez de moderna. Aceptado a propósito (petición malformada, ningún cliente conocido la emite; la era legacy es la resiliente y `handleModernRequest()` interpreta igual el `null`). No explotable: el rate limiting, la validación de argumentos y la visibilidad del contenido son idénticos en ambas eras.
- **INFO — `has_filter`:** mide el cuerpo de `MCP::init()`, no el `add_action('init', 'atareao_functionality_init')` del bootstrap. Es sensible al `add_filter` de producción, que es lo que se quería fijar.
- **INFO — `sendAllowHeader()`:** el escenario llama al callback directamente; el paso real por core (prioridad 20 después de `rest_send_allow_header`) se cerró en la E2E de producción.

Puntos ciegos de mutación detectados y **cerrados**: la primera disyuntiva del discriminador (`_meta.protocolVersion` sin `Mcp-Method`) no estaba aislada por ninguna aserción; ahora lo está (tarea 1.5).

---

## 6. E2E en producción (tarea 4.4) — ✅ superada

Verificada el **2026-10-29** tras desplegar **v1.18.0**. El despliegue en producción es **manual** (no hay workflow de deploy); el ejecutó el usuario. Los tres ficheros clave del plugin desplegado son **md5-idénticos** a los del zip del release v1.18.0 y el header reporta `Version: 1.18.0`.

| Comprobación | Antes | Ahora |
|---|---|---|
| `OPTIONS https://atareao.es/mcp` → `Allow` | `POST` | **`200` · `allow: POST, OPTIONS`** |
| `POST` legacy `tools/call` con `_meta.progressToken` | `400 -32602` | **`200` + resultado real de la herramienta** (en `/wp-json/…/mcp` y en `/mcp`) |
| Secuencia legacy del cliente (`initialize` → `tools/list` → `tools/call`) | rota en `tools/call` | **`protocolVersion 2025-06-18`, 3 herramientas, `tools/call` sin error** |
| Cliente MCP real (OpenCode 2.1.4, `opencode run --standalone`) | no podía invocar | **invocó `tools.atareao.get_latest_posts({limit:1})`** → «Podman, IPs reales y La Liga» / `podman-ips-reales-y-la-liga` |
| Era moderna (con `Mcp-Method` o `_meta.protocolVersion`) | — | **sigue seleccionando moderna** (`400 -32602` por faltar capacidades): sin regresión |

**Hallazgo colateral (fuera del change):** la clave `protocol` de la configuración del cliente MCP de OpenCode (`"legacy" | "auto" | "2026-07-28"`) es **inerte** en v2.1.4 — se comprobó en el cable que el cliente **siempre** abre con `initialize` (era legacy) y nunca emite `server/discover`. Se deja puesta (inocua y forward-compatible: el servidor ya negocia nativamente `2026-07-28` si un cliente futuro la honra). `opencode mcp list` diciendo `connected` **no es oráculo**: OpenCode no valida el esquema de las entradas MCP.

---

## 7. Estado final del árbol y de las ramas

```
(sin diferencias sin commitear)
```

- `main` == `3886146` (merge de #119, release v1.18.0).
- `development` == `cdf523b` (merge del archive #121), 4 commits por delante de `main` (archive + sync), normal entre releases.
- Ramas remotas: solo `main` y `development`. Las ramas fusionadas (`fix/mcp-interop-fixes`, `feature/mcp-discovery`, `chore/archive-mcp-discovery`, `release/v1.18.0`, `chore/sync-*`, `chore/archive-mcp-interop-fixes`) se **borraron** con autorización.
- Sin changes de OpenSpec activos (`openspec list` → *No active changes found*).

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

---

## 9. Entrega (referencia de gitflow)

| Paso | Referencia |
|---|---|
| Fix | PR #117 `fix/mcp-interop-fixes → development` (3 commits) |
| Release | PR #118 `development → main` → v1.18.0 (tag + GitHub Release) → PR #119 `release/v1.18.0 → main` → PR #120 `sync → development` |
| Archive | PR #121 `chore/archive-mcp-interop-fixes → development` |
