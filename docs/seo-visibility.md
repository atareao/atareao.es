# seo-visibility

Feature del change `openspec/changes/seo-visibility/` (capabilities `seo-metadata`,
`author-page`, `site-linking`, `sitemap`, `seo-measurement` y el delta sobre
`structured-data`). El objetivo es subir el CTR (baseline global 1,4 % sobre
11,65 M de impresiones) mejorando metadatos, datos estructurados, estructura y
enlazado. **La funcionalidad vive en el plugin**
`wp-content/plugins/atareao-functionality/`; el tema es presentación.

## Qué es y ficheros implicados

- `wp-content/plugins/atareao-functionality/includes/class-seo.php` — títulos,
  descripciones, grafo JSON-LD, enlaces canónicos, sitemap y protocolo de
  medición. Punto de extensión: los filtros de The SEO Framework (TSF).
- `wp-content/themes/atareao-theme/archive-{tutorial,podcast,aplicacion,software}.php`
  — `H1` único de listado.
- `wp-content/themes/atareao-theme/author.php` — superficie de autor.
- `wp-content/themes/atareao-theme/template-parts/content-none.php` — encabezado
  de «sin resultados» degradado a `H2` para no colisionar con el `H1` del archivo.
- `wp-content/themes/atareao-theme/footer.php` — etiqueta del feed propio.
- `tests/test-valet.php` — escenarios procedurales (arnés del proyecto).

## Cómo funciona

### Títulos y descripciones (seo-metadata)

- `SEO::filterSeoFrameworkHomepageTitle()` — portada: nombre del sitio
  (`≤60`, sin la tagline larga, que sigue visible en el `header` de
  `front-page.php` vía `get_bloginfo('description')`). Listados: título propio sin
  sufijo de marca (`SEO::LISTING_TITLES`). Episodios: título recortado a 60.
- `SEO::filterSeoFrameworkBlogDescription()` — descripción propia en la portada y
  en los cuatro listados (`SEO::LISTING_DESCRIPTIONS`, todas 120–160). El
  singular delega en `SEO::getDescription()` (capability `seo`) y solo añade la
  garantía de rango y de no repetir el título (`SEO::clampDescription()`).

### Datos estructurados (structured-data)

Todo se añade al **mismo** grafo de `the_seo_framework_schema_graph_data`
(un único bloque `ld+json`):

- `PodcastSeries` en el archivo `/podcast/` (excepción explícita de la regla de
  cobertura) y `PodcastEpisode` en cada episodio, enlazado a la serie por `@id`
  (`SEO::podcastSeriesId()`); `associatedMedia` con el audio (`mp3-url`) cuando
  existe.
- `VideoObject` cuando el contenido embebe un vídeo de YouTube/Vimeo.
- `HowTo` en tutoriales con pasos ordenados (`<ol><li>…`). Se emite aunque Google
  retiró el resultado enriquecido: valor para consumidores agénticos y de IA.
- `Organization` en la portada, y `sameAs` en las entidades `Person` de primer
  nivel con los perfiles externos (`SEO::socialProfiles()`). Nunca se duplican
  entidades ya presentes: se enlazan por `@id`.

### Estructura y autor (seo-metadata / author-page)

- `H1` único en los cuatro listados (`archive-*.php`); las tarjetas emiten `H2`.
- `author.php` sirve `/author/{nicename}/` con biografía, foto y perfiles, y
  `SEO::ensureAuthorArchive()` registra la regla de reescritura
  `^author/([^/]+)/?$ → index.php?author_name=…`. **Tras desplegar hay que
  ejecutar `flush_rewrite_rules()`** (por ejemplo con
  `just wp -- rewrite flush`).

### Enlazado (site-linking)

- Canal de YouTube: `SEO::normalizeYouTubeChannel()` corrige
  `youtube.com/c/atareao` al canal `UCP8dTCaLNJFxfklb_GNqe6A`, aplicado al leer
  la opción (`option_atareao_social_youtube`).
- Vídeo propio: `SEO::embeddedVideoUrl()` deriva la URL canónica del `iframe` del
  contenido y `SEO::appendEpisodeVideoLink()` añade el enlace al episodio.
- Suscripción: Apple canónico, iVoox con el id real y RSS propio. Los valores se
  aplican en producción (son opciones `atareao_social_*` y `atareao_podcast_feed`).

### Sitemap (sitemap)

Decisión: **se publica un único inventario (`sitemap.xml`)**. El nombre de índice
falso `sitemap_index.xml` redirige 301 a `sitemap.xml`
(`SEO::redirectSitemapIndex()`), de modo que no se sirve contenido idéntico bajo
dos nombres. La cola obsoleta 2010–2016 se excluye ampliando
`SEO::filterExcludeOldPages()` con las entradas publicadas antes de 2017
(`SEO::legacyPostIds()`).

## Variables y configuración

- Opciones del sitio: `atareao_social_youtube`, `atareao_social_ivoox`,
  `atareao_social_apple`, `atareao_social_github`, `atareao_podcast_feed`,
  `atareao_podcast_cover`, `atareao_site_logo`.
- Constantes del plugin: `SEO::YOUTUBE_CHANNEL_ID`, `SEO::CTR_BASELINE`.
- Sin credenciales nuevas.

## Cómo habilitar / desplegar

1. Desplegar el plugin y el tema.
2. `flush_rewrite_rules()` para la regla del autor y del sitemap.
3. Aplicar en producción los valores de las opciones de enlazado.
4. Verificar el HTML servido y pasar `Rich Results Test` (pendiente post-deploy).

## Medición (seo-measurement)

Protocolo a 30 días vista contra `docs/medicion-baseline.md` §3 (CTR global
1,4 %, episodios ~1,3 %, `yt-dlp` 0,2 %), reutilizando el cliente de solo lectura
de Search Console de `gsc-reporting` (`just gsc`, `searchanalytics.query` por
página y por consulta), sin credencial nueva:

1. `just gsc oportunidades` — consultas con muchas impresiones y poco CTR.
2. `just gsc compara` — antes/después por página × query.
3. Registrar el informe por escrito con la fecha del periodo medido y contrastar
   el CTR global, el de los episodios y el de `yt-dlp` con `SEO::ctrBaseline()`.

## Decisiones y follow-ups

- `PodcastSeries` es la única entidad que rompe la regla de cobertura de
  `structured-data`; la excepción está documentada en la spec.
- La causa exacta del 404 del autor se confirma post-deploy; si no fuera de
  rewrite, ajustar la configuración del CPT/rewrite.
- Pendiente post-deploy: `Rich Results Test` y verificación del HTML servido.
- Pendiente a 30 días: ejecutar la comparación de CTR y registrar el resultado.
