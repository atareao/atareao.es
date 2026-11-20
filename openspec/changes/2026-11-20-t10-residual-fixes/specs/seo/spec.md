# seo — Delta

## Purpose

La integración del plugin `atareao-functionality` con The SEO Framework (TSF) por los **filtros que
TSF realmente dispara**, con contrato explícito de los dos callbacks que reescriben título y
descripción en la portada y los listados; la **guardia** que impide registrar hooks de TSF
inexistentes; el archivo de autor canónico con la redirección del nicename heredado; y la garantía de
que todo lo que queda fuera de esas superficies conserva el valor que genera TSF.

## ADDED Requirements

### Requirement: Integración con The SEO Framework por sus filtros reales

El plugin SHALL integrarse con The SEO Framework **únicamente** por los filtros que TSF 5.2.1 dispara
de verdad. En concreto:

- Para el título, SHALL registrar `the_seo_framework_title_from_generation` con un callback de firma
  `filterGeneratedTitle( string $title, ?array $args ): string`, aceptando **2 argumentos**. El
  segundo argumento SHALL tratarse como **anulable** (`?array`), con las claves `id`, `tax`, `pta` y
  `uid` posibles y `uid` pudiendo ser `null`; el callback SHALL NOT desreferenciar claves ausentes.
- Para la descripción, SHALL registrar `the_seo_framework_generated_description` con un callback de
  firma `filterGeneratedDescription( string $desc, ?array $args, string $type ): string`, aceptando
  **3 argumentos**, donde `$type` ∈ `search|opengraph|twitter`.
- El plugin SHALL NOT registrar `the_seo_framework_title` ni `the_seo_framework_description` (no
  existen en TSF 5.2.1).
- El plugin SHALL NOT modificar los hooks `the_seo_framework_schema_graph_data` y
  `the_seo_framework_sitemap_exclude_ids`, que sí existen y ya funcionan.

El callback de título SHALL devolver el **valor recibido sin cambios** salvo cuando la petición
actual sea la portada o una de las plantillas de listado cubiertas; en esas superficies SHALL devolver
el título curado, de **60 caracteres o menos**. El callback SHALL devolver el valor recibido sin
cambios en cualquier otro contexto, y en particular cuando `is_admin()`, una petición AJAX, un feed,
una petición REST, la generación de un sitemap o `robots` estén activos, o cuando `$args` sea `null`.

El callback de descripción SHALL devolver la descripción curada en la portada y en las plantillas de
listado cubiertas para **los tres valores de `$type`**, y el valor recibido sin cambios en cualquier
otro contexto.

#### Scenario: El título de la portada lo reescribe el filtro real

- **WHEN** TSF genera el título de la portada y se ejecuta `filterGeneratedTitle()`
- **THEN** devuelve el título curado de la portada, de 60 caracteres o menos

#### Scenario: El título de un listado lo reescribe el filtro real

- **WHEN** TSF genera el título de `/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/` o `/software/`
- **THEN** devuelve el título curado de esa superficie

#### Scenario: Un contenido singular no se altera

- **WHEN** TSF genera el título de un post, un tutorial o un episodio
- **THEN** el callback devuelve el mismo valor que recibe, sin cambios

#### Scenario: Un contexto ajeno al front-end no se altera

- **WHEN** TSF genera un título en el admin, en una petición AJAX, en un feed, en una petición REST, en un sitemap o para `robots`
- **THEN** el callback devuelve el mismo valor que recibe, sin cambios

#### Scenario: El segundo argumento llega anulado

- **WHEN** el callback recibe `$args` como `null` o sin la clave que espera
- **THEN** no se produce error y devuelve el mismo valor que recibe

#### Scenario: La descripción curada se aplica a los tres tipos de generación

- **WHEN** TSF genera la descripción de la portada o de un listado con `$type` igual a `search`, `opengraph` o `twitter`
- **THEN** devuelve la descripción curada de esa superficie

#### Scenario: La descripción fuera de alcance no se altera

- **WHEN** TSF genera la descripción de un contenido singular, de un feed o de un sitemap
- **THEN** el callback devuelve el mismo valor que recibe, sin cambios

### Requirement: Guardia contra hooks de SEO inexistentes

El proyecto SHALL mantener una **lista verificada** de hooks `the_seo_framework_*` que el plugin puede
registrar, versionada en el repositorio junto al arnés de tests. Un test SHALL fallar si el plugin
registra un hook `the_seo_framework_*` que no figure en la lista, de modo que un hook inexistente no
pueda volver a introducirse sin ser detectado.

El test SHALL ejecutarse sin TSF instalado (como en CI) comprobando que **cada hook que registra el
plugin pertenece a la lista**. Un **paso de verificación en producción**, con TSF instalado, SHALL
comprobar que **cada hook de la lista aparece en el código de TSF** (ocurrencia de `apply_filters`), y
SHALL reportar como fallo cualquier hook de la lista que TSF no dispare.

El plugin SHALL cargar y funcionar **sin error fatal** cuando TSF no está instalado: los filtros
quedan inertes y el resto del plugin opera con normalidad.

#### Scenario: Un hook registrado fuera de la lista rompe el test

- **WHEN** el plugin registra un hook `the_seo_framework_*` que no está en la lista verificada
- **THEN** el test falla e identifica el hook

#### Scenario: La guardia pasa sin TSF instalado

- **WHEN** se ejecuta el test en CI, sin TSF instalado, y todos los hooks registrados pertenecen a la lista
- **THEN** el test pasa

