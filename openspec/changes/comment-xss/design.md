# Design: Cierre del XSS almacenado en comentarios

## Context

El sitio sirve comentarios con un listado propio del tema (`atareao_comment_callback` en `wp-content/themes/atareao-theme/functions.php`) y con un envío por AJAX (`js/comment-ajax.js` + `CommentSecurity::processAjaxComment()`), además del flujo normal de `preprocess_comment` (`CommentSecurity::validateComment()`). El formulario de contacto (`ContactForm`) reutiliza el mismo esquema de captcha y anti-timing.

**Cadena de XSS almacenado (verificada).**

- Entrada: `class-comment-security.php:134` sanea el autor con `sanitize_text_field` (`$author = sanitize_text_field(wp_unslash($_POST['author']))`). `sanitize_text_field` **no decodifica entidades** y **no rechaza** una cadena que ya viene con `&lt;`/`&gt;`; el atacante envía `&lt;img src=x onerror=alert(1)&gt;` y se almacena **literal** (sin `<`).
- Salida: `functions.php:696` pinta `printf('<b class="fn">%s</b>', get_comment_author_link());` **sin escapar**. Al insertar la cadena en el HTML, el navegador decodifica las entidades y materializa un `<img onerror=…>`.
- Reinyección en JS: `js/comment-ajax.js:52` lee el nombre con `fn.textContent` (aquí ya está decodificado a `<img …>`), y `js/comment-ajax.js:71` lo reinyecta con `note.innerHTML = 'Respondiendo a ' + authorName + ' <button …>Cancelar</button>'`. El minificado `js/comment-ajax.min.js` contiene el mismo sink.
- Impacto: pulsa «Responder» quien modera; el `onerror` se ejecuta con su sesión (robo de cookies/nonce, acciones como admin).

**Defensas frágiles en el mismo frente.**

- `functions.php:246`: rama fallback del AJAX que imprime `get_comment_text($comment_obj)` sin escapar. Hoy neutralizada porque `processAjaxComment()` limpia el contenido con `sanitize_textarea_field` (`class-comment-security.php:136`), pero un cambio futuro en esa limpieza reabriría el XSS.
- `index.php:21-25`: `printf('<h1 …>…%s…</h1>', single_cat_title('', false))`, lo mismo para `single_tag_title` y `get_the_author()`, sin `esc_html`.
- `class-comment-security.php:66,115`: `$expected_sig = hash_hmac('sha256', $captcha_a . ':' . $captcha_b, wp_salt('nonce'))` firma solo `a:b`. La firma es **constante** para un par `a:b` dado y por tanto **reutilizable**.
- `class-comment-security.php:70-85` y `class-contact-form.php:57-89`: `form_time` **no entra en la firma** y en comentarios solo se comprueba la cota inferior (`($now - $form_time) < 2`). Manipular `form_time` hacia atrás anula toda la protección anti-timing. El formulario de contacto ya comprueba cota superior (`max_seconds = 3600`) pero **tampoco firma** `form_time`.

Restricciones del repo: **no existe framework de tests ni build tools**. La verificación es `just php-lint`, `just phpcs`, un arnés externo de stubs en `/tmp` (no versionado) y E2E manual. PSR12, PHP 8.3. Regla de separación: la funcionalidad/business logic va en el plugin; el tema es presentación. **No se renombra ni se borra ninguna opción ni hook** y el sitio público no cambia salvo el arreglo.

## Goals / Non-Goals

**Goals**

- Cerrar la cadena de XSS almacenado del autor del comentario en las tres capas (plantilla, rama fallback y JS de respuesta).
- Escapar también los `printf` de archivo de `index.php`.
- Endurecer la firma del captcha incluyendo `form_time`, con cota inferior y superior, en comentarios y en contacto.
- Dejar el contrato de regresión documentado (nombre `<img … onerror=…>` que no ejecuta nada al pulsar «Responder»).

**Non-Goals**

- No se cambia el contrato del formulario (campos, nombres, hooks ni opciones).
- No se renombra ni se borra ninguna opción ni hook.
- No se introduce un framework de tests, build tools ni dependencias.
- No se cambia el comportamiento visible del sitio salvo el arreglo (un comentario legítimo se sigue viendo y enviando igual).
- No se reescribe la sanitización de entrada del autor: se **añade** el escape de salida.
- No se reemplaza el `innerHTML` que inserta el `comment_html` **ya saneado** que devuelve el servidor (queda fuera del sink explotable).

