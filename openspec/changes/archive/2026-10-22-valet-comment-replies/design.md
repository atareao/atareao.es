# Design

## Context

Ver `proposal.md` para la motivación. Los hechos del estado actual que condicionan este diseño:

- El módulo `\Atareao\ValetConfig` engancha `comment_post` (`includes/class-valet-config.php:84`) y `transition_comment_status` (`:85`), y ambas rutas desembocan en el helper privado `notifyApprovedComment()` (`:447`), que **no mira quién escribió** el comentario: solo comprueba que el tipo sea `comment` y que la cota de notificaciones lo permita. El payload actual no lleva identificador del comentario.
- La puerta REST anónima vive en `atareao-functionality.php:170` (`rest_authentication_errors`): rechaza con `401` toda petición anónima cuyo método efectivo no sea de lectura, y exime por **igualdad exacta de ruta normalizada** contra una lista que solo contiene `/atareao/v1/mcp` (`:155-161`). Por diseño, las peticiones autenticadas con Application Password no se ven afectadas (declarado en `openspec/specs/rest-access-policy/spec.md`, scenario «Application Password sigue funcionando»).
- El anti-abuse de comentarios engancha `preprocess_comment` (`includes/class-comment-security.php:28`). Ese filtro lo ejecuta `wp_new_comment()`, **no** el controlador REST de comentarios: la vía REST esquiva captcha, honeypot y timing.
- El precedente de la casa para «servicio externo → comentario en WordPress» es `includes/class-mastodon-replies.php:1101`: `wp_insert_comment()` con `wp_kses($content, wp_kses_allowed_html('comment'))`, `comment_agent => 'Mastodon'` y `comment_approved => 0`.
- El plugin **no tiene autoloader**: cada clase nueva exigiría su `require_once` manual. **Este change no añade ninguna clase ni fichero nuevo**: el camino de respuesta lo recorre el servicio Valet (ver D1) y lo que aquí se implementa vive en ficheros ya cargados (el módulo de notificaciones y los enganches REST del core).
- El módulo ya tiene resuelto un patrón de límite por ventana con contador y hash salado (`getRateLimitKey()`, `commentRateLimitAllows()`), reutilizable.

### Hechos verificados en el consumidor (`/data/rust/valet`)

Comprobados leyendo el repositorio del consumidor (evidencia con `fichero:línea`; los números de línea se citan tal como estaban en el momento de la verificación):

1. **El struct de ingesta no declara los campos auxiliares que se le quieran añadir.** `IngestRequest` (`/data/rust/valet/src/routes/notifications.rs:73-83`) tiene exactamente `message`, `origin`, `author`, `level` y `metadata`. **No declara `comment_id`, ni `post_id`, ni `untrusted`, y no existe un solo `deny_unknown_fields` en todo el repositorio** (verificado con `grep -rn "deny_unknown_fields" --include=*.rs`).
2. **`ingest()` persiste solo cinco campos.** `validate()` + `NotificationsRepo::insert()` (`:110-118`) guardan `origin`, `author`, `message`, `level` y `metadata`; `dispatch_push()` (`:120-126`) despacha únicamente `origin`, `author` y `message`.
3. **Consecuencia dura:** cualquier campo de **primer nivel** distinto de esos cinco **se acepta y se descarta en silencio** —sin error y sin rastro—. Por tanto, tal como estaba especificado antes, un `comment_id`/`post_id` de primer nivel **nunca llegaría** al consumidor, y el `untrusted` de primer nivel enviado por el change anterior (`2026-10-17-valet-notifications-hardening`) **jamás llegó** tampoco: era un campo **inerte**. Lo que realmente mitigó SEC-001 fue la **cabecera literal dentro de `message`**, que sí viaja (es uno de los campos que se persiste y se despacha).
4. **La vía correcta es `metadata`, y ya está soportada de punta a punta — no requiere tocar Rust**: `POST /api/notifications` la acepta exigiendo que sea **objeto JSON** (un array responde `400`) y con su propio límite `MAX_METADATA_BYTES` (`:37`, `:158-182`); se **persiste** (`INSERT INTO notifications (..., metadata)`, `/data/rust/valet/src/db/repos/notifications.rs:41`) y se **expone** en `GET /api/notifications/unread` —el `SELECT` incluye `metadata` (`:61` y `:133`) y la propia spec de Valet (`/data/rust/valet/openspec/specs/notifications/spec.md:182`) declara que la lectura SHALL exponer `id, origin, author, message, level, metadata`—.
5. **Alineación de límites (hecho positivo):** `MAX_MESSAGE_BYTES = 4096` (`:28`) **coincide** con el `MESSAGE_MAX_BYTES` del lado WordPress: los dos extremos ya están alineados y el presupuesto byte-safe del PHP no puede rebotar en el consumidor.

Estado: `git status --porcelain` limpio en `/data/rust/valet`, rama `development`. **En este change no se toca el repositorio Valet.**

### Hechos verificados en el core de WordPress (tarea 1.1, bloque A)

**De dónde se ha leído el core.** No está en la imagen cacheada `docker.io/library/wordpress:cli-php8.3`:

```
$ podman run --rm --entrypoint sh docker.io/library/wordpress:cli-php8.3 -c 'ls /var/www/html/wp-includes/comment.php /usr/src/wordpress/wp-includes/comment.php'
ls: /var/www/html/wp-includes/comment.php: No such file or directory
ls: /usr/src/wordpress/wp-includes/comment.php: No such file or directory
```

(`/var/www/html` existe pero está vacío.) No hay ninguna otra imagen de WordPress con core en caché (`podman images | grep -i wordpress` solo devuelve esa). Por tanto el core se leyó **directamente del repositorio oficial, rama `6.5`**, que es más barato que un `podman pull` y da rutas citables igualmente:

