# indexacion

Feature del change `openspec/changes/indexation-hygiene/` (issue
`atareao/visibilidad#21`, diagnóstico en `plans/PLAN-008.md` del repo `visibilidad`).
**Higiene de rastreo**, no crecimiento: sacar del índice el ruido que Google Search Console
mide (búsquedas y archivos de categoría), cortar el rastreo de `?replytocom=` y `/search/`
desde `robots.txt`, tapar con `301` las familias de `404` con equivalente y dejar **una sola**
etiqueta `robots` por documento.

La funcionalidad vive en el plugin `wp-content/plugins/atareao-functionality/`; el tema es
presentación.

## Qué es y ficheros implicados

- `wp-content/plugins/atareao-functionality/includes/class-seo.php` — integración con The SEO
  Framework (TSF) por sus filtros reales: `filterRobotsMeta()` con el predicado `isNoindexRequest()`
  (capabilities `search-indexing` y `category-archives`), `filterRobotsTxtPolicy()` sobre el filtro
  `robots_txt` (capability `crawl-policy`) y el mapa `301` `legacyRedirectTarget()` /
  `redirectLegacyPaths()` (capability `legacy-redirects`).
- `tests/test-valet.php` — escenarios procedurales de las capabilities (arnés del proyecto).
- `tests/t10-residuals/verified-hooks.php` — **fuente única de verdad** de los hooks de TSF que el
  plugin puede registrar, con su `fichero:línea`. El `noindex` de búsqueda se apoya en el filtro
  verificado `the_seo_framework_robots_meta_array`.
- `openspec/changes/indexation-hygiene/` — `proposal.md`, `design.md` (decisiones D1–D7) y
  `specs/*/spec.md` (requisitos y escenarios).

## Cómo funciona

### Búsquedas (search-indexing)

Toda URL bajo `/search/` deja de ser indexable: la primera página (`/search/<término>/`), su
paginación (`/search/<término>/page/<N>/`, sin límite de profundidad) y la búsqueda sin término
(`/search/`). El documento pasa a incluir `noindex` (con `follow`: se deja de **indexar**, no se
corta el rastreo por enlaces).

**Fuente de verdad del `noindex` (D1 = alternativa A):** TSF sigue siendo el **único emisor** de la
etiqueta `<meta name="robots">`; el plugin solo aporta **las reglas**. Se hace por el filtro real y
verificado de TSF 5.2.1:

- Hook: `the_seo_framework_robots_meta_array`, aplicado en
  `autodescription/inc/classes/meta/robots.class.php:170`. Firma:
  `apply_filters( 'the_seo_framework_robots_meta_array', array $meta, ?array $args, int $options )`.
- `$meta` es un array asociativo booleano con las claves `noindex`, `nofollow`, `noarchive`,
  `max_snippet`, `max_image_preview`, `max_video_preview`. TSF lo filtra **una sola vez** y emite
  **una sola** meta `robots`, así que mutar `$meta['noindex'] = true` resuelve a la vez el requisito
  de búsquedas y la invariante `robots-meta` (una única etiqueta).

