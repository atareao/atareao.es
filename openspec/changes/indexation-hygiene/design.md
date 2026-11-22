# Design

## Context

Ver `proposal.md` (Why) y las specs `specs/*/spec.md` (requisitos). El estado relevante para el
*cómo* —verificado en el código y en producción el 2026-11-22—:

- **Sitio WordPress + Podman**, core en contenedores (nunca se edita). Solo `wp-content/` está
  versionado. El plugin `atareao-functionality` concentra la funcionalidad y ya integra
  **The SEO Framework 5.2.1** desde `wp-content/plugins/atareao-functionality/includes/class-seo.php`
  (`::init()`, líneas 145-182). El plugin propio **no** escribe reglas `noindex`.
- **TSF no está versionado en el repo** (solo vive en el contenedor de producción). La lección del
  issue **#200** es determinante: `the_seo_framework_title` y `the_seo_framework_description` **no
  existen** en 5.2.1, y los tests daban verde porque el doble de `add_filter()` no comprueba que el
  hook exista. La guardia `tests/t10-residuals/verified-hooks.php` mantiene la única lista
  autorizada de hooks `the_seo_framework_*` (5 hoy, **ninguno de `robots`**) y prohíbe reintroducir
  los inexistentes. Cualquier hook de TSF nuevo SHALL verificarse contra 5.2.1 y registrarse ahí.
- **El `robots.txt` es virtual.** No hay fichero en disco: lo genera WordPress (vía TSF) y llega al
  filtro `robots_txt`. Ya hay un consumidor:
  `class-mcp-discovery.php:98` → `add_filter('robots_txt', …filterRobotsTxt, 10, 2)` (línea 386+),
  que añade la directiva `Agentmap:`. Directivas actuales: `Disallow: /wp-admin/`,
  `Allow: /wp-admin/admin-ajax.php`, `Sitemap: https://atareao.es/sitemap.xml`, `Agentmap:`.
- **Los `301` propios ya viven en `class-seo.php`** con el patrón `\Atareao\*`:
  `redirectSitemapIndex()` en `parse_request` prioridad 1 (`class-seo.php:176`, cuerpo en 658) y
  `redirectLegacyAuthorNicename()` en `template_redirect` prioridad 2 (`class-seo.php:182`, cuerpo en
  694); ambos emiten `wp_safe_redirect($target, 301)` + `exit`. El resto de `301` son automáticos de
  WordPress (`redirect_canonical`: slug viejo, `?p=`, `/podcast/NNN`).
- **`/software/` NO es una categoría.** Es el archivo del CPT `software`
  (`has_archive => true`, rewrite slug `software`; `class-post-types.php:598-608`). Los archivos de
  **término** son la taxonomía nativa `category` (`/ubuntu/`, `/como/`) y las custom
  (`software_category` slug `software-categoria`, `tutorial_category`, `aplicacion_category`,
  `difficulty`, `platform`; `class-taxonomies.php:71-251`). El plan y la decisión del usuario incluyen
  `/software/` dentro de la familia «categorías» a efectos de `noindex`.
- **Metas `robots` duplicadas**: en `?replytocom=` conviven la de WordPress (`wp_robots()`, hook
  `wp_head` prioridad 1) y la de TSF. Google aplica la más restrictiva (`noindex`).
- **Arnés de pruebas procedural** (`tests/test-valet.php`) con dobles en `tests/wp-stubs.php`, sin
  framework ni autoloader; `just test`, `just php-lint`, `just phpcs`, `just check-spec`.

### Línea base medida en producción (2026-11-22)

Confirmada con `curl` contra `https://atareao.es` (tareas 1.2 y 1.4). Es el **estado de partida**
que el change debe mover solo donde toca:

| Superficie | HTTP | `robots` de partida |
|---|---|---|
| `/search/linux/` | 200 | `max-snippet:-1,max-image-preview:large,max-video-preview:-1` (indexable) |
| `/search/linux/page/5/` | 200 | indexable |
| `/search/page/81/` | 200 | `noindex,…` (0 resultados) |
| `/search/` | 404 | sin término (ya no indexable) |
| `/software/` | 200 | indexable (143 páginas) |
| `/software/page/3/` | 200 | indexable |
| `/ubuntu/` | 200 | indexable (72) |
| `/como/` | 200 | indexable (40) |
| `/blog/` | 200 | indexable (debe seguir igual) |
| `/author/atareao/` | 200 | indexable (debe seguir igual) |
| `/tutorial/introduccion-al-diario-de-un-crustaceo/` | 200 | (singular) |
| `/tutorial/opencode/instalar-opencode/` | 404 | sin equivalente aún |
| `/instalar/vmm` | 404 | sin equivalente aún |
| `/tag/` | 404 | sin equivalente aún |

