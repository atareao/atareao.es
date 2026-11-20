# Tasks

## 0. Preparación y baseline

- [x] 0.1 Confirmar el punto de extensión y las funciones existentes en
      `wp-content/plugins/atareao-functionality/includes/class-seo.php`
      (`the_seo_framework_title`, `the_seo_framework_description`,
      `the_seo_framework_schema_graph_data`) y decidir si el markup nuevo vive ahí o en un módulo
      propio con el mismo contrato; verificar con `rg -n "the_seo_framework_" wp-content/plugins/atareao-functionality/includes/class-seo.php`
- [x] 0.2 Capturar el HTML servido actual como referencia de partida de la portada, `/blog/`,
      `/tutoriales/`, `/podcast/`, `/aplicaciones/`, `/software/`, un episodio y `/author/atareao/`;
      verificar que las respuestas quedan guardadas y que el `<title>` de la portada mide 305 caracteres
      — **VERIFICADO en producción (2026-11-20)**: descarga con `curl -A 'Mozilla/5.0' …?cb=<ts>` de las
      8 superficies; códigos: `/`, `/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/`, `/software/`,
      `/author/yyyxfsgj/` = 200 y **`/author/atareao/` = 404**. El `<title>` de la portada mide
      **305 caracteres** (literal:
      `atareao con Linux - Si disfrutas con Linux, Docker, Bash, Scripting, Python, Rust etc. o
      simplemente, quieres aprender, sin lugar a dudas, este es tu sitio. …`). HTML guardado en
      `/tmp/opencode/surf2/*.html`.
- [x] 0.3 Confirmar la causa real del 404 de `/author/atareao/` (regla de rewrite, `author_base` o
      consulta) antes de tocar plantillas; verificar con `just wp -- rewrite list` o la inspección
      equivalente
      — **VERIFICADO**: la regla de rewrite de `SEO::ensureAuthorArchive()` **sí está registrada**
      (`add_rewrite_rule('^author/([^/]+)/?$', 'index.php?author_name=$matches[1]', 'top')`) y funciona:
      `/author/yyyxfsgj/`, `/author/admin/` y `/author/Superlewis/` responden 200. El 404 se debe a que
      **no existe ningún usuario con `user_nicename = atareao`**; el autor real (ID 3) tiene
      `user_nicename = yyyxfsgj` y `display_name = atareao`. Es un problema de **datos**, no de
      plantilla ni de rewrite. → issue **#200**.
- [x] 0.4 Confirmar el acceso al cliente de Search Console de `gsc-reporting` con `just gsc resumen`
      y registrar la salida como baseline de partida
      — **VERIFICADO**: `just gsc resumen` → `Periodo: 2026-10-24 — 2026-11-20 (28 días)`; `Clics 4,943`;
      `Impresiones 399,496`; `CTR 1.24%`; `Posición media 6.99` (exit 0). `ATAREAO_GSC_SERVICE_ACCOUNT`
      está presente y `scripts/gsc/{gsc.py,report.py}` funcionan.
- [x] 0.5 Escribir en `docs/seo-visibility.md` la referencia a `docs/medicion-baseline.md` §3 (CTR
      global 1,4 %, episodios ~1,3 %, `yt-dlp` 0,2 %) como baseline de la medición; verificar que el
      documento existe y cita la sección

## 1. Títulos y descripciones

- [x] 1.1 RED — Escribir en `tests/test-valet.php` los escenarios de `seo-metadata`: `<title>` de la
      portada ≤60, tagline larga presente en el `header`, listados sin sufijo de marca, descripciones
      de portada y listados en 120–160 y distintas del título; ejecutar `just test` y confirmar que los
      nuevos fallan en ROJO y el resto sigue en VERDE
- [x] 1.2 GREEN — Implementar en `class-seo.php` (y en `header.php`/`front-page.php` si hiciera falta)
      los títulos y las descripciones mínimos para pasar los tests; ejecutar `just test` y
      `just php-lint` y confirmar 100 % en verde sin errores
