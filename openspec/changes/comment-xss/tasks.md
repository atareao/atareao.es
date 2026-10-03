# Tasks: Cierre del XSS almacenado en comentarios

> **Nota inicial:** el repositorio **no tiene framework de tests ni build tools**. La verificación del change combina análisis estático (`just php-lint`, `just phpcs`), un **arnés de stubs externo** que vive solo en `/tmp/opencode/comment-xss-harness/` (fuera del repo y no versionado) y E2E manual en producción. El arnés no forma parte del commit ni del árbol.

## 1. Phase 0 — Baseline y confirmación de la cadena

- [x] 1.1 Confirmar la cadena XSS línea a línea con evidencia `fichero:línea`: `class-comment-security.php:134` (`sanitize_text_field` almacena entidades literales), `functions.php:696` (autor sin escapar), `js/comment-ajax.js:52,71` (lectura y reinyección con `innerHTML`), `js/comment-ajax.min.js` (mismo sink). **Verificación:** cada eslabón citado existe en el fichero y la línea indicada; se anota en `design.md` §Context. **Evidencia:** arnés RED `TOTAL=12 PASS=1 FAIL=11`, exit 1 — fallan E1/E2 (autor), J1/J2 (sink `innerHTML` fuente y minificado), C1/C6 (firma sin tiempo); E3/E4 (fallback e `index.php`) también en rojo.
- [x] 1.2 Confirmar las defensas frágiles: `functions.php:246` (`get_comment_text()` sin escapar), `index.php:21-25` (`printf` sin `esc_html`), `class-comment-security.php:66,115` (firma HMAC solo `a:b`), `class-comment-security.php:70-85` y `class-contact-form.php:57-89` (`form_time` no firmado y sin cota superior en comentarios). **Verificación:** cada afirmación cita su línea. **Evidencia:** confirmadas las cuatro primeras (RED E3/E4/C1/C4 y C6) y remediadas en `3e1346d`/`42e073d`; la firma de `ContactForm` (`class-contact-form.php:63`, `atareao_form_time` no firmado) queda confirmada como pendiente, sin remediar (ver 4.3).
- [x] 1.3 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el par errores/warnings. **Evidencia:** baseline conocido `752 errores / 427 warnings`; `just php-lint` → 0 errores en 60 ficheros.

## 2. Arreglo del tema (salida escapada)

- [x] 2.1 Escapar nombre y URL del autor en `atareao_comment_callback` (`functions.php:696`): `esc_html` para el nombre y `esc_url` para la URL, conservando `<b class="fn">` y el enlace legítimo. **Verificación:** arnés — un nombre `&lt;img src=x onerror=…&gt;` sale escapado y no materializa un `<img>`. **Evidencia:** GREEN E1 `&amp;lt;img` presente y sin `<img src=x`; E2 conserva `href="https://legit.example/perfil"`; commit `3e1346d`.
- [x] 2.2 Escapar la rama fallback del AJAX (`functions.php:246`) con `wp_kses_post(get_comment_text($comment_obj))`. **Verificación:** arnés — la rama fallback no emite contenido sin sanear. **Evidencia:** GREEN E3 (textual `wp_kses_post(get_comment_text(`); commit `3e1346d`.
- [x] 2.3 Escapar los `printf` de `index.php:21-25` con `esc_html` (`single_cat_title`, `single_tag_title`, `get_the_author`). **Verificación:** arnés — los títulos de archivo salen escapados. **Evidencia:** GREEN E4 (los tres `printf` con `esc_html`); commit `3e1346d`.

## 3. Arreglo del JavaScript

- [x] 3.1 Eliminar el sink `innerHTML` de la nota de «Responder» en `js/comment-ajax.js:71`: construir con `textContent`/`createTextNode` para el nombre y `createElement('button')` (`type="button"`, `id="atareao-cancel-reply"`) para el botón, conservando posición y handler de cancelación. **Verificación:** arnés — la nota no usa `innerHTML`/`insertAdjacentHTML` y el botón sigue reseteando `comment_parent` a `0`. **Evidencia:** GREEN J1 (sin sink, con `createElement('button')` y `textContent`); commit `3e1346d`.
- [x] 3.2 Actualizar a mano `js/comment-ajax.min.js` para que no quede el sink antiguo (no hay build tools). **Verificación:** arnés — el minificado no contiene `innerHTML="Respondiendo a "`. **Evidencia:** GREEN J2 (sin `innerHTML="Respondiendo a "`, conserva `atareao-cancel-reply`); commit `3e1346d`.

## 4. Captcha firmado con el tiempo

