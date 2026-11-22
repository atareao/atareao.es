# search-indexing — Delta

## Purpose

La indexabilidad de las páginas de búsqueda del sitio y de su paginación: todas las URLs de
`/search/` deben quedar fuera del índice de los buscadores, con o sin resultados, de modo que el
resultado interno de búsqueda no compita ni genere ruido de rastreo.

## ADDED Requirements

### Requirement: Las páginas de búsqueda no son indexables

El sitio SHALL servir **toda** URL de búsqueda bajo `/search/` —con resultados y sin resultados—
como HTTP `200` y SHALL incluir en su `<head>` una etiqueta `<meta name="robots">` cuyo contenido
incluya la directiva `noindex`. El sitio SHALL NOT servir ninguna página de búsqueda como indexable.
El requisito se aplica por igual a la **primera página** (`/search/<término>/`) y a cualquier página
de su **paginación** (`/search/<término>/page/<N>/`), sin límite de profundidad.

La emisión SHALL proceder de **un único emisor** de la etiqueta `robots` (ver la capability
`robots-meta`), de modo que añadir `noindex` a la búsqueda SHALL NOT introducir una segunda etiqueta
`robots` en el documento.

#### Scenario: Búsqueda con resultados

- **WHEN** se solicita `/search/linux/` y hay resultados para el término
- **THEN** la respuesta es `200` y la etiqueta `robots` del documento contiene `noindex`

#### Scenario: Búsqueda sin resultados

- **WHEN** se solicita una búsqueda que no arroja resultados (p. ej. `/search/page/81/`, que hoy ya responde `noindex`)
- **THEN** la respuesta sigue siendo `200` y la etiqueta `robots` contiene `noindex`, sin romperse

#### Scenario: Paginación profunda de una búsqueda

- **WHEN** se solicita una página de paginación de `/search/` (p. ej. `/search/linux/page/5/` o `page/309/`)
- **THEN** la respuesta es `200` y la etiqueta `robots` contiene `noindex`

#### Scenario: Búsqueda sin término

- **WHEN** se solicita `/search/` sin término o con el término vacío
- **THEN** la respuesta no es indexable: la etiqueta `robots` contiene `noindex`

#### Scenario: Una sola etiqueta `robots` en la búsqueda

- **WHEN** se inspecciona el `<head>` de una página de búsqueda
- **THEN** existe exactamente una etiqueta `<meta name="robots">`
