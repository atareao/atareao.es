# author-page — Delta

## Purpose

La página de autor del sitio como superficie indexable con identidad: hoy `/author/atareao/`
devuelve 404, de modo que la persona autora no tiene una URL canónica propia ni biografía, foto y
perfiles externos que un buscador o un consumidor agéntico pueda leer.

## ADDED Requirements

### Requirement: Archivo de autor indexable con identidad

El sistema SHALL servir la página del autor en una URL estable (`/author/atareao/`) con una
respuesta **200**, no un 404, y SHALL permitir su indexación. La página SHALL mostrar la biografía
del autor, su fotografía y sus perfiles externos, y SHALL declarar un único encabezado `<h1>` con el
nombre del autor. Los perfiles externos SHALL publicarse además como `sameAs` en la entidad `Person`
del grafo de datos estructurados conforme a la capability `structured-data`.

#### Scenario: La página de autor responde

- **WHEN** se solicita `/author/atareao/`
- **THEN** la respuesta es 200 y no un 404

#### Scenario: La página es indexable

- **WHEN** se inspecciona el `<head>` de la página de autor
- **THEN** no declara `noindex` y la URL aparece como indexable

#### Scenario: Muestra biografía y fotografía

- **WHEN** se inspecciona el cuerpo de la página de autor
- **THEN** contiene la biografía del autor y su fotografía

#### Scenario: Encabezado único

- **WHEN** se cuentan los elementos `<h1>` de la página de autor
- **THEN** hay exactamente uno y contiene el nombre del autor

#### Scenario: Los perfiles se publican como sameAs

- **WHEN** se inspecciona la entidad `Person` del grafo de la página de autor
- **THEN** declara `sameAs` con sus perfiles externos (GitHub, YouTube y plataformas de podcast)
