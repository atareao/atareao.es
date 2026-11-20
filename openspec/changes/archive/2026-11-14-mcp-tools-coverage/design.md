# Design: Cobertura de herramientas del MCP público

## Contexto

El servidor MCP público vive en `wp-content/plugins/atareao-functionality/includes/class-mcp.php`. El catálogo se declara en `listTools()` (~línea 857) y se despacha en `callTool()` (~línea 954) con un `switch` sobre el nombre. Cada implementación devuelve un `content` con un `text` que es el JSON serializado. La validación se hace con ayudantes (`intArgument`, `postTypeArgument`) que devuelven `WP_Error` y producen `-32602` antes de tocar la base. El endpoint limpio `/mcp` y la ruta REST comparten la misma implementación.

La puntuación externa (Glama TDQS, 3,7/5) desglosa en cuatro dimensiones con media aritmética: Disambiguation 4/5, Naming Consistency 5/5, Tool Count 3/5, Completeness 3/5. Subir las dos últimas es lo que mueve la nota.

## Decisiones

### 1. Añadir tres herramientas, sin tocar las tres existentes

`get_latest_posts`, `get_post` y `search_posts` conservan nombre, esquema y comportamiento. Se añaden:

- `get_post_by_slug` — lookup por identidad textual (slug).
- `list_posts` — navegación filtrada y paginada.
- `list_terms` — navegación por taxonomía.

**Alternativa descartada:** añadir `slug` como parámetro opcional de `get_post` con un `oneOf` entre `id` y `slug`. Se descarta porque debilita el esquema (ninguno de los dos queda requerido y el scorer de esquemas penaliza la ambigüedad) y porque una herramienta dedicada cuenta además para Tool Count. El coste es una herramienta más, aceptable con seis.

### 2. No tocar `get_latest_posts`

Cabía añadirle `page`, pero entonces `get_latest_posts` y `list_posts` harían casi lo mismo y empeoraría Disambiguation. Se deja `get_latest_posts` como «las N más recientes» (atajo) y `list_posts` como «la navegación completa». La descripción de cada una marca la frontera de forma explícita.

### 3. Respuestas de los listados con envoltura y metadatos

`list_posts` y `list_terms` devuelven un objeto `{ items | terms, page, per_page, total, total_pages }` en el `text` del `content`. Las herramientas existentes conservan su respuesta (un array serializado), de modo que ningún cliente actual se rompe. Esta asimetría es deliberada: las nuevas herramientas nacen con contrato bueno; las viejas no se cambian para no romper.

### 4. Lista permitida de taxonomías, fija y del dominio

Igual que `ALLOWED_POST_TYPES`, se define `ALLOWED_TAXONOMIES` como constante con las ocho taxonomías públicas del dominio: `category`, `post_tag`, `tutorial_category`, `tutorial_tag`, `aplicacion_category`, `software_category`, `difficulty` y `platform`. Se valida contra ella y se anuncia como `enum`. No se usa `get_taxonomies()` en runtime, por el mismo motivo que no se usa `get_post_types()`: el contrato publicado debe ser estable y no depender de lo que registre un tercero.

### 5. Visibilidad: reutilizar el filtro existente

Todas las consultas nuevas usan los mismos `args` de `WP_Query` que ya usa el código (`post_status => 'publish'`, `post_password => ''`, tipos públicos permitidos). No se escribe una ruta de consulta alternativa. El conteo de `list_terms` se hace con la misma restricción para no contar contenido no público.

### 6. Filtro por autor sin exponer datos internos

El filtro `author` acepta un **slug** de autor, no un ID. La respuesta de cada entrada incluye el nombre y el slug del autor (ya públicos en el blog), nunca su correo, su rol ni su ID de usuario.

### 7. La capa WebMCP no se toca

`assets/js/webmcp.js` registra tres tools en el navegador y su spec (`webmcp`) exige exactamente esas tres. Añadir herramientas al servidor MCP no viola esa spec (no prohíbe que existan más en el servidor), y mantener el espejo del navegador sin ampliar acota el cambio. Queda como follow-up, no como parte de este delta.

### 8. Sin `outputSchema` ni cambios de transporte

Se mantiene el formato actual de `content`/`text`. No se introduce `outputSchema`, porque el objetivo es navegabilidad y el scorrer no lo premia; y tocar el transporte añadiría riesgo sin retorno.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Exponer contenido no público | Reutilizar las mismas restricciones de `WP_Query`; escenarios de test específicos por herramienta nueva |
| Ambigüedad entre `get_latest_posts` y `list_posts` | Descripciones explícitas de «cuándo usar»; frontera documentada |
| Romper clientes existentes | No se cambia el esquema ni el formato de respuesta de las tres herramientas existentes |
| Coste de consulta (listados con `total`) | `per_page` acotado y tope de página; el `total` se obtiene del propio `WP_Query` sin consulta extra |
| Superficie de taxonomías no previstas | Lista permitida fija; validación estricta |

## Plan de verificación

1. `just php-lint` y `just phpcs` sin nuevos hallazgos.
2. `just test` con los escenarios nuevos (validación, visibilidad, paginación, taxonomías) en verde y los previos sin regresión.
3. `openspec validate mcp-tools-coverage --strict` sin hallazgos.
4. E2E en producción: `tools/list` devuelve seis herramientas; `get_post_by_slug`, `list_posts` y `list_terms` responden con contenido público; un post protegido no se filtra por slug.
5. Reinspección de la ficha de Glama (se puntúa en su calendario) para confirmar la subida de Tool Count y Completeness.
