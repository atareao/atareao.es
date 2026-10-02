# pocketid-login Specification

## Purpose
TBD - created by archiving change pocketid-oidc-login. Update Purpose after archive.

## Requirements

### Requirement: OIDC Discovery endpoint resolution

Cuando la opción `atareao_pocketid_url` está configurada, el sistema SHALL resolver los endpoints de autorización, token y userinfo desde el documento de descubrimiento OIDC (`/.well-known/openid-configuration`) de esa URL, cacheando el resultado durante 12 horas. El endpoint de autorización usado para redirigir el navegador SHALL ser el `authorization_endpoint` publicado por el discovery (en Pocket ID: `${base}/authorize`, la página de login/consentimiento) y NUNCA la API interna `/api/oidc/authorize`. Si el discovery falla o la caché expira, el sistema SHALL reintentar la descarga; si persiste el fallo, SHALL usar rutas por defecto derivadas de la base (`/authorize`, `/api/oidc/token`, `/api/oidc/userinfo`).

#### Scenario: Discovery document available
- **WHEN** se resuelven los endpoints de Pocket ID
- **THEN** se usan los valores publicados en `/.well-known/openid-configuration` y se cachean 12 horas

#### Scenario: Discovery unavailable
- **WHEN** el discovery falla y no hay caché válida
- **THEN** se introducen las rutas por defecto `authorize`, `api/oidc/token`, `api/oidc/userinfo` sobre la URL base

#### Scenario: Authorization endpoint points to the login UI
- **WHEN** se construye la URL de autorización
- **THEN** debe apuntar a `${base}/authorize` (interfaz de Pocket ID), no a la API interna

### Requirement: Authorization request with state and PKCE

Cuando un visitante anónimo accede a una acción permitida de `wp-login.php` con la configuración completa y el modo "Exigir PocketID" activo, el sistema SHALL redirigir al endpoint de autorización con `response_type=code`, `client_id`, `redirect_uri=wp_login_url()` (codificado exactamente una vez), `scope=openid profile email`, un parámetro `state` aleatorio (mínimo 32 caracteres) y un `code_challenge` S256 derivado de un `code_verifier` aleatorio. El `state` y el `code_verifier` SHALL almacenarse en una cookie HttpOnly+Secure+SameSite=Lax con TTL de 5 minutos.

#### Scenario: Anonymous access to wp-login.php
- **WHEN** un visitante anónimo accede a wp-login.php (acción permitida) y el modo exigir está activo
- **THEN** recibe una redirección 302 al endpoint de autorización con todos los parámetros OIDC

#### Scenario: redirect_uri single-encoding
- **WHEN** se construye la URL de autorización
- **THEN** el parámetro `redirect_uri` está codificado exactamente una vez (un doble encoding provocaría un mismatch y denegaría el flujo)

### Requirement: Callback validation and session establishment

Al recibir un `code` en `wp-login.php`, el sistema SHALL: (1) validar el parámetro `state` recibido contra la cookie con `hash_equals`, denegando con HTTP 403 y limpiando la cookie si falta, difiere o ha expirado; (2) canjear el `code` mediante POST al token endpoint con `grant_type=authorization_code`, `client_id`, `client_secret`, `redirect_uri`, `code` y `code_verifier` (autenticación de cliente `client_secret_post`); (3) solicitar userinfo con el `access_token` (Bearer); (4) aceptar únicamente emails válidos con claim `email_verified` verdadero (si el claim existe); (5) autenticar SOLO usuarios de WordPress existentes, buscados por email, denegando con 403 a los inexistentes; (6) limpiar cookies de auth previas, establecer la sesión de WordPress y redirigir a `redirect_to` validado como URL local o, en su defecto, al panel.

#### Scenario: Successful login
- **WHEN** el code y el state son válidos y el email pertenece a un usuario WP existente
- **THEN** se inicia la sesión de WordPress y se redirige al `redirect_to` validado o al panel

#### Scenario: State mismatch or expiry
- **WHEN** el `state` recibido no coincide con la cookie o ha expirado
- **THEN** se responde con un error 403 y se elimina la cookie de state

#### Scenario: Invalid or expired authorization code
- **WHEN** el token endpoint rechaza el code o el `code_verifier` es incorrecto
- **THEN** se registra el error en el log del servidor y se muestra al usuario un mensaje genérico

