# mcp-server Delta

## ADDED Requirements

### Requirement: Filtro por tipo de contenido en las herramientas de consulta

`search_posts` y `get_latest_posts` SHALL aceptar un argumento opcional `post_type` que restrinja los resultados a uno de los tipos de contenido **públicos existentes** (`post`, `tutorial`, `capitulo`, `aplicacion`, `podcast`, `software`). Si el argumento está ausente, el comportamiento SHALL conservarse (todos los tipos públicos). Si el valor no pertenece al conjunto permitido —incluido el tipo inexistente `application`—, el sistema SHALL responder JSON-RPC `-32602` (invalid params) sin ejecutar la consulta.

#### Scenario: Filtro válido por tipo de contenido

- **WHEN** un cliente invoca `search_posts` con `post_type` igual a `podcast`
- **THEN** el sistema devuelve únicamente entradas de tipo `podcast`

#### Scenario: Tipo de contenido no permitido

- **WHEN** un cliente invoca `search_posts` o `get_latest_posts` con un `post_type` que no es uno de los tipos públicos existentes (por ejemplo `application`)
- **THEN** el sistema responde `-32602` sin ejecutar la consulta

#### Scenario: `post_type` ausente se comporta como hasta ahora

- **WHEN** un cliente invoca `search_posts` o `get_latest_posts` sin `post_type`
- **THEN** el sistema consulta todos los tipos públicos como antes del cambio

### Requirement: Exposición de metas públicas de CPT en las respuestas

Las herramientas SHALL incluir en el resultado las **metas públicas** del CPT correspondiente: para `podcast`, `mp3-url`, `number` y `season`; para `capitulo`, `numero-capitulo` y `tutorial-id`; y `post_views_count` cuando exista. Las respuestas SHALL incluir también las **taxonomías públicas** asociadas a la entrada. El sistema SHALL NOT exponer metadatos internos (claves con prefijo `_`, como `_download_url`, `_repository_url` y `_version`) ni datos de usuario.

#### Scenario: Un `podcast` incluye sus metas públicas

- **WHEN** se recupera o busca un `podcast`
- **THEN** el resultado incluye `mp3-url`, `number` y `season` (cuando existan) y no incluye claves internas

#### Scenario: Un `capitulo` incluye sus metas públicas

- **WHEN** se recupera o busca un `capitulo`
- **THEN** el resultado incluye `numero-capitulo` y `tutorial-id` (cuando existan)

#### Scenario: Las taxonomías públicas se exponen

- **WHEN** una entrada tiene términos en taxonomías públicas
- **THEN** el resultado los incluye de forma legible

#### Scenario: No se exponen metas internas

- **WHEN** se recupera una `aplicacion` o un `software`
- **THEN** el resultado no incluye `_download_url`, `_repository_url` ni `_version`

#### Scenario: Sin datos de usuario

- **WHEN** se inspecciona cualquier respuesta
- **THEN** no aparecen datos de autor con información sensible (correo, roles o capacidades)
