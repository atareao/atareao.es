# Proposal: Capa WebMCP de consulta estructurada (tools en el navegador sobre el MCP existente)

## Why

Los agentes de IA que ya operan **dentro del navegador** (API `document.modelContext`, WebMCP — W3C Community Group Draft; Chrome 146+ tras flag, origin trial en 149) descubren las capacidades de una página scrapeando el DOM o conversando con él: caro, frágil y ruidoso en tokens. WebMCP permite que la propia página **registre herramientas tipadas** (JSON Schema) que el agente invoca directamente.

atareao.es es un archivo de contenido masivo y estructurado en CPTs (`post`, `tutorial`, `capitulo`, `podcast`, `aplicacion`, `software`) con metadatos propios (audio, número de episodio/temporada, número de capítulo, tutorial padre) hoy invisibles a esos agentes. El sitio **ya expone** un servidor MCP público por REST (`POST /wp-json/atareao/v1/mcp`, JSON-RPC 2.0, 3 tools de solo lectura, rate-limit, CORS, exento del filtro de auth anónimo). Falta la **capa de navegador** que registre herramientas WebMCP y, con ella, exponer el contenido estructurado.

## What Changes

- **Nueva capa WebMCP (navegador).** Un módulo del plugin registra tools vía `document.modelContext` (con *fallback* a `navigator.modelContext`, deprecado desde jul-2026) y **degrada en silencio** si la API no existe. Las tools (`get_latest_posts`, `get_post`, `search_posts`) son de solo lectura y su `execute` **reutiliza el endpoint MCP existente** (mismo origen; sin endpoints nuevos; sin nonce).
- **Backend MCP ampliado (aditivo).** `search_posts` y `get_latest_posts` aceptan un `post_type` opcional, validado contra los tipos públicos existentes. `formatPost` incluye las **metas públicas** de cada CPT y sus **taxonomías públicas**; nunca metas internas ni datos de usuario.
- **Servidor MCP conservado.** No se elimina: sigue atendiendo a clientes MCP externos por red. WebMCP y MCP son complementarios (navegador vs red), no sustitutos.
- **Sin nonce en el cliente.** El HTML se cachea ~6 h; no se incrustan nonces ni secretos en markup cacheado (un nonce por-usuario filtraría y rompería la caché).
- **Sin build tools.** JS vanilla con `defer`; sin `package.json`, sin bundler y sin polyfill (compatibilidad nativa + fallback).

## Capabilities

### New Capabilities
- `webmcp`: capa de registro de herramientas en el navegador (WebMCP) que expone la consulta estructurada del blog —CPTs y sus metas públicas— a agentes en-página, apoyada en el backend MCP existente.

### Modified Capabilities
- `mcp-server`: se añaden requisitos (filtro por `post_type` y exposición de metas/taxonomías públicas de CPT) a las herramientas de consulta de `atareao/v1/mcp`. Aditivo: no cambia ruta, transporte, nombres de tool, meta de descubrimiento ni su carácter de solo lectura.

## Impact

- **Archivos a crear/modificar (solo tras aprobación, en la fase TDD):**
  - `includes/class-webmcp.php` (nuevo) — registro y `enqueue`/`localize` del cliente WebMCP.
  - `assets/js/webmcp.js` (nuevo) — registro de tools y `execute` contra el endpoint MCP.
  - `includes/class-mcp.php` — argumento `post_type` en `search_posts`/`get_latest_posts`; `formatPost` con metas/taxonomías públicas.
  - `atareao-functionality.php` — `require_once` + `\Atareao\WebMCP::init()`; bump de `ATAREAO_PLUGIN_VERSION`.
  - `README.md` del plugin — documentar la capa WebMCP.
- **Specs al archivar:** nueva `openspec/specs/webmcp/spec.md`; delta sobre `openspec/specs/mcp-server/spec.md`.
- **Contratos que NO se tocan:** ruta `/atareao/v1/mcp`, transporte JSON-RPC, nombres de tools, meta `rel="mcp-server"`, CORS, rate-limit, CPTs/slugs/taxonomías, URLs públicas.
- **Naturaleza experimental:** la API WebMCP es un Draft de Community Group; se implementa como **mejora progresiva** (sin agente compatible, no-op).
- **Verificación:** sin framework de tests ni build; arnés externo (`/tmp/opencode/webmcp-harness/`: node con stub de `document.modelContext` + PHP con stubs de WordPress), `just php-lint`, `just phpcs` (delta +0), `node --check` y E2E por red.
