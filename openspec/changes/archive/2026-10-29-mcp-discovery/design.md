# Design: Descubrimiento MCP — catálogo AI, tarjeta de servidor y conformidad dual-era

## Context

`atareao-functionality` publica un servidor MCP público de solo lectura. Puntos de partida verificados en el código actual:

| Elemento | Evidencia | Estado |
|---|---|---|
| Ruta REST del transporte | `class-mcp.php:130` (`register_rest_route('atareao/v1','/mcp', CREATABLE, permission_callback => '__return_true')`) | Existe, sin alias limpio |
| Descubrimiento en `wp_head` | `class-mcp.php:126` (`<link rel="mcp-server" … href=rest_url('atareao/v1/mcp')>`) | No estándar, se conserva |
| `initialize` | `class-mcp.php:311-330` (`protocolVersion => '2024-11-05'`, `serverInfo.name => 'atareao-mcp'`, `capabilities.tools`) | Ignora la versión del cliente |
| Métodos servidos | `class-mcp.php:243-254` (`initialize`, `tools/list`, `tools/call`; default `-32601`) | Sin `server/discover` |
| Resultados | `class-mcp.php:607,634,666` (`content[]`, sin `resultType`) | Sin `resultType` |
| Cabeceras CORS | `class-mcp.php:99-117` (`Access-Control-Allow-Headers: Content-Type`) | Justas para el protocolo antiguo |
| Puerta anónima | `atareao-functionality.php:216-290` (`rest_route_from_request()`: cuerpo → query → prefijo `/wp-json/`; `$public_routes = ['/atareao/v1/mcp']`) | No ve un alias fuera de `/wp-json/` |
| Precedente de rutas del plugin | `tools-crontab.php:157-265` (`add_rewrite_rule('^tools/…')`, `query_vars`, `template_include`, `template_redirect` fallback) | Patrón a reutilizar |
| Vaciado de reescrituras | `atareao-functionality.php:299-351` (solo en activación/desactivación) | No reejecuta en instalaciones ya activas |

**Estándares aplicables (verificados, no asumidos):**

- **SEP-2127 — MCP Server Cards: HTTP Server Discovery.** Estado **Final** (2026-01-21, Extensions Track). Tarjeta JSON servida en la ruta recomendada `GET <streamable-http-url>/server-card`, media type `application/mcp-server-card+json`, con `$schema` **literal** `https://static.modelcontextprotocol.io/schemas/v1/server-card.schema.json`. Campos obligatorios: `name` (reverse-DNS con **exactamente un** `/`, regex `^[a-zA-Z0-9.-]+/[a-zA-Z0-9._-]+$`, 3-200 chars), `version` (semver, sin rangos), `description` (1-100 chars). Opcionales: `title`, `icons`, `websiteUrl`, `repository`, `_meta`, `remotes[]` (cada uno con `type` ∈ {`sse`,`streamable-http`} y `url` obligatorios, y `supportedProtocolVersions`/`headers`/`variables` opcionales). La tarjeta **no** enumera tools/resources/prompts.
  - `GET <endpoint>` desnudo tiene significado reservado en Streamable HTTP (abrir stream SSE): servir la tarjeta ahí está **explícitamente desaconsejado**. `GET /mcp` debe responder `405`.
