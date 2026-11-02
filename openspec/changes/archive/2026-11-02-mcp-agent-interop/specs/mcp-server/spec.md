# MCP Server Delta

## MODIFIED Requirements

### Requirement: Selección de era y conformidad del transporte Streamable HTTP

El servidor SHALL seleccionar la era por la forma de la petición: una petición con `initialize` selecciona la **era legacy**; una petición selecciona la **era moderna** si y solo si `params._meta` contiene la clave `io.modelcontextprotocol/protocolVersion`. Las cabeceras `Mcp-Method` y `MCP-Protocol-Version` SHALL NOT seleccionar la era: el SDK oficial de MCP las envía también en el dialecto 2025 posterior al `initialize`, de modo que tomarlas por un marcador de era envía a los clientes legacy reales a la era moderna —que exige metadatos por petición y responde `-32602`—; una petición que presente `Mcp-Method` sin metadatos de protocolo SHALL servirse por la **era legacy**. La mera presencia de `params._meta` SHALL NOT seleccionar la era moderna, porque `_meta` es un campo general del protocolo que también envían los clientes legacy (por ejemplo `_meta.progressToken`). En la **era moderna** el servidor SHALL: aceptar `POST` con un único mensaje JSON-RPC (petición o notificación); leer de `params._meta` (el campo general `_meta` situado dentro de `params`) el campo `io.modelcontextprotocol/protocolVersion`, obligatorio, y el campo `io.modelcontextprotocol/clientCapabilities`, opcional (si falta se trata como un objeto vacío), y, si falta `protocolVersion`, servir la petición por la era legacy sin responder `-32602`; rechazar una versión no soportada con `UnsupportedProtocolVersionError` (`-32022`) y HTTP `400`, listando en `data.supported` las versiones soportadas y en `data.requested` la solicitada; validar las cabeceras `MCP-Protocol-Version` (obligatoria y coincidente con `_meta`), `Mcp-Method` (obligatoria y coincidente con `method`) y `Mcp-Name` (obligatoria en `tools/call` y coincidente con `params.name`), respondiendo `HeaderMismatch` (`-32020`) y HTTP `400` si alguna falta o no coincide —comparando nombres de cabecera sin distinguir mayúsculas y decodificando los valores que usen el sentinel Base64 antes de comparar—; incluir `resultType` con valor `"complete"` en todo resultado; incluir `_meta.io.modelcontextprotocol/serverInfo` en todo resultado; implementar `server/discover` devolviendo en el resultado `supportedVersions` (las versiones soportadas), `capabilities`, `ttlMs` y `cacheScope`, y la identidad del servidor en `_meta.io.modelcontextprotocol/serverInfo` sin duplicarla en el nivel superior del resultado; incluir `ttlMs` y `cacheScope` en los resultados de `tools/list` y determinismo en el orden de las herramientas; responder `202 Accepted` sin cuerpo ante una notificación aceptada; responder `-32601` con HTTP `404` ante un método no implementado; y responder `405` a `GET`/`DELETE`. En la **era legacy** el servidor SHALL seguir respondiendo `initialize` con `protocolVersion`, `serverInfo` y `capabilities`, negociando la versión (la mejor versión soportada no superior a la ofrecida por el cliente; si el cliente no la indica, la más baja soportada) y manteniendo `tools/list` y `tools/call`; SHALL aceptar `notifications/initialized` y, en general, SHALL responder `202 Accepted` sin cuerpo ante cualquier mensaje sin `id` (notificación), sin emitir ningún error JSON-RPC —una notificación no puede recibir respuesta—. La validación de los metadatos obligatorios y de las cabeceras de la era moderna SHALL preceder a la aceptación del mensaje: una notificación moderna que presente cabeceras obligatorias ausentes o discordantes SHALL recibir el error correspondiente (`-32020`) y no `202 Accepted`. El servidor SHALL NOT crear sesiones ni emitir `Mcp-Session-Id`. Las versiones de protocolo soportadas y anunciadas SHALL salir de una **única fuente de verdad**. El rate limiting, la visibilidad del contenido y la validación de argumentos SHALL mantenerse sin cambios.

#### Scenario: Petición moderna servida

- **WHEN** un cliente envía una petición con `_meta` de protocolo, las cabeceras obligatorias y una versión soportada
- **THEN** el sistema la atiende y devuelve un resultado con `resultType` y `serverInfo`

#### Scenario: Metadatos obligatorios ausentes

- **WHEN** una petición omite `io.modelcontextprotocol/protocolVersion` en `params._meta`
- **THEN** el sistema la atiende por la era legacy y responde `200` con el resultado del método (o `202` si es una notificación), sin responder `-32602`

#### Scenario: Versión de protocolo no soportada

- **WHEN** una petición declara una versión que el servidor no soporta
- **THEN** el sistema responde `UnsupportedProtocolVersionError` (`-32022`) con HTTP `400`, listando las versiones soportadas y la solicitada

#### Scenario: Cabecera obligatoria ausente o discordante