- [ ] 1.3 Verificar el HTML servido de la portada y de las cuatro plantillas de listado (descarga real)
      y pasar `Rich Results Test` sobre la portada y un listado sin errores
      — **EJECUTADO en producción (2026-11-20) y NO CUMPLE**: el `<title>` servido de la portada mide
      **305** caracteres (escenario: ≤60) y todos los listados terminan en ` - atareao con Linux`
      (escenario: sin sufijo de marca): `Blog - atareao con Linux`, `Tutoriales - atareao con Linux`,
      `Podcast - atareao con Linux`, `Aplicaciones - atareao con Linux`, `Software - atareao con Linux`.
      `/blog/` **no emite** `<meta name="description">`; el resto emite descripciones de 34–50
      caracteres (escenario: 120–160). **Causa raíz**: `class-seo.php:107-108` engancha
      `the_seo_framework_title` / `the_seo_framework_description`, hooks que **The SEO Framework 5.2.1
      no expone** (solo `the_seo_framework_title_from_generation`/`_from_custom_field`/`_separator` y
      `the_seo_framework_description_excerpt`); por eso los filtros nunca se aplican. El código servido
      coincide byte a byte con el repo (`sha256 8ba153f…`), así que no es caché. `Rich Results Test` no
      se pudo ejecutar por API. → issue **#200**. **Sin marcar** (escenarios fallan).
      Nota: `<title>` cacheado con `Cache-Control: no-cache` → `x-cache-status: BYPASS`, confirmado
      repetido en dos peticiones.
- [x] 1.4 REFACTOR — Limpiar y unificar la emisión; ejecutar `just phpcs` y `just test` y confirmar que
      no hay regresiones
- [x] 1.5 Documentar títulos y descripciones (rango, delegación en `seo` para el singular) en
      `docs/seo-visibility.md`; verificar que el documento describe el comportamiento final

## 2. JSON-LD (structured-data)

- [x] 2.1 RED — Escribir los escenarios de podcast, vídeo, `HowTo` y organización/persona
      ejercitando el callback con un grafo de entrada y un contenido de prueba en `tests/test-valet.php`;
      ejecutar `just test` y confirmar los nuevos en ROJO
- [x] 2.2 GREEN — Añadir `PodcastSeries` (en `/podcast/`), `PodcastEpisode`, `VideoObject`, `HowTo`,
      `Organization` y `Person` con `sameAs` al **mismo** grafo del filtro de TSF; ejecutar `just test`
      y `just php-lint` y confirmar en verde
- [ ] 2.3 Verificar en el HTML servido que `/podcast/` y un episodio tienen **un único** bloque
      `ld+json` con las entidades nuevas, y pasar `Rich Results Test` sobre un episodio y un tutorial
      sin errores
      — **Parcialmente verificado en producción (2026-11-20)**: **un único** bloque `application/ld+json`
      por página y las entidades nuevas están presentes:
      - portada: `WebSite, WebPage, Person, Organization` (`Person.sameAs` con 9 perfiles, incluido
        `channel/UCP8dTCaLNJFxfklb_GNqe6A`);
      - `/podcast/`: `WebSite, CollectionPage, PodcastSeries`;
      - episodio (`/podcast/tu-nube-es-tuya-self-hosting-con-podman/`): `WebSite, WebPage, PodcastEpisode`;
      - tutorial (`/tutorial/tutorial-de-opencode/`): `WebSite, WebPage, Article, HowTo`.
      **Sin marcar** porque `Rich Results Test` no se pudo pasar (no hay API en `scripts/gsc/`); queda
      como verificación manual pendiente.
- [x] 2.4 REFACTOR — Consolidar la construcción de entidades sin cambiar comportamiento; ejecutar
      `just phpcs` y `just test`
- [x] 2.5 Documentar las entidades y la excepción de `PodcastSeries` en el archivo en
      `docs/seo-visibility.md`; verificar el documento

## 3. H1 y página de autor

- [x] 3.1 RED — Escribir los escenarios de `H1` único en las cuatro plantillas de listado y de
      `/author/atareao/` (200, indexable, biografía, foto, `sameAs`, `H1` único); ejecutar `just test`
      y confirmar los nuevos en ROJO
- [x] 3.2 GREEN — Añadir el `<h1 class="page-title">` a `archive-tutorial.php`, `archive-podcast.php`,
      `archive-aplicacion.php` y `archive-software.php` y restaurar el archivo de autor con su
      plantilla; ejecutar `just test` y `just php-lint` y confirmar en verde
