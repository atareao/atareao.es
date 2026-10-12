# Tasks

> **Nota inicial:** el repositorio **no tiene framework de tests ni build tools**. La verificación combina análisis estático (`just php-lint`, `just phpcs`) y comprobación del estado en producción.
>
> **Estado:** aprobada por el usuario; implementada y en verificación (2026-10-12). Las tareas 5.2, 5.3 y 5.4 requieren desplegar y un stack en marcha.
>
> **Nota de alcance:** este change **no** bumpea `ATAREAO_PLUGIN_VERSION`: el bump lo aplica el pipeline de release en lockstep con `.vampus.yml`.

## 1. Verificación previa (contra el código, no contra la memoria)

- [x] 1.1 Confirmar la ubicación real de cada conducta antes de escribir nada. **Verificación:** `rg` localiza en `class-seo.php` los hooks `lazyLoading`, `imageSizes`, `jpegQuality` y `bigImageThreshold`, y confirma que el tema no registra ninguno. **Evidencia:** `rg` localiza los cinco `add_filter` en `class-seo.php` (`lazyLoading`@10, `fixEmptyAlt`@15, `optimizeLoading`@20, `imageSizes`@10, `jpegQuality`@10 en dos filtros, `bigImageThreshold`@10) y **ninguno** en el tema.
- [x] 1.2 Confirmar la política real de `sizes`. **Verificación:** `\Atareao\SEO::imageSizes()` devuelve `(max-width: 768px) 100vw, 1200px` si `is_singular()` y `(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw` en el resto; coincide con `openspec/specs/image-handling/spec.md`. **Evidencia:** `SEO::imageSizes()` devuelve `(max-width: 768px) 100vw, 1200px` si `is_singular()` y `(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw` en el resto; coincide con `openspec/specs/image-handling/spec.md`.
- [x] 1.3 Confirmar los valores actuales de las once opciones de tamaño en producción. **Verificación:** los valores son `150/150/1`, `300/300/0`, `768/0`, `1024/1024/0`; se anotan como referencia para comprobar que no cambian. **Evidencia:** Consultadas en producción: `150/150/1`, `300/300/0`, `768/0`, `1024/1024/0`. Se anotan como referencia para 5.2.

## 2. Código — los tamaños se fijan una sola vez

- [x] 2.1 Cambiar el hook de `atareao_theme_image_sizes()`: `after_setup_theme` → `after_switch_theme`. **Verificación:** `rg` muestra el `add_action` nuevo y ninguno viejo; el cuerpo de la función no cambia. **Evidencia:** `functions.php:88` → `add_action('after_switch_theme', 'atareao_theme_image_sizes');`. El cuerpo de la función no cambia. `atareao_theme_setup()` sigue en `after_setup_theme` (debe correr cada petición).
- [x] 2.2 Confirmar que no queda ninguna otra llamada a `update_option` en el tema. **Verificación:** `rg update_option` sobre el tema solo devuelve las de esa función. **Evidencia:** `rg update_option` sobre el tema → **11 llamadas**, todas dentro de `atareao_theme_image_sizes()`. No hay ninguna otra.

## 3. Documentación — partir la guía por la frontera

- [x] 3.1 Añadir al README del plugin (`wp-content/plugins/atareao-functionality/README.md`) una sección de **política de imágenes**: los cinco filtros y sus prioridades, la cadena 10→15→20, la política de `sizes` con sus dos cadenas literales, la calidad 85 y el umbral 2560. **Verificación:** las dos cadenas de `sizes` aparecen literalmente y coinciden con la spec. **Evidencia:** Nueva sección «Política de imágenes» en el README del plugin: tabla de los cinco filtros con callbacks y prioridades, la cadena 10→15→20, las dos cadenas literales de `sizes` (líneas 824-825) y la calidad 85 y el umbral 2560, con la nota de dependencia del tema.
- [x] 3.2 Recortar `README-IMAGENES-RESPONSIVE.md`: retirar las afirmaciones sobre lazy loading, política de `sizes`, calidad 85 y umbral 2560, sustituyéndolas por una referencia al plugin. **Verificación:** el README del tema ya no enuncia esas conductas. **Evidencia:** El README del tema ya no enuncia la política: pasa a referenciarla al plugin, con una nota de dependencia cerca del principio.
- [x] 3.3 Corregir los fragmentos de código obsoletos. **Verificación:** `rg` sobre el README del tema no encuentra `atareao_theme_add_lazy_loading`, `atareao_theme_custom_srcset_sizes`, `atareao_theme_jpeg_quality` ni `atareao_theme_big_image_threshold`; el ejemplo de personalización de `sizes` deja claro que la política del sitio vive en el plugin. **Evidencia:** `rg` sobre el README del tema → 0 coincidencias de las cuatro funciones retiradas y 0 de la cadena falsa `100vw, 50vw`. El ejemplo de personalización pasa a un plugin/mu-plugin y se marca como valor de ejemplo.
- [x] 3.4 Corregir el ejemplo de `sizes` de la sección de Rendimiento. **Verificación:** coincide con la política real de 3.1. **Evidencia:** El ejemplo de `sizes` queda elidido (`…`) con prosa que remite al plugin, para **no duplicar** la política y evitar que vuelva a desincronizarse.
- [x] 3.5 Corregir el diagnóstico de «Lazy loading no funciona» y el consejo del umbral. **Verificación:** ambos remiten al plugin como causa probable. **Evidencia:** «Lazy loading no funciona» apunta ahora al plugin activo como causa probable, y el consejo del umbral remite a `SEO::bigImageThreshold`.
- [x] 3.6 Corregir el «Resumen» y añadir la nota de dependencia del plugin. **Verificación:** el resumen no atribuye al tema lo que hace el plugin. **Evidencia:** El resumen dice «el tema y el plugin», y hay nota de dependencia.
- [x] 3.7 Conservar íntegro lo que sí es del tema: tamaños `atareao-*` (verificados como reales, registrados con `add_image_size`), aspect ratios, clases CSS y uso en plantillas. **Verificación:** esas secciones siguen presentes. **Evidencia:** Íntegras: tamaños `atareao-*` (verificados reales en `functions.php:24-26`), aspect ratios, breakpoints, alineaciones, uso en plantillas, clases CSS y configuración avanzada.