- **AI Catalog** (`Agent-Card/ai-catalog`, Linux Foundation). Documento `application/ai-catalog+json` en `/.well-known/ai-catalog.json`, con `specVersion` (formato «Major.Minor», p. ej. `"1.0"`) y `entries[]`. Cada entrada exige `identifier` (URI/URN; se recomienda `urn:air:{publisher}:{namespace}:{name}`), `type` (media type, aquí `application/mcp-server-card+json`) y **exactamente uno** de `url` o `data`. `displayName`, `description` y `version` son opcionales y **SHOULD omitirse** cuando el artefacto referenciado ya los lleva (nuestra tarjeta lleva `title` y `version`): no se duplican.
- **MCP `2026-07-28`** (revisión vigente). Stateless: sin handshake, versión y capacidades por petición en `_meta` (`io.modelcontextprotocol/protocolVersion` y `…/clientCapabilities` **obligatorios**; falta → `-32602` + HTTP 400), `resultType` obligatorio en todo resultado, `server/discover` **obligatorio**, cabeceras `MCP-Protocol-Version` (obligatoria, debe coincidir con `_meta`), `Mcp-Method` (obligatoria, = `method`) y `Mcp-Name` (obligatoria en `tools/call`, = `params.name`) con `HeaderMismatch` (`-32020`) + HTTP 400 si falta o no coincide, `UnsupportedProtocolVersionError` (`-32022`) + HTTP 400, `202 Accepted` para notificaciones, `405` para `GET`/`DELETE` en el endpoint y `ttlMs`/`cacheScope` en los listados.
- **Compatibilidad dual-era** (MCP, `2026-07-28/basic/versioning`). Un servidor **MAY** implementar ambas eras: una petición con `_meta` moderno se sirve de forma stateless; una petición `initialize` selecciona semántica legacy. La detección en HTTP es: la primera petición moderna o bien funciona o bien devuelve un error moderno reconocible.

**Restricciones del repo.** Sin build tools, sin framework de tests: la verificación es `just php-lint`, `just phpcs` (PSR12), el arnés procedural `just test` (607 aserciones, `tests/test-valet.php` + `tests/wp-stubs.php`) y E2E. La implementación arranca **solo tras aprobación**. Sin `PLAN.md`.

## Goals / Non-Goals

**Goals**

- Que **cualquier agente descubra** el servidor MCP por dominio, sin configuración manual, siguiendo el flujo estándar catálogo → tarjeta → endpoint.
- Que la tarjeta sea **verdadera**: anunciar solo las versiones de protocolo que el servidor cumple de hecho.
- Que los clientes **modernos** (`2026-07-28`) puedan conectar y que los que hoy funcionan **sigan funcionando** (dual-era).
- Servir los dos artefactos de descubrimiento **desde el plugin**, versionados en el repo, con su media type, CORS de solo lectura, caché y `ETag`.
- Ofrecer el endpoint limpio `/mcp` (y, por convención, `/mcp/server-card`) conservando `/wp-json/atareao/v1/mcp`.
- No abrir ningún método con efectos, no introducir autenticación y no cambiar el sitio público.

**Non-Goals**

- **No** se implementa el Registro oficial de MCP (`registry.modelcontextprotocol.io`, `server.json` + `mcp-publisher`): es descubrimiento *global* y complementario, no descubrimiento *por dominio*, y es un paso operativo, no código del plugin.
- **No** se implementan `resources/*`, `prompts/*` ni `subscriptions/listen`: sigue siendo un servidor de tools de solo lectura. `server/discover` anunciará `capabilities.tools` y nada más.
- **No** se añade autenticación ni se restringe el CORS a un origen concreto.
- **No** se toca la capa WebMCP ni su endpoint interno.
- **No** se modifica `nginx/` salvo la **única** excepción imprescindible: una `location ^~ /.well-known/` que deja pasar el catálogo a WordPress (la regla genérica `location ~ /\. { deny all; }` lo bloqueaba con `403`). El resto de la configuración del proxy no se toca.

## Decisions

