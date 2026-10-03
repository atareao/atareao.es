# Design: Pocket ID Login Resilience

## Context

Ver `proposal.md` — Why. El módulo `\Atareao\PocketIDLogin` (`wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php`) implementa el flujo OIDC Authorization Code + PKCE. Restricciones: PHP ≥ 7.4 (servidor 8.3), clase estática, opciones `atareao_pocketid_*`, cookie `atareao_pocketid_oauth`, transient de state `atareao_pid_state_<hash>`, log `[atareao-pocketid]`, sin tests automatizados (verificación por `just php-lint` + `just phpcs` y prueba E2E manual).

Estado actual relevante:

- `oauthCookieOptions()` (líneas ~704-714) construye la cookie con `'domain' => COOKIE_DOMAIN`. Si `COOKIE_DOMAIN` fuera el dominio padre (p. ej. `.atareao.es`), la cookie dejaría de ser host-only y se enviaría también a `pocketid.atareao.es`, filtrando al IdP el `state` y el `code_verifier`. **Evidencia de producción (actualizada)**: `COOKIEPATH=/` y `COOKIE_DOMAIN=''` (vacío), con `login=https://atareao.es/wp-login.php`. Es decir, **en el estado actual la cookie ya es host-only** y la sospecha A (fuga al subdominio del IdP) queda **descartada**; la sospecha B (TTL de 5 min demasiado corto / callbacks repetidos) es la hipótesis principal restante.
- `startFlow()` (líneas ~300-355) genera `state` + `code_verifier`, los guarda en el transient con expiración `OAUTH_COOKIE_TTL` (300 s) y emite la cookie con `setOAuthCookie()` y el mismo `OAUTH_COOKIE_TTL`.
- `handleCallback()` (líneas ~360-513) borra la cookie al inicio (`clearOAuthCookie()`), luego valida el `state` contra el transient single-use y contra `$_GET['state']`. Un callback repetido cae siempre en el mismo `self::log('Callback sin cookie de estado válida.')` con la 403 genérica.
- `handleCallback()` (líneas ~469-475) rechaza con 403 si `email_verified` es falso.

## Goals / Non-Goals

**Goals:**

- Explicitar/endurecer la cookie de estado como host-only (hardening defensivo, D1): no corrige un fallo activo hoy, pero evita que el flujo dependa de que `COOKIE_DOMAIN` siga vacío en el futuro.
- Dar al state (cookie + transient) un TTL realista y compartido, y garantizar su limpieza en éxito y error (hipótesis principal del fallo observado).
- Diferenciar en el log la causa concreta de un callback inválido sin filtrar detalles al usuario.
- Hacer configurable la exigencia de `email_verified`, con default estricto (comportamiento actual).

**Non-Goals:**

- Cambiar el flujo de logout (RP-initiated logout, post-logout, `id_token`) ni su UI.
- Cambiar el descubrimiento OIDC, el bloqueo de contraseña, el PKCE ni el emparejamiento por email.
- Alterar la pantalla 403 genérica ni los textos públicos (siguen sin nombrar al proveedor).
- Sustituir el mecanismo de cookie/transient por otro almacén.

## Decisions

### D1. Cookie de estado host-only — hardening defensivo / explicitación (no bug activo)

En `oauthCookieOptions()` se sustituye `'domain' => COOKIE_DOMAIN` por `'domain' => ''`, de modo que `setcookie()` **omite el atributo `Domain`** y la cookie queda **host-only real**. Para que una cookie sea host-only hay que **no emitir `Domain`**; pasar el host exacto (`atareao.es`) como domain la convierte en una cookie de **dominio** que, por RFC 6265, cubre el host **y todos sus subdominios** (incluido `pocketid.<dominio>`), que es justo lo que se quiere evitar.

