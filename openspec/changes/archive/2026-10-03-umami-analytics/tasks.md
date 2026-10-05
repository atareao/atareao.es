# Tasks: Integración propia de la analítica Umami

> Nota: el repositorio no tiene framework de tests ni build tools. La implementación se ejecutó tras la aprobación del spec. La verificación combina un **arnés de stubs externo** (vive solo en `/tmp/opencode/analytics-harness/`, fuera del repo; 26 comprobaciones: los escenarios del spec + extras de seguridad), `just php-lint`, `just phpcs`, WP-CLI y E2E manual con `curl`. El arnés no forma parte del commit ni del árbol.

## 1. Caracterización previa (baseline)

- [x] 1.1 HTML actual de producción en `wp_footer` documentado en `design.md` (Context) y `proposal.md` (Why): `src=https://umami.atareao.es/script.js`, `data-website-id` anonimizado y `data-do-not-track`; opciones en uso `enabled=1`, `do_not_track=1`, resto en defaults, `track_comments=0`. Evidencia: baseline transcrito en los artefactos; el `curl` de contraste queda en 7.1.
- [x] 1.2 `data-umami-event` aparece 0 veces en un artículo real (tracking de comentarios desactivado). Evidencia: `proposal.md` §Why; el recuento por `curl` se reconfirma en 7.1/7.5.
- [x] 1.3 Listado de opciones legadas (`wp option get integrate_umami_options`) — **ya no ejecutable**: al desactivarse el plugin legado, su hook de desactivación borró `integrate_umami_options`. El baseline legado quedó confirmado por la **equivalencia del tag emitido en producción** (mismo `src`, mismo `data-website-id` y mismo `data-do-not-track`) y por los defaults del plugin. Nuestra copia propia (`atareao_umami_legacy_snapshot`) **no es verificable sin WP-CLI en producción**.
- [x] 1.4 Baseline PSR12 antes de tocar nada. Evidencia: `just php-lint` → 58 ficheros, 0 errores; `phpcs` global (theme+plugin) → 752 errores / 424 warnings en 67 ficheros.

## 2. Clase `Analytics`: opciones, saneado y emisión

