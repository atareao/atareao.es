# Tasks

## 0. Preparación y baseline

- [ ] 0.1 Confirmar el punto de extensión y las funciones existentes en
      `wp-content/plugins/atareao-functionality/includes/class-seo.php`
      (`the_seo_framework_title`, `the_seo_framework_description`,
      `the_seo_framework_schema_graph_data`) y decidir si el markup nuevo vive ahí o en un módulo
      propio con el mismo contrato; verificar con `rg -n "the_seo_framework_" wp-content/plugins/atareao-functionality/includes/class-seo.php`
- [ ] 0.2 Capturar el HTML servido actual como referencia de partida de la portada, `/blog/`,
      `/tutoriales/`, `/podcast/`, `/aplicaciones/`, `/software/`, un episodio y `/author/atareao/`;
      verificar que las respuestas quedan guardadas y que el `<title>` de la portada mide 305 caracteres
- [ ] 0.3 Confirmar la causa real del 404 de `/author/atareao/` (regla de rewrite, `author_base` o
      consulta) antes de tocar plantillas; verificar con `just wp -- rewrite list` o la inspección
      equivalente
- [ ] 0.4 Confirmar el acceso al cliente de Search Console de `gsc-reporting` con `just gsc resumen`
      y registrar la salida como baseline de partida
- [ ] 0.5 Escribir en `docs/seo-visibility.md` la referencia a `docs/medicion-baseline.md` §3 (CTR
      global 1,4 %, episodios ~1,3 %, `yt-dlp` 0,2 %) como baseline de la medición; verificar que el
      documento existe y cita la sección

## 1. Títulos y descripciones

- [ ] 1.1 RED — Escribir en `tests/test-valet.php` los escenarios de `seo-metadata`: `<title>` de la
      portada ≤60, tagline larga presente en el `header`, listados sin sufijo de marca, descripciones
      de portada y listados en 120–160 y distintas del título; ejecutar `just test` y confirmar que los
      nuevos fallan en ROJO y el resto sigue en VERDE
- [ ] 1.2 GREEN — Implementar en `class-seo.php` (y en `header.php`/`front-page.php` si hiciera falta)
      los títulos y las descripciones mínimos para pasar los tests; ejecutar `just test` y
      `just php-lint` y confirmar 100 % en verde sin errores
- [ ] 1.3 Verificar el HTML servido de la portada y de las cuatro plantillas de listado (descarga real)
      y pasar `Rich Results Test` sobre la portada y un listado sin errores
- [ ] 1.4 REFACTOR — Limpiar y unificar la emisión; ejecutar `just phpcs` y `just test` y confirmar que
      no hay regresiones
- [ ] 1.5 Documentar títulos y descripciones (rango, delegación en `seo` para el singular) en
      `docs/seo-visibility.md`; verificar que el documento describe el comportamiento final

## 2. JSON-LD (structured-data)

- [ ] 2.1 RED — Escribir los escenarios de podcast, vídeo, `HowTo` y organización/persona
      ejercitando el callback con un grafo de entrada y un contenido de prueba en `tests/test-valet.php`;
      ejecutar `just test` y confirmar los nuevos en ROJO
- [ ] 2.2 GREEN — Añadir `PodcastSeries` (en `/podcast/`), `PodcastEpisode`, `VideoObject`, `HowTo`,
      `Organization` y `Person` con `sameAs` al **mismo** grafo del filtro de TSF; ejecutar `just test`
      y `just php-lint` y confirmar en verde
- [ ] 2.3 Verificar en el HTML servido que `/podcast/` y un episodio tienen **un único** bloque
      `ld+json` con las entidades nuevas, y pasar `Rich Results Test` sobre un episodio y un tutorial
      sin errores
- [ ] 2.4 REFACTOR — Consolidar la construcción de entidades sin cambiar comportamiento; ejecutar
      `just phpcs` y `just test`
- [ ] 2.5 Documentar las entidades y la excepción de `PodcastSeries` en el archivo en
      `docs/seo-visibility.md`; verificar el documento

## 3. H1 y página de autor

- [ ] 3.1 RED — Escribir los escenarios de `H1` único en las cuatro plantillas de listado y de
      `/author/atareao/` (200, indexable, biografía, foto, `sameAs`, `H1` único); ejecutar `just test`
      y confirmar los nuevos en ROJO
