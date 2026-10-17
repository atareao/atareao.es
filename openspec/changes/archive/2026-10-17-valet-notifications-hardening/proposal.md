## Why

El módulo `\Atareao\ValetConfig` (capability `valet-notifications`, archivada en `2026-10-16-valet-notifications`) se mergeó en `development` con su spec de cuatro requirements. Sobre ese trabajo se hicieron **dos revisiones posteriores** cuyo inventario de hallazgos motiva este change:

- **Revisión de calidad/arquitectura** (contratos, estilo, cobertura del harness). Veredicto: el diseño es correcto y los contratos están bien definidos; los hallazgos son en su mayoría **correcciones baratas** (formato, uso de constantes, una rama muerta) y **huecos de cobertura del harness** (`tests/test-valet.php` + `tests/wp-stubs.php`), no defectos funcionales. También detectó un problema de **fidelidad del harness**: el stub de `esc_url_raw` es más restrictivo que WordPress real y da falsa confianza.
- **Revisión de seguridad**. Veredicto: sin vulnerabilidades explotables de forma trivial, pero con **dos riesgos MEDIA** que merecen mitigación (prompt injection indirecto vía formulario público y ausencia de límite en la ruta de comentarios que puede agotar workers de PHP-FPM) y **una mejora defensiva BAJA** (endurecer el endpoint de Valet). La mejora defensiva **no cruza frontera de privilegio** (exige `manage_options`), por lo que se clasifica como endurecimiento, no como vulnerabilidad.

En resumen, este change **no cambia el propósito** de la capability: conserva su contrato de opciones, su UI y su semántica de notificación. Aplica correcciones, cierra huecos de cobertura y endurece los tres puntos de riesgo identificados.

## What Changes

### Correcciones (nivel A — sin cambio de comportamiento observable, salvo A3/A4)

- **A1** — `includes/class-valet-config.php`: la línea ~84 supera los 120 caracteres (único warning PSR12 nuevo del PR). Se reformatea.
- **A2** — Sustituir el literal `4096` por la constante `self::MESSAGE_MAX_BYTES` en la validación y en el mensaje de error, para que no diverjan.
- **A3** — `includes/class-contact-form.php`: eliminar la rama de respaldo muerta `if (!$has_matrix && !$has_valet) { MatrixConfig::sendMatrixMessage(...) }`. Sin credenciales, `sendMatrixMessage()` siempre devuelve la cadena de error, de modo que la rama no puede tener éxito nunca; solo conserva el comportamiento antiguo de intentar y fallar. El comportamiento observable **no cambia** (sigue mostrándose el error): se cae directamente al error. Por eso `matrix-notifications` **no se toca**.
- **A5** — `tests/wp-stubs.php`: alinear el stub de `esc_url_raw` con `wp_allowed_protocols()` (añadir `gopher, news, irc, nntp, feed, telnet, mms, rtsp, sms, svn, fax, xmpp, webcal, urn`). Es un problema de fidelidad del harness, no del contrato.

### Huecos de cobertura (nivel B — solo tests, sin delta de spec)

`tests/test-valet.php` (86 asserts) no cubre varios escenarios ya exigidos por las specs. Se cierran **sin tocar ninguna spec**: niveles `warning`/`critical` y default, respuestas 500 y sin `response`, la rama POST del botón de prueba, el hub `renderPage()`/`activeTab()` (escenarios de `admin-settings`), `notifyOnComment` con comentario inexistente, transición con `$comment` no-objeto, recorte de `origin`/`author`, endpoint con `/` final, rutas antiabuso del formulario de contacto, presupuesto agotado, `error_log` bajo `WP_DEBUG`, fidelidad del stub y limpieza del harness (`2>/dev/null`, campos no consumidos). Detalle exacto en `tasks.md`.

### Endurecimiento defensivo (nivel C — sí cambia la spec)

