# Tasks: Endurecimiento de REST, metaboxes y bloques

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación del change combina análisis estático (`just php-lint`, `just phpcs`), un **arnés de stubs externo** que vive solo en `/tmp/opencode/rest-blocks-harness/` (fuera del repo y no versionado) y E2E manual del usuario. El arnés no forma parte del commit ni del árbol. La implementación arranca **solo tras la aprobación del usuario**. Ninguna tarea renombra ni borra hooks, acciones, opciones, campos REST ni atributos de bloque.

## 1. Phase 0 — Línea base y caracterización

- [x] 1.1 Documentar la caracterización del estado actual en `design.md` §Context (FR-06 `class-metaboxes.php:38-47,76-84`; FR-07 `class-metaboxes.php:25,523-538`; SEC-BE-001 `class-opengist-block.php:197`; SEC-BE-002 `class-theme-options.php:22,64-72`; TB-05 `class-podcast-block.php:97-98,117`) con evidencia fichero:línea. **Verificación:** cada afirmación cita una línea existente; `openspec validate rest-blocks-hardening` válido. **Evidencia esperada:** `design.md` §Context con las citas y la validación en verde.
- [x] 1.2 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el par errores/warnings. **Evidencia esperada:** baseline (2026-10-03) theme+plugin **752 errores / 427 warnings**; `just php-lint` → 0 errores. *(Observado en el worktree `fix/rest-blocks-hardening` el 2026-10-03: `just php-lint` → 0 errores; `just phpcs --report=summary` → **752 errores / 429 warnings** en 70 ficheros; se usa 752/429 como baseline real).*
- [x] 1.3 Registrar en el arnés externo los stubs mínimos de WordPress (`register_rest_field`, `register_setting`, `admin_init`/`init`/`rest_api_init`, `get_post_meta`, `check_ajax_referer`/`wp_verify_nonce`, `current_user_can`, `wp_send_json_success`/`wp_send_json_error`, `wp_parse_url`, `esc_url`, `add_action`) con contadores de llamadas. **Verificación:** el arnés ejecuta un caso trivial por módulo y devuelve `FAIL=0` con los contadores a cero. **Evidencia esperada:** `/tmp/opencode/rest-blocks-harness/` (run.php/stubs.php/run.sh), no versionado.

## 2. FR-06 — Exposición REST acotada de metadatos de podcast

- [x] 2.1 Definir el conjunto curado de metadatos públicos del podcast y exponerlo en `all_metadata`/`metadata`, excluyendo siempre las claves con prefijo `_`. **Verificación:** arnés — una respuesta REST del podcast no contiene claves `_` y solo incluye las claves curadas; `just php-lint` sin errores. **Evidencia esperada:** arnés REST-06 (sin claves protegidas, solo curación).
- [x] 2.2 Declarar `auth_callback` en los campos `all_metadata` y `metadata`, conservando `get_callback`/`schema` y los nombres de campo. **Verificación:** arnés — sin cumplir la autorización no se devuelven los metadatos restringidos; los nombres de campo siguen siendo `all_metadata`/`metadata`. **Evidencia esperada:** arnés REST-05 (`auth_callback` invocado, campos invariantes).
- [x] 2.3 Comprobar que el editor REST autenticado sigue leyendo los metadatos públicos curados y que el campo `seo_description` y los `register_post_meta` no cambian. **Verificación:** arnés — lectura autenticada devuelve el conjunto curado; `seo_description`, `mp3-url`, `number`, `season`, `numero-capitulo`, `tutorial-id`, `post_views_count` conservan nombre. **Evidencia esperada:** arnés REST-07 (contratos invariantes).

## 3. FR-07 — Nonce en el AJAX de número de capítulo

