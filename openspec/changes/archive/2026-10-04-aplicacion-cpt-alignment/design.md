# Design: Alineación del CPT `aplicacion`

## Context

Evidencia de producción (por red, 2026-10-04):
- `/wp-json/wp/v2/types` lista `aplicacion` (rest_base `aplicacion`) y **no** `application`.
- `/wp-json/wp/v2/application` → 404; `/wp-json/wp/v2/aplicacion` → 200.
- `/wp-json/wp/v2/taxonomies`: `application_category` con `types: ['application']` y `platform` con `types: ['application','software']`.
- `/wp-json/wp/v2/application_category?per_page=20` → `[]` (**0 términos**).

Código afectado: `class-metaboxes.php` (`$types`, `$app_types`, `add_meta_box(... array('application','software') ...)`, `remove_meta_box('postcustom','application',...)`), `class-taxonomies.php` (`register_taxonomy('application_category','application', …)`, `register_taxonomy('platform', array('application','software'), …)`), plantillas del tema.

## Decisions

1. **Sustituir `application` por `aplicacion`** en los bindings del plugin. El CPT real es `aplicacion`; `software` se mantiene.
2. **Renombrar la clave de taxonomía `application_category` → `aplicacion_category`.** Es BREAKING, pero hay **0 términos** (verificado), por lo que no requiere migración de datos ni reasignación. Alternativa (mantener la clave vieja y solo re-apuntar el tipo) dejaría un identificador engañoso y divergente del resto (`software_category`, `tutorial_category`); se descarta.
3. **`platform`** se re-apunta a `array('aplicacion','software')`. Los términos por defecto (`Linux`, `Windows`, …) se insertan igual (`insertDefaultPlatformTerms()` no depende del tipo).
4. **Eliminar `single-application.php`** (plantilla muerta: no hay posts de tipo `application`) y la entrada `'application'` de `404.php`. Se conserva `single-aplicacion.php`.
5. **Actualizar las plantillas del tema** que consultan `get_the_terms(..., 'application_category')` para usar `aplicacion_category` (`template-parts/content-aplicacion.php`, `single-aplicacion.php`).

## Riesgos y mitigación

- **Datos**: la taxonomía renombrada tiene 0 términos y ningún post puede estar asignado a `application` (tipo inexistente). Riesgo nulo verificado por red; aun así, el E2E comprobará que `aplicacion` sigue mostrando sus categorías si las hubiera.
- **Usuarios que migraron `_download_url`/`_version` a `aplicacion` por vía directa**: tras el cambio podrán editarlos desde el editor; no se borra ningún valor existente.
- **SEO/rewrites**: `platform` tiene rewrite `plataforma` y `application_category` `aplicacion-categoria`; al renombrar la clave se conserva el slug de rewrite, por lo que las URLs públicas no cambian.

## Verification

- Estáticos: `just php-lint`, `just phpcs` (delta +0).
- Búsqueda: sin `'application'` en bindings del plugin salvo comentarios históricos; sin `single-application.php`.
- E2E: abrir un `aplicacion` en el editor y ver los metaboxes; REST `/wp-json/wp/v2/aplicacion/<id>` sin metas `_`; REST `/wp-json/wp/v2/aplicacion_category` operativo; `/aplicaciones/` y `/aplicacion/<slug>` con normalidad.
