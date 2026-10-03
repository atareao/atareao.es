# Design: Integración propia de la analítica Umami

## Context

`atareao.es` sirve la analítica con el plugin de terceros **Integrate Umami** (v0.8.3, `Ancocodet`). Su configuración vive en un único array serializado `integrate_umami_options` y su salida es una etiqueta `<script>` inyectada en `wp_footer`:

```html
<!-- Integrate Umami -->
<script async defer
        src="https://umami.atareao.es/script.js"
        data-website-id="8e108fb4-…-ec956cac22b0"
        data-do-not-track=true >
</script>
<!-- /Integrate Umami -->
```

Opciones del plugin legado y valores reales en producción:

| clave | default | efecto | producción |
|---|---|---|---|
| `enabled` | 0 | interruptor | `1` |
| `script_url` | '' | `src` del script | `https://umami.atareao.es/script.js` |
| `website_id` | '' | `data-website-id` | `8e108fb4-…-ec956cac22b0` |
| `host_url` + `use_host_url` | 0 | `data-host-url` | sin uso |
| `ignore_admins` | 1 | no emite si `current_user_can('manage_options')` | `1` |
| `auto_track` | 1 | `data-auto-track=false` si 0 | `1` |
| `do_not_track` | 1 | `data-do-not-track=true` | `1` |
| `cache` | 0 | `data-cache=true` | `0` |
| `track_comments` | 0 | añade `data-umami-event` al botón del formulario de comentarios | `0` |

El plugin `atareao-functionality` es el lugar natural: agrupa 15 clases con el patrón `namespace Atareao;` + `if (!defined('ABSPATH')) { exit; }` + `public static function init()`, registradas en `atareao-functionality.php` con `require_once` (líneas 25-40) y `\Atareao\X::init()` dentro de `atareao_functionality_init()` (líneas 44-60). El panel de ajustes de referencia es `includes/class-matrix-config.php` (`add_options_page(..., 'manage_options', 'atareao-matrix-config', …)` + POST con `check_admin_referer` + `update_option`).

El tracker (`https://umami.atareao.es/script.js`) tiene evidencia medida: 4.655 bytes; responde `access-control-allow-origin: *` (⇒ SRI viable con `crossorigin="anonymous"`); `cache-control: public, max-age=86400, must-revalidate`; soporta atributos `data-umami-event-*` (eventos), `do-not-track`, `exclude-hash` y `exclude-search`.

Restricciones del repo: **no existe framework de tests ni build tools**; la verificación es `just php-lint`, `just phpcs`, WP-CLI y E2E manual con `curl` al HTML servido. PSR12, PHP 8.3. Regla de separación: la funcionalidad va en el plugin, el tema es solo presentación. La CSP vigente (`docs/produccion/cabeceras-seguridad-traefik.md`) ya permite `https://umami.atareao.es` en `script-src` y `connect-src`.

## Goals / Non-Goals

**Goals**

- Retirar el plugin de terceros sin alterar el dato que recibe Umami: los mismos atributos y valores en producción.
- Emitir HTML válido y escapado (`esc_url`, `esc_attr`), valores entre comillas.
- Exponer una página de ajustes propia bajo Ajustes con guardado verificado por nonce.
- Poder leer y escribir cada ajuste por WP-CLI (una clave por ajuste).
- Permitir migrar la configuración legada sin perder el `website_id`.
- Evitar el doble conteo durante la convivencia de ambos plugins.
- Corregir el tracking de comentarios sin romper el elemento del formulario.
- SRI opcional y documentado.
- NO borrar la configuración al desactivar el plugin.

**Non-Goals**

- No tocar el tema (`wp-content/themes/atareao-theme/`), ni sus plantillas de comentarios.
- No tocar la CSP ni los templates del microsite `/tools/`.
- No introducir dependencias, build tools ni framework de tests.
- No modificar el comportamiento del microsite: sigue emitiendo el script porque sus templates llaman a `get_footer()`.
- No eliminar automáticamente el plugin legado ni sus ajustes.

## Decisions

### Decisión 1: Capability nueva `analytics`, change `umami-analytics`

La emisión y la configuración de la analítica constituyen una responsabilidad con requisitos propios (qué se emite, cuándo, con qué exclusiones y cómo se configura). Se modela como capability nueva `analytics` dentro del change `umami-analytics`. No se modifica ninguna capability existente: `theme-share`, `cache-purge`, `pocketid-login` y `release-pipeline` no cambian su comportamiento.