- **D17 — Transporte dual-era.** En vez de anunciar lo que no somos ni de reescribir el transporte a ciegas: se **conserva** el handshake `initialize` (clientes que hoy funcionan) y se **añade** la era moderna `2026-07-28` (stateless por petición), que es la única forma de que un cliente actual conecte. La tarjeta anuncia **solo** lo implementado.
- **D18 — Endpoint limpio `/mcp` por normalización, sin mudar la ruta.** La ruta REST `atareao/v1/mcp` se conserva; el alias `/mcp` se resuelve normalizando la petición hacia esa misma ruta REST, de modo que **hay una sola implementación** del transporte. Así la convención de la tarjeta produce `/mcp/server-card` gratis. La ruta REST se conserva **operativa** —cerrarla costaría más spec y rompería consumidores como la capa WebMCP, que llama a esa URL desde el navegador— pero **deja de anunciarse**: el meta de descubrimiento de `wp_head` y la documentación apuntan a `/mcp`, de modo que `/mcp` queda como única dirección pública conocida.
- **D19 — Artefactos servidos por el plugin, no estáticos.** El webroot del contenedor no está versionado (`just build` solo empaqueta theme y plugin) y la nginx de producción no está en el repo. Servirlos desde el plugin es el único camino controlado, y además permite emitir los `Content-Type` de media type específicos.
- **D20 — Sin negociación de contenido en el transporte; `Content-Type` forzado por artefacto.** WordPress emite `application/json` por defecto. Los dos artefactos se sirven fuera del ciclo REST normal de render (mismo patrón que `/tools/*`), lo que permite fijar el `Content-Type` exacto sin depender de `rest_pre_serve_request`.
- **D21 — Rutas de descubrimiento resolubles sin vaciar reescrituras.** Siguiendo el precedente de `atareao_tools_template_redirect_fallback()` (`tools-crontab.php:230`), los dos artefactos `GET` se sirven por coincidencia de ruta al principio del ciclo, de modo que funcionan aunque las reglas de reescritura no se hayan vaciado en una instalación ya activa (el hook de activación no corre en instalaciones ya activas). El alias `/mcp` lleva además vaciado guardado por versión.
- **D22 — URLs derivadas del origen del sitio.** `remotes[].url`, la URL de la tarjeta y la del catálogo se derivan de `home_url()`, no se escriben a mano. En producción son HTTPS; en desarrollo (`http://atareao.localhost:8080`) son HTTP, y eso es aceptable: los escenarios de HTTPS se verifican en producción.
- **D23 — Caché.** Los dos artefactos son `GET`: entran en `fastcgi_cache` (`nginx/default.conf`), lo deseable para documentos estáticos; el endpoint `/mcp` es `POST` y nunca se cachea.
- **D24 — `GET /mcp` responde `405`.** Confirmado por SEP-2127 y por Streamable HTTP: un `GET` al endpoint desnudo está reservado (stream SSE) y servir ahí la tarjeta es no recomendado. Los clientes **no adivinan** la ruta de la tarjeta: siguen el `url` que les da el catálogo.
- **D25 — Excepción `nginx` para `/.well-known/`.** El proxy inverso del sitio deniega con `403` toda ruta con directorio oculto (`location ~ /\. { deny all; }`), lo que incluiría el catálogo AI. Se añade una `location ^~ /.well-known/ { try_files $uri /index.php?$args; }` **antes** de esa regla (nginx resuelve por la primera regex que casa) para que el catálogo llegue al plugin. Es la única modificación de `nginx/` y no afecta a `/mcp` ni `/mcp/server-card`, que ya caen en `location /`.

- **D26 — El resultado de `server/discover` usa `supportedVersions`, no `supportedProtocolVersions`.** Son dos artefactos distintos con dos nombres distintos para el mismo dato: el resultado de `server/discover` declara las versiones en **`supportedVersions`** (esquema de la era moderna `2026-07-28`), mientras que la **tarjeta** SEP-2127 las declara en `remotes[].supportedProtocolVersions`. Sin esta distinción, el cliente real (OpenCode v2.1.4) valida el resultado contra su esquema, lo descarta y cae de forma silenciosa al handshake legacy: la era moderna nunca llega a negociarse.

- **D27 — Los metadatos por petición van en `params._meta`, no en el nivel superior del mensaje.** El campo general `_meta` de MCP es un parámetro del mensaje JSON-RPC: viaja dentro de `params` (p. ej. `{"method":"tools/call","params":{"name":…,"arguments":{…},"_meta":{…}}}`). Leerlo (o escribirlo) en el nivel superior del cuerpo hace que ninguna petición real seleccione la era moderna. La selección de era y la lectura de `protocolVersion`/`clientCapabilities` se hacen por tanto en `params._meta`.

## Architecture

### Rutas

