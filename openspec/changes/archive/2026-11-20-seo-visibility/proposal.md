# seo-visibility

> **Superado (2026-11-20).** Este change queda sustituido por `2026-11-20-t10-residual-fixes`, que
> corrige la causa raíz: los filtros `the_seo_framework_title` / `the_seo_framework_description` que
> se daban por buenos **no existen en TSF 5.2.1**, así que el arnés pasaba en verde sin efecto en
> producción. Aquel change usa los hooks reales (`the_seo_framework_title_from_generation`,
> `the_seo_framework_generated_description`, `the_seo_framework_use_title_branding`), añade la
> guardia anti-falso-verde y cubre además el autor canónico y `itunes:explicit`.
>
> Se archiva con **`--skip-specs`** para no reintroducir en `openspec/specs/` requisitos de este
> planteamiento superado. La fuente de verdad es el change archivado
> `2026-11-20-t10-residual-fixes` y las specs `openspec/specs/seo/` y
> `openspec/specs/podcast-feed/`.

## Why

El sitio recibe muchísima impresión y muy poco clic: **157.774 clics sobre 11,65 M de impresiones
en 16 meses, un CTR global del 1,4 %** (baseline en `docs/medicion-baseline.md` §3 del repo de
seguimiento del programa). La posición media mejora (15,5 → ~7) mientras el CTR cae (2,4 % → ~1 %), así
que no es un problema de ranking: es que la web no convence en la SERP y las *features* de IA se
quedan el clic. Hay casos extremos verificados: episodios con 6–8,6 k impresiones al 1,3 % y
`yt-dlp` con 5.663 impresiones y **9 clics** (0,2 %).

El motivo es doble y medible en el HTML servido:

- **Metadatos pobres.** El `<title>` de la portada tiene **305 caracteres** (es el `blogname` más la
  tagline larga de WordPress); `/blog/` no tiene `meta description` y las descripciones de
  `/tutoriales/`, `/podcast/`, `/aplicaciones/` y `/software/` son flojas. Las cuatro plantillas de
  listado **no tienen `H1`** y `/author/atareao/` devuelve **404**.
- **Datos estructurados incompletos.** El grafo JSON-LD (compuesto por The SEO Framework, sin Yoast
  ni RankMath instalados) solo declara `WebSite`, `WebPage`, `Person`, `BreadcrumbList`, `ListItem`,
  `SearchAction` y `ReadAction`. Faltan `PodcastSeries`, `PodcastEpisode`, `VideoObject` donde hay
  vídeo, `HowTo` en tutoriales, `Organization` y `sameAs` en `Person`.
- **Enlazado incorrecto.** La web apunta al canal antiguo `youtube.com/c/atareao` (el real es el
  canal `UCP8dTCaLNJFxfklb_GNqe6A`), los enlaces de suscripción tienen fallos (iVoox con el slug
  incoherente `atareao-version-podcast`, «RSS» genérico, Apple no canónico) y las páginas de
  episodio **no enlazan su propio vídeo**, solo al canal.
- **Sitemap incoherente.** Hoy `sitemap.xml` y `sitemap_index.xml` sirven lo mismo: 3.181 URLs con
  cola de 2010–2016.

Es el momento de arreglarlo porque ya existe la herramienta para medir el efecto: la rama
`feature/gsc-reporting` (change archivado `2026-11-14-gsc-reporting`) aporta un cliente de solo
lectura de Search Console (`scripts/gsc/`, receta `just gsc` con `oportunidades` y `compara`) que
permite extraer las consultas con muchas impresiones y poco CTR y comparar el antes y el después.

## What Changes

- **R1 — Títulos.** El `<title>` de la portada baja a **≤60 caracteres** sin perder la tagline larga
  en el `header` visible; los títulos de los listados y de los episodios se reescriben orientados a
  la consulta real, sin sufijo de marca que coma el espacio útil.
- **R2 — Descripciones.** `meta description` propia en la portada y en las cuatro plantillas de
  listado; en posts, tutoriales y episodios la descripción se deriva del extracto, siempre en el
  rango **120–160 caracteres** y sin repetir el título.
