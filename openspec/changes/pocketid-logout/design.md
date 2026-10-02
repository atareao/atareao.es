# Design: Pocket ID Logout

## Context

Ver `proposal.md` — Why. El módulo `\Atareao\PocketIDLogin` (`includes/class-pocketid-login.php`) implementa login OIDC Authorization Code + PKCE. El hook `login_init` → `handleLoginFlow()` decide si redirige a PocketID. El token response solo lee `access_token` (el `id_token` se descarta), no existe ningún hook `wp_logout` ni filtro `logout_redirect`, y el discovery no se consulta para `end_session_endpoint`. Con el modo exigir activo, la petición `?loggedout=true` vuelve a disparar el flujo y PocketID reautentica en silencio. Restricciones: PHP ≥ 7.4, convenciones del plugin (clase estática, opciones `atareao_pocketid_*`, log `[atareao-pocketid]`), sin tests automatizados (verificación por lint + manual).

## Goals / Non-Goals

**Goals:**
- Cerrar la sesión también en el proveedor (RP-initiated logout) usando `end_session_endpoint` + `id_token_hint`.
- Respetar el estado post-logout de WordPress para que el cierre de sesión sea visible y no se revierta.
- Persistir y destruir el `id_token` de forma segura, ligado a la sesión.
- Fallar en abierto (fail-safe): si algo del proveedor no está disponible, el logout local funciona igual.

**Non-Goals:**
- Cambiar el flujo de login, la resolución de endpoints ni el bloqueo de contraseña.
- Introducir logout global/back-channel ni revocación de tokens en el proveedor más allá del `end_session_endpoint`.
- Persistir tokens para usarlos en llamadas API posteriores.

## Decisions

### D1. Respetar `loggedout` antes de la rama de enforcement

En `handleLoginFlow()`, comprobar `!empty($_GET['loggedout'])` y retornar antes de forzar el flujo OIDC, **solo para peticiones GET** (`strtoupper($_SERVER['REQUEST_METHOD']) === 'GET'`). Se prefiere esto a inspeccionar `action=logout` porque la petición que realmente reinicia el flujo es la redirección resultante (`?loggedout=true`), no la acción original.

- **Restricción a GET (SEC-BE-001, seguridad)**: el `case 'default'`/`login` de `wp-login.php` llama a `wp_signon()` sin exigir `wp-submit`, y `blockPasswordLogin()` solo bloquea el formulario cuando existe `wp-submit`. Eximir también los POST permitiría un bypass del modo exigir: un POST a `wp-login.php?loggedout=1` con `log`+`pwd` sin `wp-submit` autenticaría por contraseña. El estado post-logout de WordPress llega por un 302 GET, así que la excepción solo necesita aplicarse a GET.
- **Alternativa considerada**: añadir `loggedout` a la allowlist de acciones nativas. Descartada: `loggedout` no es una acción, es una petición a `wp-login.php` sin `action`, y la allowlist se basa en `$_GET['action']`; confundiría el modelo.
- Nota: un acceso POSTERIOR a `wp-login.php` (sin `loggedout`) puede seguir ofreciendo el flujo PocketID; eso es aceptable y D3 evita que reautentique en silencio al haber terminado la sesión del proveedor.

### D2. Logout iniciado en el proveedor vía `logout_redirect` + `allowed_redirect_hosts`

Registrar el filtro `logout_redirect` (`$redirect_to, $requested_redirect_to, $user`). Si el discovery publica `end_session_endpoint` y hay `id_token` para la sesión, devolver una URL construida con `add_query_arg` que incluya `id_token_hint`, `client_id` y `post_logout_redirect_uri` = `wp_login_url()` + `loggedout=true`. En caso contrario, devolver el `$redirect_to` local.

- Se prefiere `logout_redirect` a `wp_logout` porque necesitamos controlar la redirección del navegador al proveedor; `wp_logout` no ofrece un destino.
- **`allowed_redirect_hosts` es imprescindible**: `wp-login.php` aplica `wp_safe_redirect()` al resultado de `logout_redirect`, y `wp_safe_redirect()` rechaza hosts externos (cae al fallback local) si no están en la allowlist. Por eso se registra el filtro `allowed_redirect_hosts` → `allowPocketIdHost($hosts)`, que añade de forma única el host de `atareao_pocketid_url`. Sin él la redirección al `end_session_endpoint` nunca se completaría.
- **`allowPocketIdHost` se acota al contexto de logout (SEC-BE-002)**: para no ampliar globalmente la allowlist de `wp_safe_redirect()`, el filtro solo actúa cuando `$GLOBALS['pagenow'] === 'wp-login.php'` y `$_GET['action'] === 'logout'` (el punto exacto en que `wp-login.php` ejecuta el `wp_safe_redirect()` del `case 'logout'`). En cualquier otro contexto devuelve `$hosts` sin modificar.
- `post_logout_redirect_uri` construido solo con `wp_login_url()` (nunca a partir de entrada del usuario); se documenta en la página de ajustes para registrarlo en el cliente OIDC.
- El `end_session_endpoint` se captura de forma **opcional** en `fetchDiscoveryConfig()` (validación https + host coherente con la base); si falta o es inválido se ignora y se registra en el log. No forma parte de la validez de la caché ni del fallback.
- El `id_token` se elimina en `wp_logout` (acción separada) para que la limpieza ocurra siempre, aunque `logout_redirect` no redirija (ver D3).

