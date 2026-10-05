# Proposal: Endurecimiento antiabuso de la superficie pública (formularios y AJAX)

## Why

La auditoría de seguridad de formularios/AJAX (`/tmp/opencode/audit/formularios.md`, 2026-10-03) concluye que «los problemas son de **abuso/DoS y anti-automatización**»: los endpoints públicos aceptan envíos sin freno. Los hallazgos cubiertos por este change son:

1. **FR-01 (MEDIUM) — formulario de contacto sin rate limiting ni dedupe.** `ContactForm::handleSubmission()` (`wp-content/plugins/atareao-functionality/includes/class-contact-form.php:74-118`) valida nonce, honeypot, captcha y ventana temporal, pero **no limita el número de envíos**: el nonce es público y scrapeable, de modo que un bucle de POST con `atareao_form_time = time()-10`, `atareao_captcha_answer = a+b` y `contact_content` aleatorio provoca **bombeo ilimitado de mensajes al room Matrix** (llamada saliente `MatrixConfig::sendMatrixMessage()`, `class-contact-form.php:112`) y agotamiento de workers/DB. DoS de notificaciones a los administradores.
2. **FR-02 (MEDIUM) — contador de vistas con dedupe solo por cookie.** `handleTrackViewAjax()` (`wp-content/plugins/atareao-functionality/includes/class-metaboxes.php:581-613`) registra la acción `wp_ajax_nopriv_atareao_track_view` (`class-metaboxes.php:27`) con un nonce público (`functions.php:176`) y **deduplica únicamente por la cookie del cliente** (`class-metaboxes.php:603-611`). Un `curl` sin cookie incrementa `post_views_count` en cada petición, inflando el ranking «más vistos» (`post_views_count` es criterio de `orderby`) y amplificando escrituras en `postmeta`.
3. **FR-03 (LOW) — firma HMAC del captcha reutilizable.** La firma debía cubrir solo `a:b` (`class-comment-security.php:66,115`), sin el tiempo, permitiendo reutilizarla indefinidamente. **Estado verificado a fecha de este change:** el código actual ya firma `$a:$b:$form_time` (`class-comment-security.php:72,118,158` y `class-contact-form.php:62`) tras el change archivado `comment-xss` (commit `c498567`), que consolidó el requisito en `openspec/specs/comments/spec.md` («Captcha firmado con el tiempo y con cota superior»). Este change **formaliza ese contrato dentro del endurecimiento antiabuso y exige su verificación de no-regresión** en el formulario de contacto y en la ruta AJAX.
4. **FR-04 (LOW) — `form_time` controlado por el usuario y sin cota superior.** El instante del formulario lo envía el cliente y debía aceptarse cualquier valor pasado (`class-contact-form.php:57-89`, `class-comment-security.php:69-85`). **Estado verificado:** el código actual ya comprueba cota inferior y superior (2–3 s / 3600 s) y firma el tiempo. Este change lo fija como contrato de la capability antiabuso y exige la verificación.
5. **FR-08 (INFO) — captcha aritmético resoluble por bots.** Ambos operandos viajan al cliente en campos ocultos (`page-contact.php:49-51`, `class-comment-security.php:57-66`), por lo que el captcha es «puramente cosmético». No procede un cambio funcional: se **documenta la limitación** para que nadie lo considere el único control anti-bot.

La superficie afectada (formulario de contacto y AJAX de vistas) **no tiene capability propia** en `openspec/specs/`; el endurecimiento es transversal (restringir el abuso de endpoints públicos), por lo que se propone una **capability nueva `anti-abuse`** en lugar de un delta sobre capabilities ajenas. `comments` ya cubre FR-03/FR-04 para el flujo de comentarios; `anti-abuse` lo reafirma como parte del contrato antiabuso de la superficie pública y añade FR-01, FR-02 y FR-08, sin duplicar la implementación de comentarios.

## What Changes

