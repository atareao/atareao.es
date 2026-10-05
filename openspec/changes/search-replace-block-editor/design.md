# Design: Internalizar la búsqueda y reemplazo en el editor de bloques

## Context

El sitio usa hoy el plugin de terceros **Search and Replace for Block Editor** (`badasswp/aw-ng` v1.12.0) para buscar y reemplazar texto dentro del editor de bloques. Es una app del editor (React) distribuida como bundle compilado, con un contrato funcional acotado que se quiere internalizar en `atareao-functionality` para poder desinstalar el plugin externo sin perder comportamiento.

**Contrato funcional del original (a paridad):**

1. **Acceso**: un botón con icono de lupa en la barra de herramientas del editor y el atajo `primary+f` (CMD/CTRL+F, configurable, en la categoría global de atajos). Abre un `Modal` de `wp.components` con campos «Search» y «Replace», los conmutadores «Match case» y «Use regular expression», y los botones «Replace» y «Done». Auto-foco al abrir; precarga la búsqueda con la selección del editor (iframe `iframe[name="editor-canvas"]`). Al cambiar la búsqueda o los conmutadores cuenta coincidencias y muestra «N item(s) found»; «Replace» reemplaza **todas** las coincidencias y muestra «N item(s) replaced successfully».
2. **Bloques permitidos**: bloques con `category === 'text'`; si la lista resulta vacía, fallback a `core/paragraph`, `core/heading`, `core/list`, `core/list-item`, `core/quote`, `core/code`, `core/details`, `core/missing`, `core/preformatted`, `core/pullquote`, `core/table`, `core/verse`, `core/footnotes`, `core/freeform`. Recorrido recursivo de `innerBlocks`.
3. **Atributos por bloque**: `core/quote` → `citation`; `core/pullquote` → `value` y `citation`; `core/details` → `summary`; `core/table` → `head`, `body`, `foot`, `caption` (serializado como cadena JSON con `stringify`/`parse`); por defecto → `content`. Actualiza con `wp.data.dispatch('core/block-editor').updateBlockAttributes(clientId, {...})`.
4. **Patrón**: literal por defecto (input escapado); modo regex opt-in; sensible a mayúsculas con toggle; el patrón solo coincide con texto dentro del marcado HTML (no cruza etiquetas). Regex inválida → fallback a literal escapado.
5. **Opciones (pestaña propia)**: claves `case_matching`, `regex_matching`, `save_post`, `close_modal`, `use_shortcut`. Defaults efectivos: `use_shortcut` = `true`, el resto = `false`. `save_post` guarda el post tras reemplazar; `close_modal` cierra el modal y muestra el aviso de éxito tras reemplazar.
6. **Hooks públicos a conservar** (paridad para personalizaciones existentes): acciones `search-replace-for-block-editor.afterSearchReplace` y `search-replace-for-block-editor.replaceBlockAttribute`; filtros `search-replace-for-block-editor.allowedBlocks`, `search-replace-for-block-editor.excludedPostTypes`, `search-replace-for-block-editor.regexPattern`, `search-replace-for-block-editor.handleAttributeReplacement`, `search-replace-for-block-editor.keyboardShortcut`, `search-replace-for-block-editor.caseSensitive`, y el filtro de bloques `blocks.registerBlockType`.
7. **Carga acotada**: se carga solo en el editor de bloques (`enqueue_block_editor_assets`). Si el tipo de post actual está en la lista del filtro `excludedPostTypes`, la app no se inyecta.

**Estado actual y restricciones del repo:**

- No existen **build tools**: ni `package.json`, ni `composer.json`, ni webpack, ni transpilación. El JS de bloques se escribe en JS nativo con `wp.element.createElement` (sin JSX). El CSS se edita como fuente.
- **No existe framework de tests** (ni PHPUnit ni Jest). La verificación es análisis estático, comprobación de sintaxis JS y E2E manual.
- PSR12 es el estándar aplicado; PHP 8.3; WordPress 6.0+.
- Regla de separación: la funcionalidad va en el plugin `atareao-functionality`; el tema es solo presentación.
- El hub de ajustes `\Atareao\Settings` existe en `includes/class-settings.php` y delega en las pestañas `matrix`, `pocketid`, `umami`, `mastodon` y `tema`, con whitelist de slugs, `nav-tab-wrapper` y resolución de la pestaña por el parámetro `tab` sin JavaScript.

