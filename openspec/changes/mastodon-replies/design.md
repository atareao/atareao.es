# Design: Importación de respuestas de Mastodon

## Context

El sitio convierte hoy las respuestas de Mastodon en comentarios con el plugin de terceros **Replies Importer for Mastodon 0.0.1** (Donncha Ó Caoimh), abandonado (10 instalaciones, última publicación en enero de 2025). El código caracterizado vive fuera del repo, en el paquete descargado del plugin, y son **711 líneas** repartidas en cuatro ficheros:

| Fichero | Clase / trait | Responsabilidad |
|---|---|---|
| `replies-importer-for-mastodon.php` | — | Bootstrap: constantes, `require_once` de los cuatro includes y arranque en `plugins_loaded` (`replies_importer_for_mastodon_init()`). |
| `includes/config.php` | `Replies_Importer_For_Mastodon_Config` | Lectura/escritura de las dos opciones y borrado de la conexión. |
| `includes/admin-functions.php` | `Replies_Importer_For_Mastodon_Admin` | Menú, página de ajustes, Settings API, nonces, acciones de admin y programación del cron. |
| `includes/api-functions.php` | `Replies_Importer_For_Mastodon_API` | OAuth (crear app, autorizar, canjear, revocar), importación y desconexión. |
| `includes/debug.php` | trait `Replies_Importer_For_Mastodon_Logger` | `debug_log()` condicionado por `debug_mode`. |

**Opciones legadas.** `replies_importer_for_mastodon_settings` (array con `mastodon_instance_url`, `debug_mode` y `schedule_period`) y `replies_importer_for_mastodon_connection` (array con `client_id`, `client_secret`, `access_token`). La primera se lee en el constructor (`config.php:35`) y la segunda en `config.php:36`; se escriben en `config.php:84` y `config.php:96`; la desconexión borra la segunda con `delete_option` (`config.php:102-103`). `sanitize_settings()` admite además `schedule_period` (`hourly`/`daily`/`disabled`) y, aunque no los renderiza, `client_id`, `client_secret` y `access_token` (`admin-functions.php:202-205`).

**Hooks y UI.** `init()` engancha `admin_menu` (`add_admin_menu`), `admin_init` dos veces (`settings_init` y `handle_actions`) y `api->init()` (`admin-functions.php:20-23`). La página es `options-general.php?page=replies_importer_for_mastodon` (`admin-functions.php:30-36`), con Settings API (`register_setting('replies_importer_for_mastodon_plugin', 'replies_importer_for_mastodon_settings', …)`, `admin-functions.php:150-154`; sección y tres campos en `156-185`). El guardado de la cadencia decide entre `wp_clear_scheduled_hook()` y `$this->schedule_import()` (`admin-functions.php:208-212`); `schedule_import()` limpia y agenda `wp_schedule_event(time() + 10, 'hourly'|'daily', 'replies_importer_for_mastodon_event')` (`admin-functions.php:256-262`). `handle_actions()` procesa «Check Now» (nonce `replies_importer_for_mastodon_check_now`), «Disconnect» (nonce `replies_importer_for_mastodon_disconnect`) y el retorno OAuth con `?code=` (`admin-functions.php:220-254`). El evento se engancha en `api-functions.php:16-18`. **No existe `register_deactivation_hook`** en `replies-importer-for-mastodon.php:1-37`: el cron legado sobrevive a la desactivación.

**OAuth.** `get_authorization_url()` (`api-functions.php:26-54`) crea la app solo si faltan `client_id`/`client_secret`; el `redirect_uri` es `admin_url('options-general.php?page=replies_importer_for_mastodon')` (`api-functions.php:50`) y la URL de autorización añade `response_type=code&scope=read` (`api-functions.php:51`). `create_app()` hace `POST {instance}/api/v1/apps` con `client_name`, `redirect_uris`, `scopes='read'` y `website=get_site_url()` (`api-functions.php:62-81`). `get_access_token()` hace `POST {instance}/oauth/token` con `grant_type=authorization_code`, `code`, `client_id`, `client_secret` y `redirect_uri` (`api-functions.php:90-115`). `disconnect()` vuelve a llamar a `verify_credentials` y luego hace `POST {instance}/oauth/revoke` con `client_id`, `client_secret` y `access_token` antes de borrar la conexión (`api-functions.php:254-286`).