```bash
curl -sS -o rest-comments.php https://raw.githubusercontent.com/WordPress/wordpress-develop/6.5/src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php
curl -sS -o comment.php       https://raw.githubusercontent.com/WordPress/wordpress-develop/6.5/src/wp-includes/comment.php
curl -sS -o user.php          https://raw.githubusercontent.com/WordPress/wordpress-develop/6.5/src/wp-includes/user.php
curl -sS -o load.php          https://raw.githubusercontent.com/WordPress/wordpress-develop/6.5/src/wp-includes/load.php
```

Método: `grep -n` sobre esos ficheros. Los números de línea son los de la rama `6.5` en el momento de la verificación.

**(a) ¿`comment_post`? ¿`transition_comment_status`? — NO y NO (en el camino limpio).**

- `comment_post` **no** se dispara. El controlador crea el comentario con `wp_insert_comment()`, **no** con `wp_new_comment()`:
  - `$comment_id = wp_insert_comment( wp_filter_comment( wp_slash( (array) $prepared_comment ) ) );` → `class-wp-rest-comments-controller.php:702`.
  - `grep -n "wp_new_comment\|wp_handle_comment_submission" class-wp-rest-comments-controller.php` → **0 coincidencias**.
  - `do_action( 'comment_post', ... )` aparece **una sola vez** en `comment.php`, dentro de `wp_new_comment()` (definida en `comment.php:2210`): `comment.php:2320`.
  - El cuerpo de `wp_insert_comment()` (`comment.php:1994-2075`) solo dispara `do_action( 'wp_insert_comment', $id, $comment )` (`comment.php:2072`).
- `transition_comment_status` **no** se dispara en el camino limpio:
  - Se dispara desde `wp_transition_comment_status()` (`comment.php:1768`, acción en `:1798`), y esa función **solo** se llama desde `wp_delete_comment` (`comment.php:1510`), `wp_set_comment_status` (`comment.php:2456`) y `wp_update_comment` (`comment.php:2630`). **`wp_insert_comment()` no la llama.**
  - Pero `create_item()` **sí** aplica el estado pedido **después** del insert: `if ( isset( $request['status'] ) ) { $this->handle_status_param( $request['status'], $comment_id ); }` → `class-wp-rest-comments-controller.php:712-714`.
  - `handle_status_param()` (`:1725`) corta sin hacer nada **si el estado no cambia**: `if ( $new_status === $old_status ) { return false; }` (`:1728-1729`); si cambia, llama a `wp_set_comment_status( $comment_id, 'approve' )` (`:1736`) — y **eso sí dispara `transition_comment_status`**. (La comparación estricta se cumple porque `wp_get_comment_status()` devuelve la cadena `'approved'` para `comment_approved == 1`: `comment.php:1737-1738`.)
  - Con qué estado nace el comentario lo decide `wp_allow_comment()`: `$prepared_comment['comment_approved'] = wp_allow_comment( $prepared_comment, true );` (`:659`); y `wp_allow_comment()` (`comment.php:652`) devuelve `1` cuando hay `user_id` y el usuario tiene `moderate_comments`: `if ( ! empty( $commentdata['user_id'] ) )` (`comment.php:774`) … `if ( isset( $user ) && ( $commentdata['user_id'] == $post_author || $user->has_cap( 'moderate_comments' ) ) ) { $approved = 1; }` (`comment.php:785-786`).
  ⇒ Con el usuario de servicio (que **tiene** `moderate_comments`) y un cuerpo sin campos `author_*`, el comentario nace **aprobado**; `handle_status_param('approved')` encuentra `$new_status === $old_status` y **no** llama a `wp_set_comment_status`. **Confirmado: no hay `transition_comment_status`.**

**(b) `user_id`, `comment_agent`, `comment_author_email` con Application Password.**

- `prepare_item_for_database()` (`:1301`) solo puebla `user_id` si la petición trae **`author`** (`:1315-1334`) — no es el caso con Application Password.
- Lo rellena `create_item()`: `$missing_author = empty(...['user_id']) && empty(...['comment_author']) && empty(...['comment_author_email']) && empty(...['comment_author_url'])` (`:611-615`) y `if ( is_user_logged_in() && $missing_author ) { $user = wp_get_current_user(); $prepared_comment['user_id'] = $user->ID; $prepared_comment['comment_author'] = $user->display_name; $prepared_comment['comment_author_email'] = $user->user_email; ... }` (`:616-620`).
  ⇒ **`user_id` = el ID del usuario autenticado por la Application Password** (el de servicio), con `comment_author` = su `display_name` y `comment_author_email` = su email. **Esta es la base empírica de D3**: el servidor **conoce la identidad** y puede discriminar por ella.
- `comment_agent` = el parámetro `author_user_agent` o, si no, la **cabecera `User-Agent` de la petición** (`:1345-1349`); si no hay ninguna, `''` (`:644-646`). Es decir, **lo controla el cliente**: no sirve como marca fiable de procedencia por sí solo (por eso el sellado va en el servidor, por identidad, y no en `comment_agent`).

**(c) ¿Se ejecuta `preprocess_comment` por la vía REST? — NO.**

- El filtro se aplica **una sola vez** en el core: `$commentdata = apply_filters( 'preprocess_comment', $commentdata );` (`comment.php:2243`), dentro de `wp_new_comment()` (`comment.php:2210`) — al que el controlador **no** llama.
- Lo único parecido en el controlador es un filtro con **otro nombre**: `rest_preprocess_comment` (`class-wp-rest-comments-controller.php:1389`).
⇒ Coincide con lo esperado y con `class-comment-security.php:28`. El anti-abuse (captcha/honeypot/timing) **no** se ejecuta por esta vía. Sostiene la tarea 7.2.

**(d) ¿Es aceptable `status: approved` con `moderate_comments`? — SÍ, sin degradar.**