### D3. Persistencia del `id_token` server-side por `user_id`

Guardar el `id_token` en un transient `atareao_pocketid_idtoken_<user_id>` con TTL alineado con la expiración de la sesión (`apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user->ID, true)`, con fallback a 2 días). Se escribe en `handleCallback()` con el ID del usuario recién autenticado.

En el logout, la acción `wp_logout` (`handleWpLogout($user_id)`) **lee y borra** el transient: conserva el valor en la propiedad estática `self::$pending_id_token` y elimina el transient. El filtro `logout_redirect`, que corre después (ver la secuencia de core más abajo), **consume** esa estática para construir la URL del `end_session_endpoint`. Como red de seguridad, si la estática está vacía, `handleLogoutRedirect` reintenta leer el transient por `$user->ID` (o `get_current_user_id()`), de modo que nunca queda un `id_token` colgando si `wp_logout` no se disparó.

El motivo de no usar el session token es concreto: en `wp_logout()` de WordPress core la secuencia es `wp_destroy_current_session()` → `wp_clear_auth_cookie()` → `do_action('wp_logout', $user_id)` → `apply_filters('logout_redirect', …)`. La cookie de sesión se destruye ANTES de que corran nuestros hooks, por lo que `wp_get_session_token()` ya no devuelve nada y no podríamos reconstruir una clave derivada del session token para leer o borrar el `id_token`. La clave por `user_id` sobrevive a la destrucción de la sesión.

- **Alternativa descartada**: clave per-sesión (`sha256` del session token). Reflejaría el alcance real por dispositivo, pero requeriría capturar el token en la acción `clear_auth_cookie` (que corre antes del borrado de cookies) y stasharlo en una propiedad estática para poder usarlo después en `wp_logout`/`logout_redirect`. Se descarta por complejidad innecesaria para este sitio.
- Se prefiere transient a opción/usermeta: expira solo y no ensucia la tabla de usuarios.

### D4. Fail-safe y logging

Cualquier fallo (discovery sin `end_session_endpoint`, `id_token` ausente, redirección no viable) se registra con `error_log('[atareao-pocketid] …')` y se cae al comportamiento local. No se muestra error al usuario.

### D5. Bloqueo del login por contraseña basado en credenciales de formulario (SEC-BE-005)

`blockPasswordLogin()` detecta el intento de login tradicional por la presencia de credenciales de formulario (`isset($_POST['log'])` + `isset($_POST['pwd'])`), **sin depender de `wp-submit` ni del campo `action`**. Antes el bloqueo exigía `wp-submit` y `empty($_POST['action'])`, lo que permitía eludir el modo "Exigir PocketID": omitiendo `wp-submit`, o enviando `action=login` en el cuerpo del POST, un usuario ya autenticado (que retorna antes en `is_user_logged_in()` de `handleLoginFlow()`) podía autenticarse por contraseña.

- **Vector cerrado (`action=login`)**: eliminar la dependencia de `action` impide que un POST con `log`+`pwd` y `action=login` esquive el bloqueo.
- **Por qué es seguro**: application passwords, XML-RPC y REST no fijan `$_POST['log']`/`$_POST['pwd']` (el filtro `authenticate` recibe las credenciales en `$username`/`$password`, pero `$_POST` no las contiene). Ningún otro `action` de `wp-login.php` envía ambas claves a la vez: `postpass` envía `post_password`; `lostpassword`/`register` envían `user_login`; `resetpass` envía `pass1`/`pass2`.

## Risks / Trade-offs

- [El proveedor exige `post_logout_redirect_uri` registrado y no coincide] → Documentarlo en ajustes; si el proveedor rechaza, el usuario verá un error del proveedor, no del sitio. Mitigación: mensaje claro en la UI y valor exacto copiable.
- [Con varias sesiones concurrentes del mismo usuario, la última login sobrescribe el `id_token` y el logout desde otro dispositivo no tendrá `id_token_hint`] → Aceptable para un sitio de un único administrador y fail-safe: ese logout termina la sesión local igualmente, solo que sin cerrar la del proveedor. Mejora futura (descartada por complejidad): persistencia per-sesión capturando el token en `clear_auth_cookie`.
- [PocketID puede no publicar `end_session_endpoint` en todas las versiones] → Comportamiento fail-safe; el requirement lo cubre explícitamente.
- [Añadir el host del proveedor a `allowed_redirect_hosts` amplía globalmente los destinos aceptados por `wp_safe_redirect()`] → El host es de confianza (el propio proveedor OIDC configurado) y solo se añade cuando la configuración está completa; es la única forma de que `wp_safe_redirect()` no degrade la redirección de logout al fallback local.
- [Es un cambio que puede quedar huérfano si `pocketid-oidc-login` no se archiva antes] → Prerrequisito de archivado anotado en el proposal.

## Migration Plan

1. Archivar `pocketid-oidc-login` para que `pocketid-login` exista en `openspec/specs/`.
2. Implementar A → B → C → D con `just php-lint`/`just phpcs` en verde.
3. Desplegar; con enforce inactivo no hay cambio observable. Registrar el `post_logout_redirect_uri` en PocketID.
4. Rollback: revertir el archivo PHP; el cambio es aditivo y no toca datos persistidos más allá de un transient efímero.

## Open Questions

- Ninguna que bloquee la implementación: la clave del transient queda decidida por `user_id` (ver D3) y no altera specs ni tareas.
