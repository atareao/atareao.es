# seo Delta

## ADDED Requirements

### Requirement: Redirección alcanzable de `/sitemap_index.xml`

El plugin SHALL resolver la petición a `/sitemap_index.xml` con un **301** hacia `/sitemap.xml`
(el inventario real), y SHALL hacerlo desde un hook que se ejecute **antes** del despacho del endpoint
de sitemap de The SEO Framework —`parse_request` prioridad **15**, `bootstrap/init-front.php:44` en TSF
5.2.1—, de modo que la redirección no quede inalcanzable. La decisión de destino SHALL residir en una
única función (`sitemapRedirectTarget`).

El plugin SHALL NOT redirigir `/sitemap.xml` ni las rutas normales, SHALL NOT alterar el contenido de
`/sitemap.xml` y SHALL NOT depender de desactivar funciones de TSF.

El proyecto SHALL mantener una **guardia de orden** que falle si el callback de la redirección se
registra en un hook o prioridad que no preceda al despacho de TSF, para que un falso verde como el que
ocultó este fallo no pueda reintroducirse.

#### Scenario: `/sitemap_index.xml` redirige al inventario real

- **WHEN** se sigue una petición a `/sitemap_index.xml`
- **THEN** la respuesta es un **301** con `Location` en `/sitemap.xml`

#### Scenario: El inventario real y las rutas normales no se redirigen

- **WHEN** se inspecciona la decisión para `/sitemap.xml` o para una ruta normal
- **THEN** no hay redirección

#### Scenario: La redirección precede al despacho de TSF

- **WHEN** se inspeccionan los hooks registrados por el plugin
- **THEN** el callback de la redirección está en `parse_request` con prioridad anterior a 15

#### Scenario: El sitemap real no se altera

- **WHEN** se solicita `/sitemap.xml`
- **THEN** se sirve con **200** y su contenido no cambia por este comportamiento