- [ ] 3.3 Verificar el HTML servido: exactamente un `<h1>` en cada listado y `/author/atareao/`
      responde 200 con biografía, foto y perfiles; pasar `Rich Results Test` sobre la página de autor
      sin errores
      — **EJECUTADO en producción (2026-11-20)**: el `<h1>` único **sí** se cumple en todos los listados
      (`/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/`, `/software/` = exactamente 1 `<h1>`).
      Pero **`/author/atareao/` responde 404** (escenario: 200) — causa en §0.3. `/author/yyyxfsgj/`
      responde 200 con 1 `<h1>` (`Autor: atareao`), `author-card`/`author-avatar` presentes y
      `<title>Autor: atareao - atareao con Linux</title>`, pero **sin `<meta name="description">`**.
      `Rich Results Test` no ejecutado. → issue **#200**. **Sin marcar** (el escenario «200» falla).
- [x] 3.4 REFACTOR — Unificar el encabezado de listado; ejecutar `just phpcs` y `just test`
- [x] 3.5 Documentar la superficie de autor y los `H1` en `docs/seo-visibility.md`; verificar el
      documento

## 4. Enlazado

- [x] 4.1 RED — Escribir los escenarios de `site-linking`: ausencia de `youtube.com/c/atareao`, enlace
      al vídeo propio del episodio y enlaces de suscripción canónicos; ejecutar `just test` y confirmar
      los nuevos en ROJO
- [x] 4.2 GREEN — Derivar el enlace al vídeo del `iframe` del contenido y corregir la emisión de los
      enlaces; ejecutar `just test` y `just php-lint` y confirmar en verde
- [ ] 4.3 Aplicar en producción los valores de opciones (`atareao_social_youtube` al handle/ID
      `UCP8dTCaLNJFxfklb_GNqe6A`, `atareao_social_ivoox` con el id real, `atareao_social_apple`
      canónico, `atareao_podcast_feed` propio) y verificar el HTML servido del pie y de un episodio
      — **NO EJECUTABLE en esta sesión** (escribir opciones en producción está prohibido: solo lectura).
      **Medición read-only del estado servido**:
      - `atareao_social_youtube` **ya** apunta al canal correcto (`\Atareao\SEO::socialProfiles()` →
        `https://www.youtube.com/channel/UCP8dTCaLNJFxfklb_GNqe6A`); no queda `youtube.com/c/atareao`
        en el HTML servido.
      - `atareao_social_ivoox` = `https://www.ivoox.com/podcast-atareao-version-podcast_sq_f1618463_1.html`
        → **contiene el slug `atareao-version-podcast`** (escenario `site-linking`: no debe contenerlo)
        → **falla**.
      - `atareao_social_apple` = `https://itunes.apple.com/us/podcast/atareao-podcast/id1437197202?mt=2`
        → **no** es la ficha canónica de `podcasts.apple.com` → **falla**.
      - Episodio con vídeo (`/podcast/la-herramienta-definitiva-…/`): enlace
        `https://www.youtube.com/watch?v=2RaxUSvtAEc` + texto «Ver el vídeo de este episodio» →
        escenario del vídeo propio **cumple**.
      **Sin marcar** (requiere escritura de opciones + verificación). → issue **#200**.
- [x] 4.4 REFACTOR — `just phpcs` y `just test`
- [x] 4.5 Documentar los destinos canónicos y el criterio del vídeo propio en
      `docs/seo-visibility.md`; verificar el documento

## 5. Sitemap

- [x] 5.1 RED — Escribir los escenarios de `sitemap`: sin ficheros idénticos, índice real o
      inexistente, sin cola obsoleta y decisión documentada; ejecutar `just test` y confirmar los
      nuevos en ROJO
- [x] 5.2 GREEN — Decidir y aplicar la forma del sitemap (despublicar el índice falso o convertirlo en
      índice real) y extender la exclusión a la cola 2010–2016; ejecutar `just test` y `just php-lint`
- [ ] 5.3 Verificar en producción que `sitemap.xml` y `sitemap_index.xml` no sirven contenido idéntico
      y que el inventario no arrastra la cola obsoleta (descarga real de ambos ficheros)
      — **EJECUTADO en producción (2026-11-20) y NO CUMPLE**: `curl` → `/sitemap.xml` **200** (341.664 B)
      y `/sitemap_index.xml` **200** (341.653 B), **ambos sin redirección**; `md5sum` **idéntico**
      (`31476351933712431604c82d68c53d50`). Además `just gsc sitemaps` los reporta como **duplicados**
      (`Duplicado: sí`) y señala que `sitemap_index.xml` «declara un índice pero el fichero no contiene
      etiquetas `<sitemap>`». `robots.txt` solo declara `Sitemap: https://atareao.es/sitemap.xml`.
      **Sin marcar** (escenario «sin contenido idéntico» falla). → issue **#193**.
