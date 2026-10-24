# valet-moderation Specification

## Purpose
Esta capability define el **contrato del borrado reversible** de una respuesta del asistente, desde el punto de vista del sitio: la retirada de un comentario que el propio asistente publicó y que llegó a él **por notificación**. Fija la petición (`DELETE` sobre el elemento único con `force=false`, es decir **papelera** y no destrucción), el origen del identificador (el `metadata` de la notificación —el único que la herramienta admite—; la **enumeración** de la colección está cerrada por el servidor, mientras que limitarse a lo notificado es **disciplina del consumidor**), la **ventana de reversibilidad** que concede el core, los **desenlaces y sus códigos REST**, el **rol que basta** —el mismo ya documentado, sin ampliación de privilegio— y la ausencia de notificación al borrar (sin bucle). La petición la emite el **consumidor**, el servicio Valet (`/data/rust/valet`); este repositorio especifica lo que el sitio **acepta y hace cumplir**.

## Requirements

### Requirement: Borrado reversible de un comentario notificado

La retirada SHALL realizarse mediante `DELETE /wp-json/wp/v2/comments/<id>` con `force=false`, autenticada con la **Application Password** del usuario de servicio (el mismo del requirement «Identidad de servicio y privilegio mínimo» de `valet-replies`, sin identidad ni credencial nuevas). Con `force=false` el core **no destruye** el comentario: lo envía a la **papelera**, de modo que la operación es **reversible** desde el panel de administración.

El sitio SHALL **imponer** esa papelera al usuario de servicio, de modo que la reversibilidad deje de depender de que el consumidor envíe `force=false`: la reescritura SHALL ocurrir **antes** de que el controlador del core lea el parámetro (`wp-includes/rest-api/class-wp-rest-comments-controller.php:1071`) y con la identidad **ya resuelta**, es decir en un enganche de la petición REST posterior a `check_authentication()` (`wp-includes/rest-api/class-wp-rest-server.php:438`) y anterior al callback del controlador (`:1293`); la implementación SHALL usar `rest_request_before_callbacks` (`:1256`) reescribiendo el parámetro con `$request->set_param( 'force', false )` (`wp-includes/rest-api/class-wp-rest-request.php:456-470`). En consecuencia, la rama destructiva del core —`wp_delete_comment( $comment->comment_ID, true )` (`class-wp-rest-comments-controller.php:1087-1089`)— SHALL quedar **inalcanzable por esta vía** para el usuario de servicio, con independencia de lo que envíe el cliente (parámetro de *query*, cuerpo JSON o ausencia del parámetro). La retirada degradada SHALL seguir el camino de papelera (`wp_trash_comment()`, `:1116`) y conservar los desenlaces documentados, incluida la **segunda retirada** (`410 rest_already_trashed`, `:1108-1113`), que con `force=true` habría destruido el comentario en vez de responder.

El asistente SHALL retirar **únicamente** comentarios que llegaron al asistente **por notificación**, y el `<id>` SHALL tomarse del `metadata` de esa notificación (`metadata.comment_id`). El cierre de la **enumeración** es una **garantía real del servidor**: la superficie de colección no le concede el listado. Y desde este change la restricción a **lo notificado** SHALL ser **también garantía del servidor**, no disciplina del consumidor: el sitio SHALL escribir una **marca de procedencia** en los comentarios que **despacha** al asistente y SHALL **rechazar** la retirada de cualquier comentario del usuario de servicio que **no** lleve esa marca. La marca SHALL ser una meta propia (`_atareao_valet_notified`), **distinta** del sello anti-bucle `_atareao_valet_reply` (que marca los comentarios **del** asistente y vive en `valet-replies`), escrita **solo por el servidor**, una sola fila por comentario, en el instante en que el sitio despacha el aviso (`wp-content/plugins/atareao-functionality/includes/class-valet-config.php:707-753`). El `<id>` que la herramienta del consumidor admite sigue saliendo del `metadata` de la notificación, de modo que la herramienta y la guarda del sitio coinciden **por construcción** sobre el mismo conjunto. El `metadata.post_id` de la notificación permite contrastar que el comentario pertenece a la entrada esperada. Si la notificación no aporta `metadata.comment_id` (o lo aporta con valor `0`), SHALL NOT borrarse nada.

