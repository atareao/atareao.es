# ai-catalog-enrichment

## Why

El dominio publica hoy `/.well-known/ai-catalog.json`, que el estándar vigente de descubrimiento
de recursos agénticos (**ARD**, *Agentic Resource Discovery*) clasifica como la ruta
**predecesora**. ARD define una ruta canónica distinta —`/.well-known/ard.json`— y una relación de
enlace distinta —`rel="ard"`—, y su texto es explícito sobre las consecuencias de quedarse atrás:

> «A resource that remains only at `/.well-known/ai-catalog.json` may not be found, since
> consulting that path is optional for consumers — a publisher on the predecessor path SHOULD
> move to `ard.json`.»

Y la resolución del consumidor es **normativa**: un consumidor conforme *MUST* consultar
`/.well-known/ard.json` y *MUST* honrar `rel="ard"`. Consultar la ruta predecesora es, en sus
palabras, «a courtesy to resources published before this revision, not a conformance
requirement». Conclusión incómoda: **lo que anunciamos hoy puede no ser encontrado.**

Hay un segundo hueco, que el propio estándar anticipaba: un *catalog entry* y un *ARD entry* **no
son el mismo objeto**. El ARD entry «lleva las señales que un servicio de búsqueda necesita para
hacer comparables recursos entre publishers que nunca se han coordinado», y la señal principal es
**`representativeQueries`**, que nuestra entrada no declara. Sin ella el dominio es un catalog
entry perfectamente válido **pero no descubrible por ARD**. El estándar lo gradúa con suavidad —es
un *warning* de conformidad, no un fallo duro— pero es exactamente la señal que decide si una
consulta en lenguaje natural nos encuentra.

Por último, el proxy inverso de producción bloquea con `444` (cierra la conexión sin responder) los
User-Agents de cliente HTTP —`curl`, `wget`, `python-requests`, `Go-http`, `ruby`, `scrapy`— salvo
en una lista blanca de rutas de máquina. Una ruta de descubrimiento nueva que no entre en esa lista
es **invisible justo para los crawlers que van a consultarla**, porque un `444` es indistinguible
de «no existe».

## What Changes

- **Se sirve la ruta canónica `/.well-known/ard.json`** con el manifiesto ARD.
- **Se conserva `/.well-known/ai-catalog.json`** como ruta predecesora, derivada de la **misma
  fuente de verdad** para que ambas no puedan divergir.
- La entrada pasa a ser un **ARD entry**: se añade **`representativeQueries`** (2–5 consultas en
  lenguaje natural, tal como las formularía quien busca) y el **`@context`** del estándar.
- Se añaden los **canales auxiliares** de descubrimiento: la directiva **`Agentmap:`** en
  `robots.txt` y la etiqueta **`<link rel="ard">`** en el `<head>`.
- Se **eximen las rutas de descubrimiento** del bloqueo por User-Agent en el proxy inverso.

### Decisiones de alcance

**`displayName` y `description` se omiten a propósito.** El estándar recomienda omitirlos en la
entrada cuando el artefacto referenciado ya los lleva, para no duplicar un valor que puede
desincronizarse; la tarjeta de servidor ya declara `title` y `description`.

**No se añaden entradas de artefactos que no existen.** Anunciar las herramientas de `/tools/` como
`application/agent-skills+md` exigiría autorar un documento de skill Markdown público por
herramienta; una entrada OpenAPI exigiría publicar un documento OpenAPI que WordPress no genera.
Un catálogo que apunta a artefactos inexistentes es peor que un catálogo mínimo. Queda para un
change propio.

**El binding DNS queda fuera.** Requiere publicar registros de Service Binding
(`_entries._agents.…`) en la zona DNS, que no vive en este repositorio. Se documenta como canal
disponible, no como requisito.

**Compatibilidad hacia atrás total:** el estándar dice que una entrada publicada contra el formato
predecesor «is interpreted by any consumer that applies the base context, and needs no changes».
La compatibilidad es gratuita; lo que se gana es la ruta y la relación canónicas.

**No se toca** la tarjeta de servidor, la negociación de era, el transporte ni el contenido ya
servido más allá de lo indicado.

## Impact

- **Spec:** `mcp-discovery` — 3 requisitos MODIFIED, 1 requisito ADDED.
- **Código:** `wp-content/plugins/atareao-functionality/includes/class-mcp-discovery.php`
  (composición del manifiesto y rutas), `includes/class-mcp.php` (etiqueta del `<head>`) y el
  filtro `robots_txt` de la directiva `Agentmap:`.
- **Config:** `nginx/pro/nginx.conf` (mapa de exención de rutas de máquina).
- **Tests:** `tests/test-valet.php`, `tests/wp-stubs.php`.
- **Sin dependencias nuevas. Sin cambios de esquema de datos. Sin migración.**
