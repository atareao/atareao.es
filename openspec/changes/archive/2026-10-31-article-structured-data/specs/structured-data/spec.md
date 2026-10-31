# structured-data — Delta

## ADDED Requirements

### Requirement: Entidad de artículo en el grafo del plugin de SEO

El sistema SHALL añadir al grafo JSON-LD que compone el plugin de SEO del sitio una entidad de
artículo —`Article` o su subtipo `BlogPosting`— para cada contenido editorial singular de tipo `post`,
`tutorial`, `capitulo`, `aplicacion` o `software`, **integrándola en el grafo existente**. El sistema
SHALL NOT emitir un segundo bloque `<script type="application/ld+json">` ni un grafo paralelo: la
salida de datos estructurados de la página SHALL seguir siendo **un único bloque**.

La entidad SHALL declarar:

- `headline` con el título del contenido;
- `datePublished` y `dateModified` en formato ISO 8601;
- `mainEntityOfPage` con un `@id` **igual** al `@id` de la entidad `WebPage` que el grafo ya declara;
- `author` como `Person`, referenciada **por `@id`** cuando el grafo ya declare esa persona y, en su
  defecto, con su nombre y la URL de su perfil;
- `publisher`, referenciado **por `@id`** a la entidad de organización o persona que el grafo ya
  declara;
- `image` como `ImageObject` con la imagen destacada del contenido, con URL absoluta y con sus
  dimensiones cuando se conozcan.

La entidad SHALL NOT duplicar entidades ya presentes en el grafo: se enlazan por `@id`. El markup
SHALL NOT inventar datos —una propiedad cuyo valor no exista SHALL omitirse en lugar de emitirse vacía
o con un valor por defecto— y el grafo resultante SHALL ser JSON-LD válido.

#### Scenario: La entidad de artículo se añade al grafo existente

- **WHEN** se inspecciona el grafo de datos estructurados de un contenido editorial singular
- **THEN** contiene una entidad de tipo `Article` o `BlogPosting` además de las entidades que el plugin de SEO ya emitía

#### Scenario: La emisión sigue siendo un único bloque

- **WHEN** se cuentan los bloques `<script type="application/ld+json">` de la página
- **THEN** hay exactamente uno, y la entidad de artículo está dentro de él

#### Scenario: El titular y las fechas se declaran en ISO 8601

- **WHEN** se inspecciona la entidad de artículo
- **THEN** declara `headline` con el título del contenido y `datePublished`/`dateModified` en ISO 8601

#### Scenario: La página y el artículo quedan enlazados

- **WHEN** se compara `mainEntityOfPage.@id` de la entidad de artículo con el `@id` de la entidad `WebPage` del grafo
- **THEN** ambos valores son idénticos

#### Scenario: Autor y editorial se referencian, no se duplican

- **WHEN** el grafo ya declara la persona autora o la entidad editorial
- **THEN** la entidad de artículo las referencia por `@id` y no repite sus datos

#### Scenario: La imagen destacada se describe como objeto de imagen

- **WHEN** el contenido tiene imagen destacada
- **THEN** `image` es un `ImageObject` con URL absoluta y, cuando se conocen, sus dimensiones

#### Scenario: Los datos ausentes se omiten, no se inventan

- **WHEN** el contenido no tiene autor, imagen destacada o fecha de modificación disponibles
- **THEN** la propiedad correspondiente no aparece en la entidad, en lugar de aparecer vacía o con un valor por defecto

#### Scenario: El grafo resultante es JSON-LD válido

- **WHEN** se parsea el bloque de datos estructurados
- **THEN** es JSON válido con `@context` y el grafo con la entidad de artículo

### Requirement: Cobertura y no interferencia de los datos estructurados

El markup SHALL emitirse únicamente en **contenidos singulares publicados y públicos** de los tipos
editoriales cubiertos, y SHALL NOT emitirse en archivos, en la portada, en la búsqueda, en la página
de error ni en el panel de administración. SHALL NOT emitirse para contenido **no público** (borradores,
privados, programados o protegidos por contraseña).

Las entidades que el plugin de SEO ya emitía —`WebSite`, `WebPage`, `Person`, `BreadcrumbList` y la
entidad editorial— SHALL permanecer **inalteradas** en su tipo y en su `@id`. El resto del `<head>`
—título, descripción, URL canónica y sitemap— SHALL NOT cambiar por este comportamiento.

#### Scenario: Solo en contenido singular publicado

- **WHEN** se solicita la página de un contenido editorial singular publicado y público
- **THEN** el grafo incluye la entidad de artículo

#### Scenario: No se emite fuera del singular

- **WHEN** se solicitan un archivo, la portada, una búsqueda o la página de error
- **THEN** no se emite la entidad de artículo

#### Scenario: No se emite para contenido no público

- **WHEN** el contenido es un borrador, es privado, está programado o está protegido por contraseña
- **THEN** no se emite la entidad de artículo

#### Scenario: Las entidades del plugin de SEO no cambian

- **WHEN** se comparan los tipos y los `@id` de las entidades que ya emitía el plugin de SEO antes y después de este change
- **THEN** son idénticos

#### Scenario: El resto del head no cambia

- **WHEN** se comparan el título, la descripción, la URL canónica y el sitemap antes y después de este change
- **THEN** no cambian
