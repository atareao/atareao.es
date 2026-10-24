# valet-replies Delta

## MODIFIED Requirements

### Requirement: Contrato de respuesta del asistente

La obligación de **emitir** esta petición corresponde al **consumidor** —el servicio Valet (`/data/rust/valet`), que es quien redacta y publica la respuesta y quien custodia la Application Password—: este requirement especifica el **contrato del contrario**, no código de este repositorio. Este sitio **acepta y hace cumplir** ese contrato a través de las garantías de los otros requirements de esta capability (sellado de procedencia con guarda anti-bucle, identidad de servicio y cota de escritura); no implementa ningún cliente REST en PHP.

La respuesta del asistente SHALL publicarse mediante una petición `POST` al controlador REST de comentarios del core, `POST /wp-json/wp/v2/comments`, con los campos `post` = ID de la entrada (`post_id` recibido en la notificación, **dentro de su objeto `metadata`**) y `parent` = ID del comentario notificado (`metadata.comment_id` de esa misma notificación), y `status` = `approved`, de modo que la respuesta se publique de forma **directa, sin pasar por moderación**.

La petición SHALL autenticarse con una **Application Password** de WordPress (autenticación HTTP Basic contra la API REST). SHALL NOT añadirse ninguna ruta pública nueva a la lista de exenciones de la puerta REST anónima (`rest-access-policy`): esa puerta ya deja pasar las peticiones autenticadas por Application Password, y una ruta exenta de escritura sería un **agujero anónimo** en el sitio.

El sistema SHALL asumir y documentar que el anti-abuse de comentarios (`preprocess_comment`) **no se aplica** a esta vía: ese filtro lo ejecuta `wp_new_comment()`, y el controlador REST de comentarios no pasa por él. Es una propiedad del core, no una decisión de este change; en consecuencia, la vía de respuesta SHALL NOT depender del anti-abuse para su seguridad y SHALL apoyar su acotación en el requirement «Cota de escritura en la vía de respuesta».

**Superficie de la colección por identidad.** El contrato de este requirement SHALL ser **alcanzable**: el sitio SHALL NOT eliminar la ruta de colección `/wp/v2/comments` de forma incondicional. El filtro `rest_endpoints` SHALL resolver la superficie de esa colección **por identidad del usuario autenticado** en la petición, con estas tres reglas:

- **Sin usuario de servicio configurado** —la opción del identificador de servicio (`atareao_valet_reply_user`) ausente, vacía o `0`—, la colección SHALL eliminarse **por completo**: **falla a cerrado**, porque sin identidad de servicio no hay consumidor legítimo de esa vía.
- **Usuario autenticado que ES el usuario de servicio**: la colección SHALL conservar **verbatim** las claves no numéricas de la ruta (`schema`, `namespace`, opciones) y SHALL conservar **únicamente** el grupo de manejador cuyo `methods` contiene `POST` y **no** contiene `GET` —un `methods` puede llegar como cadena con comas o como array, y la comprobación SHALL normalizar ambos—, descartando el grupo de `GET` (el listado). Si no sobrevive ningún grupo numérico, la ruta SHALL eliminarse **entera**. El asistente SHALL poder **publicar** y SHALL NOT poder **enumerar** comentarios.
- **Cualquier otro usuario** —autenticado o anónimo—: la colección SHALL seguir eliminada **entera**, sin cambio alguno respecto del comportamiento anterior.

**Cierre de la enumeración frente al alcance de la retirada (actualizado por `valet-server-side-control`).** El cierre a la **enumeración** —el asistente no puede obtener el listado de comentarios— es una garantía **real y comprobable**: la colección SHALL NOT ofrecer `GET` al usuario de servicio. Y la retirada **deja de estar solo en manos del consumidor**: el sitio SHALL acotarla al conjunto de comentarios que **notificó** (meta de procedencia escrita al despachar el aviso, con rechazo de código propio `atareao_valet_comment_not_notified` para lo no marcado, sobre **toda** retirada del usuario de servicio) y SHALL forzar `force=false` en esa misma vía, de modo que la rama destructiva del core (`class-wp-rest-comments-controller.php:1071,1087-1089`) quede **inalcanzable** para esa identidad y la reversibilidad de **30 días** (`EMPTY_TRASH_DAYS` por defecto) sea una propiedad del **sitio**, no una disciplina del consumidor. Ambos controles se detallan en la capability `valet-moderation`; este requirement solo deja de describirlos como riesgo residual. La moderación **humana** NO queda acotada: un usuario con `moderate_comments` conserva íntegras sus facultades sobre cualquier comentario.

La resolución SHALL basarse en la **identidad del usuario ya autenticado** y SHALL NOT aceptar ninguna indicación aportada por el cliente (parámetro, cabecera o cuerpo) para decidir si la superficie se abre. La implementación SHALL apoyarse en que `check_authentication()` se ejecuta **antes** de `dispatch()`, de modo que la identidad está resuelta cuando el filtro corre.

