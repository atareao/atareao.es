# Design: Endurecimiento de REST, metaboxes y bloques

## Context

Cinco hallazgos de la auditoría de seguridad de 2026-10-03 (severidad LOW/INFO) tocan cuatro módulos de `atareao-functionality`. La motivación está en `proposal.md`; aquí se resume el estado actual con evidencia fichero:línea y las restricciones que condicionan el enfoque.

| ID | Fichero:línea | Estado actual |
|---|---|---|
| FR-06 | `class-metaboxes.php:39-83,88-105` | `register_rest_field('podcast', 'all_metadata'` con `get_callback => get_post_meta($id)` y `'metadata'` con `get_post_meta($id, '', '')`; devuelve todas las claves, incluidas las protegidas (`_`). En core, `register_rest_field()` no declara ni consume `auth_callback`. |
| FR-07 | `class-metaboxes.php:25,523-538` | Hook `wp_ajax_atareao_get_next_numero_capitulo`; el handler comprueba `current_user_can('edit_posts')` (403 si no) y lee `$_POST['tutorial_id']`/`exclude_id` con `intval()`, pero **no** llama a `check_ajax_referer`/`wp_verify_nonce`. |
| SEC-BE-001 | `class-opengist-block.php:183-206` | `isServerAllowed($url)` recorre `getAllowedHosts()`; en la línea 197 solo compara el puerto si `$allowed['port'] !== null`. Si la entrada permitida no declara puerto, cualquier puerto del host pasa. La comparación de host es exacta y en minúsculas. |
| SEC-BE-002 | `class-theme-options.php:20-23,64-72` | `ThemeOptions::init()` engancha `registerSettings()` a `admin_init`; `atareao_opengist_allowed_hosts` (y `atareao_opengist_server`/`username`) declaran `show_in_rest => true`. `admin_init` no se dispara en peticiones REST, así que la opción no queda registrada en `/wp/v2/settings`. |
| SEC-BE-002b | `class-metaboxes.php:30` | `Metaboxes::init()` (invocado desde el callback de `init` prioridad 10 del bootstrap) reengancha `registerMetaFields` a `init` a la **misma** prioridad 10. WP_Hook no ejecuta un callback añadido a la prioridad que está procesando, así que `metadata` y los `register_post_meta` nunca se registran. |
| TB-05 | `class-podcast-block.php:85-118` | `renderPodcastPlayer()`: `$audio_url = esc_url($attributes['audioUrl'])` al entrar (línea 87), pero si está vacío toma `get_post_meta($podcast_id, 'mp3-url', true)` crudo (líneas 97-98) y lo imprime con `echo $audio_url` en el `src` (línea 117). |

**Restricciones del repo.** WordPress sobre PHP 8.3, PSR12, **sin framework de tests ni build tools**. La verificación es `just php-lint` (0 errores) + `just phpcs` (baseline theme+plugin: **752 errores / 427 warnings**, objetivo delta +0), un **arnés externo de stubs** en `/tmp/opencode/rest-blocks-harness/` (fuera del repo, no versionado) y E2E manual del usuario. No se renombra ni se borra ningún hook, opción, campo REST, acción ni atributo de bloque. La implementación solo arranca tras la aprobación del change.

**Capabilities afectadas.** `opengist-block` y `theme-options` ya existen y se **modifican** (sus requirements cambian). `metaboxes` y `podcast-block` no existen y se **crean** como capabilities nuevas; ambas reciben su `## Purpose` en el delta.

## Goals / Non-Goals

**Goals:**

- Cerrar la divulgación de metadatos protegidos por la REST API del podcast, manteniendo el campo útil para el editor autenticado.
- Cerrar el CSRF de lectura del AJAX de número de capítulo sin romper la funcionalidad del editor.
- Hacer que la lista blanca de OpenGist honre el contrato «mismo esquema, host y puerto».
- Hacer efectiva la declaración `show_in_rest` de las opciones, resolviendo el bug funcional y el falso contrato.
- Añadir el escape de salida que falta en el bloque de podcast como defensa en profundidad.
- Verificar todo sin framework de tests: lint, PSR12 con delta +0, arnés externo de stubs y E2E manual.

**Non-Goals:**

