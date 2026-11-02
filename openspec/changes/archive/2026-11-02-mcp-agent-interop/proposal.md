# Proposal: MCP disponible para cualquier agente

## Why

**Medido en producción (2026-11-02), no supuesto.** Un agente que sigue el flujo estándar de descubrimiento MCP conecta y negocia bien, pero se rompe en el segundo paso: `tools/list` responde `400 {"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params"}}` si la petición moderna solo declara `io.modelcontextprotocol/protocolVersion` en `params._meta`. El servidor exige además `io.modelcontextprotocol/clientCapabilities`, un campo que muchos clientes no envían en cada petición porque no lo consideran obligatorio. El resultado es que un agente conforme a la práctica común ve «ahí no hay herramientas» cuando el servidor está sano.

Además, el bloqueo por User-Agent del proxy inverso convierte la ausencia de una ruta en un fallo de infraestructura: `GET /.well-known/mcp` con UA de cliente HTTP devuelve `502` (Traefik traduciendo el `444` de nginx), mientras la misma ruta con UA de navegador devuelve `404`. Un agente que sondea el prefijo well-known antes de leer la tarjeta concluye que el servidor está caído.

## Causa raíz

1. **Metadatos obligatorios de más.** `handleModernRequest()` (`includes/class-mcp.php`, línea 568) rechaza con `-32602`/`400` cualquier petición moderna que no traiga `io.modelcontextprotocol/clientCapabilities` en `params._meta`. El requisito vigente de `mcp-server` lo ordena así. Pero `clientCapabilities` no se usa después de validarlo: el servidor no cambia de comportamiento por recibirlo. Exigirlo en cada petición solo añade fricción.
2. **Exención incompleta en el proxy.** El `map $request_uri $mcp_machine_path` de `nginx/pro/nginx.conf` exime `/mcp` y varios documentos well-known, pero no el prefijo `/.well-known/mcp` ni `/.well-known/mcp/server-card`. La ruta `/mcp/server-card` sí está exenta por el prefijo `/mcp`; el prefijo well-known no.

## What Changes

- **`clientCapabilities` pasa a ser opcional en la era moderna.** Solo `io.modelcontextprotocol/protocolVersion` sigue siendo obligatorio. Si falta `io.modelcontextprotocol/clientCapabilities`, se trata como un objeto vacío. Las cabeceras obligatorias (`MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name` en `tools/call`) y la validación de versión no cambian. El objetivo: que un agente que solo manda la versión pueda listar y llamar herramientas.
- **El proxy exime el prefijo `/.well-known/mcp`.** Se añade `~^/\.well-known/mcp(/|\?|$)` al mapa de rutas exentas. El bloqueo general por User-Agent sigue activo en el resto del sitio. El cambio no elimina el bloqueo: lo alinea con la lista de rutas de descubrimiento que la spec ya exige exentar. `/.well-known/mcp` pasa de `502` a `404` real cuando no existe, que es lo que un agente puede interpretar.
- **Tests de regresión:** petición moderna sin `clientCapabilities` → `200` con herramientas; notificación moderna sin `clientCapabilities` → `202`; notificación moderna sin `protocolVersion` → `400`; ruta `/.well-known/mcp` no bloqueada (verificación con UA de cliente).

## Fuera de alcance

- **No se elimina el bloqueo global por User-Agent.** El mapa `$bad_bot` y su `return 444` siguen protegiendo el sitio. Solo se amplía la lista de rutas exentas del MCP y del descubrimiento.
- No se crean documentos nuevos en `/.well-known/mcp`; si la ruta no existe, responde `404`.
- No cambian la tarjeta del servidor, el catálogo AI, el manifiesto ARD, el rate limiting, la visibilidad del contenido ni la validación de argumentos.

## Impacto

| Aspecto | Efecto |
|---|---|
| Agentes que envían solo `protocolVersion` | **Arreglados**: pasan de `400` a `200`/`202` |
| Agentes que envían ambos metadatos | Sin cambios |
| Clientes legacy | Sin cambios |
| Bloqueo por User-Agent | Sigue activo; solo se amplían las exenciones del MCP |
| `/.well-known/mcp` con UA de script | Pasa de `502` a `404` (o `200` si se sirve un documento) |
