# Tasks: Pocket ID Logout

## 1. Respetar el estado post-logout (A)

- [x] 1.1 En `handleLoginFlow()`, retornar sin forzar el flujo OIDC cuando la petición es la página de cierre de sesión (`!empty($_GET['loggedout'])`), colocado tras el manejo de acciones nativas y antes de `is_user_logged_in()`, de modo que se renderice la pantalla nativa "Has cerrado la sesión". Verificación: `just php-lint includes/class-pocketid-login.php` (OK) y prueba manual de `wp-login.php?loggedout=true` con enforce activo (no redirige a PocketID).
- [x] 1.2 Confirmar que la navegación normal a `wp-login.php` sin `loggedout` sigue iniciando el flujo OIDC con enforce activo (no regresión). Verificación: `just php-lint` (OK) y prueba manual del login normal en producción.

## 2. Logout iniciado en el proveedor (B)

- [x] 2.1 Implementar el filtro `logout_redirect` (`$redirect_to, $requested_redirect_to, $user`) que, si el discovery publica `end_session_endpoint` y hay `id_token` para la sesión, devuelve la URL del proveedor con `id_token_hint`, `client_id` y `post_logout_redirect_uri` = `wp_login_url()` + `loggedout=true`; en caso contrario devuelve el `$redirect_to` local. Registrar además el filtro `allowed_redirect_hosts` → `allowPocketIdHost($hosts)`, que añade el host del proveedor: es imprescindible porque `wp-login.php` aplica `wp_safe_redirect()`, que rechaza hosts externos y caería al fallback local sin la redirección al proveedor. Verificación: `just php-lint` (OK) y revisión de la URL generada (parámetros y encoding correctos).
- [x] 2.2 Ampliar la resolución de endpoints (`fetchDiscoveryConfig()`) para conservar el `end_session_endpoint` del discovery de forma **opcional**: supera la validación https + host coherente con la base; si falta o no es válido se ignora (se registra en el log). No forma parte de la validez de la caché ni del fallback. Verificación: `just php-lint` y `just phpcs` (0 errores) y revisión del fallback (proveedor sin el campo → sin redirección externa).
- [x] 2.3 Garantizar el fail-safe: si falta `end_session_endpoint`, falta el `id_token` o la redirección no es viable, el logout local se completa igualmente (se devuelve el `$redirect_to` nativo) y se registra `error_log('[atareao-pocketid] …')`. Verificación: revisión de código y prueba manual con proveedor sin `end_session_endpoint`.

## 3. Ciclo de vida del `id_token` (C)

- [x] 3.1 Al canjear el `code`, leer y persistir el `id_token` del token response en un transient server-side con clave por usuario (`atareao_pocketid_idtoken_<user_id>`), con TTL alineado con la sesión (`apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user->ID, true)`, fallback 2 días). Verificación: `just php-lint` y `just phpcs` (0 errores) y comprobación manual de que el transient se crea tras un login real.
- [x] 3.2 El `id_token` se **lee y borra** en la acción `wp_logout` (`handleWpLogout($user_id)`, la cookie de sesión ya está destruida en ese punto) y se conserva en la propiedad estática `self::$pending_id_token`; el filtro `logout_redirect` (`handleLogoutRedirect`) la **consume** después para construir la URL del proveedor. Así la limpieza ocurre siempre, aunque no se redirija al proveedor, y no se lee/borra en `logout_redirect`. Como red de seguridad, si la estática está vacía `handleLogoutRedirect` reintenta leer el transient por `$user->ID`/`get_current_user_id()`. Verificación: prueba manual de login + logout y comprobación de que el transient desaparece.
- [x] 3.3 Asegurar que el `id_token` nunca se expone en cookies legibles por el navegador ni en la interfaz (solo en el transient server-side). Verificación: revisión de seguridad (grep de `setcookie`/salida) y `just phpcs` (0 errores).
- [x] 3.4 Manejar el caso sin `id_token`: realizar el logout del proveedor sin `id_token_hint` o completar solo el logout local, sin bloquear el cierre de sesión. Verificación: revisión de código y prueba manual.