La ruta de **elemento único** (`/wp/v2/comments/<id>`, con `GET` y `DELETE`) SHALL NOT verse afectada por esta decisión de superficie: sigue disponible, y es la que sostiene la lectura previa y la retirada reversible especificadas en la capability `valet-moderation`. Que siga disponible **no** significa que su retirada sea libre: esa vía queda sujeta a la **guarda de procedencia** de `valet-moderation`, que acota al usuario de servicio al conjunto notificado y fuerza la papelera.

Esta decisión **no exime ninguna ruta** de la puerta REST anónima: la lista de rutas públicas de `rest-access-policy` SHALL seguir conteniendo **únicamente** `/atareao/v1/mcp`, y la escritura anónima SHALL seguir rechazada.

#### Scenario: Respuesta publicada con entrada y comentario padre correctos

- **WHEN** el asistente publica una respuesta a un comentario mediante `POST /wp-json/wp/v2/comments` con `post` = `45`, `parent` = `123` y `status` = `approved`, autenticándose con una Application Password válida de un usuario con `moderate_comments`
- **THEN** se crea un comentario cuyo `comment_post_ID` es `45` y cuyo `comment_parent` es `123`, y queda aprobado de inmediato (visible en el sitio sin moderación)

#### Scenario: Notificación sin IDs no permite direccionar la respuesta

- **WHEN** la notificación recibida no aporta `metadata.comment_id` (o lo aporta con valor `0`)
- **THEN** el asistente no puede determinar el `parent` y la respuesta SHALL NOT publicarse, en lugar de crear un comentario huérfano o de primer nivel

#### Scenario: Autenticación fallida no crea comentario

- **WHEN** la Application Password es inválida, está revocada o el usuario no tiene permisos suficientes
- **THEN** el endpoint responde con error `401`/`403` y **no se crea** ningún comentario en la base de datos

#### Scenario: La escritura anónima sigue rechazada por la puerta REST

- **WHEN** un visitante anónimo intenta `POST /wp-json/wp/v2/comments` sin credenciales
- **THEN** la puerta REST resuelve con `401` y la petición no llega a crear ningún comentario

#### Scenario: El anti-abuse no se ejecuta por esta vía

- **WHEN** se publica una respuesta por `POST /wp-json/wp/v2/comments` sin captcha, sin honeypot y sin marca temporal de comentario
- **THEN** la respuesta se publica igualmente, lo que confirma que `preprocess_comment` no participa en esta vía y que la protección no puede apoyarse en él

#### Scenario: Entrada inexistente rechazada por el controlador REST

- **WHEN** la petición referencia una entrada inexistente (`post` con un ID que no corresponde a ninguna entrada)
- **THEN** el controlador REST devuelve un error y **no se crea** ningún comentario huérfano

#### Scenario: La colección de comentarios es alcanzable por el usuario de servicio

- **WHEN** el usuario de servicio envía `POST /wp-json/wp/v2/comments` y el sitio tiene configurado su identificador
- **THEN** la ruta de colección existe con su manejador de `POST` y la petición llega al controlador de creación, en lugar de resolverse con `404 rest_no_route` (defecto previo corregido)

#### Scenario: El usuario de servicio no puede enumerar comentarios

- **WHEN** el usuario de servicio envía `GET /wp-json/wp/v2/comments` (el listado de la colección)
- **THEN** la ruta no está disponible para él y la petición se resuelve con `404 rest_no_route`, de modo que el asistente **no** puede obtener el listado de comentarios del sitio

#### Scenario: Cualquier otro usuario sigue con la colección cerrada

- **WHEN** un usuario autenticado **distinto** del usuario de servicio envía `POST /wp-json/wp/v2/comments`
- **THEN** la ruta de colección sigue eliminada **entera** y la petición se resuelve con `404 rest_no_route`, sin crear ningún comentario

#### Scenario: Sin usuario de servicio configurado la colección se elimina entera

- **WHEN** la opción del identificador de servicio está ausente, vacía o vale `0`
- **THEN** la ruta de colección se elimina **por completo** —también su manejador de `POST`— para cualquier petición: el sitio falla a cerrado en lugar de abrir la vía sin identidad de servicio

#### Scenario: La apertura de la colección no se puede reclamar desde el cliente

- **WHEN** una petición distinta de la del usuario de servicio autenticado intenta hacerse pasar por ella aportando parámetros, cabeceras o cuerpo que declaren esa identidad
- **THEN** la superficie se decide por la identidad **realmente** autenticada y la colección permanece eliminada

#### Scenario: La ruta de elemento único sigue disponible para el borrado

- **WHEN** el asistente solicita `GET /wp-json/wp/v2/comments/<id>` o `DELETE /wp-json/wp/v2/comments/<id>` sobre un comentario concreto
- **THEN** la petición alcanza el manejador de elemento único (la restricción de superficie solo afecta a la colección), lo que hace posible la lectura previa y la retirada; la **retirada** del usuario de servicio queda además sujeta a la guarda de procedencia y a la papelera forzada de `valet-moderation`