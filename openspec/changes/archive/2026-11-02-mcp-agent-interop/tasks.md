# Tasks: MCP disponible para cualquier agente

## 0. Diagnóstico ya medido

- [x] Evidencia del `400` en `tools/list` con solo `protocolVersion`.
- [x] Evidencia del `502` en `/.well-known/mcp` con UA `curl` frente a `404` con UA no bloqueado.

## 1. RED

- [x] Escribir en `tests/test-valet.php`, suite «MCP Dual-Era Transport», 2 tests nuevos que deben fallar hoy y 2 tests de regresión que deben seguir verdes:
  - [x] (a) petición moderna `tools/list` con `protocolVersion` y sin `clientCapabilities` → `200` con las 3 herramientas;
  - [x] (b) notificación moderna con `protocolVersion` y sin `clientCapabilities` → `202` sin cuerpo;
  - [x] (c) petición sin `protocolVersion` → era legacy `200`/`202` (regresión: nunca `-32602`);
  - [x] (d) mantener en verde los tests existentes.
- [x] Ejecutar `just test` y pegar la salida literal con los fallos esperados.

```text
--- Scenario: Metadatos obligatorios ausentes ---
  PASS: (legacy) sin protocolVersion en _meta se sirve por la era legacy y responde 200
  PASS: (legacy) sin protocolVersion devuelve las 3 herramientas y nunca -32602

--- Scenario: Capacidades del cliente ausentes ---
  FAIL: (modern) tools/list sin clientCapabilities responde 200 (Expected: 200, got: 400)
  FAIL: (modern) tools/list sin clientCapabilities devuelve las 3 herramientas
  FAIL: (modern) el resultado lleva resultType complete (Expected: 'complete', got: NULL)
  FAIL: (modern) el resultado lleva serverInfo en _meta (Expected: 'atareao-mcp', got: NULL)

--- Scenario: Notificacion moderna con metadatos incompletos ---
  FAIL: (modern) notificacion sin clientCapabilities responde 202 (Expected: 202, got: 400)
  FAIL: (modern) notificacion sin clientCapabilities no lleva cuerpo (Expected: NULL, got: array (
  'jsonrpc' => '2.0',
  'error' => 
  array (
    'code' => -32602,
    'message' => 'Invalid params',
  ),
  'id' => NULL,
))

============================================
Total passed: 1082, Total failed: 6
FAILED TESTS:
  - (modern) tools/list sin clientCapabilities responde 200 (Expected: 200, got: 400)
  - (modern) tools/list sin clientCapabilities devuelve las 3 herramientas
  - (modern) el resultado lleva resultType complete (Expected: 'complete', got: NULL)
  - (modern) el resultado lleva serverInfo en _meta (Expected: 'atareao-mcp', got: NULL)
  - (modern) notificacion sin clientCapabilities responde 202 (Expected: 202, got: 400)
  - (modern) notificacion sin clientCapabilities no lleva cuerpo (Expected: NULL, got: array (
  'jsonrpc' => '2.0',
  'error' => 
  array (
    'code' => -32602,
    'message' => 'Invalid params',
  ),
  'id' => NULL,
))
error: recipe `test` failed with exit code 1
```

## 2. GREEN

- [x] En `class-mcp.php`, eliminar el rechazo por `clientCapabilities` ausente (línea 568) y tratarlo como objeto vacío; conservar la validación de `protocolVersion`, versión soportada y cabeceras.
- [x] Nota: al eliminar el rechazo por `clientCapabilities`, revisar que el `-32602` siga disparándose donde corresponde (argumentos inválidos de herramientas y `params.name` no textual).
- [x] En `nginx/pro/nginx.conf`, añadir `~^/\.well-known/mcp(/|\?|$)` al mapa `$mcp_machine_path`.
- [x] Ejecutar `just test` (todo verde); `npx tsc --noEmit` no aplica; usar `just php-lint` y `just phpcs`.

```text
============================================
Total passed: 1090, Total failed: 0
All tests passed successfully!
```

```text
No syntax errors detected in wp-content/plugins/atareao-functionality/includes/class-mcp.php
No syntax errors detected in wp-content/plugins/atareao-functionality/atareao-functionality.php
```

```text
Time: 1.24 secs; Memory: 46MB
error: recipe `phpcs` failed with exit code 2
```

Nota `phpcs`: el `phpcs` global falla por 841 errores preexistentes en theme/plugin/JS, fuera del alcance de este change. En `tests/test-valet.php` hay 2 errores y 75 warnings, todos preexistentes: el cambio no añade ninguno tras partir la línea larga. `class-mcp.php` tiene 0 errores y 5 warnings preexistentes. No se ejecuta `phpcbf` porque no hay violaciones nuevas que corregir.

```text
FILE: /workspace/tests/test-valet.php
FOUND 2 ERRORS AND 75 WARNINGS AFFECTING 76 LINES
```

## 3. REFACTOR

- [x] `just phpcbf` si hay violaciones. (No aplica: sin violaciones nuevas en los tests; los 2 errores y 75 warnings de `tests/test-valet.php` son preexistentes.)
- [x] `just phpcs`.
- [x] `just php-lint`.
- [x] `just test` de nuevo. Sin cambios de comportamiento.

```text
============================================
Total passed: 1090, Total failed: 0
All tests passed successfully!
```

```text
No syntax errors detected in wp-content/plugins/atareao-functionality/includes/class-mcp.php
No syntax errors detected in wp-content/plugins/atareao-functionality/atareao-functionality.php
```