## Decisions

### Decisión 1: Escapar en la salida en vez de confiar en el saneado de entrada

El arreglo principal es **escapar en el punto de salida** (`esc_html` para el texto del autor, `esc_url` para su URL, `wp_kses_post` para contenido), no depender de `sanitize_text_field` en la entrada.

**Motivo:** `sanitize_text_field` es un saneador de almacenamiento, no un escaper de contexto; no decodifica entidades ni neutraliza `&lt;script&gt;`. La única defensa correcta y estable es tratar todo dato no confiable como texto al construir el HTML. El escape en salida hace que `&lt;img …&gt;` se muestre como texto aunque llegue almacenado tal cual.

**Consecuencias:** los datos siguen entrando saneados (defensa en profundidad), pero la plantilla ya no confía en ello. Ningún comentario legítimo cambia de aspecto.

**Alternativa descartada:** decodificar/rechazar entidades en la entrada (`html_entity_decode` + `wp_strip_all_tags`). Destruiría nombres legítimos con entidades, rompería el dato almacenado y dejaría la salida igual de frágil ante cualquier otra vía de entrada (importaciones, API REST, WP-CLI).

### Decisión 2: `atareao_comment_callback` escapa nombre y URL; conserva el enlace del autor

El callback sustituye `printf('<b class="fn">%s</b>', get_comment_author_link())` por una construcción que escapa el nombre con `esc_html` y la URL con `esc_url`, manteniendo el `<b class="fn">` y el enlace cuando la URL es legítima.

**Consecuencias:** se cierra el `<b class="fn">` como sink y se conserva la semántica visual (nombre en negrita, enlazado a la web del autor si la hay).

**Alternativa descartada:** `get_comment_author_link()` con el filtro por defecto. Ese helper devuelve el nombre sin escapar y construye el `<a>` por su cuenta; no permite garantizar el escape de ambos campos en el punto exacto de impresión.

### Decisión 3: La rama fallback del AJAX escapa el contenido con `wp_kses_post`

`functions.php:246` pasa a `wp_kses_post(get_comment_text($comment_obj))` (o `esc_html` si se prefiere el contenido como texto plano), alineado con lo que WordPress permite en comentarios.

**Consecuencias:** la rama fallback deja de ser un sink aunque la limpieza de entrada cambie. `wp_kses_post` conserva el marcado permitido en comentarios (enlaces, cursivas, etc.) sin abrir XSS.

**Alternativa descartada:** `esc_html` sobre todo el contenido. Es más restrictivo de lo necesario y degradaría comentarios con enlaces permitidos; `wp_kses_post` es el escaper de contexto correcto para contenido de comentario.

### Decisión 4: `index.php` escapa los títulos de archivo con `esc_html`

Los `printf` de `single_cat_title`, `single_tag_title` y `get_the_author` se envuelven con `esc_html`, y `get_the_date` con `esc_html` si procede.

**Consecuencias:** se cierra el último `printf` sin escapar de la cabecera de archivo. Los términos y nombres no cambian para datos legítimos.

**Alternativa descartada:** confiar en que WP ya escapa esos helpers. `single_cat_title('', false)` devuelve el término sin escapar; el tercer argumento `$prefix` y el `printf` no escapan el valor.

### Decisión 5: La nota de «Responder» se construye con nodos de texto, no con `innerHTML`

`js/comment-ajax.js` reemplaza el sink por construcción DOM:

```
// antes (sink):
note.innerHTML = 'Respondiendo a ' + (authorName ? authorName : '') + ' <button …>Cancelar</button>';

// después (seguro):
note.textContent = '';
note.appendChild(document.createTextNode('Respondiendo a ' + (authorName ? authorName : '') + ' '));
var cancel = document.createElement('button');
cancel.type = 'button';
cancel.id = 'atareao-cancel-reply';
cancel.textContent = 'Cancelar';
note.appendChild(cancel);
```

Se conserva el `id` (`atareao-replying-note`), la clase, la posición (`respond.insertBefore(note, respond.firstChild)`) y el `addEventListener('click')` del botón, que sigue reseteando `comment_parent` a `0` y borrando la nota.

**Consecuencias:** un nombre con `<img … onerror=…>` se muestra como texto y jamás crea un elemento. El resto del comportamiento de «Responder» no cambia.

