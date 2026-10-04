# opengist-block Specification

## Purpose
Esta capability cierra la superficie de abuso del bloque Gutenberg `atareao/opengist` sin romper las entradas ya publicadas. El bloque acepta un atributo `server` escribible por cualquier usuario con rol Author; hoy ese valor se pasa por `esc_url()` y acaba en un `<script src>` y en peticiones `wp_remote_get`, lo que permite XSS almacenado y SSRF. La capability exige que el host efectivo pertenezca a una lista blanca administrada, construya las rutas con `rawurlencode`, use `wp_safe_remote_get` con `redirection => 0` y no emita script externo de un host no permitido, conservando el comportamiento de los bloques legítimos (gists propios sobre el servidor configurado).

## Requirements

### Requirement: Validación del host del servidor del bloque

El sistema SHALL resolver el `server` efectivo de `atareao/opengist` a partir del atributo `server` del bloque si su host está permitido y, en caso contrario, a partir de la opción de administración `atareao_opengist_server`. Un host SHALL considerarse permitido si coincide exactamente con el host de `atareao_opengist_server` o con una entrada de la lista permitida configurable `atareao_opengist_allowed_hosts`, con el mismo esquema, el mismo host (normalizado en minúsculas y sin barra final) y el mismo puerto. La coincidencia de puerto SHALL aplicarse simétricamente: cuando la URL evaluada declare un puerto, la entrada permitida SHALL declarar ese mismo puerto para autorizarla; una entrada permitida que no declare puerto SHALL NOT autorizar URLs con puerto explícito; y una entrada permitida que sí declare puerto SHALL exigirlo. El sistema SHALL validar que el servidor efectivo tenga un esquema `https` (o `http` solo cuando el host esté explícitamente permitido) y un host no vacío. El sistema SHALL normalizar el host en minúsculas y sin barra final antes de compararlo. La lista `atareao_opengist_allowed_hosts` SHALL ser editable únicamente por usuarios con capacidad `manage_options` y SHALL sanearse con `sanitize_text_field` por entrada. Si el atributo del bloque apunta a un host no permitido, el sistema SHALL ignorarlo, SHALL usar el servidor por defecto y SHALL NOT usar el atributo como URL base de ninguna petición ni de ningún atributo HTML.

#### Scenario: Atributo con host permitido

- **WHEN** un bloque trae `server="https://gist.example"` y ese host está en `atareao_opengist_allowed_hosts` o coincide con `atareao_opengist_server`
- **THEN** el sistema usa `https://gist.example` como servidor efectivo del bloque

#### Scenario: Atributo con host no permitido

- **WHEN** un bloque trae `server="https://evil.example"` y ese host no está permitido
- **THEN** el sistema ignora el atributo y usa `atareao_opengist_server` como servidor efectivo

#### Scenario: Servidor efectivo no válido

- **WHEN** ni el atributo ni la opción proporcionan un servidor con host no vacío y esquema válido
- **THEN** el sistema no realiza peticiones y muestra el aviso de configuración incompleta

#### Scenario: Solo administradores editan la lista

- **WHEN** un usuario sin capacidad `manage_options` intenta modificar `atareao_opengist_allowed_hosts`
- **THEN** el sistema no le permite guardar la lista permitida

#### Scenario: Puerto no declarado en la entrada permitida

- **WHEN** un bloque trae `server="https://host-permitido:8080"` y la entrada permitida de `atareao_opengist_allowed_hosts` (o `atareao_opengist_server`) declara `https://host-permitido` sin puerto
- **THEN** el sistema no considera permitido el host con ese puerto e ignora el atributo, usando el servidor por defecto

### Requirement: Ausencia de script externo de host no permitido

En el fallback de renderizado, el sistema SHALL emitir la etiqueta `<script src="…">` únicamente cuando el host del script sea un host permitido. Si el host no está permitido, el sistema SHALL NOT emitir ninguna etiqueta `<script>` con origen externo y SHALL mostrar un aviso comprensible (o únicamente el contenido obtenido en el servidor, si lo hubiera). La URL del script SHALL construirse a partir del servidor efectivo ya validado y nunca a partir del atributo sin validar.

