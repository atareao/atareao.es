# Proposal: Cobertura de herramientas del MCP público — slug, listado paginado y taxonomías

> Issue: https://github.com/atareao/atareao.es/issues/179

## Why

El servidor MCP público del blog (`atareao-mcp`, endpoint limpio `https://atareao.es/mcp`) expone hoy **tres** herramientas de solo lectura: `get_latest_posts`, `get_post` y `search_posts`. Funcionan y cumplen el contrato (público, sin credenciales, solo lectura), pero cubren solo tres vectores: lo más reciente, una entrada por ID y una búsqueda de texto.

Una auditoría externa independiente —la ficha de Glama `es.atareao/blog`, puntuada el 2026-10-31— evalúa ese catálogo en **3,7/5** y señala exactamente dónde pierde:

- **Tool Count 3/5**: «Three tools is on the thin side, though it matches a deliberately narrow read-only surface for a public blog. A reader could reasonably expect at least a paginated list or taxonomy browsing tool, so the set feels borderline minimal.»
- **Completeness 3/5**: «Core read paths (latest, by ID, text search) are covered… but there are notable gaps: no listing by category/tag/author, no explicit pagination controls, and no slug-based lookup. Agents needing filtered browsing would hit a dead end.»

Mientras Disambiguation (4/5) y Naming Consistency (5/5) están casi perfectas, las dos dimensiones flojas son de **cobertura**, no de redacción: un agente que quiera «los tutoriales de dificultad básica», «lo último del podcast» o «la entrada de esta URL» se encuentra con un callejón sin salida y acaba scrapeando el HTML.

Esto importa ahora por dos razones convergentes:

1. **Es el único margen que queda en el canal de agentes.** El resto está cerrado: registro MCP republicado en 1.22.0, tarjeta de servidor y catálogo AI publicados, ficha de Glama reclamada, alta enviada a `mcpservers.org` y PR abierto a `awesome-remote-mcp-servers`. El TDQS es lo único que se puede subir con trabajo propio.
2. **El tráfico de búsqueda está siendo canibalizado por las features de SERP.** Search Console (16 meses: 157.774 clics, 11.648.116 impresiones, CTR 1,4 %) muestra que las impresiones se multiplican por 5 entre julio de 2025 y marzo de 2026 mientras los clics bajan y la posición media mejora: las respuestas generativas se llevan el clic de consultas que el blog ya rankea bien. Que un agente pueda **navegar** el archivo estructurado por MCP es la respuesta natural a esa pérdida.

El objetivo es doble: subir las dos dimensiones flojas del TDQS y, sobre todo, que el servidor MCP sea de verdad **usable para navegar** el blog, no solo para consultar tres cosas.

## What Changes

- **Nueva herramienta `get_post_by_slug`**: recupera una entrada publicada por su **slug** (la forma natural de referirse a una entrada desde un enlace). `get_post` (por ID) se conserva **sin cambios**.
- **Nueva herramienta `list_posts`**: listado **paginado y filtrado** por tipo de contenido, taxonomía/término, autor, rango de fechas y orden, con **metadatos de paginación** (`page`, `per_page`, `total`, `total_pages`). Es el «navegar» que hoy no existe.
- **Nueva herramienta `list_terms`**: recorrido de **taxonomías** (categorías, etiquetas y las taxonomías del dominio: `tutorial_category`, `tutorial_tag`, `aplicacion_category`, `software_category`, `difficulty`, `platform`), con el número de entradas por término. El «punto de entrada de exploración» que pide la auditoría.
- **Lista permitida de taxonomías** publicada en el `inputSchema` como `enum`, con el mismo criterio que la lista permitida de tipos de contenido: fija, del dominio, independiente del runtime.
- **Descripciones de todas las herramientas** normalizadas (un solo idioma por campo, «cuándo usarla», qué devuelve y sus límites), para preservar la dimension Disambiguation.
- **`tools/list`** anuncia **seis** herramientas con `inputSchema` completo y `annotations` de solo lectura; la **documentación pública** (`README.md` del plugin) se actualiza con las nuevas herramientas y un ejemplo de llamada.

**No cambia**: la ruta REST legacy, el endpoint limpio `/mcp`, el transporte JSON-RPC 2.0, el rate limiting, el CORS, la tarjeta de servidor, el catálogo AI, el registro MCP, la capa WebMCP del navegador (sigue con sus tres tools) y el carácter **estrictamente de solo lectura y público** del servicio. Ninguna herramienta existente se renombra ni se elimina.

## Capabilities

### New Capabilities

Ninguna. El cambio amplía el catálogo de herramientas de una capability existente.

### Modified Capabilities

- `mcp-server`: el catálogo de herramientas crece de tres a seis (`get_post_by_slug`, `list_posts` y `list_terms` nuevas); se añade paginación y metadatos de paginación a los listados; se amplía la validación de argumentos y el filtro por tipo de contenido a las herramientas nuevas; se publica una lista permitida de taxonomías; y se actualiza la documentación pública. Los invariantes de solo lectura, visibilidad pública, exclusión de posts protegidos, rate limiting y errores genéricos se mantienen y se extienden a las herramientas nuevas.

## Impact

- **Código**: `wp-content/plugins/atareao-functionality/includes/class-mcp.php` (registro de tools, validación y tres implementaciones nuevas) y `wp-content/plugins/atareao-functionality/README.md`.
- **Spec**: `openspec/specs/mcp-server/spec.md` (requisitos modificados y añadidos por este delta).
- **Tests**: `tests/test-valet.php` (arnés procedural, `just test`) — nuevos escenarios de validación, visibilidad, paginación y taxonomías.
- **Sin impacto**: base de datos, plantillas, assets, `nginx`, la tarjeta de servidor, el catálogo AI, el registro MCP y la capa WebMCP.
- **Riesgo**: el único riesgo real es el de siempre en este endpoint —exponer contenido no público—. Lo contienen las reglas de visibilidad heredadas, que se aplican sin excepción a las herramientas nuevas. Un segundo riesgo menor es de ambigüedad entre `get_latest_posts` y un `list_posts` sin filtros; lo mitiga la descripción de cada herramienta.

## Non-goals

- No se añaden herramientas con efectos (escritura/admin) ni autenticación.
- No se toca la capa WebMCP del navegador: sigue registrando sus tres tools.
- No se cambia `outputSchema` ni el formato de las respuestas de las herramientas existentes.
- No se elimina ni renombra ninguna herramienta existente.
- No se cambia el rate limiting, el CORS ni el transporte.