- No se reescribe la política de acceso de la REST API ni se añade autenticación global.
- No se cambian nombres de hooks, acciones, opciones, campos REST ni atributos de bloque.
- No se introducen dependencias, build tools ni framework de tests.
- No se tocan los hallazgos de severidad alta de otros changes (`antiabuse`, `pocketid-hardening`, etc.) ni el sitio público.

## Decisions

### Decisión 1 (FR-06): lista curada de claves, sin `auth_callback` no-op

El defecto es que `get_post_meta($id)` y `get_post_meta($id, '', '')` vuelcan *todas* las claves, incluidas las protegidas. La defensa correcta es **acotar las claves** a un conjunto curado de metadatos públicos del podcast (`mp3-url`, `number`, `season`, `post_views_count`); las claves con prefijo `_` nunca se devuelven, ni autenticado, porque son internas por convención de WordPress. **No** se declara `auth_callback`: `register_rest_field()` de core no lo declara ni lo consume, así que sería un no-op y crearía un falso contrato de seguridad. La curación de claves es el control efectivo, y las claves curadas son públicas por diseño, por lo que no procede una restricción de capacidad.

**Consecuencias:** el editor REST y cualquier consumidor del podcast ven solo el conjunto curado de metadatos públicos; la respuesta deja de filtrar `_genesis_description`, `_edit_lock`, `_thumbnail_id`, etc. Los nombres de los campos REST no cambian, así que los consumidores legítimos no se rompen.

**Alternativas descartadas:** (a) añadir `auth_callback` a `register_rest_field()`: argumento no soportado por core, no restringe nada y da falsa sensación de control; (b) implementar una restricción real con `register_meta()`/`rest_prepare_*`/`permission_callback`: innecesaria porque las claves curadas son públicas y añadiría complejidad sin cambiar la exposición.

### Decisión 2 (FR-07): nonce ligado a la acción, conservando capacidad y contrato

`ajaxGetNextNumeroCapitulo` añade `check_ajax_referer('atareao_get_next_numero_capitulo', 'nonce')` (o `wp_verify_nonce` equivalente) **antes** de leer `$_POST` y de calcular nada, manteniendo la comprobación `current_user_can('edit_posts')`. El nonce se genera en el script del editor (`enqueueAdminEditScripts`, `class-metaboxes.php:543+`) y se envía en la petición. El hook, la acción y el contrato de respuesta (`wp_send_json_success(['next' => …])`) no cambian.

**Consecuencias:** una petición cross-site sin el nonce del editor falla con 403 y no revela el número de capítulo. El editor legítimo no se ve afectado.

**Alternativa descartada:** confiar solo en `SameSite` de cookies o en el `Referer`. No es una defensa CSRF fiable para `admin-ajax.php`; el nonce es el mecanismo estándar de WordPress.

### Decisión 3 (SEC-BE-001): coincidencia de puerto simétrica, incluidas las entradas sin puerto

`isServerAllowed()` debe tratar el puerto como parte del contrato. La regla: si la URL evaluada declara puerto, la entrada permitida debe declarar el mismo puerto; una entrada sin puerto solo autoriza URLs sin puerto explícito. Se conservan la normalización de host en minúsculas y la comparación exacta de esquema. WordPress ya limita los puertos seguros de las peticiones HTTP (`http_allowed_safe_ports`), pero eso no exime de respetar la lista blanca declarada: el contrato documentado exige coincidencia de puerto.

**Consecuencias:** `server="https://host-permitido:8080"` deja de autorizarse cuando la entrada es `https://host-permitido` sin puerto. Los bloques legítimos que declaran el mismo puerto que la entrada siguen funcionando.

**Alternativa descartada:** mantener la comparación solo cuando la entrada declara puerto. Es exactamente el desvío detectado; deja la puerta abierta a cualquier puerto del host permitido.

### Decisión 4 (SEC-BE-002): registro en `init` con prioridad efectiva para que `show_in_rest` sea efectivo