**Consecuencias:** al archivar, se crea `openspec/specs/analytics/spec.md`. El change incluye un delta `specs/analytics/spec.md` con `## Purpose` (propio de una capability nueva) y `## ADDED Requirements`.

**Alternativa descartada:** enmarcarlo como modificación de una capability existente o como change sin specs (`skip_specs: true`). La funcionalidad tiene comportamiento observable y contratos claros; no es un refactor puro.

### Decisión 2: Clase `\Atareao\Analytics` dentro de `atareao-functionality`

Se crea `wp-content/plugins/atareao-functionality/includes/class-analytics.php` con `namespace Atareao;`, la guarda `if (!defined('ABSPATH')) { exit; }` y `public static function init()`, siguiendo el patrón de las 15 clases existentes. Se registra en `atareao-functionality.php` con `require_once ATAREAO_PLUGIN_DIR . 'includes/class-analytics.php';` y `\Atareao\Analytics::init();` dentro de `atareao_functionality_init()`.

**Consecuencias:** la lógica de analítica queda versionada con el plugin, sin dependencias de terceros; el tema permanece como presentación. `init()` debe ser idempotente y sólo enganchar hooks (no consultar opciones ni emitir en el momento de la carga).

**Convención de nombres:** los métodos públicos siguen `camelCase` (`getSettings`, `sanitizeSettings`, `shouldEmit`, `buildTag`, `renderScript`, `legacyPluginLoaded`, `legacySettingsExist`, `importLegacy`, `shouldWarnAnalyticsOff`, `filterCommentSubmitButton`, `registerSettingsPage`, `renderSettingsPage` y `maybeSaveSettings`, el manejador del POST enganchado en `admin_init`). Es la convención del resto del plugin (`addConfigPage`, `notifyOnComment`, `registerSettings`, `renderOptionsPage`) y satisface `PSR1.Methods.CamelCapsMethodName`. Las constantes (`OPTION_PREFIX`, `LEGACY_OPTION`) y las claves de opción `atareao_umami_*` no cambian: son contrato del spec.

**Alternativa descartada:** implementarlo desde el `functions.php` del tema (plan antiguo `docs/plans/2026-07-31-plan-seo-seguridad-atareao.md`). Viola la regla de separación del repo y ata la funcionalidad a la presentación.

### Decisión 3: Página propia bajo Ajustes

Se registra una página propia con `add_options_page(__('Analítica', …), __('Analítica', …), 'manage_options', 'atareao-analytics', [Analytics::class, 'renderSettingsPage'])` y el guardado se verifica con POST + `check_admin_referer` + `current_user_can('manage_options')`, replicando el patrón de `class-matrix-config.php`.

**Consecuencias:** aparece como «Ajustes → Analítica», junto a «Matrix API» y «PocketID Login». No se mezcla con las opciones de presentación.

**Alternativa descartada:** añadir una sección dentro de «Atareao Theme Options» (`class-theme-options.php`, bajo Apariencia). Mezclaría analítica con opciones de presentación (la página vive bajo Apariencia y se llama «Theme Options»), compartiría el mismo `settings_fields`/grupo de opciones de otro módulo y haría menos visible una configuración con efectos en el HTML público.

### Decisión 4: Una clave de opción por ajuste

Todas las opciones se guardan como claves independientes con prefijo `atareao_umami_`:

`atareao_umami_enabled`, `atareao_umami_script_url`, `atareao_umami_website_id`, `atareao_umami_host_url`, `atareao_umami_use_host_url`, `atareao_umami_integrity`, `atareao_umami_ignore_admins`, `atareao_umami_auto_track`, `atareao_umami_do_not_track`, `atareao_umami_cache`, `atareao_umami_track_comments`, `atareao_umami_exclude_search`, `atareao_umami_exclude_hash`, `atareao_umami_skip_404`, `atareao_umami_skip_search`.

Todas con default `0` (banderas) o `''` (texto). El saneado se centraliza en un método que normaliza cada tipo: URL con `esc_url_raw`, identificadores/rutas con `sanitize_text_field`, banderas a `0`/`1`.

**Consecuencias:** lectura/escritura directa por WP-CLI (`wp option get atareao_umami_website_id`, `wp option update atareao_umami_enabled 1`), sin desempaquetar arrays serializados. Facilita la migración (mapeo 1:1) y el diagnóstico.

**Alternativa descartada:** replicar el array único `integrate_umami_options`. Peor para CLIs (serializado), para el saneado por campo y para la migración; además hereda el riesgo de que un volcado parcial pise el resto.