- [x] 5.4 REFACTOR — `just phpcs` y `just test`
- [x] 5.5 Documentar en `docs/seo-visibility.md` qué se publica y con qué forma; verificar el documento

## 6. Medición

- [x] 6.1 RED — Escribir los escenarios de `seo-measurement` (extracción por página y por consulta,
      comparación contra §3, reutilización del cliente y registro del resultado); ejecutar `just test`
      y confirmar los nuevos en ROJO
- [x] 6.2 GREEN — Dejar el protocolo ejecutable con `just gsc` (página × query) reutilizando el
      cliente de `gsc-reporting`, sin credencial nueva; ejecutar `just test` y `just php-lint`
      — **VERIFICADO en producción (2026-11-20)**: `just gsc` existe en esta rama y funciona con las
      vistas por página y por consulta, reutilizando `scripts/gsc/report.py` y la misma credencial
      (`ATAREAO_GSC_SERVICE_ACCOUNT`): `just gsc resumen` (28 días), `just gsc paginas --rows 3`,
      `just gsc queries`, `just gsc sitemaps` y `just gsc compara` devuelven tablas con datos reales
      (p. ej. `paginas --rows 3` → `…/recuperar-la-contrasena-…/` 279 clics, CTR 7.85%). Exit 0.
- [ ] 6.3 Ejecutar la comparación a 30 días vista y verificar que el informe por página y por consulta
      se genera y se contrasta con `docs/medicion-baseline.md` §3
      — **Parcial**: `just gsc compara` **sí** genera la comparativa contra el periodo anterior
      (`Actual: 2026-10-24 — 2026-11-20` vs `Anterior: 2026-09-26 — 2026-10-23`; `Clics -15.29%`,
      `Impresiones -16.79%`), pero es la ventana por defecto de 28 días **pre-deploy**. La comparación
      **a 30 días vista** (post-deploy de v1.24.0) es imposible hoy por diseño. **Sin marcar**.
- [x] 6.4 REFACTOR — `just phpcs` y `just test`
- [x] 6.5 Documentar el protocolo y el resultado en `docs/seo-visibility.md`; verificar el documento

## 7. Integración y archivo

- [x] 7.1 Ejecutar `just test` y confirmar el arnés completo en verde
- [ ] 7.2 Ejecutar `just php-lint` y `just phpcs` y confirmar cero errores
      — **Ejecutado (2026-11-20)**: `just php-lint` → **0 errores de sintaxis** en todos los ficheros
      PHP. `just phpcs` (PSR12 sobre `wp-content/themes/atareao-theme` +
      `wp-content/plugins/atareao-functionality`) sigue en **rojo por violaciones preexistentes**
      ajenas a este change (mayoría en `assets/blocks/*.js`/`assets/js/*.js`, `header.php`,
      `class-cache-purge.php`, `single-*.php`). Sobre `class-seo.php` (fichero de este change): **0
      errores**, solo 8 warnings (longitud de línea y side-effects), idéntico a lo ya documentado.
      **Sin marcar** (phpcs no está a cero).
- [ ] 7.3 Pasar `Rich Results Test` sobre portada, un listado, un episodio, un tutorial y la página de
      autor, y confirmar cero errores en las cinco
      — **No ejecutado**: `scripts/gsc/` no expone la API de URL Inspection / Rich Results y la
      verificación manual no forma parte de esta sesión read-only. **Sin marcar**.
- [ ] 7.4 Comprobar el HTML servido de las superficies tocadas (títulos, descripciones, `H1`, JSON-LD,
      enlaces y sitemap) y confirmar que se cumple cada escenario
      — **Ejecutado (2026-11-20) y NO se cumple el conjunto**: JSON-LD y `<h1>` único sí; **títulos**
      (portada 305) y **descripciones** (ausentes/cortas) fallan; **`/author/atareao/` 404**; **enlaces**
      (ivoox con slug, apple no canónico) fallan; **sitemap** duplicado. Detalle en §1.3/§2.3/§3.3/§4.3/§5.3.
      **Sin marcar**.