- [x] 4.1 Firmar `a:b:form_time` en `CommentSecurity::validateComment()` (`class-comment-security.php:66`) y añadir la comprobación de cota superior (`> 3600 s`) junto a la inferior existente (`< 2 s`), con aviso accionable. **Verificación:** arnés — cambiar `form_time` invalida la firma; un `form_time` viejo o demasiado nuevo se rechaza. **Evidencia:** GREEN C1 (firma sin tiempo rechazada), C3 (demasiado rápido) y C4 (caducado >3600 s); RED los tenía en rojo; commit `42e073d`.
- [x] 4.2 Firmar `a:b:form_time` en `CommentSecurity::processAjaxComment()` (`class-comment-security.php:115`) y recalcular `new_sig` con el tiempo incluido; mantener la respuesta de cota inferior y superior. **Verificación:** arnés — el `new_sig` del AJAX firma el tiempo y una firma manipulada no pasa. **Evidencia:** GREEN C6 (`new_sig == HMAC(a:b:new_time)`) y C2 (firma válida con tiempo no redirige); RED C6 en rojo; commit `42e073d`.
- [ ] 4.3 Firmar `a:b:form_time` en `ContactForm` (`class-contact-form.php:63`), conservando su `$min_seconds = 3` y su `$max_seconds = 3600`. **Verificación:** arnés — el contacto rechaza un `form_time` manipulado. **Evidencia:** pendiente — fuera de alcance: la restricción de la tarea prohíbe tocar el plugin salvo `includes/class-comment-security.php`; `class-contact-form.php` queda intacto.
- [x] 4.4 Confirmar que no se renombran campos, hooks ni opciones (`atareao_comment_captcha_*`, `atareao_captcha_*`, `atareao_comment_form_time`, `preprocess_comment`, `wp_ajax_*`). **Verificación:** `rg` de los nombres antes/después sin diferencias. **Evidencia:** `rg` confirma `preprocess_comment` y `atareao_comment_captcha_{a,b,sig}` / `atareao_comment_form_time` intactos en `class-comment-security.php`, y `atareao_captcha_*` / `atareao_form_time` intactos en `class-contact-form.php`; commit `42e073d`.

## 5. Verificación con arnés externo

- [x] 5.1 Montar `/tmp/opencode/comment-xss-harness/` (no versionado) con stubs de `esc_html`, `esc_url`, `wp_kses_post`, `get_comment_author*`, `get_comment_text`, `hash_hmac`, `wp_salt`, `single_cat_title`, etc. **Verificación:** el arnés carga y ejecuta sin depender del repo. **Evidencia:** arnés en `/tmp/opencode/comment-xss-harness/` (`stubs.php`, `theme_stubs.php`, `run.php`, `run.sh`); ejecuta en contenedor y produce `TOTAL/PASS/FAIL` con exit code.
- [x] 5.2 Casos de escape: autor `&lt;img … onerror=…&gt;` en plantilla, rama fallback y títulos de `index.php`. **Verificación:** `PASS` en los tres y ausencia de `<img>` ejecutable en el HTML generado. **Evidencia:** GREEN E1/E2 (plantilla), E3 (fallback) y E4 (`index.php`).
- [x] 5.3 Casos de JS: fuente y minificado sin el sink `innerHTML`; botón creado con `createElement`. **Verificación:** `PASS`; el minificado no contiene la concatenación antigua. **Evidencia:** GREEN J1 (fuente) y J2 (minificado).
- [x] 5.4a Casos de captcha y timing en comentarios (normal y AJAX): firma con `form_time`, cota inferior y superior. **Verificación:** `PASS` en firma manipulada, demasiado rápido y caducado. **Evidencia:** GREEN C1/C2/C3/C4/C6; RED los tenía en rojo.
- [ ] 5.4b Caso del formulario de contacto. **Verificación:** `PASS` en firma manipulada del contacto. **Evidencia:** pendiente — `ContactForm` fuera de alcance (ver 4.3).
- [ ] 5.5 No-regresión: un comentario legítimo (nombre y contenido normales) se sigue aceptando y mostrando igual. **Verificación:** `PASS`. **Evidencia:** pendiente — no-regresión pública no validada (el arnés solo comprueba que una firma válida no redirige); se valida en E2E de producción (7.x).
- [x] 5.6 Resultado global del arnés. **Verificación:** `TOTAL=N PASS=N FAIL=0`, `exit=0`. **Evidencia:** RED `TOTAL=12 PASS=1 FAIL=11` (exit 1) → GREEN `TOTAL=12 PASS=12 FAIL=0` (exit 0).

## 6. Análisis estático

- [x] 6.1 `just php-lint` → 0 errores. **Evidencia:** 60 ficheros «No syntax errors detected».
- [x] 6.2 `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.3. **Evidencia:** `752 errores / 427 warnings` = baseline, delta `+0 errores / +0 warnings`.

## 7. E2E manual en producción

- [ ] 7.1 Publicar un comentario con nombre `&lt;img src=x onerror=alert(1)&gt;` y comprobar que en el listado se ve el texto literal sin ejecutar nada. **Evidencia:** pendiente — E2E en producción.
- [ ] 7.2 Pulsar «Responder» sobre ese comentario y comprobar que no se ejecuta `alert`, que la nota muestra el texto y que «Cancelar» funciona. **Evidencia:** pendiente — E2E en producción.
- [ ] 7.3 Enviar un formulario con `form_time` manipulado y comprobar que se rechaza; enviar un comentario legítimo y comprobar que se acepta y muestra igual. **Evidencia:** pendiente — E2E en producción.
- [ ] 7.4 Confirmar que el sitio público no cambia más allá del arreglo (formularios, campos y avisos intactos). **Evidencia:** pendiente — no-regresión pública en producción.

## 8. PR, archivado y entrega

- [ ] 8.1 PR por gitflow (`feature/comment-xss` → `development`) con commits convencionales en español y gitmoji. **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia:** pendiente — entrega.
- [x] 8.2 Spec. **Verificación:** `openspec validate comment-xss --strict` sin hallazgos. **Evidencia:** `openspec validate comment-xss --strict` → válido, 0 hallazgos.
- [ ] 8.3 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive comment-xss` crea `openspec/specs/comments/spec.md`; `openspec list` ya no muestra el change activo. **Evidencia:** pendiente — archivado no realizado.
