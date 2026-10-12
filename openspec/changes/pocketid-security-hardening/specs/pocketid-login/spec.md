# pocketid-login Delta

## MODIFIED Requirements

### Requirement: Authorization request with state and PKCE

Cuando un visitante anónimo accede a una acción permitida de `wp-login.php` con la configuración completa y el modo "Exigir PocketID" activo, el sistema SHALL redirigir al endpoint de autorización con `response_type=code`, `client_id`, `redirect_uri=wp_login_url()` (codificado exactamente una vez), `scope=openid profile email`, un parámetro `state` aleatorio (mínimo 32 caracteres) y un `code_challenge` S256 derivado de un `code_verifier` aleatorio. El `state` y el `code_verifier` SHALL almacenarse en una cookie HttpOnly+Secure+SameSite=Lax con TTL de 15 minutos, único y compartido con el transient anti-replay del servidor.

#### Scenario: Anonymous access to wp-login.php

- **WHEN** un visitante anónimo accede a wp-login.php (acción permitida) y el modo exigir está activo
- **THEN** recibe una redirección 302 al endpoint de autorización con todos los parámetros OIDC

#### Scenario: redirect_uri single-encoding

- **WHEN** se construye la URL de autorización
- **THEN** el parámetro `redirect_uri` está codificado exactamente una vez (un doble encoding provocaría un mismatch y denegaría el flujo)

#### Scenario: Shared state TTL

- **WHEN** se inicia el flujo OIDC
- **THEN** la cookie de estado y el transient anti-replay del servidor usan el mismo TTL de 15 minutos

### Requirement: Callback validation and session establishment

Al recibir un `code` en `wp-login.php`, el sistema SHALL: (1) validar el parámetro `state` recibido contra la cookie con `hash_equals`, denegando con HTTP 403 y limpiando la cookie si falta, difiere o ha expirado; (2) canjear el `code` mediante POST al token endpoint con `grant_type=authorization_code`, `client_id`, `client_secret`, `redirect_uri`, `code` y `code_verifier` (autenticación de cliente `client_secret_post`); (3) solicitar userinfo con el `access_token` (Bearer); (4) aceptar únicamente emails válidos cuyo claim `email_verified` valga estrictamente verdadero conforme al requisito «Configurable email verification policy»; (5) resolver al usuario de WordPress mediante la vinculación `sub`↔usuario conforme al requisito «Identity binding by provider subject», denegando a los usuarios no vinculados; (6) limpiar cookies de auth previas, establecer la sesión de WordPress y redirigir a `redirect_to` validado como URL local o, en su defecto, al panel. Ante **cualquier** denegación de identidad —email sin cuenta WP, cuenta no vinculada o `sub` en conflicto— el sistema SHALL responder SIEMPRE el mismo error genérico 403, **sin `back_link`**, idéntico para todos los casos y sin revelar si el email tiene cuenta WP ni qué comprobación concreta falló; el motivo se registrará únicamente en el servidor con el prefijo `[atareao-pocketid]`.

#### Scenario: Successful login

- **WHEN** el code y el state son válidos, el `id_token` se valida (si el proveedor lo devuelve) y la identidad (`sub`) corresponde a un usuario WP existente y vinculado
- **THEN** se inicia la sesión de WordPress y se redirige al `redirect_to` validado o al panel

#### Scenario: State mismatch or expiry

- **WHEN** el `state` recibido no coincide con la cookie o ha expirado
- **THEN** se responde con un error 403 y se elimina la cookie de state

#### Scenario: Invalid or expired authorization code

- **WHEN** el token endpoint rechaza el code o el `code_verifier` es incorrecto
- **THEN** se registra el error en el log del servidor y se muestra al usuario un mensaje genérico

#### Scenario: Unregistered or unverified user

- **WHEN** el email no pertenece a ningún usuario de WordPress o `email_verified` no es estrictamente verdadero
- **THEN** se deniega el acceso con HTTP 403 sin crear ningún usuario

#### Scenario: Indistinguishable denial