- [ ] 3.2 GREEN — Añadir el `<h1 class="page-title">` a `archive-tutorial.php`, `archive-podcast.php`,
      `archive-aplicacion.php` y `archive-software.php` y restaurar el archivo de autor con su
      plantilla; ejecutar `just test` y `just php-lint` y confirmar en verde
- [ ] 3.3 Verificar el HTML servido: exactamente un `<h1>` en cada listado y `/author/atareao/`
      responde 200 con biografía, foto y perfiles; pasar `Rich Results Test` sobre la página de autor
      sin errores
- [ ] 3.4 REFACTOR — Unificar el encabezado de listado; ejecutar `just phpcs` y `just test`
- [ ] 3.5 Documentar la superficie de autor y los `H1` en `docs/seo-visibility.md`; verificar el
      documento

## 4. Enlazado

- [ ] 4.1 RED — Escribir los escenarios de `site-linking`: ausencia de `youtube.com/c/atareao`, enlace
      al vídeo propio del episodio y enlaces de suscripción canónicos; ejecutar `just test` y confirmar
      los nuevos en ROJO
- [ ] 4.2 GREEN — Derivar el enlace al vídeo del `iframe` del contenido y corregir la emisión de los
      enlaces; ejecutar `just test` y `just php-lint` y confirmar en verde
- [ ] 4.3 Aplicar en producción los valores de opciones (`atareao_social_youtube` al handle/ID
      `UCP8dTCaLNJFxfklb_GNqe6A`, `atareao_social_ivoox` con el id real, `atareao_social_apple`
      canónico, `atareao_podcast_feed` propio) y verificar el HTML servido del pie y de un episodio
- [ ] 4.4 REFACTOR — `just phpcs` y `just test`
- [ ] 4.5 Documentar los destinos canónicos y el criterio del vídeo propio en
      `docs/seo-visibility.md`; verificar el documento

## 5. Sitemap

- [ ] 5.1 RED — Escribir los escenarios de `sitemap`: sin ficheros idénticos, índice real o
      inexistente, sin cola obsoleta y decisión documentada; ejecutar `just test` y confirmar los
      nuevos en ROJO
- [ ] 5.2 GREEN — Decidir y aplicar la forma del sitemap (despublicar el índice falso o convertirlo en
      índice real) y extender la exclusión a la cola 2010–2016; ejecutar `just test` y `just php-lint`
- [ ] 5.3 Verificar en producción que `sitemap.xml` y `sitemap_index.xml` no sirven contenido idéntico
      y que el inventario no arrastra la cola obsoleta (descarga real de ambos ficheros)
- [ ] 5.4 REFACTOR — `just phpcs` y `just test`
- [ ] 5.5 Documentar en `docs/seo-visibility.md` qué se publica y con qué forma; verificar el documento

## 6. Medición

- [ ] 6.1 RED — Escribir los escenarios de `seo-measurement` (extracción por página y por consulta,
      comparación contra §3, reutilización del cliente y registro del resultado); ejecutar `just test`
      y confirmar los nuevos en ROJO
- [ ] 6.2 GREEN — Dejar el protocolo ejecutable con `just gsc` (página × query) reutilizando el
      cliente de `gsc-reporting`, sin credencial nueva; ejecutar `just test` y `just php-lint`
- [ ] 6.3 Ejecutar la comparación a 30 días vista y verificar que el informe por página y por consulta
      se genera y se contrasta con `docs/medicion-baseline.md` §3
- [ ] 6.4 REFACTOR — `just phpcs` y `just test`
- [ ] 6.5 Documentar el protocolo y el resultado en `docs/seo-visibility.md`; verificar el documento

## 7. Integración y archivo

- [ ] 7.1 Ejecutar `just test` y confirmar el arnés completo en verde
- [ ] 7.2 Ejecutar `just php-lint` y `just phpcs` y confirmar cero errores
- [ ] 7.3 Pasar `Rich Results Test` sobre portada, un listado, un episodio, un tutorial y la página de
      autor, y confirmar cero errores en las cinco
- [ ] 7.4 Comprobar el HTML servido de las superficies tocadas (títulos, descripciones, `H1`, JSON-LD,
      enlaces y sitemap) y confirmar que se cumple cada escenario
- [ ] 7.5 Marcar las tareas completadas y archivar el change con `openspec archive seo-visibility`;
      verificar que las specs destino quedan actualizadas