### Decisión 5: Equivalencia funcional en `wp_footer` con HTML válido

La emisión se engancha en `wp_footer`. Con la configuración de producción debe producir los mismos atributos y valores que el plugin legado (mismo `src`, mismo `data-website-id`, `data-do-not-track="true"`), para que el dato en Umami no cambie. A diferencia del plugin legado, el HTML debe ser válido: `src` escapado con `esc_url`, resto de atributos con `esc_attr`, valores siempre entre comillas.

No se emite nada si `enabled=0`, `script_url` vacío o `website_id` vacío. `auto_track=1` (default) no añade `data-auto-track`; `auto_track=0` añade `data-auto-track="false"`. `do_not_track=1` añade `data-do-not-track="true"`. `cache=1` añade `data-cache="true"`. `use_host_url=1` con `host_url` no vacío añade `data-host-url`. `exclude_search`/`exclude_hash` añaden `data-exclude-search`/`data-exclude-hash` cuando corresponden.

**Consecuencias:** mismo comportamiento de datos, markup correcto. Se puede retirar el plugin legado sin recalibrar Umami.

**Verificabilidad sin framework de tests:** el HTML se construye en un método puro `buildTag()` (no imprime) y todas las condiciones de emisión se concentran en `shouldEmit()`; `renderScript()` se limita a encadenar `shouldEmit()` + `buildTag()`. Esta separación es deliberada: permite verificar el markup y las reglas sin un WordPress real. La verificación de este change se hizo con un **arnés de stubs ubicado fuera del repositorio** (el repo no tiene framework de tests y no se le añade uno): define de forma controlable las funciones de WordPress usadas (opciones, hooks, condicionales, escapado), ejecuta `Analytics::init()` + `do_action('wp_footer')` capturando la salida y comprueba los escenarios del spec (18) más una comprobación extra del caso `<input type="submit" />`. El arnés no forma parte del commit.

**Alternativa descartada:** copiar literalmente el HTML legado (`data-do-not-track=true` sin comillas). No es HTML válido y complica el escapado.

### Decisión 6: Exclusión dura (nunca se inyecta)

Antes de emitir se comprueba y se aborta si: `is_admin()`, `is_feed()`, `is_preview()`, `is_customize_preview()`, petición REST (`wp_is_json_request()` o la constante `REST_REQUEST`) o `is_robots()`. Son contextos en los que el plugin legado tampoco debía emitir y en los que la analítica no aporta (o incluso contamina).

**Consecuencias:** feed, editor, previsualización, personalizador, API REST y `robots.txt` quedan siempre limpios, con independencia de la configuración.

**Alternativa descartada:** confiar solo en la configuración. Un ajuste mal puesto no debe poder romper feed ni REST.

### Decisión 7: Exclusiones opcionales con default `0`

Se añaden `atareao_umami_skip_404` y `atareao_umami_skip_search`, ambas con default `0` para no alterar el comportamiento actual (hoy se emite en 404 y en búsqueda). Con `skip_404=1` no se emite en `is_404()`; con `skip_search=1` no se emite en `is_search()`.

**Consecuencias:** el comportamiento actual se conserva por defecto; quien quiera filtrar páginas sin valor puede activarlo.

**Alternativa descartada:** activarlas por defecto. Cambiaría el dato que recibe Umami respecto a hoy.

### Decisión 8: Guarda anti-doble-inyección

En el momento de emitir (tarde y fiable) se comprueba si el plugin legado está cargado **y con intención de emitir**: `legacyPluginLoaded()` **y** su configuración `integrate_umami_options` tiene `enabled`, `script_url` y `website_id` no vacíos (`legacyWillEmit()`). Solo entonces no se emite el script propio. Si el legado está cargado pero inactivo o incompleto, no emite nada por su cuenta y el script propio **sí** se emite (antes se suprimía y el sitio se quedaba sin analítica sin aviso). En el panel se muestra un aviso indicando que hay que desactivar «Integrate Umami» al terminar la migración.

**Consecuencias:** durante la ventana en la que ambos plugins conviven no hay doble conteo; y si el legado está cargado pero apagado, la analítica no se interrumpe. La comprobación es barata y no depende del nombre del plugin ni de su estado en la lista de activos.

**Alternativa descartada:** comprobar `is_plugin_active('integrate-umami/integrate-umami.php')` antes de emitir. Depende de rutas y de que `plugin.php` esté cargado; `class_exists` es la señal directa de que el otro plugin se ha cargado, y su configuración activa es la señal de que va a emitir.

