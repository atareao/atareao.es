# Search Replace Block Editor Delta

## Purpose

Internaliza en `atareao-functionality` la búsqueda y reemplazo de texto del editor de bloques para sustituir al plugin externo «Search and Replace for Block Editor», con paridad funcional sobre los bloques de texto, coincidencia literal o mediante expresión regular, y su configuración en el hub «Atareao».

## ADDED Requirements

### Requirement: Acceso al buscador y modal de búsqueda y reemplazo

El sistema SHALL ofrecer acceso a la búsqueda y reemplazo desde el editor de bloques mediante un botón con icono de lupa en la barra de herramientas del editor y mediante el atajo `primary+f` (CMD en macOS, CTRL en otros), configurable y registrado en la categoría global de atajos. Al activarse cualquiera de los dos accesos, el sistema SHALL abrir un `Modal` de `wp.components` con un campo «Search» y un campo «Replace», dos conmutadores «Match case» y «Use regular expression», y los botones «Replace» y «Done». Al abrirse el modal, el sistema SHALL enfocar el campo «Search» y SHALL precargar la búsqueda con el texto seleccionado en el editor (contenido del iframe `iframe[name="editor-canvas"]`) cuando exista una selección. Al cambiar el texto de búsqueda o cualquiera de los dos conmutadores, el sistema SHALL contar las coincidencias y SHALL mostrar el texto «N item(s) found». Al pulsar «Replace», el sistema SHALL reemplazar todas las coincidencias encontradas y SHALL mostrar el texto «N item(s) replaced successfully». El atajo SHALL poder desactivarse desde las opciones del módulo; cuando esté desactivado, el atajo SHALL NOT dispararse y el botón de la lupa SHALL seguir disponible.

#### Scenario: Abrir con el botón de la barra de herramientas

- **WHEN** un usuario con acceso al editor de bloques pulsa el botón de la lupa de la barra de herramientas
- **THEN** se abre el modal con los campos «Search» y «Replace», los conmutadores «Match case» y «Use regular expression» y los botones «Replace» y «Done»

#### Scenario: Abrir con el atajo primary+f

- **WHEN** un usuario con el editor de bloques abierto y la opción `use_shortcut` activa pulsa `primary+f`
- **THEN** se abre el modal de búsqueda y reemplazo, enfocado en el campo «Search»

#### Scenario: Precarga con la selección del editor

- **WHEN** el usuario tiene texto seleccionado en el editor y abre el modal
- **THEN** el campo «Search» aparece precargado con el texto seleccionado

#### Scenario: Conteo de coincidencias

- **WHEN** el usuario modifica el texto de búsqueda o alterna «Match case» o «Use regular expression» y hay coincidencias
- **THEN** el modal muestra «N item(s) found» con el número de coincidencias encontradas

#### Scenario: Reemplazo de todas las coincidencias

- **WHEN** el usuario pulsa «Replace» con una búsqueda que tiene coincidencias
- **THEN** el sistema reemplaza todas las coincidencias en los bloques soportados y muestra «N item(s) replaced successfully»

#### Scenario: Atajo desactivado desde opciones

- **WHEN** la opción `use_shortcut` está en falso y el usuario pulsa `primary+f`
- **THEN** no se abre el modal y el botón de la lupa sigue abriéndolo

### Requirement: Bloques y atributos soportados

El sistema SHALL considerar elegibles para el reemplazo los bloques cuya `category` sea `text`. Si de esa selección no resulta ningún bloque, el sistema SHALL usar como fallback la lista `core/paragraph`, `core/heading`, `core/list`, `core/list-item`, `core/quote`, `core/code`, `core/details`, `core/missing`, `core/preformatted`, `core/pullquote`, `core/table`, `core/verse`, `core/footnotes` y `core/freeform`. El sistema SHALL recorrer recursivamente `innerBlocks` para alcanzar los bloques anidados. El sistema SHALL aplicar el reemplazo sobre los atributos según el tipo de bloque: `core/quote` sobre `citation`; `core/pullquote` sobre `value` y `citation`; `core/details` sobre `summary`; `core/table` sobre `head`, `body`, `foot` y `caption`, tratados como cadena serializada en JSON y deserializados antes de escribir; y para el resto de bloques sobre `content`. El sistema SHALL escribir cada cambio con `wp.data.dispatch('core/block-editor').updateBlockAttributes(clientId, {...})`. Los bloques no elegibles SHALL NOT modificarse.

#### Scenario: Reemplazo en un párrafo

- **WHEN** la búsqueda coincide con texto del atributo `content` de un bloque `core/paragraph` elegible
- **THEN** el reemplazo se aplica al atributo `content` de ese bloque y se actualiza por su `clientId`

#### Scenario: Atributos específicos por tipo de bloque

- **WHEN** la búsqueda coincide en los atributos de un `core/quote`, un `core/pullquote`, un `core/details` o un `core/table`
- **THEN** el reemplazo se aplica a `citation` en `core/quote`; a `value` y `citation` en `core/pullquote`; a `summary` en `core/details`; y a `head`, `body`, `foot` y `caption` en `core/table`, reserializando el JSON del `core/table` al escribir

#### Scenario: Bloques anidados

- **WHEN** un bloque elegible contiene bloques hijos en `innerBlocks`
- **THEN** el sistema recorre la jerarquía y aplica el reemplazo también sobre los bloques anidados

#### Scenario: Bloque no permitido intacto

- **WHEN** un bloque no está en la lista de elegibles ni tiene `category` `text`
- **THEN** sus atributos permanecen sin cambios tras el reemplazo

### Requirement: Coincidencia literal, expresión regular y sensibilidad a mayúsculas