La guarda de procedencia SHALL aplicarse **solo** al usuario de servicio: SHALL NOT acotar a ningún otro usuario. Un usuario humano con `moderate_comments` SHALL conservar íntegras sus facultades de moderación, incluida la retirada —y la destrucción definitiva— de cualquier comentario, desde el panel o por REST, porque la guarda protege frente a una **credencial de servicio** que puede recorrer identificadores, no frente a la moderación humana. La identidad SHALL resolverse con la **misma** fuente de verdad que el resto del módulo (`ValetConfig::isServiceRequest()`, `class-valet-config.php:270-275`, que exige que la opción del identificador de servicio exista y sea mayor que `0`).

La guarda SHALL cubrir **toda retirada** del usuario de servicio en la ruta de elemento único, no solo el verbo `DELETE`: el mismo controlador retira con `status` en `POST`/`PUT`/`PATCH` sobre esa ruta (`handle_status_param()`, `class-wp-rest-comments-controller.php:1836-1872`, que con `trash` ejecuta `wp_trash_comment()` en `:1869` y con `spam` `wp_spam_comment()` en `:1863`) y con el mismo permiso (`check_edit_permission()`, `:1977-1987`). La guarda SHALL cubrir **también** el paso a `hold`, porque también retira el comentario de la vista: `handle_status_param()` con `hold` (o `0`) ejecuta `wp_set_comment_status( $comment_id, 'hold' )` (`:1858-1860`) y deja `comment_approved = '0'`, de modo que el comentario **desaparece de la vista pública** del sitio —reversible, pero retirado—; la garantía de procedencia quedaría incompleta si dejase pasar esa vía. Quedan **fuera** de la guarda únicamente las transiciones que **no** retiran el comentario de la vista: `approve`/`approved`/`1` (`:1853-1856`, que publica), `untrash` (`:1871-1872`) y `unspam` (`:1865-1866`).

La **ventana de reversibilidad** SHALL ser la del core: los comentarios en papelera se eliminan definitivamente transcurridos **`EMPTY_TRASH_DAYS`** días, cuyo valor por defecto es **30** (`wp-includes/default-constants.php:387-388`); este repositorio SHALL NOT definir esa constante, de modo que la ventana queda en su valor por defecto y no se acorta. Dentro de la ventana, la restauración desde la papelera del panel SHALL devolver el comentario a su estado anterior.

El sitio SHALL NOT aplicar ninguna **cota propia** al borrado: no se raciona el número de retiradas. Es una **decisión explícita y asumida** y **no** se justifica con ninguna garantía del sitio. La acotación del **origen** pasa a ser una **garantía del servidor** (la marca de procedencia y el rechazo de lo no marcado), junto con la ventana de la notificación (`COMMENT_RATE_LIMIT` / `COMMENT_RATE_WINDOW`) que acota cuántos avisos se despachan. Lo que queda **sin cota** es el **número** de retiradas dentro del conjunto notificado, y así se declara. La **irreversibilidad** ya no está en manos del consumidor: la rama destructiva del core queda inalcanzable por esta vía para el usuario de servicio (ver el primer párrafo). **Riesgo residual declarado**: la guarda acota el conjunto por **procedencia**, no la cantidad; y las vías **ajenas al contrato** siguen existiendo (un usuario humano con `moderate_comments` —a propósito— y cualquier vía administrativa o de línea de órdenes, que no forma parte de este contrato).

Antes de borrar, el asistente SHALL **leer** el comentario (`GET /wp-json/wp/v2/comments/<id>`) para confirmar qué va a retirar. Esa lectura usa la ruta de **elemento único**, que sigue disponible con independencia de la restricción de superficie de la colección y **no** está sujeta a la guarda de procedencia (la guarda acota la **retirada**, no la lectura).

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
- **THEN** ninguna de ellas se rechaza por una cota de borrado: el sitio no raciona el número de retiradas (**decisión asumida**, ver el requirement «Borrado reversible de un comentario notificado»); la única acotación es la del origen —que ahora **garantiza** el servidor— y la reversibilidad de 30 días

#### Scenario: El rol documentado basta, sin ampliación de privilegio

- **WHEN** se inspeccionan las capacidades que exige el `DELETE` del elemento único frente a las que ya exigía el `POST` de la colección
- **THEN** ambas se satisfacen con `read` + `moderate_comments`: el aprovisionamiento documentado del usuario de servicio NO cambia y no hace falta ninguna capacidad nueva

