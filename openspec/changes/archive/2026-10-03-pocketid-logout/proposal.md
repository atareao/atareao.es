# Proposal

## Why

Con el toggle "Exigir PocketID" (`atareao_pocketid_enforce = '1'`) activo, el usuario no puede cerrar sesión: WordPress ejecuta `wp-login.php?action=logout`, redirige a `wp-login.php?loggedout=true`, y en esa petición el flujo OIDC detecta el modo exigir y vuelve a mandar al proveedor. PocketID conserva su sesión SSO y reemite un `code` sin pedir nada, de modo que el plugin reautentica al usuario y el logout queda como un no-op. Además, el plugin nunca termina la sesión en el proveedor (no usa `end_session_endpoint` ni conserva el `id_token`).

## What Changes

- **Respeto del estado post-logout**: en `handleLoginFlow()`, si la petición es la página de cierre de sesión (presencia de `loggedout`), el flujo OIDC no se inicia aunque el modo exigir esté activo; se renderiza la pantalla nativa "Has cerrado la sesión".
- **Logout iniciado en el proveedor (RP-initiated logout)**: al cerrar sesión, redirigir el navegador al `end_session_endpoint` publicado por el discovery de PocketID con `id_token_hint`, `client_id` y `post_logout_redirect_uri` validado; si el proveedor no lo publica o falta el `id_token`, el logout local se completa igualmente.
- **Ciclo de vida del `id_token`**: persistir server-side el `id_token` devuelto por el token endpoint (ligado a la sesión, nunca en cookie legible por el navegador) y borrarlo al cerrar sesión.
- **UI y documentación**: la página de ajustes SHALL informar del "Post Logout Redirect URI" que debe registrarse en PocketID, y el `README.md` del plugin SHALL documentar el comportamiento de logout.

No hay ruptura: el diseño es fail-safe y, con configuración incompleta o modo exigir inactivo, el comportamiento no cambia.

## Capabilities

### New Capabilities
<!-- Sin capacidades nuevas: todo el comportamiento nuevo amplía `pocketid-login`. -->

### Modified Capabilities

- `pocketid-login`: se modifica el requirement `Native wp-login actions passthrough` (el logout también termina la sesión en el proveedor y respeta la pantalla de sesión cerrada) y se añaden tres requirements: respeto del estado post-logout, logout iniciado en el proveedor y ciclo de vida del `id_token`.

## Impact

- **Capacidad modificada**: `pocketid-login` (delta en `specs/pocketid-login/spec.md`). **Prerrequisito de archivado**: el change `pocketid-oidc-login` debe archivarse ANTES que `pocketid-logout`, de modo que `pocketid-login` exista en `openspec/specs/` y el delta MODIFIED case.
- **Archivos**: `wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php` (nuevos hooks/métodos), página de ajustes del plugin (campo informativo del Post Logout Redirect URI) y `README.md` del plugin (documentación de logout).
- **Compatibilidad**: PHP ≥ 7.4 (el servidor corre 8.3); sin dependencias nuevas. Nombres de opciones `atareao_pocketid_*`, prefijo de log `[atareao-pocketid]`.
- **Requisito externo nuevo**: registrar `post_logout_redirect_uri` (`wp_login_url()` + `?loggedout=true`) en el cliente OIDC de PocketID si el proveedor lo exige.
- **Sin ruptura**: fail-safe; con configuración incompleta o enforce inactivo, nada cambia.
