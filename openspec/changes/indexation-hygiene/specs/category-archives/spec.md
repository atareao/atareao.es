# category-archives — Delta

## Purpose

La indexabilidad de los archivos de categoría del sitio y de las familias de listado que GSC mide
como ruido de rastreo paginado (`/software/`, `/ubuntu/`, `/como/`): quedan fuera del índice,
incluidas sus páginas de paginación, sin degradar los archivos de listado que el sitio mantiene como
superficies indexables.

## ADDED Requirements

### Requirement: Los archivos de categoría no son indexables

El sitio SHALL servir como HTTP `200` con `noindex` en la etiqueta `robots` —es decir, `noindex,
follow`— las siguientes URLs y **sus páginas de paginación**:

- los **archivos de término** de la taxonomía de categorías (p. ej. `/ubuntu/`, `/como/`);
- las **familias medidas en GSC** como ruido de rastreo paginado: `/software/`, `/ubuntu/` y
  `/como/`.

El requisito SHALL cubrir `/page/<N>/` para cualquier `N`, sin límite de profundidad (se han medido
`/software/page/143/` y `/ubuntu/page/72/`). La emisión SHALL proceder de un único emisor de la
etiqueta `robots` (capability `robots-meta`).

El sitio SHALL NOT aplicar este requisito a las superficies de listado que mantiene indexables
(`/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/`), que SHALL seguir respondiendo `200` sin
`noindex`, ni a los contenidos singulares (posts, tutoriales, episodios).

#### Scenario: Archivo de categoría en su primera página

- **WHEN** se solicita `/ubuntu/` o `/como/`
- **THEN** la respuesta es `200` y la etiqueta `robots` contiene `noindex`

#### Scenario: Archivo de la familia medida

- **WHEN** se solicita `/software/`
- **THEN** la respuesta es `200` y la etiqueta `robots` contiene `noindex`

#### Scenario: Paginación del archivo

- **WHEN** se solicita `/software/page/3/` o `/ubuntu/page/2/`
- **THEN** la respuesta es `200` y la etiqueta `robots` contiene `noindex`

#### Scenario: Los listados que se mantienen indexables no se degradan

- **WHEN** se solicita `/blog/`, `/tutoriales/`, `/podcast/` o `/aplicaciones/`
- **THEN** la respuesta es `200` y su etiqueta `robots` NO contiene `noindex`

#### Scenario: Una sola etiqueta `robots` en el archivo

- **WHEN** se inspecciona el `<head>` de un archivo de categoría
- **THEN** existe exactamente una etiqueta `<meta name="robots">`
