# valet-notifications Specification

## Purpose
Esta capability define el módulo `\Atareao\ValetConfig` del plugin `atareao-functionality` para la integración con Valet (`/data/rust/valet`): almacenamiento de credenciales (URL base del servidor Valet y token de ingesta de aplicación), envío de notificaciones HTTP al endpoint `POST /api/notifications` con autenticación Bearer y validación de tipos y límites, botón de prueba en la interfaz de administración, notificación automática al publicar comentarios aprobados y entrega de mensajes enviados mediante el formulario de contacto.

## Requirements

### Requirement: Ajustes de Valet y guardado por POST

El sistema SHALL almacenar las credenciales de Valet en las opciones `atareao_valet_url` y `atareao_valet_token`. El formulario SHALL guardarse por POST contra su propia pestaña con el nonce `atareao_valet_config`, comprobar la capacidad `manage_options` mediante `check_admin_referer` y sanear la URL con `esc_url_raw` y el token con `sanitize_text_field`. La interfaz del módulo SHALL mostrarse en la pestaña `valet` del hub de ajustes «Atareao», sin contenedor `.wrap` ni `<h1>`. Cuando el guardado se complete correctamente, el sistema SHALL mostrar el aviso «Configuración guardada.».

El campo del token SHALL renderizarse enmascarado (`type="password"`, `autocomplete="new-password"` y placeholder `••••••••`) y SHALL NOT llevar atributo `value` ni reimprimir en el HTML el token almacenado. En consecuencia, un POST con el token vacío o compuesto solo por espacios SHALL conservar el token anterior sin borrarlo: la opción `atareao_valet_token` SHALL actualizarse únicamente cuando el valor recibido, una vez recortado con `trim`, sea no vacío.

#### Scenario: Guardado con nonce válido

- **WHEN** un administrador envía el formulario de la pestaña «Valet» con un nonce `atareao_valet_config` válido
- **THEN** las opciones `atareao_valet_url` y `atareao_valet_token` se actualizan con los valores saneados y se muestra el aviso de configuración guardada

#### Scenario: Nonce inválido

- **WHEN** el POST llega sin nonce o con un nonce que no es válido
- **THEN** `check_admin_referer` interrumpe la petición y no se actualiza ninguna opción

#### Scenario: Usuario sin permisos

- **WHEN** un usuario sin capacidad `manage_options` abre la pestaña «Valet»
- **THEN** el render no imprime el formulario ni permite guardar

#### Scenario: Saneado de los campos

- **WHEN** se guardan valores con caracteres o espacios no válidos
- **THEN** la URL se normaliza y sanea con `esc_url_raw` y el token con `sanitize_text_field`

#### Scenario: El token nunca se reimprime en el HTML de la pestaña

- **WHEN** un administrador abre la pestaña «Valet» habiendo un token almacenado
- **THEN** el campo del token se sirve con `type="password"`, `autocomplete="new-password"` y sin atributo `value`, y el valor almacenado no aparece en el HTML

#### Scenario: POST con token vacío conserva el valor anterior

- **WHEN** un administrador guarda la pestaña «Valet» dejando el campo del token vacío o con solo espacios
- **THEN** la opción `atareao_valet_token` conserva el valor anterior y no se borra

### Requirement: Contrato de envío de notificaciones a Valet

El sistema SHALL exponer `ValetConfig::sendValetNotification($message, $origin = '', $author = '', $level = 'info')` como el contrato de envío del módulo. El método SHALL leer las opciones `atareao_valet_url` y `atareao_valet_token`; si alguna está vacía, SHALL devolver el mensaje «Configura la URL y el Token de Valet antes de enviar.» sin realizar ninguna petición HTTP.

El método SHALL sanear y recortar los parámetros:
- `$message`: obligatorio; si tras `trim` queda vacío o excede los 4096 bytes, SHALL devolver una cadena de error descriptiva.
- `$origin`: opcional; si está vacío, SHALL usar por defecto el host del sitio (`parse_url(home_url(), PHP_URL_HOST) ?: 'atareao.es'`), con una longitud máxima de 64 bytes.
- `$author`: opcional, con longitud máxima de 128 bytes.
- `$level`: opcional; SHALL validar contra la whitelist `['info', 'warning', 'critical']`, utilizando `'info'` si el valor proporcionado no es válido.

Con credenciales completas y parámetros válidos, el método SHALL construir el endpoint `{url}/api/notifications` (eliminando barras finales de la URL base) y realizar una petición `POST` con cuerpo JSON codificando los campos `message`, `origin`, `author` y `level`, cabeceras `Authorization: Bearer <token>` y `Content-Type: application/json`, y un timeout de 10 segundos.

El método SHALL devolver `true` cuando el código de respuesta HTTP esté entre 200 y 299 (típicamente `201 Created`), y devolver una cadena de error («Error al enviar la notificación a Valet. Inténtalo de nuevo más tarde.») ante un `WP_Error` o un código HTTP fuera de ese rango.

#### Scenario: Envío correcto con respuesta 201

- **WHEN** las credenciales están configuradas y la petición `POST` al endpoint `/api/notifications` de Valet responde con código `201`
- **THEN** `sendValetNotification()` devuelve `true`

#### Scenario: Respuesta HTTP de error (4xx o 5xx)

- **WHEN** Valet responde con un código de error como `401 Unauthorized` o `500 Internal Server Error`
- **THEN** `sendValetNotification()` devuelve una cadena de error y no produce excepciones fatales

#### Scenario: Error de red o timeout

- **WHEN** `wp_remote_post` devuelve un `WP_Error` (servidor inalcanzable, timeout)
- **THEN** `sendValetNotification()` devuelve la cadena de error