- **WHEN** se compara la respuesta para un email sin cuenta WP, para una cuenta no vinculada y para un `sub` en conflicto
- **THEN** las tres respuestas son idénticas (mismo 403 genérico, sin `back_link`) y no permiten deducir si el email tiene cuenta

#### Scenario: Session fixation prevention

- **WHEN** se establece una sesión tras un login OIDC exitoso
- **THEN** se borran las cookies de autenticación previas antes de emitir las nuevas

### Requirement: Identity token lifecycle for logout

Tras un login OIDC exitoso, el sistema SHALL persistir el `id_token` recibido en la respuesta del token endpoint en un almacén seguro server-side **ligado al token de sesión de WordPress** (`wp_get_session_token()`) y no al usuario, de modo que dos sesiones simultáneas del mismo usuario no compartan ni se pisen su `id_token`. Su TTL SHALL ser igual a la expiración real de la cookie de sesión emitida (14 días con `remember=true`), nunca inferior. El sistema SHALL NOT exponerlo nunca en cookies legibles por el navegador ni en la interfaz. El sistema SHALL persistir y usar el `id_token` **únicamente si ha sido validado** conforme al requisito «ID token validation and nonce binding»; un `id_token` no validado SHALL NOT almacenarse ni usarse como `id_token_hint`. Al cerrar sesión, el sistema SHALL eliminar el `id_token` de esa sesión. Si no existe un `id_token` validado para la sesión, el sistema SHALL realizar el logout del proveedor sin `id_token_hint` o, si no es posible, completar solo el logout local (fail-safe).

#### Scenario: Identity token stored after login

- **WHEN** el token endpoint devuelve un `id_token` válido en un login exitoso
- **THEN** el `id_token` se guarda server-side ligado al token de sesión y no queda accesible desde el navegador

#### Scenario: Identity token outlives the session cookie

- **WHEN** el usuario cierra sesión pasados más de 2 días desde el login
- **THEN** el `id_token` sigue disponible y el logout redirige a `end_session_endpoint` con `id_token_hint`

#### Scenario: Concurrent sessions keep their own identity token

- **WHEN** el mismo usuario tiene dos sesiones activas y cierra sesión en una de ellas
- **THEN** la otra sesión conserva su propio `id_token` y su logout RP-initiated sigue funcionando

#### Scenario: Invalid identity token is not stored

- **WHEN** el token endpoint devuelve un `id_token` cuya validación falla (firma, `aud`, `iss`, `exp`, `nonce` o `kid` ausente)
- **THEN** el `id_token` no se almacena ni se usa como `id_token_hint` y el motivo queda registrado

#### Scenario: Identity token removed on logout

- **WHEN** el usuario cierra sesión
- **THEN** el `id_token` almacenado de esa sesión se elimina

#### Scenario: Logout without an identity token

- **WHEN** no existe un `id_token` validado para la sesión
- **THEN** el logout del proveedor se realiza sin `id_token_hint` o solo se completa el logout local, sin bloquear el cierre de sesión

### Requirement: Configurable email verification policy

El sistema SHALL exponer un ajuste (`atareao_pocketid_require_verified_email`), por defecto activado, que determine si se exige `email_verified === true` en la respuesta de userinfo. El ajuste SHALL leerse **solo en servidor**. La comprobación SHALL ser fail-closed: el email SHALL considerarse verificado **únicamente** si el claim vale estrictamente `true`, `1`, `'1'` o `'true'`; cualquier otro valor —incluidos `false`, `0`, `'false'`, `'0'`, `''`, `null`, `'False'` o un valor ausente— SHALL considerarse no verificado. Con el ajuste activo, un email no verificado SHALL rechazarse con la 403 genérica y SHALL registrarse el motivo. Con el ajuste inactivo, el login SHALL continuar y SHALL registrarse que el email no está verificado. El mensaje mostrado al usuario SHALL seguir siendo genérico y sin nombrar al proveedor de identidad.

#### Scenario: Default strict

- **WHEN** el ajuste por defecto (activado) y la respuesta de userinfo trae `email_verified=false`
- **THEN** se rechaza con la 403 genérica y se registra el motivo