- Comprobación de capacidad: `if ( isset( $request['status'] ) && ! current_user_can( 'moderate_comments' ) ) { return new WP_Error( 'rest_comment_invalid_status', ... ); }` → `create_item_permissions_check()`, `class-wp-rest-comments-controller.php:498-506`. ⇒ Sin `moderate_comments`, mandar `status` es **`403`**.
- Con `moderate_comments`, el estado se aplica (`handle_status_param` → `wp_set_comment_status('approve')`, `:1725-1740`) y el core **no lo degrada**: `approved` queda `approved`. Además, como `wp_allow_comment()` toma la rama `$approved = 1` (`comment.php:774-786`), **no** se llega a `check_comment()` ni a los ajustes `comment_moderation`/`comment_previously_approved`, así que la moderación automática del sitio no rebaja el estado.
⇒ **D2 confirmado.**

**(e) ¿Rechaza el controlador un `parent` de otra entrada? — NO: lo acepta en silencio.**

- `prepare_item_for_database()` copia el padre sin validarlo: `if ( isset( $request['parent'] ) ) { $prepared_comment['comment_parent'] = $request['parent']; }` (`:1318-1320`).
- `create_item()` no comprueba la coherencia `comment_parent` ↔ `comment_post_ID`; `create_item_permissions_check()` solo valida el `post` de destino: existencia (`:509-524`), `draft` (`:525-532`), `trash` (`:534-541`), permiso de lectura (`:543-551`) y `comments_open()` (`:553-560`).
- `wp_allow_comment()` usa `comment_parent` **solo** para el chequeo de duplicados (`comment.php:658-673`), no para validar la coherencia; `wp_insert_comment()` tampoco.
⇒ **Limitación conocida** (ver «Limitaciones conocidas»). **No contradice ningún requirement vigente**: el escenario que afirmaba lo contrario fue retirado y la coherencia `post`/`parent` no es bloqueante. Se consigna para que nadie la vuelva a afirmar sin medirla.

**Matiz operativo (no contradice, pero condiciona la implementación de la tarea 3.x):** si la petición de respuesta incluyera `author_name`/`author_email`, `$missing_author` sería falso (`:611-615`), `user_id` **no** se poblaría (`:616-620`), `wp_allow_comment` caería a la rama `check_comment()` (`comment.php:788-800`) —que normalmente devuelve `0`—, el comentario nacería **pendiente** y entonces `handle_status_param('approved')` **sí** cambiaría el estado y **sí** dispararía `transition_comment_status`. La guarda en el embudo común ya cubre ese segundo camino, así que el anti-bucle sigue siendo correcto; pero el camino limpio es **no enviar `author_*`** para que la respuesta nazca aprobada en un solo paso.

### Hechos verificados en el canal del asistente (tarea 1.1, bloque B — la pregunta que bloqueaba el GREEN)

**Respuesta: el asistente consume los avisos por el mismo canal de datos que `GET /api/notifications/unread`, en forma de herramienta que devuelve la fila cruda —con `metadata`—; NO por el payload de WebPush. El diseño funciona tal cual: no hace falta ningún change en `/data/rust/valet`.**

Evidencia:

1. **Existe una herramienta `notifications`, y está expuesta al modelo.** `NotificationsTool` vive en `/data/rust/valet/src/tools/notifications.rs` y se **registra**: `crate::tools::notifications::NotificationsTool::new(pool.clone())` (`/data/rust/valet/src/lib.rs:137`). Se anuncia en `ALL_TOOLS` del enrutador de skills (`src/orchestrator/skill_router.rs:751`) y es herramienta de la skill `Pendientes` (`src/orchestrator/skills.rs:80`: `tools: &["tasks", "reminders", "notifications"]`).
2. **Su operación `list_unread` lee la misma consulta que el endpoint y devuelve `metadata`.** El propio docstring lo dice: delega en `NotificationsRepo::list_unread`, «la misma consulta que sirve a `GET /api/notifications/unread`», y «devuelve la fila cruda (`Notification`), con `metadata` tal cual se guardó —una cadena JSON o `null`—» (`src/tools/notifications.rs:41-46`). El resultado se entrega al modelo como `data: json!({ "notifications": items, "total": total })` (`src/tools/notifications.rs:57`).
3. **`Notification` incluye `metadata`:** `pub metadata: Option<String>` (`src/db/repos/notifications.rs:20`), y el `SELECT` de `list_unread` la trae (`src/db/repos/notifications.rs:133`).
4. **El WebPush no lleva `metadata` ni los IDs.** `dispatch_push()` (`src/routes/notifications.rs:212`) construye `PushPayload { title, body, icon, url, tag }` (`:241-248`), con `title = format!("[{origin}] {author}")` y `body = message`; y `PushPayload` (`src/services/push.rs:424-437`) **no tiene** campo `metadata`. El push es una alerta del PWA, no la vía de datos del asistente.
5. **No hay ninguna otra vía que inyecte el contenido del aviso en el contexto del LLM:** `grep -rn "notification" src/orchestrator/` solo devuelve la lista de herramientas, y no hay inyección de notificaciones entrantes en `reminder_push.rs`/`context_builder.rs`/`agent.rs`.

**Matiz (no contradice):** por la herramienta, `metadata` llega como **cadena JSON cruda** (columna tal cual); por el endpoint `unread`, se **parsea a objeto** (`to_item()`, `src/routes/notifications.rs:343-358`; `NotificationItem.metadata: Option<Value>` en `:282-289`). En ambos casos **los identificadores están presentes**.

**Conclusión:** la pregunta (f) queda **resuelta a favor del diseño**. El aviso —y con él `metadata.comment_id`/`metadata.post_id`— **sí llega** al asistente. **No hay que tocar el repositorio Valet** y **no se abre ningún change fuera de alcance**.

### Hechos verificados sobre las Application Passwords en el sitio (tarea 1.1, bloque C)

