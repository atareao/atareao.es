# Proposal: Validación del servidor y protección SSRF del bloque OpenGist

## Why

El bloque Gutenberg `atareao/opengist` acepta un atributo **`server`** que controla la URL base desde la que se descargan el script de embed y los ficheros del gist. Ese atributo lo puede fijar cualquier usuario con rol **Author** (capaz de crear y editar entradas) y **no se valida contra ninguna lista blanca**:

1. **XSS almacenado.** `renderOpengist()` hace `$server = esc_url($attributes['server'])` (`class-opengist-block.php:71-73`); `esc_url()` **no restringe el host**. Con ese valor construye `$gist_url` (`:98-102`) y, en el fallback, emite `<?php echo esc_url($script_url); ?>` dentro de `<script src="…">` (`:102`). Un Author publica una entrada que hace cargar **JavaScript de un host que él elige** en el origen del sitio: es XSS almacenado y rompe la frontera de `unfiltered_html`, que solo protege contra etiquetas en el contenido, no contra lo que el bloque inserta por su cuenta.
2. **SSRF.** `fetchGistFiles()` hace `wp_remote_get($script_url, ['timeout' => 15])` (`:124-125`) y `wp_remote_get($raw_url, ['timeout' => 15])` (`:157-158`) sin `wp_safe_remote_*`, sin validar el host y sin bloquear rangos internos (`127.0.0.1`, `169.254.169.254`, servicios internos). El servidor de WordPress queda como proxy hacia la red interna.
3. **Construcción de URL sin escapar.** `$gist_url = $server . '/' . $username . '/' . $gist_id` (`:88`) concatena valores sin `rawurlencode`, de modo que `username`/`gist_id` manipulados pueden alterar la ruta o inyectar segmentos.

El atributo `server` también lo expone el editor en `assets/blocks/opengist/index.js` (`server` en `attributes`, `:15` y `:64-66`).

El objetivo es **cerrar la superficie sin romper los bloques ya publicados**: el host efectivo debe pertenecer a una lista blanca administrada, las peticiones salientes deben ser seguras y los bloques legítimos (gists propios sobre el servidor configurado) deben seguir funcionando igual.

## What Changes

- **Nueva capability `opengist-block`**: validación del host del servidor del bloque contra una lista blanca, ausencia de `<script src>` hacia hosts no permitidos, protección SSRF en las peticiones salientes y compatibilidad con los bloques ya existentes.
- **Lista blanca de hosts**: el `server` efectivo del bloque SHALL coincidir con la opción de administración `atareao_opengist_server` o con una lista permitida configurable (nueva opción `atareao_opengist_allowed_hosts`, editable solo por administradores en la pestaña «Tema»). Si el atributo del bloque apunta a otro host, **se ignora** y se cae al servidor por defecto; si tampoco hay uno válido, se muestra un aviso.
- **Validación de esquema y host**: el servidor SHALL validarse como `https` (o `http` solo si el host está explícitamente permitido) con host no vacío; `username` y `gist_id` SHALL escaparse con `rawurlencode` al construir la ruta.
- **Peticiones salientes seguras**: `fetchGistFiles()` SHALL usar **`wp_safe_remote_get`** con `redirection => 0` y `timeout` acotado en las dos peticiones (script de embed y raw), y SHALL rechazar hosts no permitidos antes de llamar.
- **Fallback sin host ajeno**: el `<script src>` de respaldo SHALL emitirse **solo** si el host pertenece a la lista blanca; en caso contrario no se emite script externo y se muestra un aviso (o solo el contenido obtenido en servidor).
- **Editor**: el bloque documenta que el campo `server` solo se honra si el host está permitido; se conserva el atributo por compatibilidad.
- **No cambia** el HTML público de los bloques legítimos ni los nombres de opción existentes (`atareao_opengist_server`, `atareao_opengist_username`).

## Capabilities

### New Capabilities

- `opengist-block`: validación del host del servidor del bloque, ausencia de script externo de host no permitido, protección SSRF en las peticiones salientes y compatibilidad con los bloques ya existentes.

### Modified Capabilities

Ninguna. La opción `atareao_opengist_server` y el saneado existentes no cambian de contrato; la nueva opción de lista blanca se añade a la pestaña «Tema» como detalle de implementación de esta capability. Si en el diseño se confirma que el registro de la nueva opción altera un requirement de `theme-options`, se añadirá su delta antes de archivar.

## Impact

- **Archivos**:
  - Modificado: `wp-content/plugins/atareao-functionality/includes/class-opengist-block.php` (validación de host, `rawurlencode`, `wp_safe_remote_get` + `redirection => 0`, fallback condicionado).
  - Modificado: `wp-content/plugins/atareao-functionality/assets/blocks/opengist/index.js` (nota/validación del campo `server` en el editor).
  - Posiblemente modificado: `wp-content/plugins/atareao-functionality/includes/class-theme-options.php` (registro de `atareao_opengist_allowed_hosts`).
  - Nuevo (spec): `openspec/specs/opengist-block/spec.md` vía el delta `specs/opengist-block/spec.md`.
- **No cambia**: los bloques ya publicados cuyo host efectivo (atributo u opción) está permitido siguen renderizándose igual; no se renombran ni borran opciones; el resto del microsite `/tools/` y las demás pestañas del hub quedan intactos.
- **Compatibilidad**: PHP 8.3, PSR12, WordPress 6.0+. Sin dependencias nuevas (solo `wp_safe_remote_get`, `wp_parse_url` y `sanitize_text_field`).
- **Riesgo aceptado**: un bloque que apunte a un host legítimo que **no** esté en la lista blanca pasa a ignorar ese host; el administrador lo añade a `atareao_opengist_allowed_hosts` para restaurarlo.
