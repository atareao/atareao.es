# Tasks: Endurecer la exención de autenticación REST

> Sin framework de tests ni build tools. Verificación por arnés externo + estáticos. La implementación arranca **solo tras la aprobación**.

## 1. Línea base
- [x] 1.1 Baseline `just php-lint` (0 errores) y `just phpcs --standard=PSR12 --report=summary` (anotar errores/warnings).
- [x] 1.2 Evidencia del comportamiento actual con el arnés RED: `strpos` exime un URI con la subcadena en el *query string* (debe fallar el escenario «subcadena NO exime»).

## 2. RED — arnés externo (falla con el código actual)
- [x] 2.1 Arnés PHP `/tmp/opencode/rest-auth-harness/` (stubs de WordPress: `is_user_logged_in`, `rest_get_url_prefix`, `WP_Error`) que carga y ejecuta `atareao_functionality_rest_auth_errors()` con distintos `$_SERVER`/`$_GET` → debe fallar los checks de igualdad exacta.
- [x] 2.2 Los checks de línea base (ruta exacta exenta, lectura anónima permitida, sesión no rechazada) deben pasar ya en RED.

## 3. GREEN — implementación mínima
- [x] 3.1 `atareao-functionality.php`: resolver la ruta canónica (forma `rest_route` y forma `/wp-json/<ruta>`) y comparar por igualdad exacta con la lista blanca tras `rtrim('/')`; eliminar el `strpos` sobre `REQUEST_URI`.
- [x] 3.2 Arnés PHP en **VERDE** para todos los escenarios de la spec.

## 4. REFACTOR
- [x] 4.1 Mantener la lista blanca como datos; helper privado para resolver la ruta.
- [x] 4.2 `just php-lint` 0; `phpcs` sin nuevos errores; `php -l` OK.

## 5. Verificación
- [x] 5.1 `openspec validate harden-rest-auth-exemption` → valid.
- [x] 5.2 Revisión de seguridad (auditor backend): 2 MEDIUM preexistentes (AUD-BE-001, AUD-BE-002) → **cerradas** en la ampliación (sección 8); re-auditoría → **0 hallazgos abiertos**, sin bypass residual y sin regresiones.

## 6. E2E en producción (tras despliegue, los hace el usuario)
- [ ] 6.1 POST anónimo a `/wp-json/wp/v2/posts` → **401**.
- [ ] 6.2 POST anónimo a `/wp-json/wp/v2/posts?ref=/atareao/v1/mcp` → **401** (ya no exime).
- [ ] 6.3 POST anónimo al MCP exacto `/wp-json/atareao/v1/mcp` → sigue funcionando.
- [ ] 6.4 Publicación con Application Password → sigue funcionando.

## 7. Entrega
- [x] 7.1 Sincronizar este `tasks.md`.
- [ ] 7.2 PR por gitflow a `development` (commits convencionales con gitmoji).
- [ ] 7.3 `openspec archive harden-rest-auth-exemption`.

## 8. Ampliación de alcance (AUD-BE-001 / AUD-BE-002)
- [x] 8.1 AUD-BE-001 — `atareao_functionality_rest_route_from_request()` resuelve la **ruta efectiva** con precedencia de WordPress: `$_POST['rest_route']` (no vacío) > `$_GET['rest_route']` (no vacío) > reescritura `/wp-json/<ruta>`; mantiene `rtrim($ruta, '/')` e igualdad exacta.
- [x] 8.2 AUD-BE-002 — nuevo helper `atareao_functionality_rest_effective_method()`: `$_GET['_method']` > cabecera `X-HTTP-Method-Override` > `$_SERVER['REQUEST_METHOD']`, todo en `strtoupper`, aplicado **antes** de la puerta `GET/HEAD/OPTIONS`.
- [x] 8.3 Arnés extendido con 5 checks nuevos (`10`, `11`, `12`, `13a`, `13b`) que cubren *method override* por query/cabecera y precedencia del cuerpo POST sobre el *path*.
- [x] 8.4 RED contra la copia prístina de `HEAD`: **9/17** (fallan 3, 4a, 4b, 10, 11, 12, 13a, 13b).
- [x] 8.5 GREEN contra el repo: **17/17** checks (los 12 previos siguen pasando).
- [x] 8.6 `just php-lint-changed` 0 errores; `phpcs --standard=PSR12 --report=summary` del fichero: 0 errores, 1 warning (preexistente, sin cambios).

> Evidencia: arnés `/tmp/opencode/rest-auth-harness/` → RED **9/17**, GREEN **17/17**.