- **Ningún filtro del sitio las altera:** `grep -rn "wp_is_application_passwords" wp-content/` → **sin coincidencias**. El plugin y el tema no filtran ni `wp_is_application_passwords_available` ni `wp_is_application_passwords_available_for_user`. Las menciones a «Application Password» en `wp-content/` (`class-pocketid-login.php` y su README) declaran **preservarlas**, no bloquearlas.
- **El requisito del core es SSL o entorno `local`, y el sitio de desarrollo no cumple ninguno** (análisis estático; el stack **no** está arrancado):
  - `wp_is_application_passwords_supported()` → `return is_ssl() || 'local' === wp_get_environment_type();` (`wp-includes/user.php:4948-4950`); `wp_is_application_passwords_available()` filtra sobre eso (`:4962-4971`).
  - `wp_get_environment_type()` cae a `'production'` si nadie define `WP_ENVIRONMENT_TYPE` (`wp-includes/load.php:222-270`).
  - `grep -rn "WP_ENVIRONMENT_TYPE" wp-content/ docker-compose*.yml quadlets/` → **sin coincidencias**. El quadlet `quadlets/atareao-wordpress.container` solo define `WP_REDIS_HOST` y `DISABLE_WP_CRON` en `WORDPRESS_CONFIG_EXTRA`.
  - **No hay TLS en desarrollo:** `nginx/default.conf:69` es `listen 80 default_server;` para `atareao.localhost`; el quadlet publica `8091:80` (`quadlets/atareao-nginx.container:11`); `grep` de `tls|443|certresolver|websecure` en `nginx/`, `quadlets/` y `docker-compose*.yml` → **sin coincidencias** (las etiquetas de Traefik no activan TLS).
  ⇒ **Con la configuración actual, las Application Passwords NO están disponibles en el sitio de desarrollo** (`is_ssl()` falso y entorno `production`). **No es una contradicción del diseño** (en producción, HTTPS, sí lo están): es un **requisito de aprovisionamiento**. Para crear una App Password en desarrollo hace falta **una** de estas dos cosas: `WP_ENVIRONMENT_TYPE=local` en `WORDPRESS_CONFIG_EXTRA` **o** servir el sitio por HTTPS.
- **¿Se puede crear una App Password para el usuario de servicio?** **No verificable sin el stack**: el usuario de servicio aún no existe y el flujo exige la UI o `wp-cli` del sitio. **Pendiente.**
- **¿Está el stack arrancado?** **No.** `podman ps` no muestra ningún contenedor. Sobre el puerto 3000 hay un proceso Python **ajeno a Valet** (`<title>Excalidraw POC - Backend API Integration</title>`), no el servicio Valet. **No se ha arrancado nada** (por indicación expresa).

### Tarea 1.3 — punta a punta: **PENDIENTE DE PRUEBA DE DESPLIEGUE**

El servicio Valet **no** está en marcha (`podman ps` vacío; `curl -sS -m 4 http://localhost:8080/` → *Failed to connect*), así que **no se ha podido enviar una notificación real** contra el consumidor. Conforme a la instrucción, **no se ha arrancado el stack**. La tarea 1.3 queda **sin marcar**.

**Evidencia estática disponible hoy (no sustituye a la prueba de despliegue):** el propio consumidor ya prueba esta cadena con sus tests de integración, que corren **sin levantar nada**:
- `tests/api/notifications.rs:438` (`ingest_metadata_must_be_object`): con `metadata` objeto → `201` y **fila persistida con `metadata`** (comprobado leyendo la columna con el `SELECT` de `:40-46`, uso en `:460-462`).
- `tests/api/notifications.rs:554` (`unread_lists_only_unread_newest_first`): inserta una notificación **con metadata objeto** (`:564`) y afirma que en `GET /api/notifications/unread` **`metadata` llega parseado a objeto**: `assert_eq!(data[0]["metadata"]["k"], "v");` (`:598`), y `metadata` NULL llega como `null` (`:600`).

**Comandos exactos para cerrar la tarea cuando el stack esté levantado** (o, de coste cero, la alternativa ejecutando los tests del consumidor):

```bash
# Opción A — de coste cero, sin levantar nada (tests de integración del consumidor, en proceso):
cd /data/rust/valet && cargo test --test api -- unread_lists_only_unread_newest_first ingest_metadata_must_be_object

# Opción B — contra el servicio real (requiere stack arriba y una clave de ingesta):
curl -sS -X POST http://localhost:3000/api/notifications \
  -H 'Authorization: Bearer <INGEST_KEY>' \
  -H 'Content-Type: application/json' \
  -d '{"message":"Prueba de identificadores","origin":"wp-comment","author":"atareao","level":"info","metadata":{"comment_id":123,"post_id":45}}'
# → 201 {"id":"<uuid>"}

curl -sS http://localhost:3000/api/notifications/unread
# → el item debe traer "metadata":{"comment_id":123,"post_id":45}  (objeto, NO cadena)
```

### Limitaciones conocidas (consignadas, no ocultas)

1. **El controlador REST acepta en silencio un `parent` de otra entrada** (bloque A, pregunta (e)). No es bloqueante —ningún requirement vigente depende de lo contrario— pero **no debe afirmarse** sin medirlo.
2. **Las Application Passwords no están disponibles en el sitio de desarrollo con la configuración actual** (HTTP + entorno `production`): hace falta `WP_ENVIRONMENT_TYPE=local` o TLS. En producción (HTTPS) la vía es válida.
3. **La respuesta debe nacer aprobada en un solo paso:** conviene que la petición de respuesta **no** incluya `author_name`/`author_email`, para que `user_id` se poblé y `wp_allow_comment()` inserte el comentario ya aprobado (y no se dispare `transition_comment_status` en un segundo paso). El anti-bucle cubre ambos casos; este es el camino limpio.
4. **La tarea 1.3 sigue pendiente** de prueba de despliegue (ver arriba).

### Contradicciones con el diseño

**Ninguna.** Las cinco comprobaciones del bloque A y la del bloque B **confirman** el diseño: sellado en `rest_pre_insert_comment` (el hook existe para insert vía REST, `class-wp-rest-comments-controller.php:697`, y el `user_id` del usuario autenticado está disponible en `:616-620`), guarda en el embudo común, cota por ventana, `status: approved` con `moderate_comments` y transporte por `metadata`. **No hay que detener el trabajo por contradicción.** Lo que hay son **requisitos de aprovisionamiento** (App Passwords en desarrollo; validación de `parent`) y **matices operativos**, todos consignados arriba.

