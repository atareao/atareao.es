# mcp-registry — Delta

## ADDED Requirements

### Requirement: Prueba de propiedad del dominio en `/.well-known/mcp-registry-auth`

El sistema SHALL servir `GET /.well-known/mcp-registry-auth` como **texto plano** y sin exigir
autenticación, con un cuerpo que sea la **línea de prueba** de propiedad del dominio exigida por el
registro oficial de MCP: exactamente `v=MCPv1; k=<algoritmo>; p=<clave-pública>`, con `k` igual a
`ed25519` o `ecdsap384` y `p` la clave pública en Base64, sin más contenido que un salto de línea
final. La respuesta SHALL declarar `Content-Type: text/plain; charset=utf-8` y SHALL admitir `GET`
desde cualquier origen.

La línea de prueba SHALL proceder de una **única fuente de verdad configurable** —con precedencia de
una constante sobre una opción de WordPress— y SHALL NOT depender de un fichero del webroot. La
**clave privada** correspondiente SHALL NOT almacenarse en el repositorio ni en la base de datos, y
SHALL NOT servirse nunca por HTTP: el documento público contiene únicamente la clave pública.

Cuando no haya ninguna línea de prueba configurada, el sistema SHALL NOT publicar prueba alguna: la
ruta SHALL responder `404` en lugar de servir un documento vacío o una prueba falsa.

#### Scenario: La prueba de propiedad se sirve

- **WHEN** un cliente solicita `GET /.well-known/mcp-registry-auth` y hay una prueba configurada
- **THEN** el sistema responde `200` con `Content-Type: text/plain; charset=utf-8` y un cuerpo que es la línea de prueba

#### Scenario: La línea de prueba tiene la forma exacta

- **WHEN** se inspecciona el cuerpo servido
- **THEN** empieza por `v=MCPv1; k=`, declara un algoritmo admitido (`ed25519` o `ecdsap384`) y una clave pública en Base64

#### Scenario: Sin prueba configurada no se publica una falsa

- **WHEN** la prueba de propiedad no está configurada
- **THEN** el sistema responde `404` y no sirve ningún cuerpo de prueba

#### Scenario: La clave privada nunca viaja

- **WHEN** se inspecciona la respuesta y la configuración del sistema
- **THEN** el documento contiene únicamente la clave pública y no aparece en ningún punto ninguna clave privada

#### Scenario: La fuente de verdad tiene precedencia definida

- **WHEN** existe una constante de prueba de propiedad y también un valor en la opción de WordPress
- **THEN** el sistema sirve el valor de la constante

#### Scenario: Servida por el plugin, no por un fichero estático

- **WHEN** se sirve la prueba de propiedad
- **THEN** la genera el plugin y no depende de un fichero estático del webroot

#### Scenario: La prueba es accesible sin sesión

- **WHEN** un cliente anónimo solicita la prueba de propiedad
- **THEN** el sistema la sirve sin exigir autenticación

### Requirement: Metadatos de recurso protegido en `/.well-known/oauth-protected-resource`

El sistema SHALL servir `GET /.well-known/oauth-protected-resource` con `Content-Type:
application/json` y sin exigir autenticación, devolviendo un documento **OAuth 2.0 Protected Resource
Metadata** (RFC 9728) conforme al uso que de él hace la especificación de autorización de MCP:
`resource` con el **URI canónico** del endpoint MCP —esquema y host en minúsculas, sin barra final— y
`authorization_servers` como array con **al menos un** servidor de autorización, que SHALL ser el
emisor del proveedor de identidad configurado para el sitio. El documento SHALL declarar
`bearer_methods_supported` incluyendo el método de cabecera (`header`) y SHOULD declarar
`resource_documentation`.

El servidor de autorización SHALL proceder de la **misma fuente de verdad** que la configuración del
proveedor de identidad, de modo que el documento no pueda anunciar un emisor distinto del
configurado. Cuando no haya proveedor de identidad configurado, el sistema SHALL NOT publicar un
documento sin servidor de autorización: la ruta SHALL responder `404`.

El documento SHALL NOT contener credenciales, secretos, tokens ni datos internos, y SHALL NOT
enumerar las herramientas, recursos o prompts del servidor. El documento es **declarativo**: SHALL
NOT alterar la política de acceso del endpoint, que sigue atendiendo las herramientas de consulta
pública sin credenciales ni token.

#### Scenario: El documento se sirve con su media type

- **WHEN** un cliente solicita `GET /.well-known/oauth-protected-resource` y hay proveedor configurado
- **THEN** el sistema responde `200` con `Content-Type: application/json`

#### Scenario: El recurso es el URI canónico del endpoint

- **WHEN** se inspecciona `resource`
- **THEN** su valor es el URI canónico del endpoint MCP del sitio, con esquema y host en minúsculas y sin barra final

#### Scenario: Declara al menos un servidor de autorización

- **WHEN** se inspecciona `authorization_servers`
- **THEN** es un array con al menos una entrada, y esa entrada es el emisor del proveedor de identidad configurado

#### Scenario: Admite el uso de token en cabecera

- **WHEN** se inspecciona `bearer_methods_supported`
- **THEN** incluye el método de cabecera (`header`)

#### Scenario: Sin proveedor configurado no se publica un documento sin autorización

- **WHEN** no hay proveedor de identidad configurado
- **THEN** el sistema responde `404` y no publica un documento sin `authorization_servers`