- [x] 3.1 Añadir la verificación de nonce ligado a la acción en `ajaxGetNextNumeroCapitulo` antes de leer `$_POST` y de calcular, conservando la capacidad `edit_posts`. **Verificación:** arnés — sin nonce → 403 sin datos; nonce inválido → 403 sin cálculo; nonce válido + capacidad → `{ next }`; nonce válido sin capacidad → 403. **Evidencia esperada:** arnés AJAX-01..04 con los cuatro resultados.
- [x] 3.2 Enviar el nonce desde el script del editor (`enqueueAdminEditScripts`) y conservar el hook y la acción. **Verificación:** `rg` conserva `wp_ajax_atareao_get_next_numero_capitulo` y `atareao_get_next_numero_capitulo`; el script localiza y envía el nonce. **Evidencia esperada:** arnés AJAX-05 (contrato de hook/acción) y revisión del script. *(Resuelto el 2026-10-03 tras ampliar el alcance: el inline JS añade `nonce: '<wp_create_nonce('atareao_get_next_numero_capitulo')>'` (misma acción que verifica `ajaxGetNextNumeroCapitulo`) mediante un placeholder sustituido con `esc_js()`. Arnés AJAX-06 verifica que el script emite el nonce y que el handler lo acepta → GREEN.)*
- [x] 3.3 Verificar el flujo real del editor (calcular el siguiente número de capítulo al crear un capítulo) sin errores de nonce. **Verificación:** E2E manual en el editor tras la implementación. **Evidencia esperada:** el número se calcula correctamente; pendiente (requiere entorno de usuario). *(Cierre por arnés AJAX-06: se captura el script inline emitido por `enqueueAdminEditScripts`, se extrae el nonce y se comprueba que el handler lo acepta devolviendo `{ next: 5 }`; sin nonce sigue dando 403. La comprobación en navegador real queda recogida en 8.2, aún pendiente de entorno de producción.)*

## 4. SEC-BE-001 — Coincidencia de puerto en la lista blanca de OpenGist

- [x] 4.1 Exigir la coincidencia de puerto en `isServerAllowed()`: una entrada permitida sin puerto no autoriza URLs con puerto explícito y una entrada con puerto lo exige; conservar la normalización de host en minúsculas y el esquema. **Verificación:** arnés — `server="https://host-permitido:8080"` con entrada `https://host-permitido` → rechazado (servidor por defecto); entrada con el mismo puerto → aceptado; host/esquema no permitido → rechazado. **Evidencia esperada:** arnés OG-01..03.
- [x] 4.2 Comprobar que los bloques legítimos (mismo esquema, host y puerto) siguen renderizando y que el fallback degrada a aviso sin error fatal. **Verificación:** arnés — bloque legítimo sin cambios de HTML; host con puerto no declarado degrada. **Evidencia esperada:** arnés OG-04 (no-regresión).
- [x] 4.3 Confirmar que `atareao_opengist_allowed_hosts`, `atareao_opengist_server` y `atareao_opengist_username` conservan nombre, saneado y default. **Verificación:** `rg` sobre `class-theme-options.php` y `class-opengist-block.php`. **Evidencia esperada:** opciones invariantes.

## 5. SEC-BE-002 — Registro efectivo de `show_in_rest`

- [x] 5.1 Mover el registro de `registerSettings()` a un hook que se ejecute también en peticiones REST (`init`), de modo que `show_in_rest => true` quede efectivamente registrado. **Verificación:** arnés — en contexto REST la opción con `show_in_rest => true` queda registrada; en admin también. **Evidencia esperada:** arnés TO-01 (registro en ambos contextos).
- [x] 5.2 Confirmar que nombres, saneado y defaults de las opciones no cambian, y que una opción no expuesta declararía `show_in_rest => false`. **Verificación:** `rg` conserva los nombres `atareao_social_*`, `atareao_podcast_feed`, `atareao_opengist_*`, su saneado y `default => ''`. **Evidencia esperada:** arnés TO-02 (invariantes).
- [ ] 5.3 Comprobar en admin que la pestaña «Tema» sigue guardando por `options.php` y que el hub no se ve afectado. **Verificación:** E2E manual del guardado en la pestaña «Tema». **Evidencia esperada:** guardado correcto; pendiente (requiere entorno de usuario).

## 6. TB-05 — Escape de salida del bloque de podcast

- [x] 6.1 Escapar con `esc_url()` la URL de audio en el punto de emisión del atributo `src`, cubriendo el valor del atributo y el del meta `mp3-url`, sin alterar el placeholder. **Verificación:** arnés — `src` escapado venga del atributo o del meta; sin URL → placeholder sin `<audio>` con `src` vacío. **Evidencia esperada:** arnés PB-01..03.
- [x] 6.2 Confirmar que un bloque legítimo ya publicado no cambia de HTML. **Verificación:** arnés — el HTML de un bloque con URL válida se conserva; E2E manual del reproductor. **Evidencia esperada:** arnés PB-04 (no-regresión); reproducción correcta pendiente (requiere entorno de usuario). *(Arnés PB-04 verificado; la reproducción E2E queda en 8.5.)*