| URL | Método | Qué es | Implementación |
|---|---|---|---|
| `/mcp` | `POST`, `OPTIONS` | Transporte MCP (JSON-RPC 2.0), era moderna y legacy | Alias normalizado → ruta REST `atareao/v1/mcp` |
| `/mcp` | `GET`, `DELETE` | — | `405 Method Not Allowed` |
| `/mcp/server-card` | `GET` | Tarjeta de servidor (`application/mcp-server-card+json`) | Handler del plugin, `ETag`/`304` |
| `/.well-known/ai-catalog.json` | `GET` | Catálogo AI (`application/ai-catalog+json`) | Handler del plugin, `ETag`/`304` |
| `/wp-json/atareao/v1/mcp` | `POST` | Transporte (legacy; **no anunciada**, sigue operativa) | `register_rest_route` existente |

### Documentos (contrato exacto)

`GET /.well-known/ai-catalog.json` → `application/ai-catalog+json`:

```json
{
  "specVersion": "1.0",
  "entries": [
    {
      "identifier": "urn:air:atareao.es:mcp:blog",
      "type": "application/mcp-server-card+json",
      "url": "https://atareao.es/mcp/server-card"
    }
  ]
}
```

`GET /mcp/server-card` → `application/mcp-server-card+json`:

```json
{
  "$schema": "https://static.modelcontextprotocol.io/schemas/v1/server-card.schema.json",
  "name": "es.atareao/blog",
  "title": "atareao.es — consulta del blog",
  "description": "Consulta pública y de solo lectura del contenido publicado en atareao.es.",
  "version": "1.17.0",
  "websiteUrl": "https://atareao.es/",
  "remotes": [
    {
      "type": "streamable-http",
      "url": "https://atareao.es/mcp",
      "supportedProtocolVersions": ["2026-07-28", "2025-06-18"]
    }
  ]
}
```

`version` se toma de `ATAREAO_PLUGIN_VERSION`; `url`/`websiteUrl` de `home_url()`. `supportedProtocolVersions` sale de una **única fuente de verdad** compartida con `server/discover` (`initializeResult`/`DiscoverResult`), de modo que tarjeta y comportamiento no puedan divergir. `name` respeta la regex de reverse-DNS con exactamente un `/`.

### Cabeceras de los artefactos

`Access-Control-Allow-Origin: *` · `Access-Control-Allow-Methods: GET` · `Access-Control-Allow-Headers: Content-Type, If-None-Match` · `Access-Control-Expose-Headers: ETag` · `Cache-Control: public, max-age=3600` · `ETag` y `304` ante `If-None-Match` coincidente · `Content-Type` del media type correspondiente.

### Matriz de conformidad (era moderna `2026-07-28`)

| Regla | Respuesta |
|---|---|
| `_meta.io.modelcontextprotocol/protocolVersion` o `clientCapabilities` ausentes | `-32602`, HTTP 400 |
| Versión no soportada | `-32022` `UnsupportedProtocolVersionError`, `data.supported` + `data.requested`, HTTP 400 |
| `MCP-Protocol-Version` ausente o ≠ `_meta` | `-32020` `HeaderMismatch`, HTTP 400 |
| `Mcp-Method` ausente o ≠ `method` | `-32020`, HTTP 400 |
| `Mcp-Name` ausente o ≠ `params.name` en `tools/call` | `-32020`, HTTP 400 |
| `params.name` no textual en `tools/call` | `-32602` `Invalid params`, HTTP 400 |
| Todo resultado | `resultType: "complete"` + `_meta.io.modelcontextprotocol/serverInfo` |
| `tools/list` | `ttlMs` + `cacheScope: "public"` |
| `server/discover` | `supportedVersions` + `capabilities` + `ttlMs`/`cacheScope`; identidad solo en `_meta.io.modelcontextprotocol/serverInfo` |
| Notificación aceptada | HTTP 202, sin cuerpo |
| Método no implementado | `-32601`, HTTP 404 |
| `GET`/`DELETE` al endpoint | HTTP 405 |