**Algoritmo de importación** (`api-functions.php:120-249`): `verify_credentials` con `Authorization: Bearer` (`135-140`) → `$user_data['url'] . '.rss'` (`148`) → descarga del RSS (`150-157`) → por cada `$rss->channel->item`, si el `description` contiene `home_url()` (`167`) se extraen con regex los `href` que apuntan al sitio (`172-173`) → `url_to_postid($url)` (`184`) → el id del estado es `basename()` del path del `$item->link` (`193`) → `GET {instance}/api/v1/statuses/{id}/context` (`194-201`) → por cada `descendants[]` (`212`): se descartan `private`/`direct` (`213-215`), se deduplica con `get_comments(array('author_url' => $reply['url']))` (`217-220`) y se resuelve el padre con el mapa `$comment_map[$reply['in_reply_to_id']]` (`222-226`). El comentario (`229-241`) lleva `comment_content = wp_strip_all_tags($reply['content'])` (`233`), `comment_author = $reply['account']['display_name']` (`231`), `comment_author_url = $reply['url']` (`232`), `comment_parent` (`235`), `user_id = 0` (`236`), `comment_agent = 'Mastodon'` (`238`), `comment_date = gmdate('Y-m-d H:i:s', strtotime($reply['created_at']))` (`239`) y `comment_approved = 0` (`240`); se inserta con `wp_insert_comment()` (`244`) y se registra en el mapa (`245`).

**Defectos a corregir en el port:**

1. **Credenciales en el log.** `debug_log()` escribe a `error_log` sin redacción cuando `debug_mode` está activo (`debug.php:14-17`) y el importador registra la instancia **y el `access_token` en claro** (`api-functions.php:130-133`). También se registra `client_id` (`api-functions.php:43`, `47`).
2. **Pérdida de enlaces.** `wp_strip_all_tags()` sobre `comment_content` elimina los `<a>` de la respuesta (`api-functions.php:233`).

Restricciones del repo: **no existe framework de tests ni build tools**; la verificación es `just php-lint`, `just phpcs`, un arnés externo de stubs y E2E manual. PSR12, PHP 8.3. Regla de separación: la funcionalidad va en el plugin, el tema es solo presentación. El hub de ajustes `\Atareao\Settings` ya existe y delega en cuatro módulos (`includes/class-settings.php`).

## Goals / Non-Goals

**Goals**

- Absorber en `atareao-functionality` la importación de respuestas de Mastodon sin perder la conexión existente.
- Conservar el comportamiento observable del legado (opciones, endpoints, algoritmo, forma del comentario) **con dos arreglos**: sin credenciales en el log y conservando los enlaces.
- Evitar duplicados durante y después de la migración.
- Exponer la configuración como **quinta pestaña «Mastodon»** del hub «Atareao».
- Limpiar el cron legado huérfano al migrar.
- Documentar por qué ActivityPub no se absorbe.

**Non-Goals**

- No se modifica el sitio público: los comentarios entran pendientes y los aprueba la moderación existente.
- No se renombran ni se borran opciones (ni las legadas ni ninguna otra).
- No se reautoriza por obligación: la migración no exige repetir el flujo OAuth.
- No se absorben CPTs ni datos del legado (no tiene).
- No se absorbe ActivityPub.
- No se introducen dependencias, build tools ni framework de tests.

## Decisions

### Decisión 1: Capability nueva `mastodon-replies` + clase `\Atareao\MastodonReplies`

La funcionalidad se modela como capability nueva `mastodon-replies` y se implementa en `wp-content/plugins/atareao-functionality/includes/class-mastodon-replies.php` con el patrón del repo: `namespace Atareao;`, guarda `if (!defined('ABSPATH')) { exit; }`, `public static function init()` idempotente y registro con `require_once` + `MastodonReplies::init()` en `atareao-functionality.php`.

**Consecuencias:** la lógica queda versionada con el plugin; el tema permanece como presentación. Al archivar se crea `openspec/specs/mastodon-replies/spec.md`.

