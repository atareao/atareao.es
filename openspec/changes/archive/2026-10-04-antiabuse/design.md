# Design: Endurecimiento antiabuso de la superficie pública (formularios y AJAX)

## Context

> Línea base caracterizada el 2026-10-03 sobre la rama `fix/antiabuse`. Las
> citas `fichero:línea` corresponden al estado del worktree en el momento de
> redactar este diseño (tras el GREEN; los números del formulario de contacto
> incorporan ya el rate limiting introducido por este change).

### Formulario de contacto

- **Hook:** `ContactForm::init()` registra `add_action('template_redirect', ...)`
  en `class-contact-form.php:22`. El método `handleSubmission()` arranca en
  `class-contact-form.php:28`.
- **Detección:** solo procesa `POST` (`class-contact-form.php:30`) y exige
  `is_page_template('page-contact.php')` o el marcador
  `atareao_contact_form` (`class-contact-form.php:33`).
- **Campos:** `contact_name_email`, `contact_content`, honeypot
  `atareao_website`, `atareao_captcha_answer/_a/_b/_sig` y `atareao_form_time`
  (`class-contact-form.php:42-57`).
- **Validaciones actuales:** nonce `atareao_contact_form`
  (`class-contact-form.php:81-82`), campos obligatorios (`:84`), email
  (`:86`), honeypot (`:88`), cota inferior del formulario
  (`:93`), cota superior (`:95`), firma HMAC que cubre `$a:$b:$form_time`
  (`:62`, `:97`) y captcha aritmético (`:98`), además de palabras clave
  (`:91`) y «solo un enlace» (`:102`).
- **Ausencia de rate limiting (antes del change):** no había ningún contador por
  IP ni dedupe; la ruta solo estaba acotada por nonce/captcha/ventana. La
  llamada saliente es `MatrixConfig::sendMatrixMessage()`
  (`class-contact-form.php:121`, invocada dentro del bloque válido iniciado en
  `:110`). El error accionable existente se emite por redirección con
  `atareao_contact=error` y `atareao_msg` (`class-contact-form.php:133-138`);
  el éxito redirige con `atareao_contact=success` (`class-contact-form.php:124`).

### Endpoint AJAX de conteo de vistas

- **Registro:** `add_action('wp_ajax_atareao_track_view', ...)` y
  `add_action('wp_ajax_nopriv_atareao_track_view', ...)` en
  `class-metaboxes.php:26-27`; el handler es
  `Metaboxes::handleTrackViewAjax()` (`class-metaboxes.php:581`).
- **Nonce público:** el tema localiza `atareao_track_view_nonce` en
  `functions.php:176` y lo envía desde `footer.php:245` (acción
  `atareao_track_view`).
- **Dedupe previo (solo cookie):** comprobaba únicamente la cookie
  `atareao_post_view_<post_id>` (`class-metaboxes.php:603`) y, si no existía,
  incrementaba `post_views_count` con `update_post_meta()` y fijaba la cookie
  de 12 h (`class-metaboxes.php:610-611`, ahora `:613-...`). Un `curl` sin
  cookie inflaba el contador en cada petición.

### FR-03 / FR-04 — firma y ventana del captcha (no-regresión)

- La firma HMAC ya incluye el tiempo en comentarios y contacto:
  `hash_hmac('sha256', $a . ':' . $b . ':' . $form_time, wp_salt('nonce'))`
  (`class-comment-security.php:78` en `validateComment()`,
  `:164` en `processAjaxComment()`, `class-contact-form.php:62`).
- `processAjaxComment()` reintenta la firma al devolver el reto:
  `class-comment-security.php:124`.
- Cotas comprobadas en ambos flujos: `(now - form_time) < 2` y
  `> 3600` en comentarios (`class-comment-security.php:88-91` y `:182-185`) y
  `< 3` / `> 3600` en contacto (`class-contact-form.php:93-95`).
