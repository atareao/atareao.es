# MCP Server Delta

## Purpose

Este delta amplía la capability `mcp-server` con dos piezas que el anuncio del servidor necesita para ser **verdadero**: un **endpoint limpio `/mcp`** que es alias del transporte ya existente (sin mudar la ruta REST, que se conserva), y la **conformidad dual-era de Streamable HTTP** — se conserva el handshake `initialize` para los clientes que hoy funcionan y se añade la era moderna stateless (`2026-07-28`), en la que cada petición declara su versión y capacidades en `_meta`, todo resultado lleva `resultType` y `server/discover` es obligatorio. Se amplía también el CORS del endpoint a las cabeceras del protocolo. La lógica de las herramientas de consulta, la visibilidad del contenido, la validación de argumentos y el rate limiting no cambian de comportamiento.

## ADDED Requirements

### Requirement: Endpoint limpio `/mcp` como alias del transporte

El sistema SHALL aceptar el transporte MCP en la ruta limpia `/mcp` para los métodos `POST` (mensaje JSON-RPC) y `OPTIONS` (preflight), atendiendo **exactamente la misma lógica** que la ruta REST `atareao/v1/mcp`. La ruta REST `/wp-json/atareao/v1/mcp` SHALL conservarse como ruta interna/legacy, de modo que el cliente MCP ya registrado siga funcionando. Ambas rutas SHALL servirse desde una **única implementación**, sin duplicar la lógica del transporte. Un `GET` o `DELETE` sobre `/mcp` SHALL responder `405 Method Not Allowed` —un `GET` al endpoint desnudo está reservado en Streamable HTTP y servir ahí la tarjeta está desaconsejado—. La ruta de la tarjeta se deriva por convención como `/mcp/server-card`. La ruta limpia `/mcp` SHALL ser el **único endpoint anunciado**: el meta de descubrimiento que el plugin emite en `wp_head` y la documentación pública SHALL apuntar a `/mcp`, y la ruta REST `/wp-json/atareao/v1/mcp` SHALL NOT anunciarse en ningún sitio (meta, documentación, tarjeta o catálogo), aunque siga operativa.

#### Scenario: El alias atiende peticiones POST

- **WHEN** se envía un `POST` JSON-RPC a `/mcp`
- **THEN** el sistema atiende la petición con la misma lógica que la ruta REST `atareao/v1/mcp`

#### Scenario: La ruta legacy sigue operativa

- **WHEN** se envía un `POST` JSON-RPC a `/wp-json/atareao/v1/mcp`
- **THEN** el sistema atiende la petición como antes de este cambio

#### Scenario: Una sola implementación

- **WHEN** se compara el comportamiento de `/mcp` y de `/wp-json/atareao/v1/mcp`
- **THEN** ambas rutas sirven el mismo handler y no existen dos implementaciones del transporte

#### Scenario: `GET` sobre el endpoint no devuelve la tarjeta

- **WHEN** se envía un `GET` a `/mcp`
- **THEN** el sistema responde `405 Method Not Allowed` y no sirve la tarjeta

#### Scenario: El alias no abre métodos con efectos

- **WHEN** se envía un método con efectos que no sea el `POST` del transporte
- **THEN** el sistema no lo atiende como operación del transporte MCP

#### Scenario: El endpoint anunciado es el limpio

- **WHEN** se inspeccionan el meta de descubrimiento emitido en `wp_head` y la documentación pública del plugin
- **THEN** ambos apuntan a la ruta limpia `/mcp` y ninguno apunta a la ruta REST `/wp-json/atareao/v1/mcp`

#### Scenario: La ruta legacy no se anuncia pero sigue operativa

- **WHEN** se envía un `POST` JSON-RPC a `/wp-json/atareao/v1/mcp`
- **THEN** el sistema lo atiende con normalidad, aunque esa ruta no aparezca anunciada en ningún sitio

### Requirement: Conformidad dual-era de Streamable HTTP

