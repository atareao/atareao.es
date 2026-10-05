# Tasks

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación del change combina análisis estático (`just php-lint`, `just phpcs`), comprobación de sintaxis del JS (`node --check`) y E2E manual en el editor de bloques. La implementación arranca **solo tras la aprobación del usuario**.

## 1. Phase 0 — Caracterización y baseline

- [ ] 1.1 Documentar en `design.md` §Context el contrato funcional del plugin original: accesos (botón lupa + atajo `primary+f`), modal y campos, bloques permitidos y fallback, recursión en `innerBlocks`, mapeo de atributos por bloque, comportamiento del patrón (literal/regex/case), las cinco claves de opción, la lista de hooks públicos y el recorte a `enqueue_block_editor_assets`. **Verificación:** `design.md` contiene los siete puntos del contrato; `openspec validate search-replace-block-editor --strict` válido. **Evidencia:** pendiente.
- [ ] 1.2 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el baseline y se anota el par errores/warnings. **Evidencia:** pendiente.
- [ ] 1.3 Congelar como contrato de paridad la lista de hooks, conmutadores y atributos por bloque en el delta `specs/search-replace-block-editor/spec.md`. **Verificación:** el delta enumera los 8 hooks + `blocks.registerBlockType`, las claves `case_matching`, `regex_matching`, `save_post`, `close_modal`, `use_shortcut` y el mapeo `quote`/`pullquote`/`details`/`table`/default. **Evidencia:** pendiente.

## 2. Módulo PHP `SearchReplaceBlockEditor`

- [ ] 2.1 Crear `includes/class-search-replace-block-editor.php` con `namespace Atareao;`, guarda `if (!defined('ABSPATH')) { exit; }` e `init()` idempotente que solo engancha hooks. **Verificación:** `just php-lint` sin errores; `phpcs --report=source` del fichero solo con el warning esperado `PSR1.Files.SideEffects`. **Evidencia:** pendiente.
- [ ] 2.2 Registrar en `atareao-functionality.php` con `require_once` + `\Atareao\SearchReplaceBlockEditor::init();`. **Verificación:** `rg -n "class-search-replace-block-editor|SearchReplaceBlockEditor::init" atareao-functionality.php` → 2 coincidencias; `just php-lint` sin errores. **Evidencia:** pendiente.
- [ ] 2.3 Enganchar `enqueue_block_editor_assets`: registrar y encolar `assets/js/search-replace-block-editor.js` (con sus dependencias de paquetes WP) y `assets/css/search-replace-block-editor.css`. **Verificación:** el asset se encola solo en el editor de bloques; `rg -n "enqueue_block_editor_assets"` lo confirma; `just php-lint` sin errores. **Evidencia:** pendiente.
- [ ] 2.4 Localizar al JS la opción efectiva de `atareao_srfbe_options` (con sus defaults) en un objeto de configuración. **Verificación:** el objeto localizado incluye las cinco claves booleanas con `use_shortcut` en verdadero y el resto en falso cuando la opción no existe. **Evidencia:** pendiente.
- [ ] 2.5 Añadir `renderSettingsPage()` público para el hub (sin imprimir `.wrap` ni `<h1>`). **Verificación:** el render devuelve el formulario de la pestaña sin envoltorio propio; `just php-lint` sin errores. **Evidencia:** pendiente.
- [ ] 2.6 Bump de versión menor de `ATAREAO_PLUGIN_VERSION` en `atareao-functionality.php`. **Verificación:** la constante queda incrementada en el componente menor. **Evidencia:** pendiente.

## 3. App JS de búsqueda y reemplazo

- [ ] 3.1 Construir la UI con `wp.element.createElement`: botón con icono de lupa en la barra de herramientas y `Modal` de `wp.components` con campos «Search» y «Replace», conmutadores «Match case» y «Use regular expression» y botones «Replace» y «Done». **Verificación:** E2E manual: el botón abre el modal con todos los controles. **Evidencia:** pendiente.
- [ ] 3.2 Registrar el atajo `primary+f` en la categoría global con `wp.keyboardShortcuts`, condicionado a la opción `use_shortcut`. **Verificación:** E2E manual con la opción activa y desactivada; con la opción en falso el atajo no dispara nada. **Evidencia:** pendiente.
- [ ] 3.3 Auto-foco al campo «Search» al abrir y precarga con la selección del editor (iframe `iframe[name="editor-canvas"]`) cuando exista. **Verificación:** E2E manual: con texto seleccionado, el campo aparece precargado; sin selección, se abre vacío sin errores. **Evidencia:** pendiente.
- [ ] 3.4 Implementar la detección de bloques elegibles (`category === 'text'`), el fallback de la lista de bloques y la recursión en `innerBlocks`. **Verificación:** E2E manual con párrafos, listas y bloques anidados; se recorre la jerarquía y los bloques no elegibles quedan intactos. **Evidencia:** pendiente.
- [ ] 3.5 Implementar el mapeo de atributos por bloque (`quote`→`citation`; `pullquote`→`value`+`citation`; `details`→`summary`; `table`→`head`/`body`/`foot`/`caption` con JSON; default→`content`) y la escritura con `wp.data.dispatch('core/block-editor').updateBlockAttributes(clientId, {...})`. **Verificación:** E2E manual en bloques de cada tipo; los cambios se reflejan y `core/table` reserializa su JSON. **Evidencia:** pendiente.
- [ ] 3.6 Implementar el motor de patrón: literal escapado por defecto, regex opt-in, sensibilidad a mayúsculas, aplicación solo sobre texto dentro del HTML sin cruzar etiquetas y degradación a literal ante regex inválida. **Verificación:** E2E manual con búsqueda literal con metacaracteres, con regex válida, con «Match case» en ambos estados y con regex inválida (no falla). **Evidencia:** pendiente.
- [ ] 3.7 Implementar el conteo «N item(s) found» al cambiar la búsqueda o los toggles y el reemplazo global con el aviso «N item(s) replaced successfully». **Verificación:** E2E manual: el conteo cambia al escribir/alternar y el reemplazo sustituye todas las coincidencias. **Evidencia:** pendiente.
- [ ] 3.8 Implementar `save_post` (guardar el post tras reemplazar) y `close_modal` (cerrar el modal y mostrar el aviso de éxito) condicionados por sus opciones. **Verificación:** E2E manual con cada opción activa y con ambas en falso. **Evidencia:** pendiente.
- [ ] 3.9 Escribir `assets/css/search-replace-block-editor.css` para el estilo del botón y del modal. **Verificación:** E2E manual: el modal y el botón se ven integrados en el editor. **Evidencia:** pendiente.

