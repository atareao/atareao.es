# mcp-discovery Specification

## Purpose
Esta capability define el **descubrimiento por dominio** del servidor MCP público que `atareao-functionality` expone. Responde a una pregunta previa a cualquier intercambio de protocolo: *¿dónde se conecta un cliente MCP en este dominio?* Consta de dos documentos JSON —el **catálogo AI** y la **tarjeta de servidor**— y de las rutas y cabeceras que los sirven. El catálogo se publica en la URL conocida del dominio y apunta a la tarjeta; la tarjeta declara la identidad del servidor y el endpoint al que conectarse. Ambos se sirven desde el plugin (no desde ficheros estáticos del webroot), con media types específicos, CORS de solo lectura, caché pública y `ETag`, y sin exponer credenciales ni enumerar primitives.

## Requirements

### Requirement: Catálogo AI en `/.well-known/ai-catalog.json`

El sistema SHALL servir `GET /.well-known/ai-catalog.json` con `Content-Type: application/ai-catalog+json`, sin exigir autenticación, devolviendo un documento válido según el estándar AI Catalog: un objeto JSON con `specVersion` (cadena en formato «Major.Minor») y `entries` (array). El documento SHALL contener **exactamente una** entrada para el servidor MCP, con `identifier` (un identificador estable, recomendado `urn:air:…`), `type` igual a `application/mcp-server-card+json` y **exactamente uno** de `url` o `data` — en este caso `url`, con la URL **absoluta** de la tarjeta en el propio dominio. El documento SHALL NOT contener credenciales, tokens ni datos internos.

#### Scenario: El catálogo se sirve con su media type

- **WHEN** un cliente solicita `GET /.well-known/ai-catalog.json`
- **THEN** el sistema responde `200` con `Content-Type: application/ai-catalog+json`

#### Scenario: El catálogo es válido y acotado

- **WHEN** un cliente valida el documento contra el estándar AI Catalog
- **THEN** el documento declara `specVersion` y `entries`, y contiene exactamente una entrada

#### Scenario: La entrada apunta a la tarjeta del propio dominio

- **WHEN** se inspecciona la entrada del catálogo
- **THEN** su `type` es `application/mcp-server-card+json` y su `url` es la URL absoluta de la tarjeta de servidor

#### Scenario: La entrada usa `url` y no incluye credenciales

- **WHEN** se inspecciona la entrada del catálogo
- **THEN** la entrada declara `url` (no `data`) y no aparece ninguna credencial, token ni dato interno

#### Scenario: El catálogo es accesible sin sesión

- **WHEN** un cliente anónimo solicita el catálogo
- **THEN** el sistema lo sirve sin exigir autenticación

### Requirement: Tarjeta de servidor en `GET /mcp/server-card`

El sistema SHALL servir `GET /mcp/server-card` con `Content-Type: application/mcp-server-card+json`, sin exigir autenticación, devolviendo un documento que conforme el esquema de MCP Server Cards (SEP-2127): `$schema` con el valor **literal** `https://static.modelcontextprotocol.io/schemas/v1/server-card.schema.json`; `name` en notación de dominio inverso con **exactamente un** `/` y conforme a la regex `^[a-zA-Z0-9.-]+/[a-zA-Z0-9._-]+$`; `version` en semver (sin rangos); `description` de longitud ≤100 caracteres; y `remotes[]` donde cada entrada declara `type` (uno de `sse`, `streamable-http`) y `url` (URL absoluta del endpoint) e incluye `supportedProtocolVersions` con las versiones de protocolo soportadas. La tarjeta SHALL declarar un `remote` de tipo `streamable-http` cuya `url` sea el endpoint limpio `/mcp` del sitio. `supportedProtocolVersions` SHALL proceder de la **misma fuente de verdad** que la negociación del servidor, de modo que la tarjeta no pueda anunciar una versión que el servidor no cumple. La tarjeta SHALL NOT contener credenciales ni enumerar primitives (herramientas, recursos o prompts).

#### Scenario: La tarjeta se sirve con su media type

- **WHEN** un cliente solicita `GET /mcp/server-card`
- **THEN** el sistema responde `200` con `Content-Type: application/mcp-server-card+json`

#### Scenario: `$schema` es el literal obligatorio

- **WHEN** se inspecciona la tarjeta
- **THEN** `$schema` vale exactamente `https://static.modelcontextprotocol.io/schemas/v1/server-card.schema.json`

#### Scenario: El nombre es un identificador de dominio inverso válido

- **WHEN** se valida `name` contra la regex del esquema
- **THEN** el nombre tiene exactamente un `/` entre el dominio invertido y el nombre del artefacto y cumple la regex

