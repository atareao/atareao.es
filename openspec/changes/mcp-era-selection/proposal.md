# Proposal: La era del transporte MCP no puede decidirse por cabeceras

## Why

**Medido en producción (2026-11-02), no supuesto.** El endpoint público `https://atareao.es/mcp` está sano
(`initialize` → `200` con `serverInfo: atareao-mcp v1.19.0`; `tools/list` → `200` con las 3 herramientas),
pero **los clientes MCP construidos sobre el SDK oficial de TypeScript no consiguen conectar**: el puente
`mcp-remote` —el que usan Claude Desktop, Cursor, VS Code, Windsurf o Cline— aborta con

```
Connection error: SdkHttpError: Error POSTing to endpoint:
  {"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params"},"id":null}
  code: 'CLIENT_HTTP_NOT_IMPLEMENTED'   status: 400 Bad Request
```

En consecuencia, al añadir `atareao.es/mcp` a esos agentes la respuesta es «ahí no hay ningún MCP», cuando
el servidor MCP está perfectamente operativo para otros clientes (por ejemplo el SDK oficial de Python, que
conecta y lista las 3 herramientas).

## Causa raíz

`isModernRequest()` (`includes/class-mcp.php`, línea 513) da por «era moderna» cualquier petición que
presente la cabecera `Mcp-Method`:

```php
if (isset($meta[self::META_PROTOCOL_VERSION])) { return true; }
return self::requestHeader($request, 'Mcp-Method') !== null;   // ← el problema
```

Y `handleModernRequest()` exige entonces `params._meta` con `io.modelcontextprotocol/protocolVersion` **y**
`io.modelcontextprotocol/clientCapabilities`; si falta cualquiera, responde **`-32602` con HTTP `400`**
(el requisito lo manda así hoy: «…selecciona la era moderna … **o** si la petición presenta la cabecera
`Mcp-Method`»).

El SDK oficial de TypeScript envía esa cabecera **en todas** las peticiones posteriores al `initialize`,
también cuando ha negociado el dialecto 2025-06-18 y no manda `_meta`. Interceptado con un proxy local
(reproducción literal del tráfico de `mcp-remote`):

```
POST /mcp
mcp-protocol-version: 2025-06-18
Mcp-Method: notifications/initialized
{"jsonrpc":"2.0","method":"notifications/initialized"}
            ↓
400 {"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params"},"id":null}
```

Prueba A/B contra el servidor de producción — misma petición, solo cambia la cabecera:

| Petición | `notifications/initialized` | `tools/list` |
|---|---|---|
| **sin** `Mcp-Method` | `200` | `200` (3 herramientas) |
| **con** `Mcp-Method` | **`400` `-32602`** | **`400` `-32602`** |
| con `Mcp-Method` + `_meta` completa | `202` | — |

Con la era bien decidida, un cliente que negocia 2025-06-18 es **legacy** y manda `Mcp-Method` sin `_meta`:
hoy recibe `400` y muere en el primer mensaje tras el handshake. Eso es exactamente lo que ve el titular.

## What Changes

- **La era se decide solo por `params._meta.io.modelcontextprotocol/protocolVersion`.** Ni `Mcp-Method` ni
  `MCP-Protocol-Version` seleccionan la era: se siguen **validando** dentro de la era moderna
  (`HeaderMismatch`, `-32020`), pero nunca la eligen. Una petición que traiga `Mcp-Method` sin metadatos de
  protocolo se sirve por la **era legacy**.
- **Las notificaciones no reciben respuesta JSON-RPC.** `notifications/initialized` se acepta en las dos
  eras y cualquier mensaje sin `id` responde **`202 Accepted` sin cuerpo** (hoy la era legacy responde
  `200` con un cuerpo `-32601`, que un cliente no debe tener que interpretar).
- **Regresión blindada con el caso real**: los tests incorporan la petición literal que rompe a
  `mcp-remote` (cabecera `Mcp-Method` + `MCP-Protocol-Version: 2025-06-18` sin `_meta`) y el escenario
  inverso se elimina: la cabecera `Mcp-Method` deja de seleccionar la era moderna.

- **El requisito de spec se renombra** —de «Conformidad dual-era de Streamable HTTP» a «Selección de era
  y conformidad del transporte Streamable HTTP»— y el delta lo hace con `REMOVED` + `ADDED`. El motivo es
  mecánico y está medido: el escenario que **blinda la regla retirada** («La cabecera `Mcp-Method`
  selecciona la era moderna») tiene que desaparecer, y el CLI de OpenSpec **prohíbe** que un `MODIFIED`
  pierda escenarios:

  ```
  MODIFIED "Conformidad dual-era de Streamable HTTP" omits scenario(s) the current spec still has:
  "La cabecera `Mcp-Method` selecciona la era moderna" ... Copy the omitted scenarios into the MODIFIED
  block (a MODIFIED requirement replaces the whole block, so archive refuses to drop them).
  ```

  Conservar el escenario antiguo sería escribir una spec que se contradice a sí misma (y un test que exige
  `400` justo para el caso que queremos que devuelva `200`); la vía honesta es retirar el requisito y
  volver a declararlo con su contrato nuevo: mismo sitio en la spec, mismo texto para todo lo que no
  cambia, sin el escenario obsoleto y con tres escenarios de regresión.

## Cómo se aplica el delta (comprobado)

```
openspec validate mcp-era-selection --strict    →  Change 'mcp-era-selection' is valid
archive simulado sobre una copia de openspec/   →  + 1 added, - 1 removed   (el requisito queda en su sitio)
```

El delta no usa `MODIFIED`: lleva `## REMOVED Requirements` con el requisito vigente **literal** (para que la
retirada quede trazada con su texto y su escenario) y `## ADDED Requirements` con el requisito nuevo. Se
comprobó además que el validador rechaza las otras dos vías: `MODIFIED` que pierde el escenario, y
`REMOVED` + `ADDED` **con el mismo nombre** (`Requirement present in both ADDED and REMOVED`).

## Fuera de alcance

- El **bloqueo de User-Agents** del nginx de producción (`~*(curl|wget|python-requests|Go-http|ruby|scrapy|masscan|zgrab)`
  → `444`, que Traefik convierte en `502`) y la ausencia de `/.well-known/mcp`. Se midieron durante el
  diagnóstico y se documentan como deuda aparte; no son la causa del fallo de conexión (la ruta `/mcp`
  está exenta del bloqueo y responde a cualquier UA).
- La tarjeta del servidor, el catálogo, las versiones soportadas, el rate limiting, la visibilidad del
  contenido y la validación de argumentos **no cambian**.

## Impacto

| Aspecto | Efecto |
|---|---|
| Endpoint público `/mcp` y `/wp-json/atareao/v1/mcp` | Siguen igual; solo cambia la clasificación de era |
| Clientes MCP modernos (2026-07-28, con `_meta`) | Sin cambios: siguen entrando por la era moderna |
| Clientes del SDK oficial tras negociar 2025-06-18 | **Arreglados**: pasan de `400` a `200`/`202` |
| Clientes sin cabeceras de era (SDK de Python, otros) | Sin cambios |
| Compatibilidad | Elimina una regla que la spec vigente **ordena**; el delta de `mcp-server` retira el requisito y lo vuelve a declarar con cabecera nueva (`REMOVED` + `ADDED`), porque el CLI no permite que un `MODIFIED` pierda escenarios |
