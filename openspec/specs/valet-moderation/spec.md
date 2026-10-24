# valet-moderation Specification

## Purpose
Esta capability define el **contrato del borrado reversible** de una respuesta del asistente, desde el punto de vista del sitio: la retirada de un comentario que el propio asistente publicó y que llegó a él **por notificación**. Fija la petición (`DELETE` sobre el elemento único con `force=false`, es decir **papelera** y no destrucción), el origen del identificador (el `metadata` de la notificación —el único que la herramienta admite—; la **enumeración** de la colección está cerrada por el servidor, mientras que limitarse a lo notificado es **disciplina del consumidor**), la **ventana de reversibilidad** que concede el core, los **desenlaces y sus códigos REST**, el **rol que basta** —el mismo ya documentado, sin ampliación de privilegio— y la ausencia de notificación al borrar (sin bucle). La petición la emite el **consumidor**, el servicio Valet (`/data/rust/valet`); este repositorio especifica lo que el sitio **acepta y hace cumplir**.

## Requirements

### Requirement: Borrado reversible de un comentario notificado

La retirada SHALL realizarse mediante `DELETE /wp-json/wp/v2/comments/<id>` con `force=false`, autenticada con la **Application Password** del usuario de servicio (el mismo del requirement «Identidad de servicio y privilegio mínimo» de `valet-replies`, sin identidad ni credencial nuevas). Con `force=false` el core **no destruye** el comentario: lo envía a la **papelera**, de modo que la operación es **reversible** desde el panel de administración.

El asistente SHALL retirar **únicamente** comentarios que llegaron al asistente **por notificación**, y el `<id>` SHALL tomarse del `metadata` de esa notificación (`metadata.comment_id`). El cierre de la **enumeración** es una **garantía real del servidor**: la superficie de colección no le concede el listado. Pero que el asistente se limite a los identificadores notificados es **disciplina del consumidor**, **no** una garantía del sitio: la **herramienta** solo admite el identificador de la notificación (no acepta parámetros de identificadores explícitos) y el asistente no puede obtener un listado, de modo que su única fuente es el `metadata` de la notificación —más la **aprobación explícita** por herramienta—. La ruta de **elemento único** se conserva a propósito y solo exige `moderate_comments`, así que una credencial de servicio comprometida **podría** retirar **cualquier** comentario, no solo los notificados (es **riesgo residual aceptado**; el control *server-side* queda como trabajo siguiente). El `metadata.post_id` de la notificación permite contrastar que el comentario pertenece a la entrada esperada. Si la notificación no aporta `metadata.comment_id` (o lo aporta con valor `0`), SHALL NOT borrarse nada.

La **ventana de reversibilidad** SHALL ser la del core: los comentarios en papelera se eliminan definitivamente transcurridos **`EMPTY_TRASH_DAYS`** días, cuyo valor por defecto es **30** (`wp-includes/default-constants.php:387-388`); este repositorio SHALL NOT definir esa constante, de modo que la ventana queda en su valor por defecto y no se acorta. Dentro de la ventana, la restauración desde la papelera del panel SHALL devolver el comentario a su estado anterior.

El sitio SHALL NOT aplicar ninguna **cota propia** al borrado: no se raciona el número de retiradas. Es una **decisión explícita y asumida** y **no** se justifica con ninguna garantía del sitio. La única acotación es la del **origen** —el asistente toma el identificador de la notificación (el único que la herramienta admite) y la notificación está acotada por ventana (`COMMENT_RATE_LIMIT` / `COMMENT_RATE_WINDOW`)—. El **riesgo residual** se declara sin adornos: una credencial de servicio comprometida puede recorrer identificadores y mandar a la papelera **cualquier** comentario, no solo los notificados, sin cota. Y la reversibilidad **no** es una cota del **sitio**: la papelera y su ventana de `EMPTY_TRASH_DAYS` (**30 días** por defecto) **solo** operan mientras el consumidor **nunca** envíe `force=true` —**disciplina del consumidor**, no garantía del sitio—; con `force` verdadero el core ejecuta `wp_delete_comment($id, true)` (`class-wp-rest-comments-controller.php:1071,1087-1089`) y **destruye el comentario de forma irreversible**, y la **misma** credencial puede enviarlo sobre la ruta de elemento único para destruir cualquier comentario, sin cota. Por eso la protección del sitio sobre esta vía **no existe todavía**: el control *server-side* (forzar `force=false` para el usuario de servicio, p. ej. en `rest_pre_dispatch`) queda como **trabajo siguiente**, fuera de este change.