## Goals / Non-Goals

**Goals:**

- Cerrar el canal en sentido Valet → WordPress: que el asistente pueda publicar una respuesta direccionada al comentario que se le notificó.
- Hacer **imposible por construcción** el bucle de realimentación, incluso si algún día se añade otro camino de inserción de comentarios del asistente.
- Que la nueva vía de escritura tenga un límite explícito, ya que no hereda el anti-abuse del sitio.
- No abrir ni una sola ruta pública nueva.

**Non-Goals:**

- Cambiar el canal de ida más allá de los datos auxiliares transportados en `metadata` (nada de reescribir el endurecimiento, el recorte byte-safe ni el límite de notificaciones). **No se toca el repositorio `/data/rust/valet`**: el canal `metadata` ya existe y está soportado de punta a punta.
- Moderar las respuestas del asistente: se publican directamente (`status: approved`), por decisión del usuario.
- Sustituir XML-RPC: está deshabilitado a propósito en este repo y no se reintroduce.
- Usar MCP como vía de escritura: es de solo lectura por contrato.
- Enmendar `rest-access-policy`: la vía elegida ya satisface esa puerta.
- Implementar el **publicador** (quien emite la petición de respuesta, con la Application Password): es un change propio en `/data/rust/valet`, fuera de este repositorio. Aquí solo se deja la vía preparada y el contrato documentado.

## Decisions

### D1. REST core con Application Password, no endpoint propio ni XML-RPC

`POST /wp-json/wp/v2/comments` con `post`, `parent` y `status`, autenticado con Application Password.

- **Quién emite la petición:** el **servicio Valet** (`/data/rust/valet`), es decir el consumidor. Este repositorio **no implementa ningún cliente REST en PHP**: WordPress no se llama a sí mismo por HTTP con su propia credencial, y un cliente así sería código muerto. Este repositorio aporta el **sellado**, la **guarda anti-bucle** y la **cota** (que son del lado WordPress porque protegen al sitio), y **documenta el contrato**. La **Application Password la custodia Valet**: **no se almacena ninguna credencial de servicio en `wp_options` ni en ningún otro lugar del sitio**.
- **Aprovisionamiento (paso manual):** usuario de servicio dedicado con rol mínimo `read` + `moderate_comments` y su Application Password; se documenta en el README (tarea 5.3). No lo crea el código y no se tocan los quadlets.
- **Por qué:** es la API de escritura de comentarios que el core ya expone, ya autenticada por la puerta existente, y respeta el contrato de la spec de la puerta REST sin tocarla.
- **Alternativas descartadas:** (a) un endpoint propio `atareao/v1/...` exigiría añadirlo a las exenciones o implementar su propia autenticación, y ampliaría la superficie de la puerta; (b) XML-RPC está deshabilitado a propósito en este repo (`atareao_functionality_disable_xmlrpc_comment`); (c) MCP es de solo lectura por contrato; (d) un cliente REST en PHP dentro del plugin — lo ejecutaría el propio WordPress contra sí mismo, con una credencial que puede publicar sin moderación almacenada en la base de datos del sitio al que da acceso, sin que nadie la use.

### D2. `status: approved` y, en consecuencia, rol con `moderate_comments`

La publicación directa se resolvió como decisión de producto. Tiene una consecuencia técnica dura: el core exige `moderate_comments` para insertar un comentario ya aprobado por REST. Por eso el rol del usuario de servicio se define como `read` + `moderate_comments` y **nunca** `editor` ni `administrator`: es el mínimo que satisface la decisión de producto, y el resto de la matriz de permisos quedaría como privilegio innecesario.

- **Alternativa descartada:** publicar con `status: hold` y moderar a mano — no cambia el permiso necesario (`moderate_comments` sigue haciendo falta para aprobar después) y contradice la decisión tomada.

### D3. Sellado del comentario en el servidor, vía `rest_pre_insert_comment`

La marca de procedencia la aplica el servidor en `rest_pre_insert_comment`, escribiéndola en `$prepared_comment['comment_meta'][ self::REPLY_META_KEY ] = '1'` (clave `_atareao_valet_reply`), de modo que `wp_insert_comment()` la persista **con la inserción**: su docblock documenta que guarda `comment_meta` con `add_comment_meta()` antes de devolver (verificado en 6.5 y trunk). El dato que representa la marca es esa entrada de meta de comentario, y la guarda la lee con `get_comment_meta($id, '_atareao_valet_reply', true)`. La guarda anti-bucle se implementa como una comprobación de esa marca al principio de `notifyApprovedComment()` —el embudo común de las dos rutas de notificación— y **no** en cada hook por separado.

- **Por qué el sellado viaja con la inserción y no en `rest_after_insert_comment`:** el orden de `create_item()` es `rest_pre_insert_comment` (`class-wp-rest-comments-controller.php:697`) → `wp_insert_comment` (`:702`) → `handle_status_param` (`:712`, que puede disparar `transition_comment_status`) → `rest_after_insert_comment` (`:759`). Una marca persistida en `rest_after_insert_comment` llega **después** de la transición, así que no cubriría el camino de una respuesta que nace pendiente y se aprueba en la misma petición: la guarda la vería ausente y notificaría (falso verde). Sellar en `comment_meta` antes de la inserción la hace visible en el instante de cualquier transición. El harness reproduce este **mismo orden** (pre-insert → insert con meta → status → after-insert): invertirlo daría un falso verde.
- **Por qué `comment_meta` y no `comment_agent`:** `comment_agent` lo decide el cliente (la cabecera `User-Agent` de la petición, `:1345-1349`, o `''` si falta), así que ni es una marca fiable de procedencia ni un dato que el servidor controle.
- **Por qué el cliente no puede inyectarla:** `comment_meta` no figura entre los parámetros REST aceptados por el controlador, así que un cliente no puede enviarla; además el sellado **sobrescribe** cualquier valor previo del array.

