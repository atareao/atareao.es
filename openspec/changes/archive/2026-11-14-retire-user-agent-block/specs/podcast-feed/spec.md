# Podcast Feed Delta

## MODIFIED Requirements

### Requirement: Alcance y no interferencia del feed

El feed del podcast SHALL incluir únicamente podcasts **publicados y públicos**, ordenados por fecha
descendente, y SHALL NOT exponer borradores, contenido privado, contenido protegido por contraseña ni
datos internos (rutas del servidor, metadatos con prefijo `_`, datos de usuario). El **feed principal**
(`/feed/`), los feeds ligados al archivo de otros tipos de contenido y los feeds de comentarios SHALL
permanecer **inalterados** por este change.

La ruta del feed del podcast SHALL ser alcanzable por clientes HTTP. El proxy de producción SHALL NOT
aplicar el filtro por User-Agent que cierre la conexión (`444`), retirado el 2026-11-13 porque un
User-Agent se falsea trivialmente y solo ocultaba el feed a los agregadores y validadores que lo
consultan.

#### Scenario: Solo podcasts publicados y públicos

- **WHEN** existen borradores, podcasts privados o protegidos por contraseña
- **THEN** no aparecen en el feed del podcast

#### Scenario: El feed principal no cambia

- **WHEN** se solicita el feed principal `/feed/`
- **THEN** su composición y sus elementos son los mismos que antes de este change, sin vocabulario de podcasting

#### Scenario: Sin datos internos

- **WHEN** se inspecciona el feed del podcast
- **THEN** no aparecen rutas del servidor, metadatos internos ni datos de usuario

#### Scenario: El feed es alcanzable por clientes HTTP

- **WHEN** un cliente HTTP solicita el feed del podcast
- **THEN** el proxy inverso lo sirve con su código real y no responde `444`
