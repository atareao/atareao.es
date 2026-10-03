# Tasks: Importación de respuestas de Mastodon

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación del change combina análisis estático (`just php-lint`, `just phpcs`), un **arnés de stubs externo** que vive solo en `/tmp/opencode/mastodon-harness/` (fuera del repo y no versionado) y E2E manual en producción. El arnés no forma parte del commit ni del árbol.

## 1. Phase 0 — Caracterización del legado

- [x] 1.1 Documentar el inventario del legado en `design.md` §Context (opciones, hooks, endpoints, algoritmo, forma del comentario y los dos defectos) con evidencia fichero:línea. **Verificación:** cada afirmación del inventario cita una línea existente en `/tmp/opencode/plugins/replies-importer-for-mastodon/replies-importer-for-mastodon/`; `openspec validate mastodon-replies --strict` válido. **Evidencia:** `design.md` §Context con citas `fichero:línea`; `openspec validate` → «Change 'mastodon-replies' is valid».
- [x] 1.2 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el baseline y se anota el par errores/warnings. **Evidencia:** baseline medido (2026-10-03) theme+plugin: **752 errores / 426 warnings**; `just php-lint` → 0 errores.

## 2. Clase `MastodonReplies` y sus opciones

- [x] 2.1 Crear `includes/class-mastodon-replies.php` con `namespace Atareao;`, guarda `ABSPATH` e `init()` idempotente que solo engancha hooks. **Verificación:** `just php-lint` sin errores; `phpcs --report=source` del fichero solo con el warning `PSR1.Files.SideEffects`. **Evidencia:** `just php-lint` → 0 errores; phpcs del fichero: único aviso `PSR1.Files.SideEffects.FoundWithSymbols`.
- [x] 2.2 Definir las claves `atareao_mastodon_instance_url|client_id|client_secret|access_token|schedule_period|debug_mode` con default `''`/`0` y saneado por tipo (`esc_url_raw`, `sanitize_text_field`, banderas `0`/`1`). **Verificación:** arnés — guardado saneado por tipo; instancia no `https` rechazada. **Evidencia:** arnés S1 (`sanitized=true persistido=true`) y O4 (`false`/`false`, 0 llamadas).
- [x] 2.3 Registrar en `atareao-functionality.php` con `require_once` + `\Atareao\MastodonReplies::init();`. **Verificación:** `rg -n "class-mastodon-replies|MastodonReplies::init" atareao-functionality.php` → 2 coincidencias; `just php-lint` sin errores. **Evidencia:** arnés H7 (`require=true init=true`); `just php-lint` → 0 errores; presente la guarda estática de `init()` idempotente.

## 3. La pestaña en el hub (cinco pestañas)

- [x] 3.1 Añadir la pestaña `mastodon` → «Mastodon» al hub en el orden `matrix`, `pocketid`, `umami`, `mastodon`, `tema`, delegando en `MastodonReplies::renderSettingsPage()`. **Verificación:** arnés — `tabs()` contiene las cinco entradas en orden y la pestaña delega en el render del módulo. **Evidencia:** arnés H1 y H5.
- [x] 3.2 Confirmar que `MastodonReplies::renderSettingsPage()` no imprime `.wrap` ni `<h1>` (envoltorio único del hub). **Verificación:** arnés — la página contiene un único `.wrap` y un único `<h1>`. **Evidencia:** arnés H6 (`wrap=1 h1=1 modulo_limpio=true`).
- [x] 3.3 Actualizar el delta `specs/admin-settings/spec.md` (headers exactos de los 3 requirements MODIFIED, escenarios conservados) y validar. **Verificación:** `openspec validate mastodon-replies --strict` válido. **Evidencia:** delta con los 3 requirements MODIFIED; `openspec validate` → válido.

## 4. OAuth y desconexión

