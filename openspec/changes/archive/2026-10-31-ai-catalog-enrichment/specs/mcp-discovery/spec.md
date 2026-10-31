# mcp-discovery — Delta

## MODIFIED Requirements

### Requirement: Catálogo AI en `/.well-known/ai-catalog.json`

El sistema SHALL servir `GET /.well-known/ai-catalog.json` con `Content-Type: application/ai-catalog+json`, sin exigir autenticación, devolviendo un documento válido según el estándar AI Catalog: un objeto JSON con `specVersion` (cadena en formato «Major.Minor») y `entries` (array). El documento SHALL contener la entrada del servidor MCP, cuyo `identifier` SHALL ser estable (`urn:air:…`), cuyo `type` SHALL ser `application/mcp-server-card+json` y que SHALL declarar **exactamente uno** de `url` o `data` — en este caso `url`, con la URL **absoluta** de la tarjeta en el propio dominio. El documento MAY contener entradas adicionales para otros recursos del dominio; toda entrada adicional SHALL cumplir la misma regla de `url` **XOR** `data` y SHALL referenciar un artefacto que exista realmente en el dominio. El documento SHALL NOT contener credenciales, tokens ni datos internos.

Esta ruta es la **predecesora** de la ruta canónica del estándar ARD. SHALL seguir sirviéndose para no romper a los consumidores que solo la conocen, y SHALL derivarse de la **misma fuente de verdad** que `/.well-known/ard.json`, sirviendo **las mismas entradas completas** —incluido `displayName`—, de modo que el contenido de ambas no pueda divergir y la ruta predecesora sea también un ARD entry válido para un consumidor que la trate como origen equivalente.

#### Scenario: El catálogo se sirve con su media type

- **WHEN** un cliente solicita `GET /.well-known/ai-catalog.json`
- **THEN** el sistema responde `200` con `Content-Type: application/ai-catalog+json`

#### Scenario: El catálogo es válido y acotado

- **WHEN** un cliente valida el documento contra el estándar AI Catalog
- **THEN** el documento declara `specVersion` y `entries`, y contiene al menos la entrada del servidor MCP

#### Scenario: La entrada apunta a la tarjeta del propio dominio

- **WHEN** se inspecciona la entrada del servidor MCP
- **THEN** su `type` es `application/mcp-server-card+json` y su `url` es la URL absoluta de la tarjeta de servidor

#### Scenario: La entrada usa `url` y no incluye credenciales

- **WHEN** se inspecciona la entrada del servidor MCP
- **THEN** la entrada declara `url` (no `data`) y no aparece ninguna credencial, token ni dato interno

#### Scenario: El catálogo es accesible sin sesión

- **WHEN** un cliente anónimo solicita el catálogo
- **THEN** el sistema lo sirve sin exigir autenticación

#### Scenario: Las dos rutas del manifiesto no pueden divergir

- **WHEN** se comparan las entradas de `/.well-known/ai-catalog.json` y de `/.well-known/ard.json`
- **THEN** ambas proceden de la misma fuente de verdad y sirven entradas idénticas campo a campo (no solo el mismo conjunto de identificadores)

### Requirement: Cabeceras HTTP de los artefactos de descubrimiento

Las respuestas de `/.well-known/ard.json`, `/.well-known/ai-catalog.json` y `/mcp/server-card` SHALL incluir cabeceras CORS de solo lectura: `Access-Control-Allow-Origin: *`, `Access-Control-Allow-Methods: GET`, `Access-Control-Allow-Headers` que admita al menos `Content-Type` e `If-None-Match`, y `Access-Control-Expose-Headers: ETag`. Todas ellas SHALL incluir `Cache-Control: public, max-age=3600` y un `ETag`; ante una petición con `If-None-Match` que coincida con el `ETag` vigente, el sistema SHALL responder `304 Not Modified` sin cuerpo. Cada artefacto SHALL declarar su media type específico —`application/ai-catalog+json` la ruta predecesora y `application/mcp-server-card+json` la tarjeta— y no `application/json` genérico; el manifiesto ARD, cuyo media type el estándar no prescribe, SHALL servirse como `application/json`. En producción los artefactos SHALL servirse por HTTPS.

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
- **THEN** la ruta predecesora declara `application/ai-catalog+json`, el manifiesto ARD `application/json` y la tarjeta `application/mcp-server-card+json`

