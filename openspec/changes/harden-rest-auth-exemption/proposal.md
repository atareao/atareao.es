# Proposal: Endurecer la exención de autenticación REST

## Why

El filtro `atareao_functionality_rest_auth_errors` (`atareao-functionality.php:82-110`) es la **puerta** que rechaza con `401` las peticiones REST anónimas de escritura y exime de esa puerta a las rutas públicas de solo lectura. La exención se comprueba con `strpos($request_uri, $route) !== false` (línea 98): una **coincidencia de subcadena** en cualquier parte del URI, incluido el *query string*, no solo en la ruta.

Consecuencia: una petición anónima con un método con efectos hacia cualquier ruta REST cuyo URI contenga el texto `/atareao/v1/mcp` (por ejemplo `/wp-json/wp/v2/posts?ref=/atareao/v1/mcp`) **esquiva la puerta** y llega al registro de rutas como si fuera una ruta pública. Desaparece la primera barrera de autenticación; la única defensa que queda son los `permission_callback` de cada endpoint (defensa en profundidad incompleta).

**Riesgo: medio-bajo (defense-in-depth).** No se ha observado explotación: los endpoints de core validan capacidad y devolverían `401`/`403` por su cuenta. Pero la puerta debe ser correcta: una exención de autenticación solo puede aplicarse a la **ruta REST exacta**, nunca a cualquier URI que la contenga.

> Este hallazgo se registró como «SEC-BE-001» en las notas del change `webmcp-content-tools`. No debe confundirse con el «SEC-BE-001» del change `rest-blocks-hardening`, que trataba de la coincidencia de puerto en la lista blanca de OpenGist.

## What Changes

- La resolución de la ruta REST deja de ser un `strpos` sobre el URI crudo y pasa a **obtener la ruta canónica** —forma de reescritura `/wp-json/<ruta>` o forma de parámetro `?rest_route=/<ruta>`— y **compararla por igualdad exacta** con la ruta pública exenta (`/atareao/v1/mcp`), tras normalizar la barra final.
- La puerta por **método** (solo `GET`/`HEAD`/`OPTIONS` para anónimos; el resto exige sesión) y el resto del contrato (`401`, mensaje neutro) se conservan **sin cambios**.
- Las **Application Passwords siguen funcionando**: la resolución ocurre después de `determine_current_user` y la lógica de `is_user_logged_in()` no cambia.
- No se introduce ni renombra ninguna ruta, filtro, opción ni cabecera. No se toca Nginx ni producción.

## Capabilities

### New Capabilities

- `rest-access-policy`: contrato de la **puerta de autenticación REST anónima** —qué métodos se permiten sin sesión y cómo se eximen por **igualdad exacta de ruta** las rutas públicas de solo lectura—, conservando el código de estado (`401`), el mensaje neutro y el funcionamiento de las Application Passwords.

### Modified Capabilities

- (ninguna)

## Impact

- **Código:** `wp-content/plugins/atareao-functionality/atareao-functionality.php` → `atareao_functionality_rest_auth_errors()`.
- **Verificación:** arnés externo `/tmp/opencode/rest-auth-harness/` (stubs de WordPress) para RED/GREEN; `just php-lint`; `phpcs`.
- **Producción:** solo se documenta; el despliegue lo ejecuta el usuario. No se modifican ficheros de producción ni de Nginx.
