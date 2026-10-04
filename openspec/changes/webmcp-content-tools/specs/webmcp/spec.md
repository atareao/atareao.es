# webmcp Delta

## Purpose

Esta capability define la **capa WebMCP del navegador**: un módulo del plugin que registra herramientas (tools) tipadas y de solo lectura mediante la API `document.modelContext` para que un agente en-página pueda consultar el archivo estructurado del blog (CPTs y sus metas públicas) sin scrapear el DOM. Las tools apoyan su ejecución en el servidor MCP público ya existente (`POST /wp-json/atareao/v1/mcp`), por lo que no duplican lógica de consulta. La capa es una mejora progresiva: si la API no está disponible, el sitio no cambia y no se produce ningún error.

## ADDED Requirements

### Requirement: Registro de tools WebMCP en el navegador

El plugin SHALL registrar las herramientas mediante `document.modelContext` y, si esa ubicación no existe, mediante el *fallback* `navigator.modelContext` (deprecado). El registro SHALL usar `registerTool()` y SHALL NOT usar `provideContext()`/`clearContext()` (eliminadas de la especificación). Si ninguna de las dos ubicaciones expone la API, el sistema SHALL degradar en silencio (no-op), sin errores de JavaScript ni cambios en la página. El registro SHALL ocurrir solo en el **front-end público**, nunca en el panel de administración.

#### Scenario: Registro con la API en `document`

- **WHEN** el navegador expone `document.modelContext`
- **THEN** el sistema registra las tools en `document.modelContext`

#### Scenario: Fallback a `navigator.modelContext`

- **WHEN** el navegador no expone `document.modelContext` pero sí `navigator.modelContext`
- **THEN** el sistema registra las tools en `navigator.modelContext` y lo trata como ruta de transición

#### Scenario: Navegador sin WebMCP

- **WHEN** el navegador no expone ninguna de las dos ubicaciones
- **THEN** el sistema no registra nada, no produce errores y la página permanece intacta

#### Scenario: No se registra en el panel de administración

- **WHEN** se carga una página del panel de administración
- **THEN** el cliente WebMCP no se encola ni registra tools

### Requirement: Contrato de las herramientas expuestas

El sistema SHALL registrar las herramientas `get_latest_posts`, `get_post` y `search_posts`, cada una con `name`, una `description` en lenguaje natural y específica de atareao.es, un `inputSchema` que sea un objeto JSON Schema válido con sus propiedades y campos requeridos, y `annotations` que declaren `readOnlyHint: true` y `untrustedContentHint: true`. Ninguna tool SHALL tener efectos: no escribe, administra ni accede a datos internos. `search_posts` SHALL aceptar `query` (requerido), `post_type` (opcional, uno de los tipos públicos existentes), `per_page` (opcional) y `page` (opcional). `get_post` SHALL requerir `id` entero.

#### Scenario: Las tres tools están registradas con esquema válido

- **WHEN** el agente inspecciona las tools registradas
- **THEN** encuentra `get_latest_posts`, `get_post` y `search_posts`, cada una con `inputSchema` válido y sus campos requeridos

#### Scenario: Las tools se anuncian como solo lectura y contenido no confiable

- **WHEN** se inspeccionan las `annotations` de cada tool
- **THEN** `readOnlyHint` es `true` y `untrustedContentHint` es `true`

#### Scenario: Filtro por tipo de contenido en `search_posts`

- **WHEN** el agente invoca `search_posts` con `post_type` igual a uno de los tipos públicos existentes (`post`, `tutorial`, `capitulo`, `aplicacion`, `podcast`, `software`)
- **THEN** el esquema admite el argumento y la tool lo reenvía al backend

### Requirement: Backend único y reutilización del MCP existente

El `execute` de cada tool SHALL invocar el endpoint MCP existente `POST /wp-json/atareao/v1/mcp` con JSON-RPC `tools/call`, en el mismo origen. El sistema SHALL NOT introducir endpoints REST nuevos para la consulta ni duplicar en el navegador la lógica de búsqueda. El `execute` SHALL NOT requerir credenciales ni nonce, y el sistema SHALL NOT incrustar nonces, tokens ni secretos en el markup cacheable.

#### Scenario: `execute` llama al endpoint MCP

- **WHEN** un agente invoca una tool WebMCP
- **THEN** el cliente envía un `POST` JSON-RPC `tools/call` a `/wp-json/atareao/v1/mcp` y devuelve su resultado

#### Scenario: Sin endpoints REST nuevos

- **WHEN** se inspeccionan las rutas REST registradas por el plugin para la consulta de contenido
- **THEN** no existe una ruta nueva de consulta; se usa la ruta MCP existente

#### Scenario: Sin nonce en el markup

- **WHEN** se inspecciona el HTML cacheable de una página pública
- **THEN** no aparece ningún nonce, token ni secreto incrustado por la capa WebMCP

### Requirement: Manejo de errores y límites del cliente

El `execute` SHALL capturar fallos de red y respuestas no correctas del servidor y SHALL devolver un error estructurado y comprensible para el agente, sin lanzar excepciones crudas. El resultado SHALL respetar los límites del backend (`per_page` máximo 50, `page` acotado) y no SHALL volcar el archivo completo en una sola llamada.

#### Scenario: El servidor responde con error

- **WHEN** el backend devuelve una respuesta no correcta (por ejemplo `429` por rate-limit)
- **THEN** el `execute` devuelve un error estructurado accionable en vez de lanzar una excepción cruda

#### Scenario: Fallo de red

- **WHEN** la petición al backend falla por red
- **THEN** el `execute` devuelve un error estructurado y el agente puede informar al usuario

### Requirement: Solo contenido público y metas públicas

Las tools SHALL devolver únicamente contenido **publicado y público** (nunca borradores, entradas privadas ni protegidas por contraseña) y SHALL NOT exponer metadatos internos (`_download_url`, `_repository_url`, `_version`) ni datos de usuario. Las metas y taxonomías que se expongan SHALL ser las **públicas** del CPT correspondiente.

#### Scenario: No se exponen metas internas

- **WHEN** se inspecciona el resultado de cualquier tool
- **THEN** no aparecen claves de metadatos internas (con prefijo `_`) ni datos de usuario

#### Scenario: Solo contenido publicado

- **WHEN** una tool enumera o busca contenido
- **THEN** solo aparecen entradas publicadas y públicas
