# Proposal: Challenge de formulario renovable (compatible con caché HTML larga)

## Why

El HTML de las páginas se sirve desde una caché con TTL largo y embute un *challenge* antiabuso (`form_time` + captcha `a`/`b`/`sig` + nonce) generado en el servidor. Si la página cacheada supera la cota superior de `form_time` (3600 s) —se han observado copias de ~12 h—, los formularios de contacto y de comentarios responden «El formulario ha expirado» aunque el usuario acaba de abrir la página. La única salida hasta ahora era bajar el TTL de caché, lo que penaliza el rendimiento. Este change aplica la solución de libro: emitir el challenge desde un endpoint **no cacheable** y refrescarlo por JavaScript al cargar, de modo que la caché de HTML pueda mantener un TTL largo sin romper los formularios.

## What Changes

- **Endpoint público de challenge.** Se añade una acción AJAX pública (`atareao_form_challenge`, vía `POST` a `admin-ajax.php`, para `contact` y `comment`) que emite un challenge fresco: el instante, los operandos `a`/`b`, la firma HMAC y el nonce correspondiente. El endpoint SHALL enviar cabeceras de no-caché y SHALL NOT depender del documento cacheado.
- **Refresco en cliente.** Nuevo script del tema `js/form-challenge.js` (+ `.min.js`, sin build tools) que, al cargar, solicita el challenge y sobrescribe los campos ocultos (`atareao_form_time`, `atareao_captcha_a`/`_b`/`_sig`, `atareao_comment_form_time`, `atareao_comment_captcha_a`/`_b`/`_sig`) y el nonce correspondiente (`atareao_contact_nonce`, o el `nonce` de la acción AJAX de comentarios), además de la etiqueta del captcha.
- **Fallback sin JavaScript.** Se conserva el challenge renderizado en servidor; en páginas cacheadas más de la cota puede seguir caducando (limitación documentada).
- **Sin cambios de contrato.** Nombres de campos, hooks, nonces, cotas (`min`/`max`) y la validación server-side no cambian; el endpoint refleja la misma forma de challenge.

## Capabilities

### New Capabilities

- Ninguna.

### Modified Capabilities

- `anti-abuse`: se añade un requisito de challenge de formulario renovable sin recargar, compatible con una caché de HTML cuyo TTL supere la cota superior de `form_time`.

## Fuera de alcance

- **Desactivar el captcha.** El captcha aritmético sigue siendo defensa en profundidad (capability `anti-abuse`), con sus campos y validación intactos.
- **Caché de la capa de delante.** La directiva para excluir `/contactar` en la caché de delante es configuración de servidor y se entrega aparte (no es código de este repo).
- **Nonce en la ruta no-AJAX de comentarios.** No se añade verificación de nonce a `validateComment()`; solo se refrescan los nonces ya existentes.
- **Rate limiting del propio endpoint.** No se añade un límite específico al endpoint de challenge (es barato y no revela secretos); se puede endurecer en un change posterior.

## Impact

- **Archivos a modificar (solo tras aprobación, en la fase TDD):**
  - `wp-content/plugins/atareao-functionality/includes/class-contact-form.php` — emisión del challenge de contacto (reutilizable) y registro de la acción.
  - `wp-content/plugins/atareao-functionality/includes/class-comment-security.php` — generación del challenge de comentarios (ya existe) y registro de la acción.
  - `wp-content/plugins/atareao-functionality/atareao-functionality.php` — `require_once` del handler nuevo si se crea clase aparte.
  - `wp-content/themes/atareao-theme/functions.php` — `wp_enqueue_script` + `wp_localize_script` del script de challenge (contexto `contact`/`comment`).
  - `wp-content/themes/atareao-theme/js/form-challenge.js` y `form-challenge.min.js` — nuevos.
- **Nuevas specs al archivar:** ninguna; se actualiza `openspec/specs/anti-abuse/spec.md`.
- **Contratos que NO se tocan:** campos `atareao_form_time`, `atareao_captcha_a/_b/_sig`, `atareao_comment_form_time`, `atareao_comment_captcha_a/_b/_sig`, `atareao_contact_nonce`, `atareao_comment_nonce`; hooks y acciones AJAX existentes (`atareao_submit_comment`); cotas y firma HMAC.
- **Verificación:** sin framework de tests; arnés externo de stubs para el endpoint (`/tmp/opencode/`, no versionado), `just php-lint` + `just phpcs` (delta +0) y E2E con navegador (página simulada como cacheada → el JS refresca y el envío pasa).