#### Scenario: El sitio fuerza la papelera aunque el cliente pida la destrucción

- **WHEN** el usuario de servicio envía `DELETE /wp-json/wp/v2/comments/123?force=true` (o con `force` verdadero en el cuerpo) sobre un comentario que le fue notificado
- **THEN** el sitio reescribe el parámetro a `false` antes de que el controlador lo lea, la petición sigue el camino de papelera y el comentario `123` queda en `trash` **sin destruirse**: la rama `wp_delete_comment( $id, true )` no se ejecuta en ningún caso

#### Scenario: La retirada degradada no rompe la idempotencia

- **WHEN** el usuario de servicio repite la petición con `force=true` sobre un comentario que **ya** está en la papelera
- **THEN** el sitio responde `410 rest_already_trashed` (éxito idempotente) y el comentario **sigue existiendo**: con `force=true` sin degradar, esa misma petición lo habría destruido de forma irreversible

#### Scenario: La retirada se acota al conjunto notificado

- **WHEN** el usuario de servicio intenta retirar (con `force=false`) un comentario del sitio que **no** fue notificado al asistente —es decir, que no lleva la marca de procedencia—, recorriendo identificadores
- **THEN** el sitio responde con el rechazo propio del plugin y el comentario permanece **intacto**: la retirada arbitraria por enumeración de identificadores deja de ser posible para la credencial de servicio, y la acotación deja de depender de que el consumidor se limite a lo notificado

#### Scenario: La marca de procedencia se escribe al notificar

- **WHEN** el sitio despacha al asistente el aviso de un comentario aprobado
- **THEN** ese comentario queda marcado con la meta de procedencia, y la retirada por el usuario de servicio pasa la guarda; si el aviso no se despacha (cota de notificación agotada) o el comentario es del propio asistente (sello anti-bucle), no se escribe marca alguna

#### Scenario: La guarda no acota al moderador humano

- **WHEN** un usuario humano con `moderate_comments` —distinto del usuario de servicio— retira un comentario **sin** marca de procedencia (u ordena su destrucción definitiva)
- **THEN** el sitio no aplica la guarda: la operación se resuelve como siempre, porque la guarda acota al consumidor y no a la moderación humana

#### Scenario: La retirada por cambio de estado queda acotada igual

- **WHEN** el usuario de servicio intenta retirar un comentario **sin** marca de procedencia por la vía del cambio de estado (`POST`/`PUT`/`PATCH /wp-json/wp/v2/comments/<id>` con `status` = `trash` o `spam`, que el core ejecuta con `wp_trash_comment()` / `wp_spam_comment()`)
- **THEN** el sitio responde el mismo rechazo propio y el comentario permanece intacto: la guarda cubre **toda** retirada del usuario de servicio, no solo el verbo `DELETE`

#### Scenario: La desaprobación queda acotada igual

- **WHEN** el usuario de servicio envía `status=hold` en `POST`/`PUT`/`PATCH /wp-json/wp/v2/comments/<id>` sobre un comentario **sin** la marca de procedencia
- **THEN** obtiene el mismo rechazo `403` con código `atareao_valet_comment_not_notified` y el comentario **permanece intacto y publicado** (`comment_approved` no cambia a `'0'`): no se ejecuta `wp_set_comment_status( $id, 'hold' )` (`class-wp-rest-comments-controller.php:1858-1860`)

#### Scenario: La desaprobación de un comentario notificado procede

- **WHEN** el usuario de servicio envía `status=hold` sobre un comentario **sí** notificado (que lleva la marca de procedencia)
- **THEN** la guarda no interviene y el paso a `hold` **procede**: el comentario queda despublicado (`comment_approved` = `'0'`) de forma reversible, igual que cualquier otra transición de retirada dentro del conjunto notificado

#### Scenario: Las transiciones que no retiran siguen permitidas

- **WHEN** el usuario de servicio cambia el estado de un comentario hacia `approve`, o lo **restaura** con `untrash` o `unspam`
- **THEN** la guarda de procedencia no interviene: solo se acotan `trash`, `spam` y `hold`, que son las transiciones que retiran el comentario de la vista; `approve` publica y `untrash`/`unspam` restauran, no retiran

### Requirement: Desenlaces del borrado y su traducción a códigos REST

El sitio SHALL resolver el borrado con los desenlaces y códigos siguientes, que son el contrato que el consumidor debe saber interpretar:

