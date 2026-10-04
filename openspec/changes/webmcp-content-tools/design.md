# Design

## Context

- Continuación del trabajo previo. El plugin ya contiene `includes/class-mcp.php` (spec `mcp-server`) y usa `assets/vendor/` para librerías de terceros. **No hay build tools**: no existe `package.json`, bundler ni preprocesador; JS y CSS se editan como fuente.
- La capa MCP actual ya resuelve rate-limit (60/min por IP), CORS de solo lectura, validación de argumentos, paginación acotada, formato de respuesta y no-exposición de contenido no público.
- El filtro `atareao_functionality_rest_auth_errors` **bloquea los POST anónimos** a la REST API salvo la ruta `/atareao/v1/mcp`.
- API WebMCP (estado a 2026-10): `registerTool(tool)` / `unregisterTool(name)` (las `provideContext`/`clearContext` se eliminaron en mar-2026); la interfaz se movió de `navigator.modelContext` a **`document.modelContext`** (21-jul-2026), Chrome 150 deprecó la ubicación antigua. Sigue siendo soporte experimental tras flag/origin trial.

## Goals / Non-Goals

**Goals:**
- Exponer la consulta estructurada del blog (CPTs y sus metas públicas) a agentes en navegador mediante tools WebMCP tipadas y de solo lectura.
- Reutilizar el backend MCP endurecido como **única fuente de verdad** (cero duplicación de la lógica de búsqueda).
- Degradación total y silenciosa cuando la API WebMCP no está disponible.

**Non-Goals:**
- Sustituir el servidor MCP (sigue sirviendo a clientes externos por red).
- Dar soporte a navegadores sin API WebMCP (nada de polyfill).
- Tools con efectos (escritura/administración) o autenticación.

## Decisions

- **Reutilizar `atareao/v1/mcp` como backend** en lugar de endpoints nuevos. Motivo: el endpoint ya está exento del filtro de auth (una ruta nueva devolvería 401 anónimo), ya tiene rate-limit, CORS, validación y formato. Alternativa descartada: `webmcp/v1/*`, que duplica lógica y obliga a tocar el filtro de auth.
- **`document.modelContext` con fallback a `navigator.modelContext`.** Motivo: seguir la ubicación vigente sin romper en Chrome de transición. Se aísla en un único wrapper (`modelContext()`) y las tools se definen **como datos**, de modo que un futuro movimiento de la API sea un cambio de un solo punto.
- **Sin nonce ni credenciales.** La consulta es pública de solo lectura; incrustar un nonce por-usuario en HTML cacheado (~6 h) lo filtraría y rompería la caché.
- **Sin polyfill.** No hay build y vendorizar `@mcp-b/global` añade mantenimiento; la ausencia de API no altera el sitio.
- **Ampliación aditiva del MCP.** `post_type` opcional (validado contra tipos públicos existentes) y metas/taxonomías públicas en `formatPost`. Alternativa descartada: completar datos en el navegador (el agente recibiría información incompleta y se fragmentaría el contrato).
- **Ubicación del código:** en el **plugin** (funcionalidad), no en el tema (presentación). El tema no se toca.

## Risks / Trade-offs

- **API inestable** (renames como `navigator`→`document`): mitigado con wrapper único + feature-detect; el fallback se retirará cuando la ubicación antigua desaparezca.
- **Prompt injection**: el contenido del blog es no confiable para el agente → `untrustedContentHint: true`; las tools no ejecutan acciones ni aceptan credenciales.
- **Exposición de datos**: solo metas/taxonomías **públicas** y contenido publicado; no se exponen metas internas (`_download_url`, `_repository_url`, `_version`) ni datos de usuario. Revisión `auditor-backend` antes del merge.
- **Caché**: no se incrusta ningún dato por-usuario en el markup.