Se eligió el **filtro versionado** y no las opciones de la UI de TSF (`search_noindex`,
`paged_noindex`, …), que en producción están todas en off: las opciones viven en la base de datos de
producción, **no están versionadas** y son **invisibles al arnés TDD** (`just test` corre sin
WordPress). Un `noindex` que solo existiera en la BD no se podría probar ni revisar en el repo
(lección del issue #200).

Detección (`isSearchRequest()`): se cubre `is_search()` **y** el prefijo de path `/search/`, porque
el sitio enruta `/search/<término>/` por un rewrite propio que no siempre activa `is_search()`.
Fuera de `/search/` el callback devuelve el array recibido **intacto**.

**Estado de partida medido en producción (2026-11-22):** `/search/linux/` y
`/search/linux/page/5/` eran `200` e indexables; `/search/page/81/` (0 resultados) ya salía
`noindex`; `/search/` sin término respondía `404` (no indexable). Tras el cambio, todas las URLs de
`/search/` llevan `noindex`.

**Cómo se verifica:** los escenarios viven en `tests/test-valet.php` (sección
*Indexation Hygiene (search-indexing)*) y se ejecutan con `just test`. La emisión real de la
etiqueta por TSF se comprueba en el HTML servido durante el deploy (bloque 7 de `tasks.md`).

### Categorías y familias medidas (category-archives)

Los **archivos de término de la taxonomía nativa `category`** (`/ubuntu/`, `/como/`, …) y el archivo
de la **familia medida en GSC** `/software/` dejan de ser indexables, **incluidas sus páginas de
paginación** (`/software/page/3/`, `/software/page/143/`, `/ubuntu/page/72/`, …), sin límite de
profundidad. Igual que en las búsquedas, el documento pasa a `noindex` con `follow`.

**Mecanismo (mismo D1-A):** se reutiliza el **único** callback `filterRobotsMeta()` sobre el filtro
verificado `the_seo_framework_robots_meta_array`; no se añade un segundo emisor ni una segunda
etiqueta `robots`. La decisión vive en el predicado único `isNoindexRequest()` (agrupa
`isSearchRequest()` e `isCategoryArchiveRequest()`).

Detección (`isCategoryArchiveRequest()`): `is_category()` para los términos de `category` y
`is_post_type_archive('software')` para la familia medida. Ambas condicionales son verdaderas también
en `/page/<N>/`, así que la paginación queda cubierta sin lógica extra.

**Decisión D3 (incluir `/software/` aunque sea archivo de CPT).** `/software/` no es una categoría
sino el archivo del CPT `software` (`has_archive => true`, rewrite slug `software`). GSC lo cuenta
como el mayor foco de ruido de rastreo paginado de esa familia (143 páginas), así que se incluye
explícitamente aunque no sea un archivo de término.

**Exclusiones (se mantienen indexables):** los hubs de listado del sitio `/blog/` (que es
`is_home()`), `/tutoriales/`, `/podcast/` y `/aplicaciones/` (archivos de CPT distintos de
`software`), y todo el contenido singular (posts, tutoriales, episodios, aplicaciones, software).
Los escenarios del arnés afirman que esas rutas devuelven el array de directivas **intacto**.

**Estado de partida medido en producción (2026-11-22):** `/software/` (`200`, indexable, 143
páginas), `/software/page/3/` (`200`, indexable), `/ubuntu/` (`200`, indexable, 72),
`/como/` (`200`, indexable, 40); `/blog/` y `/author/atareao/` (`200`, indexables, deben seguir
igual). Tras el cambio, los archivos de término de `category` y `/software/` (con su paginación)
llevan `noindex`.

**Cómo se verifica:** los escenarios viven en `tests/test-valet.php` (sección *Indexation Hygiene
(category-archives)*) y se ejecutan con `just test`. La emisión real de la etiqueta por TSF se
comprueba en el HTML servido durante el deploy (bloque 7 de `tasks.md`).

### `robots.txt` (crawl-policy)

El `robots.txt` del sitio corta el rastreo de dos familias de basura que Google Search Console mide:
las URLs de comentario **`?replytocom=`** y las páginas de **búsqueda interna `/search/`**. Añade dos
directivas:

```
Disallow: /*?replytocom=
Disallow: /search/
```

**Origen virtual (sin fichero en disco).** El `robots.txt` **no** es un fichero del repositorio ni del
webroot: lo genera WordPress —a través de The SEO Framework— y pasa por el filtro `robots_txt`. Por eso
las directivas no pueden vivir en un fichero estático y **no versionado**: se incorporan por ese mismo
filtro. En producción conviven con las directivas preexistentes, que **no** se eliminan ni se duplican:

```
User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php
Sitemap: https://atareao.es/sitemap.xml
Agentmap: https://atareao.es/.well-known/ard.json   ← la aporta mcp-discovery (mismo filtro)
```

**Implementación (`filterRobotsTxtPolicy()`, en `class-seo.php`).** Se registra
`add_filter('robots_txt', …, 10, 2)` junto al resto de enganches del plugin. La directiva `Agentmap:`
la sigue emitiendo su dueño, `class-mcp-discovery.php` (`filterRobotsTxt()`, `add_filter` en la línea
98): este change **no lo toca** ni introduce un segundo emisor, solo añade un callback independiente
sobre el mismo filtro. `robots_txt` es un filtro del **núcleo** de WordPress (no de TSF), así que no
entra en `tests/t10-residuals/verified-hooks.php` (esa lista solo custodia los hooks
`the_seo_framework_*`).

**Idempotencia.** El callback solo añade una directiva si **no** está ya presente como línea completa
(se comparan líneas recortadas, no subcadenas). Así, aplicar el filtro dos veces —o partir de un
`robots.txt` que ya traiga las directivas— no duplica líneas, y nunca se elimina ni se reordena nada de
lo existente. Las directivas se declaran en un único sitio (`robotsDisallowDirectives()`).

**Estado de partida medido en producción (2026-11-22, tarea 1.2):** el `robots.txt` servido era
virtual y contenía `User-agent: *`, `Disallow: /wp-admin/`, `Allow: /wp-admin/admin-ajax.php`,
`Sitemap: https://atareao.es/sitemap.xml` y `Agentmap: https://atareao.es/.well-known/ard.json`.

**Cómo se verifica:** los escenarios viven en `tests/test-valet.php` (sección *Indexation Hygiene
(crawl-policy)*) y aplican el filtro real `robots_txt` sobre una base con las directivas de partida
(con `mcp-discovery` recableado), comprobando presencia, conservación, no-duplicado e idempotencia.
La descarga real de `/robots.txt` se comprueba durante el deploy (bloque 7 de `tasks.md`).

### Mapa `301` de estructuras antiguas (legacy-redirects)

**Alcance:** solo los huecos **confirmados** por el cruce de las **352 URLs `404`** (raw de Search
Console, `404-urls/Tabla.csv` del repo `visibilidad`) con el sitemap vigente (2.337 rutas). Es un mapa
**mínimo**, no un extractor genérico: cubre equivalentes **1:1** y **familias de prefijo**; todo lo
demás sigue siendo `404`. No se inventa ningún destino.

**Mecanismo.** La única fuente de verdad es `legacyRedirectTarget($path)` en `class-seo.php`: recibe
una ruta (o URL), normaliza la barra final (`/docker` y `/docker/` son la misma entrada) y devuelve el
**destino absoluto** (`https://atareao.es/...`) o `''` si no aplica. El efecto lo aplica
`redirectLegacyPaths()`, registrado en **`template_redirect` (prioridad 3)** con guarda **`is_404()`**:
solo actúa cuando la petición ya ha fallado en resolver, así que ninguna URL con equivalente llega a
servirse como `404`. No usa `parse_request` (el sitemap de TSF ya ocupa ese hook). Anti-bucle: si el
destino coincidiera con la propia ruta pedida, `legacyRedirectTarget()` devuelve `''` y no se redirige.

**Mapa (equivalente 1:1, path solicitado → destino bajo `https://atareao.es`):**

| Path solicitado | Destino |
|---|---|
| `/tutorial/como-instalar-podman` (± barra) | `/tutorial/podman/como-instalar-podman/` |
| `/tutorial/gestionar-imagenes-con-podman` (± barra) | `/tutorial/podman/gestionar-imagenes-con-podman/` |
| `/tutorial/gestionar-contenedores-con-podman` (± barra) | `/tutorial/podman/gestionar-contenedores-con-podman/` |
| `/tutorial/ejecutar-contenedores-con-podman` (± barra) | `/tutorial/podman/ejecutar-contenedores-con-podman/` |
| `/tutorial/journalctl-y-logs-en-systemd` (± barra) | `/tutorial/trabajando-con-systemd/journalctl-y-logs-en-systemd/` |
| `/tutorial/logs-en-docker` (± barra) | `/tutorial/docker/logs-en-docker/` |
| `/tutorial/como-instalar-rust` (± barra) | `/tutorial/introduccion-al-diario-de-un-crustaceo/como-instalar-rust/` |
| `/tutorial/como-proteger-tu-servidor-o-raspberry` (± barra) | `/tutorial/servidor-virtual/como-proteger-tu-servidor-o-raspberry/` |
| `/docker` (± barra) | `/tutorial/docker/` |
| `/pyldoras` (± barra) | `/tutorial/pyldoras/` |
| `/telegram` | `/software/telegram/` |
| `/software/social/4441` (± barra) | `/software/4441/` |
| `/tutorial/opencode` (hub, ± barra) | `/tutorial/tutorial-de-opencode/` |

**Familias de prefijo** (cualquier subruta → el destino descarta el sufijo):

| Prefijo | Destino |
|---|---|
| `/pyldora/*` (p. ej. `/pyldora/14`) | `/tutorial/pyldoras/` |
| `/pildoras` (y `/pildoras/*`) | `/tutorial/pyldoras/` |
| `/podcat/*` (p. ej. `/podcat/468`) | `/podcast/` |

**Sin equivalente → siguen `404`.** El mapa **no** añade regla para `/instalar/*` ni
`/instalar-de-repositorio/*` (no hay equivalente confirmado), ni para `/tutorial/opencode/instalar-opencode/`,
`/tag/*`, `/static/*`, `/.well-known/*`, `/assets/*`, `/upload*`, `wp-*` ni URLs truncadas o basura.
`legacyRedirectTarget()` devuelve `''` para todas ellas y el callback no emite redirección.

**Nota sobre `/amp/` (lo sirve el núcleo, no este mapa).** `/amp/<slug-de-post-existente>/` **ya** se
sirve como `301` mediante el *redirect guess* del **núcleo** de WordPress (cabecera
`x-redirect-by: WordPress`), que resuelve el slug y redirige a la canónica. Este change **no** añade
una regla propia para `/amp/`: registrarla sería redundante y podría competir con el núcleo. Una ruta
`/amp/` **sin** post existente no tiene equivalente y sigue siendo `404`.

**Cómo se verifica:** los escenarios viven en `tests/test-valet.php` (sección *Indexation Hygiene
(legacy-redirects)*). Comprueban, para cada entrada y familia del mapa, que
`legacyRedirectTarget()` devuelve el destino absoluto esperado (incluida la variante con barra final),
que las rutas sin equivalente devuelven `''`, el anti-bucle (ningún destino vuelve a redirigir), que
`redirectLegacyPaths()` se registra en `template_redirect` y **no** en `parse_request`, y que el
callback no emite redirección para una ruta sin equivalente.

### Pendiente (bloques 5–8 de `tasks.md`)

Aún no implementados en este change: unificación del emisor para `?replytocom=` (`robots-meta`),
deploy/verificación y re-medición con `just gsc`.
