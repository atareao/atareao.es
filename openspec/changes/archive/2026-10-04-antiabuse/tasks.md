# Tasks: Endurecimiento antiabuso de la superficie pública (formularios y AJAX)

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación del change combina análisis estático (`just php-lint`, `just phpcs` con baseline **752 errores / 429 warnings**, objetivo delta **+0**), un **arnés de stubs externo** que vive solo en `/tmp/opencode/antiabuse-harness/` (fuera del repo y no versionado) y E2E manual del usuario contra el sitio. El arnés no forma parte del commit ni del árbol. La implementación arranca **solo tras la aprobación del usuario**. Ninguna tarea renombra ni borra campos, hooks, nonces, opciones ni acciones AJAX. Objetivo rector: frenar el abuso automatizado de la superficie pública sin cambiar el comportamiento visible del sitio.
>
> **Estado de ejecución (2026-10-03, rama `fix/antiabuse`):** implementación GREEN completada para FR-01 (rate limiting del contacto) y FR-02 (dedupe server-side de vistas); FR-03/FR-04 verificados sin cambio; FR-08 documentado en `design.md` y en el docblock de `CommentSecurity`. Baseline estático medido: **752 errores / 429 warnings** (la nota original estimaba 427 warnings). Arnés externo: **TOTAL=30 PASS=30 FAIL=0** (exit 0). Las tareas 7.x (E2E manual), 8.2 (PR) y 8.3 (archive) quedan pendientes por depender del sitio en marcha o por instrucción explícita (no push/PR/merge en este trabajo).

## 1. Phase 0 — Línea base y caracterización

- [x] 1.1 Caracterizar el formulario de contacto actual en `design.md` §Context: hook `template_redirect`, validaciones (nonce, honeypot, email, ventana temporal, firma, captcha, palabras clave, solo-enlace), ausencia de rate limiting y llamada saliente a `MatrixConfig::sendMatrixMessage()`, con evidencia fichero:línea de `class-contact-form.php`. **Evidencia:** `design.md` §Context con citas `class-contact-form.php:22,28,30,33,42-57,62,81-102,110-124,133-138` (los números incorporan ya el GREEN). `openspec validate antiabuse --strict` válido.
- [x] 1.2 Caracterizar el endpoint de vistas `wp_ajax(_nopriv)_atareao_track_view` en `design.md` §Context: registro del hook, nonce público localizado en el tema, dedupe solo por cookie y escritura directa de `post_views_count`, con evidencia fichero:línea. **Evidencia:** `design.md` §Context con citas `class-metaboxes.php:26-27,581,603,610-613` y `functions.php:176`/`footer.php:245`.
- [x] 1.3 Caracterizar el estado actual de FR-03/FR-04: la firma HMAC ya incluye `form_time` y ya se comprueban cota inferior y superior en comentarios y contacto (efecto del change archivado `comment-xss`, commit `c498567`); dejar constancia de que este change solo exige verificación de no-regresión. **Evidencia:** `design.md` §Context con citas `class-comment-security.php:78,88-91,124,164,182-185` y `class-contact-form.php:62,93-95`; arnés C1/C2 en PASS.
- [x] 1.4 Fijar el baseline PSR12 antes de tocar nada. **Evidencia (2026-10-03):** `just php-lint` → 0 errores; `phpcs --standard=PSR12 --report=summary` (theme+plugin) → **752 errores / 429 warnings** (la nota original decía 427; se toma el valor medido).
- [x] 1.5 Registrar en el arnés externo los stubs mínimos de WordPress (`get_transient`, `set_transient`, `delete_transient`, `wp_salt`, `hash_hmac` en la imagen de PHP, `wp_verify_nonce`, `wp_create_nonce`, `update_post_meta`, `get_post_meta`, `get_post`, `wp_send_json_success`, `wp_send_json_error`, `setcookie`, `wp_safe_redirect` y `MatrixConfig::sendMatrixMessage` como doble con contador) con contadores de llamadas. **Evidencia:** `/tmp/opencode/antiabuse-harness/{stubs.php,run.php,run.sh}`; RED inicial `TOTAL=30 PASS=20 FAIL=10` (fallan A2-A4/B1-B4), GREEN `TOTAL=30 PASS=30 FAIL=0`, exit 0. No versionado.

## 2. Rate limiting y dedupe del formulario de contacto (FR-01)

