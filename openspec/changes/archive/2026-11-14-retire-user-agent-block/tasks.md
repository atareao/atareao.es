# Tasks: Retirar el bloqueo por User-Agent del proxy de producción

## 1. Contexto (ya medido en producción, 2026-11-13)

- [x] Evidencia de 892 peticiones de `Go-http-client` (888 a `/robots.txt`) respondidas `444`/`502`.
- [x] Evidencia de 11 peticiones a `/llms.txt` rotando UA (`curl`, `python-requests`, `Wget`), todas `502`.
- [x] Evidencia de que el único ataque real (PUT/DELETE a `/wp-json/wp/v2/pages/7154`) traía UA de Chrome y lo detuvo la regla de método.
- [x] Configuración ya retirada en `nginx/pro/` (PR #177): sin `$bad_bot`, `$mcp_machine_path` ni `$bad_bot_blocked`.

## 2. RED — tests

- [x] Localizar en `tests/test-valet.php` los escenarios que parsean el mapa retirado:
  - [x] ARD (`~L5598`), registro MCP (`~L6092`), feed del podcast (`~L7644`).
- [x] Reescribir los tres bloques para verificar la **ausencia** de un mapa que filtre por User-Agent (`map $http_user_agent`) y la permanencia de las contramedidas residuales (`$method_blocked`/`$mcp_endpoint`, `limit_req`/`limit_conn`, bloqueo de PHP en uploads).
- [x] Eliminar el parseo de `$mcp_machine_path`, sus aserciones de las 9 entradas y toda comprobación de `$bad_bot_blocked`.
- [x] No tocar las aserciones de `$skip_cache`.
- [x] `php -l tests/test-valet.php` → sin errores de sintaxis.

## 3. GREEN — spec y doc

- [x] Delta `mcp-discovery`: reformulado «Canales auxiliares de descubrimiento ARD» (rutas alcanzables, sin filtro por UA, `200`/`404`, nunca `502`).
- [x] Delta `mcp-registry`: reformulado «Cabeceras, acceso y alcance de los artefactos del registro» (sin filtro por UA, servidas sin sesión).
- [x] Delta `podcast-feed`: reformulado «Alcance y no interferencia del feed» (feed alcanzable, sin filtro por UA).
- [x] `wp-content/plugins/atareao-functionality/README.md`, sección «Exención del proxy», actualizada.

## 4. Verificación

- [x] `openspec validate retire-user-agent-block`.
- [x] `rg -n 'mcp_machine_path|bad_bot' tests/ wp-content/plugins/atareao-functionality/README.md` → sin resultados.
- [x] `just test` → **no ejecutado**: no hay contenedor Podman `atareao-php-cli` en marcha. Verificación de sintaxis vía `php -l`.

## 5. Commit

- [x] Commit único con gitmoji en español (`✅ test(nginx): …`).