**Alternativa descartada:** mantener el plugin de terceros. Está abandonado y arrastra los dos defectos.

### Decisión 2: Quinta pestaña del hub, slug `mastodon`

El hub pasa a cinco pestañas, en el orden `matrix`, `pocketid`, `umami`, `mastodon`, `tema` (las integraciones juntas y las opciones de sitio al final), con `mastodon` → «Mastodon». La UI vive en la pestaña; la página legada `options-general.php?page=replies_importer_for_mastodon` desaparece cuando se retire el plugin viejo, **sin redirecciones ni aliases** (misma política que `settings-hub`).

**Consecuencias:** un único punto de entrada y un orden coherente. El delta `admin-settings` modifica tres requirements (`Punto de entrada único y pestañas por URL`, `Delegación en los módulos y envoltorio único` y `Guardado por la vía propia de cada pestaña`).

**Alternativa descartada:** registrar una página propia «Mastodon» aparte. Reintroduce la dispersión que el hub elimina.

### Decisión 3: Opciones propias con prefijo `atareao_mastodon_`, una clave por ajuste

Se usan claves independientes: `atareao_mastodon_instance_url`, `atareao_mastodon_client_id`, `atareao_mastodon_client_secret`, `atareao_mastodon_access_token`, `atareao_mastodon_schedule_period` y `atareao_mastodon_debug_mode`, con default `''`/`0`. **Ningún nombre de opción legada se renombra ni se borra.**

**Consecuencias:** lectura/escritura directa por WP-CLI, saneado por campo y migración 1:1, como en `analytics`. La importación es de solo lectura sobre `replies_importer_for_mastodon_settings` y `replies_importer_for_mastodon_connection`.

**Alternativa descartada:** reutilizar las claves legadas. Mezclaría el ciclo de vida de otro plugin con el nuestro y haría ambigua la coexistencia; además el legado guarda arrays y no una clave por ajuste.

### Decisión 4: Migración a un clic, sin borrar las opciones legadas y limpiando el cron legado

La pestaña ofrece «Importar la conexión de Replies Importer for Mastodon» (POST + nonce + `manage_options`). Lee las dos opciones legadas, vuelca los valores en las claves propias, **no borra** las legadas, informa de lo importado (o de que no encontró nada) y, como limpieza, ejecuta `wp_clear_scheduled_hook('replies_importer_for_mastodon_event')` (el legado no lo hace nunca porque no tiene hook de desactivación). Funciona aunque el plugin legado ya esté desactivado si su opción sigue en la BD. Mismo patrón que la migración de `analytics`.

**Consecuencias:** no se pierde la conexión y no queda cron huérfano. Las opciones legadas sirven de respaldo hasta que el usuario borre el plugin.

**Alternativa descartada:** borrar las opciones legadas tras importar. Elimina el respaldo y hace irreversible un error de importación.

### Decisión 5: Continuidad de la conexión sin reautorizar

El `access_token` sigue sirviendo para la API aunque cambie el `redirect_uri`; `client_id`/`client_secret` solo se usan para autorizar y revocar. La migración, por tanto, **no exige reautorizar**.

**Consecuencia documentada:** si algún día se **reautoriza**, hay que registrar una app nueva con el `redirect_uri` de la pestaña (`admin_url('options-general.php?page=atareao-settings&tab=mastodon')`) y la app antigua queda listada en la cuenta de Mastodon hasta que se revoque a mano. No se automatiza esa limpieza.

**Alternativa descartada:** forzar la reautorización en la migración. Convertiría una operación trivial en un flujo OAuth interactivo y arriesgaría perder la conexión si algo falla a mitad.

### Decisión 6: Coexistencia sin duplicados

Si el plugin legado está cargado **y conectado** (instancia y `access_token` presentes en sus opciones), nuestro importador **no programa su cron** y la pestaña avisa de que hay que retirar el plugin legado al terminar la migración. Cuando ya no está, tomamos el relevo. Además, el dedupe propio reconoce los comentarios que creó el legado (por `author_url`).

**Consecuencias:** durante la ventana en la que ambos conviven no se importa dos veces. La detección es directa (opciones legadas con conexión), no depende del nombre ni del estado del plugin en la lista de activos.

