# Design: Pocket ID OIDC Login

## Endpoints reales de Pocket ID (verificados en source v2.x, `well_known_controller.go`)

| Endpoint | Ruta | Uso |
|---|---|---|
| Authorization (navegador) | `${PUBLIC_APP_URL}/authorize` | Redirigir el user agent (UI de passkey + consentimiento) |
| Token | `${base}/api/oidc/token` | POST server-to-server, `client_secret_post` |
| Userinfo | `${base}/api/oidc/userinfo` | GET con `Authorization: Bearer <access_token>` |
| Discovery | `/.well-known/openid-configuration` | Documento con todos los endpoints |
| JWKS | `/.well-known/jwks.json` | (no usado: validamos vía userinfo) |

- `response_types_supported: ["code"]`, `code_challenge_methods_supported: ["plain","S256"]`, `scopes_supported: ["openid","profile","email","groups","offline_access"]`, claims incluyen `email`, `email_verified`, `preferred_username`.
- **El borrador apuntaba a `/api/oidc/authorize`** (API interna del backend, no renderiza UI): bug crítico corregido usando el `authorization_endpoint` del discovery.

## Estructura del código (convenciones del plugin existente)

- Archivo: `wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php`
- Clase `namespace Atareao; class PocketIDLogin` con métodos estáticos + `public static function init()` registrando hooks, tal y como hacen `MatrixConfig`, `ContactForm`, etc.
- Registro en `atareao-functionality.php`: `require_once` + `\Atareao\PocketIDLogin::init();` dentro de `atareao_functionality_init()`.
- Página de ajustes estilo `MatrixConfig` (form manual + `check_admin_referer`), NO Settings API.
- PHP ≥ 7.4 (sin `?->`, `match`, named args, `str_contains`).

## Hooks

| Hook | Método | Propósito |
|---|---|---|
| `login_init` | `handleLoginFlow()` | Redirección a PocketID + recepción del callback |
| `authenticate` (prio 30) | `blockPasswordLogin()` | Bloquear form de contraseña solo si exigir activo |
| `login_form` | `renderLoginButton()` | Botón PocketID (modo no exigir) + aviso (modo exigir) |
| `admin_menu` | `addSettingsPage()` | Página de ajustes |

## Flujo

1. **Inicio** (`wp-login.php`, acción permitida, config completa, exigir activo): generar `state` (32 chars) + `code_verifier` (43+ chars aleatorios) → `code_challenge = base64url(sha256(code_verifier))` → cookie `atareao_pocketid_oauth` con `{state, code_verifier, redirect_to}` (HttpOnly+Secure+SameSite=Lax, TTL 300 s) → 302 al `authorization_endpoint` con `response_type=code&client_id&redirect_uri=wp_login_url()&scope=openid+profile+email&state&code_challenge=S256&code_challenge_method=S256`.
   - `redirect_uri` se pasa sin codificar previamente: `add_query_arg` ya hace el encoding (el borrador lo codificaba dos veces).
2. **Callback** (`?code`): validar `state` con `hash_equals` y TTL (cookie expira sola); borrar cookie ya. POST al token endpoint (body form: grant_type=authorization_code, client_id, client_secret, redirect_uri, code, code_verifier; timeout 15 s). Si falta `access_token` → error genérico + log.
3. **Userinfo**: GET Bearer; extraer `email` (`sanitize_email` + `is_email`); si `email_verified` existe y es falso → 403. `get_user_by('email', ...)`; inexistente → 403. `wp_clear_auth_cookie()` → `wp_set_current_user` → `wp_set_auth_cookie($id, true)` → `do_action('wp_login', ...)` → `wp_safe_redirect(wp_validate_redirect($redirect_to, admin_url()))`.
4. **Allowlist nativa**: si `$_GET['action']` ∈ {`logout`,`postpass`,`lostpassword`,`checkemail`,`confirmaction`,`rp`,`resetpass`,`register`,`login`(?)} → return (flujo nativo). `register` se puede permitir o redirigir: se permite (WP puede tener registro abierto; el login post-registro igual pasa por PocketID).
5. **Bloqueo de contraseña**: en el filtro `authenticate` (prio 30), solo si `isset($_POST['wp-submit']) && isset($_POST['log']) && isset($_POST['pwd']) && empty($_POST['action'])` y `self::isConfigured() && enforce` → `WP_Error('pocketid_required', ...)`. Nunca interfiere con app passwords, XML-RPC o REST.

## Seguridad

- PKCE S256 siempre (Pocket ID lo soporta; si el client no tiene PKCE habilitado, el servidor lo acepta y lo detecta para activarlo).
- Anti-CSRF vía `state` + `hash_equals`; cookie `samesite=Lax`.
- `redirect_to` validado con `wp_validate_redirect` (bloquea open redirects).
- `wp_clear_auth_cookie()` antes de emitir sesión (anti session fixation).
- Cero exposición de secret: secret nunca se re-printa en el form (placeholder "dejar en blanco para conservar").
- Logs con prefijo `[atareao-pocketid]`; mensajes wp_die genéricos en español.
- No se crean usuarios automáticamente (decisión explícita: solo admins existentes; comentado en el código la vía alternativa).
- Recuperación de emergencia: `action=rp`/`lostpassword` siempre nativos + WP-CLI.

## Configuración previa en Pocket ID

- Crear OIDC Client con redirect URI = `wp_login_url()` del sitio (p. ej. `https://atareao.es/wp-login.php`; soporta wildcards).
- Anotar Client ID (UUID) y Client Secret.
- (Opcional) habilitar PKCE en el client; scopes: `openid profile email`.

## Verificación

- `just php-lint` / `php -l` sobre el archivo nuevo y el modificado.
- `just phpcs` (PSR12).
- Smoke test de discovery con `curl` a la URL configurada (solo si es accesible desde el entorno).
- Prueba manual en producción: botón "Probar conexión" → login completo con passkey en wp-login.php.
