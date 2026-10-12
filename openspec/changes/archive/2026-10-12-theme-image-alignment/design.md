# Design: Alinear la documentación de imágenes y dejar de forzar los tamaños

## Context

Tres cabos sueltos de `theme-plugin-boundary`: dos observaciones que dejó como «fuera de alcance» y una que él mismo archivó y que resultó ser falsa.

## Retractación: el conflicto de query vars no existe

`theme-plugin-boundary` archivó esta observación en su `design.md`:

> `atareao_theme_blog_all_post_types()` (que deja `page` dentro de `post_type`) y `mainFeedAllPostTypes()` (que lo saca) **escriben el mismo query var**. Gana el que se registra después […] La fragilidad es preexistente.

**Era falsa.** WP 7.1.3, `wp-includes/class-wp-query.php` (leído del contenedor de producción):

```php
if ( ! ( $this->is_singular || $this->is_archive || $this->is_search || $this->is_feed
        || ( wp_is_serving_rest_request() && $this->is_main_query() )
        || $this->is_trackback || $this->is_404 || $this->is_admin || $this->is_robots
        || $this->is_favicon || $this->is_sitemap ) ) {
    $this->is_home = true;
}
```

`is_feed` está **dentro** de la negación: cuando `is_feed()` es cierto, `is_home` no se activa (y viene reseteado a `false` en la línea 520). En `/blog/` ocurre lo contrario. **Los dominios son disjuntos y nunca coinciden**: no hay dependencia de orden ni bug latente, y añadir `! $query->is_feed()` a la guarda del tema sería una condición muerta.

Se razonó desde el folclore —«el feed principal es el home feed»— sin abrir el core, y el revisor independiente lo confirmó sin verificarlo tampoco. Por eso este change **no toca ese código**: solo corrige el registro.

## Decisions

### D1 — La guía se parte por la frontera, no se mueve entera

Se valoró mover `README-IMAGENES-RESPONSIVE.md` al plugin, pero ~70% de su contenido sí es del tema: tamaños registrados con `add_image_size()`, aspect ratios, clases CSS, uso en plantillas. Moverla entera dejaría al tema sin documentación de lo suyo.

En su lugar se parte por donde pasa la frontera: la política de filtros (lazy loading, `sizes`, calidad, umbral) se documenta en el README del plugin, que es quien la aplica; la guía del tema se queda con lo suyo y **referencia** al plugin. Es exactamente la misma operación que hizo `theme-plugin-boundary` con el código.

### D2 — El cambio de hook se cuenta como cambio de comportamiento

`update_option()` no escribe si el valor no cambia, así que el cambio de `after_setup_theme` a `after_switch_theme` **no aporta rendimiento**. Se hace por corrección: hoy el tema revierte en silencio cualquier ajuste de Ajustes → Medios, y eso sorprende a quien lo toque. Tras el change, los valores se fijan una vez al activar el tema y los cambios del administrador persisten.

Como es observable, va con su requirement y sus escenarios en la spec, no escondido en un change de documentación.

### D3 — No hay migración de datos

Los once valores ya están en la base de datos de producción con exactamente los valores que el tema impone (verificado). El estado efectivo no cambia: lo único que cambia es que el tema deja de reimponerlos. No hace falta migración ni tarea de despliegue.

### D4 — Se corrige el registro archivado

La observación falsa vive en el `design.md` de un change archivado. Dejarla significa que el próximo lector creerá que hay un bug latente. Se corrige en el sitio, marcada como **retractada** y citando el core, en vez de confiar en que alguien lea este change. Es tocar un registro histórico, y se hace de forma explícita y visible.

### D5 — Fuera de alcance

- **El "olor" de rendimiento**, porque no existe: `update_option` ya cortocircuita. Si algún día se quiere, la vía sería `add_image_size()` en vez de las opciones estándar, pero eso **sí** cambiaría los nombres de tamaño que usan las plantillas (`'large'`, `'medium_large'`) y sería otro change.
- **El resto de `class-seo.php`** (rel prev/next, hreflang, filtros de título de TSF, exclusión del sitemap) sigue sin spec.

## Risks / Trade-offs

- **El único riesgo real es el cambio de comportamiento de D2.** Mitigación: el requirement va con tres escenarios (activación, petición normal, cambio desde Ajustes → Medios) y la verificación comprueba que los valores en base de datos no cambian con el change.
- **La documentación partida en dos sitios** puede desincronizarse otra vez. Mitigación: la guía del tema deja de enunciar la política (no la duplica) y solo la referencia; así no hay dos fuentes de verdad.
- **Tocar un change archivado** es inusual. Se hace porque la alternativa es dejar una afirmación falsa con apariencia de hallazgo técnico.
