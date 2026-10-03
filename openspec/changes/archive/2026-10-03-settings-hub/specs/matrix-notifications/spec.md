# Matrix Notifications Delta

## Purpose

Esta capability caracteriza y documenta el comportamiento real del módulo `\Atareao\MatrixConfig` del plugin `atareao-functionality`: el almacenamiento de las credenciales de la API de Matrix (URL, access token y room) desde wp-admin, el envío de mensajes a una sala mediante la Client-Server API, el mensaje de prueba y la notificación automática cuando se publica un comentario aprobado. La UI del módulo pasa a mostrarse en la pestaña `matrix` del hub de ajustes «Atareao»; el contrato de opciones, saneado, endpoint, cabeceras, payload y valor de retorno se conservan sin cambios. Esta capability se documenta como paso de caracterización (Phase 0) previo a refactorizar la ubicación de su pantalla.

## ADDED Requirements

### Requirement: Ajustes de Matrix y guardado por POST

El sistema SHALL almacenar las credenciales de Matrix en las opciones `atareao_matrix_url`, `atareao_matrix_token` y `atareao_matrix_room`. El formulario SHALL guardarse por POST contra su propia página con el nonce `atareao_matrix_config`, comprobar `manage_options` mediante `check_admin_referer` y sanear cada campo con `sanitize_text_field`. La interfaz del módulo SHALL mostrarse en la pestaña `matrix` del hub de ajustes «Atareao»; este change elimina su página propia en Ajustes y su envoltorio, de modo que la ubicación de la UI se describe por referencia a esa pestaña y no como una página independiente. Cuando el guardado se complete, el sistema SHALL mostrar el aviso «Configuración guardada.».

#### Scenario: Guardado con nonce válido

- **WHEN** un administrador envía el formulario de la pestaña «Matrix» con un nonce `atareao_matrix_config` válido
- **THEN** las opciones `atareao_matrix_url`, `atareao_matrix_token` y `atareao_matrix_room` se actualizan con los valores saneados y se muestra el aviso de configuración guardada

#### Scenario: Nonce inválido

- **WHEN** el POST llega sin nonce o con un nonce que no es válido
- **THEN** `check_admin_referer` interrumpe la petición y no se actualiza ninguna opción

#### Scenario: Usuario sin permisos

- **WHEN** un usuario sin capacidad `manage_options` abre la pestaña «Matrix»
- **THEN** el render no imprime el formulario ni permite guardar

#### Scenario: Saneado de los campos

- **WHEN** se guarda un valor con etiquetas HTML o espacios sobrantes en cualquiera de los tres campos
- **THEN** el valor almacenado es el resultado de `sanitize_text_field` sobre la entrada

### Requirement: Contrato de envío de mensajes a Matrix

El sistema SHALL exponer `sendMatrixMessage($message_body)` como el contrato de envío del módulo. El método SHALL leer y sanear con `sanitize_text_field` las tres credenciales y, si alguna está vacía, SHALL devolver el mensaje «Configura la URL, Token y Room ID antes de enviar.» sin realizar ninguna petición de red. Con credenciales completas, SHALL construir el endpoint `{url}/_matrix/client/v3/rooms/{room}/send/m.room.message/{txn}` (con `rtrim` de la barra final en la URL y un identificador de transacción generado con `uniqid('wp_', true)`) y SHALL realizar una petición `PUT` con cuerpo JSON `{"msgtype":"m.text","body":$message_body}`, cabeceras `Authorization: Bearer <token>` y `Content-Type: application/json`, y un timeout de 10 segundos. El método SHALL devolver `true` cuando el código de respuesta esté entre 200 y 299, y SHALL devolver una cadena de error («Error al enviar el mensaje. Intentalo de nuevo mas tarde.») ante un `WP_Error` o un código fuera de ese rango.

#### Scenario: Envío correcto

- **WHEN** las tres credenciales están configuradas y la petición `PUT` al endpoint de Matrix responde con un código 2xx
- **THEN** `sendMatrixMessage()` devuelve `true`

#### Scenario: Respuesta HTTP fuera del rango 2xx

- **WHEN** Matrix responde con un código 4xx o 5xx
- **THEN** `sendMatrixMessage()` devuelve la cadena de error genérica

#### Scenario: Error de red

- **WHEN** `wp_remote_request` devuelve un `WP_Error` (host inalcanzable, timeout, etc.)
- **THEN** `sendMatrixMessage()` devuelve la cadena de error genérica

#### Scenario: Credenciales incompletas

- **WHEN** falta la URL, el token o el room
- **THEN** `sendMatrixMessage()` devuelve el mensaje «Configura la URL, Token y Room ID antes de enviar.» y no realiza ninguna petición HTTP

### Requirement: Mensaje de prueba, notificación de comentarios y reutilización

El sistema SHALL ofrecer en la pestaña «Matrix» un botón «Enviar mensaje de prueba» que envía un texto identificando el host del sitio y muestra el aviso de éxito o el error devuelto. El módulo SHALL enganchar `notifyOnComment` en la acción `comment_post` y SHALL notificar únicamente los comentarios aprobados (`1`), obteniendo el autor, el texto sin etiquetas y la URL de la entrada; los comentarios no aprobados SHALL NOT generar notificación. El contrato `MatrixConfig::sendMatrixMessage()` SHALL ser reutilizado por otros módulos del plugin; en particular `\Atareao\ContactForm` lo invoca para entregar el formulario de contacto.

#### Scenario: Botón de prueba

- **WHEN** un administrador pulsa «Enviar mensaje de prueba» con un nonce válido
- **THEN** el sistema envía un mensaje de prueba y muestra un aviso de éxito si se envía o el error devuelto en caso contrario

#### Scenario: Comentario aprobado

- **WHEN** se publica un comentario aprobado (`comment_approved` = `1`)
- **THEN** el módulo envía a Matrix una notificación con el autor, la URL de la entrada y el texto del comentario

#### Scenario: Comentario pendiente o spam

- **WHEN** `comment_approved` no es `1` (pendiente, spam o papelera)
- **THEN** el módulo no envía ninguna notificación

#### Scenario: Reutilización por el formulario de contacto

- **WHEN** se envía el formulario de contacto y pasa sus validaciones
- **THEN** `\Atareao\ContactForm` entrega el mensaje llamando a `MatrixConfig::sendMatrixMessage()` y trata un retorno `true` como éxito
