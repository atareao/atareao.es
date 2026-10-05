# content-model Specification

## Purpose
Esta capability fija el vínculo entre los tipos de post que existen realmente en atareao.es (`post`, `podcast`, `capitulo`, `tutorial`, `aplicacion`, `software`) y los metadatos, metaboxes y taxonomías que el plugin les asocia, de modo que ninguna referencia apunte a un tipo de post inexistente. Hoy el plugin referencia `application`, que no está registrado ni tiene contenido, dejando sin editor los metadatos de descarga/versión y sin asignación las categorías de aplicación del CPT real `aplicacion`.

## Requirements

### Requirement: Metadatos y metaboxes ligados al CPT real

Los metadatos `_download_url`, `_repository_url` y `_version` y sus metaboxes SHALL asociarse al CPT `aplicacion` y al CPT `software`. El metadato `post_views_count` y su metabox «Vistas» SHALL asociarse únicamente a tipos de post existentes (`post`, `podcast`, `capitulo`, `tutorial`, `aplicacion`, `software`). El sistema SHALL NOT registrar metadatos, metaboxes ni argumentos de taxonomía contra un tipo de post inexistente (`application`).

#### Scenario: Editar metadatos de descarga en un `aplicacion`

- **WHEN** un editor abre un post de tipo `aplicacion`
- **THEN** ve los metaboxes de URL de Descarga, Repositorio y Versión, y puede guardarlos

#### Scenario: El editor de `software` se conserva

- **WHEN** un editor abre un post de tipo `software`
- **THEN** sigue viendo los metaboxes de Descarga, Repositorio y Versión

#### Scenario: Sin tipos inexistentes en los bindings

- **WHEN** se inspeccionan los tipos asociados a los metadatos y metaboxes del plugin
- **THEN** ninguno de ellos es `application`

### Requirement: Taxonomías ligadas al CPT real

La taxonomía de categorías de aplicaciones (clave `aplicacion_category`) SHALL ligarse al CPT `aplicacion`. La taxonomía `platform` SHALL ligarse a los CPT `aplicacion` y `software`. Ninguna taxonomía del plugin SHALL ligarse al tipo inexistente `application`. Las plantillas del tema que muestran estas taxonomías SHALL consultar la clave vigente.

#### Scenario: Asignar categoría a un `aplicacion`

- **WHEN** un editor abre un post de tipo `aplicacion`
- **THEN** puede asignarle términos de la taxonomía de categorías de aplicaciones y esas categorías se muestran en la plantilla

#### Scenario: Plataformas en `aplicacion` y `software`

- **WHEN** se registra la taxonomía `platform`
- **THEN** sus tipos incluyen `aplicacion` y `software`, y no `application`

#### Scenario: Sin claves de taxonomía muertas en las plantillas

- **WHEN** se renderiza un `aplicacion`
- **THEN** la plantilla consulta la clave de taxonomía vigente y no un identificador muerto

### Requirement: Ausencia de referencias muertas al tipo `application`

El plugin y el tema SHALL NOT contener referencias a un tipo de post `application` inexistente. No SHALL existir una plantilla `single-application.php` activa ni el tipo `application` en las listas de tipos usadas por el buscador o el 404.

#### Scenario: Sin plantilla muerta

- **WHEN** se inspecciona el tema
- **THEN** no existe `single-application.php`

#### Scenario: Buscador sin tipo inexistente

- **WHEN** se inspeccionan los tipos usados por el buscador / `404.php`
- **THEN** la lista no contiene `application`
