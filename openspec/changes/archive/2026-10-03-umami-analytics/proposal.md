# Proposal: Integración propia de la analítica Umami

## Why

El sitio usa el plugin de terceros **Integrate Umami** (v0.8.3, autor `Ancocodet`) para emitir el script de analítica. Ese plugin existe únicamente para imprimir una etiqueta `<script>`: arrastra `vendor/` y un autoload propio para una función que pertenece al plugin del sitio. Además presenta defectos concretos:

1. **El tracking de comentarios rompe el botón.** La opción `track_comments` hace `str_replace('<button', '<input ' . $attrs, …)` sobre el formulario. El tema declara `<button type="submit" name="%1$s" id="%2$s" class="%3$s" tabindex="4">%4$s</button>` (`wp-content/themes/atareao-theme/comments.php:114`), de modo que la opción produce HTML inválido (un `<input>` con un `</button>` huérfano) y se pierde el texto «Publicar comentario».
2. **Desactivar el plugin borra su configuración.** Su `register_deactivation_hook` ejecuta `Options::delete_options()`. Cualquier desactivación accidental elimina los ajustes y, en una migración, supone perder el `website_id` de producción.
3. **No expone opciones que el tracker sí soporta** (`data-exclude-search`, `data-exclude-hash`) ni permite Subresource Integrity (SRI).
4. **Viola la regla del repositorio**: toda la funcionalidad y la lógica de negocio van en `atareao-functionality`; el tema es solo presentación. Un plan antiguo (`docs/plans/2026-07-31-plan-seo-seguridad-atareao.md`) proponía hacerlo desde el `functions.php` del tema; se descarta.

En producción, atareao.es emite HOY exactamente esto en `wp_footer` (con `enabled=1`, `script_url=https://umami.atareao.es/script.js`, `do_not_track=1` y el resto en sus valores por defecto; `track_comments` está desactivado — verificado: `data-umami-event` aparece 0 veces en un artículo real):

```html
<!-- Integrate Umami -->
<script async defer
        src="https://umami.atareao.es/script.js"
        data-website-id="8e108fb4-…-ec956cac22b0"
        data-do-not-track=true >
</script>
<!-- /Integrate Umami -->
```

El objetivo es retirar el plugin de terceros sin alterar el dato que recibe Umami (equivalencia funcional) y sin downtime de analítica.

## What Changes

- Nueva capability **`analytics`**: la analítica del sitio pasa a ser responsabilidad de `atareao-functionality`.
- Nueva clase `\Atareao\Analytics` en `wp-content/plugins/atareao-functionality/includes/class-analytics.php`, registrada con `require_once` + `Analytics::init()` como el resto de módulos.
- **Una clave de opción por ajuste**, con prefijo `atareao_umami_` (`atareao_umami_enabled`, `_script_url`, `_website_id`, `_host_url`, `_use_host_url`, `_integrity`, `_ignore_admins`, `_auto_track`, `_do_not_track`, `_cache`, `_track_comments`, `_exclude_search`, `_exclude_hash`, `_skip_404`, `_skip_search`), todas con default `0`/`''`, legibles y escribibles por WP-CLI.
- **Página propia bajo Ajustes** (Ajustes → Analítica, slug `atareao-analytics`, `manage_options`) con guardado por POST + nonce, siguiendo el patrón de `MatrixConfig`.
- **Inyección en `wp_footer`** con equivalencia funcional al plugin actual (mismos atributos y valores ⇒ mismo dato en Umami) pero con HTML válido: valores entre comillas y escapado con `esc_url`/`esc_attr`. No se emite nada si `enabled=0`, `script_url` vacío o `website_id` vacío.
- **Exclusión dura** (nunca se inyecta): `is_admin()`, `is_feed()`, `is_preview()`, `is_customize_preview()`, peticiones REST e `is_robots()`.
- **Exclusiones opcionales** con default `0`: `skip_404` y `skip_search`.
- **Guarda anti-doble-inyección**: si el plugin legado está activo (`class_exists('\Ancozockt\Umami\Manager')`) no se emite el script, y el panel muestra un aviso para desactivar «Integrate Umami» al terminar.
- **Migración desde el plugin legado**: botón «Importar ajustes de Integrate Umami» que lee `integrate_umami_options` y vuelca los valores en las claves nuevas **sin borrar** el ajuste legado, informando de cuántos ajustes importó (o de que no encontró nada).
- **SRI opcional**: campo `integrity` vacío por defecto; si tiene valor se emite `integrity="…"` junto a `crossorigin="anonymous"`.
- **Tracking de comentarios opt-in** (`_track_comments`, default `0`) corregido: añade `data-umami-event`, `data-umami-event-post-id` y `data-umami-event-post-title` **sobre el elemento existente, sin cambiar su tipo**.
- **Desactivar el plugin no borra la configuración** (no se registra un `register_deactivation_hook` destructivo).
- **No se toca** el tema, la CSP ni los templates del microsite. El comportamiento del microsite `/tools/` se conserva (sus 11 templates llaman a `get_footer()`).
- Documentación: sección «Analítica (Umami)» en el `README.md` del plugin y una nota en `docs/produccion/cabeceras-seguridad-traefik.md`.

## Capabilities

### New Capabilities

- `analytics`: emisión del script de analítica Umami y su configuración desde wp-admin, incluyendo exclusiones, SRI opcional, tracking de comentarios y migración desde el plugin de terceros.

### Modified Capabilities

Ninguna.

## Impact

- **Archivos**:
  - Nuevo: `wp-content/plugins/atareao-functionality/includes/class-analytics.php`.
  - Modificado: `wp-content/plugins/atareao-functionality/atareao-functionality.php` (`require_once` + `Analytics::init()`).
  - Modificado: `wp-content/plugins/atareao-functionality/README.md` (sección «Analítica (Umami)»).
  - Modificado (solo nota): `docs/produccion/cabeceras-seguridad-traefik.md`.
- **Sin cambios** en el tema, la CSP ni los templates del microsite (`/tools/`).
- **Dependencias**: ninguna nueva.
- **Datos/estado**: se leen 15 claves `atareao_umami_*`; la lectura de `integrate_umami_options` es de solo lectura (no se borra).
- **Migración**: instalar/actualizar → importar ajustes → verificar el HTML emitido → desactivar y borrar «Integrate Umami». Si se sigue ese orden no hay downtime de analítica ni doble conteo.
- **Compatibilidad**: PHP 8.3, PSR12, WordPress 6.0+.
