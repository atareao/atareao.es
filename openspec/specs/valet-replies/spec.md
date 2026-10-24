# valet-replies Specification

## Purpose
Esta capability define el **camino inverso** del canal con Valet (`/data/rust/valet`): la publicación en WordPress de la respuesta que redacta el asistente a un comentario notificado. Fija el contrato de publicación (REST core autenticada con Application Password, `status: approved`), la **identidad de servicio con privilegio mínimo**, el **marcado de procedencia con guarda anti-bucle** —para que una respuesta del asistente nunca se notifique a sí misma— y una **cota de escritura** que sustituye al anti-abuse, que por construcción no cubre esta vía.

**Reparto de responsabilidades (importante para no implementar aquí un cliente).** Este sitio **implementa** las **garantías de aceptación** —el sellado de procedencia de los comentarios del asistente, la guarda anti-bucle que impide notificarlos y la cota de escritura de esa vía— y el **aprovisionamiento documentado** de la identidad de servicio (usuario dedicado, rol mínimo y Application Password). Lo que **no** implementa este repositorio es la **petición de publicación**: emitir el `POST /wp-json/wp/v2/comments` corresponde al **consumidor**, el servicio **Valet**, cuya implementación vive en `/data/rust/valet` (con su propio change) y queda **fuera de alcance** de esta capability. Aquí la petición es **contrato del contrario**: se especifica lo que el sitio acepta y hace cumplir, no se construye ningún cliente REST en PHP ni se almacena ninguna credencial de servicio en el sitio (la Application Password la custodia Valet).

## Requirements

### Requirement: Contrato de respuesta del asistente

La obligación de **emitir** esta petición corresponde al **consumidor** —el servicio Valet (`/data/rust/valet`), que es quien redacta y publica la respuesta y quien custodia la Application Password—: este requirement especifica el **contrato del contrario**, no código de este repositorio. Este sitio **acepta y hace cumplir** ese contrato a través de las garantías de los otros requirements de esta capability (sellado de procedencia con guarda anti-bucle, identidad de servicio y cota de escritura); no implementa ningún cliente REST en PHP.

La respuesta del asistente SHALL publicarse mediante una petición `POST` al controlador REST de comentarios del core, `POST /wp-json/wp/v2/comments`, con los campos `post` = ID de la entrada (`post_id` recibido en la notificación, **dentro de su objeto `metadata`**) y `parent` = ID del comentario notificado (`metadata.comment_id` de esa misma notificación), y `status` = `approved`, de modo que la respuesta se publique de forma **directa, sin pasar por moderación**.

La petición SHALL autenticarse con una **Application Password** de WordPress (autenticación HTTP Basic contra la API REST). SHALL NOT añadirse ninguna ruta pública nueva a la lista de exenciones de la puerta REST anónima (`rest-access-policy`): esa puerta ya deja pasar las peticiones autenticadas por Application Password, y una ruta exenta de escritura sería un **agujero anónimo** en el sitio.

El sistema SHALL asumir y documentar que el anti-abuse de comentarios (`preprocess_comment`) **no se aplica** a esta vía: ese filtro lo ejecuta `wp_new_comment()`, y el controlador REST de comentarios no pasa por él. Es una propiedad del core, no una decisión de este change; en consecuencia, la vía de respuesta SHALL NOT depender del anti-abuse para su seguridad y SHALL apoyar su acotación en el requirement «Cota de escritura en la vía de respuesta».

**Superficie de la colección por identidad.** El contrato de este requirement SHALL ser **alcanzable**: el sitio SHALL NOT eliminar la ruta de colección `/wp/v2/comments` de forma incondicional. El filtro `rest_endpoints` SHALL resolver la superficie de esa colección **por identidad del usuario autenticado** en la petición, con estas tres reglas:

- **Sin usuario de servicio configurado** —la opción del identificador de servicio (`atareao_valet_reply_user`) ausente, vacía o `0`—, la colección SHALL eliminarse **por completo**: **falla a cerrado**, porque sin identidad de servicio no hay consumidor legítimo de esa vía.
- **Usuario autenticado que ES el usuario de servicio**: la colección SHALL conservar **verbatim** las claves no numéricas de la ruta (`schema`, `namespace`, opciones) y SHALL conservar **únicamente** el grupo de manejador cuyo `methods` contiene `POST` y **no** contiene `GET` —un `methods` puede llegar como cadena con comas o como array, y la comprobación SHALL normalizar ambos—, descartando el grupo de `GET` (el listado). Si no sobrevive ningún grupo numérico, la ruta SHALL eliminarse **entera**. El asistente SHALL poder **publicar** y SHALL NOT poder **enumerar** comentarios.
- **Cualquier otro usuario** —autenticado o anónimo—: la colección SHALL seguir eliminada **entera**, sin cambio alguno respecto del comportamiento anterior.

