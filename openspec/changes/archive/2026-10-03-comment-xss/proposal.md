# Proposal: Cierre del XSS almacenado en comentarios

## Why

El sitio es vulnerable a un **XSS almacenado** explotable por un comentarista **anónimo**. La cadena, verificada línea a línea, es la siguiente:

1. El autor del comentario se sanea con `sanitize_text_field` en `wp-content/plugins/atareao-functionality/includes/class-comment-security.php:134`. Como el atacante envía entidades (`&lt;img src=x onerror=alert(1)&gt;`), la cadena **no contiene `<`** y `sanitize_text_field` **la almacena literal**.
2. El tema la imprime **sin escapar** en `wp-content/themes/atareao-theme/functions.php:696`: `printf('<b class="fn">%s</b>', get_comment_author_link());`. El navegador decodifica las entidades y **ejecuta el HTML/JS del atacante**.
3. El JS de «Responder» lee ese nombre con `fn.textContent` (`wp-content/themes/atareao-theme/js/comment-ajax.js:52`) y lo **reinyecta con `innerHTML`** en `js/comment-ajax.js:71` (y en su minificado `js/comment-ajax.min.js`): `note.innerHTML = 'Respondiendo a ' + authorName + ' <button …>Cancelar</button>'`.
4. Resultado: quien pulse «Responder» (por ejemplo, el moderador) **ejecuta el JS del atacante** con su sesión: robo de cookies/nonce y acciones como administrador.

En el mismo frente hay tres debilidades adicionales, ya verificadas:

- `functions.php:246`: `get_comment_text()` se imprime **sin escapar** en la rama fallback del AJAX. Hoy queda neutralizado porque el contenido sí se limpia con `sanitize_textarea_field`, pero es una defensa en profundidad frágil.
- `index.php:21-25`: `single_cat_title()` / `single_tag_title()` / `get_the_author()` se pasan a `printf` **sin `esc_html`**.
- `class-comment-security.php:66,115`: la firma HMAC del captcha cubre solo `a:b` (constante y **reutilizable una vez adivinada**); y `class-comment-security.php:70-85` (y `class-contact-form.php:57-89`): `form_time` **no va firmado** y solo se comprueba la cota inferior (`>=2`), de modo que la protección anti-timing se puede anular.

El objetivo es cerrar la cadena XSS **escapando en la salida**, eliminar el sink `innerHTML` de la nota de respuesta, endurecer la firma del captcha con el tiempo y dejar constancia de las pruebas de regresión, **sin cambiar el comportamiento del sitio público** más allá del arreglo y **sin renombrar ni borrar ninguna opción ni hook**.

## What Changes

- **Salida escapada en las plantillas del tema.** En `atareao_comment_callback` se escapa el nombre del autor (y su URL) en vez de confiar en el saneado de entrada; se escapa también el `get_comment_text()` de la rama fallback del AJAX (`functions.php:246`) con `wp_kses_post`, y los `printf` de `index.php:21-25` con `esc_html`.
- **Nota de respuesta segura en el JS.** Se elimina el sink `innerHTML` de la nota de «Responder»: el nombre se inserta con `textContent`/`createTextNode` y el botón «Cancelar» se crea con `createElement`. Se actualiza/regenera el fichero minificado `js/comment-ajax.min.js` (el repo **no tiene build tools**).
- **Captcha firmado con el tiempo.** La firma HMAC pasa de `a:b` a `a:b:form_time` en `CommentSecurity::validateComment()`, `CommentSecurity::processAjaxComment()` y `ContactForm`, y se valida **cota inferior y superior** de `form_time`.
- **Pruebas de regresión.** Queda documentado que hay que verificar que un comentario con nombre `&lt;img src=x onerror=…&gt;` no ejecuta nada al pulsar «Responder», en el arnés externo de stubs y en el E2E manual.
- **Capability nueva `comments`** que fija el contrato de: salida escapada del autor en las plantillas, construcción segura de la nota de respuesta, captcha firmado con el tiempo y sanitización del contenido.

## Capabilities

### New Capabilities

- `comments`: salida escapada del autor y del contenido en las plantillas del tema, construcción segura (sin `innerHTML`) de la nota de respuesta en el JavaScript, captcha firmado con el tiempo y con cota superior, y sanitización del contenido del comentario como defensa en profundidad.

### Modified Capabilities

- Ninguna. No se modifica el comportamiento observable de ninguna capability existente más allá del arreglo.

## Impact

- **Archivos modificados**:
  - `wp-content/themes/atareao-theme/functions.php` — escape del autor/URL en `atareao_comment_callback` (línea ~696) y del `get_comment_text()` de la rama fallback (línea ~246).
  - `wp-content/themes/atareao-theme/index.php` — `esc_html` en los `printf` de los `page-title` (líneas ~21-25).
  - `wp-content/themes/atareao-theme/js/comment-ajax.js` — nota de «Responder» sin `innerHTML`.
  - `wp-content/themes/atareao-theme/js/comment-ajax.min.js` — minificado actualizado a mano (sin build tools).
  - `wp-content/plugins/atareao-functionality/includes/class-comment-security.php` — firma con `form_time` y cota superior en `validateComment()` (líneas ~66, ~70-85) y `processAjaxComment()` (línea ~115).
  - `wp-content/plugins/atareao-functionality/includes/class-contact-form.php` — firma con `form_time` (línea ~63).
- **Nuevo (spec)**: `openspec/specs/comments/spec.md` vía el delta `specs/comments/spec.md`.
- **No cambia**: el sitio público salvo el arreglo; los formularios, los campos enviados, los hooks y los nombres de opción se conservan **palabra por palabra**. **No se renombra ni se borra ninguna opción ni hook.**
- **Sin nuevas dependencias** ni build tools: el minificado se edita a mano.
- **Compatibilidad**: PHP 8.3, PSR12, WordPress 6.0+.
