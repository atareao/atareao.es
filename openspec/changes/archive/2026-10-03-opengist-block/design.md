# Design: Validación del servidor y protección SSRF del bloque OpenGist

## Context

El bloque Gutenberg `atareao/opengist` se registra en `OpengistBlock::registerBlock()` (`class-opengist-block.php:50-63`) con `render_callback => OpengistBlock::renderOpengist()`. El render toma cuatro atributos: `server`, `username`, `gistId` y `file` (más `theme`, no usado en el HTML). El atributo `server` es la vulnerabilidad de raíz.

**Código caracterizado (fichero:línea, verificado):**

- `class-opengist-block.php:71-73`: `$server = isset($attributes['server']) && !empty($attributes['server']) ? esc_url($attributes['server']) : get_option('atareao_opengist_server', '');`. `esc_url()` solo limpia la sintaxis de la URL; **no** restringe el host ni el esquema.
- `class-opengist-block.php:74-76`: `$username` por atributo o `get_option('atareao_opengist_username')`.
- `class-opengist-block.php:88`: `$gist_url = $server . '/' . $username . '/' . $gist_id;` — concatenación sin `rawurlencode`, y `$gist_id` se toma con `esc_attr()` (`:77`), no como segmento de ruta.
- `class-opengist-block.php:98-102`: `$script_url = $gist_url . '.js';` y en el fallback `<?php echo esc_url($script_url); ?>` dentro de `<script src="…">`. `esc_url()` escapa el HTML del atributo, pero **el navegador carga JS del host del atacante**: XSS almacenado en el origen del sitio.
- `class-opengist-block.php:124-125`: `wp_remote_get($script_url, ['timeout' => 15])`.
- `class-opengist-block.php:157-158`: `wp_remote_get($raw_url, ['timeout' => 15])` sobre `$gist_url . '/raw/HEAD/' . rawurlencode($fname)`.
- `assets/blocks/opengist/index.js:15` y `:64-66`: el editor expone el atributo `server` (`value: server`, `setAttributes({ server: value })`).
- Opciones existentes: `atareao_opengist_server` (saneado `esc_url_raw`, `show_in_rest => true`, default `''`) y `atareao_opengist_username` (`sanitize_text_field`), registradas en `class-theme-options.php:44-62`, pestaña `tema` del hub.

**Quién puede escribir el atributo.** Cualquier usuario con rol Author puede crear/editar entradas y, por tanto, fijar `server`. `unfiltered_html` solo lo tienen los administradores (en single-site); un Author no puede meter `<script>` en el contenido, pero **sí** puede publicar un bloque cuyo render emite un `<script src>` hacia el host que elija. El bloque es, de hecho, un bypass de esa frontera.

**Restricciones del repo:** no hay framework de tests ni build tools; PSR12, PHP 8.3; la separación manda la funcionalidad al plugin y deja al tema la presentación. La verificación es estática, con un arnés externo de stubs y E2E en producción.

## Goals / Non-Goals

**Goals**

- Impedir que el atributo `server` del bloque dirija el `<script src>` o las peticiones salientes a un host elegido por el autor.
- Cerrar la superficie SSRF de las dos llamadas salientes.
- Construir las rutas con `rawurlencode`.
- Conservar el render de los bloques legítimos ya publicados (gists propios sobre el servidor configurado).
- Aplicar la validación tanto en el frontend como en la vista previa del editor.

**Non-Goals**

- No se elimina el atributo `server` (rompería la edición y los bloques existentes).
- No se renombran ni borran `atareao_opengist_server` ni `atareao_opengist_username`.
- No se rediseña el bloque ni su CSS/JS de presentación.
- No se añaden dependencias, build tools ni framework de tests.
- No se introducen endpoints REST públicos.

## Decisions

### Decisión 1: Capability nueva `opengist-block`

La seguridad del bloque se modela como capability nueva `opengist-block` porque describe un comportamiento (contrato de validación de host, ausencia de script no permitido, SSRF y compatibilidad) que hoy no cubre ninguna spec. El delta vive en `openspec/changes/opengist-block/specs/opengist-block/spec.md` y al archivar crea `openspec/specs/opengist-block/spec.md`.

**Consecuencias:** el contrato de seguridad queda versionado y auditable. No se modifica `theme-options` porque los requirements existentes (registro y saneado de `atareao_opengist_server`/`_username`) no cambian; la opción de lista blanca es un detalle de implementación de esta capability. Si al implementar se decide que el registro de la nueva opción altera el requirement de `theme-options`, se añadirá su delta antes de archivar.

**Alternativa descartada:** modelarlo como modification de `theme-options`. La validación no vive en el saneado de la opción sino en el render del bloque y en sus peticiones; mezclarla diluiría el contrato.