#### Scenario: La descripción y la versión cumplen sus restricciones

- **WHEN** se inspeccionan `description` y `version`
- **THEN** la descripción no supera 100 caracteres y la versión es una versión semántica válida

#### Scenario: El endpoint declarado es el limpio

- **WHEN** se inspecciona `remotes[]`
- **THEN** existe una entrada `type: streamable-http` cuya `url` es el endpoint limpio `/mcp` del sitio

#### Scenario: Las versiones anunciadas son las cumplidas

- **WHEN** se comparan las `supportedProtocolVersions` de la tarjeta con las versiones que el servidor negocia y cumple
- **THEN** ambas listas coinciden exactamente, sin anunciar ninguna versión no soportada

#### Scenario: La tarjeta no expone credenciales ni primitives

- **WHEN** se inspecciona la tarjeta
- **THEN** no contiene credenciales, tokens ni datos internos, y no enumera herramientas, recursos ni prompts

#### Scenario: La tarjeta es accesible sin sesión

- **WHEN** un cliente anónimo solicita la tarjeta
- **THEN** el sistema la sirve sin exigir autenticación

### Requirement: Cabeceras HTTP de los artefactos de descubrimiento

Las respuestas de `/.well-known/ai-catalog.json` y `/mcp/server-card` SHALL incluir cabeceras CORS de solo lectura: `Access-Control-Allow-Origin: *`, `Access-Control-Allow-Methods: GET`, `Access-Control-Allow-Headers` que admita al menos `Content-Type` e `If-None-Match`, y `Access-Control-Expose-Headers: ETag`. Ambas SHALL incluir `Cache-Control: public, max-age=3600` y un `ETag`; ante una petición con `If-None-Match` que coincida con el `ETag` vigente, el sistema SHALL responder `304 Not Modified` sin cuerpo. Cada artefacto SHALL declarar su media type específico (`application/ai-catalog+json` y `application/mcp-server-card+json`) y no `application/json` genérico. En producción los dos artefactos SHALL servirse por HTTPS.

#### Scenario: CORS de solo lectura

- **WHEN** una respuesta de un artefacto de descubrimiento se inspecciona
- **THEN** incluye `Access-Control-Allow-Origin: *`, admite el método `GET`, admite `If-None-Match` y expone `ETag`

#### Scenario: Caché pública y ETag

- **WHEN** se inspecciona la respuesta de un artefacto
- **THEN** incluye `Cache-Control: public, max-age=3600` y un `ETag`

#### Scenario: Revalidación con `304`

- **WHEN** un cliente repite la petición con `If-None-Match` igual al `ETag` vigente
- **THEN** el sistema responde `304 Not Modified` sin cuerpo

#### Scenario: Media type específico por artefacto

- **WHEN** se inspecciona el `Content-Type` de cada artefacto
- **THEN** el catálogo declara `application/ai-catalog+json` y la tarjeta `application/mcp-server-card+json`, no `application/json`

### Requirement: Servido por el plugin sin depender de ficheros estáticos

Los dos artefactos de descubrimiento SHALL ser servidos por el plugin `atareao-functionality`, no por ficheros colocados en el webroot del contenedor (que no está versionado en el repositorio ni se empaqueta con el build). Sus rutas SHALL resolverse aunque las reglas de reescritura no se hayan vaciado en una instalación ya activa. El servido SHALL NOT exigir autenticación, SHALL NOT escribir ficheros en el webroot y SHALL NOT exponer datos internos del servidor. El proxy inverso del sitio SHALL enrutar `/.well-known/` hacia WordPress, de modo que una política genérica de denegación de rutas con directorio oculto no bloquee el catálogo.

#### Scenario: La ruta la sirve el plugin

- **WHEN** se solicita uno de los dos artefactos
- **THEN** la respuesta la genera el plugin y no depende de un fichero estático del webroot

#### Scenario: Disponible sin vaciado de reescrituras

- **WHEN** se solicita un artefacto en una instalación cuyas reglas de reescritura no se han vaciado
- **THEN** el sistema responde el artefacto igualmente

#### Scenario: Sin escritura en el webroot

- **WHEN** se sirve un artefacto
- **THEN** el plugin no crea ni modifica ningún fichero en el webroot ni en el sistema de ficheros

#### Scenario: La ruta del catálogo llega al plugin

- **WHEN** un cliente solicita `GET /.well-known/ai-catalog.json` a través del proxy inverso del sitio
- **THEN** la petición alcanza a WordPress y el plugin sirve el catálogo, sin que el proxy la deniegue por ser un directorio oculto