```text
Time: 1.24 secs; Memory: 46MB
error: recipe `phpcs` failed with exit code 2
```

```text
FILE: /workspace/tests/test-valet.php
FOUND 2 ERRORS AND 75 WARNINGS AFFECTING 76 LINES
```

Nota `phpcs`: `tests/test-valet.php` mantiene 2 errores y 75 warnings preexistentes (el cambio no añade ninguno tras partir la línea larga); `class-mcp.php` tiene 0 errores y 5 warnings preexistentes; el `phpcs` global falla por 841 errores preexistentes en theme/plugin/JS. No se ejecuta `phpcbf` porque no hay violaciones nuevas que corregir.

## 4. DEPLOY (producción)

- [x] Copiar `nginx/pro/nginx.conf` al bind-mount `/home/lorenzo/docker/wordpress/nginx.conf` con copia de seguridad previa y hash.
- [x] Recargar nginx.
- [x] Desplegar el plugin al volumen `wordpress_wordpress` con copia previa y hashes.
- [x] Verificar que el md5 de repo y producción coincide.
- [x] Pegar la evidencia literal de cada comando.

```text
=== copia previa nginx ===
TS=20261102113025
dc48bca182c5a1ad03be63d5b841cd2d  nginx.conf
dc48bca182c5a1ad03be63d5b841cd2d  nginx.conf.bak-20261102113025
--- md5 tras copia ---
e86c0f16bd5f18544f5185dda0d2d016  nginx.conf
--- nginx -t ---
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
=== reload nginx ===
2026/11/02 10:33:52 [notice] 873#873: signal process started
reload_exit=0
```

```text
=== copia previa plugin ===
/home/lorenzo/docker/wordpress/backups/class-mcp.php.bak-20261102113350
f7c2dfa6f705ff0ffc729df13f75aca8  class-mcp.php.bak-20261102113350
--- md5 prod tras copia ---
55c603703ea4ca920568a48208c073be  /var/www/html/wp-content/plugins/atareao-functionality/includes/class-mcp.php
-rw-r--r--    1 www-data www-data     47683 ... class-mcp.php
=== reload php-fpm ===
PID 1: docker-init; master php-fpm PID 7
docker exec atareao sh -c "kill -USR2 7" -> reload_exit=0
```

## 5. VERIFICACIÓN E2E

- [x] Repetir la simulación del agente:
  - [x] `initialize` → `200`;
  - [x] `tools/list` con solo `protocolVersion` → `200` con 3 herramientas;
  - [x] `tools/list` con ambos metadatos → `200`;
  - [x] `tools/call search_posts` → `200`;
  - [x] `server/discover` → `200`;
  - [x] `GET /.well-known/mcp` con UA `curl` → `404` (no `502`);
  - [x] `GET /mcp/server-card` con UA `curl` → `200`.
- [x] Pegar la salida literal.

```text
=== GET /.well-known/mcp UA curl ===
HTTP 404
=== GET /mcp/server-card UA curl ===
HTTP 200
=== GET /.well-known/mcp UA wget ===
HTTP 404
=== GET /mcp/server-card UA python-requests ===
HTTP 200
```

```text
=== initialize ===
HTTP 200
{"jsonrpc":"2.0","result":{"protocolVersion":"2025-06-18","serverInfo":{"name":"atareao-mcp","version":"1.19.0"},"capabilities":{"tools":{}}},"id":1}
=== tools/list SIN clientCapabilities ===
HTTP 200
tools=3 (get_latest_posts, get_post, search_posts); resultType=complete; _meta.io.modelcontextprotocol/serverInfo.name=atareao-mcp
=== tools/list CON clientCapabilities ===
HTTP 200
=== tools/call search_posts ===
HTTP 200
resultType=complete; content devuelto con resultados reales de búsqueda
=== server/discover ===
HTTP 200
{"jsonrpc":"2.0","result":{"supportedVersions":["2026-07-28","2025-06-18"],"capabilities":{"tools":{}},"ttlMs":3600000,"cacheScope":"public","resultType":"complete","_meta":{"io.modelcontextprotocol/serverInfo":{"name":"atareao-mcp","version":"1.19.0"}}},"id":4}
```

## 6. ARCHIVE

- [x] `openspec archive mcp-agent-interop`.
- [x] `openspec validate --all --strict`.
- [x] Pegar la salida.

```text
$ openspec archive mcp-agent-interop -y

Proposal warnings in proposal.md (non-blocking):
  ⚠ Why section should not exceed 1000 characters
Task status: 30/33 tasks
Warning: 3 incomplete task(s) found. Continuing due to --yes flag.

Specs to update:
  mcp-discovery: update
  mcp-server: update
Applying changes to openspec/specs/mcp-discovery/spec.md:
  ~ 1 modified
Applying changes to openspec/specs/mcp-server/spec.md:
  ~ 1 modified
Totals: + 0, ~ 2, - 0, → 0
Specs updated successfully.
Change 'mcp-agent-interop' archived as '2026-11-02-mcp-agent-interop'.
```

```text
$ openspec validate --all --strict
...
Totals: 34 passed, 0 failed (34 items)
```

Nota: el aviso `3 incomplete task(s)` corresponde a las tres casillas de esta misma sección 6, que se marcan ahora tras ejecutar el archivado. El resto de avisos `[INFO] Requirement text is very long` son informativos y no afectan a la validación (`exit 0`).
