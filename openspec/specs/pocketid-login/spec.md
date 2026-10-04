# pocketid-login Specification

## Purpose

Esta capability integra Pocket ID, mediante el protocolo OIDC, como proveedor de identidad del sitio WordPress: resuelve los endpoints de descubrimiento y completa el flujo de autorización con PKCE y `state` anti-replay para iniciar sesión, y coordina el cierre de sesión con el proveedor (RP-initiated logout). Define además las garantías de robustez del flujo —validación del callback y del `state`, cookie de estado host-only, TTL suficiente para passkeys y errores sin filtrar información interna— y agrupa la configuración de su comportamiento desde wp-admin (exigencia de PocketID, política de `email_verified` y prueba de conexión). La interfaz pública permanece en español y sin nombrar al proveedor, que solo se menciona en la página de Ajustes.

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

Al recibir un `code` en `wp-login.php`, el sistema SHALL: (1) validar el parámetro `state` recibido contra la cookie con `hash_equals`, denegando con HTTP 403 y limpiando la cookie si falta, difiere o ha expirado; (2) canjear el `code` mediante POST al token endpoint con `grant_type=authorization_code`, `client_id`, `client_secret`, `redirect_uri`, `code` y `code_verifier` (autenticación de cliente `client_secret_post`); (3) solicitar userinfo con el `access_token` (Bearer); (4) aceptar únicamente emails válidos con claim `email_verified` verdadero (si el claim existe); (5) resolver al usuario de WordPress mediante la vinculación `sub`↔usuario (o mediante el email en un primer acceso que complete y persista esa vinculación), y NO solo por email, denegando a los usuarios no vinculados; (6) limpiar cookies de auth previas, establecer la sesión de WordPress y redirigir a `redirect_to` validado como URL local o, en su defecto, al panel. Ante **cualquier** denegación de identidad —email sin cuenta WP, cuenta no vinculada o `sub` en conflicto— el sistema SHALL responder SIEMPRE el mismo error genérico 403, **sin `back_link`**, idéntico para todos los casos y sin revelar si el email tiene cuenta WP ni qué comprobación concreta falló; el motivo se registrará únicamente en el servidor con el prefijo `[atareao-pocketid]`.

#### Scenario: Successful login

- **WHEN** el code y el state son válidos, el `id_token` se valida (si el proveedor lo devuelve) y la identidad (`sub` o email) corresponde a un usuario WP existente y vinculado
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

#### Scenario: Indistinguishable denial

- **WHEN** se compara la respuesta para un email sin cuenta WP, para una cuenta no vinculada y para un `sub` en conflicto
- **THEN** las tres respuestas son idénticas (mismo 403 genérico, sin `back_link`) y no permiten deducir si el email tiene cuenta

#### Scenario: Session fixation prevention

- **WHEN** se establece una sesión tras un login OIDC exitoso
- **THEN** se borran las cookies de autenticación previas antes de emitir las nuevas

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

El bloqueo del login tradicional por contraseña SHALL aplicarse cuando la configuración está completa Y la opción "Exigir PocketID" está activa. En ese estado (política passwordless activa), el sistema SHALL bloquear **solo la contraseña real (interactiva) del usuario**: (a) el formulario de `wp-login.php` y (b) la autenticación por contraseña de usuario vía XML-RPC. El bloqueo SHALL decidirse en el filtro `authenticate` a partir de las credenciales recibidas (comprobando `wp_check_password`), **sin depender de `$_POST['log']`/`$_POST['pwd']`, del botón `wp-submit`, del campo `action` ni de `$_REQUEST['action']`**, de modo que no pueda eludirse (p. ej. `POST /xmlrpc.php?action=lostpassword`). El sistema SHALL NOT interceptar, deshabilitar ni restringir los **Application Passwords de WordPress** ni la autenticación que los usa: no SHALL alterar el flujo `application_password_is_api_request`/`wp_authenticate_application_password`, de modo que un Application Password válido siga autenticando peticiones **REST y XML-RPC** (WordPress admite Application Passwords en ambos canales) y permitiendo publicar/editar contenido con la política activa. Si en el filtro `authenticate` ya existe un `WP_User` que NO proviene de la contraseña real del usuario (Application Password u otro autenticador), el sistema SHALL preservarlo sin sobrescribirlo; un `WP_Error` ajeno a la autenticación por contraseña (p. ej. 2FA) SHALL respetarse, mientras que los errores propios de la autenticación por contraseña de core (`invalid_username`, `incorrect_password`, `invalid_email`) SHALL normalizarse al mismo `WP_Error` genérico (`pocketid_required`), de modo que no se pueda distinguir si el usuario existe ni si la contraseña adivinada es correcta. El bloqueo SHALL devolver un `WP_Error` genérico que NO nombre al proveedor de identidad y SHALL NOT afectar al restablecimiento de contraseña (`lostpassword`, `rp`, `resetpass`), al formulario de contenido protegido (`postpass`) ni a las acciones nativas, que se despachan fuera de `wp_signon()`/`wp_authenticate()`. Con la configuración incompleta o el modo exigir inactivo, el login nativo SHALL seguir operativo —incluidas la contraseña interactiva y XML-RPC— y la pantalla de login SHALL mostrar un botón "Iniciar sesión". Ningún texto de la interfaz pública de login/logout SHALL nombrar al proveedor; su nombre SHALL limitarse a la página de Ajustes.

