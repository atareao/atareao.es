# Design: Challenge de formulario renovable

## Context

- Contacto: `page-contact.php` renderiza en servidor `atareao_form_time`, `atareao_captcha_a/_b/_sig` (`hash_hmac('sha256', a:b:time, wp_salt('nonce'))`) y `wp_nonce_field('atareao_contact_form','atareao_contact_nonce')`. Validación en `ContactForm::handleSubmission()` con cota `[3, 3600]` s.
- Comentarios: `comments.php` renderiza `atareao_comment_form_time` y `atareao_comment_captcha_a/_b/_sig`; el envío en cliente lo hace `js/comment-ajax.js` a `admin-ajax.php?action=atareao_submit_comment` con `nonce` = `wp_create_nonce('atareao_comment_nonce')`. Validación en `CommentSecurity::processAjaxComment()` (y `validateComment()` en la ruta no-AJAX), cotas `[2, 3600]` s.
- El challenge de comentarios ya se regenera tras cada envío AJAX (`new_a/new_b/new_sig/new_time`), pero **no** al cargar una página cacheada.
- Producción: caché de HTML con antigüedad observada de ~12 h → `form_time` caducado en páginas cacheadas.

## Decision

**Endpoint AJAX público, por POST, no cacheable.**

- Acción: `atareao_form_challenge` (registrada en `wp_ajax_atareao_form_challenge` y `wp_ajax_nopriv_atareao_form_challenge`).
- Vía: `POST` a `admin-ajax.php` (mismo patrón que el resto del tema). **Por qué POST:** evita que una caché de delante (proxy) cachee la respuesta por método/URL. Alternativa REST `GET` descartada por cacheable.
- Parámetro `context` ∈ `contact` | `comment`.
- Respuesta (`wp_send_json_success`): `data = { context, time, a, b, sig, nonce }`, donde:
  - `time = time()`, `a`/`b` aleatorios en `[1,9]`, `sig = hash_hmac('sha256', a:b:time, wp_salt('nonce'))` (idéntico al render).
  - `nonce` = `wp_create_nonce('atareao_contact_form')` para `contact`; `wp_create_nonce('atareao_comment_nonce')` para `comment`.
- El handler llama a `nocache_headers()` (y no toca ninguna caché), de modo que `admin-ajax.php` no se cachea aunque el proxy sea agresivo.

**Refresco en cliente.**

- Nuevo `js/form-challenge.js` (+ `.min.js`; el repo no tiene build tools, así que el minificado se edita a mano igual que `comment-ajax.min.js`).
- Localización `atareao_form_challenge = { ajax_url, context, action: 'atareao_form_challenge' }` (contexto decidido en el servidor: `contact` en `page-contact.php`; `comment` en singular con comentarios abiertos).
- En `DOMContentLoaded`: localizar el formulario, hacer `fetch` POST, y en éxito sobrescribir los campos ocultos + la etiqueta del captcha; para `comment`, además actualizar `window.atareao_ajax.nonce` para que el siguiente envío AJAX use el nonce fresco.
- En error de red: no hacer nada (queda el challenge del servidor como fallback).

## Security notes

- El endpoint no revela nada nuevo: `a` y `b` ya viajan en el HTML público. Emitir el challenge a cualquiera no aporta ventaja al abuso; los controles reales (rate limiting, dedupe, honeypot, moderación) no cambian.
- No se añade rate limit al endpoint en este change (barato, sin secretos); anotado como endurecimiento futuro.
- Se conserva `hash_hmac` con `wp_salt('nonce')` y el mismo formato `a:b:time`, por lo que el challenge emitido valida con el código actual sin tocarlo.

## Alternatives considered

- **Bajar el TTL de caché** (parche operativo): mitiga pero penaliza rendimiento y no elimina el fallo; se mantiene como configuración de servidor, no como solución.
- **Emitir el challenge en cada request con `Cache-Control` en la página**: incompatible con cachear el HTML.
- **Refresco solo por tiempo (setInterval)**: no cubre la primera apertura de una página cacheada; se refresca al cargar.

## Verification

- Arnés externo de stubs (no versionado): el handler emite `a`,`b`,`time`,`sig`,`nonce`, la firma verifica con `hash_hmac('sha256', a:b:time, salt)` y las cabeceras de no-caché están presentes; contexts inválidos se rechazan.
- `just php-lint` (0), `just phpcs` (delta +0).
- E2E navegador: guardar el HTML, simular caché (usar HTML con `form_time` antiguo) y comprobar que el JS rellena un challenge fresco y que el envío de contacto (captcha correcto) llega a `atareao_contact=success` sin tocar Matrix en las pruebas de fallo.
