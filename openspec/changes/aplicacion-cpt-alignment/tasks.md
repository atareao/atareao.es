# Tasks: Alineación del CPT `aplicacion`

> Sin framework de tests ni build tools. Verificación por estáticos, búsquedas y E2E. La implementación arranca solo tras la aprobación.

## 1. Línea base
- [ ] 1.1 Baseline `just php-lint` (0 errores) y `just phpcs` (par errores/warnings). **Evidencia:** números.
- [ ] 1.2 Evidencia de producción por red: `/wp-json/wp/v2/types` sin `application`; `/wp-json/wp/v2/application` 404; `application_category` con 0 términos. **Evidencia:** salidas anotadas (ya capturadas 2026-10-04).

## 2. RED — caracterización del defecto
- [ ] 2.1 Arnés/verificación: comprobar que con el código actual, un `aplicacion` no ofrece los metaboxes de Descarga/Repositorio/Versión y que los bindings referencian `application`. **Verificación:** evidencia de los tipos `array('application','software')` y de la taxonomía ligada a `application`.

## 3. GREEN — implementación
- [ ] 3.1 `class-metaboxes.php`: `_download_url`/`_repository_url`/`_version` → `$app_types = array('aplicacion','software')`; metaboxes de Descarga/Repositorio/Versión → `array('aplicacion','software')`; `$types`/`$view_types` sin `application`; `remove_meta_box('postcustom','aplicacion',...)` (quitar el de `application`). **Verificación:** grep sin `'application'`.
- [ ] 3.2 `class-taxonomies.php`: `register_taxonomy('aplicacion_category','aplicacion', $args)` y `register_taxonomy('platform', array('aplicacion','software'), $args)`. **Verificación:** grep.
- [ ] 3.3 Tema: actualizar `template-parts/content-aplicacion.php` y `single-aplicacion.php` a `aplicacion_category`; retirar `'application'` de `404.php`; eliminar `single-application.php`. **Verificación:** grep sin `application_category` ni `'application'`.
- [ ] 3.4 `just php-lint` → 0 errores; `just phpcs` → delta +0.

## 4. Verificación
- [ ] 4.1 `openspec validate aplicacion-cpt-alignment` → valid.
- [ ] 4.2 Búsqueda de no-regresión: ninguna referencia a `'application'` en bindings del plugin ni en listas del tema.

## 5. E2E en producción
- [ ] 5.1 Editor: un `aplicacion` muestra Descarga/Repositorio/Versión/Vistas y guarda. **Evidencia:** capturas/valores.
- [ ] 5.2 REST: `/wp-json/wp/v2/aplicacion/<id>` sin claves `_`; `/wp-json/wp/v2/aplicacion_category` operativo; `platform` con tipos `aplicacion`,`software`. **Evidencia:** salidas.
- [ ] 5.3 No-regresión: `/aplicaciones/`, `/aplicacion/<slug>`, `/tools/` y el editor sin cambios observables. **Evidencia:** HTTP 200.

## 6. Entrega
- [ ] 6.1 Sincronizar `tasks.md`.
- [ ] 6.2 PR por gitflow a `development` (commits convencionales con gitmoji).
- [ ] 6.3 `openspec archive aplicacion-cpt-alignment` (crea `content-model`).