**Alternativa descartada:** `is_plugin_active('replies-importer-for-mastodon/replies-importer-for-mastodon.php')`. Depende de rutas y de que `plugin.php` esté cargado; las opciones conectadas son la señal directa de que el legado va a importar.

### Decisión 7: Dedupe robusto por `comment_meta` propia y por `author_url`

Al insertar cada comentario se guarda el id y/o la URL del estado de Mastodon en un `comment_meta` propio. Antes de insertar se descarta si ya existe un comentario con esa meta **o** si existe uno con el mismo `comment_author_url` (compatibilidad con lo importado por el legado). `comment_author_url` se mantiene como la URL del estado (equivalencia con el legado).

**Consecuencias:** la reimportación es idempotente y la convivencia con lo ya importado por el legado no duplica.

**Alternativa descartada:** deduplicar solo por `author_url`. Ata la identidad del estado a una URL que el legado podía no guardar de forma idéntica para todos los casos; la meta propia es la identidad canónica y `author_url` queda como compatibilidad.

### Decisión 8: Arreglos obligatorios — sin credenciales en el log y enlaces conservados

(a) Nunca se registra el `access_token` ni el `client_secret` (log con prefijo `[atareao-mastodon]`, solo eventos y errores no sensibles). (b) El contenido remoto se sanea con **KSES** (`wp_kses`, etiquetas permitidas en comentarios) conservando los enlaces, en lugar de `wp_strip_all_tags()`.

**Consecuencias:** se eliminan los dos defectos conocidos del legado sin cambiar la forma del comentario.

**Alternativa descartada:** port literal sin arreglos. Filtra credenciales y degrada las respuestas.

### Decisión 9: Cron propio con `atareao_mastodon_import`

El sistema programa `atareao_mastodon_import` con cadencia horaria o diaria al conectar/guardar y lo limpia al desconectar, sin duplicar eventos (comprobando `wp_next_scheduled` antes de agendar). El hook legado `replies_importer_for_mastodon_event` no se usa.

**Consecuencias:** un solo dueño de la importación periódica. Al migrar se limpia el evento legado (Decisión 4).

**Alternativa descartada:** reutilizar el hook legado. Ataría nuestro cron al nombre de un plugin que se va a retirar.

### Decisión 10: Desconexión que revoca y borra solo lo propio

«Desconectar» llama a `POST {instancia}/oauth/revoke` y después borra **solo** nuestras opciones de conexión (`access_token` y, si procede, `client_id`/`client_secret`), **nunca** las legadas.

**Consecuencias:** la desconexión es segura y reversible por reautorización; no toca el respaldo legado.

**Alternativa descartada:** borrar las opciones legadas al desconectar. Destruiría el respaldo de la migración.

### Decisión 11: Todo admin-only, con nonce y validación

Toda la pestaña y sus acciones exigen `manage_options`; el guardado va por POST con nonce propio, el botón «Comprobar ahora» tiene el suyo, y todas las llamadas usan `wp_remote_*` con timeout y validación de código HTTP y JSON. La instancia configurada se valida como `https://`.

**Consecuencias:** la superficie de ataque queda acotada a wp-admin con capacidad de administrador; no hay endpoints públicos nuevos.

**Alternativa descartada:** exponer un endpoint REST o un cron con clave. Añadiría superficie pública sin necesidad.

### Decisión 12: Los comentarios importados entran pendientes

`comment_approved = 0`, igual que el legado. Pasan por la moderación del sitio y, al aprobarse, ya generan el aviso de Matrix que existe hoy.

**Consecuencias:** cambio nulo en el sitio público; ningún comentario aparece publicado sin revisión.

**Alternativa descartada:** importar como aprobados. Publicaría automáticamente contenido remoto y saltaría la moderación existente.

### Decisión 13: ActivityPub se retira; no se absorbe

El `README.md` del plugin documentará por qué NO se absorbe ActivityPub: 74.500 líneas, 39 rutas REST, 187 clases, mantenido por Automattic; y que su WebFinger está tapado por una regla de nginx que responde con la cuenta de `mastodon.social`. Es una tarea de documentación dentro de este change, sin código.

