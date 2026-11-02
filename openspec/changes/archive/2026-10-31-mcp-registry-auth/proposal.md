# mcp-registry-auth

## Why

El sitio ya **anuncia** su servidor MCP en el ámbito del propio dominio —catálogo, manifiesto ARD y
tarjeta de servidor—, pero no lo anuncia **fuera** de él: no aparece en ningún registro de servidores
MCP. El registro oficial (*MCP Registry*, `registry.modelcontextprotocol.io`) es el índice que
consultan los clientes y los catálogos para **descubrir** servidores; sin una entrada, la única forma
de conocer el endpoint de atareao.es es que alguien lo cite a mano. Para listarse bajo el nombre del
dominio (`es.atareao/…`) el registro exige **probar que se controla el dominio**, y esa prueba es un
documento que el propio dominio debe servir en `/.well-known/mcp-registry-auth`.

Hay una segunda pieza que el estándar pide a los servidores MCP que participan en autorización: la
especificación de autorización de MCP obliga a publicar **OAuth 2.0 Protected Resource Metadata**
(RFC 9728) en `/.well-known/oauth-protected-resource`, con el identificador canónico del recurso y
**al menos un** servidor de autorización. Hoy el nombre del servidor de autorización del sitio
(PocketID) no aparece en ningún documento legible por máquina: un cliente no tiene forma de saber
dónde se obtendrían credenciales para este dominio.

## What Changes

- Se sirve **`GET /.well-known/mcp-registry-auth`** con la **prueba de propiedad del dominio**
  (la línea `v=MCPv1; k=…; p=…`), en texto plano, sin autenticación y desde una fuente de verdad
  configurable.
- Se añade al repositorio el **manifiesto del registro** (`server.json`), con el nombre de dominio
  inverso, la versión y el remoto `streamable-http` del endpoint limpio.
- Se sirve **`GET /.well-known/oauth-protected-resource`** (RFC 9728) con el recurso canónico y el
  servidor de autorización configurado.
- Se **eximen ambas rutas** del bloqueo por User-Agent en el proxy inverso, para que los clientes que
  van a consultarlas no reciban el cierre de conexión.
- Se **documenta el flujo de publicación** en el registro, que es un paso manual del titular.

### Decisiones de alcance

**La publicación en el registro es un paso manual del titular, no del sitio.** Listar el servidor
exige firmar con la **clave privada** que corresponde a la prueba de propiedad; ese material no puede
vivir en la base de datos ni en el runtime de WordPress. Este cambio deja el manifiesto y la prueba
**listos y servidos**; la publicación la ejecuta el titular con la herramienta oficial. La clave
privada **nunca** se versiona ni se sirve: el documento público contiene sólo la clave pública, que
por definición no es secreta.

**El documento de recurso protegido es declarativo y no cambia la política de acceso.** El endpoint
MCP de atareao.es es público y sus tres herramientas son de solo lectura sin credenciales. El
documento RFC 9728 **anuncia dónde se obtendría la autorización** para este recurso; no convierte las
herramientas en protegidas ni hace que el endpoint exija un token. Esa separación se fija con un
escenario («El documento no cambia la política de acceso») para que nadie la confunda después. Si en
la revisión se prefiere no anunciar todavía un servidor de autorización, la pieza se puede recortar
de este change sin afectar al registro.

**No se sirve un artefacto sin sustancia.** Si no hay prueba de dominio configurada, la ruta
`mcp-registry-auth` responde `404` en lugar de servir un documento vacío; si no hay proveedor de
identidad configurado, la ruta de recurso protegido responde `404` en lugar de publicar un documento
sin `authorization_servers`. Los dos casos están cubiertos por escenarios: un artefacto de
verificación que miente es peor que un artefacto ausente.

**Capability nueva `mcp-registry`, no una extensión de `mcp-discovery`.** Los artefactos de
`mcp-discovery` describen el servidor *dentro* del dominio; los de esta capability existen para
**listarlo fuera** (registro) y para **declarar su postura de autorización**. Separarlos evita
acoplar este change a la revisión de la ruta canónica ARD, que es un cambio distinto y ya propuesto.

**No se toca** la tarjeta de servidor, el catálogo, el transporte, el rate limiting ni la lógica de
las herramientas.

## Capabilities

### New Capabilities

- `mcp-registry`: la prueba de propiedad del dominio que permite listar el servidor en el registro
  oficial de MCP, el manifiesto `server.json` con el que se publica, y los metadatos de recurso
  protegido (RFC 9728) que declaran dónde se obtendría la autorización.

### Modified Capabilities

*(ninguna)*

## Impact

- **Spec:** nueva capability `mcp-registry` — 4 requisitos ADDED.
- **Código:** `wp-content/plugins/atareao-functionality/includes/class-mcp-discovery.php` o un módulo
  propio del plugin (servido de los dos documentos), y `includes/class-theme-options.php` para el
  ajuste de la línea de prueba.
- **Config:** `nginx/pro/nginx.conf` (mapa de exención de rutas de máquina) y `server.json` en la
  raíz del repositorio.
- **Documentación:** el `README.md` del plugin documenta el flujo de publicación.
- **Tests:** `tests/test-valet.php`, `tests/wp-stubs.php`.
- **Sin dependencias nuevas. Sin cambios de esquema de datos. Sin migración.**