**Cierre de la enumeración frente al alcance del borrado (corrección de redacción).** El cierre a la **enumeración** —el asistente no puede obtener el listado de comentarios— es una garantía **real y comprobable**: la colección SHALL NOT ofrecer `GET` al usuario de servicio. En cambio, que el asistente retire «solo lo que llegó por notificación» **SHALL NOT** considerarse garantía del sitio: es **disciplina del consumidor** —los identificadores solo llegan en el `metadata` de la notificación— más la **aprobación explícita** por herramienta. Motivo, declarado sin adornos: la ruta de **elemento único** (`/wp/v2/comments/<id>`), que este requirement conserva a propósito, solo exige `moderate_comments`, de modo que una credencial de servicio comprometida puede **recorrer identificadores** y mandar a la papelera **cualquier** comentario —no solo los notificados— sin cota alguna. Es una condición **preexistente** (el elemento nunca se filtró) y se acepta como **riesgo residual**; el control *server-side* queda como trabajo siguiente, fuera de este change. La operación es **reversible** (30 días) **solo** mientras el consumidor envíe `force=false`; con `force=true` el core **destruye** el comentario de forma **irreversible** (`class-wp-rest-comments-controller.php:1071,1087-1089`), y el sitio **no** lo impide: es **disciplina del consumidor**, no una garantía del sitio.

La resolución SHALL basarse en la **identidad del usuario ya autenticado** y SHALL NOT aceptar ninguna indicación aportada por el cliente (parámetro, cabecera o cuerpo) para decidir si la superficie se abre. La implementación SHALL apoyarse en que `check_authentication()` se ejecuta **antes** de `dispatch()`, de modo que la identidad está resuelta cuando el filtro corre.

La ruta de **elemento único** (`/wp/v2/comments/<id>`, con `GET` y `DELETE`) SHALL NOT verse afectada por esta decisión: sigue disponible, y es la que sostiene la lectura previa y el borrado reversible especificados en la capability `valet-moderation`.

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
- **THEN** la petición alcanza el manejador de elemento único (la restricción de superficie solo afecta a la colección), lo que hace posible la lectura previa y el borrado reversible

### Requirement: Identidad de servicio y privilegio mínimo

La publicación de respuestas SHALL realizarse con un **usuario dedicado** al asistente, **distinto** de las cuentas personales de los administradores del sitio. Ese usuario SHALL tener un rol que conceda **únicamente** lo imprescindible (`read` y `moderate_comments`, necesario porque `status: approved` exige `moderate_comments`) y SHALL NOT ser `editor` ni `administrator`.

La **Application Password** de ese usuario SHALL ser revocable de forma **independiente**, sin afectar al resto del sitio ni a otras integraciones. La Application Password SHALL NOT reutilizarse como credencial de salida: el token Bearer con el que se notifica a Valet (`atareao_valet_token`, almacenado en `wp_options`) es la dirección de confianza **contraria** (WordPress → Valet) y SHALL NOT emplearse como credencial de esta vía.

#### Scenario: Rol de servicio con el mínimo imprescindible

- **WHEN** se inspeccionan las capacidades del rol del usuario de servicio
- **THEN** el rol incluye `read` y `moderate_comments`, y no incluye capacidades de edición de contenido (`edit_posts`, `edit_others_posts`, `manage_options`), ni es `editor` ni `administrator`

#### Scenario: La Application Password se revoca sin efectos colaterales

- **WHEN** se revoca la Application Password del usuario de servicio
- **THEN** la vía de respuesta deja de autenticarse (las peticiones fallan con error y no se publica comentario), mientras el resto del sitio, el canal de notificación a Valet y las cuentas personales siguen funcionando sin cambios

#### Scenario: Las credenciales de ida no sirven como credenciales de vuelta

- **WHEN** se intenta autenticar la llamada REST usando el valor de `atareao_valet_token` (token Bearer de ingreso en Valet)
- **THEN** la autenticación falla: la credencial de salida no es válida como Application Password de WordPress

### Requirement: Marcado de procedencia y guarda anti-bucle

Los comentarios creados por el asistente SHALL quedar **marcados** de forma que el módulo de notificaciones pueda identificarlos como propios. La marca SHALL aplicarla el **servidor**, sobrescribiendo cualquier valor que envíe el cliente, de modo que un cliente no pueda decidir si su comentario queda marcado o no. La marca SHALL aplicarse **únicamente** a los comentarios publicados por la **identidad de servicio** del asistente; los comentarios de cualquier otro autor SHALL quedar **sin marcar** y SHALL seguir notificándose con el comportamiento ya especificado.