### Requirement: Servido por el plugin sin depender de ficheros estáticos

Los artefactos de descubrimiento —las dos rutas del manifiesto (`ard.json` y su predecesora) y la tarjeta de servidor— SHALL ser servidos por el plugin `atareao-functionality`, no por ficheros colocados en el webroot del contenedor (que no está versionado en el repositorio ni se empaqueta con el build). Sus rutas SHALL resolverse aunque las reglas de reescritura no se hayan vaciado en una instalación ya activa. El servido SHALL NOT exigir autenticación, SHALL NOT escribir ficheros en el webroot y SHALL NOT exponer datos internos del servidor. El proxy inverso del sitio SHALL enrutar `/.well-known/` hacia WordPress, de modo que una política genérica de denegación de rutas con directorio oculto no bloquee ningún artefacto de descubrimiento.

#### Scenario: La ruta la sirve el plugin

- **WHEN** se solicita uno cualquiera de los artefactos de descubrimiento
- **THEN** la respuesta la genera el plugin y no depende de un fichero estático del webroot

#### Scenario: Disponible sin vaciado de reescrituras

- **WHEN** se solicita un artefacto en una instalación cuyas reglas de reescritura no se han vaciado
- **THEN** el sistema responde el artefacto igualmente

#### Scenario: Sin escritura en el webroot

- **WHEN** se sirve un artefacto
- **THEN** el plugin no crea ni modifica ningún fichero en el webroot ni en el sistema de ficheros

#### Scenario: La ruta del catálogo llega al plugin

- **WHEN** un cliente solicita `GET /.well-known/ard.json` o `GET /.well-known/ai-catalog.json` a través del proxy inverso del sitio
- **THEN** la petición alcanza a WordPress y el plugin sirve el artefacto, sin que el proxy la deniegue por ser un directorio oculto

## ADDED Requirements

### Requirement: Manifiesto ARD en `/.well-known/ard.json`

El sistema SHALL servir `GET /.well-known/ard.json`, sin exigir autenticación, devolviendo un **manifiesto ARD**: un objeto JSON con un array `entries` cuyos elementos son **entradas ARD**. Cada entrada SHALL declarar `identifier` —con la forma del URN anclado al dominio, `urn:air:<dominio>:<namespace>:<nombre>`—, **`displayName`**, `type` y `url` (o `data`), y SHALL declarar **`representativeQueries`** con entre 2 y 5 consultas en lenguaje natural. `identifier`, `displayName` y `type` son los términos `required` del esquema formal del ARD entry, y `url` XOR `data` es su `oneOf`; `representativeQueries` es una expectativa del estándar que su herramienta de conformidad señala como *warning* —no como fallo duro— si falta o si su número queda fuera de 2–5. Cada entrada SHOULD incluir `@context` con el valor exacto `https://agenticresourcediscovery.org/context/v1`.

Los `representativeQueries` SHALL formularse como la necesidad de quien busca (por ejemplo «qué artículos hay sobre Podman»), SHALL NOT ser nombres de funciones, endpoints ni identificadores internos, y SHALL estar en español.

Esta ruta es la **canónica** del estándar y es la que un consumidor conforme está obligado a consultar. La entrada sin `@context` sigue siendo válida —una entrada publicada contra el formato predecesor se interpreta con el contexto base sin cambios— pero declararlo es la recomendación del estándar.

El manifiesto SHALL NOT contener credenciales, tokens ni datos internos, y SHALL NOT enumerar las *primitives* del servidor MCP (herramientas, recursos o prompts) más allá de lo que ya declara la tarjeta de servidor.

#### Scenario: El manifiesto se sirve en la ruta canónica

- **WHEN** un cliente solicita `GET /.well-known/ard.json`
- **THEN** el sistema responde `200` con un objeto JSON que contiene un array `entries`