Antes de borrar, el asistente SHALL **leer** el comentario (`GET /wp-json/wp/v2/comments/<id>`) para confirmar qué va a retirar. Esa lectura usa la ruta de **elemento único**, que sigue disponible con independencia de la restricción de superficie de la colección.

#### Scenario: Respuesta del asistente retirada a la papelera

- **WHEN** el asistente envía `DELETE /wp-json/wp/v2/comments/123` con `force=false`, autenticándose con la Application Password del usuario de servicio, sobre un comentario que le fue notificado
- **THEN** el comentario `123` pasa al estado de papelera (`comment_approved` = `trash`), deja de ser visible en el sitio y **no** se destruye: sigue existiendo y es restaurable desde el panel

#### Scenario: Lectura previa al borrado

- **WHEN** el asistente consulta `GET /wp-json/wp/v2/comments/123` antes de retirarlo
- **THEN** obtiene el comentario concreto (ruta de elemento único), de modo que puede comprobar autor, contenido y entrada antes de decidir el borrado, sin necesidad de enumerar la colección

#### Scenario: Ventana de reversibilidad de 30 días

- **WHEN** un comentario enviado a la papelera por esta vía no se restaura
- **THEN** el core lo elimina definitivamente al transcurrir `EMPTY_TRASH_DAYS` días, que en este sitio vale **30** por no estar definida la constante; dentro de esa ventana la restauración desde el panel lo devuelve a su estado anterior

#### Scenario: Notificación sin identificador no permite borrar

- **WHEN** la notificación recibida no aporta `metadata.comment_id` (o lo aporta con valor `0`)
- **THEN** el asistente no puede determinar qué borrar y SHALL NOT emitir ningún `DELETE`, en lugar de borrar un comentario arbitrario o el primero del sitio

#### Scenario: El asistente no enumera para elegir qué borra

- **WHEN** el asistente necesita el identificador del comentario a retirar y no dispone del `metadata` de la notificación
- **THEN** no puede obtenerlo del sitio: la ruta de colección no le ofrece el listado (la garantía **real del servidor** es el cierre de la **enumeración**), así que la herramienta solo admite los identificadores que **recibió en la notificación**, nunca un listado

#### Scenario: Sin cota propia de borrado

- **WHEN** el asistente retira varias respuestas seguidas que le fueron notificadas
- **THEN** ninguna de ellas se rechaza por una cota de borrado: el sitio no raciona esta vía (**decisión asumida**, ver el requirement «Borrado reversible de un comentario notificado»); la única acotación es la del origen de la notificación y la reversibilidad de 30 días

#### Scenario: El rol documentado basta, sin ampliación de privilegio

- **WHEN** se inspeccionan las capacidades que exige el `DELETE` del elemento único frente a las que ya exigía el `POST` de la colección
- **THEN** ambas se satisfacen con `read` + `moderate_comments`: el aprovisionamiento documentado del usuario de servicio NO cambia y no hace falta ninguna capacidad nueva

### Requirement: Desenlaces del borrado y su traducción a códigos REST

El sitio SHALL resolver el borrado con los desenlaces y códigos siguientes, que son el contrato que el consumidor debe saber interpretar:

| Desenlace | Respuesta |
|---|---|
| Comentario existente y no en papelera: `force=false` aplicado | **`200`** con el comentario ya en la papelera (y la representación previa del comentario) |
| El comentario **ya está** en la papelera | **`410`** con código `rest_already_trashed` |
| El identificador **no corresponde a ningún comentario** | **`404`** con código `rest_comment_invalid_id` |
| El usuario autenticado **no puede** borrar ese comentario | **`403`** con código `rest_cannot_delete` |
| El sitio **no soporta** la papelera de comentarios | **`501`** con código `rest_trash_not_supported` |

«Ya está borrado» SHALL ser **inequívoco**: el `410 rest_already_trashed` distingue el caso de una segunda retirada del mismo comentario, de modo que el consumidor puede tratarlo como éxito idempotente sin ambigüedad con un borrado fallido. La papelera del core está condicionada al filtro `rest_comment_trashable`, cuyo valor por defecto es «hay papelera»; como este repositorio no define `EMPTY_TRASH_DAYS` (**30 días** por defecto, `wp-includes/default-constants.php:387-388`), el desenlace `200` de papelera es el esperado y `501 rest_trash_not_supported` describe únicamente la configuración en que un sitio desactivase la papelera.