#### Scenario: Non-canonical value is not verified

- **WHEN** el claim `email_verified` llega como `'False'`, `''`, `null` o `0.0`
- **THEN** el email se considera no verificado y, con la política activa, se rechaza con la 403 genérica

#### Scenario: Claim absent

- **WHEN** una respuesta de userinfo no incluye el claim `email_verified`
- **THEN** con la política activa el email se considera no verificado y se rechaza con la 403 genérica

#### Scenario: Lenient

- **WHEN** el ajuste desactivado y la respuesta de userinfo trae `email_verified=false`
- **THEN** el login continúa y se registra que el email no está verificado

### Requirement: Identity binding by provider subject

Tras un login OIDC exitoso, la identidad SHALL ligarse al claim `sub` del proveedor (presente en `userinfo` o en el `id_token`), almacenado server-side y asociado al usuario de WordPress, y NO SHALL usar el email como único criterio de vinculación. El sistema SHALL autenticar **únicamente** a usuarios de WordPress que ya tengan ligado ese `sub` a su cuenta. La auto-vinculación por email en el primer acceso SHALL estar **desactivada por defecto** y SHALL requerir un ajuste explícito del administrador (`atareao_pocketid_allow_email_linking`); cuando esté activa, el sistema SHALL vincular el `sub` a la cuenta cuyo email coincida **solo si** el email está verificado conforme al requisito «Configurable email verification policy». Sin ajuste activo y sin `sub` ligado, el sistema SHALL denegar el acceso con la respuesta genérica 403. Si el `sub` recibido no coincide con el `sub` previamente ligado a la cuenta resuelta por email, el sistema SHALL denegar el acceso con la respuesta genérica 403, sin reasignar la cuenta ni permitir el takeover. La vinculación SHALL ser fail-safe: si el proveedor no devuelve `sub`, el sistema SHALL NOT autenticar usando solo el email. El sistema SHALL ofrecer una herramienta WP-CLI de pre-provisioning (`wp atareao-pocketid bind-sub`) para asociar `sub`↔usuario sin depender de la auto-vinculación.

#### Scenario: Pre-provisioned subject logs in

- **WHEN** un usuario cuya cuenta ya tiene el `sub` ligado se autentica
- **THEN** el sistema le autentica y establece la sesión

#### Scenario: First login links the subject

- **GIVEN** la auto-vinculación por email está activa y el email está verificado
- **WHEN** un usuario se autentica por primera vez y su email corresponde a una cuenta WP existente
- **THEN** el sistema persiste la asociación `sub`↔usuario y establece la sesión

#### Scenario: Email verification still enforced

- **WHEN** el `sub` es correcto pero `email_verified` es falso con la política estricta activa
- **THEN** se deniega el acceso con el 403 genérico

#### Scenario: First login with auto-linking disabled

- **WHEN** un usuario se autentica por primera vez, su email corresponde a una cuenta WP existente y la auto-vinculación está desactivada
- **THEN** el sistema deniega el acceso con el 403 genérico y no modifica ninguna meta

#### Scenario: First login with auto-linking enabled

- **WHEN** la auto-vinculación está activa, el email está verificado y corresponde a una cuenta WP existente sin `sub` ligado
- **THEN** el sistema persiste la asociación `sub`↔usuario y establece la sesión

#### Scenario: Auto-linking enabled but email unverified

- **WHEN** la auto-vinculación está activa pero el email no está verificado conforme a la política
- **THEN** el sistema deniega el acceso con el 403 genérico y no vincula la cuenta

#### Scenario: Subsequent login matches the subject

- **WHEN** un usuario ya vinculado vuelve a autenticarse con el mismo `sub`
- **THEN** el sistema le autentica aunque su email haya cambiado en el proveedor

#### Scenario: Subject mismatch on an existing email account

- **WHEN** llega un `sub` distinto del previamente ligado a la cuenta resuelta por email
- **THEN** el sistema deniega el acceso con el 403 genérico y no reasigna la cuenta

