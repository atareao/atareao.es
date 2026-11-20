# Design: Perfiles externos (`sameAs`) en la entidad `Person` del grafo

## Context

Ver `proposal.md` — Why. Estado medido en producción el 2026-11-20 (nginx en bypass con `?cb=`):

| Superficie | Entidad `Person` del grafo | `sameAs` |
|---|---|---|
| `/` (portada) | `@graph[2]`, **de primer nivel**, con `name` y `url` | 9 perfiles |
| `/author/atareao/` | `WebSite.publisher` **en línea** (`name`, `url`) | **ninguno** |
| `/blog/`, `/podcast/`, `/tutoriales/`, `/aplicaciones/`, `/software/` | `WebSite.publisher` **en línea** | ninguno |
| ficha de episodio o de tutorial | `WebSite.publisher` en línea **y** `WebPage.author` (con `sameAs` = solo `x.com`) | parcial |

`enrichPersonProfiles()` (`class-seo.php:948`) recorre `$graph` como lista de primer nivel y enriquece
la `Person` que encuentra ahí. Funciona en la portada porque el plugin de SEO emite la `Person` como
entidad de primer nivel; en el resto de superficies la **incrusta** dentro de `WebSite.publisher`,
fuera del alcance del bucle. `mergeProfiles()` (`:979`) fusiona perfiles de forma aditiva y
deduplicada, y `socialProfiles()` (`:1040`) lee las opciones `atareao_social_*` normalizando el canal
de YouTube.

## Goals / Non-Goals

- **Goal**: que toda entidad `Person` que el grafo *describa* publique `sameAs` con los perfiles del
  sitio, con independencia de su profundidad.
- **Goal**: no tocar `@type`, `@id`, el número de entidades ni el número de bloques `ld+json`.
- **Non-Goal**: no se añade `sameAs` a entidades que no sean `Person` (la `Organization` de la portada
  ya la resuelve `appendSiteOrganization()`) ni se crean entidades nuevas.
- **Non-Goal**: no se introduce un caso especial para la `Person` `author` que declara el framework por
  entrada: cae bajo la misma regla general.

## Decisions

### D1 — Recorrido recursivo del grafo, restringido a `Person` descritas

Se sustituye el bucle de primer nivel por un recorrido recursivo que desciende por listas y mapas y
enriquece toda entidad cuyo `@type` incluya `Person` **y** que declare `name` o `url`. Una referencia
pura (`{@id}` o `{@id, @type}`) no se toca.

- **Por qué**: el defecto es que el bucle no alcanza las `Person` anidadas; el arreglo natural es
  alcanzarlas. Identificar por `name`/`url` evita «inflar» referencias puras, que por diseño no llevan
  propiedades.
- **Alternativas descartadas**:
  - *Anclar solo a `WebSite.publisher`*: deja fuera cualquier otra `Person` anidada y añade un caso
    especial por posición en el grafo.
  - *Condicionar a `is_author()`*: arreglaría solo la página de autor y dejaría inconsistentes los
    listados y las fichas, que sufren el mismo hueco; además ataría la lógica al contexto de la
    petición y dificultaría las pruebas.
  - *Añadir una `Person` de primer nivel nueva*: duplicaría un `@id` que el framework ya declara.

### D2 — La fusión es aditiva y deduplicada

Se reutiliza `mergeProfiles()`, que conserva los perfiles existentes (acepta cadena o lista) y añade
los del sitio sin repetir. Orden resultante: existentes primero, luego `socialProfiles()`.

### D3 — «Descrita» = declara `name` o `url`

Criterio mínimo y estable. La `Person` del `publisher` en línea declara ambos; la referencia de la
portada declara solo `@id`; la `author` por entrada declara `name` (y su `sameAs` propio).

### D4 — Ubicación del arnés

El arnés vive en `tests/structured-data/`, autocontenido (contadores propios) y cargando solo
`tests/wp-stubs.php`, como los arneses de `tests/podcast-duration/`. Se añade a la receta `test-t10`
—que ya agrupa los arneses del plugin que corren en CI— y al paso «Test PHP harnesses» de
`.github/workflows/ci.yml`, para que la regresión quede cubierta en el check requerido.

## Risks / Trade-offs

- [El grafo crece en las páginas donde el `publisher` va en línea] → incremento pequeño (una lista de
  URLs), acotado a `Person` descritas y consistente en todas las superficies.
- [Recursión sobre estructuras inesperadas] → solo se desciende por `array`; los escalares y las
  listas de cadenas se ignoran; la condición exige `@type` con `Person` y `name` o `url`.
- [Regresión en la portada o en la `Organization`] → escenarios explícitos: la portada conserva sus
  perfiles y la `Organization` no cambia.

## Migration Plan

1. Cambio de código en el plugin (sin migración de datos ni de opciones).
2. Despliegue quirúrgico del fichero `class-seo.php` con `just deploy-prod` (sha256 y `owner=82:82`).
3. Verificación en producción con `?cb=` (bypass de nginx): `/author/atareao/` con `sameAs`; portada,
   listados y ficha comparados antes/después; un único bloque `ld+json`.
4. Rollback: restaurar el fichero anterior; el cambio está confinado a una función.

## Open Questions

(none)
