# Design: Endurecer la exención de autenticación REST

## Contexto

`rest_authentication_errors` recibe un único argumento (`$result`) y se ejecuta para toda petición REST. El filtro del plugin se engancha a ese filtro y, para peticiones anónimas con método con efectos, exime las rutas de una lista blanca comparando con `strpos` sobre `REQUEST_URI`.

## Decisión 1 — Resolver la ruta canónica, no el URI crudo

La ruta REST canónica se obtiene de dos formas soportadas por WordPress:

1. **Forma de parámetro:** `exists($_GET['rest_route'])` → la ruta es `'/' . ltrim($_GET['rest_route'], '/')`.
2. **Forma de reescritura:** el *path* contiene `'/' . rest_get_url_prefix() . '/'` (por defecto `/wp-json/`); la ruta es el sufijo tras ese prefijo.

Se usa `parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)` para descartar el *query string* de la comparación (ese es justamente el vector del fallo actual).

## Decisión 2 — Comparar por igualdad exacta, tras normalizar

Tanto la ruta resuelta como la ruta pública se normalizan con `rtrim($ruta, '/')` y se comparan con `===`. Así:

- `/atareao/v1/mcp` → exenta.
- `/atareao/v1/mcp/` → exenta (barra final normalizada).
- `/atareao/v1/mcp-extra` → **no** exenta.
- `/wp-json/wp/v2/posts?ref=/atareao/v1/mcp` → **no** exenta (el *query* no entra en la comparación).

## Decisión 3 — Conservar la lista blanca como datos

La lista de rutas públicas se mantiene como un `array` (`$public_routes = array('/atareao/v1/mcp')`) resuelto por igualdad exacta, de modo que añadir una futura ruta pública sea un cambio de datos y no de lógica.

## Decisión 4 — No cambiar la puerta por método ni la sesión

Se conservan `is_user_logged_in()` y la puerta por método (`GET`/`HEAD`/`OPTIONS`). Las Application Passwords no se ven afectadas porque la autenticación ya resolvió la sesión antes de este filtro.

## Fuera de alcance

- **SEC-BE-002** (alineación del `enum` de `post_type`) → change propio `align-post-type-allowlist`.
- **Rotación de secretos (SEC-GEN-002)** → acción de despliegue del usuario.
- Endurecer el resto de rutas REST más allá de la puerta de autenticación.