- **R3 — JSON-LD.** Se añaden al grafo existente (un solo bloque `ld+json`) `PodcastSeries` en
  `/podcast/`, `PodcastEpisode` en cada episodio, `VideoObject` cuando hay vídeo embebido, `HowTo`
  en tutoriales, `Organization` y `Person` con `sameAs`.
- **R4 — Estructura y autor.** `H1` único en las cuatro plantillas de listado y página de autor
  indexable (hoy 404) con biografía, foto y `sameAs`.
- **R5 — Enlazado.** La web apunta al canal por handle/ID (nunca `/c/atareao`); cada episodio enlaza
  **su** vídeo; enlaces de suscripción corregidos (Apple canónico, iVoox con el id correcto, RSS
  propio).
- **R6 — Sitemap.** Se decide y documenta qué se publica y si hace falta un índice real; se elimina
  la duplicidad `sitemap.xml`/`sitemap_index.xml` y la cola obsoleta.
- **R7 — Medición.** Queda por escrito el protocolo de comparación del CTR a 30 días vista contra
  la baseline de `docs/medicion-baseline.md` §3, por página × query y reutilizando el cliente de
  `gsc-reporting`.

## Capabilities

### New Capabilities

- `seo-metadata`: títulos y descripciones de las superficies de plantilla (portada y listados) y el
  encabezado `H1` único de esas páginas; la resolución de la descripción de un contenido singular
  sigue siendo responsabilidad de la capability `seo`.
- `author-page`: el archivo de autor como superficie indexable con biografía, fotografía y perfiles
  externos.
- `site-linking`: los enlaces canónicos que el sitio publica hacia sus plataformas externas y hacia
  el vídeo de cada episodio.
- `sitemap`: qué URLs publica el sitio en su sitemap y con qué forma (urlset o índice real).
- `seo-measurement`: el protocolo con el que se medirá el efecto del change sobre el CTR contra la
  baseline, reutilizando el cliente de Search Console de `gsc-reporting`.

### Modified Capabilities

- `structured-data`: se añaden entidades de podcast, vídeo, `HowTo` y organización/persona al grafo,
  y se amplía la cobertura para permitir `PodcastSeries` en el archivo `/podcast/` sin romper la
  regla de un único bloque `ld+json`.

## Impact

- **Specs al archivar:** nueva `openspec/specs/seo-metadata/`, `openspec/specs/author-page/`,
  `openspec/specs/site-linking/`, `openspec/specs/sitemap/` y `openspec/specs/seo-measurement/`; y
  delta ADDED/MODIFIED sobre la existente `openspec/specs/structured-data/`.
- **Código (solo tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-seo.php` (títulos, descripciones y
    grafo JSON-LD vía los filtros de The SEO Framework).
  - `wp-content/themes/atareao-theme/archive-tutorial.php`, `archive-podcast.php`,
    `archive-aplicacion.php`, `archive-software.php` (H1 y encabezados).
  - `wp-content/themes/atareao-theme/index.php`, `front-page.php`, `header.php`, `footer.php`
    (títulos, tagline y enlaces de suscripción).
  - `wp-content/themes/atareao-theme/single-capitulo.php`, `single-podcast.php` (enlace al vídeo
    propio) y una plantilla de autor nueva.
  - Opciones del sitio (`atareao_social_youtube`, `atareao_social_ivoox`, `atareao_social_apple`,
    `atareao_podcast_feed`) en la base de datos de producción.
- **Config:** ninguna credencial nueva; la medición reutiliza la service account de `gsc-reporting`.
- **Tests:** `tests/test-valet.php` (arnés procedural del proyecto) para los escenarios que se puedan
  ejercitar sin el framework instalado; comprobación del HTML servido y `Rich Results Test` para el
  resto.
- **Fuera de alcance:** rediseño visual, migración de contenido y cualquier cambio en el core de
  WordPress (vive en contenedores y no se edita).