#### Scenario: Plugin unconfigured

- **WHEN** falta la URL, el client ID o el secret
- **THEN** el formulario de contraseña funciona con normalidad y no hay redirección alguna

#### Scenario: Configured without enforcement

- **WHEN** la configuración es completa pero el toggle "Exigir PocketID" está inactivo
- **THEN** el login nativo funciona (incluida la contraseña interactiva y XML-RPC) y se muestra el botón "Iniciar sesión"

#### Scenario: Enforcement active on the login form

- **WHEN** el toggle está activo y se envía a `wp-login.php` un POST con `log` y `pwd`, aunque se omita `wp-submit` o se incluya un `action` (p. ej. `action=login`)
- **THEN** se devuelve un `WP_Error` genérico que informa de que el acceso por contraseña está deshabilitado e invita a usar el botón "Iniciar sesión", sin nombrar al proveedor

#### Scenario: Enforcement active on XML-RPC

- **WHEN** el toggle está activo y se envía a `xmlrpc.php` una llamada `wp.getUsersBlogs`/`metaWeblog.*` con usuario y contraseña interactiva de WordPress
- **THEN** la autenticación por contraseña se rechaza y la llamada no obtiene sesión

#### Scenario: Application passwords unaffected

- **WHEN** una aplicación se autentica por REST con un Application Password de WordPress y el modo exigir está activo
- **THEN** la autenticación se permite y la aplicación puede publicar/editar contenido con normalidad

#### Scenario: Application passwords unaffected on XML-RPC

- **WHEN** una aplicación se autentica por XML-RPC con un Application Password de WordPress y el modo exigir está activo
- **THEN** la autenticación se permite (WordPress admite Application Passwords en XML-RPC) aunque la contraseña real del usuario esté bloqueada

#### Scenario: Native action query does not bypass XML-RPC

- **WHEN** el modo exigir está activo y se envía a XML-RPC una autenticación con la contraseña real y una query `?action=<nativa>` (p. ej. `action=lostpassword`)
- **THEN** la contraseña real se rechaza y la query de acción nativa no abre ningún bypass

#### Scenario: Prior authenticator result is preserved

- **WHEN** otro autenticador (p. ej. 2FA) ya devolvió un `WP_Error` o un `WP_User` ajeno a la contraseña real antes del bloqueo
- **THEN** el bloqueo respeta ese resultado sin sobrescribirlo

#### Scenario: Password errors are indistinguishable

- **WHEN** el modo exigir está activo y se prueba un usuario inexistente, un usuario existente con contraseña incorrecta y un usuario existente con contraseña correcta
- **THEN** las tres respuestas son el mismo `pocketid_required` genérico, sin revelar si el usuario existe ni si la contraseña es válida

#### Scenario: Emergency recovery unaffected

- **WHEN** el toggle está activo y se solicita el restablecimiento de contraseña (`action=rp`/`lostpassword`) o el formulario de contenido protegido (`action=postpass`)
- **THEN** el flujo nativo correspondiente se ejecuta con normalidad y no se ve afectado por el bloqueo

### Requirement: Settings page with connectivity test

