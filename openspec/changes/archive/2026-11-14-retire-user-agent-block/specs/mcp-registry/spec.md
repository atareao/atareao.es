# MCP Registry Delta

## MODIFIED Requirements

### Requirement: Cabeceras, acceso y alcance de los artefactos del registro

Las dos rutas que sirve el sitio —la prueba de propiedad del dominio y los metadatos de recurso
protegido— SHALL ser servidas por el plugin `atareao-functionality` y no por ficheros estáticos del
webroot; sus rutas SHALL resolverse aunque las reglas de reescritura no se hayan vaciado; el servido
SHALL NOT escribir ficheros en el sistema de ficheros y SHALL NOT exigir autenticación. En producción
SHALL servirse por HTTPS.

Sus respuestas SHALL incluir CORS de solo lectura (`Access-Control-Allow-Origin: *`, admitiendo el
método `GET`) y `Cache-Control` público con un `ETag`; ante una petición con `If-None-Match` que
coincida con el `ETag` vigente, el sistema SHALL responder `304 Not Modified` sin cuerpo.

Ambas rutas SHALL ser alcanzables **sin sesión por clientes HTTP**. El proxy de producción SHALL NOT
aplicar un filtro por User-Agent que cierre la conexión (`444`); ese filtro se retiró el 2026-11-13
porque un User-Agent se falsea trivialmente y solo ocultaba el contenido publicado a crawlers y
validadores legítimos.

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

- **WHEN** un cliente HTTP solicita una de las dos rutas
- **THEN** el proxy inverso la sirve y no responde `444`

#### Scenario: Las rutas son accesibles sin sesión

- **WHEN** un cliente anónimo solicita una de las dos rutas
- **THEN** el sistema la sirve sin exigir autenticación