| Desenlace | Respuesta |
|---|---|
| Comentario **notificado**, existente y no en papelera: papelera aplicada | **`200`** con el comentario ya en la papelera (y la representación previa del comentario) |
| El comentario **ya está** en la papelera | **`410`** con código `rest_already_trashed` |
| El identificador **no corresponde a ningún comentario** | **`404`** con código `rest_comment_invalid_id` |
| El usuario autenticado **no puede** borrar ese comentario | **`403`** con código `rest_cannot_delete` |
| El sitio **no soporta** la papelera de comentarios | **`501`** con código `rest_trash_not_supported` |
| El usuario de servicio pide `force=true` en la retirada | **`200`** con el comentario **ya en la papelera**: el sitio fuerza `force=false` y **no** lo destruye (o **`410`** si ya estaba en la papelera) |
| El usuario de servicio retira (incluido el paso a `hold` con `status=hold`) un comentario que el sitio **no** notificó | **`403`** con código **`atareao_valet_comment_not_notified`** (código propio del plugin) |

«Ya está borrado» SHALL ser **inequívoco**: el `410 rest_already_trashed` distingue el caso de una segunda retirada del mismo comentario, de modo que el consumidor puede tratarlo como éxito idempotente sin ambigüedad con un borrado fallido. La papelera del core está condicionada al filtro `rest_comment_trashable`, cuyo valor por defecto es «hay papelera»; como este repositorio no define `EMPTY_TRASH_DAYS` (**30 días** por defecto, `wp-includes/default-constants.php:387-388`), el desenlace `200` de papelera es el esperado y `501 rest_trash_not_supported` describe únicamente la configuración en que un sitio desactivase la papelera.

El rechazo de procedencia SHALL llevar un código **propio del plugin** (`atareao_valet_comment_not_notified`), **distinto** de `rest_cannot_delete`: el primero significa «el sitio **no notificó** este comentario, así que el usuario de servicio no puede retirarlo» y el segundo «la identidad no puede moderar ese comentario». El código es la señal que el consumidor debe mapear; el status `403` es el mismo de la familia de permisos y **no** debe interpretarse como un problema de rol ni de credencial. El status del `WP_Error` devuelto por el filtro SHALL propagarse sin alteración (`rest_convert_error_to_response()`, `wp-includes/rest-api.php:3464-3478`).

Ninguno de estos desenlaces SHALL destruir el comentario: el contrato cubre la **papelera**, y el borrado definitivo (`force=true`) queda **fuera** de esta capability. El sitio, además, **ya lo impide** para el usuario de servicio: reescribe `force` a `false` antes de que el controlador lo lea (`class-wp-rest-comments-controller.php:1071`), de modo que la rama `wp_delete_comment( $comment->comment_ID, true )` (`:1087-1089`) es **inalcanzable** por la ruta de elemento único para esa identidad. La irreversibilidad deja de ser, por tanto, disciplina del consumidor: pasa a ser una propiedad garantizada por el servidor. La vía de **elemento único** se conserva a propósito (es la que sostiene la lectura previa y la retirada), y su permiso sigue siendo `moderate_comments` (`check_edit_permission()`, `:1977-1987`), pero la **retirada** de esa identidad queda acotada al conjunto notificado. La guarda solo se pronuncia cuando el comentario **existe**: si el identificador no corresponde a ningún comentario, el sitio SHALL dejar que el controlador resuelva el `404 rest_comment_invalid_id` documentado, sin ensombrecerlo con el rechazo de procedencia.

#### Scenario: Desenlace de éxito con papelera

- **WHEN** el asistente retira un comentario **notificado** existente que no está en la papelera mediante `DELETE .../<id>` con `force=false`
- **THEN** el sitio responde `200` y el comentario queda en la papelera, sin ser destruido

#### Scenario: Retirada repetida inequívoca

- **WHEN** el asistente repite el `DELETE .../<id>` con `force=false` sobre un comentario **notificado** que ya está en la papelera
- **THEN** el sitio responde `410` con código `rest_already_trashed`, y no responde `200` ni `404`: el consumidor sabe sin ambigüedad que el comentario ya estaba retirado

#### Scenario: Identificador inexistente

- **WHEN** el asistente envía `DELETE /wp-json/wp/v2/comments/<id>` con un identificador que no corresponde a ningún comentario
- **THEN** el sitio responde `404` con código `rest_comment_invalid_id` y no altera ningún comentario: la guarda de procedencia no se pronuncia sobre un comentario que no existe y no ensombrece este desenlace