- **Capability nueva `anti-abuse`** que fija el contrato de los controles antiabuso de la superficie pública (formulario de contacto, AJAX de conteo de vistas y captcha de comentarios), sin crear capacidades paralelas por cada endpoint.
- **Rate limiting server-side del formulario de contacto (FR-01).** Contador por **IP de origen** con ventana fija y un **tope de envíos por ventana** (ajustable por filtro/constante), sobre un transient de WordPress cuya clave sea un hash de la IP (sin guardar la dirección en claro). Al superar el tope, el sistema **no llama a `sendMatrixMessage()`** y responde con el mensaje de error accionable ya existente (redirección con `atareao_contact=error`), sin revelar información interna.
- **Dedupe/tope por ventana (FR-01).** Además del límite por IP, se acota el número de envíos por ventana para impedir el bombeo repetido desde una misma IP, manteniendo un uso legítimo holgado (varios mensajes al día por persona).
- **Dedupe server-side del contador de vistas (FR-02).** Además de la cookie existente (que se conserva), el sistema registra un **transient server-side por `post_id` + IP hasheada** con TTL; una segunda petición del mismo cliente sin cookie **no incrementa** `post_views_count` y responde `cached: true`. La cookie deja de ser el único control.
- **Acotado del endpoint de vistas (FR-02).** Solo se cuenta una vez por `post_id`/IP/ventana y se mantiene la validación de `post_id` ya existente; se conserva el contrato de la respuesta (`success`/`cached`/`views`).
- **Firma del captcha con el tiempo y ventana acotada (FR-03/FR-04).** El contrato exige que la firma HMAC cubra el instante del formulario y que se comprueben las cotas inferior y superior, tanto en `preprocess_comment`/AJAX de comentarios como en el formulario de contacto. El código actual ya lo cumple (commit `c498567`); este change lo fija y exige verificar que no haya regresión. Cambiar el tiempo invalida la firma; un `form_time` fuera de la ventana o manipulado se rechaza.
- **Documentación de la limitación del captcha (FR-08).** Se deja escrito que el captcha aritmético es **defensa en profundidad** (operandos en el cliente) y que los controles principales son el rate limiting, el dedupe, el honeypot y la moderación; **no hay cambio funcional**.
- **Sin tocar el sitio público.** No se renombran ni borran campos, hooks, nonces, opciones ni acciones AJAX. Se conservan `atareao_contact_form`, `atareao_contact_nonce`, los campos `atareao_captcha_*`/`atareao_form_time`, la acción `atareao_track_view` y su nonce, y el comportamiento visible del sitio. Lo único nuevo son los controles antiabuso.
- **Errores controlados y sin información interna.** Al exceder un límite se mantiene la respuesta de error ya existente del formulario de contacto; en el endpoint de vistas se conserva el contrato de respuesta. Nada de rutas, SQL, trazas ni identificadores internos.

## Capabilities

### New Capabilities

- `anti-abuse`: contrato de los controles antiabuso de la superficie pública — rate limiting y dedupe del formulario de contacto (FR-01), dedupe server-side del contador de vistas (FR-02), firma del captcha con el tiempo y ventana temporal acotada (FR-03/FR-04) y documentación de la limitación del captcha aritmético (FR-08) — sin cambiar campos, hooks, nonces, opciones ni acciones AJAX existentes.

### Modified Capabilities

Ninguna. FR-03/FR-04 ya están implementados y especificados en `comments` (change archivado `comment-xss`, commit `c498567`); no hay nada que modificar en esa spec, solo verificar su no-regresión, que se recoge como escenario de `anti-abuse`.

## Impact

- **Archivos a modificar (solo en la fase de implementación, tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-contact-form.php` (rate limiting/dedupe por IP+ventana antes de `sendMatrixMessage()`; conserva validaciones y mensajes actuales).
  - `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php` (dedupe server-side por `post_id`+IP en `handleTrackViewAjax()`; conserva la cookie y el contrato de respuesta).
  - Posible helper compartido en `wp-content/plugins/atareao-functionality/includes/` (por ejemplo `class-anti-abuse.php` o reutilización del patrón de ventana fija ya presente en `class-mcp.php:233-273`), con `require_once` en el bootstrap del plugin si se crea un fichero nuevo.
  - Nuevo (spec al archivar): `openspec/specs/anti-abuse/spec.md` a partir del delta `specs/anti-abuse/spec.md`.
- **Archivos que se verifican pero no deberían cambiar:** `includes/class-comment-security.php` y `wp-content/themes/atareao-theme/page-contact.php` (ya firman el tiempo y comprueban cotas; solo verificación de regresión). No se toca `functions.php`/`footer.php` (nonce de vistas) ni el contrato de la acción AJAX.
- **Contratos que NO se tocan:** acción `wp_ajax(_nopriv)_atareao_track_view` y su nonce `atareao_track_view_nonce`; `template_redirect` del contacto; `atareao_contact_form`/`atareao_contact_nonce`; los campos `atareao_captcha_a`/`_b`/`_sig`/`atareao_form_time` y `atareao_comment_captcha_*`; los mensajes de error visibles y las redirecciones actuales.
- **Compatibilidad:** un visitante legítimo puede enviar varios mensajes al día y ver una entrada repetidamente sin bloqueo perceptible; el endpoint de vistas sigue respondiendo con la misma forma y deja de inflarse desde `curl` sin cookie.
- **Dependencias:** ninguna nueva (transients, `wp_salt('nonce')`, `wp_rand`, `hash_equals`, funciones nativas de WordPress). PHP 8.3, PSR12, WordPress 6.0+.
- **No cambia:** el HTML y el comportamiento visible del sitio, el microsite `/tools/`, la analítica, el login/logout, las notificaciones Matrix (salvo que ahora se limitan) ni el flujo de comentarios.
