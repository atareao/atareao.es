# Mastodon Replies Delta

## Purpose

Esta capability absorbe en `atareao-functionality` la importación de respuestas de Mastodon que hoy realiza el plugin de terceros «Replies Importer for Mastodon». Conecta con la instancia por OAuth (scopes `read`), descubre por el RSS de la cuenta los estados que enlazan al sitio, pide el contexto de cada estado y convierte sus descendientes públicos en **comentarios pendientes** de WordPress con su hilo, su autor, la URL del estado, la fecha real y `comment_agent = Mastodon`. Deduplica por `comment_meta` propio y por `author_url` (compatibilidad con lo importado por el legado), sanea el contenido con KSES **conservando los enlaces**, no filtra credenciales al log y migra la conexión legada sin borrarla ni exigir reautorizar, absteniéndose de programar su cron mientras el plugin legado siga cargado y conectado. Todo es admin-only y no altera el sitio público.

## ADDED Requirements

### Requirement: Conexión con la instancia mediante OAuth

El sistema SHALL registrar una aplicación en la instancia con `POST {instancia}/api/v1/apps` enviando `client_name`, `redirect_uris`, `scopes=read` y `website`, donde `redirect_uris` SHALL ser `admin_url('options-general.php?page=atareao-settings&tab=mastodon')`. El sistema SHALL construir la URL de autorización `{instancia}/oauth/authorize?client_id=…&redirect_uri=…&response_type=code&scope=read` y SHALL canjear el código recibido con `POST {instancia}/oauth/token` (`grant_type=authorization_code`, `code`, `client_id`, `client_secret` y el mismo `redirect_uri`). Al desconectar, el sistema SHALL revocar el token con `POST {instancia}/oauth/revoke` antes de borrar sus propias credenciales. La instancia configurada SHALL validarse como `https://`; una instancia vacía o que no sea `https` SHALL NOT usarse para ninguna llamada. El `access_token` y el `client_secret` SHALL NOT registrarse nunca en el log ni en ningún aviso. Las credenciales SHALL almacenarse en opciones propias con prefijo `atareao_mastodon_` y SHALL leerse y escribirse solo desde wp-admin con capacidad `manage_options` y nonce.

#### Scenario: Registro de la app y URL de autorización

- **WHEN** un administrador guarda una instancia `https://mastodon.example` sin `client_id`/`client_secret` y solicita autorizar
- **THEN** el sistema registra la app con `scopes=read` y `redirect_uris=https://sitio/wp-admin/options-general.php?page=atareao-settings&tab=mastodon` y devuelve la URL de autorización con `response_type=code` y `scope=read`

#### Scenario: Canje del código por un token

- **WHEN** la instancia redirige de vuelta a la pestaña `mastodon` con un `code` válido y existen `client_id` y `client_secret`
- **THEN** el sistema canjea el código en `/oauth/token` con el mismo `redirect_uri` y guarda el `access_token` resultante

#### Scenario: Desconexión que revoca el token

- **WHEN** un administrador pulsa «Desconectar» con una conexión activa
- **THEN** el sistema llama a `/oauth/revoke` y borra únicamente sus propias opciones de conexión, sin tocar las del plugin legado

#### Scenario: Instancia no válida

- **WHEN** la instancia configurada está vacía o no empieza por `https://`
- **THEN** el sistema no realiza el registro de la app, ni la autorización, ni el canje, y muestra un error accionable en la pestaña

#### Scenario: Las credenciales no acaban en el log

- **WHEN** el sistema registra un evento o un error con el `debug_mode` activo
- **THEN** la entrada del log usa el prefijo `[atareao-mastodon]` y no contiene el `access_token` ni el `client_secret`

### Requirement: Ajustes, cadencia y cron propio

El sistema SHALL almacenar cada ajuste en una opción propia con prefijo `atareao_mastodon_` (como mínimo `atareao_mastodon_instance_url`, `atareao_mastodon_client_id`, `atareao_mastodon_client_secret`, `atareao_mastodon_access_token`, `atareao_mastodon_schedule_period` y `atareao_mastodon_debug_mode`), con default `''` o `0`, y SHALL sanear cada campo por tipo: URL con `esc_url_raw`, identificadores y textos con `sanitize_text_field`, banderas normalizadas a `0`/`1`. La cadencia SHALL admitir los valores `hourly` y `daily`. El sistema SHALL programar su propio evento con el hook `atareao_mastodon_import` al conectar o guardar la cadencia, SHALL limpiarlo al desconectar y SHALL NOT crear eventos duplicados (comprobando `wp_next_scheduled` antes de agendar). El guardado SHALL procesarse por POST contra la propia pestaña, verificarse con nonce y volver a comprobar `manage_options`.

#### Scenario: Guardado saneado de los ajustes