#### Scenario: Identidad sin permiso sobre ese comentario

- **WHEN** la petición de borrado no se autentica como un usuario con permiso para moderar ese comentario
- **THEN** el sitio responde `403` con código `rest_cannot_delete` y el comentario permanece intacto

#### Scenario: Sitio sin soporte de papelera declarado

- **WHEN** un sitio desactiva la papelera de comentarios (de modo que deja de estar disponible `rest_comment_trashable`)
- **THEN** el desenlace es `501` con código `rest_trash_not_supported`, lo que documenta la única configuración en que el contrato de borrado reversible no puede cumplirse; en este repositorio no ocurre, porque la papelera está en su valor por defecto de 30 días

#### Scenario: El contrato no destruye el comentario

- **WHEN** se revisan las peticiones que el contrato admite
- **THEN** todas usan `force=false` —y el sitio **fuerza** ese valor cuando la petición la hace el usuario de servicio, aunque el cliente envíe `force=true`—, de modo que ninguna destruye el comentario: el borrado definitivo queda fuera de esta capability y es **inalcanzable** por esta vía

#### Scenario: Retirada de un comentario no notificado rechazada

- **WHEN** el usuario de servicio retira, por la ruta de elemento único, un comentario existente que no lleva la marca de procedencia
- **THEN** el sitio responde `403` con código `atareao_valet_comment_not_notified`, no responde `rest_cannot_delete` y **no** altera el comentario: el consumidor distingue sin ambigüedad «este comentario no fue notificado» de «esta identidad no puede moderar»

#### Scenario: El rechazo no ensombrece el 404

- **WHEN** el usuario de servicio envía `DELETE /wp-json/wp/v2/comments/<id>` con un identificador inexistente
- **THEN** el desenlace sigue siendo `404 rest_comment_invalid_id` y **no** el rechazo de procedencia: la guarda solo se pronuncia sobre comentarios que existen

### Requirement: El borrado no genera notificación ni consume presupuesto

El paso de un comentario a la **papelera** SHALL NOT despachar ninguna notificación al servicio Valet, de modo que el borrado **no puede realimentar el bucle** ni provocar que el asistente reaccione a su propia retirada.

La garantía SHALL apoyarse en el comportamiento ya especificado del módulo de notificaciones: el manejador de transiciones solo notifica en la transición **hacia `approved`** y sale para cualquier otro estado destino (`trash`, `spam`, `hold`), de modo que el paso a papelera no despacha nada. Este requirement **no** autoriza a modificar esa guarda: la describe como la razón por la que el borrado es inocuo.

En consecuencia, retirar una respuesta SHALL NOT consumir el presupuesto de la ruta de notificación de comentarios (`COMMENT_RATE_LIMIT` / `COMMENT_RATE_WINDOW`), que queda íntegro para avisos de comentaristas reales. La **guarda de procedencia** y la **escritura de la marca** que introduce este change SHALL NOT alterar ese comportamiento: la marca se escribe al **despachar** un aviso y no genera ninguno; el **rechazo** de una retirada no despacha nada ni consume ranura.

#### Scenario: El paso a papelera no notifica

- **WHEN** un comentario aprobado pasa a la papelera (transición hacia `trash`)
- **THEN** el módulo de notificaciones no despacha ninguna notificación a Valet

#### Scenario: Otros estados de retirada tampoco notifican

- **WHEN** un comentario transiciona hacia `spam` o hacia `hold`
- **THEN** tampoco se despacha notificación alguna: el único estado destino que notifica es `approved`

#### Scenario: El borrado no consume el presupuesto de comentarios

- **WHEN** el asistente retira una respuesta y a continuación un visitante real publica un comentario aprobado
- **THEN** el aviso del comentarista real se despacha con normalidad, porque el borrado no consumió ninguna ranura del presupuesto de la ruta de comentarios

#### Scenario: Ni la marca ni el rechazo despachan ni consumen

- **WHEN** el sitio escribe la marca de procedencia al despachar un aviso, y después **rechaza** una retirada de un comentario sin marca
- **THEN** no se despacha ninguna notificación adicional y no se consume ninguna ranura del presupuesto de comentarios: la marca es un sello de procedencia, no un aviso