- [x] 2.1 `includes/class-analytics.php` con `namespace Atareao;`, guarda `ABSPATH` y `init()` idempotente que solo engancha hooks. Evidencia: `just php-lint` sin errores; `phpcs --report=source` del fichero → 1 warning (`PSR1.Files.SideEffects`, inherente a la guarda) y **0** `PSR1.Methods.CamelCapsMethodName`.
- [x] 2.2 15 claves con prefijo `atareao_umami_` y saneado por tipo (`esc_url_raw`, `sanitize_text_field`, banderas 0/1). Evidencia: arnés escenarios 2 (defaults/incompleto) y 8 (saneado por tipo).
- [x] 2.3 Registro en `atareao-functionality.php`. Evidencia: `rg -n "class-analytics|Analytics::init" wp-content/plugins/atareao-functionality/atareao-functionality.php` → 2 ocurrencias (líneas 40 y 59); `just php-lint` sin errores.
- [x] 2.4 Emisión en `wp_footer` con equivalencia funcional y HTML válido (`esc_url`/`esc_attr`, valores entrecomillados). Evidencia: arnés escenario 1 (`PASS`: etiqueta única con `src`, `data-website-id` y `data-do-not-track="true"`).
- [x] 2.5 Exclusión dura independiente de la configuración. Evidencia: arnés escenario 5 (`is_admin`, `is_feed`, `is_preview`, `is_customize_preview`, `wp_is_json_request`, `is_robots`, `REST_REQUEST` → no emite).
- [x] 2.6 Exclusiones opcionales `skip_404`/`skip_search` con default `0`. Evidencia: arnés escenario 4 (default emite en 404/búsqueda; con la bandera no emite).
- [x] 2.7 Guarda anti-doble-inyección (`class_exists('\Ancozockt\Umami\Manager')`). Evidencia: arnés escenario 6 (con la clase legada cargada no emite y el panel avisa).
- [x] 2.8 `ignore_admins` (default `1`). Evidencia: arnés escenario 3 (admin no emite, visitante sí).
- [x] 2.9 Refactor de los métodos públicos a `camelCase` (`getSettings`, `sanitizeSettings`, `shouldEmit`, `buildTag`, `renderScript`, `legacyPluginLoaded`, `legacySettingsExist`, `importLegacy`, `shouldWarnAnalyticsOff`, `filterCommentSubmitButton`, `registerSettingsPage`, `renderSettingsPage`, `maybeSaveSettings`) para cumplir PSR1 y la convención del repo. Evidencia: `phpcs --report=source` del fichero → solo `PSR1.Files.SideEffects` (1), ninguna `CamelCapsMethodName`; arnés 26/26; `just php-lint` limpio.
- [x] 2.10 **SEC-BE-002**: copia propia de la configuración legada (`atareao_umami_legacy_snapshot`), `maybeSnapshotLegacySettings()` en `admin_init` (solo claves presentes, saneadas, sin reescritura si es idéntica), `importLegacy()` con fallback al snapshot, `legacyWillEmit()` y `shouldEmit()` usado por él, y `shouldWarnAnalyticsOff()` redefinido. Evidencia: arnés escenarios 20 (snapshot creado/saneado/idempotente/solo claves presentes), 21 (import desde snapshot con la opción legada borrada: 5/5), 22 (aviso con snapshot y sin opción viva), 23 (legado cargado e inactivo → emitimos).
- [x] 2.11 **SEC-BE-001**: `preg_replace_callback()` en `filterCommentSubmitButton()` (sustitución construida dentro del callback, sin datos del título en la cadena de reemplazo). Evidencia: arnés escenario 24 (títulos `$1`, `\1`, `$0`, `\0`, `x\1y`, `$0$1` literales).
- [x] 2.12 **SEC-BE-003**: `truncateTitle()` con `mb_substr(..., 'UTF-8')` (o `preg_match('/^.{0,50}/us')` sin mbstring; sin truncar si no se puede asegurar UTF-8). Evidencia: arnés escenario 25 (emoji×20, flechas×25, acentos×30, ASCII×60: UTF-8 válido y truncado correcto).
- [x] 2.13 **SEC-BE-004**: guardas `is_scalar()` en `toFlag()`, `getSettings()` y `sanitizeSettings()` (vía `sanitizeValue()`); los arrays se normalizan a `''`/`0`. Evidencia: arnés escenario 26 (0 avisos «Array to string conversion», valores normalizados).

## 3. Panel de Ajustes y migración

- [x] 3.1 Página `add_options_page(..., 'manage_options', 'atareao-analytics', …)`. Evidencia: arnés escenario 8 (verifica slug `atareao-analytics` y capability `manage_options`).
- [x] 3.2 Guardado por POST + `check_admin_referer` + `current_user_can('manage_options')` en el manejador `maybeSaveSettings` enganchado en `admin_init`, saneando cada campo. Evidencia: arnés escenario 8 (nonce inválido no persiste; nonce válido guarda saneado) y escenario 9 (sin permisos no guarda).
- [x] 3.3 Botón «Importar ajustes de Integrate Umami» (mapeo 1:1, sin borrar, informa del número). Evidencia: arnés escenario 10 (importa 10 ajustes, `integrate_umami_options` intacto) y escenario 11 (sin config legada no modifica nada e informa).
- [x] 3.4 Aviso de doble plugin en el panel. Evidencia: arnés escenario 6 (texto con «sigue activo» + «desactiv»).
- [x] 3.5 Red de seguridad contra el apagón silencioso, efectiva aunque el plugin legado ya haya borrado su opción. Evidencia: arnés escenario 12 (`shouldWarnAnalyticsOff` true solo con: no emitimos + evidencia legada + `legacyWillEmit` falso) y escenario 22 (aviso con snapshot y sin opción viva; desaparece al activar la analítica).