**Alternativa descartada:** escapar el nombre con una función de escape HTML propia en JS. Es frágil (fácil de equivocar) y sigue construyendo HTML por concatenación; con `textContent`/`createTextNode` no hay parser que pueda reinterpretar el dato.

### Decisión 6: Actualizar/regenerar `comment-ajax.min.js` a mano

Como el repo **no tiene build tools**, el minificado se actualiza a mano para que no quede el sink antiguo. La verificación comprueba que el minificado tampoco contiene `innerHTML="Respondiendo a "`.

**Consecuencias:** no hay deriva entre fuente y minificado. Es coherente con `main.min.js`, `navigation.min.js` y `share.min.js`, ya mantenidos manualmente.

**Alternativa descartada:** dejar el minificado sin tocar. El sitio carga el `.min.js` en producción, así que el arreglo no surtiría efecto.

### Decisión 7: La firma del captcha incluye `form_time` en sus tres puntos

`$expected_sig = hash_hmac('sha256', $captcha_a . ':' . $captcha_b . ':' . $form_time, wp_salt('nonce'))` en `validateComment()`, `processAjaxComment()` y `ContactForm`. El campo firmado viaja en el mismo formulario que `a:b` y se recalcula al emitir el captcha y en cada respuesta AJAX (`new_sig`).

**Consecuencias:** cambiar `form_time` (hacia atrás o hacia adelante) rompe la firma, así que la ventana temporal ya no se puede manipular sin pasar por el servidor. La firma deja de ser constante para un `a:b` dado.

**Alternativa descartada:** firmar solo con un secreto de sesión por formulario. Añadiría estado de sesión y fugas entre peticiones sin necesidad; incluir el propio `form_time` en el HMAC ya ata la ventana al captcha emitido.

### Decisión 8: Cota superior de `form_time` en comentarios (1 h)

Además de la cota inferior existente (`>= 2 s`), `validateComment()` y `processAjaxComment()` rechazan un `form_time` con más de `MAX_FORM_AGE = 3600` segundos de antigüedad, con un mensaje accionable («El formulario ha expirado. Recarga la página.»). El formulario de contacto ya usa `max_seconds = 3600`; se mantiene el mismo valor para coherencia.

**Consecuencias:** un formulario viejo o con `form_time` manipulado no se acepta. La ventana útil (2 s – 1 h) cubre de sobra un comentario humano y alinea ambos formularios.

**Alternativa descartada:** no añadir cota superior y confiar solo en la firma. Un `form_time` firmado por el propio servidor sigue pudiendo reutilizarse indefinidamente si el captcha no caduca; la cota acota la ventana de replay.

### Decisión 9: Sin cambios de contrato, hooks ni opciones

Los nombres de campos (`atareao_comment_captcha_a|b|sig|form_time`, `atareao_captcha_*` del contacto), los hooks (`preprocess_comment`, `wp_ajax_*`) y cualquier opción se conservan **palabra por palabra**. El arreglo solo altera el valor del HMAC y las comprobaciones de tiempo.

**Consecuencias:** no hay migración ni incompatibilidad; el formulario viejo (pestaña abierta antes del deploy) simplemente fallará la firma y pedirá recargar, como ya ocurría con cualquier rotación de `wp_salt`.

**Alternativa descartada:** renombrar campos o añadir uno nuevo (`form_time_sig`). Rompería plantillas y cachés sin aportar seguridad adicional.

### Decisión 10: Verificación sin framework: arnés externo + E2E

Al no existir framework de tests, la verificación combina `just php-lint`, `just phpcs`, un arnés externo de stubs en `/tmp/opencode/comment-xss-harness/` (no versionado, define las funciones WP usadas y simula DOM/HTML) y E2E manual en producción. El arnés comprueba el escape de salida, la ausencia del sink en fuente y minificado, la firma con tiempo y las cotas.

**Consecuencias:** el change queda verificable sin añadir infraestructura al repo ni al pipeline.

**Alternativa descartada:** añadir PHPUnit/vitest. El repo no tiene autoloader ni package manager y la convención es no introducirlos.

## Risks / Trade-offs

