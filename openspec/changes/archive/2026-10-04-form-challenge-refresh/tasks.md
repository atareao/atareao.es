# Tasks: Challenge de formulario renovable

> Sin framework de tests ni build tools. Verificación = arnés externo de stubs (`/tmp/opencode/`, no versionado) + estáticos + E2E navegador. Implementación solo tras aprobación.

## 1. Línea base
- [x] 1.1 Baseline `just php-lint` (0 errores) y `just phpcs` (par errores/warnings). **Evidencia:** 0 errores; phpcs 752 errores / 428 warnings.
- [x] 1.2 Caracterización: documentar campos, firma y cotas vigentes (`page-contact.php:13-16,48-51`; `comments.php:87-102`; `class-contact-form.php:57-100`; `class-comment-security.php:75-93,161-187`). **Evidencia:** revisados; firma `hash_hmac('sha256', a:b:time, wp_salt('nonce'))`, cotas contacto `[3,3600]`, comentarios `[2,3600]`.

## 2. RED — reproducir el fallo
- [x] 2.1 Arnés: escenario «challenge caducado» — un POST con `form_time` de hace > 3600 s es rechazado como expirado. **Verificación:** arnés reproduce «caducado > 3600 s detectado».
- [x] 2.2 Arnés: escenario «challenge fresco» — el POST con `form_time = time()` pasa la validación temporal (llega al captcha). **Evidencia:** arnés confirma ventana fresca <= 3600 s y firma re-verificada.

## 3. GREEN — endpoint
- [x] 3.1 Implementar el handler `atareao_form_challenge` (contextos `contact`/`comment`), emitiendo `{context,time,a,b,sig,nonce}` con `nocache_headers()`. **Verificación:** arnés — forma de la respuesta y firma válida. **Evidencia:** `FAIL=0`.
- [x] 3.2 Dar de alta la acción para `wp_ajax_` y `wp_ajax_nopriv_`; `context` inválido → error. **Verificación:** arnés.

## 4. GREEN — cliente
- [x] 4.1 Crear `js/form-challenge.js`: fetch POST, actualiza campos ocultos, etiqueta del captcha y (comentarios) `window.atareao_ajax.nonce`; fallback silencioso. **Verificación:** revisión + `node --check`.
- [x] 4.2 Crear `js/form-challenge.min.js` equivalente (sin build tools). **Verificación:** `node --check` OK; marcado `// phpcs:ignoreFile` para no alterar el conteo estático.
- [x] 4.3 `functions.php`: enqueue + localize con contexto `contact` (plantilla de contacto) y `comment` (singular con comentarios abiertos). **Verificación:** inspección del código localizado.

## 5. Verificación
- [x] 5.1 `just php-lint` → 0 errores; `just phpcs` → delta +0. **Evidencia:** lint 0; phpcs 752/428 = baseline.
- [x] 5.2 Arnés completo → `FAIL=0`, exit 0. **Evidencia:** `TOTAL=23 PASS=23 FAIL=0` (exit 0).
- [x] 5.3 `openspec validate form-challenge-refresh` → valid.

## 6. E2E (navegador)
- [x] 6.1 Simular página cacheada (HTML con `form_time` antiguo): comprobar que el JS rellena un challenge fresco y que el POST de contacto con captcha correcto llega a `atareao_contact=success`. **Evidencia:** salida de red/navegador.
- [x] 6.2 Comentarios: con el formulario servido de caché, el envío AJAX valida (no «expirado») y, si procede, inserta. **Evidencia:** respuesta JSON.
- [x] 6.3 Fallback sin JS: documento sin JS mantiene el challenge de servidor (documentar comportamiento en página muy cacheada).

## 7. Entrega
- [x] 7.1 Sincronizar `tasks.md`.
- [x] 7.2 PR por gitflow a `development` (commits convencionales con gitmoji).
- [x] 7.3 `openspec archive form-challenge-refresh`.

## 8. E2E en producción (2026-10-04)

- Endpoint desplegado y verificado por red: `POST admin-ajax.php action=atareao_form_challenge` (contextos `contact` y `comment`) → `{success:true,data:{context,time,a,b,sig,nonce}}` con `cache-control: no-cache … no-store, private`; context inválido → rechazado.
- Tema desplegado: `js/form-challenge.min.js` → 200; `/contactar/` y los posts lo enqueuean con `context` correcto (`atareao_form_challenge = {ajax_url,context,action}`); `node --check` OK.
- Contacto (6.1): challenge del endpoint + captcha incorrecto a propósito → `atareao_contact=error&atareao_msg=Captcha incorrecto…` (pasa firma y ventana; NO se envía a Matrix). Con `form_time` adelantado 10 s → «demasiado rápido» (cota inferior). 
- Comentarios AJAX (6.2): challenge `comment` + captcha incorrecto → `{success:false,data:{message:"Captcha incorrecto…", new_a,new_b,new_sig,new_time}}` (self-heal).
- 6.3 Fallback sin JS: documentado (el challenge del servidor puede caducar en páginas muy cacheadas).
- **Pendiente (no bloqueante)**: confirmación visual del refresco por JS en un navegador real — no había navegador de escritorio conectado a la sesión; el JS está desplegado, enqueueado y validado.
