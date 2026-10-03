# Proposal: Importación de respuestas de Mastodon

## Why

Hoy atareao.es convierte las respuestas que recibe en Mastodon en comentarios **pendientes** de WordPress con el plugin de terceros **Replies Importer for Mastodon 0.0.1** (autor `Donncha Ó Caoimh`, 711 líneas). Ese plugin está **abandonado**: 10 instalaciones y última publicación en enero de 2025. No solo duplica una responsabilidad que pertenece al plugin del sitio, sino que arrastra dos defectos:

1. **Filtra el access token.** Con `debug_mode` activo, `Replies_Importer_For_Mastodon_API::fetch_and_import_mastodon_comments()` registra la URL de la instancia **y el `access_token` en claro** en el `error_log` (`includes/api-functions.php:130-133`), y el trait `debug_log()` (`includes/debug.php:14-17`) los escribe sin ninguna redacción. Un `.error_log` legible es una credencial filtrada.
2. **Pierde los enlaces.** El comentario se inserta con `wp_strip_all_tags($reply['content'])` (`includes/api-functions.php:233`), de modo que los enlaces que el autor incluye en su respuesta desaparecen del comentario.

Además, el plugin **no tiene hook de desactivación**: el evento `replies_importer_for_mastodon_event` queda agendado en la tabla de cron para siempre aunque el plugin se desactive o se borre (no existe `register_deactivation_hook` en `replies-importer-for-mastodon.php`). Retirarlo hoy deja basura en el cron.

El objetivo es absorber esa funcionalidad como **quinta pestaña «Mastodon»** del hub de ajustes «Atareao», **sin perder la conexión existente y sin duplicar comentarios**, corrigiendo los dos defectos y limpiando el cron huérfano, sin tocar el sitio público.

## What Changes

- **Nueva capability `mastodon-replies`**: conexión OAuth con la instancia, ajustes y cadencia, importación de respuestas como comentarios pendientes con hilo, dedupe robusto, sanitización conservando enlaces, migración desde el plugin legado, coexistencia sin duplicados y errores accionables.
- **Nueva clase `\Atareao\MastodonReplies`** en `wp-content/plugins/atareao-functionality/includes/class-mastodon-replies.php`, con el patrón del repo (`namespace Atareao;`, guarda `ABSPATH`, `init()` idempotente) y registrada con `require_once` + `MastodonReplies::init()` en `atareao-functionality.php`.
- **Quinta pestaña «Mastodon»** del hub: slug `mastodon`, etiqueta «Mastodon» y orden `matrix`, `pocketid`, `umami`, `mastodon`, `tema`. La UI vive en la pestaña; la página legada `options-general.php?page=replies_importer_for_mastodon` desaparece cuando se retire el plugin viejo, **sin redirecciones ni aliases** (misma política que el change `settings-hub`).
- **Opciones propias con prefijo `atareao_mastodon_`**, una clave por ajuste (como `analytics`): instancia, `client_id`, `client_secret`, `access_token`, cadencia y debug, todas con default `''`/`0`. **Ningún nombre de opción legada se renombra ni se borra.**
- **Migración a un clic** («Importar la conexión de Replies Importer for Mastodon»): lee `replies_importer_for_mastodon_settings` y `replies_importer_for_mastodon_connection`, vuelca los valores en las claves nuevas, **no borra** las legadas, informa de lo importado (o de que no encontró nada) y ejecuta `wp_clear_scheduled_hook('replies_importer_for_mastodon_event')` para no dejar el cron huérfano. Funciona aunque el plugin legado ya esté desactivado si su opción sigue en la BD.
- **Continuidad de la conexión sin reautorizar**: el `access_token` sigue sirviendo para la API aunque cambie el `redirect_uri`; `client_id`/`client_secret` solo se usan para autorizar y revocar. Si algún día se **reautoriza**, hay que registrar una app nueva con el `redirect_uri` de la pestaña (`options-general.php?page=atareao-settings&tab=mastodon`); la app antigua queda listada en la cuenta de Mastodon hasta que se revoque a mano.
- **Coexistencia sin duplicados**: si el plugin legado está cargado **y conectado** (instancia + `access_token` presentes), nuestro importador **no programa su cron** y la pestaña avisa de que hay que retirar el plugin legado al terminar la migración. El dedupe propio reconoce además los comentarios que creó el legado (por `author_url`).
- **Arreglos obligatorios**: (a) **nunca** registrar el `access_token` ni el `client_secret` en el log (log con prefijo `[atareao-mastodon]`, solo eventos y errores no sensibles); (b) sanitizar el contenido remoto con **KSES conservando los enlaces** (etiquetas permitidas en comentarios) en lugar de `wp_strip_all_tags()`.
- **Cron propio** con hook `atareao_mastodon_import`, cadencia horaria o diaria, programado al conectar/guardar y limpiado al desconectar, sin duplicar eventos.
- **Desconexión**: revoca el token en la instancia (`/oauth/revoke`) y borra solo nuestras opciones de conexión (nunca las legadas).
- **Documentación**: el `README.md` del plugin documenta la pestaña «Mastodon» y **por qué NO se absorbe ActivityPub** (74.500 líneas, 39 rutas REST, 187 clases, mantenido por Automattic), y que su WebFinger está tapado por una regla de nginx que responde con la cuenta de `mastodon.social`.
- **No cambia el sitio público**: los comentarios importados entran pendientes (`comment_approved = 0`), pasan por la moderación del sitio y, al aprobarse, generan el aviso de Matrix que ya existe.
- **No se absorben** CPTs ni datos del legado (no tiene).

## Capabilities

### New Capabilities

- `mastodon-replies`: conexión OAuth con la instancia, ajustes y cadencia, importación de respuestas como comentarios pendientes, dedupe e idempotencia, sanitización con enlaces, migración y coexistencia con el plugin legado, y errores accionables.

### Modified Capabilities

- `admin-settings`: el hub pasa de cuatro a cinco pestañas con `mastodon` → «Mastodon», añade `MastodonReplies::renderSettingsPage()` a la delegación y reconoce el guardado por POST con nonce propio de la pestaña `mastodon` más su botón «Comprobar ahora», conservando el resto del comportamiento palabra por palabra.

## Impact

- **Archivos**:
  - Nuevo: `wp-content/plugins/atareao-functionality/includes/class-mastodon-replies.php` (clase `\Atareao\MastodonReplies`).
  - Modificado: `wp-content/plugins/atareao-functionality/atareao-functionality.php` (`require_once` + `MastodonReplies::init()`).
  - Modificado: `wp-content/plugins/atareao-functionality/README.md` (sección «Mastodon» y nota de no-absorción de ActivityPub).
  - Modificado (spec): `openspec/specs/admin-settings/spec.md` vía el delta `specs/admin-settings/spec.md` (3 requirements MODIFIED).
  - Nuevo (spec): `openspec/specs/mastodon-replies/spec.md` vía el delta `specs/mastodon-replies/spec.md`.
- **No cambia**: el sitio público (los comentarios entran pendientes), el HTML de la analítica, el flujo de login/logout, las notificaciones Matrix ni el microsite `/tools/`.
- **Sin renombrar ni borrar opciones**: ni las legadas (`replies_importer_for_mastodon_settings` / `_connection`) ni ninguna otra. La importación es de solo lectura sobre las legadas.
- **Dependencias**: ninguna nueva (solo `wp_remote_*`, `simplexml`, la Settings API y comentarios de core).
- **Compatibilidad**: PHP 8.3, PSR12, WordPress 6.0+.
