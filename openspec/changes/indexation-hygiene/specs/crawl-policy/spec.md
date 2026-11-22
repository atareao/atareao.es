# crawl-policy — Delta

## Purpose

Las directivas `Disallow` del `robots.txt` servido por el sitio, de modo que los rastreadores dejen
de solicitar las URLs de basura de comentarios (`?replytocom=`) y las páginas de búsqueda interna,
sin alterar las directivas que el sitio ya sirve hoy.

## ADDED Requirements

### Requirement: El `robots.txt` corta el rastreo de comentarios y búsquedas

El `robots.txt` servido por el sitio SHALL contener al menos estas dos directivas:

- `Disallow: /*?replytocom=` — las URLs de comentario con el parámetro `replytocom`;
- `Disallow: /search/` — las páginas de búsqueda interna.

El `robots.txt` del sitio es **virtual**: lo genera WordPress (a través de The SEO Framework) y pasa
por el filtro `robots_txt`; el sitio SHALL NOT depender de un fichero `robots.txt` en disco. La
incorporación de las directivas SHALL hacerse a través de ese mismo filtro `robots_txt` y SHALL NOT
eliminar ni duplicar las directivas ya presentes (`Disallow: /wp-admin/`,
`Allow: /wp-admin/admin-ajax.php`, `Sitemap: https://atareao.es/sitemap.xml` y la directiva
`Agentmap:` de la capability `mcp-discovery`).

#### Scenario: Directiva de comentarios servida

- **WHEN** se descarga `/robots.txt` en producción
- **THEN** contiene una línea `Disallow: /*?replytocom=`

#### Scenario: Directiva de búsquedas servida

- **WHEN** se descarga `/robots.txt` en producción
- **THEN** contiene una línea `Disallow: /search/`

#### Scenario: Las directivas preexistentes se conservan

- **WHEN** se descarga `/robots.txt` y se comparan sus directivas con las de partida
- **THEN** siguen presentes `Disallow: /wp-admin/`, `Allow: /wp-admin/admin-ajax.php`,
  `Sitemap: https://atareao.es/sitemap.xml` y la línea `Agentmap:`

#### Scenario: No hay directiva duplicada

- **WHEN** se descarga `/robots.txt`
- **THEN** cada directiva añadida por este change aparece exactamente una vez