**Consecuencias:** se deja constancia de una decisión aparte ya confirmada, para que no se reabra por error. No se toca ActivityPub ni la configuración de nginx.

**Alternativa descartada:** absorber ActivityPub junto con las respuestas. Inviable por tamaño, mantenimiento ajeno y solapamiento con la federación ya cubierta.

### Decisiones de la revisión independiente (14-20)

Las decisiones 14 a 20 recogen las correcciones derivadas de una **revisión independiente** (calidad y seguridad) de la primera implementación, que encontró dos hallazgos altos y varios medios/bajos. El diseño original (Decisiones 1-13) no las contemplaba; se documentan aquí para que el artefacto refleje lo implementado y lo verificado por el arnés.

### Decisión 14: Acciones en `admin_init` y render de solo lectura

Las acciones de la pestaña (guardar, autorizar, callback OAuth, «Comprobar ahora», desconectar e importar del legado) se procesan en `admin_init`; el render (`renderSettingsPage()`) es de **solo lectura**: no hace HTTP, no escribe opciones y no redirige. El motivo es de orden de ejecución del core: en `wp-admin/admin.php` el orden es `do_action('admin_init')` → `require_once ABSPATH . 'wp-admin/admin-header.php'` → `do_action($page_hook)`, de modo que un `wp_safe_redirect()` lanzado desde el callback de la página llegaría **después** de que se hayan enviado las cabeceras y no podría redirigir. Cada acción comprueba `manage_options`, valida su nonce y termina en `wp_safe_redirect()` hacia la pestaña con el aviso en el transient `atareao_mastodon_notice`.

**Consecuencias:** los POST funcionan y las cabeceras quedan libres para redirigir; el render es idempotente y no dispara llamadas remotas al pintar (evita registros de app o peticiones accidentales).

**Alternativa descartada:** procesar los POST en el callback de la página. Con el orden real del core las cabeceras ya están enviadas y el `wp_safe_redirect()` no surte efecto.

### Decisión 15: Autorización en dos pasos con `state` de un solo uso

`wp_safe_redirect()` no puede saltar a un host externo, así que la pestaña no puede redirigir directamente a la instancia. El manejador POST «Autorizar» registra la app si falta, genera un `state` aleatorio y **no** redirige: deja la URL de autorización en el transient `atareao_mastodon_auth_url` y el `state` (con el usuario) en `atareao_mastodon_oauth_state`, ambos con TTL de 600 s; el render pinta el enlace «Continuar la autorización». El callback OAuth llega desde el navegador abierto en la instancia y **no puede llevar nonce** de WordPress: el control de integridad equivalente es el `state`, comparado con `hash_equals`, atado al usuario que lo generó y de **un solo uso** (el transient se borra en cada callback, válido o no).

**Consecuencias:** el flujo funciona dentro de las restricciones de `wp_safe_redirect()` y resiste CSRF en el retorno; reutilizar o falsificar el `state` no canjea el código.

**Alternativa descartada:** confiar solo en el nonce. El retorno lo origina el navegador desde la instancia, no un formulario propio, así que el nonce no viaja.

### Decisión 16: Relevo automático del cron

El relevo no depende de volver a guardar la cadencia. `ensureSchedule()` se engancha al final de `admin_init` y agenda `atareao_mastodon_import` cuando hay conexión propia, `legacyWillImport()` es falso y no hay ya un evento programado; así ve la conexión recién importada en la misma petición. Además, `deactivated_plugin` escucha la desactivación del slug `replies-importer-for-mastodon` y fuerza el agendado (`ensureSchedule(true)`) en la misma petición de desactivación, cuando la clase legada aún está cargada y `legacyWillImport()` seguiría devolviendo verdadero.

**Consecuencias:** al retirar el plugin legado, nuestro cron toma el relevo de inmediato, sin una ventana en la que nadie importe y sin exigir que el usuario vuelva a guardar.

**Alternativa descartada:** agendar solo al guardar. Dejaría de agendarse la conexión migrada y abriría una ventana sin importación tras desactivar el legado.

### Decisión 17: Host y red acotados

