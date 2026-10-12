# Proposal: Endurecimiento de seguridad del login OIDC con Pocket ID

## Why

El 2026-10-12 se auditó el flujo OIDC de la capability `pocketid-login` (`wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php` y su pestaña de ajustes). El flujo es sólido en lo esencial —`state`/`nonce`/PKCE con CSPRNG y single-use, validación estricta del `id_token` (sin `alg=none`/HS\*), vinculación `sub`↔usuario fail-safe, 403 uniforme sin fuga de información, cookie de estado host-only, sin open redirect y sin fuga de secretos— pero la auditoría encontró **2 hallazgos MEDIUM, 4 LOW y 8 endurecimientos** de defensa en profundidad. No hay hallazgos críticos ni altos.

Los dos MEDIUM tienen impacto real y verificable en el código:

1. **El `id_token` caduca a los 2 días mientras la sesión dura 14.** `apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user->ID, true)` (línea 637) usa un default de 2 días y no hay ningún filtro `auth_cookie_expiration` registrado en el repo, mientras `wp_set_auth_cookie($user->ID, true, is_ssl())` (línea 652) emite una sesión de 14 días. A partir del día 2, el logout ya no encuentra el `id_token` y cae al fail-safe local: **la sesión SSO de Pocket ID no se cierra** y quien use después ese navegador vuelve a entrar sin passkey. El `design.md` del change archivado `2026-10-03-pocketid-logout` ya declaraba como objetivo «TTL alineado con la expiración de la sesión»: el código incumple su propio diseño.

2. **Vinculación por email en el primer acceso.** `resolveUserByIdentity()` liga el `sub` del proveedor a cualquier cuenta de WordPress cuyo email coincida y que todavía no tenga `atareao_pocketid_sub` (líneas 697-707). La única comprobación previa es `is_email()` y la política de `email_verified`, que hoy solo bloquea si el claim vale exactamente `false`/`0`/`'false'`/`'0'`. Es una decisión explícita de la spec vigente, pero deja una ventana de takeover para toda cuenta que aún no haya entrado nunca por OIDC.

La verificación en producción (host `atareao`, Docker + Traefik → nginx → `wordpress:fpm-alpine`, `pocket-id` en `pocketid.atareao.es`) confirmó que el flujo OIDC está vivo y correcto (302 a `/authorize` con `state`, `code_challenge` S256 y `nonce`), y permitió **descartar** el hallazgo LOW de la cookie de sesión: `wp-config.php` incluye el snippet estándar de reverse proxy (`$_SERVER['HTTPS'] = 'on'` cuando `HTTP_X_FORWARDED_PROTO` contiene `https`), Traefik fija ese header y nginx lo propaga a FastCGI como `HTTP_X_FORWARDED_PROTO`, por lo que `is_ssl()` devuelve `true` y la cookie sí se emite con `Secure`. Queda como robustez (no depender de configuración externa al repo), no como vulnerabilidad.

## What Changes