### Decisión 2 (DECISIÓN CLAVE): conservar el atributo `server` validándolo contra una lista blanca

**Se conserva el atributo `server`** (no se elimina) y **solo se honra si su host pertenece a una lista blanca**; si no, se ignora y se usa la opción de administración. Se descarta eliminar el atributo porque:

- Rompería la edición de los bloques existentes y la lectura de atributos ya guardados en `post_content` (el bloque no usa `deprecated`/migración).
- Impediría el caso legítimo de apuntar a un servidor OpenGist distinto del configurado (por ejemplo, un gist alojado en otra instancia propia).
- La lista blanca ya separa «lo que un administrador autoriza» de «lo que un Author escribe»; no hace falta borrar el atributo para cerrar el ataque.

**Quién puede cambiar el `server` efectivo:** solo el administrador, mediante la opción `atareao_opengist_server` y la nueva lista `atareao_opengist_allowed_hosts`. El Author puede seguir escribiendo el atributo en el editor, pero **no** puede ampliar la lista ni colar un host no permitido: su valor se ignora silenciosamente y el bloque cae al servidor permitido.

**Cómo se mantiene la funcionalidad legítima (gists propios):** el host de `atareao_opengist_server` está permitido por definición. Los bloques que usan el valor por defecto siguen funcionando sin tocar nada. Si un bloque apunta a otro host propio, el administrador lo añade una vez a `atareao_opengist_allowed_hosts` y el bloque vuelve a renderizar como antes.

**Lista blanca:** unión de (a) el host de `atareao_opengist_server` y (b) las entradas de `atareao_opengist_allowed_hosts` (opción nueva, `show_in_rest => true`, default `''`, saneada por entrada con `sanitize_text_field`), editable solo desde la pestaña `tema` con `manage_options`. La comparación normaliza a minúsculas, incluye el puerto si se especificó y exige el mismo esquema del servidor configurado, salvo que el host se permita explícitamente con `http`.

**Alternativa descartada:** eliminar el atributo `server` del bloque. Es la opción más segura en teoría, pero rompe entradas publicadas y la edición, y sacrifica el caso legítimo de instancias propias; la lista blanca resuelve lo mismo sin regresión.

**Alternativa descartada:** dejar el atributo y solo escapar la URL. Es exactamente el estado vulnerable actual; `esc_url()` no limita el host.

### Decisión 3: URL de petición validada y escapada

El servidor efectivo se valida (`wp_parse_url`, host no vacío, esquema permitido) y se reconstruye de forma canónica; `username`, `gist_id` y `file` se escapan con `rawurlencode` como segmentos de ruta antes de concatenar. Se elimina el `esc_attr()` como sustituto de escape de ruta.

**Consecuencias:** el atacante no puede cambiar la ruta ni inyectar segmentos; la URL coincide siempre con el host permitido.

**Alternativa descartada:** confiar en `esc_url()`/`esc_attr()` para los segmentos. No son escapes de ruta.

### Decisión 4: `wp_safe_remote_get` con `redirection => 0`

Las dos llamadas pasan de `wp_remote_get` a **`wp_safe_remote_get`**, con `redirection => 0` y `timeout` acotado. Además, antes de llamar, se comprueba que el host sea permitido. `wp_safe_remote_*` bloquea IPs privadas/reservadas (loopback, link-local, rangos internos) y es la herramienta de core para el caso.

**Consecuencias:** se cierra el SSRF hacia la red interna y no se sigue una redirección a un host ajeno. La comprobación previa de host evita incluso emitir la petición a un host no permitido.

**Riesgo aceptado:** un servidor OpenGist legítimo alojado en una IP privada dejaría de funcionar. Es el equilibrio habitual al cerrar SSRF; el administrador puede permitir explícitamente ese host en la lista, pero `wp_safe_remote_get` seguiría bloqueando la IP privada. Se documenta como riesgo.

**Alternativa descartada:** `wp_remote_get` con validación manual de host. Deja abiertas las redirecciones y no replica la lista negra de IPs de core.

### Decisión 5: Fallback sin script de host no permitido

El `<script src>` de respaldo se emite **solo** si el host del script es permitido. Si no, no se emite script externo: se muestra un aviso (o solo el contenido obtenido por servidor, que ya viene de un host permitido). Con esto el bloque nunca introduce JS de un tercero no autorizado en el origen del sitio.

**Consecuencias:** incluso si el render por servidor falla, un host no permitido no llega al HTML. La degradación es visible y comprensible.

**Alternativa descartada:** emitir el `<script src>` con `esc_url()` «porque escapa». Ese es el fallo actual: escapa el HTML, no el origen del script.

### Decisión 6: Compatibilidad y migración sin regresión