### Decisión 9: Migración desde `integrate_umami_options` sin borrar el ajuste legado, con copia propia (snapshot)

El panel incluye un botón «Importar ajustes de Integrate Umami» (POST + nonce + `manage_options`) que lee la configuración legada **viva** `get_option('integrate_umami_options')` o, si esta ya no existe, la **copia propia** `get_option('atareao_umami_legacy_snapshot')`. Vuelca sus valores en las claves nuevas mediante el mapeo `enabled→_enabled`, `script_url→_script_url`, `website_id→_website_id`, `host_url→_host_url`, `use_host_url→_use_host_url`, `ignore_admins→_ignore_admins`, `auto_track→_auto_track`, `do_not_track→_do_not_track`, `cache→_cache`, `track_comments→_track_comments`. La operación **no borra** `integrate_umami_options`; informa de cuántos ajustes ha importado o de que no encontró la configuración legada.

**Causa raíz (SEC-BE-002):** el plugin legado **borra** `integrate_umami_options` al desactivarse (`Options::delete_options()` en su `deactivate()`). Si el usuario desactiva «Integrate Umami» sin importar antes, la opción desaparece y cualquier aviso que dependiera de ella jamás se mostraría: la analítica se apaga en silencio e indefinidamente. Por eso el sistema mantiene una **copia propia**: `maybeSnapshotLegacySettings()` corre en `admin_init` y, si existe configuración legada, guarda en `atareao_umami_legacy_snapshot` **solo las claves presentes del mapeo** (las 10), saneadas por tipo con `sanitizeValue()`. No escribe si no hay nada nuevo (snapshot idéntico) o si la configuración legada ya no existe, así que no genera escrituras en cada petición de admin. La opción del snapshot **no** forma parte de `defaults()` ni del formulario; es estado interno del plugin.

De este modo la importación funciona **después** de haber desactivado y borrado el plugin legado, porque la copia es nuestra. Orden recomendado (ya no obligatorio):

1. Instalar/actualizar `atareao-functionality` (con `class-analytics.php`).
2. Entrar en Ajustes → Analítica e importar desde «Integrate Umami».
3. Verificar el HTML emitido (mismo `src`, `data-website-id` y `data-do-not-track`).
4. Desactivar y borrar el plugin «Integrate Umami».

**Consecuencias:** aunque el paso 4 se haga antes del 2, ya no se pierde el `website_id`: la copia propia lo conserva y la importación lo recupera. El ajuste legado permanece como respaldo hasta que el usuario borre el plugin.

Para cubrir el apagón silencioso (el usuario desactiva «Integrate Umami» sin haber importado ni activado la analítica propia y Umami deja de recibir datos sin ningún error visible), el panel incluye una red de seguridad **efectiva**: `shouldWarnAnalyticsOff()` es verdadero cuando (a) no vamos a emitir para un visitante normal (`enabled` desactivado, `script_url` o `website_id` vacíos), (b) hay evidencia de configuración legada (snapshot propio **o** ajuste vivo) y (c) `legacyWillEmit()` es falso. El aviso dice que hay una copia guardada y que se puede importar o activar los ajustes.

**Alternativa descartada:** borrar `integrate_umami_options` tras importar. Eliminaría el respaldo y haría irreversible un error de importación. **Alternativa descartada 2:** depender solo del ajuste legado vivo para el aviso, que es justo el fallo que el legado provoca al desactivarse.

### Decisión 10: SRI opcional

El campo `atareao_umami_integrity` está vacío por defecto (sin SRI, como hoy). Si tiene valor, se emite `integrity="<hash>"` junto a `crossorigin="anonymous"`; si está vacío, no se emite ninguno de los dos. El formato esperado es `sha384-<base64>`.

Hash actual del tracker: `sha384-KovSIPpdrAZNHs+M91d7FOrLat5rqcpTtQUq/GLIzYwAt+eN0EQHlgdUgm/0U2j+`.

Procedimiento de recálculo:

```bash
curl -s https://umami.atareao.es/script.js | openssl dgst -sha384 -binary | openssl base64 -A
```

**Consecuencias:** el SRI es viable porque el script responde `access-control-allow-origin: *`; exige que siga sirviéndose con CORS. Cada actualización de Umami invalida el hash: si no se actualiza, la analítica deja de cargar en silencio. Por eso es opcional y no el default: activarlo es una decisión consciente con mantenimiento.