## 4. UI y documentación (D)

- [x] 4.1 Añadir en la página de ajustes el campo informativo "Post Logout Redirect URI" (`wp_login_url()` + `?loggedout=true`, solo lectura) junto al Redirect URI actual, con el mismo estilo. Verificación: render correcto en wp-admin y valor copiable.
- [x] 4.2 Actualizar `README.md` del plugin: añadir subsección "Cierre de sesión (logout)" dentro de "Autenticación con PocketID (OIDC)" que describa el cierre de sesión local + en el proveedor y el registro del Post Logout Redirect URI, además del comportamiento fail-safe. Verificación: revisión del README y coherencia con la UI.

## 5. Calidad

- [x] 5.1 Ejecutar `just php-lint` y `just phpcs` (PSR12) sobre el plugin; `phpcbf` si procede. Verificación: `just php-lint` sin errores de sintaxis; `just phpcs` sobre `class-pocketid-login.php` con 0 errores (quedan warnings de longitud de línea pre-existentes en el archivo).
- [x] 5.2 Verificación integrada: login OIDC completo, logout con enforce activo (pantalla "sesión cerrada", sin re-login silencioso) y sin regresiones en Matrix/ContactForm u otras clases. Verificación: comandos en verde y prueba manual de extremo a extremo en producción.

## 6. Auditoría de seguridad

- [x] 6.1 Auditoría (auditor-backend) del flujo de logout: open redirect en `logout_redirect`/`post_logout_redirect_uri`, exposición del `id_token`, CSRF, binding del transient a la sesión y compatibilidad PHP 7.4. Verificación: informe sin hallazgos críticos ni altos abiertos. Resultado: SEC-BE-001 (bypass anónimo por `?loggedout`+POST), SEC-BE-002 (allowlist global de `allowed_redirect_hosts`) y SEC-BE-005 (bypass por usuario autenticado vía `action=login`) corregidos; SEC-BE-003/004 informativos, aceptados por diseño.

## 6.2 Correcciones de auditoría (SEC-BE-001/002/005)

Todas las correcciones de esta sección están implementadas y verificadas (`just php-lint` sin errores; `just phpcs` sobre `class-pocketid-login.php` con 0 errores).

- [x] 6.2.1 SEC-BE-001 (crítica, bypass del modo exigir): restringir el retorno temprano de `handleLoginFlow()` por `loggedout` a peticiones GET (`strtoupper($_SERVER['REQUEST_METHOD']) === 'GET'`), de modo que un POST a `wp-login.php?loggedout=true` con `log`+`pwd` sin `wp-submit` siga sujeto a `blockPasswordLogin()`. Verificación: `just php-lint` y `just phpcs` (0 errores nuevos).
- [x] 6.2.2 SEC-BE-002 (baja, acotar `allowed_redirect_hosts`): `allowPocketIdHost()` solo añade el host del proveedor cuando `$GLOBALS['pagenow'] === 'wp-login.php'` y `$_GET['action'] === 'logout'`, evitando ampliar globalmente la allowlist de `wp_safe_redirect()`. Verificación: `just php-lint` y `just phpcs` (0 errores nuevos).
- [x] 6.2.3 SEC-BE-005 (media, causa raíz del bypass): `blockPasswordLogin()` deja de depender de `wp-submit` **y de `action`**; bloquea por presencia de credenciales de formulario (`log` + `pwd`), cerrando el vector `action=login` de un POST ya autenticado. No afecta a application passwords/XML-RPC/REST (ninguno fija esos campos en `$_POST`) ni a otros `action` de `wp-login.php` (no envían `log`+`pwd` a la vez). Verificación: `just php-lint` y `just phpcs` (0 errores nuevos).

## 7. Integración y entrega

- [x] 7.1 Commit convencional en `feature/pocketid-logout` (✨ feat: pocketid logout) + push. Verificación: commit creado con el mensaje correcto.
- [x] 7.2 Abrir PR a `development` por gitflow. Verificación: PR abierto con CI en verde.
