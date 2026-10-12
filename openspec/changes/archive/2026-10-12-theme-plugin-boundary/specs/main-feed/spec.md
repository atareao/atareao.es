# main-feed Delta

## ADDED Requirements

### Requirement: Main feed composition

La composición del feed principal SHALL ser propiedad del plugin `atareao-functionality`, registrada desde `\Atareao\PostTypes` sobre `pre_get_posts`. El feed principal SHALL incluir las entradas (`post`) y todos los tipos de contenido personalizados públicos, y SHALL excluir páginas y adjuntos. Los elementos SHALL ordenarse por fecha descendente y limitarse a contenido publicado. El sistema SHALL NOT alterar los feeds ligados al archivo de un tipo de contenido concreto, los feeds de comentarios ni la consulta principal del panel de administración.

El tema `atareao-theme` NO SHALL registrar la composición del feed principal. Las decisiones sobre *cómo se ve* un listado —cuántos elementos por página en el archivo de tutoriales y qué tipos aparecen en la página del blog— SHALL permanecer en el tema como política de presentación.

#### Scenario: Feed principal

- **WHEN** se solicita el feed principal
- **THEN** incluye entradas y todos los CPT públicos, excluye páginas y adjuntos, y ordena por fecha descendente

#### Scenario: Feed de archivo de un tipo concreto

- **WHEN** se solicita el feed de un archivo de tipo de contenido
- **THEN** su composición no se altera

#### Scenario: Feed de comentarios

- **WHEN** se solicita un feed de comentarios
- **THEN** su composición no se altera

#### Scenario: Consulta principal del panel

- **WHEN** la consulta principal es del panel de administración
- **THEN** no se altera

#### Scenario: Solo contenido publicado

- **WHEN** existen borradores o entradas programadas
- **THEN** no aparecen en el feed principal
