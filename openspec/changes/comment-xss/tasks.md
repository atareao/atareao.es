# Tasks: Cierre del XSS almacenado en comentarios

> **Nota inicial:** el repositorio **no tiene framework de tests ni build tools**. La verificación del change combina análisis estático (`just php-lint`, `just phpcs`), un **arnés de stubs externo** que vive solo en `/tmp/opencode/comment-xss-harness/` (fuera del repo y no versionado) y E2E manual en producción. El arnés no forma parte del commit ni del árbol. **Este change es solo documentación: no se escribe ni se modifica código `wp-content/**` hasta la aprobación del usuario.**

## 1. Phase 0 — Baseline y confirmación de la cadena

- [ ] 1.1 Confirmar la cadena XSS línea a línea con evidencia `fichero:línea`: `class-comment-security.php:134` (`sanitize_text_field` almacena entidades literales), `functions.php:696` (autor sin escapar), `js/comment-ajax.js:52,71` (lectura y reinyección con `innerHTML`), `js/comment-ajax.min.js` (mismo sink). **Verificación:** cada eslabón citado existe en el fichero y la línea indicada; se anota en `design.md` §Context. **Evidencia:** pendiente.
- [ ] 1.2 Confirmar las defensas frágiles: `functions.php:246` (`get_comment_text()` sin escapar), `index.php:21-25` (`printf` sin `esc_html`), `class-comment-security.php:66,115` (firma HMAC solo `a:b`), `class-comment-security.php:70-85` y `class-contact-form.php:57-89` (`form_time` no firmado y sin cota superior en comentarios). **Verificación:** cada afirmación cita su línea. **Evidencia:** pendiente.
- [ ] 1.3 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el par errores/warnings. **Evidencia:** pendiente.

## 2. Arreglo del tema (salida escapada)

- [ ] 2.1 Escapar nombre y URL del autor en `atareao_comment_callback` (`functions.php:696`): `esc_html` para el nombre y `esc_url` para la URL, conservando `<b class="fn">` y el enlace legítimo. **Verificación:** arnés — un nombre `&lt;img src=x onerror=…&gt;` sale escapado y no materializa un `<img>`. **Evidencia:** pendiente.
- [ ] 2.2 Escapar la rama fallback del AJAX (`functions.php:246`) con `wp_kses_post(get_comment_text($comment_obj))`. **Verificación:** arnés — la rama fallback no emite contenido sin sanear. **Evidencia:** pendiente.
- [ ] 2.3 Escapar los `printf` de `index.php:21-25` con `esc_html` (`single_cat_title`, `single_tag_title`, `get_the_author`). **Verificación:** arnés — los títulos de archivo salen escapados. **Evidencia:** pendiente.

## 3. Arreglo del JavaScript

- [ ] 3.1 Eliminar el sink `innerHTML` de la nota de «Responder» en `js/comment-ajax.js:71`: construir con `textContent`/`createTextNode` para el nombre y `createElement('button')` (`type="button"`, `id="atareao-cancel-reply"`) para el botón, conservando posición y handler de cancelación. **Verificación:** arnés — la nota no usa `innerHTML`/`insertAdjacentHTML` y el botón sigue reseteando `comment_parent` a `0`. **Evidencia:** pendiente.
- [ ] 3.2 Actualizar a mano `js/comment-ajax.min.js` para que no quede el sink antiguo (no hay build tools). **Verificación:** arnés — el minificado no contiene `innerHTML="Respondiendo a "`. **Evidencia:** pendiente.

## 4. Captcha firmado con el tiempo

