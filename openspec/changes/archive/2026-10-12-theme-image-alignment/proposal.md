# Proposal: Alinear la documentación de imágenes y dejar de forzar los tamaños

## Why

Dos cabos sueltos que dejó `theme-plugin-boundary`, más una observación suya que resultó ser falsa.

**1. La guía de imágenes del tema documenta lo que ya no hace el tema.** `README-IMAGENES-RESPONSIVE.md` (268 líneas) atribuye al tema el lazy loading, la política de `sizes`, la calidad JPEG 85 y el umbral de 2560 px, que ahora aplica `\Atareao\SEO`. Muestra fragmentos de `add_filter` que ya no existen en `functions.php` y **nunca ha documentado la política real de `sizes`**: enseña `sizes="(max-width: 768px) 100vw, 50vw"`, que no es lo que el sitio emite. Quien siga la guía para cambiar el `sizes` editará el fichero equivocado y no conseguirá nada.

**2. El tema fuerza los tamaños de imagen en cada petición.** `atareao_theme_image_sizes()` corre en `after_setup_theme` y llama a `update_option()` once veces. Verificado contra el core: `update_option` **no escribe** si el valor no cambia («If the new and old values are the same, no need to update»), así que el coste en rendimiento es despreciable. El problema real es otro: **cualquier cambio en Ajustes → Medios se revierte silenciosamente** en la petición siguiente. Es un footgun, no un problema de rendimiento, y así hay que contarlo.

**3. Una observación archivada que era falsa.** El `design.md` de `theme-plugin-boundary` afirma que `atareao_theme_blog_all_post_types()` y el filtro del feed se pisan en `/feed/`, y que el resultado depende del orden de registro. El core de WP 7.1.3 lo desmiente: `is_feed` está dentro de la negación que activa `is_home`, así que en un feed `is_home()` es `false` y los dominios de los dos callbacks son disjuntos. Dejarlo ahí hace que el próximo lector crea que hay un bug latente.

## What Changes

- **La guía se parte por la frontera.** Lo que documenta la política del plugin —lazy loading, `sizes`, calidad, umbral— pasa al README de `atareao-functionality`. La guía del tema conserva lo que sí es suyo (tamaños, clases CSS, aspect ratios, uso en plantillas, diagnóstico) con referencias cruzadas.
- **Se documenta por fin la política real de `sizes`**: `(max-width: 768px) 100vw, 1200px` en páginas singulares y `(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw` en listados y archivos.
- **`atareao_theme_image_sizes()` pasa a `after_switch_theme`**: los tamaños se fijan al activar el tema, no en cada petición.
- **Se corrige la observación falsa** en el `design.md` archivado, marcada como retractada y citando el core.

## Capabilities

### Modified Capabilities

- `image-handling`: nuevo requirement sobre la escritura de los tamaños de imagen — se fijan una sola vez, al activar el tema, y los cambios de Ajustes → Medios dejan de revertirse.

## Impact

- **Modificado**: `wp-content/themes/atareao-theme/README-IMAGENES-RESPONSIVE.md` (recortado a lo suyo, con referencias al plugin).
- **Modificado**: `wp-content/plugins/atareao-functionality/README.md` (nueva sección de política de imágenes).
- **Modificado**: `wp-content/themes/atareao-theme/functions.php` (un `add_action`: `after_setup_theme` → `after_switch_theme`).
- **Modificado**: `openspec/changes/archive/2026-10-12-theme-plugin-boundary/design.md` (retractación).
- **Sin cambios de versión.** Sin migración de datos: los valores ya están en la base de datos, así que el estado efectivo no cambia.

## Aviso: esto SÍ cambia un comportamiento

El punto 2 no es cosmético. Hoy el tema **impone** sus tamaños: si alguien cambia Ajustes → Medios, la siguiente petición lo revierte. Tras el change, ese cambio **persistirá**. Es la conducta sensata y por eso lo propongo, pero es un cambio observable y va con su escenario en la spec.