- [x] 2.1 Implementar un contador de **ventana fija** por IP hasheada con transients (`get_transient`/`set_transient`), TTL igual a la ventana y **tope de envíos ajustable** por filtro/constante, aplicado **antes** de `sendMatrixMessage()`. **Evidencia:** `class-contact-form.php:65-67,79-80,111,149-178`; arnés A1 (`matrixCalls=1` bajo el tope; `matrixCalls=0` al superarlo) y A2/A4; `rg` confirma `hash('sha256', $ip . '|' . wp_salt('nonce'))`.
- [x] 2.2 Comprobar que el tope no estorba el uso legítimo (varios mensajes al día) y que el valor es ajustable sin editar el cuerpo del código. **Evidencia:** arnés A2 — con `tope=1` pasa el primero y se bloquea el segundo; con `tope=2` cambia el umbral; filtro `atareao_contact_rate_limit` (`class-contact-form.php:149-153`).
- [x] 2.3 No confiar en cabeceras de proxy (`X-Forwarded-For` u otras) para determinar la IP salvo configuración explícita y no almacenar la IP en claro. **Evidencia:** arnés A3 — variar `HTTP_X_FORWARDED_FOR` con el mismo `REMOTE_ADDR` sigue bloqueado y la clave del transient no contiene la IP; `class-contact-form.php:173-178`.
- [x] 2.4 Al superar el límite, responder con el error accionable ya existente (`atareao_contact=error` + `atareao_msg`) y **no** enviar a Matrix ni revelar información interna. **Evidencia:** arnés A4 (`matrixCalls=1` tras la primera y sin incremento al bloquear, redirección `atareao_contact=error`, sin cadenas internas); `class-contact-form.php:79-80,133-138`.
- [x] 2.5 Conservar nonce, campos, honeypot, mensajes de error y redirección actuales; no cambiar el contrato del formulario. **Evidencia:** `rg` conserva `atareao_contact_form`, `atareao_contact_nonce`, `atareao_captcha_a/_b/_sig`, `atareao_form_time` y `atareao_contact=success|error`; arnés A5 (envío válido sigue redirigiendo a `success`).

## 3. Dedupe server-side del contador de vistas (FR-02)

- [x] 3.1 Registrar un transient server-side por `post_id` + **IP hasheada** con TTL de ventana en `handleTrackViewAjax()`, además de la cookie existente (que se conserva). **Evidencia:** `class-metaboxes.php:610-618`; arnés B1 (transient con clave hasheada, cookie `atareao_post_view_42` intacta, sin IP en claro).
- [x] 3.2 No incrementar `post_views_count` en una segunda petición del mismo cliente dentro de la ventana, aunque no envíe cookie; responder `cached: true` con el valor actual. **Evidencia:** arnés B2 (`views` igual antes/después, `cached=true`); `class-metaboxes.php:611-613`.
- [x] 3.3 Mantener el incremento para otra IP distinta y reanudarlo cuando la ventana caduque. **Evidencia:** arnés B3/B4 (`views` +1 para otra IP; +1 tras `delete_transient`); `class-metaboxes.php:615-618`.
- [x] 3.4 Acotar el endpoint: como máximo un incremento por `post_id`/IP/ventana; conservar la validación de `post_id`, el nonce y la forma de la respuesta (`success`, `cached`, `views`). **Evidencia:** arnés B5 (nonce y `post_id` inválidos no escriben; claves de respuesta conservadas); `class-metaboxes.php:583-595,603-613`.

## 4. Firma del captcha y ventana temporal acotada (FR-03/FR-04)

- [x] 4.1 Verificar (sin cambiar comportamiento) que la firma HMAC del captcha cubre `$a:$b:$form_time` en `validateComment()`, `processAjaxComment()` y `ContactForm`, y que se comprueban cota inferior y superior. **Evidencia:** arnés C1a (`processAjaxComment` firma `a:b:tiempo`), C1b (cambiar solo el tiempo invalida la firma), C1c/C1d (cotas inferior/superior), C1e (ruta AJAX); `class-comment-security.php:78,88-91,124,164,182-185`, `class-contact-form.php:62,93-95`.
- [x] 4.2 Verificar la no-regresión respecto a la capability `comments`: el endurecimiento ya archivado (commit `c498567`) sigue vigente y este change no lo altera. **Evidencia:** `rg` conserva `hash_hmac(...' . $form_time ...)` y `(now - form_time) < 2` / `> 3600`; `openspec/specs/comments/spec.md` sin cambios (`git status` limpio en `openspec/specs/`).
- [x] 4.3 En el formulario de contacto, un `form_time` manipulado o fuera de ventana impide la llamada a `sendMatrixMessage()`. **Evidencia:** arnés C2a (firma con tiempo cambiado → `matrixCalls=0`), C2b (caducado → 0), C2c (demasiado reciente → 0).