- **WHEN** un administrador guarda la pestaña «Mastodon» con una URL y una cadencia válidas o inválidas
- **THEN** cada ajuste se persiste saneado por su tipo con su clave `atareao_mastodon_*` y el guardado se rechaza si el nonce no es válido

#### Scenario: Programación al conectar

- **WHEN** la conexión queda completa (instancia y `access_token`) con cadencia `hourly` o `daily`
- **THEN** el sistema agenda `atareao_mastodon_import` con esa recurrencia si no había ya un evento agendado

#### Scenario: Cambio de cadencia sin duplicar

- **WHEN** un administrador cambia la cadencia de `hourly` a `daily`
- **THEN** el sistema limpia el evento anterior y agenda uno nuevo `daily` sin dejar dos eventos activos

#### Scenario: Desconexión limpia el cron

- **WHEN** un administrador desconecta la instancia
- **THEN** el sistema limpia el evento `atareao_mastodon_import` y borra sus opciones de conexión

### Requirement: Importación de respuestas como comentarios pendientes

El sistema SHALL descubrir los estados de la cuenta con `GET {instancia}/api/v1/accounts/verify_credentials` (cabecera `Authorization: Bearer`) y a partir de la URL devuelta SHALL descargar su feed `.rss`. Por cada ítem del RSS cuyo contenido contenga `home_url()`, el sistema SHALL extraer los `href` que apunten al sitio, resolver la entrada con `url_to_postid()`, obtener el identificador del estado del `basename` del enlace del ítem y pedir `GET {instancia}/api/v1/statuses/{id}/context` para recorrer sus `descendants`. Por cada descendiente cuya visibilidad no sea `private` ni `direct`, el sistema SHALL insertar un comentario **pendiente** (`comment_approved = 0`) con `comment_content` saneado, `comment_author` = `display_name`, `comment_author_url` = URL del estado, `comment_parent` resuelto por el mapa de `in_reply_to_id` (estructura de hilo), `comment_agent` = `Mastodon`, `comment_date_gmt` = fecha real del `created_at` y `comment_date` = su equivalente local calculado con `get_date_from_gmt()`, de modo que la fecha mostrada sea la real, y `user_id` = 0. Los descendientes `private` y `direct` SHALL NOT importarse. El sistema SHALL usar `wp_remote_*` con timeout y SHALL validar el código HTTP y el JSON antes de recorrer `descendants`.

#### Scenario: Importación de un hilo con respuestas públicas

- **WHEN** un estado enlaza a una entrada del sitio y su contexto contiene descendientes públicos
- **THEN** cada descendiente se inserta como comentario pendiente de esa entrada, con la URL del estado como `comment_author_url`, el `display_name` como autor, la fecha del `created_at` y `comment_agent = Mastodon`

#### Scenario: Hilo anidado

- **WHEN** un descendiente responde a otro descendiente ya importado en el mismo recorrido
- **THEN** el comentario insertado queda enlazado como hijo del comentario correspondiente mediante `comment_parent`

#### Scenario: Respuestas privadas o directas

- **WHEN** un descendiente tiene visibilidad `private` o `direct`
- **THEN** el sistema no lo inserta y continúa con el resto

#### Scenario: Estados que no enlazan al sitio

- **WHEN** un ítem del RSS no contiene una URL de `home_url()` o la URL no resuelve a una entrada mediante `url_to_postid()`
- **THEN** el sistema lo omite sin insertar ningún comentario

#### Scenario: Respuesta HTTP o JSON inválido

- **WHEN** el contexto de un estado devuelve un código distinto de `200` o un cuerpo que no es JSON válido
- **THEN** el sistema registra el fallo sin secretos, se abstiene de ese estado y continúa con el resto

### Requirement: Dedupe e idempotencia

El sistema SHALL guardar el identificador y/o la URL del estado de Mastodon en un `comment_meta` propio al insertar cada comentario. Antes de insertar, el sistema SHALL descartar el descendiente si ya existe un comentario con esa meta propia **o** si ya existe un comentario con el mismo `comment_author_url` (compatibilidad con los comentarios creados por el plugin legado). Reimportar un mismo estado SHALL NOT duplicar comentarios.

#### Scenario: Comentario ya importado por nosotros

- **WHEN** un descendiente ya fue importado y tiene la meta propia del estado
- **THEN** el sistema no lo vuelve a insertar

#### Scenario: Comentario ya importado por el plugin legado

- **WHEN** un descendiente ya existe como comentario con el mismo `comment_author_url` pero sin la meta propia
- **THEN** el sistema no lo vuelve a insertar

#### Scenario: Reimportar no duplica

- **WHEN** se ejecuta la importación dos veces sobre el mismo estado
- **THEN** el número de comentarios de la entrada no aumenta en la segunda ejecución

