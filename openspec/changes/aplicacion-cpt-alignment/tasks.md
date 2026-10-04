# Tasks: Alineación del CPT `aplicacion`

> Sin framework de tests ni build tools. Verificación por estáticos, búsquedas y E2E. La implementación arranca solo tras la aprobación.

## 1. Línea base
- [x] 1.1 Baseline `just php-lint` (0 errores) y `just phpcs` (par errores/warnings). **Evidencia:** números.
- [x] 1.2 Evidencia de producción por red: `/wp-json/wp/v2/types` sin `application`; `/wp-json/wp/v2/application` 404; `application_category` con 0 términos. **Evidencia:** salidas anotadas (ya capturadas 2026-10-04).

## 2. RED — caracterización del defecto
- [x] 2.1 Arnés/verificación: comprobar que con el código actual, un `aplicacion` no ofrece los metaboxes de Descarga/Repositorio/Versión y que los bindings referencian `application`. **Verificación:** evidencia de los tipos `array('application','software')` y de la taxonomía ligada a `application`.

## 3. GREEN — implementación
- [x] 3.1 `class-metaboxes.php`: `_download_url`/`_repository_url`/`_version` → `$app_types = array('aplicacion','software')`; metaboxes de Descarga/Repositorio/Versión → `array('aplicacion','software')`; `$types`/`$view_types` sin `application`; `remove_meta_box('postcustom','aplicacion',...)` (quitar el de `application`). **Verificación:** grep sin `'application'`.
- [x] 3.2 `class-taxonomies.php`: `register_taxonomy('aplicacion_category','aplicacion', $args)` y `register_taxonomy('platform', array('aplicacion','software'), $args)`. **Verificación:** grep.
- [x] 3.3 Tema: actualizar `template-parts/content-aplicacion.php` y `single-aplicacion.php` a `aplicacion_category`; retirar `'application'` de `404.php`; eliminar `single-application.php`. **Verificación:** grep sin `application_category` ni `'application'`.
- [x] 3.4 `just php-lint` → 0 errores; `just phpcs` → delta +0.

## 4. Verificación
- [x] 4.1 `openspec validate aplicacion-cpt-alignment` → valid.
- [x] 4.2 Búsqueda de no-regresión: ninguna referencia a `'application'` en bindings del plugin ni en listas del tema.

## 5. E2E en producción
- [ ] 5.1 Editor: un `aplicacion` muestra Descarga/Repositorio/Versión/Vistas y guarda. **Evidencia:** capturas/valores.
- [ ] 5.2 REST: `/wp-json/wp/v2/aplicacion/<id>` sin claves `_`; `/wp-json/wp/v2/aplicacion_category` operativo; `platform` con tipos `aplicacion`,`software`. **Evidencia:** salidas.
- [ ] 5.3 No-regresión: `/aplicaciones/`, `/aplicacion/<slug>`, `/tools/` y el editor sin cambios observables. **Evidencia:** HTTP 200.

## 6. Entrega
- [ ] 6.1 Sincronizar `tasks.md`.
- [ ] 6.2 PR por gitflow a `development` (commits convencionales con gitmoji).
- [ ] 6.3 `openspec archive aplicacion-cpt-alignment` (crea `content-model`).

## 7. Evidencia observada (2026-10-04)

- Evidencia de producción por red: `/wp-json/wp/v2/types` sin `application`; `/wp-json/wp/v2/application` → 404; `/wp-json/wp/v2/aplicacion` → 200; `/wp-json/wp/v2/application_category?per_page=20` → `[]` (0 términos).
- RED: con el código previo, `$app_types`/`$seo_endpoints`/`$types`/`$view_types` y los metaboxes referenciaban `application`; la taxonomía se registraba para `application`.
- GREEN: sustituido `application` → `aplicacion` en `class-metaboxes.php` (`$seo_endpoints`, `$types`, `$app_types`, metaboxes de Descarga/Repositorio/Versión, `$view_types`, `registerViewsAdminHooks`, y `remove_meta_box('postcustom','application',...)` eliminado), `class-taxonomies.php` (`aplicacion_category` ligada a `aplicacion`; `platform` a `aplicacion`+`software`), `404.php`, `single-aplicacion.php` y `template-parts/content-aplicacion.php`; READMEs del plugin/tema actualizados; `single-application.php` **eliminado** (109 líneas).
- `just php-lint` → 0 errores (60 ficheros PHP sin errores de sintaxis).
- `phpcs --standard=PSR12 --report=summary` (tema+plugin) → **752 errores / 421 warnings en 69 ficheros** (baseline 752/428 en 70): errores **+0**, warnings **−7** (los de la plantilla eliminada).
- `grep` de no-regresión: 0 referencias a `'application'`/`"application"` como tipo de post en plugin y tema.
- Arnés externo `/tmp/opencode/metaboxes-meta-harness/` → `TOTAL=23 PASS=22 FAIL=1`; el único `FAIL` (`VIEW-01`) es un escenario del change `views-sanitize-nonnegative` (saneado no negativo), cuya corrección **no forma parte de esta rama**; los escenarios de esta capability (`AAPP-01`/`AAPP-02`, `ME-04c`, `MT-*`, `SEO-*`, `CTR-01`) pasan.
- `openspec validate aplicacion-cpt-alignment` → «Change 'aplicacion-cpt-alignment' is valid».
- Pendiente: E2E en producción (5.x) y PR/archive (6.2/6.3).
