# Tasks: Registro REST de metadatos endurecido y `seo_description` de solo lectura

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación del change combina análisis estático (`just php-lint`, `just phpcs`), un **arnés de stubs externo** que vive solo en `/tmp/opencode/metaboxes-meta-harness/` (fuera del repo y no versionado) y E2E manual del usuario. El arnés no forma parte del commit ni del árbol. La implementación arranca **solo tras la aprobación del usuario**. Ninguna tarea renombra ni borra campos REST, tipos de post ni claves de meta.

## 1. Phase 0 — Línea base y caracterización

- [ ] 1.1 Documentar la caracterización del estado actual en `design.md` §Context (ME-01 `class-metaboxes.php:89-198`; ME-02 `:165-197`; ME-03 `:166-197`; ME-04 `:109-163`; SEO-01 `:63-83`) con evidencia fichero:línea. **Verificación:** cada afirmación cita una línea existente; `openspec validate metaboxes-meta-hardening` válido. **Evidencia esperada:** `design.md` §Context con las citas y la validación en verde.
- [ ] 1.2 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el par errores/warnings. **Evidencia esperada:** baseline theme+plugin **752 errores / 429 warnings**; `just php-lint` → 0 errores.
- [ ] 1.3 Registrar en el arnés externo los stubs mínimos de WordPress (`register_post_meta`, `register_rest_field`, `get_post_meta`, `update_post_meta`, `sanitize_text_field`, `current_user_can`, `__return_true`, `add_action`, `do_action`) con contadores y un modelo fiel de `WP_Hook`. **Verificación:** el arnés ejecuta un caso trivial por módulo y devuelve `FAIL=0`. **Evidencia esperada:** `/tmp/opencode/metaboxes-meta-harness/` (run.php/stubs.php/run.sh), no versionado.
- [ ] 1.4 Reproducir el `TypeError` fatal con el código actual como test de caracterización (RED). **Verificación:** el arnés, ejecutando el `registerMetaFields()` actual con `$app_types` array, registra el fallo «Cannot access offset of type array on array». **Evidencia esperada:** arnés MT-RED con `FAIL=1` y el mensaje del fatal.

## 2. ME-01/ME-02 — Registro de metadatos sin error fatal

- [ ] 2.1 Corregir `registerMetaFields()` para iterar `$app_types` y pasar un tipo string a cada `register_post_meta()` de las metas `_`; enganchar `registerMetaFields()` al hook `init`. **Verificación:** arnés con el ciclo real de hooks — `do_action('init')` ejecuta el registro sin `TypeError` ni fatal. **Evidencia esperada:** arnés MT-01 (GREEN).
- [ ] 2.2 Comprobar que las metas quedan registradas para sus tipos (`mp3-url`, `number`, `season`, `numero-capitulo`, `tutorial-id`, `post_views_count`). **Verificación:** arnés — la tabla de metas registradas contiene cada clave con su tipo. **Evidencia esperada:** arnés MT-02.
- [ ] 2.3 Guardia de regresión: verificar que pasar un array como `$post_type` volvería a producir el fatal (el arnés lo detecta). **Verificación:** arnés MT-03 — la guardia reproduce el `TypeError` si se reactivara el array. **Evidencia esperada:** arnés MT-03.

## 3. ME-03 — Metas internas fuera de REST

- [ ] 3.1 Registrar `_download_url`, `_repository_url` y `_version` con `show_in_rest => false`. **Verificación:** arnés — los argumentos registrados tienen `show_in_rest === false`. **Evidencia esperada:** arnés MT-04.
- [ ] 3.2 Comprobar que una respuesta REST del flujo real de un `application`/`software` no incluye `_download_url`, `_repository_url` ni `_version`, ni anónima ni autenticada. **Verificación:** arnés — la proyección REST no contiene claves `_`. **Evidencia esperada:** arnés MT-05.

## 4. ME-04 — Escritura REST con capacidad