## 4. Documentación — corregir el registro archivado

- [x] 4.1 Corregir en `openspec/changes/archive/2026-10-12-theme-plugin-boundary/design.md` la observación del conflicto de query vars, marcándola como **retractada** y citando el código del core. **Verificación:** el texto ya no afirma que exista el conflicto y explica por qué era falso. **Evidencia:** Observación tachada y marcada **RETRACTADA** en el `design.md` archivado, con el código del core citado y la consecuencia explícita (no se toca el código).

## 5. Verificación

- [x] 5.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs` sin empeorar el baseline (763 E / 423 W en 74 ficheros). **Evidencia:** `just php-lint` → 61 ficheros, 0 errores. `just phpcs` → **763 E / 423 W en 74 ficheros, idéntico al baseline**.
- [x] 5.2 Los valores de las opciones **no cambian** con el change. **Verificación:** los once valores en producción siguen siendo los de 1.3 tras desplegar. **Evidencia:** Verificado en producción tras desplegar (1.16.1, 2026-10-12). Los once valores son idénticos a los de 1.3: `150/150/1`, `300/300/0`, `768/0`, `1024/1024/0`. `diff` contra el baseline capturado antes del despliegue: **sin diferencias**. **Los 6 ficheros desplegados son byte a byte idénticos a `origin/main`** (`functions.php`, `style.css`, los dos README, `class-seo.php` y el header del plugin), y `wp plugin get`/`wp theme get` reportan **1.16.1**. El código bajo prueba es el del release.
- [x] 5.3 La salida renderizada **no cambia**. **Verificación:** el HTML de una página singular, un archivo de CPT y `/feed/` sigue siendo idéntico al de referencia (los `sizes` de las imágenes siguen siendo los mismos). **Evidencia:** Verificado en producción tras desplegar. Las **9 páginas** (4 singulares, 4 archivos de CPT, `/feed/`) son **idénticas byte a byte** al baseline pre-despliegue (9/9 md5 coincidentes) y las **69 firmas de imagen** (`sizes`/`loading`/`fetchpriority`) son idénticas (121 líneas de firma comparadas). El arnés está validado como reproducible: la captura pre-despliegue coincide con la de la verificación del change anterior (9/9 md5). Medido de nuevo tras el despliegue final (1.16.1): 9/9 páginas y 69/69 firmas siguen idénticas.
- [ ] 5.4 **(requiere desplegar y stack en marcha)** Que el hook nuevo hace lo que dice. **Verificación:** en un entorno con el stack, desactivar y reactivar el tema deja las once opciones con los valores del tema; y un cambio en Ajustes → Medios **persiste** tras una petición. **Evidencia:** pendiente.
- [x] 5.5 Spec. **Verificación:** `openspec validate theme-image-alignment --strict` sin hallazgos. **Evidencia:** `openspec validate theme-image-alignment --strict` válido; `openspec validate --all` → 26 passed, 0 failed.

> **Residual conocido (2026-10-12): la tarea 5.4 queda SIN VERIFICAR.**
>
> En consecuencia la **6.2 tampoco se marca**: su verificación es «todas las casillas marcadas»,
> y por construcción no lo están. El archivado en sí **sí se ejecutó**
> (`openspec archive theme-image-alignment --yes`, `+1 added` a la spec).
>
> Decisión explícita del usuario al cerrar el change. Requiere desactivar y reactivar el tema,
> y eso no se hace en producción. Lo que **sí** está verificado empíricamente contra producción:
> el código desplegado es byte a byte el del release (6/6 ficheros), los once valores de opción
> no cambian y las 9 páginas siguen siendo idénticas con las 69 firmas de imagen intactas.
>
> Lo que **no** está verificado: que `after_switch_theme` fije las once opciones al activar el
> tema, y que un cambio en Ajustes → Medios persista tras una petición. Se puede cerrar en
> cualquier momento levantando el stack de desarrollo y siguiendo el enunciado de la 5.4.

## 6. Entrega

- [x] 6.1 PR por gitflow de `feature/theme-image-alignment` a `development` con commits convencionales. **Verificación:** PR abierto/mergeado; CI `lint` en verde. **Evidencia:** PR **#101** (`feature/theme-image-alignment` → `development`), fusionado en `development` como `77c3e4d`. Tres commits convencionales: `996dbdf` docs(openspec), `fc45b28` fix(theme), `34d5cb8` docs(images). CI `lint` en verde. Publicado además como **v1.16.1** (PR #102 → #103 → #104), con `main` y `development` en árboles idénticos.
- [ ] 6.2 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive theme-image-alignment` aplica el delta sobre `openspec/specs/image-handling/spec.md`. **Evidencia:** pendiente.
