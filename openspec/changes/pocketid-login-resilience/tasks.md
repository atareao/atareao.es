# Tasks: Pocket ID Login Resilience

> Sin TDD: el repo no tiene framework de tests. Verificación por `just php-lint`, `just phpcs` y prueba E2E manual. La tarea 0.1 y 0.2 registran evidencia ya confirmada; el resto empieza sin marcar.

## 0. Diagnóstico y evidencia (prerequisito)

- [x] 0.1 Evidencia ya confirmada en producción: `COOKIEPATH=/`, `COOKIE_DOMAIN=''` (vacío) y `login=https://atareao.es/wp-login.php`. Conclusión: en el estado actual **la cookie de estado ya es host-only** → la sospecha A (fuga al subdominio del IdP) queda DESCARTADA. Verificación: valores confirmados por el operador.
- [x] 0.2 Log de los últimos 10 minutos **sin** `Callback sin cookie de estado válida.` → el fallo no se reproduce ahora; la sospecha B (TTL de 5 min demasiado corto / callbacks repetidos) queda como hipótesis principal. Verificación: revisión del log `[atareao-pocketid]`.
- [ ] 0.3 *Pendiente solo si el fallo vuelve a reproducirse*: capturar la cabecera `Set-Cookie` real de `atareao_pocketid_oauth` al iniciar el flujo y las cabeceras del callback (`Cookie` recibida, `state` de la query y marcas de tiempo de emisión/retorno) para medir el tiempo real del prompt de passkey. Verificación: evidencia registrada.
- [ ] 0.4 *Pendiente*: confirmar si el fallo se reprodujo en **uso normal** (navegación real) o durante **pruebas manuales** (recargas/reintentos del desarrollador), lo que reforzaría o debilitaría la sospecha B. Verificación: conclusión documentada; el diseño robusto no depende de ella.

## 1. D1 — Cookie de estado host-only (hardening preventivo)

> La evidencia actual (`COOKIE_DOMAIN=''`) indica que la cookie ya es host-only: esta sección es hardening defensivo/explicitación, no la corrección de un bug activo.

- [x] 1.1 En `oauthCookieOptions()`, sustituir `'domain' => COOKIE_DOMAIN` por `'domain' => ''` para **omitir el atributo `Domain`** (host-only real; un `Domain` no vacío, aunque sea el host exacto, cubriría también los subdominios por RFC 6265). Conservar `path`, `Secure`, `HttpOnly` y `SameSite=Lax`. Verificación: `just php-lint wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php` sin errores y revisión de que no queda ningún uso de `COOKIE_DOMAIN` para la cookie de estado.
- [x] 1.2 Confirmar que la limpieza de la cookie (`clearOAuthCookie()`) usa las mismas opciones host-only (sin atributo `Domain`), de modo que el borrado alcance exactamente a la cookie emitida. Verificación: revisión de código y `just phpcs` sin errores nuevos.

## 2. D2 — TTL único y compartido del state

- [x] 2.1 Añadir `const STATE_TTL = 15 * MINUTE_IN_SECONDS;` y usarla tanto en el `set_transient(...)` del state en `startFlow()` como en la emisión de la cookie. Verificación: `just php-lint` sin errores y búsqueda de que no queden dos valores divergentes.
- [x] 2.2 Eliminar o redefinir `OAUTH_COOKIE_TTL` como alias de `STATE_TTL` según el criterio del diseño, actualizando todos sus usos. Verificación: `just phpcs` sin errores nuevos y `just php-lint` sin errores.
- [x] 2.3 Confirmar que la cookie y el transient se eliminan al completar o fallar el flujo (cookie en `handleCallback()`, transient al consumirlo). Verificación: revisión de código.

## 3. D3 — Fallos de callback diagnosticables

- [x] 3.1 En `handleCallback()`, registrar con `[atareao-pocketid]` la causa concreta: cookie ausente, cookie ilegible (JSON inválido o sin `state`/`code_verifier`), state ausente/expirado, replay y mismatch, además de `code` ausente. Verificación: `just php-lint` sin errores y revisión de que cada rama registra su causa.
- [x] 3.2 Garantizar que ningún mensaje de log vuelca el valor del `state`, del `code` ni del `code_verifier`, y que el usuario sigue viendo la 403 genérica (`dieGeneric403()`). Verificación: revisión de código y `just phpcs` sin errores nuevos.

## 4. D4 — Ajuste de email verificado + UI

- [x] 4.1 Añadir `const OPTION_REQUIRE_VERIFIED_EMAIL = 'atareao_pocketid_require_verified_email';` y leerlo **solo en servidor** en `handleCallback()` con default `'1'` (estricto). Verificación: `just php-lint` sin errores.
- [x] 4.2 Con el ajuste activo, mantener el rechazo con 403 genérica y registrar el motivo cuando `email_verified=false`; con el ajuste inactivo, continuar el login y registrar que el email no está verificado. Verificación: revisión de las dos ramas.
- [x] 4.3 Preservar el caso "claim ausente": si no existe `email_verified` en la respuesta, no bloquear. Verificación: revisión de código.
- [x] 4.4 Añadir el checkbox del ajuste en `renderSettingsPage()` y su `update_option()` en el guardado (`'1'`/`'0'`, patrón de `OPTION_ENFORCE`), protegido por el nonce existente (`check_admin_referer`) y `manage_options`. Verificación: render correcto en wp-admin y `just php-lint` sin errores.

## 5. Documentación (README)

- [x] 5.1 Actualizar `wp-content/plugins/atareao-functionality/README.md`: alcance host-only de la cookie de estado, nuevo TTL del state, causas de callback diagnosticadas y el ajuste `atareao_pocketid_require_verified_email` (default estricto), incluyendo su comando WP-CLI de gestión. Verificación: revisión del README.

## 6. Calidad

- [x] 6.1 Pasar `just php-lint` y `just phpcs` (PSR12) sobre el plugin; `just phpcbf` si procede. Verificación: `just php-lint` sin errores de sintaxis; `just phpcs` sin errores nuevos.

## 7. Verificación E2E manual

- [ ] 7.1 Iniciar el flujo OIDC (passkey) y confirmar que la cookie de estado es host-only (la cabecera `Set-Cookie` **no incluye el atributo `Domain`**) y no se envía a `pocketid.<dominio>`. Verificación: inspección de la cabecera `Set-Cookie` y de las peticiones al IdP.
- [ ] 7.2 Repetir un callback (recargar o reintentar) y confirmar que el log distingue "cookie ausente" de "replay" y que el usuario ve la 403 genérica. Verificación: log `[atareao-pocketid]` y pantalla.
- [ ] 7.3 Completar un prompt de passkey lento (dentro del nuevo TTL) y confirmar que el callback valida el state y el login se establece. Verificación: sesión iniciada correctamente.
- [ ] 7.4 Verificar ambos modos del ajuste de email verificado: activo (rechaza `email_verified=false` con 403 genérica) e inactivo (continúa y registra el email no verificado). Verificación: prueba manual en wp-admin y log.
- [ ] 7.5 Confirmar que el flujo de logout y la pantalla post-logout no han cambiado. Verificación: logout manual sin regresiones.