- **Por qué el embudo común:** cualquier camino futuro que acabe en `notifyApprovedComment()` queda cubierto sin replicar la comprobación, incluido un hipotético tercer hook.
- **Por qué sellar en el servidor y no en el cliente:** el cliente (Valet) no debe poder decidir si su comentario es notificable; si la marca dependiera de un campo enviado en la petición, un error o una omisión reabriría el bucle.
- **Por qué hooks REST y no campos del comentario:** `user_id`, `comment_agent` o `comment_author_email` dependen de qué rellene el propio controlador REST; apoyar la guarda en ellos es apoyarla en tripas del core que pueden cambiar. La tarea 1.1 mide empíricamente qué deja el controlador en esos campos para **documentarlo**, no para depender de ello.

### D4. Cota de escritura con el patrón ya existente

Contador por ventana con clave derivada y hash salado, con límite y ventana configurables por filtro, siguiendo `getRateLimitKey()` / `commentRateLimitAllows()` de `ValetConfig`.

- **Por qué:** es el único control de volumen del camino de escritura (el anti-abuse no aplica) y el patrón ya está probado en el repositorio, lo que evita introducir un segundo mecanismo de límites.
- **Qué se acota:** los comentarios publicados **por el asistente** en la ventana. Como el sellado identifica exactamente esos comentarios, la clave puede derivarse de la identidad de servicio, sin usar datos de visitantes.

**Valores por defecto elegidos** (constantes en `ValetConfig`; hasta ahora solo vivían en el docblock del código):

| Constante | Valor | Filtro que lo ajusta |
| --- | --- | --- |
| `REPLY_RATE_LIMIT` | `5` | `atareao_valet_reply_rate_limit` |
| `REPLY_RATE_WINDOW` | `3600` (una hora) | `atareao_valet_reply_rate_window` |
| `REPLY_RATE_PREFIX` | `atareao_valet_reply_rl_` | — (constante) |
| `REPLY_RATE_ERROR_CODE` | `atareao_valet_reply_rate_limited` | — (constante) |

- **Por qué el prefijo es distinto del presupuesto de notificaciones:** el presupuesto usa `atareao_valet_comment_rl_` y la cota de respuestas `atareao_valet_reply_rl_`. Los dos contadores miden cosas distintas (notificaciones **enviadas a** Valet vs. respuestas **publicadas por** Valet) y **nunca** deben compartir clave: compartirla haría que un bucle de publicación consumiera el presupuesto de aviso y silenciara notificaciones legítimas (o al revés).
- **Por qué el código de error es parte del contrato:** el `WP_Error` que devuelve `rest_pre_insert_comment` viaja al cliente con `status => 429`, de modo que el servicio Valet puede discriminar «cota agotada» de cualquier otro fallo. Va documentado en el README porque lo consume un cliente que no está en este repositorio.
- **Por qué 5/h y no 20/h:** el presupuesto de notificaciones ya es **20 por 3600 s** (`COMMENT_RATE_LIMIT`), y las respuestas **nacen de** notificaciones (el asistente responde a lo que se le avisa). Un límite de ese orden sería por tanto un **subconjunto** del presupuesto ya existente y no acotaría **nada nuevo**. Cinco respuestas por hora —una cada doce minutos, sostenidas— queda muy por encima del ritmo legítimo de una conversación humana y a la vez acota un **bucle desbocado** o una **credencial filtrada** a cinco comentarios publicados por hora, sobre una vía que **no** hereda el anti-abuse del sitio (que es precisamente lo que hace que ese tope importe aquí y no en el camino de `wp_new_comment`).

**Unificación del REFACTOR (deuda parcialmente saldada).** `getWindowKey()`, `getWindowCount()` y `recordWindowEvent()` son ahora un **único punto compartido** por los dos contadores del módulo: la derivación de la clave (hash salado con `wp_salt('nonce')` + bucket por ventana), la lectura del valor y el incremento con TTL de dos ventanas no se escriben dos veces. Las **políticas**, en cambio, siguen separadas —y deben seguir— porque miden cosas distintas:

- El **presupuesto de notificaciones** consume la ranura **en la comprobación** (`commentRateLimitAllows()` comprueba y registra en el mismo acto): lo que acota es la **petición HTTP saliente**, así que una notificación descartada **no** debe devolver la cuota. Si se consumiera al confirmar el envío, un fallo del consumidor permitiría reintentos sin límite.
- La **cota de respuestas** es de **solo lectura** en la comprobación (`serviceReplyQuotaAllows()`) y registra la ranura **al publicar** (`countServiceReply()`, en `wp_insert_comment`): lo que acota son las **publicaciones**, y solo deben contar las efectivas. Consumirla en la comprobación haría que una petición rechazada —o que fallara entre la comprobación y la inserción— gastara cuota por algo que nunca se publicó.

⇒ Extraer el **mecanismo** a un único punto es correcto; extraer la **política** sería un error de diseño. Esto **salda parte** de la **deuda aceptada** en el change `2026-10-17-valet-notifications-hardening` (tarea 4.9 C2): allí se aceptó que «el *mecanismo* (derivación + bucket + `get/set_transient`) sigue duplicado». Aquí al menos la duplicación **entre los dos contadores de `ValetConfig`** desaparece; la que pudiera quedar entre `ValetConfig` y `ContactForm` (misma fórmula, prefijo y nombres de filtro propios) **no** forma parte de este change ni se toca.

### D5. La guarda se prueba por mutación, no solo por tests de camino feliz

Un test que pasa no demuestra que la guarda haga algo. La verificación por mutación (quitar el sellado o la guarda debe poner la suite en rojo, con asserts concretos y exit code distinto de cero como evidencia) es parte del plan, no un extra opcional.

- **Por qué:** el fallo característico de una guarda anti-bucle es existir sin ser efectiva (comprobación en un hook que no se dispara, marca que se sobrescribe después, orden de filtros equivocado). Solo la mutación lo distingue.

