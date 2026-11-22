# robots-meta — Delta

## Purpose

La emisión de la etiqueta `<meta name="robots">` en el documento servido: cada página SHALL exponer
como máximo una etiqueta `robots` coherente, de modo que los buscadores no reciban directivas
contradictorias procedentes de dos emisores distintos.

## ADDED Requirements

### Requirement: Una única etiqueta `robots` por documento

El documento HTML servido por el sitio SHALL contener **como máximo una** etiqueta
`<meta name="robots">`. En las páginas de comentario (`?replytocom=`) —donde hoy conviven la
etiqueta `noindex, follow` de WordPress y la de The SEO Framework— el sitio SHALL emitir
**exactamente una** etiqueta, cuyo contenido SHALL incluir `noindex`. La emisión SHALL proceder de un
único emisor.

Este requisito SHALL convivir con las capabilities `search-indexing` y `category-archives`: las
páginas que pasan a `noindex` SHALL NOT ganar una segunda etiqueta `robots` por efecto de ese cambio.

#### Scenario: Página de comentario con una sola etiqueta

- **WHEN** se solicita una URL con `?replytocom=` de un post existente
- **THEN** el `<head>` contiene exactamente una etiqueta `<meta name="robots">` y su contenido incluye `noindex`

#### Scenario: Página de búsqueda con una sola etiqueta

- **WHEN** se solicita una página de `/search/`
- **THEN** el `<head>` contiene exactamente una etiqueta `<meta name="robots">`

#### Scenario: Un post normal no se ve afectado

- **WHEN** se solicita un post sin `?replytocom=`
- **THEN** el `<head>` contiene como máximo una etiqueta `<meta name="robots">` y este requisito no le añade `noindex`