- [ ] 4.1 Firmar `a:b:form_time` en `CommentSecurity::validateComment()` (`class-comment-security.php:66`) y añadir la comprobación de cota superior (`> 3600 s`) junto a la inferior existente (`< 2 s`), con aviso accionable. **Verificación:** arnés — cambiar `form_time` invalida la firma; un `form_time` viejo o demasiado nuevo se rechaza. **Evidencia:** pendiente.
- [ ] 4.2 Firmar `a:b:form_time` en `CommentSecurity::processAjaxComment()` (`class-comment-security.php:115`) y recalcular `new_sig` con el tiempo incluido; mantener la respuesta de cota inferior y superior. **Verificación:** arnés — el `new_sig` del AJAX firma el tiempo y una firma manipulada no pasa. **Evidencia:** pendiente.
- [ ] 4.3 Firmar `a:b:form_time` en `ContactForm` (`class-contact-form.php:63`), conservando su `$min_seconds = 3` y su `$max_seconds = 3600`. **Verificación:** arnés — el contacto rechaza un `form_time` manipulado. **Evidencia:** pendiente.
- [ ] 4.4 Confirmar que no se renombran campos, hooks ni opciones (`atareao_comment_captcha_*`, `atareao_captcha_*`, `atareao_comment_form_time`, `preprocess_comment`, `wp_ajax_*`). **Verificación:** `rg` de los nombres antes/después sin diferencias. **Evidencia:** pendiente.

## 5. Verificación con arnés externo

- [ ] 5.1 Montar `/tmp/opencode/comment-xss-harness/` (no versionado) con stubs de `esc_html`, `esc_url`, `wp_kses_post`, `get_comment_author*`, `get_comment_text`, `hash_hmac`, `wp_salt`, `single_cat_title`, etc. **Verificación:** el arnés carga y ejecuta sin depender del repo. **Evidencia:** pendiente.
- [ ] 5.2 Casos de escape: autor `&lt;img … onerror=…&gt;` en plantilla, rama fallback y títulos de `index.php`. **Verificación:** `PASS` en los tres y ausencia de `<img>` ejecutable en el HTML generado. **Evidencia:** pendiente.
- [ ] 5.3 Casos de JS: fuente y minificado sin el sink `innerHTML`; botón creado con `createElement`. **Verificación:** `PASS`; el minificado no contiene la concatenación antigua. **Evidencia:** pendiente.
- [ ] 5.4 Casos de captcha y timing: firma con `form_time` en comentarios (normal y AJAX) y contacto; cota inferior y superior. **Verificación:** `PASS` en firma manipulada, demasiado rápido y caducado. **Evidencia:** pendiente.
- [ ] 5.5 No-regresión: un comentario legítimo (nombre y contenido normales) se sigue aceptando y mostrando igual. **Verificación:** `PASS`. **Evidencia:** pendiente.
- [ ] 5.6 Resultado global del arnés. **Verificación:** `TOTAL=N PASS=N FAIL=0`, `exit=0`. **Evidencia:** pendiente.

## 6. Análisis estático

- [ ] 6.1 `just php-lint` → 0 errores. **Evidencia:** pendiente.
- [ ] 6.2 `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.3. **Evidencia:** pendiente.

## 7. E2E manual en producción

- [ ] 7.1 Publicar un comentario con nombre `&lt;img src=x onerror=alert(1)&gt;` y comprobar que en el listado se ve el texto literal sin ejecutar nada. **Evidencia:** pendiente.
- [ ] 7.2 Pulsar «Responder» sobre ese comentario y comprobar que no se ejecuta `alert`, que la nota muestra el texto y que «Cancelar» funciona. **Evidencia:** pendiente.
- [ ] 7.3 Enviar un formulario con `form_time` manipulado y comprobar que se rechaza; enviar un comentario legítimo y comprobar que se acepta y muestra igual. **Evidencia:** pendiente.
- [ ] 7.4 Confirmar que el sitio público no cambia más allá del arreglo (formularios, campos y avisos intactos). **Evidencia:** pendiente.

## 8. PR, archivado y entrega

- [ ] 8.1 PR por gitflow (`feature/comment-xss` → `development`) con commits convencionales en español y gitmoji. **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia:** pendiente.
- [ ] 8.2 Spec. **Verificación:** `openspec validate comment-xss --strict` sin hallazgos. **Evidencia:** pendiente.
- [ ] 8.3 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive comment-xss` crea `openspec/specs/comments/spec.md`; `openspec list` ya no muestra el change activo. **Evidencia:** pendiente.
