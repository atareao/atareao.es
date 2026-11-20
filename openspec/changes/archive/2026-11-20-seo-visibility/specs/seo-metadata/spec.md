# seo-metadata — Delta

## Purpose

Los títulos y las descripciones que el sitio expone en las superficies de plantilla —la portada y
los archivos de listado— y el encabezado `H1` único de esas páginas, de modo que cada superficie
presente en la SERP un título y una descripción propios y medibles. La resolución de la descripción
de un contenido singular (post, tutorial o episodio) sigue siendo responsabilidad de la capability
`seo`.

## ADDED Requirements

### Requirement: Títulos orientados a la consulta y dentro del límite de la SERP

El sistema SHALL emitir el `<title>` de la portada con una longitud de **60 caracteres o menos**. La
etiqueta larga de marca («Tutoriales, Podcast y Software Libre») SHALL seguir presente en el `header`
visible de la portada, sin formar parte del `<title>` cuando haga que este supere el límite. Los
títulos de las plantillas de listado (`/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/` y
`/software/`) y de las páginas de episodio SHALL reescribirse en función de la consulta real de
búsqueda de esa superficie y SHALL NOT terminar en un sufijo de marca que consuma el espacio útil.

#### Scenario: El título de la portada cabe en la SERP

- **WHEN** se cuenta la longitud del `<title>` servido en la portada
- **THEN** es de 60 caracteres o menos

#### Scenario: La tagline larga sigue visible

- **WHEN** se inspecciona el `header` visible de la portada
- **THEN** contiene la etiqueta larga de marca, aunque no forme parte del `<title>`

#### Scenario: Los títulos de listado no llevan sufijo de marca

- **WHEN** se inspecciona el `<title>` de `/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/` y `/software/`
- **THEN** ninguno termina en el sufijo de marca del sitio

#### Scenario: El título de un episodio describe su tema

- **WHEN** se inspecciona el `<title>` de la página de un episodio
- **THEN** describe el tema del episodio y no supera los 60 caracteres

### Requirement: Descripciones propias en la portada y en los listados

El sistema SHALL emitir una `<meta name="description">` propia y no vacía en la portada y en cada
una de las plantillas de listado (`/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/` y
`/software/`). Cada descripción SHALL tener entre **120 y 160 caracteres**, SHALL describir la
superficie en lugar de ser genérica y SHALL NOT repetir literalmente el título. En el contenido
singular (posts, tutoriales y episodios) la descripción SHALL derivarse del extracto conforme a la
capability `seo`, con el mismo rango de longitud.

#### Scenario: /blog/ declara una descripción

- **WHEN** se solicita `/blog/` y se inspecciona su `<head>`
- **THEN** contiene una `<meta name="description">` con contenido no vacío

#### Scenario: Las descripciones caen en el rango

- **WHEN** se miden las descripciones de la portada y de las cuatro plantillas de listado
- **THEN** cada una tiene entre 120 y 160 caracteres

#### Scenario: La descripción no repite el título

- **WHEN** se compara la `meta description` de una superficie con su `<title>`
- **THEN** no son la misma cadena

#### Scenario: La descripción de un singular se deriva del extracto

- **WHEN** un post, tutorial o episodio tiene extracto
- **THEN** su `meta description` deriva de ese extracto conforme a la capability `seo`

### Requirement: Encabezado H1 único en las plantillas de listado

El sistema SHALL emitir exactamente un elemento `<h1>` en cada una de las cuatro plantillas de
listado (`/tutoriales/`, `/podcast/`, `/aplicaciones/` y `/software/`) y SHALL NOT emitir ningún
`<h1>` dentro de las tarjetas de cada listado.

#### Scenario: Un solo H1 por listado

- **WHEN** se cuentan los elementos `<h1>` del HTML servido en `/tutoriales/`
- **THEN** hay exactamente uno

#### Scenario: El mismo contrato en los demás listados

- **WHEN** se cuentan los elementos `<h1>` en `/podcast/`, `/aplicaciones/` y `/software/`
- **THEN** cada página tiene exactamente uno

#### Scenario: Las tarjetas no aportan H1

- **WHEN** se inspecciona una tarjeta de contenido dentro de un listado
- **THEN** no contiene ningún `<h1>`
