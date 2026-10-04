# Proposal: Alineación del CPT `aplicacion` (y limpieza de referencias a `application`)

## Why

El plugin referencia el tipo de post `application`, que **no existe** en producción: el CPT registrado y con contenido es `aplicacion` (verificado por red: `/wp-json/wp/v2/application` → 404, `/wp-json/wp/v2/aplicacion` → 200). En consecuencia, los metadatos `_download_url`, `_repository_url` y `_version`, sus metaboxes, y las taxonomías `application_category` y `platform` no están ligados al CPT real, de modo que no se pueden editar ni asignar desde el editor de `aplicacion`; además persisten restos muertos (`single-application.php` y `'application'` en el buscador del 404).

## What Changes

- **Metadatos y metaboxes ligados al CPT real.** `_download_url`, `_repository_url` y `_version` (y sus metaboxes de Descarga/Repositorio/Versión) SHALL registrarse para `aplicacion` y `software`, retirando el tipo inexistente `application`. El metabox «Vistas» y `post_views_count` SHALL asociarse a los tipos existentes (`post`, `podcast`, `capitulo`, `tutorial`, `aplicacion`, `software`) sin `application`.
- **Taxonomías ligadas al CPT real.** La taxonomía de categorías de aplicaciones SHALL ligarse a `aplicacion`; se renombra su clave `application_category` → `aplicacion_category` (**BREAKING**: en producción tiene 0 términos, verificado por red → sin migración de datos). La taxonomía `platform` SHALL ligarse a `aplicacion` y `software`. Las plantillas del tema SHALL consultar la clave vigente.
- **Limpieza de restos muertos.** Se elimina la plantilla `single-application.php` y la referencia `'application'` del buscador de `404.php`.
- Sin cambios en los CPT existentes (`aplicacion`, `software`, `podcast`, `capitulo`, `tutorial`), sus slugs ni sus rewrites.

## Capabilities

### New Capabilities

- `content-model`: contrato de vinculación de metadatos, metaboxes y taxonomías a los tipos de post que existen realmente, sin referencias a tipos inexistentes.

### Modified Capabilities

- Ninguna. (La spec `metaboxes` conserva un requisito histórico que declara `registerMetaFields()` «fuera de alcance»; su limpieza es deuda documental y no cambia comportamiento observable, por lo que queda fuera de este change.)

## Fuera de alcance

- No se renombra el CPT `aplicacion` ni su slug (`aplicacion`) / archivo (`aplicaciones`).
- No se modifican datos: no hay términos que migrar (0 términos en `application_category`), y no se reasignan posts.
- No cambia el resto de taxonomías (`tutorial_category`, `tutorial_tag`, `software_category`, `difficulty`, `platform` salvo el tipo `aplicacion`).
- No se toca el contratato REST de metadatos (`rest-metafields`).

## Impact

- **Archivos a modificar (solo tras aprobación, en la fase TDD):**
  - `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php` — arreglos `$types` y `$app_types`, tipos de los metaboxes de descarga/repositorio/versión/vistas y sus `remove_meta_box`.
  - `wp-content/plugins/atareao-functionality/includes/class-taxonomies.php` — `application_category` → `aplicacion_category` (tipo `aplicacion`); `platform` (`aplicacion` + `software`).
  - `wp-content/themes/atareao-theme/template-parts/content-aplicacion.php` y `single-aplicacion.php` — clave de taxonomía.
  - `wp-content/themes/atareao-theme/404.php` — retirar `'application'` de la lista de tipos del buscador.
  - `wp-content/themes/atareao-theme/single-application.php` — eliminar (plantilla muerta).
- **Nuevas specs al archivar:** `openspec/specs/content-model/spec.md`.
- **Contratos que NO se tocan:** CPTs, slugs/rewrites, nombres de meta, taxonomías distintas de las citadas.
- **Verificación:** sin framework de tests; `just php-lint` (0 errores) + `just phpcs` (delta +0), búsqueda de no-referencia a `application`, E2E (editar descarga/versión/categoría en un `aplicacion` y REST de taxonomías).