## Goals / Non-Goals

**Goals:**

- Internalizar en `atareao-functionality` la búsqueda y reemplazo del editor de bloques con paridad funcional respecto al plugin original.
- Reescribir la app en JS nativo sobre los globals de WordPress, sin JSX, sin transpilación y sin build tools.
- Exponer la configuración como sexta pestaña `editor` («Editor») del hub «Atareao».
- Conservar los nombres de los hooks públicos del original para no romper personalizaciones.
- Cargar la app solo en el editor de bloques y respetar los tipos de post excluidos.
- Permitir desinstalar el plugin externo tras la verificación.

**Non-Goals:**

- No se toca el frontend del sitio: la funcionalidad es exclusiva del editor de bloques.
- No se da soporte al editor clásico ni a otras pantallas de wp-admin.
- No se añaden build tools, gestores de paquetes, JSX ni transpilación.
- No se rediseña el hub ni se cambia el comportamiento de las cinco pestañas existentes.
- No se porta la página «More Plugins» del plugin original ni ningún aviso de promoción.
- No se vendoriza el bundle compilado del plugin original.

## Decisions

### Decisión 1: Reescritura en JS nativo sobre los globals de WordPress

La app se reescribe en `assets/js/search-replace-block-editor.js` usando los globals `wp.element` (con `createElement`), `wp.components`, `wp.data`, `wp.hooks`, `wp.keyboardShortcuts`, `wp.blockEditor`, `wp.icons` y `wp.i18n`, sin JSX ni build. El archivo se encola desde PHP como dependencia de los paquetes de WordPress correspondientes.

**Consecuencias:** el código es legible y editable como fuente, coherente con la convención «no build tools» del repo, y no arrastra una cadena de compilación. El coste es un poco más de verbosidad (`createElement`) y la necesidad de vigilar la disponibilidad de cada global.

**Alternativas descartadas:**
- **(a) Vendorizar el bundle compilado `dist/app.js` del plugin original.** Daría paridad exacta, pero deja código opaco, difícil de mantener y atado a la versión concreta del plugin; además mezcla licencias y ciclos de vida.
- **(b) Añadir webpack/npm.** Permitiría escribir JSX, pero viola la convención explícita del repo de no tener build tools y añade tooling que hoy no existe.

### Decisión 2: Nueva clase PHP `\Atareao\SearchReplaceBlockEditor`

La parte servidor vive en `includes/class-search-replace-block-editor.php`, con el patrón del repo: `namespace Atareao;`, guarda `if (!defined('ABSPATH')) { exit; }`, `public static function init()` idempotente que solo engancha hooks, y un `renderSettingsPage()` público para el hub. La clase se registra en `atareao-functionality.php` con `require_once` + `SearchReplaceBlockEditor::init()`.

**Consecuencias:** la responsabilidad queda encapsulada y versionada con el plugin; el registro es explícito (no hay autoloader) y hay que mantener el `require_once` en sync.

**Alternativa descartada:** poner la lógica en `class-settings.php`. El hub debe seguir siendo un contenedor de pestañas; cada módulo conserva su lógica y su guardado.

### Decisión 3: Opciones en el hub, nueva pestaña `editor`

Las opciones se gestionan desde una nueva pestaña `editor` («Editor») del hub, en sexto lugar del orden `matrix`, `pocketid`, `umami`, `mastodon`, `tema`, `editor`. La opción se persiste como `atareao_srfbe_options` (array). El guardado es por POST contra la propia pestaña, con nonce `atareao_srfbe_settings` y capacidad `manage_options`, saneando cada valor a booleano.

**Consecuencias:** un único punto de entrada en wp-admin, coherente con la política del hub. Requiere modificar el delta `admin-settings` (tres requirements) y la delegación a `SearchReplaceBlockEditor::renderSettingsPage()`.