**Alternativa descartada:** activar SRI por defecto con el hash conocido. Acoplaría la analítica a una versión del script y el primer despliegue de Umami la rompería silenciosamente.

### Decisión 11: Tracking de comentarios opt-in y sin romper el elemento

`atareao_umami_track_comments` tiene default `0` (producción no lo usa). Con el valor `1`, y solo en `is_singular()`, se inyectan sobre el elemento ya existente del formulario de comentarios los atributos: `data-umami-event="comment"`, `data-umami-event-post-id="<id>"` y `data-umami-event-post-title="<título truncado a 50 caracteres + …>"`. Se añaden los atributos al elemento, **sin cambiar su tipo ni sustituirlo**: funciona igual si el tema usa `<button>` o `<input type="submit">`, y no se pierde su contenido.

**Consecuencias:** se corrige el defecto del plugin legado (que convertía `<button>` en `<input>` y dejaba un `</button>` huérfano). La opción no cambia el comportamiento por defecto.

**Alternativa descartada:** portar el `str_replace('<button', '<input ', …)` del plugin legado. Es la causa directa del HTML inválido y del texto perdido.

### Decisión 12: Desactivar el plugin no borra la configuración

`Analytics` no registra un `register_deactivation_hook` destructivo. Las claves `atareao_umami_*` permanecen tras desactivar (o reactivar) el plugin. Se documenta explícitamente en el README.

**Consecuencias:** una desactivación accidental (o para depurar) no pierde el `website_id` ni el resto de ajustes; contrasta con `Options::delete_options()` del plugin legado.

**Alternativa descartada:** borrar opciones al desactivar para «dejar limpio». Es precisamente el defecto que motiva parte del cambio.

### Decisión 13: No se toca el tema, la CSP ni el microsite

El trazado del hook vive en el plugin. No se modifica `wp-content/themes/atareao-theme/` ni `docs/produccion/cabeceras-seguridad-traefik.md` (salvo una nota documental). Los 11 templates de `/tools/` siguen llamando a `get_footer()`, así que el script se sigue emitiendo ahí y el comportamiento se conserva.

**Consecuencias:** cambio contenido, sin regresiones en la presentación. La CSP ya permite `https://umami.atareao.es` en `script-src` y `connect-src`.

**Nota de documentación:** `docs/` está en `.gitignore` (línea 6) y no se versiona. Por eso la nota de CSP se incorpora al `README.md` del plugin (versionado) y el runbook local `docs/produccion/cabeceras-seguridad-traefik.md` permanece **fuera** del control de versiones (solo local). No se modifica la política de `.gitignore` ni se fuerza la inclusión de `docs/` en el commit.

**Alternativa descartada:** condicionar la emisión a `!is_page_template()` para excluir el microsite. Alteraría el comportamiento actual sin necesidad.

## Risks / Trade-offs