Por defecto el sistema SHALL interpretar la búsqueda como texto literal, escapando los metacaracteres del input antes de construir el patrón. El modo expresión regular SHALL ser opt-in mediante el conmutador «Use regular expression»; en ese modo el sistema SHALL usar el patrón tal cual lo escriba el usuario. El conmutador «Match case» SHALL determinar si la coincidencia distingue entre mayúsculas y minúsculas. En todos los casos el sistema SHALL aplicar el patrón únicamente sobre el texto contenido dentro del marcado HTML de cada atributo, sin cruzar etiquetas HTML. Si el patrón de una expresión regular no es válido, el sistema SHALL degradar a la interpretación literal escapada en lugar de fallar.

#### Scenario: Coincidencia literal por defecto

- **WHEN** el usuario busca un texto con caracteres especiales de expresión regular y el modo regex está desactivado
- **THEN** los caracteres se interpretan de forma literal y solo coinciden las apariciones exactas de ese texto dentro del contenido de cada atributo

#### Scenario: Coincidencia por expresión regular

- **WHEN** el usuario activa «Use regular expression» y escribe un patrón válido
- **THEN** el sistema usa el patrón como expresión regular para encontrar coincidencias en el texto de los atributos

#### Scenario: Sensibilidad a mayúsculas

- **WHEN** el usuario alterna «Match case»
- **THEN** la coincidencia pasa a distinguir (o a dejar de distinguir) entre mayúsculas y minúsculas según el estado del conmutador

#### Scenario: Expresión regular inválida

- **WHEN** el usuario activa el modo regex y escribe un patrón inválido
- **THEN** el sistema no falla y aplica la búsqueda como texto literal escapado

### Requirement: Opciones del módulo y pestaña en el hub

El sistema SHALL persistir la configuración del módulo en la opción `atareao_srfbe_options` como un array con cinco claves booleanas: `case_matching`, `regex_matching`, `save_post`, `close_modal` y `use_shortcut`. Los valores por defecto SHALL ser `use_shortcut` en verdadero y `case_matching`, `regex_matching`, `save_post` y `close_modal` en falso. El sistema SHALL exponer la configuración como la pestaña `editor` («Editor») del hub «Atareao», cuya función de render es `SearchReplaceBlockEditor::renderSettingsPage()`. El guardado de la pestaña SHALL realizarse por POST contra sí misma, SHALL exigir la capacidad `manage_options`, SHALL verificar el nonce `atareao_srfbe_settings` y SHALL sanear cada valor a booleano antes de persistirlo. El sistema SHALL localizar la opción efectiva al JavaScript del editor. Cuando `save_post` esté activo, el sistema SHALL guardar el post tras reemplazar; cuando `close_modal` esté activo, el sistema SHALL cerrar el modal y mostrar el aviso de éxito tras reemplazar.

#### Scenario: Valores por defecto

- **WHEN** la opción `atareao_srfbe_options` no existe todavía
- **THEN** el módulo usa `use_shortcut` en verdadero y `case_matching`, `regex_matching`, `save_post` y `close_modal` en falso

#### Scenario: Guardado de las opciones

- **WHEN** un administrador guarda la pestaña «Editor»
- **THEN** el formulario se envía por POST a la propia pestaña con el nonce `atareao_srfbe_settings`, cada valor se sanea a booleano y se persiste en `atareao_srfbe_options`

#### Scenario: Pestaña en el hub

- **WHEN** un administrador abre `options-general.php?page=atareao-settings&tab=editor`
- **THEN** se muestra el contenido de la pestaña «Editor» renderizado por `SearchReplaceBlockEditor::renderSettingsPage()`

#### Scenario: Guardado del post tras reemplazar

- **WHEN** la opción `save_post` está activa y el usuario pulsa «Replace»
- **THEN** el sistema guarda el post después de aplicar el reemplazo

#### Scenario: Cierre del modal tras reemplazar

- **WHEN** la opción `close_modal` está activa y el usuario pulsa «Replace»
- **THEN** el sistema cierra el modal y muestra el aviso de éxito del reemplazo

### Requirement: Recorte al editor de bloques y compatibilidad de hooks

El sistema SHALL encolar sus assets únicamente en `enqueue_block_editor_assets`, de modo que la app SHALL NOT cargarse en el frontend ni en el editor clásico. Si el tipo de post actual figura en la lista devuelta por el filtro `search-replace-for-block-editor.excludedPostTypes`, el sistema SHALL NOT inyectar la app. El sistema SHALL conservar, para compatibilidad con personalizaciones existentes, los nombres de hook del plugin original: las acciones `search-replace-for-block-editor.afterSearchReplace` y `search-replace-for-block-editor.replaceBlockAttribute`; los filtros `search-replace-for-block-editor.allowedBlocks`, `search-replace-for-block-editor.excludedPostTypes`, `search-replace-for-block-editor.regexPattern`, `search-replace-for-block-editor.handleAttributeReplacement`, `search-replace-for-block-editor.keyboardShortcut` y `search-replace-for-block-editor.caseSensitive`; y el filtro de bloques `blocks.registerBlockType`.

#### Scenario: Solo en el editor de bloques

- **WHEN** se carga cualquier pantalla que no sea el editor de bloques, incluido el frontend y el editor clásico
- **THEN** los assets de la app no se encolan y no se inyecta ningún botón ni atajo

#### Scenario: Tipo de post excluido

- **WHEN** el tipo de post actual está incluido en la lista del filtro `search-replace-for-block-editor.excludedPostTypes`
- **THEN** la app no se inyecta en el editor de ese tipo de post

#### Scenario: Hooks preservados

- **WHEN** una personalización se engancha a los hooks `search-replace-for-block-editor.*` o al filtro `blocks.registerBlockType`
- **THEN** el sistema conserva esos nombres de hook y la personalización sigue operando como con el plugin original