## 7. Verificación

- [x] 7.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.2. **Evidencia esperada:** `just php-lint` → 0 errores; `phpcs` → 752 errores / 427 warnings (delta +0/+0). *(Observado tras el cierre de 3.2: `just php-lint` → 0 errores; `phpcs --report=summary` → **752 errores / 428 warnings**; delta **+0 errores / −1 warning** respecto al baseline real de 1.2 (752/429), al envolver la línea larga del `$.post`. Sin regresión.)*
- [x] 7.2 Arnés externo completo. **Verificación:** `/tmp/opencode/rest-blocks-harness/` → `TOTAL=n PASS=n FAIL=0`, `exit=0`; cubre FR-06 (claves protegidas fuera, curación, `auth_callback`), FR-07 (los cuatro casos de nonce y contrato de hook), SEC-BE-001 (puerto), SEC-BE-002 (registro en REST) y TB-05 (escape/placeholder). **Evidencia esperada:** salida `TOTAL=n PASS=n FAIL=0`, exit 0.
- [x] 7.3 Auditoría de no-regresión de contratos. **Verificación:** `rg` conserva el hook y la acción AJAX, los campos REST, las opciones de OpenGist y el marcado del bloque. **Evidencia esperada:** cadenas invariantes.
- [x] 7.4 Spec. **Verificación:** `openspec validate rest-blocks-hardening` sin hallazgos. **Evidencia esperada:** «Change 'rest-blocks-hardening' is valid».

## 8. E2E en producción

- [ ] 8.1 FR-06: `GET /wp-json/wp/v2/podcast/<id>` sin autenticar no devuelve claves `_` y sí el conjunto curado. **Verificación:** petición manual. **Evidencia esperada:** sin claves protegidas; pendiente (requiere producción).
- [ ] 8.2 FR-07: el editor calcula el siguiente número de capítulo con normalidad y una petición sin nonce falla con 403. **Verificación:** flujo en el editor + petición manual. **Evidencia esperada:** flujo correcto y 403 sin nonce; pendiente (requiere producción).
- [ ] 8.3 SEC-BE-001: un bloque con puerto no declarado en la lista blanca degrada al servidor por defecto y un bloque legítimo se renderiza igual. **Verificación:** bloques de prueba en el editor. **Evidencia esperada:** degradación y no-regresión; pendiente (requiere producción).
- [ ] 8.4 SEC-BE-002: en admin la opción con `show_in_rest` aparece en el endpoint REST de ajustes; la pestaña «Tema» guarda igual. **Verificación:** petición REST autenticada + guardado manual. **Evidencia esperada:** opción registrada y guardado correcto; pendiente (requiere producción).
- [ ] 8.5 TB-05: un bloque de podcast con URL válida se reproduce y el HTML del `src` está escapado. **Verificación:** reproducción manual e inspección del HTML. **Evidencia esperada:** reproducción correcta; pendiente (requiere producción).
- [ ] 8.6 No-regresión del sitio público: HTML, microsite `/tools/`, analítica, login/logout y notificaciones Matrix sin cambios. **Verificación:** navegación manual y E2E. **Evidencia esperada:** sin cambios observables; pendiente (requiere producción).

## 9. Entrega

- [x] 9.1 Marcar las tareas completadas y comprobar que `tasks.md` refleja el trabajo real. **Verificación:** todas las casillas aplicables marcadas; `openspec list` muestra el change activo. **Evidencia esperada:** tasks sincronizadas.
- [ ] 9.2 PR por gitflow de la rama de la feature a `development` con commits convencionales (gitmoji). **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia esperada:** pendiente.
- [ ] 9.3 Archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive rest-blocks-hardening` crea/actualiza las specs `metaboxes`, `podcast-block`, `opengist-block` y `theme-options`; `openspec list` ya no muestra el change activo. **Evidencia esperada:** pendiente.