- **`robots.txt` (1.2):** servido de forma **virtual** por WordPress (TSF) a través del filtro
  `robots_txt`; lo consume `class-mcp-discovery.php:98` (`filterRobotsTxt()`, cuerpo en 396) que
  añade `Agentmap:`. Directivas de partida, en este orden: `User-agent: *`, `Disallow: /wp-admin/`,
  `Allow: /wp-admin/admin-ajax.php`, `Sitemap: https://atareao.es/sitemap.xml`,
  `Agentmap: https://atareao.es/.well-known/ard.json`. No hay fichero en disco.
- **`301` existentes (1.3):** el patrón del plugin vive en `class-seo.php` con métodos `\Atareao\*`:
  `redirectSitemapIndex()` en `parse_request` prioridad 1 (cuerpo en 658) y
  `redirectLegacyAuthorNicename()` en `template_redirect` prioridad 2 (cuerpo en 694); ambos emiten
  `wp_safe_redirect($target, 301)` + `exit`. El mapa nuevo de `legacy-redirects` reutiliza este
  patrón (`sitemapRedirectTarget()`/comparación de path y preservación de sufijo y query).

## Goals / Non-Goals

**Goals:**

- Que búsquedas y archivos de categoría dejen de ser indexables, sin degradar los listados que se
  mantienen indexables ni el contenido singular.
- Cortar el rastreo de `?replytocom=` y `/search/` desde `robots.txt`.
- Tapar las familias de `404` con equivalente mediante `301`.
- Dejar **una sola** etiqueta `robots` por documento.
- Que todo lo anterior sea verificable con el arnés procedural y re-medible con `just gsc`.

**Non-Goals:**

- Rediseño, contenido editorial, sitemap o exclusión de IDs (fuera de alcance, ver proposal).
- Instalar otro plugin SEO ni tocar el core de TSF (no está versionado).
- Tocar `nginx/pro/wp.conf`: no hace falta; el `robots.txt` y los `301` se resuelven en PHP.
- Perseguir indexación (es higiene de rastreo, no crecimiento).

## Decisions

### D1. Fuente de verdad del `noindex`: quién emite la etiqueta `robots`

**RESUELTA = alternativa A** (tarea 1.1, verificada contra el TSF 5.2.1 que corre en producción).
Hay tres caminos, y se descartan B y C por los motivos que se dan abajo:

- **A — TSF sigue siendo el único emisor; el plugin aporta *las reglas*. (ELEGIDA).** El plugin añade
  el `noindex` por un filtro **real y verificado** de TSF sobre el array de `robots`, de modo que la
  etiqueta la sigue componiendo TSF (conserva `max-snippet`/`max-image-preview`, etc.) y se mantiene
  una **sola** etiqueta —lo que resuelve también el requisito `robots-meta`—.
- **B — WordPress core como único emisor.** Se emite por el filtro core `wp_robots` (existe desde WP
  5.7, estable y verificable sin TSF) y se retira del `<head>` la etiqueta de TSF. **Descartada:**
  degrada el resto de directivas de robots que TSF aporta (`max-snippet`, `max-image-preview`,
  `max-video-preview`) y desacopla la política de robots de la herramienta SEO del sitio; además el
  filtro de TSF seguiría emitiendo su etiqueta salvo que se desenganchara, con el riesgo de dos
  etiquetas que este mismo change quiere evitar.
- **C — Ajuste en la UI de TSF (sin código).** Fuente de verdad en producción; cero riesgo de
  divergencia con TSF. **Descartada como primaria:** no está versionada, no la ejerce el arnés TDD
  (invisible a `just test`), no es reproducible en CI y el repo ya pagó ese precio con los títulos de
  la portada. Queda como **respaldo manual** si algún día el filtro deja de existir.

**Hechos verificados (2026-11-22, TSF 5.2.1 activo en producción):**