La comparación de **nombres** de cabecera es insensible a mayúsculas; los **valores** de `Mcp-Name` se decodifican del sentinel Base64 (`=?base64?…?=`) antes de comparar, y los valores conflictivos se rechazan.

### Trampas identificadas (y su tratamiento)

- **Alias invisible a la puerta anónima.** `atareao_functionality_rest_route_from_request()` (`atareao-functionality.php:216`) resuelve cuerpo → query → prefijo `/wp-json/`. Un `POST /mcp` no trae `rest_route` y su `REQUEST_URI` no contiene `/wp-json/` → devolvería `''` → 401. Tratamiento: la normalización del alias marca la petición con un indicador propio y la puerta consulta ese indicador (sigue exigiendo **ruta exacta**).
- **Reescrituras no vaciadas.** El hook de activación no corre en instalaciones ya activas. Tratamiento: rutas de descubrimiento resolubles por coincidencia de ruta (D21) y vaciado guardado por versión para el alias.
- **`Content-Type` por defecto de WordPress.** Tratamiento: D20.
- **`GET` desnudo con significado reservado.** Tratamiento: D24 (`405`).
- **Dos direcciones para el mismo servicio.** Anunciar `/wp-json/atareao/v1/mcp` además de `/mcp` dejaría dos entradas conocidas y ninguna canónica. Tratamiento: el meta de `wp_head` y la documentación apuntan solo a `/mcp`; la ruta REST no se anuncia (pero se conserva operativa).
- **Origen (`Origin`) y DNS rebinding.** Streamable HTTP pide validar `Origin` y responder `403` si es inválido. Este es un servicio **público remoto** y de **solo lectura**, sin credenciales ambientales: no mantenemos allowlist de orígenes y **no** tratamos ningún origen como inválido (CORS `*`). Se documenta como desviación razonada.
- **Directorio oculto bloqueado por el proxy.** La regla `location ~ /\. { deny all; }` de `nginx/default.conf` devolvería `403` para `/.well-known/ai-catalog.json` antes de llegar a PHP. Tratamiento: D25 (excepción `^~ /.well-known/`).

- **Ubicación de `_meta`.** Leer o escribir los metadatos de protocolo en el nivel superior del cuerpo (en vez de `params._meta`) hace que la era moderna nunca se seleccione con un cliente real. Tratamiento: D27 + escenarios que distinguen `params._meta` de un `_meta` de nivel superior.

- **Casting de `params.name`.** Convertir a cadena un `params.name` no textual (`(string) $params['name']`) emite un aviso `Array to string conversion` y además hace que la cabecera `Mcp-Name: Array` «coincida», dejando pasar una petición malformada. Tratamiento: SEC-BE-002 — comprobar `is_string` antes de comparar y responder `-32602` (Invalid params), HTTP `400`.
- **Nombres de herramientas con caracteres no ASCII.** Las tres actuales son ASCII y caben en `Mcp-Name` sin codificar; el decodificador Base64 se implementa igualmente porque es obligatorio.

## Risks

- **Riesgo de romper al cliente que hoy funciona.** Mitigación: era legacy intacta (`initialize`, `tools/list`, `tools/call`) y negociación tolerante; los escenarios de no-regresión son obligatorios antes de cerrar.
- **Riesgo de anunciar versiones que no se cumplen.** Mitigación: fuente única para `supportedProtocolVersions` + escenario de coherencia tarjeta/comportamiento.
- **Riesgo de que la conformidad moderna sea incompleta.** Mitigación: la matriz de conformidad se transcribe escenario a escenario al arnés `tests/test-valet.php` y se verifica en rojo y en verde.
- **Riesgo de regresión del sitio público.** Mitigación: no se toca nginx, ni el tema, ni las rutas existentes; escenario de no-regresión.

## Verification Strategy

`just php-lint` (0 errores) · `just phpcs` (PSR12, delta +0 respecto al baseline) · `just test` (arnés procedural, verde, con los escenarios nuevos) · validación estructural de los dos documentos JSON contra sus esquemas · `openspec validate mcp-discovery --strict` · E2E en producción del flujo catálogo → tarjeta → endpoint.
