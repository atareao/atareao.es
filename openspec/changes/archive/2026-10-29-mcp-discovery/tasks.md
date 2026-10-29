# Tasks: Descubrimiento MCP — catálogo AI, tarjeta de servidor y conformidad dual-era

> **Nota inicial:** el repositorio **no** tiene framework de tests ni build tools. La verificación combina análisis estático (`just php-lint`, `just phpcs`), el **arnés procedural** `just test` (`tests/test-valet.php` + `tests/wp-stubs.php`, hoy **723 aserciones**) y E2E manual en producción. La implementación arrancó **tras la aprobación del usuario**. Ninguna tarea renombra ni borra opciones, rutas REST ni hooks. Objetivo rector: **que cualquier agente descubra el servidor MCP y pueda conectar**.

## 1. Phase 0 — Línea base y caracterización

- [x] 1.1 Registrar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el par errores/warnings. **Evidencia:** baseline anotado con fecha.
- [x] 1.2 Caracterizar con el arnés el comportamiento actual del transporte: `initialize` devolvía `protocolVersion` `2024-11-05` ignorando la versión ofrecida; `tools/list` y `tools/call` sin `resultType`; `GET` no soportado; cabeceras CORS limitadas a `Content-Type`. **Verificación:** escenarios de caracterización que documentan cada hecho. **Evidencia:** aserciones en verde **antes** del cambio.
- [x] 1.3 Caracterizar la resolución de la ruta efectiva y la puerta anónima: `POST /wp-json/atareao/v1/mcp` exento; la misma petición por un alias limpio devolvía `''` → 401. **Verificación:** aserción que demuestra el 401 del alias. **Evidencia:** test de caracterización en verde.
- [x] 1.4 `openspec validate mcp-discovery --strict` sin hallazgos. **Verificación:** salida literal del CLI.

## 2. TDD — RED

- [x] 2.1 Escribir los escenarios del catálogo AI (media type, validez, una única entrada con `type`/`url`, sin credenciales, accesible sin sesión). **Verificación:** `just test` falla por ausencia de la ruta y el resto sigue en verde.
- [x] 2.2 Escribir los escenarios de la tarjeta de servidor (`$schema` literal, `name` reverse-DNS, `version` semver, `description` ≤100, `remotes[]` con `type`/`url`/`supportedProtocolVersions`, sin credenciales ni primitives).
- [x] 2.3 Escribir los escenarios de cabeceras de los artefactos (CORS, `Cache-Control`, `ETag`/`304`, media type).
- [x] 2.4 Escribir los escenarios de rutas servidas por el plugin y resolubles sin vaciado de reescrituras.
- [x] 2.5 Escribir los escenarios del endpoint limpio `/mcp` (alias atiende `POST`, la ruta legacy sigue operativa, no hay lógica duplicada, `GET /mcp` → 405).
- [x] 2.6 Escribir los escenarios de la era moderna: `_meta` obligatorio (`-32602`+400), versión no soportada (`-32022`+400), `HeaderMismatch` (`-32020`+400) para cada cabecera, `resultType` presente, `serverInfo` presente, `server/discover`, `ttlMs`+`cacheScope`, notificación → 202, método desconocido → 404/`-32601`, `GET`/`DELETE` → 405.
- [x] 2.7 Escribir los escenarios de la era legacy (no-regresión): `initialize` responde, negociación de versión, `tools/list` y `tools/call` siguen operativos.
- [x] 2.8 Escribir los escenarios de CORS ampliado (preflight con `MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name`).
- [x] 2.9 Escribir los escenarios de la puerta anónima para el alias (`POST /mcp` exento; `/mcp-extra` y subcadenas siguen 401).
- [x] 2.10 Escribir el escenario de coherencia tarjeta/comportamiento (`supportedProtocolVersions` = versiones realmente cumplidas). **Verificación:** los escenarios nuevos fallan y los 607 previos siguen en verde.
- [x] 2.11 Escribir los escenarios de anuncio: el meta de descubrimiento de `wp_head` y la documentación apuntan solo a `/mcp`; ningún artefacto anunciado menciona la ruta REST; la ruta REST sigue operativa. **Verificación:** fallan al principio y los previos siguen en verde.

## 3. TDD — GREEN

- [x] 3.1 **Nueva clase `\Atareao\MCPDiscovery`** (`includes/class-mcp-discovery.php`): sirve el catálogo y la tarjeta con sus media types y cabeceras, y expone la **fuente única** de versiones soportadas.
- [x] 3.2 Registrar la clase en `atareao-functionality.php` (`require_once` + `MCPDiscovery::init()`) y enganchar sus rutas con resolución por coincidencia de ruta (D21).
- [x] 3.3 **Alias `/mcp`**: normalizar la petición hacia la ruta REST `atareao/v1/mcp` (D18) interceptando `parse_request` (prioridad 5, antes de `rest_api_loaded`) y alimentando el canal `rest_route`; **sin** necesidad de vaciado de reescrituras. Conservar la ruta legacy. `GET`/`DELETE` → `405`.
- [x] 3.4 **Puerta anónima**: reconocer la ruta efectiva del alias como ruta pública exacta (vía el canal `rest_route` normalizado), sin aflojar la comprobación.
- [x] 3.5 **Era moderna en `class-mcp.php`**: selección de era; `_meta` obligatorio; validación de `MCP-Protocol-Version`/`Mcp-Method`/`Mcp-Name` (con decodificación Base64 del sentinel); `resultType` en todo resultado; `serverInfo` en `_meta`; `server/discover`; `ttlMs`/`cacheScope` en `tools/list`; `202` para notificaciones; `405` para `GET`/`DELETE`; `404`+`-32601` para método desconocido; `-32022` para versión no soportada.
- [x] 3.6 **Era legacy**: `initialize` responde y negocia la versión (mejor soportada ≤ ofrecida; si el cliente no la indica, la más baja soportada); `tools/list` y `tools/call` intactos.
- [x] 3.7 **CORS ampliado** a las cabeceras del protocolo.
- [x] 3.8 Repuntar a `/mcp` el meta de descubrimiento de `wp_head` y las referencias del `README.md`; ninguna referencia anunciada a la ruta REST.
- [x] 3.9 `just test` en **verde**, `just php-lint` 0 errores, `just phpcs` sin delta. **Verificación:** salida literal de los tres comandos.

