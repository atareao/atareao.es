## Why

El asistente de Valet (`/data/rust/valet`) **ya recibe** una notificación cuando un comentario queda aprobado en el sitio, pero **no puede responder**: la notificación viaja sin el ID del comentario (`notifyApprovedComment()` construye `message`, `origin`, `author`, `level` y `untrusted`, y ninguno de ellos identifica el comentario). Sin ese identificador el asistente no puede direccionar la respuesta (`parent`), así que hoy la conversación es de un solo sentido: Valet se entera de que alguien escribió, y nada más.

Habilita la respuesta y aparece el **riesgo principal de este change: el bucle de realimentación**. Si el comentario con el que el asistente responde acabase disparando `comment_post` o `transition_comment_status` —los dos hooks a los que está enganchada la notificación (`includes/class-valet-config.php:84` y `:85`)—, Valet recibiría un aviso de su propio comentario y podría volver a responder, indefinidamente. Además de la amplificación, el bucle consumiría el presupuesto de la ruta de comentarios (**20 notificaciones / 3600 s**, `COMMENT_RATE_LIMIT` / `COMMENT_RATE_WINDOW`), con lo que acabaría **silenciando avisos legítimos** de comentaristas reales. El bucle no es hipotético: `notifyApprovedComment()` notifica cualquier comentario de tipo `comment` **sin mirar quién lo escribió**, de modo que un comentario auto-aprobado del asistente es indistinguible de uno de un visitante. La guarda anti-bucle es, por tanto, la pieza que hace segura la funcionalidad, no un extra.

Se aborda ahora porque la dirección Valet → WordPress es la mitad que falta del canal, y porque el canal de ida ya está endurecido, probado y archivado (`2026-10-16-valet-notifications`, `2026-10-17-valet-notifications-hardening`).

## What Changes

**1. Notificación direccionable (`valet-notifications`, MODIFIED).**

- `ValetConfig::sendValetNotification()` amplía su firma con `$comment_id = 0` y `$post_id = 0` y añade al cuerpo JSON un objeto `metadata` que transporta los identificadores como enteros `metadata.comment_id` y `metadata.post_id`.
- Los identificadores **no** van como campos de primer nivel: el consumidor `/data/rust/valet` solo persiste y expone `message`, `origin`, `author`, `level` y `metadata`, y **descarta en silencio** cualquier otro campo de primer nivel, de modo que unos IDs de primer nivel jamás llegarían al asistente.
- El objeto `metadata` **se omite por completo** cuando no hay nada que transportar: el mensaje de prueba no lo lleva (ni como `null`), de modo que la forma del payload de esa ruta no cambia; el formulario de contacto lo lleva para transportar la marca de procedencia (`metadata.untrusted`).
- `notifyApprovedComment()` pasa los IDs para que el destinatario pueda direccionar una respuesta (`parent`).
- Se corrige el requirement del **marcado de contenido público como no confiable**: la marca `untrusted` SHALL viajar dentro de `metadata` (`metadata.untrusted`), porque el campo de primer nivel que se especificó antes era **inerte** (el consumidor lo descartaba). La mitigación **principal** sigue siendo la cabecera literal al inicio de `message`, que sí viaja.
- Nada más de los requirements actuales se altera: el endurecimiento del endpoint (`https`, `wp_http_validate_url()`, `redirection => 0`), el recorte byte-safe y el presupuesto `MESSAGE_MAX_BYTES` se conservan intactos.

**2. Camino inverso (`valet-replies`, NUEVA capability).**

- **Contrato de respuesta:** la respuesta se publica con `POST /wp-json/wp/v2/comments` (controlador REST del core), con `post` = ID de la entrada y `parent` = ID del comentario recibido, autenticada con **Application Password**, y `status` = `approved` (publicación directa, sin moderación).
- **Sin ruta nueva exenta:** no se añade ninguna ruta pública a la lista de exenciones de la puerta REST. La autenticación por Application Password ya satisface esa puerta, y una ruta exenta de escritura sería un **agujero anónimo**.
- **Identidad de servicio con privilegio mínimo:** usuario dedicado, distinto de las cuentas personales, con un rol que conceda únicamente `read` + `moderate_comments` (imprescindible porque `status: approved` exige `moderate_comments`); **nunca** `editor` ni `administrator`. Application Password revocable de forma independiente y **distinta** del token Bearer de ida.
- **Marcado de procedencia y guarda anti-bucle:** los comentarios creados por el asistente quedan marcados **por el servidor** (sobrescribiendo lo que envíe el cliente) y el módulo de notificaciones **no despacha** notificación alguna para un comentario marcado —ni por `comment_post` ni por `transition_comment_status`—. La implementación se apoya en los enganches REST del core (`rest_pre_insert_comment` para el sellado y, si hay que persistir meta, `rest_after_insert_comment`), no en campos que el controlador REST pudiera no rellenar.
- **Cota de escritura:** como la vía REST esquiva el anti-abuse (`preprocess_comment` no se ejecuta en el controlador REST de comentarios) y WordPress no limita las escrituras autenticadas, se acota el número de comentarios publicados por el asistente dentro de una ventana temporal, con límite y ventana configurables por filtro y valores por defecto sensatos. Al alcanzar la cota, la petición se rechaza **sin** crear el comentario.

