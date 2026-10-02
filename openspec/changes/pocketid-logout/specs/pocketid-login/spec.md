# Spec Delta

## MODIFIED Requirements

### Requirement: Native wp-login actions passthrough

Las acciones de `wp-login.php` `logout`, `lostpassword`, `checkemail`, `confirmaction`, `rp`, `resetpass` y `postpass` SHALL ejecutarse de forma nativa, sin redirección al proveedor. Esto garantiza el cierre de sesión correcto, la recuperación de acceso por email (emergencia) y los formularios de contenido protegido. Además, al solicitar `action=logout` la sesión SHALL cerrarse también en el proveedor (RP-initiated logout) y la petición resultante con `loggedout` SHALL renderizar la pantalla nativa de sesión cerrada sin reiniciar el flujo OIDC.

#### Scenario: Logout unaffected
- **WHEN** se solicita `wp-login.php?action=logout`
- **THEN** el flujo de logout nativo de WordPress se ejecuta sin redirección a Pocket ID y, cuando el proveedor lo permite, se inicia también el cierre de sesión en PocketID; la petición posterior con `loggedout` muestra la pantalla nativa de sesión cerrada

#### Scenario: Emergency recovery by email
- **WHEN** se solicita `wp-login.php?action=rp` con una clave de restablecimiento válida
- **THEN** el reset nativo permite establecer una nueva sesión aunque Pocket ID esté caído

#### Scenario: Protected content password form
- **WHEN** se envía el formulario de contenido protegido (`action=postpass`)
- **THEN** se procesa de forma nativa sin intervención del proveedor

### Requirement: Password login block gated by configuration

El bloqueo del login tradicional por contraseña SHALL aplicarse únicamente cuando la configuración está completa Y la opción "Exigir PocketID" está activa. El bloqueo SHALL limitarse a las peticiones con credenciales de formulario de `wp-login.php` (presencia de `log` y `pwd`), **sin depender del botón `wp-submit` ni del campo `action`**, de modo que no pueda eludirse omitiendo `wp-submit` o enviando un `action` (p. ej. `action=login`) en el cuerpo del POST. SHALL NO afectar a las application passwords, XML-RPC ni a la autenticación REST (ninguno de esos flujos fija `log`/`pwd` en la petición). Con la configuración incompleta o el modo exigir inactivo, el login nativo SHALL seguir operativo y la pantalla de login SHALL mostrar un botón "Iniciar sesión con PocketID".

#### Scenario: Plugin unconfigured
- **WHEN** falta la URL, el client ID o el secret
- **THEN** el formulario de contraseña funciona con normalidad y no hay redirección alguna

#### Scenario: Configured without enforcement
- **WHEN** la configuración es completa pero el toggle "Exigir PocketID" está inactivo
- **THEN** el login nativo funciona y se muestra el botón "Iniciar sesión con PocketID"

#### Scenario: Enforcement active on the login form
- **WHEN** el toggle está activo y se envía a `wp-login.php` un POST con `log` y `pwd`, aunque se omita `wp-submit` o se incluya un `action` (p. ej. `action=login`)
- **THEN** se devuelve un `WP_Error` que informa del uso obligatorio de Pocket ID

#### Scenario: Application passwords unaffected
- **WHEN** una aplicación se autentica por REST con una application password
- **THEN** la autenticación se permite aunque el modo exigir esté activo

## ADDED Requirements

### Requirement: Post-logout state is respected

Cuando la petición **GET** a `wp-login.php` es la página de cierre de sesión (presencia del parámetro `loggedout`), el sistema SHALL NOT iniciar el flujo OIDC, aunque la configuración esté completa y el modo "Exigir PocketID" esté activo. En ese caso SHALL renderizarse la pantalla nativa de WordPress "Has cerrado la sesión", evitando el re-login inmediato y silencioso por la sesión SSO aún vigente en el proveedor. La excepción SHALL limitarse a peticiones GET: una petición POST con credenciales (`log` + `pwd`) y el parámetro `loggedout` SHALL NOT quedar exenta y seguirá sujeta al modo "Exigir PocketID", de modo que no pueda eludirse el bloqueo del login por contraseña.

#### Scenario: Enforced logout shows the native logged-out screen
- **WHEN** el modo exigir está activo y se accede por GET a `wp-login.php?loggedout=true`
- **THEN** el flujo OIDC no se inicia y se muestra la pantalla nativa "Has cerrado la sesión"

#### Scenario: Enforced login still redirects
- **WHEN** el modo exigir está activo y se accede a `wp-login.php` sin el parámetro `loggedout`
- **THEN** el flujo OIDC se inicia con normalidad

#### Scenario: Enforced POST with the logout parameter is not exempted
- **WHEN** el modo exigir está activo y se envía un POST a `wp-login.php?loggedout=true` con `log` y `pwd` y sin `wp-submit`
- **THEN** la petición no queda exenta por el parámetro `loggedout` y no se autentica por contraseña

### Requirement: RP-initiated logout at the identity provider

Al cerrar sesión, el sistema SHALL redirigir el navegador al `end_session_endpoint` publicado por el discovery de PocketID, incluyendo `id_token_hint` (el `id_token` obtenido en el login), `client_id` y `post_logout_redirect_uri`. El `post_logout_redirect_uri` SHALL ser una URL local validada (`wp_login_url()` con `loggedout=true`). Si el discovery no publica `end_session_endpoint`, si falta el `id_token` o si la redirección no es posible, el logout local SHALL completarse igualmente, devolviendo el destino de logout local de WordPress. Una petición de logout con `post_logout_redirect_uri` no local SHALL descartarse y usar el destino local.

#### Scenario: Provider publishes an end session endpoint
- **WHEN** el discovery publica `end_session_endpoint` y existe un `id_token` para la sesión
- **THEN** el logout redirige al `end_session_endpoint` con `id_token_hint`, `client_id` y `post_logout_redirect_uri` local validado

#### Scenario: Provider without end session endpoint
- **WHEN** el discovery no publica `end_session_endpoint`
- **THEN** el logout local se completa y se redirige al destino local de WordPress, sin error

#### Scenario: Non-local post logout redirect is discarded
- **WHEN** el `post_logout_redirect_uri` solicitado no es una URL local del sitio
- **THEN** se descarta y se usa el destino local de logout

### Requirement: Identity token lifecycle for logout

Tras un login OIDC exitoso, el sistema SHALL persistir el `id_token` recibido en la respuesta del token endpoint en un almacén seguro server-side ligado al usuario (eliminado al cerrar sesión), y SHALL NOT exponerlo nunca en cookies legibles por el navegador ni en la interfaz. Al cerrar sesión, el sistema SHALL eliminar el `id_token` almacenado. Si no existe un `id_token` para la sesión, el sistema SHALL realizar el logout del proveedor sin `id_token_hint` o, si no es posible, completar solo el logout local (fail-safe).

#### Scenario: Identity token stored after login
- **WHEN** el token endpoint devuelve un `id_token` en un login exitoso
- **THEN** el `id_token` se guarda server-side ligado a la sesión y no queda accesible desde el navegador

#### Scenario: Identity token removed on logout
- **WHEN** el usuario cierra sesión
- **THEN** el `id_token` almacenado se elimina

#### Scenario: Logout without an identity token
- **WHEN** no existe un `id_token` para la sesión
- **THEN** el logout del proveedor se realiza sin `id_token_hint` o solo se completa el logout local, sin bloquear el cierre de sesión