#### Scenario: La entrada es descubrible por ARD

- **WHEN** se valida la entrada del servidor MCP contra las expectativas de un ARD entry
- **THEN** la entrada declara `representativeQueries` con entre 2 y 5 consultas en lenguaje natural

#### Scenario: La entrada declara los términos obligatorios del ARD entry

- **WHEN** se valida la entrada del servidor MCP contra el esquema formal del ARD entry
- **THEN** declara `identifier` con forma de URN anclada al dominio, `displayName` y `type`, y exactamente uno de `url` o `data`

#### Scenario: La entrada declara el contexto del estándar

- **WHEN** se inspecciona la entrada del servidor MCP
- **THEN** incluye `@context` con el valor exacto `https://agenticresourcediscovery.org/context/v1`

#### Scenario: Las consultas describen la necesidad, no la implementación

- **WHEN** se revisan los `representativeQueries` de la entrada
- **THEN** son consultas que formularía una persona, en español, y no nombres de funciones ni de endpoints

#### Scenario: El manifiesto no enumera primitives ni credenciales

- **WHEN** se inspecciona el manifiesto
- **THEN** no contiene credenciales, tokens ni datos internos, y no enumera herramientas, recursos ni prompts

#### Scenario: El manifiesto es accesible sin sesión

- **WHEN** un cliente anónimo solicita el manifiesto
- **THEN** el sistema lo sirve sin exigir autenticación

### Requirement: Canales auxiliares de descubrimiento ARD

Además del well-known URI, el sistema SHALL anunciar el origen de entradas ARD por los canales auxiliares que define el estándar:

- **`robots.txt`**: SHALL incluir la directiva `Agentmap:` apuntando a la URL **absoluta** del manifiesto ARD. El `robots.txt` del sitio es **virtual** —lo genera WordPress y lo filtra The SEO Framework; no existe ningún fichero `robots.txt` en el webroot— de modo que la directiva SHALL añadirse mediante el filtro `robots_txt` y SHALL NOT depender de un fichero no versionado.
- **`<head>`**: SHALL incluir `<link rel="ard" href="…">` con la URL absoluta del manifiesto, en toda petición que ejecute `wp_head`.
- **Proxy inverso**: las rutas de descubrimiento SHALL estar exentas del bloqueo por User-Agent. El proxy responde `444` cerrando la conexión a los User-Agents de cliente HTTP, y una ruta de descubrimiento no exenta es indistinguible de una ruta inexistente para los clientes que consultan el descubrimiento.
- **DNS**: el binding de servicio (`_entries._agents.…` / `_search._agents.…`) queda **fuera de alcance**: reside en la zona DNS, que no vive en este repositorio.

Los canales SHALL ser redundantes entre sí —si uno no se consulta, los otros siguen apuntando al mismo manifiesto— y SHALL NOT exponer credenciales ni datos internos.

#### Scenario: `robots.txt` declara el Agentmap

- **WHEN** un cliente solicita `GET /robots.txt`
- **THEN** la respuesta incluye una línea `Agentmap:` con la URL absoluta de `/.well-known/ard.json`

#### Scenario: El `<head>` declara la relación `ard`

- **WHEN** se inspecciona el `<head>` de una página pública
- **THEN** contiene `<link rel="ard">` apuntando a la URL absoluta del manifiesto

#### Scenario: Las rutas de descubrimiento no sufren el bloqueo por User-Agent

- **WHEN** un cliente con User-Agent de cliente HTTP (`curl`, `wget`, `python-requests`, `Go-http`) solicita una ruta de descubrimiento
- **THEN** el proxy inverso la sirve y no responde `444`

#### Scenario: Los canales apuntan al mismo manifiesto

- **WHEN** se comparan las URLs declaradas por el `Agentmap`, por el `<link rel="ard">` y la ruta well-known
- **THEN** las tres son la misma URL absoluta

#### Scenario: El binding DNS no es requisito

- **WHEN** se evalúa la conformidad del descubrimiento
- **THEN** la ausencia de registros DNS `_agents` no incumple ningún requisito de esta capability