#### Scenario: Fallback con host permitido

- **WHEN** el servidor efectivo tiene un host permitido y no se pudo obtener ningún fichero por servidor
- **THEN** el sistema emite el `<script src>` apuntando a ese host permitido

#### Scenario: Fallback con host no permitido

- **WHEN** el servidor efectivo resultara ser un host no permitido
- **THEN** el sistema no emite ningún `<script>` externo y muestra un aviso en su lugar

#### Scenario: Script del mismo sitio permitido

- **WHEN** el servidor efectivo es el propio sitio y ese host está en la lista permitida
- **THEN** el sistema puede emitir el `<script src>` del propio origen

### Requirement: Protección SSRF en las peticiones salientes

El sistema SHALL construir las URLs de petición a partir del servidor efectivo validado, escapando `username` y `gist_id` con `rawurlencode` antes de concatenar la ruta. El sistema SHALL realizar las dos peticiones salientes (script de embed y contenido raw) con `wp_safe_remote_get`, SHALL fijar `redirection => 0` y SHALL acotar el `timeout`. El sistema SHALL NOT usar `wp_remote_get` ni permitir que una redirección lleve la petición a un host distinto del permitido. El sistema SHALL rechazar la petición cuando el host del servidor efectivo no esté permitido, sin llegar a contactarlo.

#### Scenario: Peticiones seguras al host permitido

- **WHEN** el servidor efectivo tiene un host permitido
- **THEN** el sistema pide el script y el raw con `wp_safe_remote_get`, `redirection => 0` y un `timeout` acotado

#### Scenario: Intento de SSRF a host interno

- **WHEN** el atributo del bloque apunta a `http://127.0.0.1`, `http://169.254.169.254` o un host no permitido
- **THEN** el sistema no realiza ninguna petición a ese host y cae al servidor por defecto

#### Scenario: Redirección a otro host

- **WHEN** el host permitido responde con una redirección hacia otro host
- **THEN** el sistema no sigue la redirección (`redirection => 0`) y descarta la respuesta

#### Scenario: Ruta con caracteres especiales

- **WHEN** `username` o `gist_id` contienen caracteres que deben escaparse
- **THEN** el sistema los escapa con `rawurlencode` al construir la URL

### Requirement: Compatibilidad con los bloques ya existentes

El sistema SHALL seguir renderizando los bloques ya publicados cuyo host efectivo esté permitido, sin cambiar el HTML resultante ni el nombre, el saneado o el default de las opciones existentes `atareao_opengist_server` y `atareao_opengist_username`. El sistema SHALL mantener el contenido obtenido por servidor (renderizado propio) como camino principal y el fallback como respaldo. Cuando el host efectivo no esté permitido, el sistema SHALL degradar a un aviso sin provocar errores fatales ni entradas en blanco. El atributo `server` del bloque SHALL conservarse por compatibilidad y su validación SHALL aplicarse tanto en el frontend como en la vista previa del editor.

#### Scenario: Bloque legítimo sin cambios

- **WHEN** una entrada ya publicada usa el servidor por defecto (o un atributo con host permitido) con un gist público válido
- **THEN** el bloque se renderiza igual que antes de este cambio

#### Scenario: Opciones existentes intactas

- **WHEN** se actualiza el plugin
- **THEN** `atareao_opengist_server` y `atareao_opengist_username` conservan su nombre, su saneado (`esc_url_raw` y `sanitize_text_field`) y su default `''`

#### Scenario: Degradación sin error fatal

- **WHEN** el host efectivo no está permitido
- **THEN** el bloque muestra un aviso en lugar del gist y el resto de la página se sirve con normalidad

#### Scenario: Editor coherente con el frontend

- **WHEN** el editor construye la vista previa con el atributo `server`
- **THEN** solo se honra si el host está permitido, igual que en el frontend
