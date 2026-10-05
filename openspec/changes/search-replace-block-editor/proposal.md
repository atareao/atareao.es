# Proposal: Internalizar la búsqueda y reemplazo en el editor de bloques

## Why

El sitio depende hoy del plugin de terceros **Search and Replace for Block Editor** (`badasswp/aw-ng` v1.12.0) para buscar y reemplazar texto dentro del editor de bloques. Es una dependencia más que instalar, actualizar y auditar, cuando la funcionalidad es acotada y encaja de lleno en la responsabilidad de `atareao-functionality`: operar sobre los bloques del editor. Absorberlo permite desinstalar el plugin externo y eliminar una fuente de actualizaciones de terceros.

El original es una app del editor escrita en React y distribuida como bundle compilado. Este change la reescribe en **JS nativo sobre los globals de WordPress** (`wp.element`, `wp.components`, `wp.data`, …), sin build tools, siguiendo la convención del repo. La paridad se mantiene no solo en el comportamiento visible, sino también en los **nombres de los hooks públicos** que el original expone, para no romper las personalizaciones que ya existan en el sitio.

## What Changes

- **Nueva capability `search-replace-block-editor`**: acceso por botón de lupa y atajo `primary+f`, modal de búsqueda y reemplazo, detección de coincidencias, reemplazo global sobre los bloques de texto, patrón literal o regex, sensibilidad a mayúsculas configurable y respeto de los tipos de post excluidos.
- **Nueva clase PHP `\Atareao\SearchReplaceBlockEditor`** en `wp-content/plugins/atareao-functionality/includes/class-search-replace-block-editor.php`, con el patrón del repo (`namespace Atareao;`, guarda `ABSPATH`, `init()` idempotente) y registrada con `require_once` + `SearchReplaceBlockEditor::init()` en `atareao-functionality.php`.
- **Assets JS/CSS nuevos**: `assets/js/search-replace-block-editor.js` (app nativa, sin JSX ni transpilación) y `assets/css/search-replace-block-editor.css`.
- **Sexta pestaña `editor` («Editor») del hub «Atareao»**: la configuración del módulo vive en el hub, en el orden `matrix`, `pocketid`, `umami`, `mastodon`, `tema`, `editor`. No se registra una página ni un menú propios.
- **Opción `atareao_srfbe_options`** (array) con cinco claves booleanas: `case_matching`, `regex_matching`, `save_post`, `close_modal` y `use_shortcut`; defaults efectivos `use_shortcut` = `true` y el resto = `false`. Guardado por POST contra la propia pestaña con nonce `atareao_srfbe_settings`, capacidad `manage_options` y saneado booleano.
- **Comportamientos condicionados por opción**: `save_post` guarda el post tras reemplazar; `close_modal` cierra el modal y muestra el aviso de éxito; `use_shortcut` habilita o deshabilita el atajo.
- **Compatibilidad de hooks**: se conservan los nombres originales `search-replace-for-block-editor.afterSearchReplace`, `search-replace-for-block-editor.replaceBlockAttribute`, `search-replace-for-block-editor.allowedBlocks`, `search-replace-for-block-editor.excludedPostTypes`, `search-replace-for-block-editor.regexPattern`, `search-replace-for-block-editor.handleAttributeReplacement`, `search-replace-for-block-editor.keyboardShortcut`, `search-replace-for-block-editor.caseSensitive` y el filtro de bloques `blocks.registerBlockType`.
- **Carga acotada**: los assets se encolan solo en `enqueue_block_editor_assets`; no se inyecta nada en el frontend ni en el editor clásico, y si el tipo de post está excluido por el filtro correspondiente la app no se inyecta.
- **Bump de versión menor** de `ATAREAO_PLUGIN_VERSION` en `atareao-functionality.php`. Si este change y `post-type-switcher` se fusionan juntos, la versión final la fija el primero en mergear.

## Capabilities

### New Capabilities

- `search-replace-block-editor`: búsqueda y reemplazo de texto en el editor de bloques, con botón y atajo configurables, modal de búsqueda y reemplazo, bloques de categoría `text` (con fallback), recursión en `innerBlocks`, mapeo de atributos por tipo de bloque, patrón literal o expresión regular, sensibilidad a mayúsculas, opciones propias y hooks de compatibilidad con el plugin original.

### Modified Capabilities

- `admin-settings`: el hub pasa de cinco a seis pestañas con `editor` → «Editor» al final del orden, añade `SearchReplaceBlockEditor::renderSettingsPage()` a la delegación y reconoce el guardado por POST con nonce `atareao_srfbe_settings` y saneado booleano de la pestaña `editor`, conservando el resto del comportamiento palabra por palabra.

## Impact

- **Archivos**:
  - Nuevo: `wp-content/plugins/atareao-functionality/includes/class-search-replace-block-editor.php` (clase `\Atareao\SearchReplaceBlockEditor`).
  - Nuevo: `wp-content/plugins/atareao-functionality/assets/js/search-replace-block-editor.js`.
  - Nuevo: `wp-content/plugins/atareao-functionality/assets/css/search-replace-block-editor.css`.
  - Modificado: `wp-content/plugins/atareao-functionality/atareao-functionality.php` (`require_once` + `SearchReplaceBlockEditor::init()` + bump de `ATAREAO_PLUGIN_VERSION`).
  - Modificado: `wp-content/plugins/atareao-functionality/includes/class-settings.php` (sexta pestaña `editor`).
  - Modificado (spec): `openspec/specs/admin-settings/spec.md` vía el delta `specs/admin-settings/spec.md` (3 requirements MODIFIED).
  - Nuevo (spec): `openspec/specs/search-replace-block-editor/spec.md` vía el delta `specs/search-replace-block-editor/spec.md`.
- **No cambia**: el frontend del sitio, el editor clásico, el resto de pestañas del hub, la analítica, el login/logout, las notificaciones Matrix ni el microsite `/tools/`.
- **Dependencias**: ninguna nueva; solo los globals de WordPress en el JS y las APIs de core en PHP. No se añaden build tools ni npm/webpack.
- **Compatibilidad**: PHP 8.3, PSR12, WordPress 6.0+ (para el JS, `wp.keyboardShortcuts` disponible desde WP 6.4; se documenta como requisito efectivo).
- **Retirada del plugin externo**: una vez verificado el change, el plugin `badasswp/aw-ng` puede desinstalarse sin pérdida funcional.