## 4. TDD — REFACTOR

- [x] 4.1 Unificar en la fuente única las versiones anunciadas por la tarjeta y por `server/discover` (`MCP::supportedProtocolVersions()` delega en `MCPDiscovery::supportedProtocolVersions()`); eliminar constantes duplicadas.
- [x] 4.2 Revisar PSR12 y estructura sin alterar comportamiento. **Verificación:** `just php-lint`, `just phpcs` (delta +0) y `just test` verde.
- [x] 4.3 Comprobar que no queda código muerto ni rutas huérfanas. **Verificación:** `rg` sobre las rutas nuevas y las antiguas.

## 5. Documentación

- [x] 5.1 Documentar en el `README.md` las tres URLs (endpoint limpio `https://atareao.es/mcp`, tarjeta `…/mcp/server-card`, catálogo `/.well-known/ai-catalog.json`), el flujo de descubrimiento y las versiones de protocolo soportadas.
- [x] 5.2 Documentar que la ruta REST legacy se conserva operativa pero **no se anuncia**, y la desviación razonada sobre validación de `Origin`.

## 5b. Conformidad de la era moderna (hallazgos durante la implementación)

- [x] 5b.1 **El resultado de `server/discover` usa `supportedVersions`, no `supportedProtocolVersions`** (D26): este último es el nombre del campo de la tarjeta SEP-2127. El resultado declara además `capabilities`, `ttlMs` y `cacheScope`, y no duplica `serverInfo` fuera de `_meta`. **Verificación:** escenarios `(modern)` y `(opencode)` en verde; simulación de la respuesta ante el probe real del cliente.
- [x] 5b.2 **Los metadatos por petición se leen de `params._meta`** (D27), no del nivel superior del mensaje: `isModernRequest()` comprueba `$body['params']['_meta']` y `handleModernRequest()` lee `$params['_meta']`. **Verificación:** escenarios `(meta)` en verde y flujo completo del cliente real (probe moderno + `tools/list` moderno + `initialize` legacy).
- [x] 5b.3 **`params.name` no textual rechazado con `-32602`** (hallazgo de auditoría SEC-BE-002): se elimina la conversión a cadena que emitía `Array to string conversion` y hacía coincidir la cabecera `Mcp-Name: Array`. **Verificación:** escenario `(invalido)` en verde y sonda de tipos hostiles (array/entero/booleano) sin avisos capturados.

## 6. Verificación final

- [x] 6.1 Análisis estático sin delta: `just php-lint` 0 errores; `just phpcs` delta +0 respecto a 1.1.
- [x] 6.2 Arnés procedural completo en verde (709/0), con los escenarios nuevos y los previos intactos.
- [x] 6.3 Validación estructural de los dos documentos JSON contra sus esquemas y comprobación de la regex de `name` y del límite de `description` (validación standalone: PASS).
- [x] 6.4 `openspec validate mcp-discovery --strict` sin hallazgos.
- [ ] 6.5 E2E en producción: `GET /.well-known/ai-catalog.json` → `GET /mcp/server-card` → `POST /mcp` (`server/discover` y `tools/call`) con un cliente real; `GET /mcp` → 405; no-regresión del sitio público y del endpoint legacy. **Pendiente:** el entorno del sitio no está aprovisionado.
- [x] 6.6 Documentación sincronizada con el comportamiento real (5.1/5.2).
- [x] 6.7 Simulación del flujo completo de un cliente real sobre el transporte: probe `server/discover` → HTTP 200 con `supportedVersions` y `resultType`; `tools/list` moderno → herramientas con `ttlMs`/`cacheScope` y `resultType`; `initialize` legacy → versión negociada y `serverInfo`. **Evidencia:** ejecución literal con las dos clases reales y los stubs del arnés.

## 7. Entrega

- [x] 7.1 PR por gitflow de la rama del change a `development` con commits convencionales (gitmoji). **Evidencia:** PR #115 (`feature/mcp-discovery` → `development`), CI `lint` en verde, mergeado en `11bfa03`.
- [x] 7.2 Marcar las tareas completadas y archivar el change (`openspec archive mcp-discovery`), verificando que los headers del delta coinciden **literalmente** con los de las specs destino. **Evidencia:** el único requisito `MODIFIED` («CORS para la consulta anónima») coincide literalmente con `openspec/specs/mcp-server/spec.md`; el resto son `ADDED` (spec nueva `mcp-discovery` y requisito nuevo en `rest-access-policy`). Archive ejecutado.
- [x] 7.3 Comprobar que `openspec list` ya no muestra el change activo. **Evidencia:** `openspec list` no muestra el change; queda en `openspec/changes/archive/2026-10-29-mcp-discovery/`.
