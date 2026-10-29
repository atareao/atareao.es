# Proposal: Descubrimiento MCP — catálogo AI, tarjeta de servidor y endpoint limpio `/mcp`

## Why

El plugin `atareao-functionality` ya expone un **servidor MCP público de solo lectura** en `POST /wp-json/atareao/v1/mcp` (`wp-content/plugins/atareao-functionality/includes/class-mcp.php:130`), con tres herramientas de consulta (`get_latest_posts`, `get_post`, `search_posts`), rate limiting por IP y CORS de servicio público. El problema no es que no exista: es que **nadie puede descubrirlo**. Un agente solo puede conectarse si un humano le dicta la URL completa; no hay nada en el dominio que diga «aquí hay un servidor MCP y aquí se conecta uno».

Los estándares que resuelven exactamente eso ya están finalizados:

- **MCP Server Cards** ([SEP-2127](https://modelcontextprotocol.io/seps/2127-mcp-server-cards), **Final**, 2026-01-21, Extensions Track; spec normativa en `modelcontextprotocol/experimental-ext-server-card`): una **tarjeta JSON** (`application/mcp-server-card+json`) que declara *dónde* conectarse — `remotes[].url` y `supportedProtocolVersions` — sin enumerar herramientas (esas se listan en runtime).
- **AI Catalog** ([Agent-Card/ai-catalog](https://github.com/Agent-Card/ai-catalog), Linux Foundation): el **catálogo por dominio** (`application/ai-catalog+json`) publicado en `/.well-known/ai-catalog.json`, con entradas tipadas que apuntan a la tarjeta.

Flujo de descubrimiento del cliente: pide `/.well-known/ai-catalog.json` → encuentra la entrada `application/mcp-server-card+json` → pide la tarjeta → lee `remotes[].url` → conecta. Cada salto existe para que el siguiente sea localizable **sin configuración manual**.

Y una brecha de conformidad que convertiría el anuncio en una trampa si no se cierra: el servidor declara `protocolVersion => '2024-11-05'` (`class-mcp.php:320`) e implementa el handshake `initialize` **ignorando** la versión que ofrece el cliente, **no** implementa `server/discover`, **no** negocia por petición y **no** valida las cabeceras obligatorias de Streamable HTTP. La revisión vigente del protocolo (**`2026-07-28`**) es **stateless**: elimina el handshake `initialize`, lleva la versión y las capacidades **en cada petición** (`_meta.io.modelcontextprotocol/*`), exige `resultType` en todo resultado y **obliga** a implementar `server/discover`. Un cliente moderno que lea nuestra tarjeta y conecte fallará. **Anunciar sin cumplir es peor que no anunciar**: el agente llega, no conecta y marca el servicio como roto.

## What Changes

- **Endpoint limpio `POST /mcp`.** Alias del transporte MCP sobre la ruta REST existente (`/wp-json/atareao/v1/mcp`), que **se conserva operativa** para no romper a ningún cliente ya configurado (incluido el MCP ya registrado y la capa WebMCP). El alias es lo que hace que la convención de la tarjeta (`<url-del-endpoint>/server-card`) produzca `https://atareao.es/mcp/server-card`, la ruta recomendada por SEP-2127, en lugar de la fea `/wp-json/atareao/v1/mcp/server-card`. El endpoint limpio pasa a ser el **único anunciado**: el meta de descubrimiento de `wp_head` y el `README.md` apuntan a `/mcp`, y la ruta REST deja de anunciarse en cualquier artefacto.
- **Tarjeta de servidor** en `GET /mcp/server-card` (`application/mcp-server-card+json`): identidad, `remotes[]` con `type: "streamable-http"`, `url` apuntando al endpoint limpio y `supportedProtocolVersions`. Sin credenciales y sin enumerar primitives (las tools se listan en runtime).
- **Catálogo AI** en `GET /.well-known/ai-catalog.json` (`application/ai-catalog+json`): una entrada `urn:air:atareao.es:mcp:blog` de tipo `application/mcp-server-card+json` que apunta a la tarjeta.
- **Ambos artefactos los sirve el plugin** (no ficheros estáticos en el webroot del contenedor, que no está versionado en el repo ni se empaqueta con `just build`): rutas atendidas por `atareao-functionality`, con `ETag`/`304`, `Cache-Control: public, max-age=3600` y CORS de solo lectura.
- **Conformidad dual-era de Streamable HTTP.** Se **conserva** el handshake `initialize` (era *legacy*, `2025-06-18` y anteriores) y se **añade** la era *moderna* (`2026-07-28`): versión y capacidades por petición en `_meta`, `server/discover`, `resultType` en todo resultado, `ttlMs`/`cacheScope` en los listados, validación de las cabeceras obligatorias (`MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name` → `HeaderMismatch`), `UnsupportedProtocolVersionError`, `202 Accepted` para notificaciones y `405` para `GET`/`DELETE`. La tarjeta anuncia **exactamente** las versiones que el servidor cumple.
- **CORS** del endpoint MCP ampliado a las cabeceras del protocolo (`MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name`, `Accept`, `Authorization`).
- **Documentación**: el `README.md` del plugin documenta las tres URLs (endpoint limpio, tarjeta, catálogo) y el flujo de descubrimiento.

## Capabilities

### New Capabilities

- `mcp-discovery`: descubrimiento del servidor MCP por dominio — catálogo AI en `/.well-known/ai-catalog.json`, tarjeta de servidor en `/mcp/server-card`, las cabeceras HTTP de ambos (media type, CORS, caché, `ETag`/`304`) y su servido desde el plugin sin depender de ficheros estáticos del webroot.

### Modified Capabilities

- `mcp-server`: se **añade** el endpoint limpio `/mcp` como alias reescrito del transporte y la **conformidad dual-era de Streamable HTTP** (moderna `2026-07-28` + legacy con `initialize`), y se **amplía** el CORS del endpoint a las cabeceras del protocolo.
- `rest-access-policy`: la puerta anónima reconoce la **ruta efectiva** del alias `/mcp` como ruta pública exenta, sin aflojar la exención por ruta exacta.

## Impact

- **Archivos a modificar (solo en la fase de implementación, tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-mcp.php` — conformidad dual-era (era moderna por petición, `server/discover`, `resultType`, `ttlMs`/`cacheScope`, validación de cabeceras, `202`/`405`/`404`, negociación en `initialize`) y ampliación del CORS. La lógica de las tres herramientas, la visibilidad, la validación de argumentos y el rate limiting **no cambian de comportamiento**.
  - **Nuevo** `wp-content/plugins/atareao-functionality/includes/class-mcp-discovery.php` — clase `\Atareao\MCP_Discovery`: sirve el catálogo y la tarjeta, sus cabeceras y su fallback de ruta.
  - `wp-content/plugins/atareao-functionality/atareao-functionality.php` — `require_once` de la clase nueva, normalización del alias `/mcp` (para que llegue al transporte y a la puerta anónima) y vaciado de reglas de reescritura **guardado por versión**.
  - `wp-content/plugins/atareao-functionality/README.md` — documentación de las tres URLs y del flujo de descubrimiento.
  - `openspec/specs/mcp-discovery/spec.md` (nuevo, al archivar) y deltas en `openspec/specs/mcp-server/spec.md` y `openspec/specs/rest-access-policy/spec.md`.
  - `tests/test-valet.php` y `tests/wp-stubs.php` — escenarios nuevos del arnés procedural.
- **Rutas y contratos que NO se tocan:** la ruta REST `atareao/v1/mcp` (`CREATABLE`), los nombres `get_latest_posts`/`get_post`/`search_posts`, el meta `rel="mcp-server"` de `wp_head`, JSON-RPC 2.0 como transporte y el endpoint que usa la capa WebMCP (`rest_url('atareao/v1/mcp')`). No se renombra ni se borra ninguna opción ni hook.
- **Compatibilidad:** los clientes MCP que hoy funcionan (handshake `initialize`) **siguen funcionando**; los clientes modernos (`2026-07-28`) pasan a poder conectar. No se introduce autenticación: el servicio sigue siendo público y de solo lectura.
- **No cambia:** el sitio público, el microsite `/tools/`, la analítica, el login/logout ni las notificaciones Matrix.
- **Dependencias:** ninguna nueva (funciones nativas de WordPress y PHP 8.3).
- **Compatibilidad técnica:** PHP 8.3, PSR12, WordPress 6.0+, sin build tools ni framework de tests.