- Estado: implementado por el change archivado `comment-xss` (commit
  `c498567`) y consolidado en `openspec/specs/comments/spec.md`. Este change
  **no lo modifica**; solo exige verificación de no-regresión (el arnés C1/C2
  lo confirma).

## Decisions

### FR-01 — Rate limiting del formulario de contacto

- Se añade un contador de **ventana fija** por IP de origen hasheada, con
  transients (`get_transient`/`set_transient`).
- Clave: `atareao_contact_rl_<sha256(ip|salt)>_<bucket>`, donde
  `<bucket> = floor(time() / ventana)` (`class-contact-form.php:173-178`). El
  bucket materializa la ventana fija sin depender del TTL al incrementar y la
  IP nunca se almacena en claro.
- Umbral ajustable: filtro `atareao_contact_rate_limit` o constante
  `ATAREAO_CONTACT_RATE_LIMIT` (por defecto 5); ventana con
  `atareao_contact_rate_window` / `ATAREAO_CONTACT_RATE_WINDOW` (3600 s)
  (`class-contact-form.php:149-166`).
- La comprobación precede a toda validación y a `sendMatrixMessage()`: al
  alcanzar el tope se responde con el error accionable ya existente
  (`class-contact-form.php:79-80`). El contador se incrementa **solo** cuando
  el envío es válido, justo antes de la llamada saliente
  (`class-contact-form.php:111`).
- La IP se toma **solo** de `$_SERVER['REMOTE_ADDR']`; no se consultan
  cabeceras de proxy como `X-Forwarded-For` (`class-contact-form.php:175`).

### FR-02 — Dedupe server-side del contador de vistas

- Además de la cookie existente (conservada) se registra un transient
  `atareao_view_seen_<post_id>_<sha256(ip|salt)>` con TTL de 12 h, la misma
  ventana que la cookie (`class-metaboxes.php:610-618`).
- Una segunda petición del mismo cliente dentro de la ventana responde
  `cached: true` con el valor actual y **no** incrementa `post_views_count`
  (`class-metaboxes.php:611-613`).
- Se conservan la validación de nonce, la de `post_id`, el saneado del valor y
  la forma de la respuesta (`success`, `cached`, `views`).

### FR-08 — Limitación del captcha aritmético

- **El captcha aritmético es solo defensa en profundidad.** Ambos operandos
  viajan al cliente en campos ocultos (`page-contact.php:49-51`,
  `class-comment-security.php:57-66`), por lo que un bot lo resuelve
  trivialmente. No es un control anti-bot robusto.
- Los controles que realmente frenan la automatización son el **rate limiting**,
  el **dedupe server-side**, el **honeypot** y la **moderación**.
- **Sin cambio funcional:** los campos `atareao_captcha_a`/`_b`/`_sig`,
  `atareao_form_time` y `atareao_comment_captcha_*`, la firma HMAC y su
  validación permanecen intactos. La limitación se documenta en el docblock de
  `CommentSecurity` (`class-comment-security.php:2-13`).

## Verification

- Arnés externo `/tmp/opencode/antiabuse-harness/` cubriendo A1-A5, B1-B5 y
  C1-C2: `TOTAL=30 PASS=30 FAIL=0` (exit 0). No versionado.
- `just php-lint`: 0 errores.
- `just phpcs --standard=PSR12` (theme + plugin): 752 errores / 429 warnings,
  delta **+0/+0** respecto al baseline medido.
- `openspec validate antiabuse --strict`: «Change 'antiabuse' is valid».
- `openspec/specs/` sin cambios.

## Risks / Trade-offs

- El contador read-then-write no es atómico; una ráfaga muy concurrente podría
  sobrepasar el tope por una unidad. Aceptable para un limitador de abuso.
- `X-Forwarded-For` no se usa aunque el sitio esté detrás de nginx: si algún día
  se necesita, deberá hacerse con una configuración explícita (lista de proxies
  de confianza), no confiando ciegamente en la cabecera.
- Coordinar rate limiting con la caché de nginx queda fuera de este change
  (no se toca nginx).