- [x] 4.1 `create_app()`: `POST {instancia}/api/v1/apps` con `client_name`, `redirect_uris` = `admin_url('options-general.php?page=atareao-settings&tab=mastodon')`, `scopes=read` y `website`. **Verificación:** arnés — la petición lleva esos campos y `redirect_uris` apunta a la pestaña. **Evidencia:** arnés O1.
- [x] 4.2 URL de autorización y canje del código (`/oauth/authorize` y `/oauth/token`) con el mismo `redirect_uri` y persistencia del `access_token`. **Verificación:** arnés — canje con `grant_type=authorization_code`; nonce/capability exigidos. **Evidencia:** arnés O2.
- [x] 4.3 `disconnect()`: `POST {instancia}/oauth/revoke` y borrado solo de las opciones propias de conexión. **Verificación:** arnés — se llama a `/oauth/revoke` y las opciones legadas quedan intactas. **Evidencia:** arnés O3.
- [x] 4.4 Validar la instancia como `https://` antes de cualquier llamada. **Verificación:** arnés — instancia vacía o `http://` no dispara peticiones y muestra error. **Evidencia:** arnés O4.

## 5. Importación, dedupe y cron propio

- [x] 5.1 Descubrimiento por RSS: `verify_credentials` (Bearer) → `url . '.rss'` → ítems que contengan `home_url()` → `href` al sitio → `url_to_postid()`. **Verificación:** arnés — con RSS simulado, solo los estados que enlazan a una entrada real producen importación. **Evidencia:** arnés I1 e I4.
- [x] 5.2 Contexto del estado: id por `basename` del enlace, `GET /api/v1/statuses/{id}/context`, descarte de `private`/`direct` y validación de código HTTP/JSON. **Verificación:** arnés — `private`/`direct` no se insertan; HTTP distinto de 200 o JSON inválido no inserta y continúa. **Evidencia:** arnés I3 e I5.
- [x] 5.3 Inserción del comentario pendiente con hilo: `comment_parent` por `in_reply_to_id`, `comment_agent = Mastodon`, `gmdate(created_at)`, `user_id = 0`, `comment_approved = 0`. **Verificación:** arnés — el hilo y los campos del comentario coinciden con el contrato. **Evidencia:** arnés I1 e I2.
- [x] 5.4 Dedupe por `comment_meta` propia + compatibilidad con `author_url`; reimportar no duplica. **Verificación:** arnés — ya importado por meta o por `author_url` no se reinserta; doble ejecución no aumenta el recuento. **Evidencia:** arnés D1, D2 y D3.
- [x] 5.5 Cron propio `atareao_mastodon_import` (hourly/daily), programado al conectar/guardar sin duplicar y limpiado al desconectar. **Verificación:** arnés — `wp_next_scheduled` evita duplicados; cambiar cadencia reprograma; desconectar limpia. **Evidencia:** arnés S3 y S4.

## 6. Migración y coexistencia

- [x] 6.1 Acción «Importar la conexión de Replies Importer for Mastodon»: lee las dos opciones legadas, vuelca en las propias, **no borra** las legadas e informa de lo importado o de que no encontró nada. **Verificación:** arnés — valores volcados; opciones legadas intactas; funciona con el legado desactivado si la opción persiste. **Evidencia:** arnés M1 (count=6, legado intacto) y M2 (count=0).
- [x] 6.2 Limpieza del cron legado al importar: `wp_clear_scheduled_hook('replies_importer_for_mastodon_event')`. **Verificación:** arnés — el hook legado queda limpio. **Evidencia:** arnés M3.
- [x] 6.3 Coexistencia: si el legado está cargado y conectado, no programar `atareao_mastodon_import` y avisar en la pestaña; sin el legado, tomar el relevo. **Verificación:** arnés — no se agenda con el legado conectado y se agenda sin él; aviso presente. **Evidencia:** arnés M4 y M5.

## 7. Seguridad y sanitización