#### Scenario: Missing subject

- **WHEN** el proveedor no devuelve el claim `sub`
- **THEN** el sistema no autentica usando solo el email y deniega con el 403 genérico

#### Scenario: No linked accounts warning

- **WHEN** «Exigir PocketID» está activo y ninguna cuenta tiene `sub` ligado
- **THEN** la pestaña de ajustes muestra un aviso y `wp atareao-pocketid bind-sub` permite vincular una cuenta

### Requirement: ID token validation and nonce binding

El sistema SHALL incluir un parámetro `nonce` aleatorio (mínimo 32 caracteres) en la petición de autorización, almacenado server-side junto al `state` (cookie y transient single-use) y consumido al procesar el callback. Cuando el token endpoint devuelva un `id_token`, el sistema SHALL validarlo antes de confiar en él (por ejemplo como `id_token_hint` en el logout), comprobando como mínimo: la firma contra el JWKS publicado por el discovery (`jwks_uri`) con el algoritmo permitido, `aud` igual al `client_id`, `iss` igual al `issuer` del discovery, `exp` no expirado (con una tolerancia de reloj acotada) y el `nonce` coincidente con el enviado. El sistema SHALL exigir la presencia del header `kid` en el `id_token` y seleccionar la JWK por ese `kid`; un `id_token` sin `kid` o con un `kid` desconocido SHALL rechazarse. Cuando el `id_token` se valide y la respuesta de userinfo incluya `sub`, el sistema SHALL comprobar que `userinfo.sub` coincide con `id_token.sub` y SHALL denegar el acceso con la 403 genérica si difieren. Si la validación falla, el sistema SHALL registrar el motivo con el prefijo `[atareao-pocketid]` y SHALL NOT persistir ni usar el `id_token`; el login SHALL completarse o rechazarse conforme a la política, sin exponer detalles al usuario. Si el proveedor no publica `jwks_uri` o el `id_token` no puede validarse, el sistema SHALL documentar y razonar la decisión de basar la autenticación en el `userinfo` obtenido sobre TLS y SHALL NOT tratar el `id_token` como prueba de identidad.

#### Scenario: Nonce sent in the authorization request

- **WHEN** se inicia el flujo OIDC
- **THEN** la URL de autorización incluye un parámetro `nonce` aleatorio almacenado junto al `state`

#### Scenario: Valid identity token

- **WHEN** el token endpoint devuelve un `id_token` cuya firma, `aud`, `iss`, `exp`, `kid` y `nonce` son correctos
- **THEN** el `id_token` se acepta y se usa conforme al ciclo de vida definido

#### Scenario: Invalid signature

- **WHEN** la firma del `id_token` no valida contra el JWKS del proveedor
- **THEN** el `id_token` se rechaza, no se persiste y se registra el motivo

#### Scenario: Missing or unknown kid

- **WHEN** el `id_token` no incluye `kid` o su `kid` no corresponde a ninguna JWK publicada
- **THEN** el `id_token` se rechaza, no se persiste y se registra el motivo

#### Scenario: Subject inconsistency between userinfo and identity token

- **WHEN** el `id_token` se valida y `userinfo.sub` no coincide con `id_token.sub`
- **THEN** el acceso se deniega con el 403 genérico y se registra el motivo

#### Scenario: Expired token

- **WHEN** el `id_token` está expirado fuera de la tolerancia de reloj
- **THEN** el `id_token` se rechaza, no se persiste y se registra el motivo

#### Scenario: Nonce mismatch

- **WHEN** el `nonce` del `id_token` no coincide con el enviado en la petición de autorización
- **THEN** el `id_token` se rechaza, no se persiste y se registra el motivo

#### Scenario: Audience or issuer mismatch

- **WHEN** el `aud` no es el `client_id` o el `iss` no es el `issuer` del discovery
- **THEN** el `id_token` se rechaza, no se persiste y se registra el motivo

## ADDED Requirements

### Requirement: Outbound OIDC fetches do not follow redirects