### D6. Identificadores y marca de procedencia **dentro de `metadata`**, no como campos de primer nivel

Los identificadores se envían como `metadata.comment_id` y `metadata.post_id` (enteros no negativos) y la marca de contenido público como `metadata.untrusted` (booleano). El objeto `metadata` **se omite por completo** cuando no hay nada que transportar —el mensaje de prueba, que no lleva identificadores ni marca—, de modo que la forma del payload de esa ruta permanece inalterada.

- **Por qué no como campos de primer nivel (hecho verificado, no preferencia):** el consumidor solo persiste y expone `message`, `origin`, `author`, `level` y `metadata` (`/data/rust/valet/src/routes/notifications.rs:73-83`, `:110-118`; `src/db/repos/notifications.rs:41`). No es estricto —no hay `deny_unknown_fields`—, así que un campo de primer nivel desconocido **no rompe** la petición: **se descarta en silencio**. Los identificadores se perderían sin que nadie se enterase, y la funcionalidad entera (la respuesta direccionada) quedaría inerte. Este es exactamente el fallo que ya ocurrió con el `untrusted` de primer nivel del change anterior.
- **Por qué `metadata` y no un cambio en Rust:** `metadata` ya se acepta, se persiste y se expone, con su propio límite; usarlo evita tocar un repositorio ajeno y despliegue coordinado. Coste asumido: `metadata` es un contenedor genérico, así que la forma del dato vive en la convención entre los dos extremos y hay que documentarla (tarea 6.2).
- **Por qué la marca de no confiable se **duplica** (cabecera literal en `message` + `metadata.untrusted`):** `message` es el único canal que el consumidor persiste **y despacha** con seguridad, incluido el push; `metadata` depende de que el asistente lo lea. La cabecera literal es por tanto la mitigación **principal** (efectiva aunque el asistente ignore `metadata`) y `metadata.untrusted` es el dato estructural para un consumidor que sí lo mire. Retirar la cabecera dejaría SEC-001 sin mitigación real.
- **Alternativa descartada:** un `metadata` tipado con un esquema rígido —no existe; el campo es un objeto JSON libre, así que el contrato se documenta y se prueba, no se valida en el consumidor.

## Risks / Trade-offs

- **[El publicador no existe todavía]** → sin el change en `/data/rust/valet` la vía queda montada pero sin uso: el sitio sabrá sellar, no notificar y acotar, y el contrato estará documentado, pero nadie emitirá la petición de respuesta. Mitigación/observación: el contrato queda documentado (README, tarea 5.3) y la vía es **inerte** —no hay riesgo mientras nadie publique, y la credencial ni siquiera existe hasta que se aprovisione—.
- **[Resuelto — verificado]** ~~[El consumidor Rust (`/data/rust/valet`) puede deserializar de forma estricta]** → los campos nuevos romperían los avisos de comentario.~~ **Hecho verificado:** el consumidor **no** es estricto (no hay `deny_unknown_fields`); el riesgo real es el **contrario** y ya está mitigado: un campo de primer nivel desconocido se descarta en silencio. Mitigación aplicada en el diseño: los identificadores y la marca viajan **dentro de `metadata`** (D6), que el consumidor sí persiste y expone. Verificación de cierre: tarea 1.3 (de punta a punta, contra `GET /api/notifications/unread`).
- **[El asistente puede consumir WebPush en lugar del endpoint `unread`, y el push no lleva los identificadores]** → hoy `dispatch_push()` recibe **solo** `origin`, `author` y `message` (`/data/rust/valet/src/routes/notifications.rs:120-126`), así que los IDs persistidos en `metadata` **no viajarían en el push**: si el asistente solo mira el push, la respuesta direccionada seguiría siendo imposible. Mitigación: **resolverlo antes del GREEN** con la pregunta (f) de la tarea 1.1 (¿consume `GET /api/notifications/unread`, que sí expone `metadata`, o el payload de WebPush?). Si consume WebPush, hará falta un **change en el repositorio `/data/rust/valet`** para incluir los identificadores en el push: queda **fuera de este change** salvo decisión expresa del usuario, y en ese caso se abre como change propio en el otro repositorio. No se mitiga aquí porque tocar Valet está explícitamente fuera de alcance.
- **[La guarda anti-bucle puede parecer efectiva sin serlo]** → comprobación colocada donde el hook no se dispara, marca sobrescrita después del sellado, o prioridad de filtro equivocada. Mitigación: sellado en `rest_pre_insert_comment`, guarda en el embudo común, y **verificación por mutación** de cada control.
- **[La publicación directa no tiene red humana]** → un fallo del asistente publica contenido sin moderación. Mitigación parcial: cota de escritura y revocación independiente de la Application Password (parar el canal es revocar, no desplegar). El riesgo de contenido es aceptado por decisión de producto.
- **[La vía de respuesta no hereda el anti-abuse]** → inherente al controlador REST del core; no se puede mitigar sin reimplementar validaciones. Mitigación: la cota de escritura (D4) y la autenticación como única puerta de entrada.
- **[Ampliar la lista de exenciones de la puerta REST sería un agujero anónimo]** → mitigación: la lista **no se toca**; se añade una tarea de verificación de que sigue conteniendo solo `/atareao/v1/mcp`.
- **[El rol de servicio podría reutilizar una cuenta personal]** → ampliaría el radio de daño de una credencial filtrada. Mitigación: usuario dedicado con rol propio, especificado en `valet-replies` y documentado en el README.
- **[Una credencial de publicación sin moderación almacenada en el sitio]** → Si el cliente REST viviera en PHP y su credencial se guardara en `wp_options`, el sitio al que esa credencial da acceso custodiaría una clave capaz de publicar sin moderación, y el código sería además inerte (WordPress no se llama a sí mismo por HTTP). Mitigación adoptada: **el cliente y la credencial no están aquí** — el publicador es un change propio en `/data/rust/valet`, que custodia la Application Password; este repositorio no almacena ninguna credencial de servicio.

## Migration Plan