- [x] 7.1 Log sin secretos: prefijo `[atareao-mastodon]`, nunca `access_token` ni `client_secret`. **Verificación:** arnés — ninguna entrada de log contiene el token ni el secret. **Evidencia:** arnés O5 y E1.
- [x] 7.2 KSES conservando enlaces: `wp_kses` con las etiquetas permitidas en comentarios en lugar de `wp_strip_all_tags()`. **Verificación:** arnés — el enlace se conserva; las etiquetas no permitidas se eliminan. **Evidencia:** arnés Z1 y Z2.
- [x] 7.3 Todo admin-only: `manage_options` en la pestaña y en cada acción; nonce propio en guardado y en «Comprobar ahora». **Verificación:** arnés — sin permisos no se accede ni se ejecuta; nonce inválido se rechaza. **Evidencia:** arnés Z3 y Z4.
- [x] 7.4 `wp_remote_*` con timeout y errores accionables sin secretos mostrados en la pestaña. **Verificación:** arnés — `WP_Error`, HTTP no 2xx y JSON mal formado se registran y se muestran sin credenciales. **Evidencia:** arnés E1, E2, E3 e I6.

## 8. README

- [x] 8.1 Documentar la pestaña «Mastodon» (orden de migración, claves de opción, cron propio y limpieza del cron legado). **Verificación:** revisión del `README.md`; `rg -n "atareao_mastodon_|Comprobar ahora" wp-content/plugins/atareao-functionality/README.md` muestra la sección. **Evidencia:** sección «Mastodon (respuestas)» con conexión, cadencia, «Comprobar ahora», importación desde el legado y tabla de claves.
- [x] 8.2 Documentar por qué NO se absorbe ActivityPub (74.500 líneas, 39 rutas REST, 187 clases, mantenido por Automattic; WebFinger tapado por nginx que responde con `mastodon.social`). **Verificación:** revisión del `README.md`; la sección menciona los tres motivos. **Evidencia:** sección «Plugins de terceros» con el detalle de líneas/clases/rutas y el WebFinger tapado por nginx.

## 9. Verificación

- [x] 9.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.2 (se espera +1 warning `PSR1.Files.SideEffects`). **Evidencia:** `just php-lint` → 0 errores; phpcs 752 errores / 427 warnings → **+0 errores / +1 warning** (SideEffects).
- [x] 9.2 Arnés externo completo. **Verificación:** `/tmp/opencode/mastodon-harness/` → `TOTAL=N PASS=N FAIL=0`, `exit=0`; cubre importación con RSS y context simulados, dedupe, hilo, pendientes, sanitización conservando enlaces, migración que no borra, limpieza del cron legado, abstención en coexistencia y desconexión. **Evidencia:** `TOTAL=40 PASS=40 FAIL=0`, exit 0.
- [ ] 9.3 E2E manual en producción con el plugin legado **puesto** (importar a un clic, aviso de coexistencia, no duplicación) y **quitado** (nuestro cron se programa, el sitio público no cambia). **Verificación:** checklist del `design.md` §Verification. **Evidencia:** pendiente (requiere producción y conexión real con Mastodon).
- [ ] 9.4 No-regresión del hub y del sitio público: cinco pestañas (`tab=matrix|pocketid|umami|mastodon|tema`), `tab` inválido → `matrix`, comentarios siguen pendientes hasta aprobarlos, analítica/login/Matrix/microsite `/tools/` intactos. **Verificación:** E2E manual. **Evidencia:** pendiente (requiere E2E en producción).
- [x] 9.5 Spec. **Verificación:** `openspec validate mastodon-replies --strict` sin hallazgos; `openspec validate --all` válido. **Evidencia:** `openspec validate mastodon-replies --strict` → «Change 'mastodon-replies' is valid».

## 10. Entrega

- [ ] 10.1 PR por gitflow de `feature/mastodon-replies` a `development` con commits convencionales. **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia:** pendiente.
- [ ] 10.2 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive mastodon-replies` aplica los deltas (crea `openspec/specs/mastodon-replies/spec.md` y actualiza `admin-settings`); `openspec list` ya no muestra el change activo. **Evidencia:** pendiente.
