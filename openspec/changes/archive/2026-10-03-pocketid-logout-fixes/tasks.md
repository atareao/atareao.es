# Tasks: Pocket ID Logout Fixes

> Sin TDD: el repo no tiene framework de tests. Verificación por `just php-lint`, `just phpcs` y prueba E2E manual en producción.

## 1. Versionar el esquema de la caché de descubrimiento (A)

- [x] 1.1 Añadir la constante `const CONFIG_SCHEMA = 2;` en `PocketIDLogin` y hacer que `fetchDiscoveryConfig()` incluya `'config_schema' => self::CONFIG_SCHEMA` en el array devuelto, tanto en el retorno del discovery como en el `source => 'fallback'`. Verificación: `just php-lint wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php` sin errores.
- [x] 1.2 En `getOIDCConfig()`, aceptar la caché solo si `is_array($cached)` y `(int) $cached['config_schema'] === self::CONFIG_SCHEMA` además de los tres endpoints obligatorios; en caso contrario, ignorarla y refrescar. Verificación: `just php-lint` sin errores y revisión de que una caché sin `config_schema` ya no se devuelve.
- [x] 1.3 Asegurar que `end_session_endpoint` sigue siendo opcional y NO condiciona la validez de la caché ni la reutilización. Verificación: revisión de código; el requirement `Discovery cache carries a schema version` se cumple con caché versionada aunque no haya `end_session_endpoint`.

## 2. Refresco puntual del discovery en el logout (B)

- [x] 2.1 En `handleLogoutRedirect()`, si `empty($config['end_session_endpoint'])`, llamar **una sola vez** a `self::getOIDCConfig(true)`; si tras el refresco aparece `end_session_endpoint` continuar con la redirección al proveedor; si no, registrar `error_log('[atareao-pocketid] …')` y devolver `$redirect_to` (fail-safe). Verificación: `just php-lint` sin errores y revisión de que no existe bucle de descargas.
- [x] 2.2 Confirmar que nunca se construye la URL con entrada del usuario y que el refresco solo actúa cuando la configuración está completa (`isConfigured()`). Verificación: revisión de código y `just phpcs` sin errores nuevos.
- [x] 2.3 Comprobar el caso proveedor sin `end_session_endpoint` tras refrescar: el logout local se completa sin error y se registra el motivo. Verificación: `just php-lint` y prueba manual con un proveedor/discovery sin el campo (o simulando la ausencia).

## 3. Pantalla post-logout limpia en modo exigir (C)

- [x] 3.1 En `renderLoginFooter()`, renderizar el botón "Iniciar sesión" **solo** cuando `OPTION_ENFORCE === '1'` y `!empty($_GET['loggedout'])`; en ese caso, inyectar un `<style>` que oculte el formulario inerte (`#loginform`, `#nav`, `#backtoblog` y enlaces de recuperación de contraseña) y, sin retornar, renderizar el aviso nativo de sesión cerrada junto con el botón "Iniciar sesión" (mismo `add_query_arg('action', 'pocketid', wp_login_url())` y `esc_url`). En el resto de páginas de `wp-login.php` (por ejemplo `lostpassword`) **no** se renderiza aviso ni botón: se deja la página nativa tal cual. Eliminar el aviso "Se requiere PocketID para acceder." (la etiqueta pública es siempre "Iniciar sesión", sin subtítulo que nombre al proveedor). Verificación: `just php-lint` sin errores.
- [x] 3.2 Mantener intacto el comportamiento de la pantalla normal: con enforce inactivo se sigue mostrando el botón "Iniciar sesión" y el formulario normal, sin inyectar CSS; con enforce activo fuera de `loggedout` no se añade aviso ni botón. Verificación: revisión de código y prueba manual de ambos casos.
- [x] 3.3 Confirmar que el POST con `log`+`pwd` sigue bloqueado con `pocketid_required` aunque el formulario esté oculto (el bloqueo es de servidor, no depende del CSS). Verificación: `just php-lint` y prueba manual del POST.
- [x] 3.4 Reescribir el mensaje del `WP_Error` `pocketid_required` para que diga: "El inicio de sesión con contraseña está deshabilitado. Usa el botón «Iniciar sesión»." sin nombrar al proveedor. Revisar todas las cadenas de texto de la UI pública (botón, avisos y mensajes de error) para confirmar que ninguna nombra al proveedor; el nombre solo puede aparecer en la página de Ajustes. Verificación: `just php-lint` y búsqueda de "Pocket ID"/"PocketID" limitada al contexto de ajustes.

## 4. UI y documentación (D)

- [x] 4.1 Verificar/actualizar en la página de ajustes el campo informativo "Post Logout Redirect URI" (`wp_login_url()` + `?loggedout=true`, solo lectura y copiable). Verificación: render correcto en wp-admin.
- [x] 4.2 Actualizar el `README.md` del plugin para documentar el comportamiento de logout corregido, el registro del Post Logout Redirect URI y la regla de producto: el botón público dice "Iniciar sesión" y el nombre del proveedor no aparece en la UI pública, solo en la página de Ajustes. Verificación: revisión del README.

## 5. Calidad

- [x] 5.1 Pasar `just php-lint` y `just phpcs` (PSR12) sobre el plugin; `just phpcbf` si procede. Verificación: `just php-lint` sin errores de sintaxis; `just phpcs` sin errores nuevos (los warnings de longitud de línea pre-existentes en el archivo son aceptables).

## 6. Despliegue y verificación E2E en producción

- [x] 6.1 En producción, tras desplegar, refrescar/limpiar el transient `atareao_pocketid_oidc_config` (borrarlo o ejecutar "Probar conexión" en la página de ajustes) para forzar la descarga de un discovery con esquema actual. Verificación: el transient pasa a incluir `config_schema` y `end_session_endpoint`.
- [x] 6.2 Registrar el Post Logout Redirect URI (`wp_login_url()` + `?loggedout=true`) en el cliente OIDC de PocketID. Verificación: PocketID acepta el registro y no rechaza la vuelta tras el logout.
- [x] 6.3 Prueba manual E2E: login OIDC (passkey) → logout con enforce activo → pantalla limpia sin formulario de contraseña, con botón "Iniciar sesión", aviso nativo de sesión cerrada y sesión del proveedor cerrada (el siguiente login vuelve a pedir passkey/credencial, sin re-login silencioso). Verificar además que en ninguna pantalla pública (login normal, enforce, post-logout y mensaje de error `pocketid_required`) aparece el texto "Pocket ID"/"PocketID". Verificación: escenario completado en producción sin regresiones en Matrix/ContactForm.
- [x] 6.4 Verificar que con enforce inactivo la pantalla de login sigue mostrando el botón y el formulario normal, y que la caché versionada no provoca descargas repetidas. Verificación: prueba manual y revisión del log `[atareao-pocketid]`.