**Alternativa descartada:** replicar el menú de nivel superior del plugin original. Rompe la convención de punto de entrada único que el hub acababa de consolidar.

### Decisión 4: Assets JS/CSS nuevos y dedicados

Se añaden `assets/js/search-replace-block-editor.js` y `assets/css/search-replace-block-editor.css`. El PHP registra y encola ambos solo en `enqueue_block_editor_assets`, y localiza al JS la opción efectiva mediante un objeto de configuración.

**Consecuencias:** el editor de bloques carga un asset acotado; el frontend no paga coste. El CSS queda versionado junto al JS.

**Alternativa descartada:** reutilizar assets existentes de otros módulos. Mezclaría responsabilidades y ámbitos de carga.

### Decisión 5: Conservar los nombres de hooks y objeto del original

Se conservan los nombres de hook del plugin original (`search-replace-for-block-editor.*` y `blocks.registerBlockType`) y el nombre del objeto de configuración localizado, para que cualquier personalización existente siga funcionando como un reemplazo drop-in.

**Consecuencias:** máxima compatibilidad con personalizaciones ya desplegadas. El namespace del plugin propio no se refleja en estos nombres, lo que se documenta como decisión consciente de paridad.

**Alternativa descartada:** renombrar los hooks a `atareao/...`. Rompería las personalizaciones existentes que hoy se enganchan a los nombres originales.

### Decisión 6: Bump de versión menor del plugin

Se incrementa `ATAREAO_PLUGIN_VERSION` con un bump de tipo menor en `atareao-functionality.php`. Si este change y `post-type-switcher` se fusionan juntos, la versión final la fija el primero en mergear, para no provocar conflictos de conflicto de versión entre ambos.

**Consecuencias:** el cambio queda identificable en la versión del plugin. El orden de merge determina el valor final, evitando un salto doble no deseado.

**Alternativa descartada:** no tocar la versión. Dificultaría identificar cuándo llegó la funcionalidad y rompería la convención de versionado del plugin.

## Risks / Trade-offs

- **[Sin framework de tests JS]** → No hay Jest ni similares en el repo. Mitigación: verificación de sintaxis con `node --check` sobre el JS y E2E manual en el editor de bloques (abrir con botón y con atajo, contar, reemplazar, comprobar bloques anidados y atributos específicos, y comprobar `save_post`/`close_modal`).
- **[Selectores de la barra de herramientas que cambian entre versiones de WordPress]** → El botón de la barra se registra por las APIs del editor (`wp.blockEditor`/`wp.plugins`), no por selectores CSS frágiles; aun así, un cambio de API entre versiones podría requerir ajuste. Mitigación: E2E manual en la versión de WordPress del sitio y revisión al actualizar.
- **[El contenido editable vive en un iframe]** → La selección del editor y el foco se leen del iframe `iframe[name="editor-canvas"]`; el acceso al documento del iframe puede fallar en algunos contextos. Mitigación: la precarga con la selección es best-effort y su ausencia no bloquea la apertura del modal.
- **[Disponibilidad de `wp.keyboardShortcuts`]** → El registro del atajo `primary+f` en la categoría global depende de `wp.keyboardShortcuts`, disponible desde WordPress 6.4. Mitigación: se documenta como requisito efectivo (WP≥6.4) y el botón de la lupa sigue funcionando sin el atajo; el atajo es además desactivable desde opciones.
- **[Diferencia de comportamiento con el bundle original]** → Al reescribir en nativo puede haber divergencias sutiles (orden de hooks, forma exacta de los avisos). Mitigación: el contrato funcional de `design.md` §Context fija el comportamiento esperado y la verificación E2E lo comprueba contra la paridad descrita.
- **[Duplicidad durante la convivencia con el plugin externo]** → Mientras ambos estén activos, podrían registrarse dos botones o dos atajos. Mitigación: desinstalar el plugin externo tras verificar la paridad, tal como indica el objetivo del change.
- **[Regex inválida introducida por el usuario]** → Un patrón inválido podría romper el conteo o el reemplazo. Mitigación: el motor degrada a interpretación literal escapada en lugar de fallar.