El sistema SHALL exponer la configuración de Pocket ID en la pestaña «PocketID» del hub de ajustes «Atareao» (`options-general.php?page=atareao-settings&tab=pocketid`), con los campos URL de Pocket ID, Client ID, Client Secret (patrón "dejar en blanco para conservar el actual"), el toggle "Exigir PocketID" y un botón "Probar conexión" que descarga el discovery, valida la respuesta y muestra los tres endpoints detectados. El guardado SHALL protegerse con nonce (`check_admin_referer`) y la URL SHALL validarse como `https://`. La pestaña SHALL mostrar el Redirect URI (`wp_login_url()`) que debe registrarse en Pocket ID. El `client_secret` SHALL almacenarse endurecido: si existe una constante o variable de entorno `ATAREAO_POCKETID_CLIENT_SECRET`, ésta SHALL tener precedencia y NO SHALL guardarse en `wp_options`; en su defecto el secreto SHALL guardarse en una opción con `autoload` desactivado y SHALL NOT devolverse nunca al formulario, ni registrarse en logs, ni incluirse en exportaciones. La descarga del discovery SHALL restringirse a `https` y al host configurado (sin SSRF hacia otros hosts).

#### Scenario: Connectivity test succeeds

- **WHEN** la URL configurada responde con un discovery válido
- **THEN** se muestran los endpoints de autorización, token y userinfo detectados

#### Scenario: Connectivity test fails

- **WHEN** la URL es inalcanzable o el discovery es inválido
- **THEN** se muestra un mensaje de error genérico y se registra el detalle en el log

#### Scenario: Secret field left blank

- **WHEN** se guardan los ajustes con el campo Client Secret vacío
- **THEN** se conserva el secret almacenado previamente

#### Scenario: Secret is not echoed back

- **WHEN** se renderiza la página de Ajustes con un secreto ya guardado
- **THEN** el secreto no se devuelve al formulario ni aparece en el HTML

#### Scenario: Constant takes precedence

- **WHEN** está definida `ATAREAO_POCKETID_CLIENT_SECRET`
- **THEN** el secreto se lee de esa constante y no se guarda en `wp_options`

### Requirement: Error handling without information leakage

Todos los fallos (red, HTTP, respuestas no parseables, claims ausentes) SHALL registrarse en el log del servidor con un prefijo identificable (`[atareao-pocketid]`) y SHALL presentarse al usuario como mensajes genéricos en español a través de `wp_die`, sin exponer detalles internos de red, HTTP o del proveedor.

#### Scenario: Outage of the identity provider
- **WHEN** Pocket ID está inalcanzable durante el callback
- **THEN** se registra el error detallado en el log y el usuario ve un mensaje genérico de error temporal

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

Tras un login OIDC exitoso, el sistema SHALL persistir el `id_token` recibido en la respuesta del token endpoint en un almacén seguro server-side ligado al usuario (eliminado al cerrar sesión), y SHALL NOT exponerlo nunca en cookies legibles por el navegador ni en la interfaz. El sistema SHALL persistir y usar el `id_token` **únicamente si ha sido validado** conforme al requisito «ID token validation and nonce binding»; un `id_token` no validado SHALL NOT almacenarse ni usarse como `id_token_hint`. Al cerrar sesión, el sistema SHALL eliminar el `id_token` almacenado. Si no existe un `id_token` validado para la sesión, el sistema SHALL realizar el logout del proveedor sin `id_token_hint` o, si no es posible, completar solo el logout local (fail-safe).

#### Scenario: Identity token stored after login

- **WHEN** el token endpoint devuelve un `id_token` válido en un login exitoso
- **THEN** el `id_token` se guarda server-side ligado a la sesión y no queda accesible desde el navegador

#### Scenario: Invalid identity token is not stored

- **WHEN** el token endpoint devuelve un `id_token` cuya validación falla (firma, `aud`, `iss`, `exp` o `nonce`)
- **THEN** el `id_token` no se almacena ni se usa como `id_token_hint` y el motivo queda registrado

#### Scenario: Identity token removed on logout

- **WHEN** el usuario cierra sesión
- **THEN** el `id_token` almacenado se elimina

#### Scenario: Logout without an identity token