#### Scenario: Unregistered or unverified user
- **WHEN** el email no pertenece a ningún usuario de WordPress o `email_verified` es falso
- **THEN** se deniega el acceso con HTTP 403 sin crear ningún usuario

#### Scenario: Session fixation prevention
- **WHEN** se establece una sesión tras un login OIDC exitoso
- **THEN** se borran las cookies de autenticación previas antes de emitir las nuevas

### Requirement: Native wp-login actions passthrough

Las acciones de `wp-login.php` `logout`, `lostpassword`, `checkemail`, `confirmaction`, `rp`, `resetpass` y `postpass` SHALL ejecutarse de forma nativa, sin redirección al proveedor. Esto garantiza el cierre de sesión correcto, la recuperación de acceso por email (emergencia) y los formularios de contenido protegido.

#### Scenario: Logout unaffected
- **WHEN** se solicita `wp-login.php?action=logout`
- **THEN** el flujo de logout nativo de WordPress se ejecuta sin redirección a Pocket ID

#### Scenario: Emergency recovery by email
- **WHEN** se solicita `wp-login.php?action=rp` con una clave de restablecimiento válida
- **THEN** el reset nativo permite establecer una nueva sesión aunque Pocket ID esté caído

#### Scenario: Protected content password form
- **WHEN** se envía el formulario de contenido protegido (`action=postpass`)
- **THEN** se procesa de forma nativa sin intervención del proveedor

### Requirement: Password login block gated by configuration

El bloqueo del login tradicional por contraseña SHALL aplicarse únicamente cuando la configuración está completa Y la opción "Exigir PocketID" está activa. El bloqueo SHALL limitarse al formulario HTML de `wp-login.php` (detección de `wp-submit` con `log` y `pwd` y acción vacía) y SHALL NO afectar a las application passwords, XML-RPC ni a la autenticación REST. Con la configuración incompleta o el modo exigir inactivo, el login nativo SHALL seguir operativo y la pantalla de login SHALL mostrar un botón "Iniciar sesión con PocketID".

#### Scenario: Plugin unconfigured
- **WHEN** falta la URL, el client ID o el secret
- **THEN** el formulario de contraseña funciona con normalidad y no hay redirección alguna

#### Scenario: Configured without enforcement
- **WHEN** la configuración es completa pero el toggle "Exigir PocketID" está inactivo
- **THEN** el login nativo funciona y se muestra el botón "Iniciar sesión con PocketID"

#### Scenario: Enforcement active on the login form
- **WHEN** el toggle está activo y se envía el formulario de contraseña de wp-login.php
- **THEN** se devuelve un `WP_Error` que informa del uso obligatorio de Pocket ID

#### Scenario: Application passwords unaffected
- **WHEN** una aplicación se autentica por REST con una application password
- **THEN** la autenticación se permite aunque el modo exigir esté activo

### Requirement: Settings page with connectivity test

El sistema SHALL exponer una página de ajustes (Ajustes → PocketID Login) con los campos URL de Pocket ID, Client ID, Client Secret (patrón "dejar en blanco para conservar el actual"), el toggle "Exigir PocketID" y un botón "Probar conexión" que descarga el discovery, valida la respuesta y muestra los tres endpoints detectados. El guardado SHALL protegerse con nonce (`check_admin_referer`) y la URL SHALL validarse como `https://`. La página SHALL mostrar el Redirect URI (`wp_login_url()`) que debe registrarse en Pocket ID.

#### Scenario: Connectivity test succeeds
- **WHEN** la URL configurada responde con un discovery válido
- **THEN** se muestran los endpoints de autorización, token y userinfo detectados

#### Scenario: Connectivity test fails
- **WHEN** la URL es inalcanzable o el discovery es inválido
- **THEN** se muestra un mensaje de error genérico y se registra el detalle en el log

#### Scenario: Secret field left blank
- **WHEN** se guardan los ajustes con el campo Client Secret vacío
- **THEN** se conserva el secret almacenado previamente

### Requirement: Error handling without information leakage

Todos los fallos (red, HTTP, respuestas no parseables, claims ausentes) SHALL registrarse en el log del servidor con un prefijo identificable (`[atareao-pocketid]`) y SHALL presentarse al usuario como mensajes genéricos en español a través de `wp_die`, sin exponer detalles internos de red, HTTP o del proveedor.

#### Scenario: Outage of the identity provider
- **WHEN** Pocket ID está inalcanzable durante el callback
- **THEN** se registra el error detallado en el log y el usuario ve un mensaje genérico de error temporal