- [ ] 7.5 Marcar las tareas completadas y archivar el change con `openspec archive seo-visibility`;
      verificar que las specs destino quedan actualizadas
      — **NO se archiva**: los escenarios de `seo-metadata` y `author-page` no se cumplen en producción
      (§1.3/§3.3) y `site-linking`/`sitemap` tampoco (§4.3/§5.3); `seo-measurement` depende de la ventana
      a 30 días. Se dejan sin marcar y se documenta el motivo. Follow-up: issue **#200**.

## Estado de verificación (esta sesión)

- RED demostrado: con la implementación revertida (`git stash` de `class-seo.php` y las plantillas),
  `just test` da 67 fallos, todos `(sv)`, con el resto del arnés en verde (1229).
- GREEN: `just test` → 1296 pasan, 0 fallan. `just php-lint` → sin errores de sintaxis.
- REFACTOR: los ficheros PHP tocados tienen 0 errores en `phpcs --standard=PSR12`. `just phpcs` sigue
  en rojo por errores **preexistentes** ajenos a este change (mayoría en `assets/blocks/*.js` y
  `assets/js/*.js`, más `header.php` y `class-cache-purge.php`); por eso 7.2 queda sin marcar.
- Desviaciones:
  - El H1 de listado y la superficie de autor viven en el **tema** (`archive-*.php`, `author.php`),
    que es presentación; la lógica SEO sigue en el plugin. No se tocaron `single-capitulo.php`,
    `single-podcast.php`, `class-podcast-feed.php`, `class-srt-upload.php` ni
    `scripts/podcast-transcripts/` (los trabaja otro agente).
  - `content-none.php` degrada su encabezado a `H2` para no colisionar con el H1 del archivo.
  - El protocolo de medición depende de la capability `gsc-reporting`: `just gsc` **no existe** en
    esta rama, así que 6.2/6.3 quedan pendientes de esa capability y de los 30 días post-deploy.
  - `VideoObject` se emite como entidad propia en el mismo grafo; enlazarlo por `@id` desde la
    entidad del contenido queda como follow-up (los escenarios de la spec solo exigen su presencia).
- Pendiente post-deploy (sin marcar): HTML servido, `Rich Results Test` (1.3, 2.3, 3.3, 7.3),
  opciones de producción de enlazado (4.3), sitemap real (5.3), comparación de CTR a 30 días (6.3).
- No se archiva el change (7.5 sin marcar), por indicación expresa.

## Verificación post-deploy sobre producción (2026-11-20, v1.24.0)

Sesión **read-only** sobre `https://atareao.es` (`curl` cache-busting + `ssh atareao 'docker exec wpcli
wp …'`). Resultado: **el change NO se archiva**; los escenarios de `seo-metadata`, `author-page`,
`site-linking` y `sitemap` no se cumplen en producción. Se marcan solo §0.2, §0.3, §0.4 y §6.2
(verificables hoy).

- **Cumple**: JSON-LD con un único bloque y las entidades nuevas (§2.3, parcial), `<h1>` único en los
  cinco listados (§3.3, parcial), canal de YouTube normalizado y enlace al vídeo propio del episodio
  (§4.3, parcial), cliente GSC operativo (§6.2).
- **No cumple**: `<title>` de portada (305), listados con sufijo de marca y descripciones cortas/ausentes
  (§1.3); `/author/atareao/` 404 (§3.3); ivoox con slug y apple no canónico (§4.3);
  `sitemap.xml` ≡ `sitemap_index.xml` (§5.3).
- **No verificable aquí**: `Rich Results Test` (§1.3/§2.3/§3.3/§7.3), escritura de opciones (§4.3),
  ventana a 30 días post-deploy (§6.3).
- **Causa raíz principal**: los filtros `the_seo_framework_title`/`the_seo_framework_description`
  enganchados en `class-seo.php:107-108` **no existen en The SEO Framework 5.2.1** (solo
  `the_seo_framework_title_from_generation`/`_from_custom_field`/`_separator` y
  `the_seo_framework_description_excerpt`), por lo que los títulos/descripciones nunca se reescriben.
- Follow-up: issue **#200**; sitemap duplicado ya seguido en issue **#193**.