#### Scenario: Credenciales incompletas

- **WHEN** falta la URL o el token de Valet
- **THEN** `sendValetNotification()` devuelve el mensaje «Configura la URL y el Token de Valet antes de enviar.» y no realiza ninguna petición de red

#### Scenario: Mensaje vacío

- **WHEN** `$message` está vacío o solo contiene espacios
- **THEN** `sendValetNotification()` devuelve un mensaje de error indicando que el mensaje es obligatorio y no realiza petición de red

### Requirement: Presupuesto de bytes y recorte del mensaje de comentario

El mensaje construido para notificar un comentario SHALL NOT superar `MESSAGE_MAX_BYTES` (4096) bytes. El mensaje SHALL componerse de una cabecera —`Comentario de <autor> en <host>`, salto de línea, permalink de la entrada y salto de línea— seguida del contenido del comentario recortado al presupuesto restante. Cuando el contenido se recorta, el sistema SHALL añadir el sufijo `…` (U+2026). El recorte SHALL ser byte-safe: SHALL NOT partir un carácter multibyte (p. ej. mediante `mb_strcut()` o descartando la secuencia UTF-8 incompleta final). La notificación SHALL NOT descartarse por longitud: al respetar el presupuesto por construcción nunca se supera el límite duro de `sendValetNotification()`, que en caso contrario devolvería error y perdería el aviso.

#### Scenario: Contenido muy largo se recorta con sufijo y no se descarta

- **WHEN** un comentario aprobado tiene un contenido que excede el presupuesto disponible
- **THEN** el mensaje enviado no supera los 4096 bytes, el contenido recortado termina con el sufijo `…` y la notificación se envía igualmente (no se descarta por longitud)

#### Scenario: Recorte byte-safe con contenido multibyte

- **WHEN** el punto de corte del contenido cae sobre caracteres multibyte (p. ej. acentos o emoji)
- **THEN** el recorte no parte ningún carácter UTF-8 y el mensaje resultante conserva una codificación válida

### Requirement: Mensaje de prueba, notificación de comentarios y formulario de contacto

El sistema SHALL ofrecer en la pestaña «Valet» un botón «Enviar mensaje de prueba» (`atareao_valet_test`) que envía una notificación de prueba identificando el host del sitio y muestra el aviso de éxito o el error devuelto.

La notificación de comentarios SHALL engancharse en DOS acciones complementarias que se reparten los dos caminos por los que un comentario queda aprobado:

- `comment_post` (método `notifyOnComment`): WordPress dispara esta acción al **insertar** el comentario; notifica cuando el comentario queda aprobado en la inserción (`comment_approved` = `1`).
- `transition_comment_status` (método `notifyOnCommentTransition`): WordPress dispara esta acción desde `wp_set_comment_status()`; notifica **solo** en la transición hacia `approved`, es decir, cuando `$new_status === 'approved'` y `$old_status !== 'approved'`, de modo que un comentario pendiente aprobado a posteriori desde el panel también despache.

Ambas rutas SHALL compartir el mismo helper privado (`notifyApprovedComment`) que construye y envía la notificación con el autor del comentario, la URL de la entrada y el texto del comentario, con nivel `'info'`, respetando el presupuesto de bytes del mensaje de comentario. Las dos rutas son mutuamente excluyentes —un comentario insertado ya aprobado pasa por `comment_post`, y uno aprobado después pasa por la transición— por lo que el sistema SHALL NOT generar doble notificación; una transición repetida `approved → approved` o cualquier transición a un estado no aprobado (`hold`, `spam`, `trash`) SHALL ignorarse.

El contrato `ValetConfig::sendValetNotification()` SHALL ser integrado en `\Atareao\ContactForm`: cuando el formulario de contacto se envíe correctamente y pase las validaciones antiabuso y captcha, si las credenciales de Valet están configuradas, SHALL enviar una notificación a Valet con autor, host y contenido del mensaje, de forma complementaria al envío a Matrix.

#### Scenario: Botón de prueba

- **WHEN** un administrador pulsa «Enviar mensaje de prueba» con nonce válido en la pestaña «Valet»
- **THEN** el sistema envía una notificación de prueba a Valet y muestra aviso de éxito o mensaje de error

#### Scenario: Comentario aprobado notifica a Valet

- **WHEN** se publica un comentario aprobado (`comment_approved` = `1`) y Valet está configurado
- **THEN** el módulo envía a Valet una notificación con autor, permalink del post y texto del comentario

#### Scenario: Comentario no aprobado no notifica a Valet

- **WHEN** se publica un comentario pendiente, marcado como spam o en papelera (`comment_approved` != `1`)
- **THEN** no se envía ninguna notificación a Valet

#### Scenario: Comentario aprobado a posteriori desde el panel notifica a Valet

- **WHEN** un comentario pendiente se aprueba después desde el panel (transición `hold` → `approved` vía `wp_set_comment_status()`)
- **THEN** el módulo envía a Valet una notificación con autor, permalink del post y texto del comentario

#### Scenario: Transición a un estado no aprobado no notifica a Valet

- **WHEN** un comentario transiciona a `hold`, `spam` o `trash` (`$new_status !== 'approved'`)
- **THEN** no se envía ninguna notificación a Valet

#### Scenario: Sin doble notificación por aprobación

- **WHEN** un comentario insertado ya aprobado dispara `comment_post`, o una transición repite `approved → approved`
- **THEN** se envía una única notificación a Valet (la transición repetida se ignora y no hay solape entre los dos hooks)

#### Scenario: Entrega desde el formulario de contacto

- **WHEN** se envía un formulario de contacto válido y Valet está configurado
- **THEN** `ContactForm` envía la notificación a Valet con el remitente y contenido del formulario