- **[Doble conteo durante la ventana de migración]** → Mientras convivan ambos plugins se contarían dos veces las visitas. Mitigado con la guarda anti-doble-inyección (Decisión 8): si `\Ancozockt\Umami\Manager` existe, no se emite el script propio. La ventana se cierra desactivando el plugin legado tras importar.
- **[Pérdida del `website_id` si se desactiva el plugin legado antes de importar]** → El plugin legado borra sus ajustes al desactivarse. Mitigado con el orden de migración (Decisión 9) y con que la importación no borra el ajuste legado: una vez importado, el dato está en las claves nuevas.
- **[Apagón silencioso de la analítica tras desactivar el plugin legado sin importar ni activar la analítica propia]** → El script desaparece del HTML y Umami deja de recibir datos sin ningún error visible. Mitigado de verdad con la **copia propia** (Decisión 9): `shouldWarnAnalyticsOff()` se dispara aunque el plugin legado ya haya borrado su opción, porque la evidencia procede del snapshot `atareao_umami_legacy_snapshot`. Además, si el legado está cargado pero inactivo, `legacyWillEmit()` es falso y seguimos emitiendo, así que tampoco hay corte.
- **[Backreferences de PCRE en el título del evento]** (SEC-BE-001) → El título (dato del usuario) se interpolaba en la cadena de reemplazo de `preg_replace`; `$1`/`\1` se expandían y corrompían el atributo. Mitigado con `preg_replace_callback`, que construye la sustitución dentro del callback (nada de datos no confiables en la cadena de reemplazo).
- **[Truncado por bytes que parte multibyte]** (SEC-BE-003) → Un título cuya longitud en bytes supera 50 pero en caracteres no, se partía por bytes. Mitigado con `truncateTitle()`: `mb_substr(..., 'UTF-8')` si hay mbstring, `preg_match('/^.{0,50}/us')` si no, y sin truncar si no se puede asegurar UTF-8 válido.
- **[Conversión array→string]** (SEC-BE-004) → `toFlag()`, `getSettings()` y `sanitizeSettings()` casteaban a string sin comprobar el tipo. Mitigado con guardas `is_scalar()`: los valores no escalares se normalizan a `''` o `0` sin avisos.
- **[SRI desincronizado]** → Cada actualización de Umami cambia el hash del script y, si no se actualiza el ajuste, la analítica deja de cargar sin errores visibles. Mitigado por ser opt-in y no el default, y por el procedimiento de recálculo documentado (Decisión 10).
- **[Un `script_url` de otro host requiere CSP nueva]** → La CSP actual solo permite `https://umami.atareao.es`. Si algún día se sirve el script desde otro host, habrá que actualizar `script-src`/`connect-src` en `docs/produccion/cabeceras-seguridad-traefik.md`. Documentado; no se toca ahora.
- **[Configuración incompleta]** → `enabled=1` con `script_url` o `website_id` vacíos no emite nada (Decisión 5). Preferible a emitir un script roto.
- **[Tracking de comentarios y temas que usan `<input>`]** → La inyección es agnóstica al tipo de elemento (Decisión 11), pero requiere localizar el elemento concreto del formulario; si un tema no usara ninguno de los dos patrones, no habría a qué añadir atributos. Fuera de alcance el soportar formularios arbitrarios.
- **[Verificación sin framework de tests]** → No hay tests automatizados en el repo. La verificación es estática (`just php-lint`, `just phpcs`) y E2E manual (`curl` al HTML servido, WP-CLI). Riesgo de regresión asumido y acotado por la simplicidad del cambio.

## Migration Plan

1. Añadir `includes/class-analytics.php` y registrarla en `atareao-functionality.php`.
2. Verificar el HTML emitido con la configuración de producción (mismos atributos que el plugin legado).
3. Importar los ajustes desde Ajustes → Analítica (lee `integrate_umami_options`, no lo borra).
4. Verificar de nuevo el HTML emitido.
5. Desactivar y borrar el plugin «Integrate Umami».
6. Verificar que no se duplica el script y que Umami sigue recibiendo datos.

**Rollback:** reactivar «Integrate Umami» (la guarda de la Decisión 8 hace que el plugin propio no emita mientras el legado esté activo) o revertir el commit que registra `Analytics`. No hay estado ni datos implicados: las claves `atareao_umami_*` pueden quedarse o borrarse por WP-CLI.

## Verification

- `just php-lint` sin errores y `just phpcs` sin warnings nuevos respecto al baseline. Baseline medido (2026-10-03): **752 errores / 424 warnings** en `theme` + `plugin`; la cifra «24» era el recuento de `class-pocketid-login.php`, no el total. Objetivo de delta fijado: **+0 errores y +1 warning** (`PSR1.Files.SideEffects`, inherente a la guarda `ABSPATH` y presente en cada clase, p. ej. `class-matrix-config.php`).
- Con la configuración de producción: `curl -s https://atareao.es/ | grep -A6 'Umami'` muestra el mismo `src`, `data-website-id` y `data-do-not-track="true"` que antes del cambio.
- Con el plugin legado activo y `atareao_umami_enabled=1`: el HTML servido no contiene el script duplicado.
- Importación: con `integrate_umami_options` presente, el panel informa de los ajustes importados y `wp option get atareao_umami_website_id` coincide; `wp option get integrate_umami_options` sigue existiendo.
- Sin `integrate_umami_options`, la importación informa de que no encontró nada.
- WP-CLI: `wp option update atareao_umami_enabled 0` → desaparece el script; `wp option update atareao_umami_enabled 1` → reaparece.
- SRI: con `atareao_umami_integrity` vacío no aparecen `integrity`/`crossorigin`; con el hash aparece `integrity="sha384-…" crossorigin="anonymous"`.
- `openspec validate umami-analytics --strict` sin hallazgos.
- E2E diferida: comprobar en Umami que las visitas siguen registrándose tras retirar el plugin legado.

## Open Questions

Ninguna. El alcance, las claves de opción, las exclusiones, el SRI y el orden de migración quedan resueltos arriba.