- [ ] 4.1 Declarar `auth_callback` que exija `edit_posts` en las metas públicas que no lo tenían (`number`, `season`, `numero-capitulo`, `tutorial-id`, `post_views_count`). **Verificación:** arnés — cada meta pública registrada expone un `auth_callback`; con usuario sin `edit_posts` la escritura se rechaza. **Evidencia esperada:** arnés MT-06/MT-07.
- [ ] 4.2 Comprobar que la lectura pública se conserva y que la escritura con `edit_posts` persiste el valor saneado. **Verificación:** arnés — lectura disponible; escritura con capacidad persiste `intval`/`sanitize_text_field`. **Evidencia esperada:** arnés MT-08/MT-09.
- [ ] 4.3 Comprobar que el campo `metadata` del tipo `podcast` solo devuelve las claves curadas y nunca claves `_`. **Verificación:** arnés — proyección de `metadata` sin claves `_` y con las claves curadas. **Evidencia esperada:** arnés MT-10.

## 5. SEO-01 — `seo_description` de solo lectura y saneado

- [ ] 5.1 Retirar el `update_callback` de `seo_description` (solo lectura) y sanear el `get_callback` con `sanitize_text_field`. **Verificación:** arnés — el registro no declara `update_callback`; la lectura devuelve el valor saneado; sin `_genesis_description` → cadena vacía. **Evidencia esperada:** arnés SEO-01/SEO-02/SEO-03.
- [ ] 5.2 Comprobar que una escritura REST de `seo_description` no modifica `_genesis_description` y que el nombre del campo y sus tipos no cambian. **Verificación:** arnés — la escritura no llama a `update_post_meta`; el campo conserva nombre y endpoints. **Evidencia esperada:** arnés SEO-04/SEO-05.

## 6. Verificación

- [ ] 6.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.2. **Evidencia esperada:** `just php-lint` → 0 errores; `phpcs` con el mismo par o mejor que 752/429.
- [ ] 6.2 Arnés externo completo. **Verificación:** `/tmp/opencode/metaboxes-meta-harness/` → `TOTAL=<n> PASS=<n> FAIL=0`, `exit=0`; ejercita el **ciclo real de hooks** y cubre ME-01/ME-02 (registro sin fatal), ME-03 (metas `_` fuera de REST), ME-04 (capacidad de escritura, `metadata` acotado) y SEO-01 (`seo_description` de solo lectura saneado). **Evidencia esperada:** salida `FAIL=0`, exit 0.
- [ ] 6.3 Auditoría de no-regresión de contratos. **Verificación:** `rg` conserva los campos REST `all_metadata`, `metadata` y `seo_description`, los tipos de post y los nombres de las metas. **Evidencia esperada:** cadenas invariantes.
- [ ] 6.4 Spec. **Verificación:** `openspec validate metaboxes-meta-hardening` sin hallazgos. **Evidencia esperada:** «Change 'metaboxes-meta-hardening' is valid».

## 7. E2E en producción

- [ ] 7.1 ME-01/ME-02: el sitio carga sin fatal y `/wp-json/wp/v2/podcast/<id>` responde con normalidad. **Verificación:** petición manual. **Evidencia esperada:** sin error 500; pendiente (requiere producción).
- [ ] 7.2 ME-03: la respuesta REST de un `application`/`software` no incluye las metas `_`. **Verificación:** petición manual anónima y autenticada. **Evidencia esperada:** claves `_` ausentes; pendiente (requiere producción).
- [ ] 7.3 ME-04: la escritura REST de `post_views_count` sin capacidad se rechaza y con capacidad persiste. **Verificación:** petición manual autenticada y no autenticada. **Evidencia esperada:** 403/denegado y persistencia; pendiente (requiere producción).
- [ ] 7.4 SEO-01: `seo_description` se lee saneado y no admite escritura por REST. **Verificación:** lectura + intento de escritura manual. **Evidencia esperada:** valor saneado y sin cambios; pendiente (requiere producción).
- [ ] 7.5 No-regresión del sitio público y del editor: HTML, metaboxes de admin, microsite `/tools/`, analítica y login sin cambios. **Verificación:** navegación manual y E2E. **Evidencia esperada:** sin cambios observables; pendiente (requiere producción).

## 8. Entrega

- [ ] 8.1 Marcar las tareas completadas y comprobar que `tasks.md` refleja el trabajo real. **Verificación:** todas las casillas aplicables marcadas; `openspec list` muestra el change activo. **Evidencia esperada:** tasks sincronizadas.
- [ ] 8.2 PR por gitflow de la rama de la feature a `development` con commits convencionales (gitmoji). **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia esperada:** pendiente.
- [ ] 8.3 Archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive metaboxes-meta-hardening` crea la spec `rest-metafields`; `openspec list` ya no muestra el change activo. **Evidencia esperada:** pendiente.
