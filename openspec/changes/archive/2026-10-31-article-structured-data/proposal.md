# article-structured-data

## Why

El grafo JSON-LD del sitio lo emite **The SEO Framework** en un único `<script
type="application/ld+json">`. Comprobado hoy sobre el sitio en producción: en la portada contiene
`WebSite`, `WebPage` y `Person`, y en una entrada del blog contiene **solo `WebSite` y `WebPage`**. No
hay `Article` ni `BlogPosting`, ni `author`, ni `datePublished`, ni `dateModified`, ni la imagen del
contenido. El grafo describe la **página** pero no el **artículo**.

Para un buscador o un consumidor agéntico, eso significa que el contenido publicado no tiene autor
atribuido, ni fecha de publicación, ni fecha de actualización: los tres datos que Google exige para el
resultado enriquecido de artículo y que cualquier sistema de atribución o de recencia necesita. La
extensión *Articles* de The SEO Framework, que añadiría ese markup, **no está instalada**.

El propio framework expone un filtro público para **extender su grafo** sin emitir un segundo bloque
`ld+json`. Es la vía correcta y es la única que este change usa: **un solo grafo**, entidades
enlazadas por `@id`.

## What Changes

- Se añade al grafo del framework una entidad **`Article`/`BlogPosting`** para cada contenido editorial
  singular, con `headline`, `datePublished`, `dateModified`, `author`, `publisher`, `image` y
  `mainEntityOfPage`.
- La entidad se **integra en el grafo existente** y **reutiliza por referencia** (`@id`) las entidades
  que el framework ya emite (`WebSite`, `WebPage`, `Person`, `Organization`), en lugar de duplicarlas.
- El markup **no inventa datos**: una propiedad cuyo valor no exista se omite.

### Decisiones de alcance

**Capability nueva `structured-data`, no una extensión de `seo`.** La spec de `seo` define la
resolución de la **descripción** SEO de un post, y su `Purpose` no cubre el grafo de datos
estructurados. Colgar aquí los requisitos del grafo dejaría una spec cuyo propósito no describe su
contenido; la separación mantiene cada capability coherente con lo que documenta.

**Un solo grafo, nunca un segundo `ld+json`.** El framework ya documenta que su extensión Articles
emite un bloque aparte «by design», pero aquí no hay extensión: lo correcto es **añadir la entidad al
grafo** que el framework compone, de modo que `mainEntityOfPage` apunte al `@id` de la `WebPage` y no
haya dos descripciones de la misma página compitiendo. Un escenario fija que la emisión sigue siendo
**un único bloque**.

**Se cubren los tipos editoriales escritos** (`post`, `tutorial`, `capitulo`, `aplicacion`,
`software`), que son el contenido al que el markup de artículo aplica.

**Fuera de alcance, en un change propio:** el dato estructurado de los **episodios y la serie de
podcast** (`PodcastEpisode`, `PodcastSeries`). Tiene su propio vocabulario, depende del feed y merece
su propia revisión; mezclarlo aquí haría un change con dos historias y dos conjuntos de escenarios sin
relación.

**No se inventan datos ni se emite lo que Google retiró.** Sin autor, fecha o imagen, la propiedad se
omite. No se emite `HowTo`, `FAQ` ni `Review`: Google retiró los dos primeros y desaconseja el tercero.

**No se toca** el `<title>`, la descripción, el canonical, el sitemap ni los ajustes del framework.

## Capabilities

### New Capabilities

- `structured-data`: el dato estructurado (JSON-LD) que el sitio publica para su contenido editorial,
  compuesto **dentro del grafo** del plugin de SEO del sitio, con las entidades de artículo y sus
  referencias cruzadas.

### Modified Capabilities

*(ninguna)*

## Impact

- **Spec:** nueva capability `structured-data` — 2 requisitos ADDED.
- **Código:** `wp-content/plugins/atareao-functionality/includes/class-seo.php` (que ya registra los
  filtros del framework) o un módulo propio del plugin con el mismo contrato.
- **Config:** ninguna.
- **Tests:** `tests/test-valet.php`, `tests/wp-stubs.php` (el callback se puede ejercitar con un grafo
  de entrada y un contenido de prueba, sin necesitar el framework instalado).
- **Dependencia de runtime:** The SEO Framework (ya presente). **Sin dependencias nuevas. Sin cambios
  de esquema de datos. Sin migración.**