Las descargas de red que hace el plugin contra el proveedor —el documento de descubrimiento OIDC, el JWKS, el token endpoint (canje del `code`) y la petición de userinfo— SHALL realizarse sin seguir redirecciones HTTP (`redirection => 0`), de modo que el host final consultado sea siempre el host configurado y no se pueda alcanzar un destino interno mediante un `3xx` del proveedor. Cuando una respuesta sea una redirección, el sistema SHALL registrarla con el prefijo `[atareao-pocketid]` y tratarla como fallo, aplicando el comportamiento de error correspondiente (endpoints por defecto en el discovery; no confiar en el `id_token` en el JWKS; fallo temporal en el token endpoint y en userinfo). El endurecimiento SHALL impedir además que WpOrg\Requests reenvíe los headers de la petición —incluido `Authorization: Bearer`— al destino de la redirección, de modo que ni el `client_secret` ni el `code` (token endpoint) ni el `access_token` (userinfo) lleguen a un host no configurado.

#### Scenario: Discovery redirect is not followed

- **WHEN** el host configurado responde al discovery con un `3xx` hacia otro host
- **THEN** el sistema no sigue la redirección, registra el fallo y aplica las rutas por defecto derivadas de la base

#### Scenario: JWKS redirect is not followed

- **WHEN** el `jwks_uri` responde con un `3xx` hacia otro host
- **THEN** el sistema no sigue la redirección, registra el fallo y no trata el `id_token` como prueba de identidad

#### Scenario: Token endpoint redirect is not followed

- **WHEN** el `token_endpoint` responde con un `3xx` durante el canje del `code`
- **THEN** el sistema no sigue la redirección, registra el fallo con `[atareao-pocketid]` y no envía el `client_secret` ni el `code` al destino de la redirección

#### Scenario: Userinfo redirect is not followed

- **WHEN** el `userinfo_endpoint` responde con un `3xx`
- **THEN** el sistema no sigue la redirección, registra el fallo con `[atareao-pocketid]` y no reenvía el `access_token` al destino de la redirección

### Requirement: Discovery failures are negatively cached

Cuando la descarga del descubrimiento OIDC falla, el sistema SHALL registrar el fallo en una caché negativa de corta duración (del orden de 60 segundos) de modo que las peticiones anónimas subsiguientes a `wp-login.php` no repitan las descargas de red mientras el proveedor siga inalcanzable. La caché negativa SHALL expirar por sí sola y SHALL NOT impedir la recuperación una vez el proveedor vuelve a responder. El sistema SHALL seguir registrando el fallo con el prefijo `[atareao-pocketid]` sin exponer detalles al usuario.

#### Scenario: Repeated anonymous requests with the provider down

- **WHEN** el proveedor está inalcanzable y llegan varias peticiones anónimas seguidas a `wp-login.php`
- **THEN** solo la primera intenta las descargas de red y las siguientes usan la caché negativa, sin bloquear un worker de PHP-FPM

#### Scenario: Recovery after the provider returns

- **WHEN** la caché negativa ha expirado y el proveedor vuelve a responder
- **THEN** el sistema descarga el discovery de nuevo y reanuda el flujo normal

### Requirement: Session cookie secure flag follows the site scheme

El sistema SHALL emitir la cookie de sesión de WordPress con el flag `Secure` determinado por el esquema del sitio (`home_url()`), y NO SHALL depender de `is_ssl()` para esa decisión, de modo que el flag sea correcto aunque `$_SERVER['HTTPS']` no llegue definido a PHP-FPM (por ejemplo, tras un proxy inverso que termina el TLS sin propagar el esquema). En un sitio servido por `https`, la cookie de sesión SHALL emitirse siempre con `Secure` y con el prefijo `wordpress_sec_`.

#### Scenario: HTTPS site behind a TLS-terminating proxy

- **WHEN** el sitio está configurado con esquema `https` y la petición llega a PHP-FPM sin `$_SERVER['HTTPS']` definido
- **THEN** la cookie de sesión se emite con el flag `Secure` y con el prefijo `wordpress_sec_`