## 5. Documentación de la limitación del captcha (FR-08)

- [ ] 5.1 Documentar que el captcha aritmético es **defensa en profundidad** (operandos enviados al cliente) y que los controles principales son rate limiting, dedupe server-side, honeypot y moderación, sin cambio funcional. **Parcial:** documentado en `design.md` §FR-08 y en el docblock de `CommentSecurity` (`class-comment-security.php:2-13`). **Pendiente:** la sección equivalente en `README.md` del plugin (fuera del alcance de ficheros asignado a este trabajo).
- [x] 5.2 Confirmar que no hay cambio funcional en el captcha: campos, firma y validación intactos. **Evidencia:** `rg` conserva `atareao_captcha_a`/`_b`/`_sig`, `atareao_form_time` y `atareao_comment_captcha_*`; arnés C1/C2 sin alterar el contrato.

## 6. Verificación

- [x] 6.1 Análisis estático. **Evidencia:** `just php-lint` → 0 errores; `phpcs --standard=PSR12 --report=summary` (theme+plugin) → **752 errores / 429 warnings**, delta **+0/+0** respecto al baseline de 1.4.
- [x] 6.2 Arnés externo completo. **Evidencia:** `/tmp/opencode/antiabuse-harness/` → `TOTAL=30 PASS=30 FAIL=0`, `exit=0`; cubre A1-A5, B1-B5 y C1-C2. No versionado.
- [x] 6.3 Auditoría de no-regresión de contratos. **Evidencia:** `rg` conserva `atareao_track_view`, `atareao_track_view_nonce`, `atareao_contact_form`, `atareao_contact_nonce`, `atareao_captcha_*` y `atareao_form_time`; no se renombró ni borró ninguna opción, hook o acción.
- [x] 6.4 Spec. **Evidencia:** `openspec validate antiabuse --strict` → «Change 'antiabuse' is valid».

## 7. E2E manual

- [ ] 7.1 Contacto — uso legítimo: el usuario envía uno o dos mensajes desde el navegador y recibe `success`; el mensaje llega al room Matrix. **Pendiente** (requiere el sitio en marcha).
- [ ] 7.2 Contacto — abuso: ráfaga de POST (`curl`) desde una IP supera el tope → error accionable y **sin** mensajes nuevos en Matrix. **Pendiente.**
- [x] 7.3 Vistas — sin cookie: repetir `curl` a `admin-ajax.php` con `action=atareao_track_view` y nonce válido sin cookie no infla `post_views_count`. **Pendiente.**
- [ ] 7.4 IP real: comprobar que detrás de nginx el contador usa la IP del cliente y que `X-Forwarded-For` no lo altera. **Pendiente.**
- [ ] 7.5 No-regresión del sitio público: HTML, microsite `/tools/`, analítica, login/logout y flujo de comentarios sin cambios observables. **Pendiente.**

## 8. Entrega

- [ ] 8.1 Comprobar que la documentación de la limitación del captcha coincide con el comportamiento real. **Pendiente.**
- [ ] 8.2 PR por gitflow de `fix/antiabuse` a `development` con commits convencionales (gitmoji). **Pendiente** (este trabajo no hace push/PR/merge por instrucción).
- [ ] 8.3 Marcar las tareas completadas y archivar el change. **Pendiente** (archive requiere todas las casillas y E2E; no se ejecuta en este trabajo).

## 9. E2E producción (2026-10-04)

- [x] 7.3 Vistas sin cookie: `track_view` con nonce válido desde la misma IP → primer envío incrementa (400→401) y el segundo devuelve `cached:true` sin inflar → dedupe server-side por IP funcionando.
- [ ] 7.1/7.2/7.4/7.5 y 8.1: pendientes (contacto con captcha + Matrix, IP real tras nginx, no-regresión completa).