Ninguno de estos desenlaces SHALL destruir el comentario: el contrato cubre la **papelera**, y el borrado definitivo (`force=true`) queda **fuera** de esta capability. El sitio, sin embargo, **no impide** `force=true` (`delete_item()`, `class-wp-rest-comments-controller.php:1071` lee `force` de la petición y `:1087-1089` ejecuta `wp_delete_comment($comment->comment_ID, true)`): la misma credencial de servicio podría enviarlo sobre la ruta de elemento único y **destruir cualquier comentario de forma irreversible**, sin papelera ni ventana. Es **riesgo residual aceptado**; el control *server-side* —forzar `force=false` para el usuario de servicio, p. ej. en `rest_pre_dispatch`— queda como **trabajo siguiente**, fuera de este change.

#### Scenario: Desenlace de éxito con papelera

- **WHEN** el asistente retira un comentario existente que no está en la papelera mediante `DELETE .../<id>` con `force=false`
- **THEN** el sitio responde `200` y el comentario queda en la papelera, sin ser destruido

#### Scenario: Retirada repetida inequívoca

- **WHEN** el asistente repite el `DELETE .../<id>` con `force=false` sobre un comentario que ya está en la papelera
- **THEN** el sitio responde `410` con código `rest_already_trashed`, y no responde `200` ni `404`: el consumidor sabe sin ambigüedad que el comentario ya estaba retirado

#### Scenario: Identificador inexistente

- **WHEN** el asistente envía `DELETE /wp-json/wp/v2/comments/<id>` con un identificador que no corresponde a ningún comentario
- **THEN** el sitio responde `404` con código `rest_comment_invalid_id` y no altera ningún comentario

#### Scenario: Identidad sin permiso sobre ese comentario

- **WHEN** la petición de borrado no se autentica como un usuario con permiso para moderar ese comentario
- **THEN** el sitio responde `403` con código `rest_cannot_delete` y el comentario permanece intacto

#### Scenario: Sitio sin soporte de papelera declarado

- **WHEN** un sitio desactiva la papelera de comentarios (de modo que deja de estar disponible `rest_comment_trashable`)
- **THEN** el desenlace es `501` con código `rest_trash_not_supported`, lo que documenta la única configuración en que el contrato de borrado reversible no puede cumplirse; en este repositorio no ocurre, porque la papelera está en su valor por defecto de 30 días

#### Scenario: El contrato no destruye el comentario

- **WHEN** se revisan las peticiones que el contrato admite
- **THEN** todas usan `force=false` y ninguna destruye el comentario: el borrado definitivo (`force=true`) queda fuera de esta capability

### Requirement: El borrado no genera notificación ni consume presupuesto

El paso de un comentario a la **papelera** SHALL NOT despachar ninguna notificación al servicio Valet, de modo que el borrado **no puede realimentar el bucle** ni provocar que el asistente reaccione a su propia retirada.

La garantía SHALL apoyarse en el comportamiento ya especificado del módulo de notificaciones: el manejador de transiciones solo notifica en la transición **hacia `approved`** y sale para cualquier otro estado destino (`trash`, `spam`, `hold`), de modo que el paso a papelera no despacha nada. Este requirement **no** autoriza a modificar esa guarda: la describe como la razón por la que el borrado es inocuo.

En consecuencia, retirar una respuesta SHALL NOT consumir el presupuesto de la ruta de notificación de comentarios (`COMMENT_RATE_LIMIT` / `COMMENT_RATE_WINDOW`), que queda íntegro para avisos de comentaristas reales.

#### Scenario: El paso a papelera no notifica

- **WHEN** un comentario aprobado pasa a la papelera (transición hacia `trash`)
- **THEN** el módulo de notificaciones no despacha ninguna notificación a Valet

#### Scenario: Otros estados de retirada tampoco notifican

- **WHEN** un comentario transiciona hacia `spam` o hacia `hold`
- **THEN** tampoco se despacha notificación alguna: el único estado destino que notifica es `approved`

#### Scenario: El borrado no consume el presupuesto de comentarios

- **WHEN** el asistente retira una respuesta y a continuación un visitante real publica un comentario aprobado
- **THEN** el aviso del comentarista real se despacha con normalidad, porque el borrado no consumió ninguna ranura del presupuesto de la ruta de comentarios
