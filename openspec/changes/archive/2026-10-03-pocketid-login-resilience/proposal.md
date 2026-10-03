# Proposal

## Why

En producción, el log del plugin `[atareao-pocketid]` ha mostrado de forma intermitente `Callback sin cookie de estado válida.` (línea ~367 de `wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php`), y el rechazo `Email no verificado para: <email>` (líneas ~469-475) es una política fija que el operador no puede ajustar sin tocar el proveedor. La causa del fallo de cookie **no está confirmada**: la evidencia de producción muestra `COOKIE_DOMAIN=''` y `COOKIEPATH='/'`, de modo que la cookie de estado **ya es host-only** y la hipótesis de fuga al subdominio del IdP queda **descartada**; ahora mismo el fallo no se reproduce. La hipótesis principal restante es el TTL de 5 minutos (`OAUTH_COOKIE_TTL = 300`), corto para un prompt de passkey lento o un reintento, agravado porque `handleCallback()` borra la cookie nada más empezar, con lo que un callback repetido (recarga, reintento, dos pestañas) degenera en el mismo mensaje genérico.

Este change actúa por **robustez** (TTL realista del state + logging que diferencie la causa del callback), por la **política configurable de verificación de email** y, de forma **preventiva**, para que la cookie quede host-only por diseño y no dependa de que `COOKIE_DOMAIN` siga vacío en el futuro.

## What Changes

- **Cookie de estado host-only (preventivo)**: la cookie de estado OIDC dejará de usar `COOKIE_DOMAIN` como atributo `Domain`. Se emitirá host-only para el host exacto de `wp_login_url()`, de modo que nunca se envíe a `pocketid.<dominio>`. La evidencia actual (`COOKIE_DOMAIN=''`) indica que hoy ya es host-only: es **hardening defensivo**, no la corrección de un bug activo, y evita depender de que `COOKIE_DOMAIN` siga vacío.
- **TTL realista y compartido**: se introducirá una constante única de TTL del state (p. ej. `STATE_TTL = 15 * MINUTE_IN_SECONDS`) usada tanto por la cookie como por el transient single-use del servidor, sustituyendo el uso de `OAUTH_COOKIE_TTL` (o redefiniéndolo) sin romper compatibilidad. Ambos SHALL eliminarse al completar o fallar el flujo. Es la hipótesis principal para el fallo observado.
- **Fallos de callback diagnosticables**: ante un callback inválido, el plugin registrará con el prefijo `[atareao-pocketid]` la causa concreta (cookie ausente, cookie ilegible, state ausente/expirado, replay o mismatch) sin exponer detalles al usuario, que seguirá viendo la pantalla 403 genérica.
- **Política de email verificado configurable**: nuevo ajuste `atareao_pocketid_require_verified_email` (por defecto `'1'`, estricto) con checkbox en la página de Ajustes. Con el ajuste activo, `email_verified=false` se rechaza con la 403 genérica; con el ajuste inactivo, el login continúa y se registra que el email no está verificado. La preferencia se lee **solo en servidor**; el mensaje al usuario sigue siendo genérico y sin nombrar al proveedor.
- **Documentación**: se actualizará el `README.md` del plugin (secciones de login, problemas encontrados y opciones WP-CLI) con el nuevo alcance de la cookie, el TTL y el ajuste de email verificado.

No hay ruptura: el ajuste de email verificado nace activado (comportamiento actual), el flujo de logout no se modifica y con configuración incompleta o modo exigir inactivo nada cambia.

## Capabilities

### New Capabilities
<!-- Sin capacidades nuevas: todo el comportamiento nuevo amplía `pocketid-login`. -->

### Modified Capabilities

- `pocketid-login`: se añaden cuatro requirements — `State cookie is scoped to the login host`, `State lifetime tolerates realistic login durations`, `Callback failures are diagnosable` y `Configurable email verification policy` — que endurecen el alcance y el ciclo de vida de la cookie/state OIDC, mejoran el diagnóstico de fallos de callback y hacen configurable la política de verificación de email ya introducida por el flujo OIDC.

## Impact

- **Change previo relacionado**: el flujo OIDC (`pocketid-oidc-login`) y el logout (`pocketid-logout`, `pocketid-logout-fixes`), ya archivados. Este change es aditivo sobre ellos y no altera el flujo de logout.
- **Capacidad modificada**: `pocketid-login` (delta en `specs/pocketid-login/spec.md`, solo operaciones ADDED).
- **Archivos**: `wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php` (`oauthCookieOptions()`, `startFlow()`, `handleCallback()`, nueva constante de TTL, lectura del ajuste de email verificado y la página de ajustes `renderSettingsPage()`) y `wp-content/plugins/atareao-functionality/README.md`.
- **Compatibilidad**: PHP ≥ 7.4 (el servidor corre 8.3); sin dependencias nuevas. Nombres de opciones `atareao_pocketid_*`, transient de state `atareao_pid_state_<hash>`, cookie `atareao_pocketid_oauth`, prefijo de log `[atareao-pocketid]`.
- **Cambio de comportamiento acotado**: en el estado actual del sitio la cookie ya es host-only (`COOKIE_DOMAIN=''`), por lo que D1 no cambia el comportamiento observable hoy; es preventivo. Si el sitio creciera a un login multi-subdominio, una cookie host-only no se compartiría, pero no es el caso. El ajuste de email verificado se lee solo en servidor y por defecto conserva el rechazo estricto.
- **Causa no confirmada**: la causa del `Callback sin cookie de estado válida.` no está confirmada (la fuga al IdP queda descartada por la evidencia y no se reproduce ahora); el change no depende de ella porque el TTL, el logging y el hardening son correctos por diseño.
- **Sin ruptura**: fail-safe; con el ajuste por defecto y el flujo actual, nada cambia salvo la robustez del state y el detalle del log.
