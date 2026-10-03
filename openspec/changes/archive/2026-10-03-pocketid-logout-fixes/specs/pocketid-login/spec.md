# Spec Delta

## ADDED Requirements

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