El servidor SHALL seleccionar la era por la forma de la petición: una petición con `initialize` selecciona la **era legacy**; una petición que declare metadatos de protocolo por petición selecciona la **era moderna**. En la **era moderna** el servidor SHALL: aceptar `POST` con un único mensaje JSON-RPC (petición o notificación); leer de `params._meta` (el campo general `_meta` situado dentro de `params`) los campos `io.modelcontextprotocol/protocolVersion` y `io.modelcontextprotocol/clientCapabilities` y rechazar la petición con `-32602` (Invalid params) y HTTP `400` si falta cualquiera de los obligatorios; rechazar una versión no soportada con `UnsupportedProtocolVersionError` (`-32022`) y HTTP `400`, listando en `data.supported` las versiones soportadas y en `data.requested` la solicitada; validar las cabeceras `MCP-Protocol-Version` (obligatoria y coincidente con `_meta`), `Mcp-Method` (obligatoria y coincidente con `method`) y `Mcp-Name` (obligatoria en `tools/call` y coincidente con `params.name`), respondiendo `HeaderMismatch` (`-32020`) y HTTP `400` si alguna falta o no coincide —comparando nombres de cabecera sin distinguir mayúsculas y decodificando los valores que usen el sentinel Base64 antes de comparar—; incluir `resultType` con valor `"complete"` en todo resultado; incluir `_meta.io.modelcontextprotocol/serverInfo` en todo resultado; implementar `server/discover` devolviendo en el resultado `supportedVersions` (las versiones soportadas), `capabilities`, `ttlMs` y `cacheScope`, y la identidad del servidor en `_meta.io.modelcontextprotocol/serverInfo` sin duplicarla en el nivel superior del resultado; incluir `ttlMs` y `cacheScope` en los resultados de `tools/list` y determinismo en el orden de las herramientas; responder `202 Accepted` sin cuerpo ante una notificación aceptada; responder `-32601` con HTTP `404` ante un método no implementado; y responder `405` a `GET`/`DELETE`. En la **era legacy** el servidor SHALL seguir respondiendo `initialize` con `protocolVersion`, `serverInfo` y `capabilities`, negociando la versión (la mejor versión soportada no superior a la ofrecida por el cliente; si el cliente no la indica, la más baja soportada) y manteniendo `tools/list` y `tools/call`. El servidor SHALL NOT crear sesiones ni emitir `Mcp-Session-Id`. Las versiones de protocolo soportadas y anunciadas SHALL salir de una **única fuente de verdad**. El rate limiting, la visibilidad del contenido y la validación de argumentos SHALL mantenerse sin cambios.

#### Scenario: Petición moderna servida

- **WHEN** un cliente envía una petición con `_meta` de protocolo, las cabeceras obligatorias y una versión soportada
- **THEN** el sistema la atiende y devuelve un resultado con `resultType` y `serverInfo`

#### Scenario: Metadatos obligatorios ausentes

- **WHEN** una petición moderna omite `io.modelcontextprotocol/protocolVersion` o `io.modelcontextprotocol/clientCapabilities`
- **THEN** el sistema responde `-32602` con HTTP `400`

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

## MODIFIED Requirements

### Requirement: CORS para la consulta anónima

Al ser un servicio público de solo lectura, el endpoint SHALL responder con cabeceras CORS que permitan la consulta anónima **desde cualquier origen** para el método de lectura (`POST`), sin exigir credenciales: `Access-Control-Allow-Origin` SHALL admitir cualquier origen y `Access-Control-Allow-Credentials` SHALL NOT habilitarse (no hay credenciales que enviar). El endpoint SHALL resolver la petición de preflight `OPTIONS` con los métodos permitidos (`POST`, `OPTIONS`) y con las cabeceras permitidas **mínimas y necesarias**, que SHALL incluir al menos `Content-Type`, `Accept`, `MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name` y `Authorization`, y SHALL NOT permitir métodos con efectos. Si en el futuro se decidiera restringir el origen, la restricción SHALL quedar escrita y razonada antes de aplicarse.

#### Scenario: Consulta anónima desde un origen distinto

- **WHEN** un cliente anónimo consulta el endpoint desde un origen distinto
- **THEN** la respuesta incluye `Access-Control-Allow-Origin` que admite ese origen y no habilita credenciales

#### Scenario: Preflight `OPTIONS`

- **WHEN** el navegador envía una petición de preflight `OPTIONS`
- **THEN** el sistema responde con los métodos permitidos (`POST`, `OPTIONS`) y las cabeceras mínimas necesarias, incluidas las del protocolo MCP

#### Scenario: Cabeceras del protocolo admitidas

- **WHEN** un cliente MCP envía `MCP-Protocol-Version`, `Mcp-Method` o `Mcp-Name`
- **THEN** el preflight las admite como cabeceras permitidas

#### Scenario: Sin métodos con efectos

- **WHEN** se intenta invocar el endpoint con un método que no sea de consulta
- **THEN** el sistema no permite métodos con efectos en CORS
