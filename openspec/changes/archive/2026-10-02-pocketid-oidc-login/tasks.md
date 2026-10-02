# Tasks: Pocket ID OIDC Login

## 1. Scaffold del módulo

- [x] 1.1 Crear `includes/class-pocketid-login.php` con `namespace Atareao;` y clase estática `PocketIDLogin` (constantes de opciones, `ATAREAO_POCKETID_*`) siguiendo el patrón de `MatrixConfig`. Verificación: `just php-lint` y `php -l includes/class-pocketid-login.php` sin errores.
- [x] 1.2 Registrar `require_once` y `\Atareao\PocketIDLogin::init();` en `atareao-functionality.php` dentro de `atareao_functionality_init()`. Verificación: `php -l atareao-functionality.php` y `just phpcs` sin fallos; el sitio carga sin fatal errors.

## 2. Discovery OIDC

- [x] 2.1 Implementar `getOIDCConfig()` que descarga `{base}/.well-known/openid-configuration` con `wp_remote_get` (timeout 10 s, verificación TLS), lo valida (issuer/endpoints presentes) y lo cachea en transient `atareao_pocketid_oidc_config` por 12 h; borra el transient y reintenta si falla; devuelve `authorization_endpoint`, `token_endpoint`, `userinfo_endpoint` con fallback a rutas por defecto. Verificación: unitario manual vía `php-cli` con una URL real si es accesible; siempre `php -l` y revisión.
- [x] 2.2 Asegurar que el endpoint de autorización devuelto apunte a `${base}/authorize` (nunca `/api/oidc/authorize`). Verificación: revisión de código y prueba con el botón "Probar conexión".

## 3. Flujo de autorización

- [x] 3.1 Implementar `handleLoginFlow()` en `login_init`: allowlist de acciones nativas (`logout`, `postpass`, `lostpassword`, `checkemail`, `confirmaction`, `rp`, `resetpass`, `register`); si no está configurado o no se exige, no interfiere. Verificación: revisión + prueba manual de cada acción en wp-login.php.
- [x] 3.2 Generar `state` (32 caracteres) + `code_verifier` aleatorio (43+ caracteres) + `code_challenge` S256 (base64url sin padding), guardarlos en cookie `atareao_pocketid_oauth` (JSON `{state, code_verifier, redirect_to}`; HttpOnly + Secure + SameSite=Lax; TTL 300 s) y redirigir 302 al authorization endpoint con `response_type=code`, `client_id`, `redirect_uri=wp_login_url()` sin pre-codificar, `scope=openid profile email`, `state`, `code_challenge`, `code_challenge_method=S256`. Verificación: inspección de la URL generada (redirect_uri codificado una sola vez).
- [x] 3.3 Respetar `redirect_to` validado (`wp_validate_redirect`) al construir la cookie y el destino final. Verificación: revisión.

## 4. Callback y sesión

- [x] 4.1 En el callback (`$_GET['code']`): validar `state` con `hash_equals` contra la cookie y borrarla siempre; fallo → `wp_die` 403 genérico. Verificación: simular callback con state erróneo en un entorno de prueba (o revisión exhaustiva).
- [x] 4.2 Canjear el code por tokens: POST al token endpoint (body form con `grant_type=authorization_code`, `client_id`, `client_secret`, `redirect_uri`, `code`, `code_verifier`; timeout 15 s); errores → `error_log('[atareao-pocketid] …')` + mensaje genérico. Verificación: revisión + prueba real con Pocket ID.
- [x] 4.3 Obtener userinfo con Bearer; validar `email` (`sanitize_email`+`is_email`) y `email_verified` (si existe y es falso → 403); buscar usuario por email; inexistente → 403 "Acceso denegado". Verificación: casos revisados en código + prueba real.
- [x] 4.4 Establecer sesión: `wp_clear_auth_cookie()`, `wp_set_current_user`, `wp_set_auth_cookie($id, true)`, `do_action('wp_login', …)` y `wp_safe_redirect(wp_validate_redirect($redirect_to, admin_url()))`. Verificación: login real de extremo a extremo en producción.

## 5. Bloqueo de contraseña y botón de login

- [x] 5.1 Implementar filtro `authenticate` (prio 30) que bloquee SOLO el form HTML de wp-login.php (`wp-submit` + `log` + `pwd` + action vacía) y SOLO si config completa + toggle "Exigir PocketID"; devuelve `WP_Error('pocketid_required', …)`. Verificación: revisión; verificar que application passwords/XML-RPC/REST no se ven afectados.
- [x] 5.2 Implementar `login_form`: botón "Iniciar sesión con PocketID" (modo no exigir) y aviso informativo (modo exigir), enlazando al mismo flujo vía `wp_login_url()` con el state/PKCE generados. Verificación: render en wp-login.php.

## 6. Página de ajustes

- [x] 6.1 Crear página "PocketID Login" en Ajustes (`add_options_page`) con campos URL (validar https://), Client ID, Client Secret (patrón vacío = conservar), toggle "Exigir PocketID", Redirect URI informativo (readonly) y nonce (`check_admin_referer`). Verificación: guardado/consulta en wp-admin sin errores.
- [x] 6.2 Botón "Probar conexión" que ejecuta `getOIDCConfig()`, muestra los endpoints detectados o error genérico + log. Verificación: prueba con URL real de Pocket ID.
- [x] 6.3 Sanitización: `sanitize_text_field`/`esc_url_raw` en guardado y `sanitize_text_field` en lectura; nunca re-printar el secret en el form. Verificación: revisión de seguridad.

## 7. Calidad y seguridad

- [x] 7.1 Ejecutar `just php-lint`, `just phpcs` (PSR12) y `phpcbf` si procede sobre el plugin completo. Verificación: salida limpia.
- [x] 7.2 Auditoría de seguridad (auditor-backend) del flujo OIDC: CSRF, open redirect, session fixation, fuga de secret, exponer detalles internos, compatibilidad PHP 7.4. Verificación: informe sin hallazgos críticos.
- [x] 7.3 Añadir receta `check-spec` al `.justfile` que invoque `openspec list --changed`/validación, cumpliendo la REGLA DE ORO de AGENTS.md. Verificación: `just check-spec` funciona.

## 7.4 Correcciones de auditoría (SEC-BE-001..007)

- [x] 7.4.1 State con single-use y TTL server-side (transient sha256, borrado tras validar). Verificación: revisión + phpcs.
- [x] 7.4.2 Validación https + host de los endpoints del discovery y `sslverify` explícito en token/userinfo. Verificación: revisión + phpcs.
- [x] 7.4.3 `state`/`code` sin `sanitize_text_field` (comparación byte-exacta). Verificación: grep sin restos + phpcs.
- [x] 7.4.4 Cookie Secure según esquema real de `wp_login_url()`. Verificación: revisión.
- [x] 7.4.5 `check-spec` lista todos los changes activos (sin `head -1`). Verificación: `just check-spec` OK.
- [x] 7.4.6 Copy del toggle honesto (no promete bloquear XML-RPC/REST/app passwords). Verificación: revisión UI.

## 8. Integración y entrega

- [x] 8.1 Verificación integrada: `just php-lint` + `just phpcs` sobre todo el repo; smoke test de discovery; sin regresiones en otras classes (Matrix, ContactForm, etc.). Verificación: comandos en verde.
- [ ] 8.2 Commit convencional en `feature/pocketid-oidc-login` (✨ feat: pocketid oidc login) + push + PR a `development` por gitflow. Verificación: PR abierto con CI en verde.