- **C3 (SEC-003, BAJA — endurecimiento, NO vulnerabilidad)** — En `sendValetNotification()`: exigir `https` en el endpoint, validarlo con `wp_http_validate_url()` y enviar `redirection => 0`. Sin `manage_options` no es explotable, así que se documenta como dureza defensiva.
- **C1 (SEC-001, MEDIA)** — El formulario de contacto es público (pre-auth) y su contenido llega literalmente a los campos `author`/`message` del JSON que consume un **asistente de IA** (Valet). Es un vector de **prompt injection indirecto**. Mitigación: marcar el contenido de origen público como **no confiable** de forma explícita y observable en el payload, y **acotar la longitud** de los campos. Además, se unifica la política de longitud: el mensaje del formulario **se recorta con el mismo mecanismo byte-safe** que la ruta de comentarios (sufijo `…`), en lugar de rechazarse; se conserva el límite duro de `sendValetNotification()` como defensa en profundidad.
- **C2 (SEC-002, MEDIA)** — La ruta de comentarios (`comment_post` / `transition_comment_status`) no tiene límite propio y hace un `wp_remote_post` **síncrono con timeout de 10 s**, permitiendo que comentarios auto-aprobados o pingbacks/trackbacks (que saltan captcha, honeypot y timing) agoten workers de PHP-FPM. Mitigación: límite de envíos por ventana en esa ruta (contador por IP con hash salado, configurable por filtro) y **exclusión de pingbacks/trackbacks**. El **timeout de 10 s se mantiene** (decisión previa ya razonada).
- **A4** — Cuando el formulario de contacto notifica correctamente a Matrix pero Valet falla, el fallo queda **totalmente silencioso**. Se registra mediante `error_log` (misma convención `WP_DEBUG` que el resto del módulo), **sin incluir jamás el token ni datos sensibles** (remitente, email o cuerpo).

## Impact

### Ficheros afectados

- `wp-content/plugins/atareao-functionality/includes/class-valet-config.php` — A1, A2, C1 (marcado + presupuesto), C2 (límite + exclusión pingback/trackback), C3 (endurecimiento).
- `wp-content/plugins/atareao-functionality/includes/class-contact-form.php` — A3 (rama muerta), A4 (registro del fallo), C1 (origen no confiable + acotado).
- `tests/test-valet.php` — todos los huecos del nivel B y los tests nuevos del nivel C.
- `tests/wp-stubs.php` — A5 (fidelidad de `esc_url_raw`), B12 (limpieza del harness).
- **Documentación**:
  - `wp-content/plugins/atareao-functionality/README.md` — aclarar que los «4096 bytes» del formulario de contacto **se recortan** (no se rechazan), documentar el marcado `untrusted`, el límite de la ruta de comentarios y la exigencia de `https`.

### Especificaciones tocadas

- `valet-notifications` — **única spec modificada**: un requirement MODIFIED (`Contrato de envío de notificaciones a Valet`) y tres ADDED (marcado no confiable, límite de envíos en comentarios, registro de fallos del canal Valet).

### Especificaciones que NO se tocan (y por qué)

- `matrix-notifications` — **NO se toca**: A3 elimina una rama muerta sin alterar el comportamiento observable (sigue mostrándose el error cuando no hay canal configurado); el contrato de `sendMatrixMessage()` no cambia.
- `admin-settings` — **NO se toca**: los huecos B4 se cubren con tests contra escenarios que la spec **ya exige**; no hay cambio de UI ni de contrato del hub.
- `anti-abuse` — **NO se toca**: ese requirement documenta el rate limit y el captcha **del formulario de contacto**. El límite de C2 se aplica a la **ruta de despacho a Valet desde comentarios**, vive en `ValetConfig` con su propio contador y sus propios filtros, y no modifica el comportamiento del formulario de contacto descrito en `anti-abuse`.

### Compatibilidad y seguridad

- Sin rotura de contratos ni de opciones existentes. El campo nuevo del payload (`untrusted`) es aditivo; el consumidor actual de Valet ignora campos desconocidos.
- PHP 8.3, PSR12. La verificación de PSR12 en este change es **real** (ejecutada con evidencia), corrigiendo el falso «sin warnings» de la tarea 4.2 del change anterior.