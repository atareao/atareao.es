# Design

## Context

Ver `proposal.md` para la motivación. El estado relevante para el *cómo*:

- El sitio es **WordPress + Podman**, con el core en contenedores (nunca se edita). Solo `wp-content/`
  está versionado. El **tema** es presentación y el **plugin** `atareao-functionality` concentra la
  funcionalidad (5 CPTs, bloque Gutenberg, microsite `/tools/`).
- No hay build tools (ni `package.json`, ni `composer.json`). PHP 8.3, PSR12, JS/CSS como fuente.
- El SEO de la página lo compone **The SEO Framework** (TSF): no hay Yoast ni RankMath. El plugin ya
  engancha los filtros de TSF desde `wp-content/plugins/atareao-functionality/includes/class-seo.php`
  (`the_seo_framework_title`, `the_seo_framework_description`, `the_seo_framework_schema_graph_data`,
  `document_title_parts`). El change **extiende ese mismo punto**, no crea otro.
- El grafo JSON-LD de TSF llega al filtro como **array secuencial de entidades** (sin
  `@context`/`@graph`, que TSF monta después) y la salida de la página es **un único** bloque
  `ld+json`. La entidad de artículo ya se añade ahí (change `article-structured-data`).
- La resolución de la descripción por post ya existe: `\Atareao\SEO::getDescription()` (capability
  `seo`).
- El arnés de pruebas es procedural (`tests/test-valet.php`), sin framework ni autoloader;
  `just test` propaga el código de salida. Calidad: `just php-lint`, `just phpcs`.
- La medición reutiliza el cliente de solo lectura de Search Console de la rama
  `feature/gsc-reporting` (`scripts/gsc/`, `just gsc`), con la service account ya provisionada.

## Goals / Non-Goals

**Goals:**

- Que la portada, los listados y los episodios presenten título y descripción propios y medibles.
- Completar el grafo JSON-LD con las entidades de podcast, vídeo, `HowTo` y organización/persona, sin
  romper el contrato de un único bloque.
- Corregir la estructura (`H1`) y las superficies ausentes (autor) que impiden un resultado rico.
- Dejar los enlaces externos canónicos y el sitemap coherente.
- Poder medir el efecto con la herramienta que ya existe, sin dependencias nuevas.

**Non-Goals:**

- Rediseño visual, migración de contenido ni cambio de los CPTs.
- Instalar Yoast, RankMath ni otro plugin de SEO: se trabaja sobre TSF y el plugin propio.
- Tocar el core de WordPress o los contenedores.
- Introducir credenciales, dependencias de Python o de PHP nuevas.
- Perseguir resultados enriquecidos que Google retiró (el caso de `HowTo`): se emite por su valor para
  consumidores agénticos.

## Decisions

### D1. Seis capabilities en lugar de una, para no romper la coherencia del `Purpose`

`seo` documenta **solo** la resolución de la descripción de un post y su `Purpose` no cubre títulos,
encabezados ni superficies; `structured-data` cubre el grafo. Por eso:

- `seo-metadata` (nueva): títulos, descripciones de plantilla y `H1` de listados.
- `author-page` (nueva): la superficie de autor.
- `site-linking` (nueva): los enlaces externos canónicos.
- `sitemap` (nueva): el inventario de URLs.
- `seo-measurement` (nueva): el protocolo de medición.
- `structured-data` (modificada): se le añaden entidades y se amplía su cobertura.

- **Alternativas descartadas:** colgar títulos y `H1` de `seo` (dejaría una spec cuyo `Purpose` no
  describe su contenido); una única capability `seo-visibility` con todo (mezcla cuatro historias con
  ciclos de vida distintos). Es el mismo criterio con el que `article-structured-data` creó
  `structured-data` en vez de extender `seo`.

### D2. Un único grafo, extendido por el filtro de TSF

Las entidades nuevas se **añaden al array** que TSF entrega a `the_seo_framework_schema_graph_data` y
se enlazan por `@id`; nunca se emite un segundo `ld+json`. `PodcastSeries` se emite en el archivo
`/podcast/`, que la regla de cobertura prohibía: por eso esa regla se **modifica** (no se añade otra
que la contradiga) y la excepción queda explícita en la spec.

- **Alternativas descartadas:** emitir bloques `ld+json` propios desde las plantillas (rompe el
  contrato de bloque único y duplica el grafo); tocar el core de TSF (no está versionado).

### D3. Títulos por los filtros existentes, tagline fuera del `<title>`

La portada se sirve por `front-page.php`, que ya imprime la tagline larga en un `<h1 class="site-title">`
visible. El `<title>` se corrige en `filterSeoFrameworkHomepageTitle()`/`filterHomepageTitle()` para
bajar a ≤60 caracteres; la tagline larga **no** se elimina, solo deja de formar parte del `<title>`.
Los títulos de listado y de episodio se reescriben en los mismos filtros.

- **Alternativas descartadas:** acortar la tagline de WordPress (afecta a la marca y a otros sitios);
  un plugin SEO externo (fuera de alcance).

