# Design: Endurecimiento de seguridad del login OIDC con Pocket ID

## Context

`pocketid-login` integra Pocket ID como proveedor de identidad OIDC del sitio. El flujo vive en `wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php` (clase `\Atareao\PocketIDLogin`), que también contiene íntegramente la pestaña de ajustes «PocketID» (render y guardado); `includes/class-settings.php` es solo el hub que delega.

Producción (host `atareao`): Docker + Traefik (`traefik:v3.6.10`) → nginx (`nginx:alpine`) → WordPress (`wordpress:fpm-alpine`), con Pocket ID en `pocketid.atareao.es`. Traefik termina el TLS, fija `X-Forwarded-Proto: https` y nginx lo propaga a FastCGI como `HTTP_X_FORWARDED_PROTO` (nginx pasa los headers de la petición como parámetros `HTTP_*`). `wp-config.php` incluye el snippet estándar de reverse proxy que, a partir de ese header, define `$_SERVER['HTTPS'] = 'on'`.

No hay framework de tests ni build tools en el repo: la verificación es análisis estático (`just php-lint`, `just phpcs`) más E2E manual.

## Goals / Non-Goals

- **Goals**: cerrar los 2 MEDIUM y los 4 LOW de la auditoría del 2026-10-12, más los endurecimientos H-2 y H-4; alinear la spec con el código.
- **Non-Goals**: rediseñar el flujo OIDC, cambiar de proveedor, introducir dependencias nuevas, añadir un framework de tests, ni tocar el despliegue de Pocket ID (fuera del repo).

## Decisions

### D1 — TTL del `id_token` ligado a la sesión, no al usuario

Hoy el transient se keyea por `user_id`, así que dos sesiones del mismo usuario comparten y se anulan mutuamente el `id_token_hint`: entrar en un dispositivo sobrescribe el del otro y salir en uno lo borra para el otro. Se decide keyearlo por el token de sesión (`wp_get_session_token()`) y dimensionar su TTL con la misma expiración que la cookie de sesión.

- **Alternativa descartada**: mantener el keying por usuario y solo subir el TTL. Arregla el síntoma del logout tardío, pero deja el comportamiento errático en multisesión.

### D2 — Auto-vinculación por email desactivada por defecto

Es el cambio con más impacto operativo. La spec vigente permite ligar el `sub` a la primera identidad que presente un email de una cuenta WP sin `sub` ligado. Se decide invertir el default:

- **Por defecto**: sin `sub` ligado, la autenticación se deniega con la 403 genérica. El administrador pre-provisiona con WP-CLI.
- **Opt-in**: un ajuste (`atareao_pocketid_allow_email_linking`) reactiva la auto-vinculación, y solo entonces se exige además `email_verified` estrictamente verdadero.
- **Fail-safe**: aviso en la pestaña de ajustes cuando «Exigir PocketID» está activo y no hay ninguna cuenta vinculada, y bloqueo del propio guardado: si no hay ninguna cuenta con `sub` ligado, marcar «Exigir PocketID» no lo activa (se conserva el valor anterior) y se avisa al administrador. La recuperación real, si el sitio quedara sin acceso, es `wp atareao-pocketid bind-sub` por WP-CLI (o acceso directo a la BD); `lostpassword` solo restablece la contraseña y el login por contraseña sigue bloqueado con el modo exigir activo.
- **Alternativa descartada**: dejar la auto-vinculación activa y limitarse a exigir `email_verified` estricto. Reduce la ventana pero no la cierra: la propiedad del email depende enteramente del proveedor, y con `EMAILS_VERIFIED=true` (recomendado en el README del plugin) todo email cuenta como verificado.

### D3 — `email_verified` fail-closed

Se sustituye la lista de valores «no verificados» por una allowlist estricta. Con Pocket ID (booleano JSON) el comportamiento observable no cambia; el endurecimiento protege frente a proveedores o respuestas con formas no canónicas.

- **Riesgo**: si el proveedor dejara de enviar el claim, con la política activa se rechazaría el login. Se acepta porque Pocket ID siempre lo envía y porque el ajuste permite desactivar la política.

### D4 — Descargas sin redirecciones

`wp_remote_get`/`wp_remote_post` siguen hasta 5 saltos por defecto. Se fija `redirection => 0` en las **cuatro** peticiones contra el proveedor: discovery, JWKS, token endpoint (canje del `code`) y userinfo. El motivo es que WpOrg\Requests reenvía los headers de la petición —incluido `Authorization: Bearer`— en una redirección, de modo que un `3xx` del proveedor podría filtrar el `client_secret` y el `code` (token endpoint) o el `access_token` (userinfo) hacia un host no configurado. La alternativa (`reject_unsafe_urls`) es más agresiva y puede romper despliegues legítimos con redirecciones internas, por lo que se descarta.

