# Proposal: Retirar el bloqueo por User-Agent del proxy de producción

## Why

**Medido en producción (2026-11-13), no supuesto.** El proxy de producción cerraba la conexión
(`return 444`, traducido por Traefik a un `502` en el cliente) a cualquier User-Agent que coincidiera
con una lista de clientes de línea de comandos (`curl`, `wget`, `python-requests`, `Go-http`, …). Sobre
24 h de logs:

- **892 peticiones de `Go-http-client`**, 888 de ellas a `/robots.txt`, respondidas con `444`/`502`.
- **11 peticiones a `/llms.txt`** de una misma IP rotando User-Agent (`curl`, `python-requests`,
  `Wget`), todas `502`.

El **único ataque real** del periodo —un `PUT` y un `DELETE` a `/wp-json/wp/v2/pages/7154`— llegó con
User-Agent de Chrome y lo detuvo la **regla de método** (`$method_blocked`), no el filtro por
User-Agent. El filtro, por tanto, no aportaba seguridad: un User-Agent se falsea trivialmente y solo
ocultaba el contenido publicado a crawlers y validadores legítimos, que interpretan el cierre de
conexión como «la ruta no existe».

## What Changes

- **Se retiran del proxy `$bad_bot`, `$mcp_machine_path` y `$bad_bot_blocked`**, junto con el
  `if ($bad_bot_blocked) { return 444; }` de `nginx/pro/wp.conf`. Es el cambio ya aplicado en
  `nginx/pro/` (PR #177); este change solo actualiza la spec, los tests y la documentación para que
  describan el comportamiento vigente.
- **Las rutas de descubrimiento (MCP/ARD), las del registro MCP, el feed del podcast y los `.srt`**
  pasan a ser alcanzables por cualquier cliente HTTP: se sirven con su código real (`200`/`404`) y
  nunca con un `444`/`502`.
- **Se mantienen las contramedidas residuales**: la regla de método (`$method_blocked` /
  `$mcp_endpoint`), `limit_req` / `limit_conn` por IP y los bloqueos de `xmlrpc.php`, `wp-login.php`
  y PHP en `uploads`.
- **La suite `tests/test-valet.php`** deja de parsear el mapa retirado y verifica la **ausencia** de un
  mapa que filtre por User-Agent (`map $http_user_agent`), más la permanencia de las contramedidas.

## Fuera de alcance

- No se toca la configuración de `nginx/` (ya corregida en el PR #177).
- No se modifican las reglas de caché (`$skip_cache`), de método, ni los límites por IP.

## Impacto

| Aspecto | Efecto |
|---|---|
| Crawlers y validadores (`Go-http-client`, `curl`, `wget`, …) | **Arreglados**: pasan de `444`/`502` a la respuesta real (`200`/`404`) |
| Ataque real (PUT/DELETE a `/wp-json`) | Sin cambios: sigue detenido por la regla de método |
| Specs `mcp-discovery`, `mcp-registry`, `podcast-feed` | Reformuladas: sin «exención del bloqueo por User-Agent» |
| Suite `tests/test-valet.php` | Reescrita: verifica la ausencia del filtro, no su exención |

## Referencias

- Issue: #178
- PR: #177 (`🐛 fix(nginx): retirar el bloqueo por User-Agent`)