1. Resolver las preguntas empíricas de la tarea 1.1 (ver «Open Questions») **antes** de implementar. Si alguna respuesta contradice D3 o D4, ajustar el plan y reportarlo antes de seguir.
2. Implementar y probar la mitad PHP (payload con IDs, sellado, guarda, cota) con la suite del harness en verde.
3. **Verificar de punta a punta** (tarea 1.3) que los identificadores llegan al consumidor por `metadata`: persistencia + `GET /api/notifications/unread` devolviéndolos. Resolver además la pregunta (f) de 1.1 (¿`unread` o WebPush?): si el asistente consume WebPush, **detenerse** y abrir un change en `/data/rust/valet` antes de dar el GREEN por bueno.
4. **Aprovisionar en el sitio (paso manual, documentado en el README, tarea 5.3):** usuario de servicio dedicado, rol mínimo (`read` + `moderate_comments`) y su Application Password —la credencial la custodia el servicio Valet; **no se almacena ninguna credencial de servicio en el sitio**. El entorno de desarrollo exige además `WP_ENVIRONMENT_TYPE=local` o HTTPS para que el core ofrezca Application Passwords (`wp-includes/user.php:4948-4950`); **es un paso manual, no se tocan los quadlets**.
5. **Rollback:** revocar la Application Password desactiva instantáneamente la vía de respuesta sin tocar el código. Revertir el payload de la notificación (código) es independiente y no requiere migración de datos. Ni el sellado ni la cota introducen cambios de esquema.

## Open Questions

**Resueltas por investigación (hechos verificados, ver «Hechos verificados en el consumidor»):**

- **¿Tolera el consumidor campos nuevos en el JSON?** **Sí, pero de forma inútil**: no hay `deny_unknown_fields` en `/data/rust/valet`, así que un campo desconocido no rompe la petición… **se descarta en silencio**. Conclusión: el transporte debe ir por `metadata` (D6) y **no** se toca el repositorio Valet.
- **¿Qué canal usa el consumidor para los datos auxiliares?** `metadata`, ya soportado de punta a punta (aceptado como objeto JSON, persistido y expuesto en `GET /api/notifications/unread`).
- **¿Estaban alineados los límites de longitud?** Sí: `MAX_MESSAGE_BYTES = 4096` en el consumidor coincide con `MESSAGE_MAX_BYTES` del módulo.
- **¿Era efectivo el `untrusted` de primer nivel del change anterior?** **No.** Era un campo inerte: el consumidor lo descartaba. La mitigación efectiva de SEC-001 fue y sigue siendo la cabecera literal dentro de `message`. Corregido en este change (marca en `metadata.untrusted` **y** cabecera literal en `message`).

**Resueltas por medición (tarea 1.1). Detalle y evidencia con `fichero:línea` en las secciones de arriba — «Hechos verificados en el core de WordPress», «…en el canal del asistente» y «…sobre las Application Passwords»:**

- **¿La creación de un comentario por la ruta REST dispara `comment_post`?** **No** — `create_item()` usa `wp_insert_comment()` (`class-wp-rest-comments-controller.php:702`), que solo dispara `wp_insert_comment` (`comment.php:2072`); `comment_post` vive en `wp_new_comment()` (`comment.php:2320`), al que el controlador nunca llama.
- **¿Y `transition_comment_status` si se inserta ya aprobado?** **No** — `wp_insert_comment()` no la dispara, y `handle_status_param('approved')` (`:1725-1729`) corta sin cambiar nada cuando el comentario ya nace aprobado (lo que ocurre con el usuario de servicio, `comment.php:774-786`).
- **¿Qué deja el controlador REST en `user_id`, `comment_agent` y `comment_author_email`?** `user_id` = **el ID del usuario autenticado** por la Application Password, con su `display_name` y su email (`:611-620`); `comment_agent` = el `User-Agent` de la petición (lo elige el cliente), `''` si falta (`:1345-1349`, `:644-646`). Es la base de D3.
- **¿Se ejecuta `preprocess_comment` por esa vía?** **No** — solo lo aplica `wp_new_comment()` (`comment.php:2243`).
- **¿`status: approved` es aceptable con `moderate_comments`?** **Sí** — sin `moderate_comments` es `403` (`:498-506`); con ella se aplica y el core no lo degrada (D2 confirmado).
- **¿Están disponibles las Application Passwords en el sitio y se puede crear una para el usuario de servicio?** **No están disponibles en el sitio de desarrollo con la configuración actual** (HTTP + entorno `production`; el core exige SSL o entorno `local`, `user.php:4948-4950`) y **ningún filtro del sitio las habilita**. **La creación de la credencial no es verificable sin el stack** (usuario de servicio aún inexistente): queda como requisito de aprovisionamiento, no como contradicción.
- **¿Cómo consume el asistente las notificaciones: `unread` o WebPush?** **Por el canal de datos, no por el push**: tiene una herramienta `notifications` registrada (`src/lib.rs:137`) cuya operación `list_unread` lee las mismas filas —**con `metadata`**— que `GET /api/notifications/unread`, mientras que el payload WebPush (`push.rs:424-437`) no lleva `metadata`. **El diseño funciona tal cual y NO hace falta un change en `/data/rust/valet`.**

**Extras medidos, no preguntados:** (e) el controlador **acepta en silencio un `parent` de otra entrada** (`:1318-1320`; sin comprobación en `create_item`/`create_item_permissions_check`) — limitación conocida; y el **matiz operativo** de que la respuesta debe nacer aprobada en un solo paso (no enviar `author_name`/`author_email`) para no recaer en el segundo camino hacia `transition_comment_status`.

**Pendiente (no resuelto aquí):** la tarea 1.3, prueba de punta a punta, requiere el stack en marcha; no se ha ejecutado y **no se marca**. Los comandos exactos quedan escritos arriba.

Las respuestas **no** contradicen el diseño ni obligan a cambiar las specs; el plan no se ajusta salvo por lo ya consignado en «Limitaciones conocidas».