### Requirement: Sanitización y seguridad

El contenido remoto SHALL sanearse con KSES (`wp_kses`) usando el conjunto de etiquetas permitidas en comentarios, de modo que se conserven los enlaces que el autor incluye en su respuesta. Los campos remotos usados en el comentario (autor, URL, fecha) SHALL sanearse por tipo antes de insertarse. El sistema SHALL limitar el acceso a la pestaña y a todas sus acciones de guardado, autorización, comprobación y migración a usuarios con capacidad `manage_options` y SHALL verificar un nonce en cada acción. El sistema SHALL NOT registrar el `access_token`, el `client_secret` ni ninguna otra credencial.

#### Scenario: Los enlaces se conservan

- **WHEN** una respuesta contiene un enlace en el HTML y se importa
- **THEN** el comentario insertado conserva el enlace en lugar de perderlo

#### Scenario: HTML no permitido se elimina

- **WHEN** una respuesta contiene etiquetas o atributos no permitidos en comentarios
- **THEN** el contenido se sanea y solo queda el subconjunto permitido, conservando los enlaces

#### Scenario: Usuario sin permisos

- **WHEN** un usuario sin capacidad `manage_options` intenta ver la pestaña o enviar cualquiera de sus acciones
- **THEN** no puede verla ni ejecutar la acción

#### Scenario: Acción sin nonce válido

- **WHEN** se envía una acción de la pestaña con un nonce ausente o inválido
- **THEN** la acción se rechaza y no se modifica ningún ajuste

### Requirement: Migración desde el plugin legado y coexistencia

El sistema SHALL ofrecer una acción de importación a un clic que lea las opciones legadas `replies_importer_for_mastodon_settings` y `replies_importer_for_mastodon_connection`, vuelque sus valores en las opciones propias `atareao_mastodon_*`, **no borre** las opciones legadas e informe de los ajustes importados o de que no encontró nada; la acción SHALL funcionar aunque el plugin legado ya esté desactivado si su opción permanece en la base de datos. Como limpieza, la importación SHALL ejecutar `wp_clear_scheduled_hook('replies_importer_for_mastodon_event')` para no dejar el cron legado huérfano. Mientras el plugin legado esté cargado **y conectado** (instancia y `access_token` presentes), el sistema SHALL NOT programar su propio cron y la pestaña SHALL mostrar un aviso de que hay que retirar el plugin legado al terminar la migración; cuando el legado ya no esté, el sistema SHALL tomar el relevo.

#### Scenario: Importación con la configuración legada presente

- **WHEN** existen `replies_importer_for_mastodon_settings` y/o `replies_importer_for_mastodon_connection` y un administrador pulsa importar
- **THEN** los valores se vuelcan en las claves `atareao_mastodon_*`, las opciones legadas permanecen intactas y se informa de lo importado

#### Scenario: Importación sin nada que importar

- **WHEN** no existe ninguna de las opciones legadas y un administrador pulsa importar
- **THEN** no se modifica ningún ajuste y se informa de que no se encontró nada que importar

#### Scenario: Limpieza del cron legado

- **WHEN** se ejecuta la importación
- **THEN** el sistema limpia el evento `replies_importer_for_mastodon_event`

#### Scenario: Coexistencia con el legado conectado

- **WHEN** el plugin legado está cargado y tiene instancia y `access_token`
- **THEN** el sistema no programa `atareao_mastodon_import` y la pestaña avisa de que hay que retirar el plugin legado tras migrar

#### Scenario: Relevo tras retirar el legado

- **WHEN** el plugin legado deja de estar cargado y la conexión propia está configurada
- **THEN** el sistema programa su cron y pasa a importar por sí mismo

### Requirement: Errores accionables sin secretos

El sistema SHALL capturar y registrar sin secretos los fallos de red (`WP_Error`), los códigos HTTP distintos de 2xx y los cuerpos que no sean JSON válido en cualquier llamada a la instancia, y SHALL mostrar en la pestaña un mensaje comprensible que indique qué pasó y qué revisar. Los avisos SHALL NOT contener credenciales.

#### Scenario: Fallo de red

- **WHEN** una llamada a la instancia devuelve un `WP_Error` (timeout o DNS)
- **THEN** el sistema registra el fallo sin secretos y muestra en la pestaña un mensaje accionable

#### Scenario: Respuesta HTTP de error

- **WHEN** la instancia responde con un código distinto de 2xx
- **THEN** el sistema registra el código y el contexto del endpoint sin secretos y avisa en la pestaña

#### Scenario: JSON mal formado

- **WHEN** la instancia responde con un cuerpo que no se puede decodificar como JSON
- **THEN** el sistema trata el resultado como error, lo registra sin secretos y continúa sin insertar comentarios
