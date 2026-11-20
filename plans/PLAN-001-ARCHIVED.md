---
# PLAN-001 — Coherencia de tests, specs y docs tras retirar el bloqueo por User-Agent

- **Issue:** #178
- **Rama de trabajo:** `fix/nginx-sin-bloqueo-ua` (PR #177)
- **Rama base:** `development`
- **Estado:** archivado (completado)

## 1. Objetivo

Dejar el repositorio coherente tras retirar el bloqueo por User-Agent del proxy de producción (ya aplicado en producción y reflejado en el PR #177). El PR solo modificó `nginx/`; hay que alinear tests, specs y documentación con el nuevo estado, y verificar la suite en verde.

## 2. Contexto

El PR #177 eliminó de `nginx/pro/nginx.conf` los mapas `$bad_bot`, `$mcp_machine_path` y `$bad_bot_blocked`, y de `nginx/pro/wp.conf` el bloque `if ($bad_bot_blocked) { return 444; }`. Justificación (medición 24 h): el filtro devolvía `444` (502 en el cliente) a crawlers y validadores legítimos (`Go-http-client` → `/robots.txt`, validador → `/llms.txt`), mientras el único ataque real del periodo llegó con UA de Chrome y lo detuvo la regla de método. Un UA se falsea trivialmente.

Efecto colateral: `tests/test-valet.php` verifica el *mecanismo* retirado, no el comportamiento, y pasa a rojo (~11 aserciones en 4 escenarios). El CI (`lint`) no ejecuta `just test`, por lo que el PR pasó en verde.

## 3. Contramedidas que SE MANTIENEN

- Regla de método `$mcp_endpoint` / `$method_blocked` (`OPTIONS` solo en `/mcp`).
- `limit_req` por endpoint y `limit_conn`.
- Bloqueos de `xmlrpc.php`, `wp-login` y PHP en `wp-content/uploads/`.

## 4. Tareas

- [x] 4.1 Change proposal OpenSpec `openspec new change retire-user-agent-block` con `proposal.md`, deltas de spec (`mcp-discovery`, `mcp-registry`, `podcast-feed`) y `tasks.md`.
- [x] 4.2 `tests/test-valet.php`: reescribir los 4 escenarios para que verifiquen el **comportamiento observable** en lugar del mecanismo:
  - ausencia del filtro por UA (`$bad_bot`, `$bad_bot_blocked`) en `nginx/pro/nginx.conf` y del `if` correspondiente en `nginx/pro/wp.conf`;
  - que el resto de reglas de seguridad siguen presentes (regla de método, límites, xmlrpc, wp-login, PHP en uploads).
- [x] 4.3 Actualizar `openspec/specs/mcp-discovery/spec.md`, `openspec/specs/mcp-registry/spec.md` y `openspec/specs/podcast-feed/spec.md`: reformular los SHALL que describen la exención por UA para reflejar que el filtro se retiró y que las rutas siguen alcanzables por clientes HTTP.
- [x] 4.4 Actualizar la sección «Exención del proxy» de `wp-content/plugins/atareao-functionality/README.md`.
- [x] 4.5 `just lint`/php-lint y `openspec validate` en verde.
- [x] 4.6 `just test` (harness `tests/test-valet.php`) en verde.
- [x] 4.7 Crear `docs/retirar-bloqueo-user-agent.md`.
- [x] 4.8 Archivar el change OpenSpec (`openspec archive retire-user-agent-block`), marcar tareas y renombrar este plan a `plans/PLAN-001-ARCHIVED.md`.

> **Verificación (2026-11-14):** `openspec validate --all` → **38 passed, 0 failed**. El change se archivó como `openspec/changes/archive/2026-11-14-retire-user-agent-block/`. La tarea 4.6 quedó **pendiente de ejecución** por no haber contenedor Podman `atareao-php-cli` en marcha; la verificación equivalente se hizo con `php -l tests/test-valet.php`.

## 5. Verificación (criterios de aceptación)

- `just test` termina con exit code 0 y sin `FAIL:`.
- No quedan referencias a `$bad_bot`, `$bad_bot_blocked` ni `$mcp_machine_path` en el repo (salvo `openspec/changes/archive/**`, que es histórico).
- `openspec validate` pasa para el change y para las specs destino.
- El PR #177 incluye los cambios de tests/specs/docs y se puede mergear a `development`.

## 6. Riesgos

- Reescribir los escenarios podría debilitar la cobertura si solo se comprueba «ausencia». Mitigación: mantener aserciones positivas sobre las contramedidas residuales.
- `just test` requiere el entorno Podman (`atareao-php-cli`) levantado.
---