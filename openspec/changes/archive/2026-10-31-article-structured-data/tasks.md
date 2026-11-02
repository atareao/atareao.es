# Tasks: article-structured-data

## 0. Preparación

- [x] 0.1 Confirmar el punto de extensión del grafo del plugin de SEO
      (`the_seo_framework_schema_graph_data`) y decidir si el markup vive en `class-seo.php` o en un
      módulo propio con el mismo contrato
- [x] 0.2 Capturar el grafo **real** de producción (portada y una entrada) como referencia de partida:
      hoy contiene `WebSite`/`WebPage` (`Person` en la portada) y **ninguna** entidad de artículo
- [x] 0.3 Determinar los `@id` que el framework usa para `WebPage`, `Person` y la entidad editorial, para
      poder referenciarlos sin duplicarlos
- [x] 0.4 Confirmar en el arnés (`tests/wp-stubs.php`) los stubs necesarios para ejercitar el callback
      con un grafo de entrada y un contenido de prueba, **sin** el framework instalado
- [x] 0.5 Fijar la lista de tipos de contenido editorial cubiertos y confirmar que `page` queda fuera

## 1. RED — los tests primero, y que fallan

- [x] 1.1 El grafo de un contenido editorial singular incluye una entidad `Article`/`BlogPosting`
- [x] 1.2 La salida sigue siendo **un único** bloque `ld+json` (no se añade un segundo script)
- [x] 1.3 `headline` y las fechas (`datePublished`/`dateModified`) se declaran en ISO 8601
- [x] 1.4 `mainEntityOfPage.@id` es idéntico al `@id` de la entidad `WebPage` del grafo
- [x] 1.5 El autor y la entidad editorial se referencian por `@id` y no se duplican
- [x] 1.6 La imagen destacada se describe como `ImageObject` con URL absoluta
- [x] 1.7 Los datos ausentes (autor, imagen, fecha de modificación) **se omiten** y no se inventan
- [x] 1.8 No se emite en archivos, portada, búsqueda ni error; ni para contenido no público
- [x] 1.9 Los tipos y `@id` de las entidades preexistentes no cambian
- [x] 1.10 El grafo resultante es JSON-LD válido
- [x] 1.11 Ejecutar `just test` → los escenarios nuevos en **ROJO**; el resto en **VERDE**

### Evidencia — fase RED

**Preparación (0.x).** Punto de extensión confirmado contra la documentación oficial de The SEO
Framework: el filtro `the_seo_framework_schema_graph_data` recibe `( $graph, $args )`, donde `$graph`
es un **array secuencial de entidades** sin el envoltorio `@context`/`@graph` —TSF lo monta después— y
`$args` es `null` cuando se autodetermina. La entidad nueva se **añade al array** y se referencia por
`@id`; `@type` admite cadena **o array**. El markup vive en `class-seo.php`, que ya registra los
filtros de TSF.

**Grafo real de producción (capturado el 2026-10-30).** Portada: `WebSite` + `WebPage` + `Person`.
Entrada singular: `WebSite` + `WebPage`, **sin** `Article`/`BlogPosting`; la `WebPage` **ya** declara
`author` (`@id` `…/#/schema/Person/<hash>`), `datePublished` y `dateModified`, y la `WebSite` declara
`publisher` (`@id` `…/#/schema/Person`). De ahí salen las dos referencias que exigen los tests.

**Contratos que el test fija y que la spec dejaba al criterio de la implementación:**

1. `headline` es el **título crudo** del contenido (`get_the_title`), sin el sufijo de marca que TSF
   añade al `name` de la `WebPage`.
2. `dateModified` **se omite** cuando la fecha de modificación es igual a la de publicación (nunca
   modificado), que es exactamente el criterio con el que TSF decide no emitirla.
3. Si el grafo **no declara ninguna entidad `WebPage`**, no hay `@id` al que enlazar
   `mainEntityOfPage`: **no se emite** la entidad de artículo.

**Comando y resultado.** `just test` (contenedor `wordpress:cli-php8.3`):

- Línea base antes de la fase RED: `Total passed: 785, Total failed: 0`
- Tras escribir los tests: `Total passed: 802, Total failed: 26` (`EXIT=1`, como exige el rojo)
- **Los 26 fallos llevan todos el prefijo `(sd)`**: ninguna aserción preexistente se ha roto.
- 17 de las 43 aserciones nuevas pasan ya en rojo: son **guardas de no regresión** (ausencia de un
  segundo bloque `ld+json`, identidad del filtro fuera del singular y para contenido no público,
  `page`/`podcast` excluidos, entidades preexistentes intactas, JSON válido y escapado del bloque). La
  costura no existe todavía, así que las otras **26** fallan.

