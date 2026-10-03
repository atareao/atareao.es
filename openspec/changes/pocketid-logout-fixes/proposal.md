# Proposal

## Why

El change `pocketid-logout` implementa RP-initiated logout en `wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php`, pero en producción se confirmaron dos fallos reales: (1) la caché de descubrimiento creada por versiones anteriores (commit `6525474`) nunca guardó `end_session_endpoint`, y `getOIDCConfig()` la acepta con solo los tres endpoints obligatorios, de modo que el logout cae al fail-safe local y **no cierra la sesión en PocketID**; y (2) con "Exigir PocketID" activo, la pantalla `wp-login.php?loggedout=true` muestra el aviso de sesión cerrada junto con el formulario de contraseña inerte, sin ofrecer el botón de PocketID, dejando al usuario en un callejón sin salida.

## What Changes

- **Fiabilidad del cierre de sesión ante cachés antiguas**: la configuración de descubrimiento cacheada incluirá una versión de esquema (`CONFIG_SCHEMA = 2`). Una entrada de caché sin la versión de esquema actual se considerará inválida y se refrescará desde el proveedor, de modo que `end_session_endpoint` (publicado por PocketID pero ausente en cachés antiguas) se repoble.
- **Refresco puntual en el logout**: si en el momento del logout la configuración disponible carece de `end_session_endpoint` pero el proveedor lo publica (sigue siendo opcional en el discovery), el plugin refrescará el discovery **una vez** antes de caer al logout local.
- **UX de la pantalla post-logout en modo exigir**: en `GET wp-login.php?loggedout=true`, con "Exigir PocketID" activo y configuración completa, el plugin ocultará el formulario de contraseña (inerte) y mostrará el aviso nativo de sesión cerrada junto con el botón/enlace "Iniciar sesión".
- **No exponer el nombre del proveedor en la UI pública**: el botón de login pasa a etiquetarse únicamente "Iniciar sesión" y se elimina el aviso "Se requiere PocketID para acceder."; el mensaje de error `pocketid_required` deja de nombrar al proveedor. El nombre del proveedor ("Pocket ID") queda reservado a la página de Ajustes (solo administradores).
- **UI y documentación**: se documentará el "Post Logout Redirect URI" a registrar en PocketID y se actualizará el `README.md` del plugin.

No hay ruptura: con configuración incompleta o modo exigir inactivo, la pantalla y el flujo no cambian. El comportamiento sigue siendo fail-safe.

## Capabilities

### New Capabilities
<!-- Sin capacidades nuevas: todo el comportamiento nuevo amplía `pocketid-login`. -->

### Modified Capabilities

- `pocketid-login`: se añaden cuatro requirements — `Discovery cache carries a schema version`, `Provider session termination survives a stale discovery cache`, `Clean post-logout screen under enforcement` y `Public login UI does not expose the provider name` — que refinan la resolución/caché del discovery, la UX de la pantalla post-logout y los textos públicos ya introducidos/afectados por `pocketid-logout`.

## Impact

- **Change previo relacionado**: `pocketid-logout` (activo). Este change depende de él: el logout RP-initiated y el respeto del estado post-logout que aquí se corrigen los introdujo aquel change. Debe archivarse `pocketid-logout` antes de implementar y archivar `pocketid-logout-fixes`.
- **Capacidad modificada**: `pocketid-login` (delta en `specs/pocketid-login/spec.md`, solo operaciones ADDED).
- **Archivos**: `wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php` (`getOIDCConfig()`, `fetchDiscoveryConfig()`, `handleLogoutRedirect()`, `renderLoginFooter()` y un nuevo estilo/hook condicional para ocultar el formulario), página de ajustes del plugin y `README.md` del plugin.
- **Compatibilidad**: PHP ≥ 7.4 (el servidor corre 8.3); sin dependencias nuevas. Nombres de opciones `atareao_pocketid_*`, transient `atareao_pocketid_oidc_config`, prefijo de log `[atareao-pocketid]`.
- **Migración**: la caché antigua se invalida sola al faltarle `CONFIG_SCHEMA`; no requiere limpieza manual, aunque en producción se puede borrar el transient de forma explícita.
- **Requisito externo**: registrar el Post Logout Redirect URI (`wp_login_url()` + `?loggedout=true`) en el cliente OIDC de PocketID.
- **Sin ruptura**: fail-safe; con configuración incompleta o enforce inactivo, nada cambia.