- **Reclasificación**: la evidencia de producción confirma `COOKIE_DOMAIN=''` y `COOKIEPATH='/'`, por lo que **la cookie ya es host-only hoy y la sospecha A (fuga al subdominio del IdP) queda DESCARTADA**. D1 NO corrige un bug activo: es *hardening defensivo/explicitación* de una garantía que en la práctica dependía del valor de una constante de configuración. Se mantiene para que la cookie sea host-only **por diseño** y no por coincidencia, y no se degrade si en el futuro se define `COOKIE_DOMAIN` como dominio padre (una práctica común en WordPress, p. ej. con `WP_HOME`/multisite).
- **Por qué**: el `state` y el `code_verifier` son secretos del flujo del cliente. Si la cookie se emitiera con un `Domain` no vacío —el de `COOKIE_DOMAIN` o incluso el host exacto—, por RFC 6265 el navegador la enviaría también a los subdominios del dominio y la adjuntaría a `pocketid.<dominio>`, con lo que el IdP recibiría material anti-CSRF que nunca debería ver. Omitir el atributo `Domain` la limita al host exacto que la creó.
- **Implementación**: en `oauthCookieOptions()` se fija `'domain' => ''` para **no emitir el atributo `Domain`** (host-only implícito). No se toca `path`, `Secure`, `HttpOnly` ni `SameSite=Lax`. `clearOAuthCookie()` reutiliza las mismas opciones (mismo dominio vacío), por lo que el borrado alcanza exactamente la cookie host-only emitida.
- **Impacto**: si el sitio viviera repartido en varios subdominios con login cruzado, una cookie host-only no se compartiría; no es el caso de este sitio (un único host), y aun siéndolo, cada flujo se inicia y completa en el mismo host, por lo que el login no se rompe.
- **Alternativa descartada**: conservar `COOKIE_DOMAIN` y confiar en que permanezca vacío. Se descarta porque deja la seguridad del flujo a merced de un ajuste global de WordPress que puede cambiar sin relación con este plugin.
- **Alternativa descartada**: emitir la cookie con el host exacto (`atareao.es`) como `Domain`. Se descarta porque un `Domain` no vacío, por RFC 6265, también cubre los subdominios; no es host-only y reabriría la fuga al IdP.

### D2. TTL único y compartido para el state

Se introduce una constante `const STATE_TTL = 15 * MINUTE_IN_SECONDS;` (900 s) y se usa tanto en `set_transient(...)` de `startFlow()` como en `setOAuthCookie()` (a través de `oauthCookieOptions()`).

- **Por qué 15 min**: un prompt de passkey lento (biometría, cambio de dispositivo, reintento) puede superar con holgura los 5 min actuales sin dejar una ventana de riesgo desproporcionada; el state sigue siendo single-use y se consume en el callback. Cookie y transient deben compartir TTL para que no haya estados donde una parte del flujo siga viva y la otra no.
- **Compatibilidad**: `OAUTH_COOKIE_TTL` se sustituye por `STATE_TTL` o se redefine como alias (`const OAUTH_COOKIE_TTL = self::STATE_TTL;`) para no romper referencias externas; internamente se unifica. Al no haber consumidores fuera de la clase, se puede eliminar la constante antigua y actualizar sus usos.
- **Limpieza**: el transient se consume en el callback (se borra antes de validaciones posteriores) y la cookie se borra al inicio de `handleCallback()`; el TTL solo acota la ventana máxima de validez, no impide la limpieza inmediata.
- **Alternativa descartada**: subir solo `OAUTH_COOKIE_TTL` de 300 a 900. Se descarta por dejar dos nombres/valores potencialmente divergentes; una única constante evita que cookie y transient se desincronicen.

### D3. Logging diferenciado de causas de callback

En `handleCallback()` se sustituye el registro único por mensajes específicos con el prefijo `[atareao-pocketid]` según la causa:

- cookie ausente o vacía (`$_COOKIE` sin `atareao_pocketid_oauth`),
- cookie ilegible (JSON inválido o sin `state`/`code_verifier`),
- state ausente o expirado (transient no encontrado / verifier distinto),
- replay (transient ya consumido / reutilizado),
- mismatch (`hash_equals` falla),
- `code` ausente.

Todos ellos mantienen la pantalla 403 genérica (`dieGeneric403()`), sin exponer la causa al usuario ni nombrar al proveedor. No se registra el valor del state, del code ni del verifier (evitar volcar secretos al log).

- **Por qué**: el mensaje actual `Callback sin cookie de estado válida.` no distingue entre las causas candidatas (reload, reintento, dos pestañas, fuga al IdP, TTL corto), lo que impide diagnosticar en producción.

### D4. Ajuste `atareao_pocketid_require_verified_email` (default estricto)

Se añade la constante `const OPTION_REQUIRE_VERIFIED_EMAIL = 'atareao_pocketid_require_verified_email';` y un checkbox en `renderSettingsPage()`, con `update_option()` en el guardado (patrón `'1'`/`'0'`, igual que `OPTION_ENFORCE`). El valor por defecto es `'1'` (estricto).

En `handleCallback()`:

- Si el ajuste está activo (`'1' === get_option(self::OPTION_REQUIRE_VERIFIED_EMAIL, '1')`) y el claim es falso, se mantiene el rechazo con 403 genérica y se registra el motivo.
- Si el ajuste está inactivo, el login continúa y se registra que el email no está verificado.
- Si el claim está ausente, no se bloquea (comportamiento actual preservado).