## Capabilities

### New Capabilities

- `valet-replies`: define el camino inverso Valet → WordPress: contrato de publicación de la respuesta por REST core autenticada con Application Password, identidad de servicio con privilegio mínimo, marcado de procedencia con guarda anti-bucle, y cota de escritura de la vía de respuesta.

### Modified Capabilities

- `valet-notifications`: cambia el contrato `sendValetNotification()` (firma y payload con `metadata.comment_id` / `metadata.post_id`, y el objeto `metadata` omitido cuando no procede de un comentario), se precisa que `notifyApprovedComment()` incluye el ID del comentario y el de la entrada para que la notificación sea direccionable, y se corrige el requirement «Marcado de contenido público como no confiable» para que la marca viaje como `metadata.untrusted` en vez de como campo de primer nivel, manteniendo la cabecera literal en `message` como mitigación principal.

## Impact

### Ficheros afectados (previsibles)

- `wp-content/plugins/atareao-functionality/includes/class-valet-config.php` — firma y payload de `sendValetNotification()` (objeto `metadata` con identificadores y marca de procedencia, omitido cuando queda vacío), paso de IDs desde `notifyApprovedComment()` y guarda anti-bucle en las dos rutas de notificación.
- **No hay fichero de cliente REST en PHP.** El camino de respuesta lo recorre el **servicio Valet** (`/data/rust/valet`), que es quien emite la petición `POST /wp-json/wp/v2/comments`: WordPress no se llama a sí mismo por HTTP con su propia credencial. Por eso **no se crea** `includes/class-valet-replies.php` ni ningún otro cliente de salida, y **no se almacena ninguna credencial de servicio en WordPress** (ni en `wp_options` ni en ningún otro sitio): la **Application Password la custodia el servicio Valet**. Lo que este repositorio implementa es el **sellado de procedencia**, la **guarda anti-bucle** y la **cota de escritura** (que viven en el módulo de notificaciones y en los enganches REST del core), más el **aprovisionamiento documentado** de la identidad de servicio (paso manual).
- `tests/test-valet.php` — tests nuevos (payload con IDs, guarda anti-bucle, cota) y no regresión de los existentes.
- `tests/wp-stubs.php` — stubs nuevos para los enganches REST del core (`rest_pre_insert_comment`, `rest_after_insert_comment`).
- `wp-content/plugins/atareao-functionality/README.md` — contrato íntegro que el servicio Valet debe cumplir (endpoint, campos, autenticación, `status`), aprovisionamiento de la identidad de servicio (usuario dedicado, rol mínimo `read` + `moderate_comments`, creación de la Application Password) y el requisito del entorno (SSL o `WP_ENVIRONMENT_TYPE=local`), además de las advertencias: la vía **esquiva el anti-abuse** y `wp_http_validate_url()` **no aplica** aquí (es una defensa de la ruta de salida, no de una petición entrante autenticada).

### Alcance (no-goals)

- **Implementar el publicador** (quien emite la petición de respuesta) **no es de este change**: es un change propio en el repositorio `/data/rust/valet`, que es quien custodia la Application Password. Este change deja el sitio preparado —payload direccionable, sellado, guarda y cota— y el **contrato documentado**, pero sin ese change nadie publica respuestas.
- **No vive aquí ningún cliente REST en PHP ni ninguna credencial de servicio**: WordPress no se llama a sí mismo por HTTP con su propia credencial, y una Application Password capaz de publicar sin moderación no debe quedar almacenada en la base de datos del sitio al que da acceso. El aprovisionamiento (usuario de servicio, rol mínimo y credencial) es un **paso manual documentado** en el README.

### Especificaciones tocadas

- `valet-notifications` — **MODIFIED**: tres requirements (`Contrato de envío de notificaciones a Valet`, `Mensaje de prueba, notificación de comentarios y formulario de contacto` y `Marcado de contenido público como no confiable`). Los otros cuatro requirements de la spec se conservan sin cambios.
- `valet-replies` — **ADDED**: capability nueva con cuatro requirements.