- **WHEN** no existe un `id_token` validado para la sesión
- **THEN** el logout del proveedor se realiza sin `id_token_hint` o solo se completa el logout local, sin bloquear el cierre de sesión

### Requirement: Discovery cache carries a schema version

La configuración de descubrimiento OIDC cacheada SHALL incluir una versión de esquema (`CONFIG_SCHEMA`). Una entrada de caché que no declare la versión de esquema actual SHALL considerarse inválida y SHALL refrescarse desde el proveedor, de modo que los campos nuevos (en particular `end_session_endpoint`, opcional) se repueblen aunque la caché antigua contuviera los tres endpoints obligatorios. La versión de esquema SHALL formar parte de la validez de la caché; `end_session_endpoint` SHALL seguir siendo opcional y NO SHALL condicionar por sí solo la reutilización de una caché con la versión correcta.

#### Scenario: Legacy cache is ignored

- **GIVEN** una configuración cacheada por una versión anterior del plugin que no incluye la versión de esquema
- **WHEN** se llama a `getOIDCConfig()`
- **THEN** la caché se ignora y se descarga un discovery nuevo desde el proveedor

#### Scenario: Fresh cache is reused

- **GIVEN** un discovery recién descargado
- **WHEN** se almacena en caché
- **THEN** la entrada incluye la versión de esquema actual y se reutiliza mientras siga válida

### Requirement: Provider session termination survives a stale discovery cache

Al cerrar sesión en modo exigir, si la configuración de descubrimiento disponible carece de `end_session_endpoint` pero el proveedor lo publica, el sistema SHALL refrescar el discovery **una vez** (`getOIDCConfig(true)`) antes de recurrir al logout local, de modo que la sesión del proveedor se cierre de verdad. Si tras el refresco el proveedor sigue sin publicar `end_session_endpoint`, el logout local SHALL completarse sin error. El refresco SHALL ser puntual (una única descarga) y SHALL registrarse con el prefijo `[atareao-pocketid]`; nunca SHALL provocar un bucle de descargas.

#### Scenario: Stale cache without end_session_endpoint

- **GIVEN** una caché de descubrimiento sin `end_session_endpoint` y un proveedor que lo publica
- **WHEN** el usuario cierra sesión
- **THEN** el plugin refresca el discovery y redirige a `end_session_endpoint` con `id_token_hint` y `post_logout_redirect_uri`

#### Scenario: Provider without end_session_endpoint

- **GIVEN** un proveedor que no publica `end_session_endpoint` incluso tras refrescar el discovery
- **WHEN** el usuario cierra sesión
- **THEN** se completa el logout local sin error

### Requirement: Clean post-logout screen under enforcement

Con "Exigir PocketID" activo y la configuración completa, en `GET wp-login.php?loggedout=true` el sistema SHALL ocultar el formulario de contraseña (que es inerte porque el login por contraseña está bloqueado) y SHALL mostrar el aviso nativo de sesión cerrada junto con un botón/enlace "Iniciar sesión" que inicia el flujo OIDC (sin nombrar al proveedor). El aviso "Se requiere PocketID para acceder." SHALL eliminarse. El ocultado del formulario y el botón "Iniciar sesión" SHALL renderizarse únicamente en esa pantalla post-logout y NO SHALL alterar la pantalla de login normal; en el resto de páginas de `wp-login.php` en modo exigir (por ejemplo acciones nativas como `lostpassword`) NO SHALL añadirse aviso ni botón, dejando la página nativa tal cual.

#### Scenario: Enforced logged-out page

- **GIVEN** el modo exigir activo y la configuración completa
- **WHEN** se accede por GET a `wp-login.php?loggedout=true`
- **THEN** el formulario de contraseña no se muestra y sí aparece el botón "Iniciar sesión"

#### Scenario: Password submission still blocked

- **GIVEN** el modo exigir activo
- **WHEN** un POST envía `log` y `pwd`
- **THEN** se bloquea con `pocketid_required`

#### Scenario: Non-enforced page unchanged

- **GIVEN** el modo exigir inactivo
- **WHEN** se carga la pantalla de login
- **THEN** se sigue mostrando el botón "Iniciar sesión" y el formulario normal

### Requirement: Public login UI does not expose the provider name

