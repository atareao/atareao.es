# Tasks: Challenge de formulario renovable

> Sin framework de tests ni build tools. Verificación = arnés externo de stubs (`/tmp/opencode/`, no versionado) + estáticos + E2E navegador. Implementación solo tras aprobación.

## 1. Línea base
- [ ] 1.1 Baseline `just php-lint` (0 errores) y `just phpcs` (par errores/warnings). **Evidencia:** números.
- [ ] 1.2 Caracterización: documentar campos, firma y cotas vigentes (`page-contact.php:13-16,48-51`; `comments.php:87-102`; `class-contact-form.php:57-100`; `class-comment-security.php:75-93,161-187`). **Evidencia:** citas fichero:línea.

## 2. RED — reproducir el fallo
- [ ] 2.1 Arnés: escenario «challenge caducado» — un POST con `form_time` de hace > 3600 s es rechazado como expirado. **Verificación:** reproduce el 302 con `atareao_msg=El formulario ha expirado`.
- [ ] 2.2 Arnés: escenario «challenge fresco» — el POST con `form_time = time()` pasa la validación temporal (llega al captcha). **Evidencia:** `FAIL` del escenario 2.1 contra el comportamiento actual de página cacheada (sin endpoint/JS no hay refresco).

## 3. GREEN — endpoint
- [ ] 3.1 Implementar el handler `atareao_form_challenge` (contextos `contact`/`comment`), emitiendo `{context,time,a,b,sig,nonce}` con `nocache_headers()`. **Verificación:** arnés — forma de la respuesta y firma válida. **Evidencia:** `FAIL=0`.
- [ ] 3.2 Dar de alta la acción para `wp_ajax_` y `wp_ajax_nopriv_`; `context` inválido → error. **Verificación:** arnés.

## 4. GREEN — cliente
- [ ] 4.1 Crear `js/form-challenge.js`: fetch POST, actualiza campos ocultos, etiqueta del captcha y (comentarios) `window.atareao_ajax.nonce`; fallback silencioso. **Verificación:** revisión + E2E.
- [ ] 4.2 Crear `js/form-challenge.min.js` equivalente (sin build tools). **Verificación:** comportamiento idéntico en E2E.
- [ ] 4.3 `functions.php`: enqueue + localize con contexto `contact` (plantilla de contacto) y `comment` (singular con comentarios abiertos). **Verificación:** inspección del HTML localizado.

## 5. Verificación
- [ ] 5.1 `just php-lint` → 0 errores; `just phpcs` → delta +0.
- [ ] 5.2 Arnés completo → `FAIL=0`, exit 0.
- [ ] 5.3 `openspec validate form-challenge-refresh` → valid.

## 6. E2E (navegador)
- [ ] 6.1 Simular página cacheada (HTML con `form_time` antiguo): comprobar que el JS rellena un challenge fresco y que el POST de contacto con captcha correcto llega a `atareao_contact=success`. **Evidencia:** salida de red/navegador.
- [ ] 6.2 Comentarios: con el formulario servido de caché, el envío AJAX valida (no «expirado») y, si procede, inserta. **Evidencia:** respuesta JSON.
- [ ] 6.3 Fallback sin JS: documento sin JS mantiene el challenge de servidor (documentar comportamiento en página muy cacheada).

## 7. Entrega
- [ ] 7.1 Sincronizar `tasks.md`.
- [ ] 7.2 PR por gitflow a `development` (commits convencionales con gitmoji).
- [ ] 7.3 `openspec archive form-challenge-refresh`.