- **Por qué un ajuste y no cambiar el default**: la decisión de producto es conservar el rechazo estricto por defecto; el ajuste permite relajar la política en instalaciones donde PocketID no marca los emails como verificados, sin tocar el proveedor.
- **Seguridad**: la preferencia se lee únicamente en servidor (nunca desde parámetros de la petición), el guardado se protege con el nonce existente (`check_admin_referer`) y la página exige `manage_options`. El mensaje al usuario sigue siendo genérico y sin nombrar al proveedor.

## Risks / Trade-offs

- [La cookie host-only podría no compartirse entre subdominios si el sitio creciera a un login multi-subdominio] → No aplica hoy (un solo host); cada flujo OIDC vive en el mismo host. Documentado en el README.
- [Un TTL más largo (15 min) amplía la ventana de un state no consumido] → El state sigue siendo single-use y se valida también contra `$_GET['state']`; la cookie es `HttpOnly`, `Secure` y host-only, y no se envía al IdP. El riesgo añadido es marginal frente a la mejora de robustez.
- [El diagnóstico depende de distinguir causas en el callback] → No cambia el comportamiento visible (misma 403 genérica); solo mejora el log. Se evita registrar valores secretos.
- [El ajuste laxo de `email_verified` rebaja la garantía de identidad] → Es opt-in y nace en el valor estricto; con él inactivo se registra que el email no está verificado para que quede traza.
- [Sin tests automatizados] → Se mitiga con `just php-lint`, `just phpcs` y la prueba E2E manual de la sección de tareas.

## Diagnostic task (prerequisite)

Antes de implementar, el change incluye una tarea de **diagnóstico**. **Resultado ya obtenido en producción**:

- `COOKIEPATH=/` y `COOKIE_DOMAIN=''` (vacío), con `login=https://atareao.es/wp-login.php`.
- El log de los últimos 10 minutos **no muestra ningún** `Callback sin cookie de estado válida` (no reproduce ahora).

Conclusión de esa evidencia: **la cookie ya es host-only y la sospecha A (fuga al subdominio del IdP) queda DESCARTADA** en el estado actual. La **sospecha B (TTL de 5 min demasiado corto y/o callbacks repetidos: recarga, reintento, dos pestañas) pasa a ser la hipótesis principal**. D1 se mantiene como hardening preventivo (ver D1), no como corrección de un bug activo.

Queda **pendiente** (solo si el fallo vuelve a reproducirse) capturar el `Set-Cookie` real de `atareao_pocketid_oauth` al iniciar el flujo y las cabeceras del callback (`Cookie` recibida, `state` de la query y timestamps de emisión/retorno).

Caso a confirmar en esta tarea: si el fallo se reprodujo realmente en **uso normal** (navegación real de usuarios) o durante **pruebas manuales** (recargas/reintentos del desarrollador), lo que reforzaría o debilitaría la sospecha B.

El change fija el comportamiento robusto (hardening host-only + TTL 15 min + logging de causas) con independencia de la causa exacta: los tres cambios son correctos por diseño y no dependen de reproducir el fallo para implementarse.

## Migration Plan

1. Evidencia de diagnóstico: ya registrada la configuración (`COOKIE_DOMAIN=''`, `COOKIEPATH='/'`, host `atareao.es`) y la no reproducción reciente; la captura del `Set-Cookie`/cabeceras del callback queda pendiente solo si el fallo reaparece (ver `tasks.md`, tarea 0).
2. Implementar D1 → D2 → D3 → D4 con `just php-lint` y `just phpcs` (PSR12) en verde.
3. Actualizar el `README.md` del plugin.
4. Desplegar. No hay migración de datos: la cookie es efímera y el transient de state expira solo; el nuevo ajuste se lee con default `'1'`.
5. Prueba E2E manual: login OIDC (passkey) con prompt tardío y en ventana de incógnito; verificar que el callback no cae en "cookie de estado válida" y que la cabecera `Set-Cookie` de la cookie de estado no incluye el atributo `Domain`; verificar ambos modos del ajuste de email verificado.
6. Rollback: revertir el archivo PHP y el README. El cambio es aditivo (nueva constante, nuevo ajuste y ramas nuevas); al desinstalar el ajuste, el getter devuelve el default `'1'`.

## Open Questions

- La causa exacta del `Callback sin cookie de estado válida.` **no está confirmada**; la evidencia descarta la sospecha A (fuga al IdP) y señala la B (TTL/callbacks repetidos) como hipótesis principal. No bloquea la implementación: el TTL (15 min), el logging de causas y el hardening host-only se deciden aquí y son correctos independientemente de la causa.
- Caso a confirmar en la tarea 0: si el fallo se reprodujo en uso normal o durante pruebas manuales.