- **SEC-BE-001 (MEDIUM)** — Alinear el TTL del `id_token` con la expiración real de la sesión (14 días con `remember=true`) y **ligarlo al token de sesión de WordPress** (`wp_get_session_token()`) en vez de al usuario, de modo que dos sesiones simultáneas del mismo usuario no se pisen el `id_token_hint`.
- **SEC-BE-002 (MEDIUM)** — Endurecer la vinculación en el primer acceso: **desactivada por defecto** y activable solo por un ajuste explícito del administrador; cuando esté activa, exigir `email_verified` estrictamente verdadero. Sin `sub` previamente ligado y sin el ajuste activo, denegar con la 403 genérica. Incluir herramienta WP-CLI de pre-provisioning de `sub` y un fail-safe que evite el bloqueo total del acceso.
- **SEC-BE-003 (LOW)** — Impedir SSRF por redirecciones: las descargas del discovery y del JWKS (líneas 283 y 811) SHALL NOT seguir redirecciones HTTP.
- **SEC-BE-004 (LOW)** — Endurecer la política de `email_verified` a fail-closed: solo `true`/`1`/`'1'`/`'true'` cuentan como verificado.
- **SEC-BE-005 (LOW)** — Caché negativa corta (60 s) ante fallo de discovery, para no re-descubrir en cada `GET wp-login.php` anónimo con el IdP caído (DoS de workers FPM).
- **SEC-BE-006 (LOW, robustez)** — Derivar el flag `Secure` de la cookie de sesión del esquema del sitio (`home_url()`) en lugar de `is_ssl()`, para no depender del snippet de `wp-config.php` externo al repo.
- **H-2** — Comprobar que `userinfo.sub` coincide con `id_token.sub` cuando el `id_token` se ha validado.
- **H-4** — Exigir `kid` en el `id_token` y en la JWK seleccionada.
- **Spec** — Corregir la inconsistencia de TTL (el requisito «Authorization request with state and PKCE» dice 5 minutos y el resto de la spec y el código usan 15) y documentar el TTL real del `id_token` en su requisito de ciclo de vida.
- **Versionado** — este change **no** bumpea `ATAREAO_PLUGIN_VERSION`: el bump de versión lo aplica el pipeline de release en lockstep con `.vampus.yml` (header `Version:`, `ATAREAO_PLUGIN_VERSION` y tema).

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `pocketid-login`: ciclo de vida del `id_token` (TTL y binding por sesión), política de `email_verified` (fail-closed), vinculación de identidad por `sub` (auto-vinculación por email desactivada por defecto y condicionada a un ajuste), validación del `id_token` (`kid` obligatorio y coherencia `sub` userinfo/id_token), descargas sin redirecciones, caché negativa del discovery y flag `Secure` de la cookie de sesión.

## Impact

- **Archivos**:
  - Modificado: `wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php` (TTL y binding por sesión del `id_token`, `redirection => 0` en discovery/JWKS, `email_verified` estricto, política de vinculación, nuevo toggle «Permitir vinculación por email en el primer acceso» con su aviso, `kid`, coherencia de `sub`, caché negativa, flag `Secure` y comandos WP-CLI `bind-sub`/`list-bindings`).
  - Modificado (spec): `openspec/specs/pocketid-login/spec.md` vía el delta `specs/pocketid-login/spec.md`.
  - Nota: la pestaña de ajustes de PocketID —render y guardado— vive íntegramente en `includes/class-pocketid-login.php`; `includes/class-settings.php` es solo el hub que delega y **no se modifica**.
  - Nota: este change **no** modifica `atareao-functionality.php`. El bump de `ATAREAO_PLUGIN_VERSION` lo aplica el pipeline de release en lockstep con `.vampus.yml` (header `Version:`, constante y tema).
- **Riesgo operativo (SEC-BE-002)**: si la auto-vinculación queda desactivada por defecto y ninguna cuenta tiene `sub` ligado, el modo «Exigir PocketID» deja el acceso solo por el flujo OIDC y **deniega a todos**. Mitigación obligatoria: herramienta WP-CLI de pre-provisioning (`wp atareao-pocketid bind-sub <user> <sub>`), aviso en la pestaña de ajustes cuando no hay ninguna cuenta vinculada, y bloqueo del propio guardado (sin ninguna cuenta con `sub` ligado, marcar «Exigir PocketID» no lo activa y se avisa al administrador). La recuperación real requiere `wp atareao-pocketid bind-sub` por WP-CLI (o acceso a la BD); `lostpassword` no permite entrar con el modo exigir activo.
- **Compatibilidad**: PHP 8.3, PSR12, WordPress 7.x. Sin dependencias nuevas, sin build tools y sin framework de tests (verificación por análisis estático y E2E manual).
- **Verificación**: `just php-lint` (0 errores), `just phpcs` (sin empeorar baseline), `openspec validate pocketid-security-hardening --strict`, y E2E manual del login y del logout (incluido el caso de sesión de más de 2 días).