Todas las llamadas a la instancia usan `wp_safe_remote_get()` / `wp_safe_remote_post()` con `redirection => 0`, para no seguir una redirección hacia otro host. El `url` devuelto por `verify_credentials` debe ser `https` y del **mismo host** que la instancia configurada; si no coincide, la importación se omite y se registra. La instancia se valida como `https://` con host no vacío antes de cualquier llamada.

**Riesgo aceptado explícito:** una instancia servida en una IP privada o en un host distinto del configurado dejaría de funcionar. Es la contrapartida de cerrar la superficie de SSRF; en el caso de uso actual (instancias públicas de Mastodon) no aplica.

**Alternativa descartada:** `wp_remote_*` sin `redirection` y sin comprobar el host de `verify_credentials`. Permitiría que una redirección o una respuesta manipulada dirigieran el `Bearer` a un host ajeno.

### Decisión 18: Topes por ejecución

Cada run acota su coste con `RSS_LIMIT = 20` (ítems del feed), `CONTEXT_LIMIT = 10` (llamadas a `/context`) y `COMMENT_LIMIT = 100` (comentarios insertados). Al truncar se registra con `debugLog()`.

**Consecuencias:** una cuenta con mucho volumen no agota el tiempo de la petición ni la cuota de la instancia; la siguiente ejecución continúa desde donde el dedupe deje.

**Alternativa descartada:** sin topes. Un RSS grande o una cuenta muy activa podrían provocar timeouts y consumo desmedido de la API.

### Decisión 19: Fecha real en GMT y su equivalente local

El comentario se inserta con `comment_date_gmt` = fecha real del `created_at` y `comment_date` = su equivalente local calculado con `get_date_from_gmt(comment_date_gmt)`. Corrige la doble conversión del legado, que escribía `gmdate(...)` en `comment_date` (campo local) y dejaba `comment_date_gmt` con el valor por defecto de WordPress.

**Consecuencias:** la fecha mostrada es la real del estado en la zona del sitio; GMT y local quedan coherentes.

**Alternativa descartada:** port literal de `comment_date = gmdate(...)`. WordPress volvía a convertir y la fecha mostrada quedaba desfasada.

### Decisión 20: Resumen del último run, aviso por transient y saneado de secretos

El resultado de cada run se persiste en la opción `atareao_mastodon_last_run` (fecha, insertados, omitidos y último error) y la pestaña lo pinta en solo lectura; los avisos de las acciones viajan por el transient `atareao_mastodon_notice`, que sobrevive a la redirección. `sanitizeSecret()` recorta el espacio exterior y rechaza `access_token` y `client_secret` con espacios internos o caracteres de control (señal de valor corrupto), conservando el resto de caracteres atípicos válidos en lugar de mutilarlos; `client_id` usa `sanitize_text_field`.

**Consecuencias:** el usuario ve qué pasó en la última importación sin depender del log, y las credenciales no se corrompen ni se filtran al saneado genérico de texto.

**Alternativa descartada:** guardar el resumen en un transient. Se perdería por expiración y la pestaña no podría mostrar el último error de forma persistente; el saneado genérico de texto también mutilaría tokens con caracteres válidos.

## Alternatives discarded (resumen)

- **Port literal sin arreglos**: replica el filtrado de credenciales y la pérdida de enlaces. Descartada; el usuario eligió «equivalencia + arreglos».
- **Reutilizar las claves de opción del legado**: mezcla ciclos de vida y complica la coexistencia; el legado guarda arrays. Descartada.
- **Dedupe solo por `author_url`**: sin identidad canónica del estado, frágil ante variaciones de URL. Descartada.
- **Importar como aprobados**: salta la moderación y publica contenido remoto sin revisión. Descartada.
- **Absorber ActivityPub**: 74.500 líneas, 187 clases y 39 rutas REST mantenidas por terceros; WebFinger ya tapado por nginx. Descartada.
- **Página propia «Mastodon» fuera del hub**: reintroduce la dispersión que el hub elimina. Descartada.
- **Endpoint REST o cron con clave pública**: añade superficie sin necesidad. Descartada.

## Risks / Trade-offs