#### Scenario: El documento no expone secretos ni primitives

- **WHEN** se inspecciona el documento
- **THEN** no contiene credenciales, secretos, tokens ni datos internos, y no enumera herramientas, recursos ni prompts

#### Scenario: El documento no cambia la política de acceso

- **WHEN** el documento está publicado y un cliente anónimo invoca las herramientas de consulta del endpoint
- **THEN** el endpoint sigue atendiéndolas sin exigir credenciales ni token

### Requirement: Manifiesto del registro (`server.json`)

El repositorio SHALL contener un manifiesto `server.json` —el documento con el que el titular publica
el servidor en el registro oficial de MCP— conforme al esquema de servidor del registro: `$schema`
con el valor literal del esquema vigente, `name` en notación de **dominio inverso** con **exactamente
un** `/` y con el prefijo que corresponde a la autenticación por dominio (`es.atareao/blog`),
`description`, `version` en semver y `remotes[]` con al menos una entrada de tipo `streamable-http`
cuya `url` sea la **URL absoluta** del endpoint limpio `/mcp`.

El manifiesto SHALL declarar el **mismo nombre, la misma versión y la misma URL de endpoint** que la
tarjeta de servidor del dominio, de modo que el registro y el catálogo del sitio no puedan divergir.
El manifiesto SHALL NOT contener credenciales, secretos ni datos internos, y SHALL NOT enumerar
herramientas, recursos ni prompts.

La publicación efectiva en el registro SHALL ser un **paso manual del titular**, firmado con la clave
privada correspondiente a la prueba de propiedad; SHALL NOT depender de un secreto almacenado en el
sitio ni ejecutarse desde el runtime de WordPress.

#### Scenario: El manifiesto existe y es JSON válido

- **WHEN** se parsea el `server.json` del repositorio
- **THEN** es un objeto JSON válido que declara `$schema`, `name`, `description`, `version` y `remotes`

#### Scenario: El nombre usa el prefijo del dominio

- **WHEN** se inspecciona `name`
- **THEN** tiene exactamente un `/` y su prefijo es el dominio en orden inverso con el nombre del artefacto (`es.atareao/blog`)

#### Scenario: El remoto es el endpoint limpio

- **WHEN** se inspecciona `remotes[]`
- **THEN** contiene una entrada de tipo `streamable-http` cuya `url` es la URL absoluta de `/mcp`

#### Scenario: El registro y la tarjeta no divergen

- **WHEN** se comparan el nombre, la versión y la URL del endpoint del manifiesto con los de la tarjeta de servidor
- **THEN** los tres coinciden

#### Scenario: El manifiesto no expone secretos ni primitives

- **WHEN** se inspecciona el manifiesto
- **THEN** no contiene credenciales, secretos ni datos internos, y no enumera herramientas, recursos ni prompts

### Requirement: Cabeceras, acceso y alcance de los artefactos del registro

Las dos rutas que sirve el sitio —la prueba de propiedad del dominio y los metadatos de recurso
protegido— SHALL ser servidas por el plugin `atareao-functionality` y no por ficheros estáticos del
webroot; sus rutas SHALL resolverse aunque las reglas de reescritura no se hayan vaciado; el servido
SHALL NOT escribir ficheros en el sistema de ficheros y SHALL NOT exigir autenticación. En producción
SHALL servirse por HTTPS.

Sus respuestas SHALL incluir CORS de solo lectura (`Access-Control-Allow-Origin: *`, admitiendo el
método `GET`) y `Cache-Control` público con un `ETag`; ante una petición con `If-None-Match` que
coincida con el `ETag` vigente, el sistema SHALL responder `304 Not Modified` sin cuerpo.

Ambas rutas SHALL estar **exentas del bloqueo por User-Agent** del proxy inverso: el proxy cierra la
conexión a los User-Agents de cliente HTTP, y un artefacto de verificación o de metadatos que sufra
ese bloqueo es indistinguible de una ruta inexistente justo para los clientes que lo consultan.

#### Scenario: CORS y caché de las rutas servidas

- **WHEN** se inspecciona la respuesta de cualquiera de las dos rutas
- **THEN** incluye `Access-Control-Allow-Origin: *`, admite el método `GET`, declara caché pública y expone un `ETag`

#### Scenario: Revalidación con `304`

- **WHEN** un cliente repite la petición con `If-None-Match` igual al `ETag` vigente
- **THEN** el sistema responde `304 Not Modified` sin cuerpo

#### Scenario: Servido por el plugin sin ficheros estáticos

- **WHEN** se solicita una de las dos rutas
- **THEN** la respuesta la genera el plugin y no depende de un fichero estático del webroot

#### Scenario: Disponible sin vaciado de reescrituras

- **WHEN** se solicita una de las dos rutas en una instalación cuyas reglas de reescritura no se han vaciado
- **THEN** el sistema responde el artefacto igualmente

#### Scenario: Las rutas no sufren el bloqueo por User-Agent

- **WHEN** un cliente con User-Agent de cliente HTTP (`curl`, `wget`, `python-requests`, `Go-http`) solicita una de las dos rutas
- **THEN** el proxy inverso la sirve y no cierra la conexión

#### Scenario: Las rutas son accesibles sin sesión

- **WHEN** un cliente anónimo solicita una de las dos rutas
- **THEN** el sistema la sirve sin exigir autenticación