**Cómo se ejercita la costura sin el framework instalado.** No se llama a ningún método nuevo del
plugin ni se adivina su nombre: se reejecuta `\Atareao\SEO::init()` (el envío real del plugin) y se
aplica `apply_filters( 'the_seo_framework_schema_graph_data', $grafo, null )`. La costura se declara
por el **grafo de entrada** y el **contenido de prueba**, nunca por la firma del método.

**Ficheros.** `tests/test-valet.php` (suite `=== Running Article Structured Data Test Suite ===`, 580
líneas nuevas) y `tests/wp-stubs.php` (563 líneas nuevas: modelo del ciclo de plantilla —`is_singular`,
objeto consultado, `post_password_required`, fechas, imagen destacada, autor— más la ampliación de
`WP_Post` con `post_modified`/`post_date_gmt`/`post_modified_gmt`). **Ningún fichero de producción se
ha tocado** (`git status --short` solo lista los dos ficheros de test).

**Decisión de alcance reforzada en RED:** `podcast` queda **fuera** de la emisión —tiene su propio
change— y así lo fija un escenario propio; `page` también queda fuera.

## 2. GREEN — implementación mínima

- [x] 2.1 Registrar el filtro del grafo desde el módulo que decida la tarea 0.1
- [x] 2.2 Componer la entidad de artículo con `headline`, fechas, `mainEntityOfPage`, `author`,
      `publisher` e `image`, referenciando por `@id` lo que el grafo ya declara
- [x] 2.3 Omitir las propiedades sin valor (sin defaults inventados)
- [x] 2.4 Restringir la emisión a contenido singular publicado y público de los tipos cubiertos
- [x] 2.5 `just test` → **100% verde**; `just php-lint` limpio

### Evidencia — fase GREEN

**Dónde vive.** `wp-content/plugins/atareao-functionality/includes/class-seo.php`, el mismo módulo que
ya registraba los filtros de The SEO Framework (tarea 0.1). Se añade un enganche y un bloque de métodos
privados; **ningún módulo nuevo**.

**Enganche.** `add_filter( 'the_seo_framework_schema_graph_data', array( __CLASS__, 'addArticleSchema' ),
10, 2 )` junto al resto de filtros en `SEO::init()`. Dos argumentos aceptados: `( $graph, $args )`.

**Implementación.** `addArticleSchema()` como único punto de entrada, con cinco ayudantes privados para
no duplicar recorridos ni decisiones:

- `resolveSchemaPost()` — resuelve la entrada (`$args['id']` si llega, si no la consultada; respaldo a
  `get_queried_object()`).
- `isArticleSchemaPost()` — tipo editorial, estado `publish` y `post_password_required()`.
- `findSchemaEntity()` — localiza una entidad por `@type`, admitiendo `@type` como cadena **o array**.
- `buildArticleEntity()` — compone la entidad añadiendo al array, sin reescribir ninguna existente.
- `resolveSchemaAuthor()`, `resolveSchemaPublisher()`, `resolveSchemaImage()` — las referencias por `@id`
  cuando el grafo ya las declara, y la omisión de la propiedad cuando no hay dato.

**Comandos y resultado (literal).**

- `just test` → **`Total passed: 828, Total failed: 0`**, `EXIT=0`. La suite
  `=== Running Article Structured Data Test Suite ===` pasa de **26 fallos** en rojo a **0 fallos**:
  **43 PASS / 0 FAIL**.
- `just php-lint` → limpio (ningún fichero del tema ni del plugin con error de sintaxis).
- `just phpcs wp-content/plugins/atareao-functionality/includes/class-seo.php` →
  `FOUND 0 ERRORS AND 2 WARNINGS AFFECTING 2 LINES`. Los dos avisos son **preexistentes** en `HEAD`
  (`PSR1.Files.SideEffects` en la cabecera y una línea larga hacia la 170); no son de este change.
- **Efecto colateral positivo:** el fichero en `HEAD` arrastraba un `ERROR` de PSR12
  (`Expected 1 newline at end of file; 0 found`) que el GREEN deja corregido.

**Alcance.** `git status --short` y `git diff --stat` muestran **solo**
`wp-content/plugins/atareao-functionality/includes/class-seo.php` (256 inserciones, 1 supresión: el
bloque nuevo y el salto de línea final). Los tests no se han tocado: son el contrato.

## 3. REFACTOR

- [x] 3.1 Una sola función de composición; sin ramas duplicadas por tipo de contenido
- [x] 3.2 Sin entidades duplicadas: todo lo que ya está en el grafo se referencia por `@id`
- [x] 3.3 `just phpcs` sin violaciones nuevas en los ficheros tocados; `just php-lint-changed` limpio

### Evidencia — fase REFACTOR

Sin cambio de comportamiento (`828 / 0` antes y después):