## 4. Pestaña y guardado de opciones en el hub

- [ ] 4.1 Añadir la sexta pestaña `editor` → «Editor» al hub en `includes/class-settings.php`, al final del orden (`matrix`, `pocketid`, `umami`, `mastodon`, `tema`, `editor`), delegando en `SearchReplaceBlockEditor::renderSettingsPage()`. **Verificación:** `options-general.php?page=atareao-settings&tab=editor` muestra la pestaña y su contenido; el resto de pestañas no cambia. **Evidencia:** pendiente.
- [ ] 4.2 Renderizar el formulario de la pestaña como POST contra sí misma con nonce `atareao_srfbe_settings` y capacidad `manage_options`. **Verificación:** E2E manual: guardar persiste los valores y un usuario sin `manage_options` no accede. **Evidencia:** pendiente.
- [ ] 4.3 Saneado booleano de las cinco claves y defaults (`use_shortcut` = `true`, resto = `false`) en `atareao_srfbe_options`. **Verificación:** E2E manual: valores no booleanos se convierten a booleano y los defaults aplican cuando la opción no existe. **Evidencia:** pendiente.
- [ ] 4.4 Actualizar el delta `specs/admin-settings/spec.md` con los 3 requirements MODIFIED (encabezados literales del spec destino, texto completo) y sus escenarios. **Verificación:** `openspec validate search-replace-block-editor --strict` válido. **Evidencia:** pendiente.

## 5. Compatibilidad de hooks

- [ ] 5.1 Conservar los nombres de hook del original: acciones `search-replace-for-block-editor.afterSearchReplace` y `search-replace-for-block-editor.replaceBlockAttribute`; filtros `search-replace-for-block-editor.allowedBlocks`, `.excludedPostTypes`, `.regexPattern`, `.handleAttributeReplacement`, `.keyboardShortcut`, `.caseSensitive`; y el filtro de bloques `blocks.registerBlockType`. **Verificación:** `rg` sobre el JS muestra cada nombre y sus puntos de aplicación; una personalización de prueba enganchada recibe la llamada. **Evidencia:** pendiente.
- [ ] 5.2 Respetar los filtros `allowedBlocks` y `excludedPostTypes`: la lista de bloques se puede personalizar y un tipo de post excluido no recibe la app. **Verificación:** E2E manual: con el tipo de post actual en `excludedPostTypes` no aparece el botón ni el atajo. **Evidencia:** pendiente.

## 6. Verificación

- [ ] 6.1 Análisis estático PHP. **Verificación:** `just php-lint` → 0 errores. **Evidencia:** pendiente.
- [ ] 6.2 phpcs sin empeorar el baseline. **Verificación:** `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.2 (se espera +1 warning `PSR1.Files.SideEffects` por la guarda `ABSPATH`). **Evidencia:** pendiente.
- [ ] 6.3 Sintaxis del JS. **Verificación:** `node --check wp-content/plugins/atareao-functionality/assets/js/search-replace-block-editor.js` → exit 0. **Evidencia:** pendiente.
- [ ] 6.4 E2E manual en el editor de bloques. **Verificación:** abrir con botón y con atajo, precarga con selección, conteo al escribir, reemplazo global con aviso, bloques anidados, atributos específicos (`quote`/`pullquote`/`details`/`table`), regex válida e inválida, «Match case» en ambos estados, `save_post` y `close_modal`, y pestaña «Editor» guardando. **Evidencia:** pendiente.
- [ ] 6.5 Spec. **Verificación:** `openspec validate search-replace-block-editor --strict` sin hallazgos. **Evidencia:** pendiente.

## 7. Entrega

- [ ] 7.1 PR por gitflow de `feature/search-replace-block-editor` a `development` con commits convencionales. **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia:** pendiente.
- [ ] 7.2 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive search-replace-block-editor` aplica los deltas (crea `openspec/specs/search-replace-block-editor/spec.md` y actualiza `admin-settings`); `openspec list` ya no muestra el change activo. **Evidencia:** pendiente.