- **Filtro:** `the_seo_framework_robots_meta_array`, aplicado en
  `autodescription/inc/classes/meta/robots.class.php:170`. Firma:
  `apply_filters( 'the_seo_framework_robots_meta_array', array $meta, ?array $args, int $options )`.
  Registrado en `tests/t10-residuals/verified-hooks.php` como sexto hook verificado; la guardia
  (`tsf_hook_violations()`) pasa **sin TSF instalado** porque el plugin solo lo registra, no lo
  inventa.
- **`$meta` (array asociativo, valor booleano en origen):** `noindex`, `nofollow`, `noarchive`,
  `max_snippet`, `max_image_preview`, `max_video_preview`. Poner `$meta['noindex'] = true` produce
  `noindex`. TSF filtra **una sola vez** este array y emite **una sola** meta `robots`, así que este
  filtro resuelve a la vez el requisito `search-indexing` y la invariante `robots-meta`.
- **Opciones de TSF en producción, todas en off:** `search_noindex=0`, `noindex_taxonomies=[]`,
  `noindex_post_types=[]`, `paged_noindex=0`, `home_paged_noindex=0`, `author_noindex=0`,
  `date_noindex=0`. Son el **estado de partida**: hoy TSF solo marca `noindex` cuando la búsqueda no
  tiene resultados (lo decide por su cuenta, no por `search_noindex`).

**Por qué filtro versionado y no tocar las opciones de TSF:** las opciones viven en la base de datos
de producción, **no están versionadas** y son **invisibles al arnés TDD** (`just test` corre sin
WordPress). Un `noindex` que solo existe en la base de datos no se puede probar ni revisar en el
repo; el filtro sí, y además es el único mecanismo que el arnés puede custodiar. Esto es exactamente
la lección del issue **#200**.

### D2. El texto del `noindex` es `noindex, follow`, no `noindex, nofollow`

Se copia la convención que ya usa el sitio para `?replytocom=` (WordPress emite `noindex, follow`).
`follow` conserva el flujo de enlaces internos; el sitio quiere dejar de *indexar*, no cortar el
rastreo por enlaces. Alternativa descartada: `noindex, nofollow` (aislaría página a página y
empobrecería el descubrimiento sin ganancia).

### D3. Ámbito de `category-archives`: incluye `/software/` aunque sea archivo de CPT

La spec cubre los archivos de término y, además, las familias medidas en GSC. `/software/` es un
archivo de **CPT**, no una categoría; se incluye porque es la familia más paginada medida (143
páginas) y así lo decidió el usuario (plan §3). Se **excluyen** los archivos hermanos que se
mantienen indexables (`/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/`) y el contenido
singular. La divergencia `/software/` vs sus hermanos es deliberada y queda documentada.

- **Alternativas descartadas:** tratar `/software/` como term archive (no lo es; se implementaría en
  el sitio equivocado); noindex en **todos** los archivos de CPT (tumbaría los hubs de descubrimiento
  que el change no quiere tocar).

### D4. `robots.txt` por el filtro `robots_txt`, sin fichero estático

Las dos directivas se añaden concatenando al final de la salida del filtro `robots_txt`, sobre el
mismo `$output` que ya recibe `Agentmap:`. Se escoge **un** callback nuevo, idempotente (no duplica
si la línea ya está) y que **no** toca el callback existente de `class-mcp-discovery.php`. Se
descarta un fichero `robots.txt` en disco (el sitio no lo tiene y no está versionado) y nginx
(`§7 del plan`: cambio quirúrgico e innecesario).

- **Alternativas descartadas:** editar `class-mcp-discovery.php` (mezclaría la política de rastreo con
  el descubrimiento ARD, dos historias distintas); fichero estático (rompe el virtual y la
  distribución del sitemap dinámico).

### D5. El mapa `301` vive en el plugin y precede al `404`

Se implementa con el mismo patrón que las redirecciones ya existentes en `class-seo.php`
(`wp_safe_redirect($target, 301)` + `exit`, con `sitemapRedirectTarget()`/comparación de path y
preservación de sufijo y query). Se engancha en `template_redirect` con prioridad baja (antes de que
se emita el `404` de la plantilla) —o en `parse_request` si el caso lo exige, como
`redirectSitemapIndex()` que debe preceder el endpoint de sitemap de TSF a prioridad 15—. El mapa se
declara como una estructura de datos (path → destino) para que sea mínimo y testeable.

- **Alternativas descartadas:** `nginx` (`map` de `301`) —no está en el alcance de este repo y el plan
  pide no tocar `wp.conf`—; dispersar `301` en plantillas (inmantenible).