- **WHEN** una petición moderna omite `MCP-Protocol-Version`, `Mcp-Method` o `Mcp-Name`, o alguno no coincide con el cuerpo
- **THEN** el sistema responde `HeaderMismatch` (`-32020`) con HTTP `400`

#### Scenario: Comparación de valores codificados

- **WHEN** `Mcp-Name` llega codificado con el sentinel Base64 y, decodificado, coincide con `params.name`
- **THEN** el sistema acepta la cabecera y no responde `HeaderMismatch`

#### Scenario: `params.name` no textual

- **WHEN** una petición moderna invoca `tools/call` con un `params.name` que no es una cadena
- **THEN** el sistema responde `-32602` (Invalid params) con HTTP `400`, sin convertir el parámetro a cadena ni compararlo con la cabecera `Mcp-Name`

#### Scenario: `server/discover` conforme al esquema

- **WHEN** un cliente moderno invoca `server/discover`
- **THEN** el resultado declara las versiones soportadas en el campo `supportedVersions` (y **no** en `supportedProtocolVersions`, que es el nombre del campo de la tarjeta), declara `capabilities` e `ttlMs`/`cacheScope`, lleva `resultType` con valor `complete` y sitúa la identidad del servidor en `_meta.io.modelcontextprotocol/serverInfo`

#### Scenario: Los listados son cacheables

- **WHEN** un cliente invoca `tools/list`
- **THEN** el resultado incluye `ttlMs` y `cacheScope` además de la lista de herramientas

#### Scenario: Notificación aceptada

- **WHEN** una petición moderna contiene una notificación (sin `id`)
- **THEN** el sistema responde `202 Accepted` sin cuerpo

#### Scenario: Método no implementado

- **WHEN** una petición moderna invoca un método que el servidor no implementa
- **THEN** el sistema responde `-32601` con HTTP `404`

#### Scenario: La era legacy sigue funcionando

- **WHEN** un cliente envía `initialize` con su versión
- **THEN** el sistema responde con `protocolVersion`, `serverInfo` y `capabilities`, y las llamadas posteriores a `tools/list` y `tools/call` siguen operativas

#### Scenario: Negociación de versión en la era legacy

- **WHEN** un cliente legacy ofrece una versión soportada
- **THEN** el sistema responde una versión soportada que no supera la ofrecida; si el cliente no la indica, responde la más baja soportada

#### Scenario: Sin sesiones

- **WHEN** el sistema atiende cualquier petición del transporte
- **THEN** no crea sesión ni emite `Mcp-Session-Id`

#### Scenario: No hay dos listas de versiones

- **WHEN** se comparan las versiones usadas en la negociación y en `server/discover`
- **THEN** ambas proceden de la misma fuente y son idénticas

#### Scenario: Cliente legacy con `params._meta` genérico

- **WHEN** una petición legacy —sin `io.modelcontextprotocol/protocolVersion` en `params._meta`— incluye `params._meta` con claves ajenas al protocolo (por ejemplo `progressToken`)
- **THEN** el sistema la atiende por la **era legacy** y responde `200` con el resultado del método, sin responder `-32602`

#### Scenario: La cabecera `Mcp-Method` no selecciona la era moderna

- **WHEN** una petición presenta la cabecera `Mcp-Method` sin `io.modelcontextprotocol/protocolVersion` en `params._meta`
- **THEN** el sistema la atiende por la **era legacy** y responde `200` con el resultado del método (o `202` si es una notificación), y nunca `-32602`

#### Scenario: Cliente del SDK oficial tras negociar 2025-06-18

- **WHEN** un cliente envía `initialize` con una versión que se negocia a `2025-06-18` y después envía `notifications/initialized` —sin `params`— con las cabeceras `MCP-Protocol-Version: 2025-06-18` y `Mcp-Method: notifications/initialized`
- **THEN** el sistema la acepta con `202 Accepted` sin cuerpo y la sesión de trabajo continúa: `tools/list` con esas mismas cabeceras responde `200` con las herramientas

#### Scenario: Notificación en la era legacy

- **WHEN** una petición legacy contiene un mensaje sin `id` (por ejemplo `notifications/initialized`)
- **THEN** el sistema responde `202 Accepted` sin cuerpo y SHALL NOT responder ningún error JSON-RPC

#### Scenario: Notificación moderna con metadatos incompletos

- **WHEN** una petición moderna (con `io.modelcontextprotocol/protocolVersion` en `params._meta`) contiene una notificación (sin `id`) que omite `io.modelcontextprotocol/clientCapabilities`
- **THEN** el sistema responde `202 Accepted` sin cuerpo, porque `clientCapabilities` es opcional

#### Scenario: Capacidades del cliente opcionales

- **WHEN** una petición moderna declara `io.modelcontextprotocol/protocolVersion` en `params._meta` pero omite `io.modelcontextprotocol/clientCapabilities`
- **THEN** el sistema la atiende y responde `200` con el resultado del método, sin responder `-32602`