- **[Rotación de `wp_salt` invalida formularios abiertos]** → Al incluir el tiempo y usar `wp_salt('nonce')`, cualquier cambio de sales obliga a recargar. Es el comportamiento actual ante cambios de salt; el mensaje «Recarga la página» ya existe.
- **[`wp_kses_post` podría recortar marcado permitido]** → Se usa el mismo conjunto que WordPress aplica a comentarios; un comentario legítimo con enlaces/énfasis se conserva. Riesgo bajo y verificado por arnés.
- **[Deriva entre `comment-ajax.js` y su minificado]** → Mitigado con la Decisión 6 y una comprobación explícita en el arnés sobre `comment-ajax.min.js`.
- **[Falsos positivos de «demasiado rápido» o «expirado»]** → La ventana 2 s–3600 s es holgada; el mensaje es accionable. Riesgo aceptado.
- **[Otras entradas de comentario (REST, importadores, WP-CLI) no pasan por `validateComment`]** → El escape de salida cubre la impresión con independencia del origen; el endurecimiento del captcha solo afecta a los formularios públicos. Es el diseño correcto: la salida es la última línea de defensa.
- **[Verificación sin framework]** → No hay tests automatizados en el repo. Se cubre con análisis estático, arnés externo y E2E manual (Decisión 10).

## Migration Plan

Orden obligatorio:

1. **Tema (salida):** escapar autor/URL en `atareao_comment_callback`, `get_comment_text()` de la rama fallback y los `printf` de `index.php`.
2. **JS:** construir la nota con `textContent`/`createElement` y actualizar `comment-ajax.min.js` a mano.
3. **Plugin (captcha):** firmar `a:b:form_time` y añadir cota superior en `class-comment-security.php` (validación normal y AJAX) y en `class-contact-form.php`.
4. **Verificar** con `just php-lint`, `just phpcs` y el arnés externo.
5. **Desplegar** y hacer E2E manual en producción con un nombre de prueba `<img src=x onerror=…>`.

**Rollback:** revertir el commit. No hay migración de datos ni cambios de opción, así que el rollback es inmediato. La rotación de `wp_salt` no se toca.

## Verification

> El repositorio **no tiene framework de tests** ni build tools. La verificación combina análisis estático, un **arnés externo de stubs** que vive solo en `/tmp/opencode/comment-xss-harness/` (fuera del repo y no versionado) y E2E manual en producción. La implementación arranca **solo tras la aprobación del usuario**.

- **Lint**: `just php-lint` → 0 errores.
- **phpcs**: `just phpcs` (theme+plugin) sin empeorar el baseline. Objetivo de delta **+0 errores**.
- **Arnés externo de stubs** (`/tmp/opencode/comment-xss-harness/`, no versionado): define de forma controlable `esc_html`, `esc_url`, `wp_kses_post`, `get_comment_author*`, `get_comment_text`, `hash_hmac`, `wp_salt`, `single_cat_title`, etc., y comprueba:
  1. **Escape de salida del autor**: un nombre almacenado `&lt;img src=x onerror=alert(1)&gt;` produce `&lt;img …&gt;` en el HTML y **no** un `<img>` real; la URL se escapa.
  2. **Escape del fallback**: la rama fallback del AJAX no emite `get_comment_text()` sin sanear.
  3. **Escape de `index.php`**: los títulos de categoría/etiqueta/autor salen escapados.
  4. **Nota de respuesta**: `comment-ajax.js` y `comment-ajax.min.js` **no** contienen el sink `innerHTML = 'Respondiendo a '`; la nota usa `textContent` y el botón se crea con `createElement`.
  5. **Firma con tiempo**: cambiar `form_time` invalida la firma en `validateComment`, `processAjaxComment` y `ContactForm`; un `form_time` por debajo del mínimo o por encima del máximo se rechaza.
  6. **No-regresión**: un comentario legítimo (nombre y contenido normales) se sigue aceptando y mostrando igual.
- **E2E manual en producción**: publicar un comentario con nombre `&lt;img src=x onerror=alert(1)&gt;` y comprobar que (a) en el listado se ve el texto literal sin ejecutar nada, (b) al pulsar «Responder» no se ejecuta `alert` y el botón «Cancelar» funciona, (c) un formulario con `form_time` manipulado se rechaza.
- `openspec validate comment-xss --strict` sin hallazgos.

## Open Questions

Ninguna. El alcance del arreglo (escape en salida, nota sin `innerHTML`, firma con tiempo y cota superior, verificación con arnés + E2E) queda resuelto arriba.
