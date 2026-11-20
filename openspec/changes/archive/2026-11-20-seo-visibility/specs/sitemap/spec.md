# sitemap — Delta

## Purpose

Qué URLs publica el sitio en su sitemap y con qué forma, de modo que los buscadores reciban un
único inventario coherente en lugar de dos ficheros con contenido idéntico y una cola de contenido
obsoleto.

## ADDED Requirements

### Requirement: Contenido y forma del sitemap

El sitio SHALL publicar un único inventario canónico de URLs indexables y SHALL NOT servir
contenido idéntico bajo nombres distintos: si expone `sitemap.xml` y `sitemap_index.xml`, el segundo
SHALL ser un índice real (con referencias a otros sitemaps) o SHALL NOT publicarse. El inventario
SHALL NOT incluir la cola de URLs obsoletas de 2010–2016, salvo que se documente de forma explícita
la decisión de mantenerlas. La decisión sobre qué se publica y con qué forma SHALL quedar por
escrito en `docs/`.

#### Scenario: No hay dos ficheros idénticos

- **WHEN** se descargan `sitemap.xml` y `sitemap_index.xml` y se comparan
- **THEN** o no son idénticos, o solo se publica uno de los dos

#### Scenario: El nombre de índice lo cumple

- **WHEN** se publica un fichero llamado `sitemap_index.xml`
- **THEN** es un índice real con elementos `<sitemap>` que referencian otros sitemaps

#### Scenario: Sin cola obsoleta

- **WHEN** se listan las URLs del sitemap publicado
- **THEN** no incluye las URLs obsoletas de 2010–2016, salvo decisión documentada en `docs/`

#### Scenario: La decisión queda documentada

- **WHEN** se busca la política del sitemap en `docs/`
- **THEN** existe un documento que explica qué se publica y con qué forma