### D5 — Flag `Secure` derivado del esquema del sitio

`is_ssl()` solo mira `$_SERVER['HTTPS']` y `SERVER_PORT`; en producción funciona por el snippet de `wp-config.php`, que vive **fuera** del repo. Se decide derivar el flag del esquema de `home_url()` para que el plugin sea correcto por sí mismo. Nótese la incoherencia actual: la cookie de estado OIDC del propio plugin ya deriva `Secure` del esquema de `wp_login_url()`.

### D6 — Caché negativa del discovery

Ante fallo de discovery se cachea el error ~60 s en lugar de reintentar en cada petición anónima. Se descarta alargar el TTL de la caché positiva (12 h) porque retrasaría la recuperación tras un cambio legítimo de configuración del proveedor.

## Risks / Trade-offs

- **Bloqueo de acceso (D2)**: el riesgo principal. Mitigado con la herramienta WP-CLI, el aviso en ajustes y el bloqueo del guardado cuando no hay ninguna cuenta vinculada. Requiere inventariar las cuentas antes de desplegar (tarea 1.2). La recuperación real requiere `wp atareao-pocketid bind-sub` por WP-CLI (o acceso a la BD); `lostpassword` no es una vía de entrada con el modo exigir activo.
- **Rechazo por claim ausente (D3)**: aceptado y reversible por ajuste.
- **Regresión en el logout (D1)**: el cambio de keying toca el ciclo de vida del `id_token`; exige E2E específico con sesión de más de 2 días (tarea 10.3).
- **N-002 — transient huérfano del `id_token` (riesgo aceptado)**: si en el logout no hay cookie `wordpress_logged_in_*`, `handleWpLogout()` retorna pronto y no borra el transient de esa sesión; cada login crea uno nuevo con TTL de 14 días. Se acepta porque no hay vector remoto práctico (exigiría operar sin sesión válida) y los huérfanos expiran por su propio TTL.
- **N-005 — TTL del transient vs. expiración del navegador (riesgo aceptado)**: el TTL del transient (14 d) es 12 h inferior a la expiración de la cookie del navegador (`$expiration + 12 h` de gracia de core). Se acepta porque la ventana solo degrada el logout RP-initiated a logout local, sin impacto de seguridad.
- **N-006 — PII en `error_log` (riesgo aceptado)**: los mensajes de `self::log()` incluyen el email del usuario. Es comportamiento preexistente a este change; queda pendiente de anonimizar en un cambio aparte.
- **Logout RP-initiated parcial sin la Logout Callback URL (prerrequisito de despliegue)**: si el cliente OIDC de Pocket ID no tiene registrada la *Logout Callback URL* (`https://atareao.es/wp-login.php?loggedout=true`, comparada de forma exacta incluida la query), el `GET /api/oidc/end-session` responde `302` y el proveedor registra `WRN Error getting logout callback URL, the user has to confirm the logout manually`. El resultado es un logout a medias: la sesión SSO del proveedor **sí se cierra** (el plugin envía `id_token_hint` y `post_logout_redirect_uri`, y Pocket ID los acepta), pero el usuario **no vuelve al sitio** —queda en la pantalla de login de Pocket ID (`https://pocketid.<dominio>/login?redirect=%2F`) tras una confirmación manual— en vez de en `wp-login.php?loggedout=true`. Es un fallo de configuración del cliente en el proveedor, no del plugin. **Mitigación:** registrar la Logout Callback URL en los ajustes del cliente (ver `Migration Plan`, paso 2, y tarea 11.4). Verificado con Pocket ID 2.14.0 el 2026-10-12; referencia: <https://github.com/pocket-id/pocket-id/issues/1236>. Nota de versión: Pocket ID < 2.4.0 arrastra el CVE-2026-28512 (bypass de validación de `redirect_uri`); la 2.14.0 no está afectada.

## Migration Plan

1. Inventariar cuentas y `sub` ligados (tarea 1.2).
2. Registrar en el cliente OIDC de Pocket ID la **Logout Callback URL** (`https://atareao.es/wp-login.php?loggedout=true`), además de la Callback URL (`https://atareao.es/wp-login.php`). Pocket ID compara la URL de forma exacta, query incluida; sin ella el logout RP-initiated falla a medias (ver `Risks / Trade-offs` y tarea 11.4).
3. Desplegar el código con el ajuste de auto-vinculación desactivado.
4. Pre-provisionar los `sub` con `wp atareao-pocketid bind-sub` antes de dar por bueno el despliegue.
5. Verificar el aviso de «ninguna cuenta vinculada» desaparece.
6. Rollback: reactivar el toggle de auto-vinculación restaura el comportamiento anterior sin revertir código.

## Open Questions

- ¿Debe el ajuste de auto-vinculación desaparecer por completo en una versión futura (una vez todas las cuentas estén vinculadas)?