- **[Duplicación de importación durante la convivencia con el legado]** → Si ambos importaran, se duplicarían comentarios. Mitigado con la Decisión 6 (no programamos cron si el legado está cargado y conectado) y con la Decisión 7 (dedupe por meta propia + `author_url`, que reconoce lo ya importado por el legado).
- **[Credenciales en el log]** → El defecto del legado se reproduce si se copia su `debug_log`. Mitigado con la Decisión 8(a): redacción y prefijo `[atareao-mastodon]`, con una comprobación dedicada en el arnés y en el E2E.
- **[Pérdida de enlaces]** → `wp_strip_all_tags()` los elimina. Mitigado con la Decisión 8(b): KSES conservando enlaces, verificado por arnés.
- **[Reautorización que deja una app antigua en Mastodon]** → Consecuencia documentada en la Decisión 5; la migración no exige reautorizar y la app antigua se revoca a mano.
- **[Cron legado huérfano]** → El legado no lo limpia nunca. Mitigado porque la importación ejecuta `wp_clear_scheduled_hook('replies_importer_for_mastodon_event')` (Decisión 4).
- **[Instancia no `https` o respuestas no JSON]** → Mitigado validando `https://`, timeout, código HTTP y decodificación JSON antes de recorrer `descendants` (Decisiones 11 y «Errores accionables»).
- **[Variación de la estructura del RSS o del `context` de Mastodon]** → El descubrimiento depende del RSS de la cuenta y del campo `descendants`. El arnés fija una muestra representativa; el E2E manual valida contra la instancia real. Riesgo asumido y acotado por el contrato de Mastodon.
- **[Verificación sin framework de tests]** → No hay tests automatizados en el repo. La verificación es estática (`just php-lint`, `just phpcs`), con arnés externo de stubs y E2E manual en producción.
- **[Acciones que no redirigen por el orden del core]** → Con el procesado en el callback de la página, `wp_safe_redirect()` llegaría con las cabeceras ya enviadas. Mitigado con la Decisión 14 (procesado en `admin_init` y render de solo lectura).
- **[Replay o falsificación del callback OAuth]** → El retorno desde la instancia no puede llevar nonce de WordPress. Mitigado con la Decisión 15: `state` aleatorio, atado al usuario, con TTL de 600 s y de un solo uso (`hash_equals` + borrado).
- **[SSRF o `Bearer` enviado a un host ajeno]** → Una redirección o un `url` manipulado en `verify_credentials` podrían llevar el token a otro host. Mitigado con la Decisión 17 (`redirection => 0` y comprobación de `https` + mismo host). Riesgo aceptado: una instancia en IP privada u host distinto deja de funcionar.
- **[Importación desbocada]** → Una cuenta con mucho volumen podría agotar el tiempo de la petición o la cuota de la API. Mitigado con los topes de la Decisión 18 (`RSS_LIMIT`/`CONTEXT_LIMIT`/`COMMENT_LIMIT`).
- **[Fecha del comentario desfasada]** → El legado escribía `gmdate()` en el campo local y WordPress volvía a convertir. Mitigado con la Decisión 19 (`comment_date_gmt` real y `comment_date` con `get_date_from_gmt`).
- **[Ventana sin importación al retirar el legado]** → Agendar solo al guardar dejaría sin relevo la conexión migrada. Mitigado con la Decisión 16 (`ensureSchedule()` en `admin_init` y `deactivated_plugin` del slug legado).

## Migration Plan

Orden obligatorio:

1. **Instalar/actualizar** `atareao-functionality` (con `class-mastodon-replies.php`).
2. **Importar a un clic** desde la pestaña «Mastodon» («Importar la conexión de Replies Importer for Mastodon»): vuelca instancia, `client_id`, `client_secret` y `access_token` en las claves propias, sin borrar las legadas, y limpia el cron legado.
3. **Verificar que importa y no duplica**: comprobar los ajustes migrados, el aviso de coexistencia y una importación de prueba (los comentarios entran pendientes y no se repiten al reimportar).
4. **Desactivar y borrar** el plugin legado. El relevo del cron es **automático**: `admin_init` agenda la importación propia en cuanto ve la conexión (también la recién migrada) y, además, la desactivación del slug legado la fuerza en la misma petición, así que **no hace falta volver a guardar** la cadencia para que arranque la importación.

