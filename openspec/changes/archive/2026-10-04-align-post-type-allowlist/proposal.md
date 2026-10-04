# Proposal: Alinear el contrato de `post_type` con la lista permitida

## Why

Las specs `mcp-server` y `webmcp` documentan el argumento opcional `post_type` como **uno de los seis tipos de contenido del dominio**: `post`, `tutorial`, `capitulo`, `aplicacion`, `podcast`, `software`. El `enum` del cliente WebMCP (`assets/js/webmcp.js:23`) contiene exactamente esos seis.

Sin embargo, el servidor valida el argumento explícito contra `get_post_types(array('public' => true))` (`class-mcp.php:520`, usado por `postTypeArgument()` en la línea 548). Ese conjunto **incluye `page`** y otros tipos públicos que no pertenecen al dominio, de modo que `post_type=page` es aceptado mientras el contrato documentado y el cliente solo reconocen los seis.

**Riesgo: ninguno (contrato/coherencia).** No hay impacto de seguridad: la consulta sería de solo lectura y pública. Pero servidor y cliente declaran conjuntos distintos, lo que hace ambiguo el contrato y puede producir resultados inesperados en un agente (p. ej. `search_posts` con `post_type=page` devolviendo páginas).

> Registrado como «SEC-BE-002» en las notas del change `webmcp-content-tools`.

## What Changes

- Se introduce una **lista permitida explícita** en el servidor (`post`, `tutorial`, `capitulo`, `aplicacion`, `podcast`, `software`) que valida el argumento **explícito** `post_type` de `search_posts` y `get_latest_posts`.
- Un valor fuera de la lista —incluido `page`— SHALL responder JSON-RPC **`-32602`** (invalid params) sin ejecutar la consulta, igual que ya ocurre con el tipo inexistente `application`.
- El comportamiento **sin** `post_type` (ausente/`null`) **no cambia**: sigue abarcando todos los tipos públicos, tal y como declara el escenario «`post_type` ausente se comporta como hasta ahora».
- El `enum` del cliente WebMCP ya coincide con la lista (seis tipos); **no requiere cambios**. No se renombra ningún tipo, herramienta, argumento ni código de error.

## Capabilities

### New Capabilities

- (ninguna)

### Modified Capabilities

- `mcp-server`: el argumento explícito `post_type` de las herramientas de consulta se valida contra una **lista permitida fija** de los seis tipos del dominio, y no contra «cualquier tipo público»; el comportamiento por defecto (sin argumento) se conserva.

## Impact

- **Código:** `wp-content/plugins/atareao-functionality/includes/class-mcp.php` → constante de lista permitida + `postTypeArgument()`.
- **Verificación:** arnés PHP existente (extendido) en `/tmp/opencode/`; `just php-lint`; `phpcs`.
- **Sin cambios** en el JS ni en producción (el despliegue lo ejecuta el usuario).
