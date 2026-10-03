# opengist-block Delta

## MODIFIED Requirements

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