**Rollback:** reactivar el plugin legado o revertir el commit que registra `MastodonReplies`. Las opciones legadas nunca se borran, así que la conexión se puede recuperar; las claves `atareao_mastodon_*` pueden quedarse o borrarse por WP-CLI.

## Verification

> El repositorio **no tiene framework de tests** ni build tools. La verificación combina análisis estático, un **arnés externo de stubs** que vive solo en `/tmp/opencode/mastodon-harness/` (fuera del repo y no versionado) y E2E manual en producción. La implementación arranca **solo tras la aprobación del usuario**.

- **Lint**: `just php-lint` → 0 errores.
- **phpcs**: `just phpcs` (theme+plugin) sin empeorar el baseline. Baseline medido (2026-10-03) en theme+plugin: **752 errores / 426 warnings**. Objetivo de delta **+0 errores** (se espera +1 warning `PSR1.Files.SideEffects`, inherente a la guarda `ABSPATH` y presente en todas las clases).
- **Arnés externo de stubs** (`/tmp/opencode/mastodon-harness/`, no versionado): define de forma controlable las funciones de WordPress usadas (`wp_remote_*`, `get_option`/`update_option`/`delete_option`, `get_comments`, `wp_insert_comment`, `add_comment_meta`, `wp_kses`, `wp_next_scheduled`/`wp_schedule_event`/`wp_clear_scheduled_hook`, `url_to_postid`, `home_url`, `current_user_can`, `check_admin_referer`, condicionales de admin, etc.) y comprueba con un RSS y un `context` simulados:
  1. **Importación**: descubre el estado que enlaza al sitio, inserta sus descendientes como comentarios **pendientes** con el hilo resuelto por `in_reply_to_id`, autor, URL del estado, fecha real y `comment_agent = Mastodon`; descarta `private`/`direct`.
  2. **Dedupe**: un comentario ya importado (por meta propia) y uno ya existente por `author_url` (legado) no se duplican; reimportar no aumenta el número de comentarios.
  3. **Sanitización**: los enlaces se conservan y las etiquetas no permitidas se eliminan; no aparece ningún secreto en el log.
  4. **Migración**: lee las dos opciones legadas, vuelca los valores en las claves propias, **no borra** las legadas, informa de lo importado (o de que no encontró nada) y limpia `replies_importer_for_mastodon_event`.
  5. **Coexistencia**: con el legado cargado y conectado no se programa `atareao_mastodon_import` y la pestaña avisa; sin el legado, se programa.
  6. **Desconexión**: llama a `/oauth/revoke` y borra solo las opciones propias.
  7. **Conexión**: `redirect_uris` apunta a `options-general.php?page=atareao-settings&tab=mastodon`, scopes `read`; instancia no `https` rechazada.
  8. **Correcciones de la revisión independiente**: acciones en `admin_init` con el render sin efectos, `state` OAuth de un solo uso, validación de `https`/mismo host con `redirection => 0`, topes de RSS/contexto/comentarios, fecha real GMT + local, `ensureSchedule()` en `admin_init` y `deactivated_plugin` del slug legado, aviso accionable y resumen del último run.
  Resultado actual del arnés: **`TOTAL=51 PASS=51 FAIL=0`, `exit=0`**.
- **E2E manual en producción**: instalar → importar a un clic → verificar ajustes migrados y aviso de coexistencia → comprobar que una importación de prueba crea comentarios pendientes y que reimportar no duplica → desactivar y borrar el plugin legado → comprobar que el cron legado ya no está, que el nuestro se programa y que el sitio público no cambia (los comentarios siguen pendientes hasta aprobarlos).
- **No-regresión**: el hub muestra cinco pestañas (`tab=matrix|pocketid|umami|mastodon|tema`), `tab` ausente/desconocido → `matrix`, el resto de pestañas conserva su comportamiento, y el microsite `/tools/`, la analítica, el login/logout y las notificaciones Matrix no cambian.
- `openspec validate mastodon-replies --strict` sin hallazgos.

## Open Questions

Ninguna. El orden de las pestañas, las claves de opción, los arreglos, el dedupe, la migración, la coexistencia y la no-absorción de ActivityPub quedan resueltos arriba.