1. **Una sola lista de tipos.** Los cinco tipos editoriales suben a
   `private const ARTICLE_SCHEMA_TYPES = array( 'post', 'tutorial', 'capitulo', 'aplicacion', 'software'
   );`, con docblock que deja escrito **por qué** `page` queda fuera (a propósito) y `podcast` también
   (tendrá su propio change). `isArticleSchemaPost()` la consume; es la única lista de tipos del fichero.
2. **`resolveSchemaPost()` en un solo punto.** Se elimina el inicializador y las dos asignaciones en
   ramas: la entrada se resuelve en una expresión ternaria y el respaldo a `get_queried_object()` queda
   como una única comprobación posterior. Semántica idéntica.
3. **`@type` uniforme `Article`** para los cinco tipos editoriales: sin ramas por tipo de contenido
   (reafirma 3.1).

**Verificación (literal).** `just test` → `Total passed: 828, Total failed: 0`, `EXIT=0`; suite de
artículo **43 PASS / 0 FAIL**. `just php-lint-changed` → sin errores de sintaxis.
`just phpcs wp-content/plugins/atareao-functionality/includes/class-seo.php` → **`FOUND 0 ERRORS`** y
los mismos 2 warnings preexistentes. `git status --short` → solo `class-seo.php`.

## 4. Verificación

- [x] 4.1 `openspec validate article-structured-data --strict` → valid
- [x] 4.2 `just check-spec` → en verde
- [x] 4.3 Auditoría: escapado de los valores que llegan del contenido (títulos, extractos, URLs) para
      que no puedan romper el JSON ni inyectar marcado
- [x] 4.4 Comparar el grafo producido con el vocabulario schema.org y con las propiedades exigidas para
      el resultado enriquecido de artículo
- [x] 4.5 Verificación E2E en producción tras el despliegue: `Article`/`BlogPosting` presente en una
      entrada publicada, un único bloque `ld+json`, y `mainEntityOfPage` enlazado con la `WebPage`

### Evidencia — verificación

**4.1 / 4.2.** `openspec validate article-structured-data --strict` → *Change 'article-structured-data'
is valid*. `just check-spec` → valida los dos changes activos (`article-structured-data`,
`ai-catalog-enrichment`) y sale con `EXIT=0`.

**4.3 — auditoría de escapado (auditor de backend, solo lectura, 0 hallazgos).** El plugin **no
serializa** el JSON-LD: solo **añade datos** (un array PHP) al grafo y es The SEO Framework quien compone
e imprime el `<script type="application/ld+json">`. Contraprueba empírica con un título y un autor
maliciosos (`"</script><script>alert(1)</script> & <img src=x onerror=…>`) sobre la serialización
estándar del framework (`wp_json_encode`, sin `JSON_UNESCAPED_SLASHES`): **18 PASS / 0 FAIL**, y el
*control negativo* (concatenar el JSON sin escapar) demuestra que el mismo payload **sí** rompería el
bloque, de modo que el resultado no es trivial. En el bloque resultante `</script` aparece **una sola
vez** (la del cierre), el `json_decode` interno conserva `@context`/`@graph` y el `headline` recuperado
es **idéntico** al título original; un parser HTML ve **1 `<script>`** (el envoltorio) y **0 `<img>`**.
El plugin **no emite ningún bloque `ld+json` por su cuenta** (`wp_head` → 0 bloques).

> *Riesgo residual (no es hallazgo, no explotable hoy):* la defensa descansa en que el framework use
> `wp_json_encode` con los flags por defecto (escapa `/` → `<\/script`). El plugin no puede imponer los
> flags de un tercero; es una dependencia de integración verificable en el despliegue.

**4.4 — cotejo con el vocabulario y el resultado enriquecido.** Contra la referencia oficial de Google
para `Article` (`Article`/`NewsArticle`/`BlogPosting`; **todas las propiedades son recomendadas, ninguna
obligatoria**):

| Propiedad Google | Emitida | Comprobación |
|---|---|---|
| `@type` | `Article` | uno de los tres tipos admitidos ✔ |
| `headline` | sí | `Text`, título crudo del contenido ✔ |
| `image` | sí | `ImageObject` con `url` **absoluta** y `width`/`height` (1200×675 = 16:9, > 50 000 px) ✔ |
| `datePublished` | sí | `DateTime` **ISO 8601 con zona horaria** ✔ |
| `dateModified` | cuando aplica | `DateTime` ISO 8601; **se omite** si nunca se modificó (es opcional) ✔ |
| `author` | sí | `Person`: se referencia el `@id` del `Person` que el grafo ya declara, del que resuelven `name` y `sameAs`/`url`; `Person` de respaldo con `name`+`url` cuando el grafo no lo declara ✔ |
| `publisher` | sí | referencia `@id` a la editorial que declara la `WebSite` ✔ |

