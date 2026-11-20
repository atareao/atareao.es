# structured-data — Delta

## ADDED Requirements

### Requirement: Datos estructurados de la serie y de los episodios de podcast

El grafo JSON-LD SHALL declarar una entidad `PodcastSeries` en el archivo `/podcast/` y una entidad
`PodcastEpisode` en la página de cada episodio, **añadidas al mismo grafo** que ya compone el plugin
de SEO (sin un segundo bloque `ld+json`). La `PodcastSeries` SHALL declarar el nombre de la serie, su
URL y su imagen, y SHALL ser referenciada por `@id` desde cada `PodcastEpisode`. Cada
`PodcastEpisode` SHALL declarar su número de episodio, su `partOfSeries` (por `@id`), su fecha de
publicación en ISO 8601 y su medio asociado (`associatedMedia` con la URL del audio) cuando exista
audio. El markup SHALL NOT inventar datos: una propiedad cuyo valor no exista SHALL omitirse.

#### Scenario: La serie se declara en el archivo de podcast

- **WHEN** se inspecciona el grafo de `/podcast/`
- **THEN** contiene una entidad `PodcastSeries` con su nombre, su URL y su imagen

#### Scenario: Cada episodio declara su entidad

- **WHEN** se inspecciona el grafo de la página de un episodio
- **THEN** contiene una entidad `PodcastEpisode` con su número, su fecha de publicación y su `partOfSeries`

#### Scenario: El episodio enlaza la serie por @id

- **WHEN** se compara `partOfSeries.@id` del episodio con el `@id` de la `PodcastSeries` del archivo
- **THEN** ambos valores son idénticos

#### Scenario: El audio se declara cuando existe

- **WHEN** el episodio tiene audio asociado
- **THEN** su `PodcastEpisode` declara `associatedMedia` con la URL del audio

#### Scenario: Un solo bloque ld+json

- **WHEN** se cuentan los bloques `<script type="application/ld+json">` de `/podcast/` y de un episodio
- **THEN** hay exactamente uno en cada página, con las entidades de podcast dentro

### Requirement: VideoObject en los contenidos con vídeo

Cuando una página embebe un vídeo (en un episodio de podcast o en un capítulo de tutorial), el grafo
SHALL declarar una entidad `VideoObject` con el nombre, la descripción, la miniatura, la fecha de
subida y la URL del vídeo, referenciada desde la entidad del contenido. Si la página no embebe
vídeo, el grafo SHALL NOT declarar `VideoObject`.

#### Scenario: El episodio con vídeo declara VideoObject

- **WHEN** se inspecciona el grafo de un episodio que embebe un vídeo
- **THEN** contiene una entidad `VideoObject` con nombre, miniatura, fecha de subida y URL del vídeo

#### Scenario: Sin vídeo no hay VideoObject

- **WHEN** se inspecciona el grafo de un contenido que no embebe ningún vídeo
- **THEN** no contiene ninguna entidad `VideoObject`

### Requirement: HowTo en los tutoriales

El grafo de un tutorial que exponga pasos ordenados SHALL declarar una entidad `HowTo` con el nombre
del tutorial y la lista ordenada de pasos (`step` con `name` y `text`). Si el tutorial no expone
pasos ordenados, el grafo SHALL NOT declarar `HowTo`. La emisión es válida aunque Google haya
retirado el resultado enriquecido de `HowTo`: el dato se publica para consumidores agénticos y de
IA.

#### Scenario: El tutorial declara sus pasos

- **WHEN** se inspecciona el grafo de un tutorial con pasos ordenados
- **THEN** contiene una entidad `HowTo` con su nombre y la lista de pasos en orden

#### Scenario: Un tutorial sin pasos no declara HowTo

- **WHEN** se inspecciona el grafo de un tutorial que no expone pasos ordenados
- **THEN** no contiene ninguna entidad `HowTo`

### Requirement: Organización y perfiles de la persona

El grafo SHALL declarar una entidad `Organization` para el sitio, con su nombre, su URL, su logotipo
y sus perfiles externos (`sameAs`), referenciada por `@id` como editorial cuando corresponda. La
entidad `Person` que el grafo declara SHALL incluir `sameAs` con los perfiles externos del autor
(GitHub, YouTube y plataformas de podcast). El sistema SHALL NOT duplicar entidades ya presentes en
el grafo: se enlazan por `@id`.

#### Scenario: La organización se declara

- **WHEN** se inspecciona el grafo del sitio
- **THEN** contiene una entidad `Organization` con su nombre, su URL y su logotipo

#### Scenario: La organización declara sus perfiles

- **WHEN** se inspecciona la entidad `Organization`
- **THEN** declara `sameAs` con los perfiles externos del sitio

#### Scenario: La persona declara sus perfiles

- **WHEN** se inspecciona la entidad `Person` del grafo
- **THEN** declara `sameAs` con sus perfiles de GitHub, YouTube y plataformas de podcast

#### Scenario: Las entidades no se duplican

- **WHEN** una entidad ya está presente en el grafo
- **THEN** el markup la referencia por `@id` en lugar de repetir sus datos

## MODIFIED Requirements

### Requirement: Cobertura y no interferencia de los datos estructurados

El markup SHALL emitirse únicamente en **contenidos singulares publicados y públicos** de los tipos
editoriales cubiertos, y SHALL NOT emitirse en archivos, en la portada, en la búsqueda, en la página
de error ni en el panel de administración. La única excepción es la entidad `PodcastSeries`, que
SHALL emitirse en el archivo `/podcast/` y solo en él. SHALL NOT emitirse markup para contenido **no
público** (borradores, privados, programados o protegidos por contraseña).

Las entidades que el plugin de SEO ya emitía —`WebSite`, `WebPage`, `Person`, `BreadcrumbList` y la
entidad editorial— SHALL permanecer **inalteradas** en su tipo y en su `@id`. El resto del `<head>`
—título, descripción, URL canónica y sitemap— SHALL NOT cambiar por este comportamiento.

#### Scenario: Solo en contenido singular publicado

- **WHEN** se solicita la página de un contenido editorial singular publicado y público
- **THEN** el grafo incluye la entidad de artículo

#### Scenario: La serie de podcast se emite en su archivo

- **WHEN** se solicita el archivo `/podcast/`
- **THEN** el grafo incluye la entidad `PodcastSeries`

#### Scenario: No se emite fuera del singular

- **WHEN** se solicitan otros archivos, la portada, una búsqueda o la página de error
- **THEN** no se emite la entidad de artículo ni la de serie

#### Scenario: No se emite para contenido no público

- **WHEN** el contenido es un borrador, es privado, está programado o está protegido por contraseña
- **THEN** no se emite la entidad de artículo

#### Scenario: Las entidades del plugin de SEO no cambian

- **WHEN** se comparan los tipos y los `@id` de las entidades que ya emitía el plugin de SEO antes y después de este change
- **THEN** son idénticos

#### Scenario: El resto del head no cambia

- **WHEN** se comparan el título, la descripción, la URL canónica y el sitemap antes y después de este change
- **THEN** no cambian
