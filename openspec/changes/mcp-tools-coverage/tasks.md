# Tasks: Cobertura de herramientas del MCP público — slug, listado paginado y taxonomías

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación combina análisis estático (`just php-lint`, `just phpcs`), el **arnés procedural** `just test` (`tests/test-valet.php` + `tests/wp-stubs.php`) y E2E manual en producción. La implementación NO arranca hasta la aprobación del usuario. Objetivo rector: **que un agente pueda navegar el archivo del blog por MCP** (slug, listados filtrados y taxonomías), sin romper ninguna de las tres herramientas existentes.

## 1. Phase 0 — Línea base y caracterización

- [x] 1.1 Registrar el baseline estático antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` registra el par errores/warnings. **Evidencia:** baseline anotado con fecha.
- [x] 1.2 Caracterizar con el arnés el comportamiento actual del catálogo: `tools/list` devuelve exactamente tres herramientas con sus esquemas; `get_post` exige `id` entero; `search_posts` pagina con `page`/`per_page`; un post protegido por contraseña no se devuelve. **Verificación:** escenarios de caracterización en verde **antes** del cambio.
- [x] 1.3 Caracterizar la validación: `post_type` fuera de la lista permitida → `-32602`; `page`/`per_page` fuera de rango → `-32602`. **Verificación:** aserciones en verde.
- [x] 1.4 `openspec validate mcp-tools-coverage --strict` sin hallazgos. **Verificación:** salida literal del CLI.

## 2. TDD — RED

- [x] 2.1 Escribir los escenarios de `get_post_by_slug`: devuelve una entrada pública por slug con el mismo formato que `get_post`; slug inexistente → error genérico «Post not found»; slug de post protegido → el mismo error; slug colisionado → la más reciente; `slug` ausente/vacío/excesivo → `-32602`. **Verificación:** `just test` falla por ausencia de la herramienta y el resto sigue en verde.
- [x] 2.2 Escribir los escenarios de `list_posts`: paginación con metadatos (`page`, `per_page`, `total`, `total_pages`); filtro por `post_type`; filtro por `taxonomy`+`term`; filtro por `author`; rango `after`/`before`; `orderby`/`order`; `per_page`/`page` fuera de rango → `-32602`; `term` sin `taxonomy` → `-32602`; excluye posts no públicos.
- [x] 2.3 Escribir los escenarios de `list_terms`: devuelve términos con `name`, `slug` y conteo; el conteo solo cuenta entradas públicas; `taxonomy` obligatorio; `taxonomy` fuera de la lista permitida → `-32602`; paginación y orden.
- [x] 2.4 Escribir los escenarios del catálogo: `tools/list` anuncia seis herramientas, cada una con `inputSchema` válido y `annotations` de solo lectura; el `enum` de `post_type` coincide en las seis que lo aceptan; el `enum` de `taxonomy` coincide con la lista permitida. **Verificación:** los escenarios nuevos fallan y los previos siguen en verde.
- [x] 2.5 Escribir los escenarios de no-regresión: `get_latest_posts`, `get_post` y `search_posts` conservan nombre, esquema y comportamiento. **Verificación:** siguen en verde.

## 3. TDD — GREEN

- [x] 3.1 Definir la constante `ALLOWED_TAXONOMIES` (ocho taxonomías públicas del dominio). **Verificación:** `just php-lint`.
- [x] 3.2 Implementar el ayudante de validación de taxonomía (análogo a `postTypeArgument`) y los ayudantes de validación de `slug`, fechas y enumerados de orden.
- [x] 3.3 Implementar `get_post_by_slug` reutilizando `formatPost()` y las reglas de visibilidad de `get_post`; resolver colisión por fecha descendente.
- [x] 3.4 Implementar `list_posts` con `WP_Query` (mismos `args` de visibilidad) y la envoltura con metadatos de paginación.
- [x] 3.5 Implementar `list_terms` con `get_terms` restringido al conteo de entradas públicas.
- [x] 3.6 Registrar las tres herramientas en `listTools()` con `description` y `inputSchema` completos, y despacharlas en `callTool()`. **Verificación:** `just test` en verde y `cargo`/`php-lint` sin errores.
- [x] 3.7 Actualizar el `README.md` del plugin con las seis herramientas y un ejemplo de `list_posts`. **Verificación:** lectura directa.

## 4. REFACTOR

- [x] 4.1 Unificar los `args` comunes de visibilidad en un único ayudante para que las cuatro herramientas de listado no repitan el filtro.
- [x] 4.2 Normalizar las descripciones de las seis herramientas (un idioma por campo, «cuándo usar», límites). **Verificación:** revisión manual.
- [x] 4.3 `just php-lint` y `just phpcs` sin nuevos hallazgos; `just test` 100 % verde.

## 5. Verificación final y despliegue

- [x] 5.1 `openspec validate mcp-tools-coverage --strict` sin hallazgos.
- [ ] 5.2 Desplegar a producción por el flujo habitual del plugin.
- [ ] 5.3 E2E en producción: `tools/list` devuelve seis; `get_post_by_slug`, `list_posts` y `list_terms` responden con contenido público; un post protegido no aparece por slug; `post_type`/`taxonomy` fuera de lista → `-32602`.
- [x] 5.4 Anotar el estado de TDQS antes del cambio (3,7/5; Tool Count 3/5, Completeness 3/5) para comparar tras la reinspección de Glama.
- [ ] 5.5 `openspec archive mcp-tools-coverage` una vez aprobado el resultado.