`mainEntityOfPage` no está en la lista de Google, pero sí en schema.org y es lo que enlaza el artículo con
la `WebPage` del grafo (requisito de este change). **E2E local de composición** con el **grafo JSON-LD
real de producción** inyectado en el callback (13 aserciones, **13 PASS / 0 FAIL**): la página sigue
declarando **un único** bloque `ld+json`; la entidad `Article` lleva `headline` = título crudo,
`mainEntityOfPage.@id` = permalink real de la entrada, `author` = **solo** el `@id`
`…#/schema/Person/<hash>` real y `publisher` = **solo** el `@id` `…#/schema/Person` real, `image`
`ImageObject` con la URL real del adjunto, fechas ISO coherentes; las entidades `WebSite` y `WebPage`
originales quedan **byte a byte idénticas**.

**4.5.** *Pendiente:* exige el despliegue a producción (lo ejecuta el titular). La E2E local de arriba
cubre la composición y el enlace; en producción queda confirmar que el bloque real de TSF incorpora la
entidad.

### Evidencia — 4.5, verificación E2E en producción

Sondeos con User-Agent de navegador (el bloqueo por User-Agent del proxy cierra la conexión a `curl` sin él)
sobre páginas ya desplegadas, analizando el bloque `ld+json` con un parser de verdad.

**Tipos cubiertos — la entidad está y está integrada:**

| Página | Bloques `ld+json` | Entidades del grafo | `Article` | `mainEntityOfPage.@id` == `WebPage.@id` |
|---|---|---|---|---|
| `/tutorial/tutorial-de-opencode/que-es-opencode-del-equipo-sst-a-165k-estrellas/` (`capitulo`) | **1** | `WebSite`, `WebPage`, `Article` | **sí** | **sí** |
| `/tutorial/tutorial-de-opencode/` (`tutorial`) | **1** | `WebSite`, `WebPage`, `Article` | **sí** | **sí** |
| `/software/pgcli-el-cliente-postgresql-que-te-hara-olvidar-psql/` (`software`) | **1** | `WebSite`, `WebPage`, `Article` | **sí** | **sí** |

En los tres, la entidad declara `headline`, `datePublished` y `dateModified` en ISO 8601
(`2026-09-30T10:00:00+02:00`), `author` y `publisher` referenciados **por `@id`** a la `Person` que ya
declaraba el grafo (sin repetir sus datos), e `image` como `ImageObject` con URL **absoluta** y dimensiones
(`2048×2048`). El `@context` es `https://schema.org` y el grafo sigue siendo **un solo bloque**.

**Cobertura negativa — donde no debe aparecer, no aparece:**

| Página | Estado | Bloques | `Article` |
|---|---|---|---|
| `/` (portada) | 200 | 1 | **no** |
| `/blog/` (archivo) | 200 | 1 | **no** |
| `/?s=opencode` (búsqueda) | 200 | 1 | **no** |
| `/esto-no-existe-404-x/` | 404 | 1 | **no** |
| `/podcast/podman-ips-reales-y-la-liga/` (`podcast`, tipo **no** cubierto) | 200 | 1 | **no** |

El bloque sigue siendo exactamente **uno** en todas ellas (las entidades del plugin de SEO no se alteran),
que es la no interferencia que exige el requisito. Nota metodológica: el bloque se comprueba **desde el
`ld+json` servido**, no desde el valor de retorno del código; es la misma lección que el §6 de
`podcast-feed-namespace` — medir el documento, no la función.

**Riesgo residual de 4.3, comprobado en el despliegue:** no se ha observado ningún bloque roto ni marcado
inyectado en el HTML servido de las páginas probadas.

## 5. Entrega

- [x] 5.1 PR `feature/article-structured-data → development` con **merge commit** (convención del proyecto)
      — fusionado ([#129](https://github.com/atareao/atareao.es/pull/129), `79569be`)
- [x] 5.2 Archivar el change fusionando el delta en `openspec/specs/structured-data/spec.md` — archivado: el delta de `structured-data` se volcó en la spec y el change quedó en `openspec/changes/archive/`

## Notas de implementación

- **Un solo grafo.** El requisito «un único bloque» es el que impide el atajo de emitir un segundo
  `<script>` a mano; cualquier implementación que lo haga incumple el change.
- **Referenciar, no duplicar.** Si el framework ya declara la persona autora o la entidad editorial, se
  referencian por `@id`. Duplicarlas crearía dos descripciones del mismo nodo y una posible
  contradicción.
- **Omitir es correcto.** Una propiedad ausente es mejor que una falsa: no se rellenan fechas o autores
  por conveniencia.
- **El podcast queda fuera.** `PodcastEpisode` y `PodcastSeries` necesitan su propio change; su
  vocabulario y sus dependencias no son los del artículo.