### Especificaciones que NO se tocan (y por qué)

- **`rest-access-policy`** — **no se toca, y así debe documentarse.** La spec viva declara literalmente que «Las peticiones con **sesión iniciada** —incluidas las autenticadas por **Application Password**— SHALL NOT ser rechazadas por esta puerta» y tiene un scenario «Application Password sigue funcionando». La vía elegida (REST core + Application Password) **ya la satisface sin enmienda**. Añadir `/wp-json/wp/v2/comments` a la lista de exenciones sería exactamente lo contrario de lo que esa capability protege: convertiría una ruta de escritura en anónima. La única tarea relacionada es una **verificación** de que la lista de exenciones sigue conteniendo solo `/atareao/v1/mcp`.
- **`matrix-notifications`** — la vía de respuesta no publica nada en Matrix y el change no altera el canal de notificación a Matrix; la guarda anti-bucle actúa sobre el despacho a Valet, no sobre Matrix.
- **`admin-settings`** — no se añade pestaña, opción ni formulario nuevo. La identidad de servicio se crea con usuarios y roles estándar de WordPress y la Application Password se gestiona desde el perfil de ese usuario; no hay UI propia que construir.
- **`anti-abuse`** — el anti-abuse engancha `preprocess_comment`, que el controlador REST de comentarios **no** ejecuta; el change no lo modifica ni lo debilita (la vía autenticada queda fuera de su alcance por diseño del core, no por decisión nuestra). En consecuencia, la cota de escritura se especifica como requirement **propio de `valet-replies`**, no como cambio de `anti-abuse`.

### Dependencia externa (consumidor Rust)

El consumidor Rust vive en `/data/rust/valet` y es un sistema externo a este repositorio. La dependencia **ya no es** «que su deserializador tolere campos nuevos»: está **verificado** que los tolera —no existe `deny_unknown_fields` en todo el repositorio— y ese es justamente el problema, porque **los descarta en silencio**. La dependencia real, por tanto, es que los datos auxiliares viajen por el canal que el consumidor **sí** persiste y expone: **`metadata`**.

- **Hecho verificado:** `IngestRequest` (`/data/rust/valet/src/routes/notifications.rs:73-83`) declara solo `message`, `origin`, `author`, `level` y `metadata`; `ingest()` persiste exactamente esos cinco (`:110-118`); `GET /api/notifications/unread` los expone (`src/db/repos/notifications.rs:61`, `:133`, y `openspec/specs/notifications/spec.md:182`). Un `comment_id`/`post_id` de primer nivel se aceptaría sin error y **se perdería**.
- **Consecuencia de alcance:** los identificadores y la marca de procedencia se envían **dentro de `metadata`**, así que **el repositorio Valet no se toca en este change** — el canal ya está soportado de punta a punta (aceptado como objeto JSON con su propio límite `MAX_METADATA_BYTES`, persistido y expuesto).
- **Riesgo abierto (fuera de alcance salvo decisión expresa):** si el asistente consume las notificaciones por el **payload de WebPush** en lugar de `GET /api/notifications/unread`, los identificadores **no le llegarían**, porque `dispatch_push()` recibe hoy solo `origin`, `author` y `message` (`:120-126`). En ese caso haría falta un **change en el repositorio `/data/rust/valet`** para incluir los IDs en el push; eso queda **fuera** de este change y se resolvería con un change propio en el otro repositorio. La pregunta se cierra como bloqueante en la tarea 1.1 (f) antes de dar el GREEN por bueno.
- **Nota de alcance:** en el mensaje de prueba el objeto `metadata` se **omite**, así que la forma del payload de esa ruta no cambia.
- **Hecho positivo:** el límite de `message` está alineado en los dos extremos —`MAX_MESSAGE_BYTES = 4096` (`:28`) coincide con el `MESSAGE_MAX_BYTES` del módulo—, de modo que el recorte byte-safe del PHP no puede rebotar en el consumidor.

### Pieza discutible en alcance

De los cuatro requirements de `valet-replies`, **la cota de escritura es el único cuyo alcance es discutible**: podría argumentarse que es sobre-ingeniería mientras no haya un incidente real, o que su sitio natural sería `anti-abuse`. Se incluye porque sin ella no existe **ningún** límite en el camino de escritura (esta vía esquiva el anti-abuse y WordPress no acota las escrituras autenticadas), y porque el módulo ya tiene el patrón resuelto (contador con clave derivada y hash salado). Si el usuario decide recortar alcance, es el requirement que debe salir, y debe salir **antes** de escribir tests.