`ThemeOptions::registerSettings()` pasa de `admin_init` a `init` (con **prioridad 20**), que se ejecuta tanto en el contexto de administración como en las peticiones REST. La prioridad es clave: `ThemeOptions::init()` se invoca desde el callback de `init` (prioridad 10) del bootstrap del plugin, y WP_Hook no ejecuta callbacks añadidos a la prioridad que está procesando; enganchar a la misma prioridad 10 dejaría `registerSettings()` sin ejecutar en ningún contexto (el bug que introdujo la primera versión de este change). Con la prioridad 20, WP_Hook la procesa en la siguiente iteración del mismo `init`. Así `show_in_rest => true` queda realmente registrado y el editor/`/wp/v2/settings` ven las opciones y sus defaults (bajo la autorización `manage_options`). El **mismo patrón** corrige `Metaboxes::registerMetaFields()` (SEC-BE-002b), que estaba muerto por idéntico motivo: `metadata` y los `register_post_meta` no se registraban. Nombres, saneado y defaults no cambian. Si en el futuro no se quisiera exponer alguna opción, la vía correcta es declarar `show_in_rest => false`, no declararlo y no cumplirlo.

**Consecuencias:** la declaración REST deja de ser un falso contrato. No hay riesgo de exposición indebida porque el endpoint de ajustes REST exige `manage_options`.

**Alternativa descartada:** retirar `show_in_rest`. Resuelve el falso contrato pero elimina una capacidad que el editor puede aprovechar; el change documenta además que si no se desea, debe ponerse `false`.

### Decisión 5 (TB-05): escapar en el punto de salida, no confiar en el saneado de entrada

`renderPodcastPlayer()` escapa con `esc_url($audio_url)` justo antes de emitirlo en el `src` del `<audio>`, cubriendo tanto el valor del atributo como el del meta. Es defensa en profundidad: `mp3-url` ya se sanea con `esc_url_raw` al guardar (`class-metaboxes.php:730`), pero el punto de salida no debe confiar en el saneado de entrada.

**Consecuencias:** el HTML de un bloque legítimo no cambia (misma URL escapada); una URL con caracteres especiales se emite de forma válida.

**Alternativa descartada:** sanear solo al guardar. Un cambio de origen del valor (importación, meta manipulado por otro código) eludiría el saneado; el escape en salida es el control correcto.

## Risks / Trade-offs

- **FR-06 — consumidores que esperaban claves concretas:** acotar las claves puede romper a quien leía una clave no incluida en el conjunto curado. → Mitigación: elegir el conjunto curado a partir de los metadatos públicos que el editor realmente usa; los nombres de campo REST no cambian; verificar el editor del bloque de podcast con E2E.
- **FR-07 — editor que no envíe el nonce:** si el script no se actualiza, el editor deja de calcular el número de capítulo. → Mitigación: la tarea incluye actualizar el script del editor y una verificación E2E del flujo en el editor; el hook y el contrato se conservan.
- **SEC-BE-001 — bloques legítimos con puerto explícito:** un bloque que usa `https://host:8080` con la entrada `https://host` pasará a rechazarse. → Mitigación: es el comportamiento correcto según el contrato; se documenta y se degrada elegantemente al servidor por defecto, sin error fatal.
- **SEC-BE-002 — efectos de registrar en `init`:** mover el registro podría alterar el orden respecto a `admin_init`. → Mitigación: `register_setting` en `init` es una práctica estándar y no depende del orden de `admin_init`; verificar en admin y en REST.
- **TB-05 — regresión de marcado:** cambiar el escape podría alterar el HTML. → Mitigación: `esc_url()` sobre una URL válida produce la misma cadena; verificar el HTML del bloque.
- **Verificación sin framework:** no hay tests automatizados en repo. → Mitigación: `just php-lint` + `just phpcs` (delta +0), arnés externo de stubs en `/tmp/opencode/rest-blocks-harness/` con `TOTAL/PASS/FAIL` y E2E manual.

## Migration Plan

No hay migración de datos: no se renombran ni borran opciones, hooks, acciones, campos REST ni meta. El despliegue es un cambio de código; el rollback es revertir el commit. Los bloques y entradas existentes siguen renderizando. No se requiere `search-replace` ni limpieza de transients.

## Open Questions

Ninguna. El conjunto curado de claves FR-06 se decide antes de implementar a partir de los metadatos públicos del podcast; no cambia el spec (que exige «conjunto curado sin claves protegidas»), solo la lista concreta, y se verifica en el arnés.
