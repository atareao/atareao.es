# Proposal

## Why

El sitio atareao.es quiere autenticación exclusiva con PocketID (passwordless con passkeys/WebAuthn) para el acceso a wp-admin, eliminando el vector de ataque del login por contraseña. El borrador previo ("PocketID OIDC Login") contiene bugs críticos verificados contra el código fuente de Pocket ID (endpoint de autorización incorrecto, bloqueo de contraseña incondicional, ausencia de PKCE, doble url-encoding del redirect_uri) y no sigue las convenciones del plugin `atareao-functionality` (namespace `Atareao`, clases estáticas, página de ajustes con nonce propio).

## What Changes

- Nuevo módulo `\Atareao\PocketIDLogin` dentro del plugin existente `atareao-functionality` (`includes/class-pocketid-login.php` + registro en `atareao-functionality.php`).
- Flujo OIDC Authorization Code + PKCE (S256) con validación de `state` anti-CSRF en cookie HttpOnly+Secure+SameSite=Lax (TTL 5 min).
- Resolución de endpoints vía **OIDC Discovery** (`/.well-known/openid-configuration`) con caché transient de 12 h y fallback a rutas derivadas: `/authorize`, `/api/oidc/token`, `/api/oidc/userinfo`.
- Allowlist de acciones nativas de `wp-login.php` que NO se redirigen a PocketID: `logout`, `lostpassword`, `checkemail`, `confirmaction`, `rp`, `resetpass`, `postpass` — garantiza logout correcto, recuperación de emergencia por email y formularios de contenido protegido.
- Bloqueo del formulario de contraseña SOLO cuando la configuración está completa y el toggle "Exigir PocketID" está activo; se preservan application passwords, XML-RPC y autenticación REST. Con el toggle inactivo, se muestra un botón "Iniciar sesión con PocketID" en la pantalla de login.
- Página de ajustes (Ajustes → PocketID Login) con campos URL / Client ID / Client Secret (patrón "dejar en blanco para conservar"), toggle y botón "Probar conexión" que ejecuta el discovery y muestra los endpoints detectados.
- Logging server-side de errores (prefijo identificable) + mensajes genéricos al usuario (sin fuga de detalles internos).

## Capabilities

- **New Capabilities**:
  - `pocketid-login` → `specs/pocketid-login/spec.md`
- **Modified Capabilities**: ninguna.

## Impact

- **Archivos**: `atareao-functionality.php` (require_once + init), `includes/class-pocketid-login.php` (nuevo), `.justfile` (receta `check-spec` inexistente, se añade para cumplir la REGLA DE ORO).
- **Compatibilidad**: PHP ≥ 7.4 (cabecera del plugin) — el servidor corre 8.3; sin sintaxis PHP 8 exclusiva.
- **Dependencia externa**: Pocket ID con discovery OIDC (verificado en source v2.x: `authorization_endpoint=${PUBLIC_APP_URL}/authorize`, `token_endpoint=…/api/oidc/token` con `client_secret_post`, `userinfo_endpoint=…/api/oidc/userinfo`, scopes `openid profile email`, PKCE `S256` soportado, `response_types_supported=["code"]`). La URL y el redirect URI (`wp_login_url()`) deben estar registrados en un OIDC Client de Pocket ID.
- **Sin almacenamiento**: no persiste tokens; no crea usuarios; solo autentica usuarios existentes de WP por email (con check de `email_verified`).
- **Ruptura**: ninguna si el toggle está inactivo (transición segura); con toggle activo, el login por contraseña queda bloqueado pero siempre queda la recuperación por email (`action=rp`) y WP-CLI.