El módulo de notificaciones SHALL NOT despachar ninguna notificación para un comentario que lleve esa marca. En consecuencia, una respuesta del asistente **nunca** podrá provocar otra notificación a Valet —ni por `comment_post` ni por `transition_comment_status`— y no podrá realimentar el bucle ni consumir el presupuesto de la ruta de comentarios. Un comentario sin la marca SHALL seguir notificándose con el comportamiento ya especificado.

La implementación SHALL sellar el comentario en `rest_pre_insert_comment`, sobre el dato que se va a insertar, de modo que la marca **viaje con la inserción** y esté presente en el instante de cualquier transición de estado. La implementación SHALL NOT apoyarse en `rest_after_insert_comment`: el controlador REST lo ejecuta **después** de resolver el estado pedido, de modo que una marca persistida en ese punto llegaría tarde para una respuesta que nace pendiente y se aprueba dentro de la misma petición (la guarda la vería ausente y notificaría). La implementación SHALL NOT depender de campos que el controlador REST pudiera no rellenar.

#### Scenario: Una respuesta sellada no notifica por comment_post

- **WHEN** el asistente publica una respuesta ya aprobada (la creación dispara `comment_post`) y Valet está configurado
- **THEN** el módulo de notificaciones no envía ninguna notificación: el comentario lleva la marca de procedencia

#### Scenario: Una respuesta sellada no notifica por transition_comment_status

- **WHEN** un comentario marcado como del asistente provoca una transición hacia `approved`
- **THEN** el módulo de notificaciones no envía ninguna notificación

#### Scenario: Una respuesta sellada que nace pendiente y se aprueba en la misma petición tampoco notifica

- **WHEN** el asistente publica una respuesta que se inserta como pendiente y el controlador REST la aprueba a continuación dentro de la misma petición (transición hacia `approved`), de modo que la vía `comment_post` no llega a notificar por no estar aprobada al insertarse
- **THEN** el módulo de notificaciones no envía ninguna notificación por la vía `transition_comment_status`, porque la marca de procedencia ya viajó con la inserción y la guarda la encuentra presente antes de cualquier transición

#### Scenario: Un comentario normal sigue notificando

- **WHEN** se publica un comentario aprobado de un visitante, sin marca de procedencia
- **THEN** el módulo envía la notificación a Valet con normalidad (la guarda anti-bucle no altera el comportamiento previo)

#### Scenario: El cliente no puede publicar sin la marca por esta vía

- **WHEN** una petición a `POST /wp-json/wp/v2/comments` intenta enviar explícitamente un valor de la marca que indique «comentario de visitante» (o lo omite)
- **THEN** el comentario queda marcado igualmente como procedente del asistente, porque el servidor sobrescribe el valor del cliente

#### Scenario: La marca no depende de campos que el controlador REST pudiera no rellenar

- **WHEN** se revisa la implementación del sellado
- **THEN** el sellado se aplica en `rest_pre_insert_comment`, viaja **con la inserción** (no en `rest_after_insert_comment`, que corre después de resolver el estado) y no en campos opcionales que el controlador pudiera dejar vacíos

### Requirement: Cota de escritura en la vía de respuesta

Puesto que la vía de respuesta esquiva el anti-abuse y WordPress no acota las escrituras autenticadas, el sistema SHALL acotar el número de comentarios publicados por el asistente dentro de una **ventana temporal**. El límite y la ventana SHALL ser **configurables por filtro**, con valores por defecto sensatos.

Al alcanzarse la cota, la petición SHALL rechazarse **sin** crear el comentario, de modo que la acotación nunca se materialice como comentarios publicados de más.

El contador SHALL seguir el patrón ya existente en el repositorio para límites por ventana: clave derivada de un identificador estable e **hash salado**, sin almacenar jamás datos en claro ni datos personales.

#### Scenario: Dentro de la cota la respuesta se publica

- **WHEN** el asistente publica una respuesta y no se ha alcanzado el límite de la ventana
- **THEN** el contador de la ventana se incrementa y el comentario se crea

#### Scenario: Al alcanzar la cota se rechaza sin crear el comentario

- **WHEN** el asistente intenta publicar una respuesta habiendo agotado el límite de la ventana
- **THEN** la petición se rechaza con un error y **no se crea** ningún comentario

#### Scenario: Límite y ventana configurables por filtro

- **WHEN** un sitio filtra los valores de límite o de ventana (p. ej. a 3 respuestas por 600 segundos)
- **THEN** la acotación se comporta conforme a los valores filtrados, y los valores por defecto se restauran al retirar el filtro

#### Scenario: El contador no almacena datos en claro

- **WHEN** se inspecciona la clave del contador usada por la cota
- **THEN** la clave contiene únicamente un hash salado derivado de un identificador estable, sin direcciones ni datos personales en claro