La interfaz pública de login/logout SHALL NOT mostrar el nombre del proveedor de identidad en botones, avisos ni mensajes de error. El nombre del proveedor SHALL limitarse a la página de Ajustes (solo administradores). Esta restricción aplica a todas las pantallas públicas servidas por el plugin, incluida la pantalla post-logout en modo exigir.

#### Scenario: Login button label

- **GIVEN** la pantalla pública de login (con o sin enforce activo)
- **WHEN** se muestra el botón que inicia el flujo OIDC
- **THEN** el botón dice únicamente "Iniciar sesión" y ningún subtítulo ni aviso de la pantalla nombra al proveedor

#### Scenario: Enforced password block message

- **GIVEN** el modo exigir activo
- **WHEN** se envía un POST con `log` y `pwd` y se bloquea con `pocketid_required`
- **THEN** el mensaje del `WP_Error` no contiene el nombre del proveedor de identidad (ninguna mención a "Pocket ID"/"PocketID") e invita a usar el botón "Iniciar sesión"

#### Scenario: Settings page may name the provider

- **GIVEN** un administrador autenticado en la página de Ajustes del plugin
- **WHEN** se renderiza esa página
- **THEN** la página sí puede nombrar al proveedor de identidad

### Requirement: State cookie is scoped to the login host

La cookie de estado OIDC (`atareao_pocketid_oauth`) SHALL emitirse como **host-only**, es decir, **sin el atributo `Domain`** en la cabecera `Set-Cookie` (un `Domain` no vacío, aunque sea el host exacto, cubriría por RFC 6265 también sus subdominios). De este modo la cookie nunca se envía al subdominio del proveedor de identidad (`pocketid.<dominio>`). El resto de atributos de la cookie (`path`, `Secure`, `HttpOnly`, `SameSite=Lax`) SHALL conservarse.

#### Scenario: Host-only cookie
- **GIVEN** el plugin configurado y un flujo de autorización iniciado desde el host del sitio
- **WHEN** el plugin emite la cookie de estado OIDC
- **THEN** la cabecera `Set-Cookie` **no incluye el atributo `Domain`** y la cookie no se envía a `pocketid.<dominio>`

#### Scenario: Existing cookies unaffected
- **GIVEN** que existen cookies de estado de otros subdominios del dominio
- **WHEN** el plugin emite la cookie de estado OIDC del sitio
- **THEN** la nueva cookie es host-only y las cookies de otros subdominios no se ven alteradas

### Requirement: State lifetime tolerates realistic login durations

La cookie de estado OIDC y el state single-use del servidor (transient anti-replay) SHALL compartir un TTL único y suficiente para completar un prompt de passkey o un reintento (por ejemplo, 15 minutos). Ambos SHALL eliminarse al completar o fallar el flujo: la cookie en cada callback y el transient al consumirlo, con independencia del resultado.

#### Scenario: Slow passkey prompt
- **GIVEN** un login iniciado y un usuario que tarda hasta el TTL definido en completar el prompt de passkey
- **WHEN** el navegador vuelve al callback dentro del TTL
- **THEN** el callback valida el state correctamente y el flujo continúa

#### Scenario: Cleanup
- **GIVEN** un callback procesado, tanto con éxito como con error
- **WHEN** el plugin termina de procesarlo
- **THEN** la cookie de estado y el transient del state quedan eliminados

### Requirement: Callback failures are diagnosable

Ante un callback inválido, el plugin SHALL registrar en el log del servidor, con el prefijo `[atareao-pocketid]`, la causa concreta del fallo —cookie ausente, cookie ilegible, state ausente/expirado, replay o mismatch— sin exponer detalles al usuario, que SHALL ver la pantalla 403 genérica.

#### Scenario: Missing cookie
- **GIVEN** un callback OIDC sin la cookie de estado
- **WHEN** el plugin procesa el callback
- **THEN** se registra la causa "cookie ausente" y el usuario ve la pantalla 403 genérica

#### Scenario: Replay
- **GIVEN** un state ya consumido (replay) que vuelve a llegar al callback
- **WHEN** el plugin procesa el callback
- **THEN** se registra la causa "replay" y el usuario ve la pantalla 403 genérica

#### Scenario: Expired state
- **GIVEN** un state cuyo transient ha expirado
- **WHEN** el plugin procesa el callback
- **THEN** se registra la causa "expirado" y el usuario ve la pantalla 403 genérica