### D6. Una sola etiqueta `robots`: el mismo emisor único de D1

`robots-meta` se resuelve por la misma decisión de emisor único: si TSF compone la etiqueta (D1-A),
el `noindex` de comentario ya proviene de TSF y **lo que sobra es la de WordPress**; si no, se
retira la de TSF. En cualquiera de los casos, la regla es **un solo emisor** y las páginas que pasan
a `noindex` por D1 **no** añaden una segunda etiqueta. La invariante se prueba contando etiquetas en
el HTML servido/compuesto.

### D7. Verificación sin TSF instalado (anti-falso-verde)

El arnés procedural corre sin TSF ni WordPress reales (dobles en `tests/wp-stubs.php`). Los
escenarios se prueban: (a) en el punto de extensión del plugin con dobles de las condicionales
(`is_search()`, `is_home()`, `is_singular()`, `is_archive()`, `is_post_type_archive()` ya existían en
`tests/wp-stubs.php`; los dobles `is_category()` e `is_paged()` **se añadieron** en el bloque 3, no
existían antes —corrección de lo que decía este design—); (b) en la salida
del filtro `robots_txt` con una base de directivas de prueba; (c) en el mapa `301` con peticiones
sintéticas. La emisión real de la etiqueta por TSF se verifica **en el HTML servido** (deploy). La
guardia `verified-hooks.php` SHALL pasar sin TSF instalado.

## Risks / Trade-offs

- **Hook de TSF inventado (repetir #200).** → No se usa ningún hook `the_seo_framework_*` que no esté
  en la guardia; la tarea de preparación lo verifica contra 5.2.1 antes del GREEN.
- **Dos emisores → dos etiquetas `robots`.** Añadir `noindex` por `wp_robots` mientras TSF también
  emite crea una segunda etiqueta. → Decisión de emisor único (D1/D6); escenario explícito de conteo.
- **`Disallow: /search/` es de medio plazo y limita el diagnóstico.** Google tarda en reprocesar
  `robots.txt`. → Aceptado y documentado; es el comportamiento estándar. Reversible quitando la línea.
- **`noindex` en categorías puede reducir el descubrimiento** si alguna rankeaba. → Re-medición con
  `just gsc` a 28 días por página × query; revisar impresiones por categoría antes/después.
- **Mapa `301` como deuda de mantenimiento.** → Mantenerlo mínimo, una entrada por familia, con
  tests; completar solo con la lista real de los 352 `404` (ver Open Questions).
- **TSF en producción alberga la configuración real.** → Este design fija **una** fuente de verdad
  (D1) y la documenta en `docs/indexacion.md`; no se mezclan UI y código.

## Migration Plan

1. Aprobar este change (`just check-spec` en verde) y resolver **D1** (aprobación del usuario).
2. **Preparación:** verificar el hook de robots de TSF 5.2.1 (o decidir D1-C), volcar las directivas
   actuales de `robots.txt` y confirmar el patrón de `301` existente.
3. **TDD por bloques**, en el orden de `tasks.md`: búsquedas → categorías → `robots.txt` → mapa `301`
   → meta única. RED con `just test`; GREEN con `just test` + `just php-lint`; REFACTOR con
   `just phpcs` sobre los ficheros tocados.
4. **Deploy** del plugin (revertir el commit para el rollback) y purga de caché si aplica.
5. **Verificación en producción:** `curl` a las superficies (búsquedas, categorías, `robots.txt`,
   familias `301`, meta única).
6. **Re-medición** a 28 días con `just gsc compara --days 28`, registrada en `docs/indexacion.md`.
7. **Rollback:** revertir el commit del plugin; si el `noindex` se aplicó por la UI de TSF (D1-C),
   restaurar el ajuste anterior en producción.

## Open Questions

- **Nombre exacto del filtro de `robots` en TSF 5.2.1** (o su ausencia) y `fichero:línea`: se resuelve
  en la tarea 1.1 leyendo el plugin instalado. No cambia las specs (observan la etiqueta) ni el
  enfoque; sí el punto de extensión de D1.
- **Lista completa por URL de los 352 `404`:** el export de GSC dado (`indexacion/*.csv`) es agregado
  (motivo + recuento), sin URLs. El mapa `301` arranca con las tres familias conocidas
  (`/tutorial/opencode/*`, `/instalar/*`, `/amp/`) y se completa cuando se disponga de la lista por
  URL; la spec no enumerará URLs concretas.