### D4. Descripciones: superficies propias, singular delegado en `seo`

Las descripciones de portada y de los cuatro listados se emiten en el filtro de TSF correspondiente.
En el singular se sigue usando `\Atareao\SEO::getDescription()` (capability `seo`) para no duplicar la
lógica de resolución; lo que se añade es la garantía de rango (120–160) y de no repetir el título.

### D5. `H1` en los cuatro listados sin duplicarlo

Los templates `archive-tutorial.php`, `archive-podcast.php`, `archive-aplicacion.php` y
`archive-software.php` hoy solo imprimen la descripción del archivo. Se añade un `<h1 class="page-title">`
con el título del archivo y la descripción pasa a párrafo de introducción. Las tarjetas usan `<h2>`,
así que no hay colisión de encabezado.

### D6. Página de autor real

`/author/atareao/` devuelve 404: hay que registrar/restaurar el archivo de autor y darle plantilla con
biografía, foto y `sameAs`. La causa exacta del 404 (regla de rewrite, `author_base` o consulta) se
confirma en la fase de preparación antes de tocar la plantilla.

### D7. Enlace al vídeo propio derivado del contenido

La mayoría de episodios embeben el vídeo como `iframe` de YouTube dentro del contenido (el tema ya
envuelve los `iframe` de YouTube/Vimeo en `.video-responsive`). El enlace al vídeo propio se **deriva
de ese `iframe`** en lugar de exigir un metadato nuevo por episodio, para no depender de rellenar
datos a mano. Si un episodio no embebe vídeo, no se emite enlace.

- **Alternativas descartadas:** un metadato `video-url` nuevo (obliga a editar cientos de episodios);
  enlazar solo al canal (es el problema que se corrige).

### D8. Sitemap: un inventario, sin fichero duplicado

TSF genera el sitemap y el plugin ya excluye páginas antiguas por ID (`filterExcludeOldPages`). Se
decide si se despublica el `sitemap_index.xml` falso (que no es índice) o se convierte en índice real,
y se extiende la exclusión a la cola obsoleta 2010–2016. La decisión queda escrita en `docs/`.

### D9. Medición con la herramienta existente

No se escribe un cliente nuevo: la comparación a 30 días usa `just gsc` (`searchanalytics.query` por
página y por consulta) de `gsc-reporting`. El protocolo y los valores de referencia (§3 de la
baseline) quedan por escrito.

### D10. `HowTo` se emite aunque Google retiró su resultado enriquecido

Google retiró el resultado enriquecido de `HowTo`, pero el vocabulario sigue siendo válido y útil para
consumidores agénticos y de IA (el objetivo GEO del change). Se emite solo cuando el tutorial expone
pasos ordenados.

## Risks / Trade-offs

- **Dependencia de TSF.** Todo el `<head>` y el grafo dependen de los filtros de TSF; un cambio de
  versión del framework puede alterar los `@id`. → Los escenarios fijan el contrato (un bloque,
  `@id` estables) y el arnés prueba el callback con un grafo de entrada, sin TSF instalado.
- **El 404 del autor puede tener causa distinta a la plantilla.** → Confirmarla antes de tocar nada;
  si es de rewrite, el arreglo vive en la configuración del CPT/rewrite, no en el template.
- **Derivar el vídeo del `iframe` es frágil ante formatos no YouTube/Vimeo.** → Es exactamente lo que
  el tema ya reconoce (`.video-responsive`); lo demás se omite en vez de inventar datos.
- **Datos de suscripción en la base de datos de producción.** Los enlaces son opciones
  (`atareao_social_*`, `atareao_podcast_feed`), no código: el arreglo se aplica en producción y la
  spec solo fija el contrato.
- **La baseline es de 16 meses y el WAF bloquea ráfagas.** → La comparación es de solo lectura y en
  serie; el cliente de `gsc-reporting` ya contempla el límite.

## Migration Plan

1. Aprobar este change (`just check-spec` en verde).
2. Fase TDD por bloques (títulos/descripciones · JSON-LD · `H1` y autor · enlazado · sitemap ·
   medición): RED con `just test`, GREEN con `just test` + `just php-lint`, REFACTOR con `just phpcs`.
3. Verificar en el HTML servido y con `Rich Results Test` (sin errores) en producción.
4. Aplicar los valores de opciones en producción (canal, iVoox, Apple, RSS) y publicar el sitemap
   decidido.
5. A los 30 días, ejecutar la comparación de `seo-measurement` y registrarla.
6. **Rollback:** revertir el commit del plugin/tema; los cambios de opciones y de sitemap se revierten
   restaurando su valor anterior.

## Open Questions

- El identificador real de iVoox y la URL canónica de Apple se toman del panel del programa; no
  cambian la spec ni el enfoque.
- Si conviene un metadato de vídeo para los pocos episodios sin `iframe` reconocible, se decide al
  implementar; la spec solo exige que, si hay vídeo, se enlace.