### Requirement: Configurable email verification policy

El sistema SHALL exponer un ajuste (`atareao_pocketid_require_verified_email`), por defecto activado, que determine si se exige `email_verified === true` en la respuesta de userinfo. El ajuste SHALL leerse **solo en servidor**. Con el ajuste activo, un `email_verified=false` SHALL rechazarse con la 403 genérica y SHALL registrarse el motivo. Con el ajuste inactivo, el login SHALL continuar y SHALL registrarse que el email no está verificado. El mensaje mostrado al usuario SHALL seguir siendo genérico y sin nombrar al proveedor de identidad.

#### Scenario: Default strict
- **GIVEN** el ajuste por defecto (activado)
- **WHEN** la respuesta de userinfo trae `email_verified=false`
- **THEN** se rechaza con la 403 genérica y se registra el motivo

#### Scenario: Lenient
- **GIVEN** el ajuste desactivado
- **WHEN** la respuesta de userinfo trae `email_verified=false`
- **THEN** el login continúa y se registra que el email no está verificado

#### Scenario: Claim absent
- **GIVEN** una respuesta de userinfo que no incluye el claim `email_verified`
- **WHEN** el plugin evalúa la verificación
- **THEN** no se bloquea el login (comportamiento actual preservado)

### Requirement: Identity binding by provider subject

Tras un login OIDC exitoso, la identidad SHALL ligarse al claim `sub` del proveedor (presente en `userinfo` o en el `id_token`), almacenado server-side y asociado al usuario de WordPress, y NO SHALL usar el email como único criterio de vinculación. El sistema SHALL autenticar únicamente a usuarios de WordPress que (a) ya tengan ligado ese `sub` a su cuenta o (b) presenten ese email en un primer acceso que complete y persista la asociación `sub`↔usuario. Si el `sub` recibido no coincide con el `sub` previamente ligado a la cuenta resuelta por email, el sistema SHALL denegar el acceso con la respuesta genérica 403, sin reasignar la cuenta ni permitir el takeover. La vinculación SHALL ser fail-safe: si el proveedor no devuelve `sub`, el sistema SHALL NOT autenticar usando solo el email. El rechazo de `email_verified` no verificado conforme a la política `atareao_pocketid_require_verified_email` SHALL mantenerse.

#### Scenario: First login links the subject

- **WHEN** un usuario se autentica por primera vez y su email corresponde a una cuenta WP existente
- **THEN** el sistema persiste la asociación `sub`↔usuario y establece la sesión

#### Scenario: Subsequent login matches the subject

- **WHEN** un usuario ya vinculado vuelve a autenticarse con el mismo `sub`
- **THEN** el sistema le autentica aunque su email haya cambiado en el proveedor

#### Scenario: Subject mismatch on an existing email account

- **WHEN** llega un `sub` distinto del previamente ligado a la cuenta resuelta por email
- **THEN** el sistema deniega el acceso con el 403 genérico y no reasigna la cuenta

#### Scenario: Missing subject

- **WHEN** el proveedor no devuelve el claim `sub`
- **THEN** el sistema no autentica usando solo el email y deniega con el 403 genérico

#### Scenario: Email verification still enforced

- **WHEN** el `sub` es correcto pero `email_verified` es falso con la política estricta activa
- **THEN** se deniega el acceso con el 403 genérico

### Requirement: ID token validation and nonce binding

El sistema SHALL incluir un parámetro `nonce` aleatorio (mínimo 32 caracteres) en la petición de autorización, almacenado server-side junto al `state` (cookie y transient single-use) y consumido al procesar el callback. Cuando el token endpoint devuelva un `id_token`, el sistema SHALL validarlo antes de confiar en él (por ejemplo como `id_token_hint` en el logout), comprobando como mínimo: la firma contra el JWKS publicado por el discovery (`jwks_uri`) con el algoritmo permitido, `aud` igual al `client_id`, `iss` igual al `issuer` del discovery, `exp` no expirado (con una tolerancia de reloj acotada) y el `nonce` coincidente con el enviado. Si la validación falla, el sistema SHALL registrar el motivo con el prefijo `[atareao-pocketid]` y SHALL NOT persistir ni usar el `id_token`; el login SHALL completarse o rechazarse conforme a la política, sin exponer detalles al usuario. Si el proveedor no publica `jwks_uri` o el `id_token` no puede validarse, el sistema SHALL documentar y razonar la decisión de basar la autenticación en el `userinfo` obtenido sobre TLS y SHALL NOT tratar el `id_token` como prueba de identidad.