#### Scenario: La lista coincide con el TSF instalado

- **WHEN** se ejecuta el paso de verificación en producción con TSF 5.2.1
- **THEN** cada hook de la lista aparece en el código de TSF y la verificación pasa

#### Scenario: El plugin sin TSF no provoca error fatal

- **WHEN** el plugin se carga sin TSF instalado
- **THEN** carga sin error fatal y los filtros de TSF quedan inertes

### Requirement: Títulos y descripciones curados de la portada y los listados

El sistema SHALL emitir el `<title>` de la portada con una longitud de **60 caracteres o menos**. El
sistema SHALL emitir, en `/blog/` y en cada una de las cuatro plantillas de listado (`/tutoriales/`,
`/podcast/`, `/aplicaciones/` y `/software/`), un `<title>` curado y una `<meta name="description">`
curada y no vacía, procedentes de las tablas de títulos y descripciones del plugin, con una longitud
de entre **120 y 160 caracteres** para la descripción. Ningún `<title>` de esas superficies SHALL
terminar en el sufijo de marca del sitio.

#### Scenario: El título de la portada cabe en la SERP

- **WHEN** se cuenta la longitud del `<title>` servido en la portada
- **THEN** es de 60 caracteres o menos

#### Scenario: El blog tiene título y descripción curados

- **WHEN** se inspecciona el `<title>` y la `<meta name="description">` servidos en `/blog/`
- **THEN** ambos son los curados de la superficie y la descripción mide entre 120 y 160 caracteres

#### Scenario: Las cuatro plantillas tienen título y descripción curados

- **WHEN** se inspecciona el `<title>` y la `<meta name="description">` de `/tutoriales/`, `/podcast/`, `/aplicaciones/` y `/software/`
- **THEN** cada superficie sirve su título y su descripción curados, y ninguno termina en el sufijo de marca

### Requirement: La descripción curada se comparte con las tarjetas sociales

En la portada y en las plantillas de listado cubiertas, la descripción servida en `<meta
name="description">`, `og:description` y `twitter:description` SHALL ser **la misma cadena curada**.

#### Scenario: Las tres descripciones coinciden

- **WHEN** se comparan `<meta name="description">`, `og:description` y `twitter:description` de `/blog/`
- **THEN** las tres son idénticas a la descripción curada de la superficie

### Requirement: Archivo de autor canónico y redirección del nicename heredado

El autor cuyo `display_name` es `atareao` SHALL tener `user_nicename` igual a `atareao`, de forma que
`GET /author/atareao/` responda **200** con el archivo de ese autor. El cambio de `user_nicename`
SHALL realizarse con un **paso de datos idempotente**: ejecutarlo una segunda vez no SHALL modificar
ningún dato.

`GET /author/yyyxfsgj/` (nicename heredado) SHALL responder un **301** hacia la URL canónica del
archivo, en **una sola redirección**, sin bucle: la URL canónica no SHALL redirigirse a sí misma. La
redirección SHALL resolverse en `template_redirect`, detectando el **path** de la petición heredada
(la regla de rewrite lo resuelve a un 404, no a un archivo), y SHALL NOT aplicarse a ningún otro
nicename.

#### Scenario: La URL canónica responde

- **WHEN** se solicita `GET /author/atareao/`
- **THEN** la respuesta es 200 y es el archivo del autor 3

#### Scenario: El nicename heredado redirige a la canónica

- **WHEN** se solicita `GET /author/yyyxfsgj/`
- **THEN** la respuesta es 301 con `Location` apuntando a la URL canónica del archivo

#### Scenario: La redirección no entra en bucle

- **WHEN** se sigue el 301 de `/author/yyyxfsgj/`
- **THEN** la URL canónica responde 200 sin volver a redirigir

#### Scenario: El paso de datos es idempotente

- **WHEN** se ejecuta el paso de datos una segunda vez
- **THEN** no modifica el `user_nicename` y no produce efecto alguno

### Requirement: No regresión fuera de las superficies cubiertas

En las superficies no cubiertas —un post, un tutorial, un episodio, el feed principal `/feed/`, un
sitemap y el panel de administración— el título y la descripción generados por TSF SHALL permanecer
**idénticos** a los que TSF produce sin el plugin actuando: la integración SHALL NOT alterarlos.

#### Scenario: Un post conserva su título y descripción

- **WHEN** se compara el `<title>` y la `<meta name="description">` de un post con los que TSF genera sin la intervención del plugin
- **THEN** son idénticos

#### Scenario: Un tutorial conserva su título y descripción

- **WHEN** se compara el `<title>` y la `<meta name="description">` de un tutorial con los que TSF genera sin la intervención del plugin
- **THEN** son idénticos

#### Scenario: Un episodio conserva su título y descripción

- **WHEN** se compara el `<title>` y la `<meta name="description">` de un episodio con los que TSF genera sin la intervención del plugin
- **THEN** son idénticos

#### Scenario: El feed principal no se altera

- **WHEN** se inspecciona el feed principal `/feed/`
- **THEN** sus títulos y descripciones no cambian respecto a la generación de TSF

#### Scenario: Un sitemap no se altera

- **WHEN** se inspecciona un sitemap
- **THEN** su contenido no cambia respecto a la generación de TSF

#### Scenario: El admin no se altera

- **WHEN** se inspecciona el título y la descripción en el panel de administración
- **THEN** no cambian respecto a la generación de TSF