## 4. SRI opcional

- [x] 4.1 Campo `integrity` (default vacío); emite `integrity="…"` + `crossorigin="anonymous"` solo con valor. Evidencia: arnés escenarios 13 (SRI configurado), 14 (no configurado) y 15 (hash inválido no rompe el HTML).
- [x] 4.2 Documentar el recálculo del hash y la advertencia de invalidación. Evidencia: README §«SRI (Subresource Integrity) opcional» con el comando `curl … | openssl dgst -sha384 -binary | openssl base64 -A` y la nota de que cada upgrade de Umami invalida el hash.

## 5. Tracking de comentarios (opt-in)

- [x] 5.1 `track_comments` (default `0`): con `1` y `is_singular()` inyecta los 3 atributos sobre el elemento existente sin cambiar su tipo. Evidencia: arnés escenarios 16 (sigue siendo `<button>`, conserva «Publicar comentario», título truncado a 50 + `…`), 19 (caso `<input type="submit" />`: conserva `<input>` y `value`, 3 atributos justo tras el nombre de etiqueta, sin `/` suelto), 24 (títulos `$`/`\` literales) y 25 (multibyte emoji/3B/2B/ASCII).
- [x] 5.2 Con `track_comments=0` no se añade ningún `data-umami-event`. Evidencia: arnés escenario 17 (HTML idéntico al de entrada).

## 6. Documentación

- [x] 6.1 Sección «Analítica (Umami)» en el `README.md` del plugin + entrada en el índice de `## Características` + subsección «CSP». Evidencia: revisión del README (las 15 claves, orden de migración y motivo, SRI, CSP, WP-CLI, tabla de exclusiones y nota de no-destructividad).
- [x] 6.2 Nota de CSP: va en el `README.md` del plugin (versionado) y permanece en el runbook local `docs/produccion/cabeceras-seguridad-traefik.md`, que está en `.gitignore` y **no** se versiona (no se modifica la política de `.gitignore`). Evidencia: `git ls-files docs | wc -l` → `0` y el commit no contiene ningún fichero de `docs/`.
- [x] 6.3 **SEC-BE-002** (README): corrige la contradicción «el legado borra su config / el aviso la necesita» documentando la copia propia; el orden de migración deja de ser obligatorio (se puede importar tras desactivar el legado) y se documenta `wp option get/delete atareao_umami_legacy_snapshot`. Evidencia: README §«Migración desde Integrate Umami (con copia propia; el orden ya no es obligatorio)».
- [x] 6.4 Ampliación del delta `specs/analytics/spec.md` y de `design.md`: escenario «Plugin legado cargado pero inactivo» (Req. 1), «Importación después de desactivar el plugin legado» (Req. 2), «Título del evento con caracteres especiales o multibyte» (Req. 4); en `design.md`, copia propia con causa raíz, corrección de la guarda (`legacyWillEmit`) y del riesgo de apagón. Evidencia: `openspec validate umami-analytics --strict` → válido.

## 7. Verificación E2E

- [x] 7.1 Comparar el HTML emitido antes/después con la configuración de producción y comprobar una página del microsite. Método: `curl` con **cache-buster** `?cb=<epoch>` (la URL limpia se sirve de caché de nginx; con el `?cb` la respuesta es `x-cache-status: BYPASS`, render fresco). Resultados:

  | Comprobación | Resultado |
  |---|---|
  | Portada `/`, artículo, `/tools/uuid/`, búsqueda `?s=linux`, 404 | marcador propio presente (`propio=2`: apertura + cierre), **`legado=0`** |
  | Etiqueta inyectada | **exactamente 1** (`tags=1`), bien formada y cerrada |
  | Atributos emitidos | `<script async defer src="https://umami.atareao.es/script.js" data-website-id="8e108fb4-…-ec956cac22b0" data-do-not-track="true"></script>` |
  | Equivalencia con el legado | mismo `src`, **mismo `data-website-id`** que servía el plugin legado y mismo `data-do-not-track`; solo cambia el entrecomillado del valor (HTML válido) |
  | SRI | sin `integrity`/`crossorigin` (campo vacío, por diseño) |
  | `/feed/` y `/wp-json` | **sin** script (exclusión dura) |
  | Microsite `/tools/uuid/` | sí emite (comportamiento conservado) |
  | Botón de comentarios | `<button type="submit" name="submit" id="submit" class="submit button" tabindex="4">Publicar comentario` intacto; `data-umami-event` = 0 |
  | CSP de producción | `script-src` y `connect-src` incluyen `https://umami.atareao.es` |
  | Errores PHP en el HTML | 0 (sin Warning/Notice/Deprecated); HTTP 200 |

- [x] 7.2 Guarda anti-doble-inyección con el plugin legado activo en producción — **no reproducible en producción**: el plugin legado ya está desactivado, así que el escenario no existe hoy. Queda cubierta por el arnés: escenario 6 (legado cargado → no emitimos) y escenario 23 (legado cargado e inactivo → sí emitimos).
- [x] 7.3 Importación real y persistencia del ajuste legado por WP-CLI — la importación por WP-CLI **no es verificable desde este entorno** y la opción legada ya no existe. El **resultado** sí está verificado: los ajustes están completos y el tag emitido es equivalente al legado (7.1).
- [x] 7.4 Verificación estática. Evidencia: `just php-lint` → 0 errores; `phpcs --report=source` del fichero nuevo → 1 warning (`PSR1.Files.SideEffects`, inherente y presente en todas las clases) y 0 errores; global 752 errores / 425 warnings vs baseline 752 / 424 (**+0 errores, +1 warning**). Arnés final: `TOTAL=26 PASS=26 FAIL=0`, `exit=0`.
- [x] 7.5 Checklist de migración en producción y datos en Umami tras la retirada. **Pendiente solo de la confirmación del usuario**: la parte de migración ya está verificada por la emisión (7.1); falta comprobar el panel de Umami (Realtime) y la ausencia de avisos en `Ajustes → Analítica`. Se cierra después con esa evidencia. **Cierre (2026-10-03):** migración verificada en producción —el tag emitido es funcionalmente equivalente al del plugin retirado (mismo `data-website-id`, anonimizado como `8e108fb4-…-ec956cac22b0`, y `data-do-not-track="true"`, sin rastro del legado)—; **el panel de Umami registra visitas** (confirmado por el usuario en ventana privada, coherente con `ignore_admins=1`, que excluye las sesiones de administrador); y la pestaña **Umami** del hub (antes «Ajustes → Analítica») no muestra avisos, verificado en la E2E de wp-admin del change `settings-hub`.
- [x] 7.6 Limpieza del arnés: borrado `/data/php/atareao.es/.harness/` y eliminada la línea `.harness/` de `.git/info/exclude`; la copia canónica vive solo en `/tmp/opencode/analytics-harness/`. Evidencia: `ls .harness` → no existe; `grep -c harness .git/info/exclude` → `0`; `git status --short` sin `.harness/`.

> **Nota operativa (caché):** la URL sin query sirve HTML antiguo (`x-cache-status: HIT`) durante el TTL (12 h en portada / 1 h en el resto) con el tag legado. Es funcionalmente idéntico (mismo `src` y mismo `data-website-id`), por lo que **no hay pérdida de datos**; el HTML nuevo aparece al caducar la entrada o purgando la caché.

## 8. Entrega

- [x] 8.1 PR de `feature/umami-analytics` a `development` por gitflow — PR #58 mergeado (merge commit b469970).