#### Scenario: Nonce sent in the authorization request

- **WHEN** se inicia el flujo OIDC
- **THEN** la URL de autorización incluye un parámetro `nonce` aleatorio almacenado junto al `state`

#### Scenario: Valid identity token

- **WHEN** el token endpoint devuelve un `id_token` cuya firma, `aud`, `iss`, `exp` y `nonce` son correctos
- **THEN** el `id_token` se acepta y se usa conforme al ciclo de vida definido

#### Scenario: Invalid signature

- **WHEN** la firma del `id_token` no valida contra el JWKS del proveedor
- **THEN** el `id_token` se rechaza, no se persiste y se registra el motivo

#### Scenario: Expired token

- **WHEN** el `id_token` está expirado fuera de la tolerancia de reloj
- **THEN** el `id_token` se rechaza, no se persiste y se registra el motivo

#### Scenario: Nonce mismatch

- **WHEN** el `nonce` del `id_token` no coincide con el enviado en la petición de autorización
- **THEN** el `id_token` se rechaza, no se persiste y se registra el motivo

#### Scenario: Audience or issuer mismatch

- **WHEN** el `aud` no es el `client_id` o el `iss` no es el `issuer` del discovery
- **THEN** el `id_token` se rechaza, no se persiste y se registra el motivo

### Requirement: Application passwords remain functional under the passwordless policy

Con la opción "Exigir PocketID" activa y la configuración completa, el sistema SHALL preservar íntegramente los **Application Passwords de WordPress** y la autenticación que los usa, tanto en **REST como en XML-RPC** (WordPress admite Application Passwords en ambos canales). El sistema SHALL NOT interceptar, deshabilitar ni restringir `application_password_is_api_request` ni `wp_authenticate_application_password`; un Application Password válido SHALL seguir autenticando y permitir publicar, editar y borrar contenido con normalidad, exactamente igual que con la política inactiva. El bloqueo SHALL alcanzar únicamente a la **contraseña real del usuario** (formulario web y XML-RPC) y SHALL NOT alcanzar a los Application Passwords, que son un mecanismo de credencial distinto; además SHALL preservar un `WP_User` ya resuelto por otro autenticador y respetar un `WP_Error` ajeno a la contraseña (normalizando los errores propios de la contraseña a `pocketid_required`). El sistema SHALL documentar esta preservación para que la política passwordless no rompa los flujos editoriales desde clientes externos.

#### Scenario: Publish content over REST with an application password

- **WHEN** el modo exigir está activo y un cliente externo se autentica por REST con un Application Password válido
- **THEN** la petición se autentica y el cliente puede publicar o editar contenido con normalidad

#### Scenario: Publish content over XML-RPC with an application password

- **WHEN** el modo exigir está activo y un cliente externo se autentica por XML-RPC con un Application Password válido
- **THEN** la petición se autentica y el cliente puede publicar o editar contenido con normalidad

#### Scenario: Application password not intercepted by the block

- **WHEN** el modo exigir está activo y llega una petición REST o XML-RPC autenticada con Application Password
- **THEN** el bloqueo de la contraseña real no la intercepta, no la rechaza y no modifica su resultado

#### Scenario: Same behavior with the policy inactive

- **WHEN** se compara una publicación REST autenticada con Application Password con el modo exigir activo y con el modo exigir inactivo
- **THEN** el resultado de la operación es equivalente en ambos casos

#### Scenario: Interactive password still blocked

- **WHEN** el modo exigir está activo y se intenta autenticar con la contraseña real del usuario (formulario web o XML-RPC) en lugar de un Application Password
- **THEN** la autenticación por contraseña real se rechaza conforme a la política passwordless

#### Scenario: Preservation is documented

- **WHEN** una persona consulta el `README.md` del plugin
- **THEN** encuentra que los Application Passwords y la publicación por REST y XML-RPC siguen funcionando con la política passwordless activa