El camino principal sigue siendo el render por servidor (código propio) y el fallback el respaldo. Los nombres, saneado (`esc_url_raw`/`sanitize_text_field`) y defaults de las opciones existentes no cambian. El atributo `server` se conserva. La vista previa del editor aplica la misma validación que el frontend (aviso si el host no está permitido) para que el autor vea el efecto antes de publicar.

**Consecuencias:** actualizar el plugin no altera el HTML de los bloques legítimos; solo se degradan los que apuntan a hosts no permitidos, que son precisamente los de riesgo.

**Alternativa descartada:** sanear el atributo al leerlo y guardarlo «normalizado». Cambiaría el contenido guardado y el comportamiento de edición sin necesidad.

## Alternatives discarded (resumen)

- **Eliminar el atributo `server`**: rompe entradas publicadas y la edición, y elimina el caso legítimo de instancias propias. Descartada.
- **Solo escapar la URL**: no limita el host; es el estado vulnerable. Descartada.
- **Validar manualmente con `wp_remote_get`**: no cubre redirecciones ni la lista negra de IPs de core. Descartada.
- **Modificar el requirement de `theme-options`**: la validación no es saneado de opción sino contrato del render. Descartada.

## Risks / Trade-offs

- **[Regresión en bloques publicados]** → Un bloque que apunte a un host fuera de la lista deja de renderizar el gist hasta que el administrador añada el host. Mitigado por compatibilidad (Decisión 6): el default y los hosts permitidos siguen igual, y la lista se amplía sin tocar el bloque.
- **[Servidor legítimo en IP privada]** → `wp_safe_remote_get` bloquea IPs privadas aunque el host esté permitido (Decisión 4). Riesgo aceptado y documentado.
- **[Verificación sin framework de tests]** → No hay tests automatizados. Se verifica con `just php-lint`, `just phpcs`, un arnés externo de stubs en `/tmp` y E2E manual en producción.
- **[Divergencia editor/frontend]** → La validación debe duplicarse. Mitigado haciéndola explícita en el contrato (`Compatibilidad con los bloques ya existentes`) y verificándola en el arnés y en el E2E.

## Verification

> El repositorio **no tiene framework de tests** ni build tools. La verificación combina análisis estático, un **arnés externo de stubs** que vive solo en `/tmp/opencode/opengist-harness/` (fuera del repo y no versionado) y E2E manual en producción. La implementación arranca **solo tras la aprobación del usuario**.

- **Lint**: `just php-lint` → 0 errores.
- **phpcs**: `just phpcs` (theme+plugin) sin empeorar el baseline. Baseline medido (2026-10-03) en theme+plugin: **752 errores / 426 warnings**. Objetivo de delta **+0 errores**.
- **Arnés externo de stubs** (`/tmp/opencode/opengist-harness/`, no versionado): define de forma controlable las funciones de WordPress usadas (`get_option`, `esc_url`, `esc_attr`, `esc_html`, `esc_html_e`, `wp_parse_url`, `wp_safe_remote_get`, `wp_remote_get`, `wp_remote_retrieve_response_code`, `wp_remote_retrieve_body`, `is_wp_error`, `wp_kses`, `current_user_can`, `sanitize_text_field`, etc.) y comprueba:
  1. **Host permitido**: un atributo con host en la lista usa ese host; el HTML coincide con el esperado.
  2. **Host no permitido**: un atributo a `evil.example` se ignora, cae al servidor por defecto y **no** aparece `evil.example` en ningún `<script src>` ni en ninguna URL de petición.
  3. **SSRF**: un atributo a `127.0.0.1`/`169.254.169.254` no dispara peticiones; las llamadas usan `wp_safe_remote_get` con `redirection => 0`.
  4. **Escapado de ruta**: `username`/`gist_id` con caracteres especiales se pasan por `rawurlencode`.
  5. **Fallback**: con host permitido se emite `<script src>`; con host no permitido no se emite script externo y aparece el aviso.
  6. **Compatibilidad**: un bloque con el servidor por defecto se renderiza igual; las opciones existentes no cambian de nombre/saneado/default.
  Resultado objetivo: `TOTAL=N PASS=N FAIL=0`, `exit=0`.
- **E2E manual en producción**: comprobar que un bloque existente de gist propio sigue renderizando; que un bloque con `server` a un host ajeno no carga script; que la lista blanca se edita solo con `manage_options`; que añadir un host propio lo restaura; y que el resto del sitio (microsite `/tools/`, hub, analítica) no cambia.
- `openspec validate opengist-block --strict` sin hallazgos.

## Open Questions

Ninguna. La decisión sobre el atributo `server` (conservarlo validándolo contra la lista blanca) y la ampliación de la lista por el administrador quedan resueltas en la Decisión 2.
