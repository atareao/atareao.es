# Proposal

## Why

El asistente de Valet (`/data/rust/valet`) **puede responder** a un comentario notificado desde el change `2026-10-22-valet-comment-replies`, pero **no puede retirar** una respuesta suya. Hoy una respuesta equivocada —una alucinación, un tono inadecuado, una respuesta a un comentario que resultó ser spam— solo se puede quitar a mano desde el panel de administración. La conversación es, por tanto, de ida y vuelta **asimétrica**: el asistente publica sin intervención humana y todo deshacer exige presencia humana.

Este change cierra esa asimetría con dos piezas: **una capability nueva** (`valet-moderation`) que fija el contrato del borrado **desde el punto de vista del sitio**, y **una corrección previa, bloqueante y ajena a la funcionalidad nueva**, sin la cual nada de lo anterior es alcanzable.

### Defecto previo H1 (bloqueante, va dentro de este change)

**La ruta de publicación está muerta.** `atareao-functionality.php:77-84` elimina **incondicionalmente** la ruta de colección `/wp/v2/comments` de la API REST:

```php
function atareao_functionality_disable_rest_comment_endpoint($endpoints)
{
    if (isset($endpoints['/wp/v2/comments'])) {
        unset($endpoints['/wp/v2/comments']);   // línea 80
    }
    return $endpoints;
}
add_filter('rest_endpoints', 'atareao_functionality_disable_rest_comment_endpoint');
```

El servicio Valet publica con `POST /wp-json/wp/v2/comments`, así que hoy recibe **`404 rest_no_route`**: el requirement «Contrato de respuesta del asistente» de `valet-replies` es **insatisfacible** y la prueba de punta a punta de aquel change quedó bloqueada precisamente por esto.

**Evidencia y alcance del defecto:**

- El filtro llega de `3b7baf0` («✨ feat(cache): add cache purge class and refactor contact form and matrix config», 20-jul-2026), un commit de otra materia: es **preexistente**, **ajeno** al change `valet-comment-replies` ya mergeado y **no detectado** allí porque la verificación de punta a punta nunca llegó a ejecutarse (el stack estaba parado y el aprovisionamiento manual pendiente).
- **Ninguna spec del corpus declara ese cierre.** Ni `valet-replies`, ni `valet-notifications`, ni `rest-access-policy` mencionan la eliminación de la colección: el filtro es **código heredado sin especificación**. Es el motivo por el que el arreglo entra como **MODIFIED de `valet-replies`** y no como un cambio de otra capability.
- El cierre es **incondicional**: no mira la identidad del usuario, ni el método, ni la ruta de elemento único. Consecuencia colateral: tampoco el propio panel `moderate_comments` puede usar la vía REST de la colección desde un cliente, aunque el sitio no documenta ese cierre en ninguna parte.

**Por qué el arreglo es viable y seguro (H2, verificado):** `rest_endpoints` se aplica en `get_routes()` (`wp-includes/rest-api/class-wp-rest-server.php:957,974`), y `check_authentication()` se ejecuta en `serve_request()` (`:438`), **antes** de `dispatch()` (`:1063`). Dentro del filtro el usuario actual **ya está resuelto**, así que la exención por identidad es posible y no depende de nada que aporte el cliente.

## What Changes

**1. Superficie REST de la colección de comentarios (`valet-replies`, MODIFIED).**

- El filtro `rest_endpoints` **deja de eliminar la colección de forma incondicional** y pasa a decidir **por identidad del usuario autenticado**:
  - **Sin usuario de servicio** configurado (`atareao_valet_reply_user` vacío o `0`) → la colección se elimina **entera** (fallo a **cerrado**).
  - **Usuario de servicio** → la colección conserva **únicamente** el grupo de manejador `POST` (`create_item`) y **descarta** el de `GET` (`get_items`): el asistente puede **publicar**, pero **no puede enumerar** comentarios. El cierre a la **enumeración** es una garantía **real del servidor**. En cambio, la decisión de alcance **D2** («solo se borran comentarios que llegaron por notificación») **NO** pasa a ser garantía del sitio: es **disciplina del consumidor** —los identificadores solo llegan en el `metadata` de la notificación— más la **aprobación explícita** por herramienta. La ruta de **elemento único**, que este change conserva a propósito, solo exige `moderate_comments`, de modo que una credencial comprometida puede recorrer identificadores y mandar a la papelera **cualquier** comentario, no solo los notificados, y sin cota (**riesgo residual aceptado**; el control *server-side* queda como trabajo siguiente, fuera de este change; reversible 30 días).
  - **Cualquier otro usuario** (autenticado o anónimo) → la colección sigue eliminada **entera**, exactamente como hasta ahora.
- La ruta de **elemento único** (`/wp/v2/comments/<id>`) **no se toca**: sigue disponible, que es lo que hace posible la lectura previa del borrado (decisión **D5**).
- Nada de esto añade una ruta pública a la puerta REST anónima (`rest-access-policy` **no se toca**): la exención por identidad solo la disfruta el usuario de servicio ya autenticado, y la escritura anónima sigue rechazada.

**2. Capability nueva `valet-moderation`: el contrato del borrado.**

