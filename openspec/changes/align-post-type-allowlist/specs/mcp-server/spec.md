# mcp-server Delta

## MODIFIED Requirements

### Requirement: Filtro por tipo de contenido en las herramientas de consulta

`search_posts` y `get_latest_posts` SHALL aceptar un argumento opcional `post_type` que restrinja los resultados a uno de los tipos de contenido del **dominio** del blog: `post`, `tutorial`, `capitulo`, `aplicacion`, `podcast` y `software`. La validación del argumento **explícito** SHALL realizarse contra una **lista permitida fija** con esos seis tipos, y SHALL NOT depender de «cualquier tipo público» del runtime. Si el argumento está **ausente** (o es `null`), el comportamiento SHALL conservarse: la consulta abarca todos los tipos públicos, como antes del cambio. Si el valor no pertenece a la lista permitida —incluido el tipo inexistente `application` y el tipo público no perteneciente al dominio `page`—, el sistema SHALL responder JSON-RPC `-32602` (invalid params) **sin** ejecutar la consulta. El `enum` de `post_type` que el servidor anuncia en el `inputSchema` de las herramientas de consulta (`tools/list`) SHALL ser **esa misma lista permitida**, de modo que el contrato publicado coincida con la validación.

#### Scenario: Filtro válido por tipo de contenido

- **WHEN** un cliente invoca `search_posts` con `post_type` igual a `podcast`
- **THEN** el sistema devuelve únicamente entradas de tipo `podcast`

#### Scenario: Tipo de contenido no permitido

- **WHEN** un cliente invoca `search_posts` o `get_latest_posts` con un `post_type` que no es uno de los tipos del dominio (por ejemplo `application`)
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: Tipo público fuera del dominio no permitido

- **WHEN** un cliente invoca `search_posts` o `get_latest_posts` con `post_type` igual a `page` (tipo público pero fuera del dominio)
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: `post_type` ausente se comporta como hasta ahora

- **WHEN** un cliente invoca `search_posts` o `get_latest_posts` sin `post_type`
- **THEN** el sistema consulta todos los tipos públicos como antes del cambio

#### Scenario: El esquema anunciado coincide con la lista permitida

- **WHEN** un cliente envía `tools/list`
- **THEN** el `enum` de `post_type` anunciado incluye exactamente los seis tipos del dominio y ningún otro
