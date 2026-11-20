# Proposal: Perfiles externos (`sameAs`) en la entidad `Person` del grafo

## Why

El criterio de T10 «página de autor accesible con `sameAs`» **no se cumple**. En `/author/atareao/`
el grafo JSON-LD declara a la persona autora como una entidad `Person` **anidada** dentro de
`WebSite.publisher` y **sin** `sameAs`. El enriquecedor de perfiles externos solo recorre las
entidades `Person` de **primer nivel**, y esa página no tiene ninguna: se queda sin los perfiles.

En la portada sí aparecen porque allí el plugin de SEO sí emite una `Person` de primer nivel. La misma
carencia afecta al `publisher` de los listados (`/blog/`, `/podcast/`, `/tutoriales/`, `/aplicaciones/`,
`/software/`) y de las fichas singulares, donde la `Person` viene **en línea** (con `name` y `url`)
pero sin `sameAs`. La persona del sitio queda, pues, descrita a medias en todas las superficies salvo
la portada.

## What Changes

- El enriquecimiento de perfiles externos (`sameAs`) deja de limitarse a las entidades `Person` de
  primer nivel y alcanza **toda** entidad `Person` que el grafo **describa** (con `name` o `url`),
  anidada o no.
- Las **referencias puras** de `Person` (solo `@id`, sin propiedades propias) SHALL quedar intactas: no
  se convierten en nodos en línea.
- No se altera el `@type` ni el `@id` de ninguna entidad, ni se duplican perfiles ya presentes. Los
  perfiles se leen de las opciones `atareao_social_*` con la misma normalización del canal de YouTube
  que usa la entidad `Organization`.
- Efecto: `/author/atareao/` —y el `publisher` de listados y fichas— declara `sameAs` con GitHub,
  YouTube y las plataformas de podcast.

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `structured-data`: se añade el requisito «Perfiles externos en la entidad `Person` del grafo», que
  extiende la publicación de `sameAs` a las `Person` anidadas (hoy solo se enriquecen las de primer
  nivel) y fija que las referencias puras permanezcan intactas.

## Impact

- Código: `wp-content/plugins/atareao-functionality/includes/class-seo.php` — el enriquecedor
  `enrichPersonProfiles()` (línea 948) y su uso desde `addArticleSchema()` (línea 697), enganchado al
  filtro `the_seo_framework_schema_graph_data`.
- Datos estructurados servidos: `/author/atareao/` gana `sameAs` (el arreglo); los listados y las
  fichas singulares ganan `sameAs` en su `Person` `publisher` (efecto consistente); la portada no
  cambia.
- Sin cambios en `@type`, `@id`, número de entidades ni número de bloques `ld+json`; sin migración de
  datos ni cambios de configuración.
- Tests: nuevo arnés `tests/structured-data/test_person_sameas.php` y escenarios añadidos a la suite
  de datos estructurados de `tests/test-valet.php`; receta `test-t10` y job `lint` de CI actualizados.
- Documentación: `docs/author-sameas.md`.