- El asistente retira una respuesta con `DELETE /wp-json/wp/v2/comments/<id>` y **`force=false`** (decisión **D3**): el comentario va a la **papelera**, no se destruye. La ventana de reversibilidad es la del core, **30 días** por defecto (`EMPTY_TRASH_DAYS`, `wp-includes/default-constants.php:387-388`; el repositorio no define la constante), y **solo** opera mientras la petición lleve `force=false`: con `force=true` el core **destruye de inmediato y de forma irreversible** (`class-wp-rest-comments-controller.php:1071,1087-1089`), y el sitio **no** lo impide —**disciplina del consumidor**, no garantía del sitio—.
- El `<id>` del borrado sale del `metadata` de la notificación (`metadata.comment_id`): el consumidor retira **solo** comentarios que llegaron por notificación (D2). Es **disciplina del consumidor** (más la aprobación explícita por herramienta), **no** una garantía del sitio: la ruta de elemento único se conserva a propósito y una credencial comprometida podría borrar cualquier comentario (riesgo residual aceptado, ver **1**).
- **Sin cota propia** de borrado (decisión **D4**): la vía no se raciona. La única acotación es la del **origen** —la disciplina del consumidor y la ventana de la notificación (`COMMENT_RATE_LIMIT` / `COMMENT_RATE_WINDOW`)— y el borrado es **reversible**. El sitio **no** impone esa restricción, de modo que renunciar a la cota es una **decisión** asumida con el riesgo residual a la vista, no una consecuencia de una garantía inexistente.
- **Lectura previa** (decisión **D5**): antes de borrar, el asistente lee el comentario (`GET /wp-json/wp/v2/comments/<id>`) para confirmar qué va a retirar.
- Los **desenlaces y sus códigos** quedan fijados como contrato: `200` con el comentario ya en la papelera; `410 rest_already_trashed`; `404 rest_comment_invalid_id`; `403 rest_cannot_delete`; `501 rest_trash_not_supported`.
- El **rol no cambia** (H6): `read` + `moderate_comments` basta —`check_edit_permission()` acepta `moderate_comments` a secas para `DELETE`, y `create_item_permissions_check()` ya lo exige para el `POST`—, así que el aprovisionamiento documentado en el README sigue siendo válido tal cual.
- **Borrar no notifica** (H3): `notifyOnCommentTransition()` sale salvo transición **hacia `approved`** (`class-valet-config.php:673-684`), de modo que el paso a `trash` (y a `spam`/`hold`) no despacha nada. **No hay bucle por el lado del borrado** y el borrado no consume el presupuesto de la ruta de comentarios.

## Capabilities

### New Capabilities

- `valet-moderation`: contrato del **borrado reversible** de una respuesta del asistente desde el punto de vista del sitio —petición `DELETE .../<id>` con `force=false`, papelera con ventana de 30 días, retirada dirigida por el identificador de la notificación (**disciplina del consumidor, no garantía del sitio**), lectura previa, desenlaces con sus códigos REST, rol suficiente y ausencia de notificación (sin bucle).

### Modified Capabilities

- `valet-replies`: cambia el requirement «Contrato de respuesta del asistente». La superficie REST de la colección de comentarios deja de estar cerrada incondicionalmente y pasa a resolverse por identidad: `POST` para el usuario de servicio (sin `GET`, para que no pueda enumerar), colección **entera** eliminada para cualquier otro, y **fallo a cerrado** cuando no hay usuario de servicio configurado. Los otros tres requirements de la capability (`Identidad de servicio y privilegio mínimo`, `Marcado de procedencia y guarda anti-bucle`, `Cota de escritura en la vía de respuesta`) se conservan **intactos**.

## Impact

### Ficheros afectados (previsibles)

- `wp-content/plugins/atareao-functionality/atareao-functionality.php` — el filtro `atareao_functionality_disable_rest_comment_endpoint()` (`:77-84`) pasa a resolver por identidad, con `get_current_user_id()` y la opción `atareao_valet_reply_user` (`class-valet-config.php:140`, con filtro del mismo nombre, `:275`). Se conserva la firma y el nombre del hook.
- `wp-content/plugins/atareao-functionality/includes/class-valet-config.php` — **previsiblemente sin cambios**: el borrado no añade código nuevo al sitio (es comportamiento del controlador REST del core) y la guarda de transición ya ignora todo lo que no sea `approved`. Solo se tocaría si la lectura del ID de servicio necesitase un accesor público reutilizable; en ese caso, sin cambio de contrato.
- `tests/test-valet.php` — tests nuevos: tres casos de **identidad** del filtro (usuario de servicio, otro usuario autenticado, anónimo/sin usuario configurado), dos casos de **método** (`POST` aceptado; `GET` de la colección no disponible) y la no-regresión del borrado sin notificación (`trash`/`spam`/`hold` no despachan y no consumen presupuesto).
- `tests/wp-stubs.php` — stubs necesarios para ejercitar el filtro `rest_endpoints` con la **forma real** que el core entrega al filtro (por ruta, una **lista de grupos de manejador con clave numérica** —el método va en el campo `'methods'` de cada grupo— más las opciones de ruta en claves no numéricas, tal como la guarda `register_route()` en `wp-includes/rest-api/class-wp-rest-server.php:928-932`) y la transición a `trash`.
- `wp-content/plugins/atareao-functionality/README.md` — se documenta el **contrato de borrado** que el servicio Valet debe cumplir: petición, `force=false`, de dónde sale el `<id>`, lectura previa, tabla de desenlaces con códigos, ventana de 30 días, rol suficiente, la advertencia de que el asistente **no puede enumerar** la colección (garantía **real** del servidor) y de que la restricción a «solo lo notificado» es **disciplina del consumidor**, **no** una garantía del sitio (con el **riesgo residual** explícito).

### Alcance (no-goals)

- **Emitir la petición de borrado no es de este change**: la emite el servicio **Valet** (`/data/rust/valet`, con su propio change), que es quien custodia la Application Password. Este repositorio fija el contrato, deja la ruta alcanzable y documenta el procedimiento.
- **No se implementa `force=true`.** El contrato es **papelera** (reversible, D3). El borrado definitivo queda fuera: si algún día se quiere, será un change propio con su propia justificación de irreversibilidad. Y **el sitio no lo impide**: no se fuerza `force=false` para el usuario de servicio, así que la misma credencial podría enviar `force=true` y destruir cualquier comentario de forma irreversible (riesgo residual aceptado; el control *server-side* queda como trabajo siguiente).
- **No se añade UI, pestaña ni opción nueva** (`admin-settings` no se toca): el borrado se ejerce por REST y la reversibilidad se ejerce desde la papelera de comentarios del panel, que ya existe.
- **No se añade cota de borrado** (D4) ni se toca la cota de escritura existente.
- **No se toca la puerta REST anónima** (`rest-access-policy`): la lista de exenciones sigue conteniendo **solo** `/atareao/v1/mcp`.
- **No se toca el anti-abuse** (`anti-abuse`): el borrado no pasa por `preprocess_comment` ni lo debilita.

### Especificaciones tocadas

- `valet-replies` — **MODIFIED**: un requirement (`Contrato de respuesta del asistente`), que gana la resolución de superficie por identidad (cierre de la **enumeración**); sus seis escenarios previos se conservan **literalmente** y se añaden los de identidad, método y fallo a cerrado.
- `valet-moderation` — **ADDED**: capability nueva con tres requirements.

### Especificaciones que NO se tocan (y por qué)

- **`rest-access-policy`** — la exención que introduce este change es **por identidad de usuario autenticado**, no por ruta: la lista de rutas públicas exentas (`$public_routes = ['/atareao/v1/mcp']`, `atareao-functionality.php:153-155`) **no cambia**, y el scenario «Application Password sigue funcionando» de esa spec sigue describiendo el comportamiento real. La única tarea relacionada es una **verificación** de que la lista sigue conteniendo solo `/atareao/v1/mcp`.
- **`valet-notifications`** — el borrado no entra en el módulo de notificaciones: la guarda existente (`notifyOnCommentTransition()` sale salvo transición hacia `approved`) ya lo cubre, y este change **no la modifica**; solo la caracteriza con tests.
- **`matrix-notifications`** — el borrado no publica nada en Matrix ni altera el canal.
- **`comments`** — el borrado no toca el escapado de plantillas, el captcha ni la sanitización del contenido.
- **`admin-settings`** — no se añade pestaña, opción ni formulario.

### Dependencia externa (consumidor Rust)

La emisión de `DELETE` vive en el servicio **Valet** (`/data/rust/valet`), sistema externo a este repositorio y **fuera de alcance**: este change no lo modifica ni lo inspecciona. El acoplamiento es de **contrato** y es unidireccional: el sitio garantiza que la colección es alcanzable por `POST` (para responder) y que el elemento único es alcanzable por `GET`/`DELETE` (para leer y retirar), y que el borrado no genera notificación. El consumidor decide **cuándo** retirar una respuesta; el sitio no le concede ninguna capacidad que no tuviera ya el rol `read` + `moderate_comments`.

## Verificación pendiente (no forma parte de este change)

La **prueba de punta a punta sigue bloqueada** y **no se cierra aquí**:

- Requiere el **stack arrancado** (`just start`; el servicio Valet no está en marcha) y el **aprovisionamiento manual** de la identidad de servicio (usuario dedicado con `read` + `moderate_comments` y su Application Password), además del requisito de entorno del core (SSL o `WP_ENVIRONMENT_TYPE=local`) ya documentado en `valet-replies`.
- Lo que **queda verificado de forma estática** en este change: el defecto H1 y su corrección por identidad (tests de harness sobre el filtro), la no-notificación del paso a papelera y el contrato documentado.
- Lo que **queda pendiente**: que un `POST` real del servicio publique la respuesta (deja de devolver `404 rest_no_route`) y que un `DELETE .../<id>` real con `force=false` deje el comentario en la papelera, con los códigos de la tabla de desenlaces observados **en vivo**.
- **No es un requisito de este change**: es deuda de verificación heredada de `valet-comment-replies` que este change **desbloquea** pero no puede cerrar por sí solo.
