# 📸 Guía de Imágenes Responsive - Tema Atareao

## ✅ Implementación Completa

El sitio Atareao tiene soporte completo para imágenes responsive. Este documento cubre lo que aporta **el tema**; la política de imágenes (lazy loading, `sizes`, calidad y umbral) la aplica **el plugin** `atareao-functionality`, y aquí solo se referencia.

> **Dependencia del plugin.** El tema `atareao-theme` **no registra** los filtros de imágenes: los aplica el plugin `atareao-functionality`. Si el plugin se desactiva, el sitio pierde el lazy loading, la política de `sizes`, la calidad 85 y el umbral de 2560 px. Ver «Política de imágenes» en el README del plugin.

### 🎯 Características Principales

#### 1. **Imágenes Automáticamente Responsive**

- Todas las imágenes tienen `max-width: 100%` y `height: auto` (CSS del tema)
- WordPress genera los atributos `srcset`; la política de `sizes` la aplica el plugin `atareao-functionality`
- Lazy loading nativo (`loading="lazy"`), aplicado por el plugin
- Soporte para diferentes tamaños de pantalla

#### 2. **Tamaños de Imagen Personalizados**

```php
// Tamaños disponibles:
- 'thumbnail'       → 150x150px (recortado)
- 'medium'          → 300x300px
- 'medium_large'    → 768px de ancho
- 'large'           → 1024x1024px
- 'atareao-thumbnail' → 400x300px (recortado)
- 'atareao-medium'    → 800x600px (recortado)
- 'atareao-large'     → 1200x800px (recortado)
```

#### 3. **Aspect Ratios por Tipo de Contenido**

**En listados (archive/blog):**

- Tutoriales y Capítulos: `16:9`
- Aplicaciones y Software: `1:1` (cuadrado)
- Podcasts: `1:1` (cuadrado)

**En posts individuales:**

- Todas las imágenes destacadas: aspecto automático, máx 600px de alto
- En móvil: máximo 300px de alto

#### 4. **Responsive Breakpoints**

```css
/* Móvil (< 768px) */
- Imágenes al 100% del ancho
- Aspect ratios más cuadrados (4:3)
- Gallery en 1 columna
- Imágenes alineadas (left/right) ocupan ancho completo

/* Tablet (768px - 1023px) */
- Grid de posts: 2 columnas
- Imágenes alineadas: máx 40%
- Gallery: columnas automáticas (min 200px)

/* Desktop (≥ 1024px) */
- Grid de posts: 3 columnas
- Imágenes alineadas: máx 35%
- Gallery: columnas automáticas (min 300px)
```

#### 5. **Alineaciones de WordPress**

Soporte completo para:

- `.alignleft` - Imagen a la izquierda con texto envolvente
- `.alignright` - Imagen a la derecha con texto envolvente
- `.aligncenter` - Imagen centrada
- `.alignfull` - Ancho completo
- `.alignwide` - Ancho extendido

#### 6. **Optimizaciones Aplicadas**

✅ **Lazy Loading**: Las imágenes se cargan cuando entran en el viewport (lo aplica el plugin)
✅ **Object-fit**: Mantiene proporciones sin distorsionar
✅ **Calidad JPEG**: 85% (la fija el plugin `atareao-functionality`)
✅ **Big Image Threshold**: 2560px (lo fija el plugin `atareao-functionality`)
✅ **Hover Effects**: Zoom suave en miniaturas
✅ **Srcset Automático**: WordPress genera múltiples tamaños

---

## 📱 Uso en Templates

### Imagen Destacada Básica

```php
<?php if (has_post_thumbnail()) : ?>
    <div class="post-thumbnail">
        <?php the_post_thumbnail('large'); ?>
    </div>
<?php endif; ?>
```

### Imagen con Enlace

```php
<?php if (has_post_thumbnail()) : ?>
    <div class="post-thumbnail">
        <a href="<?php the_permalink(); ?>">
            <?php the_post_thumbnail('atareao-medium'); ?>
        </a>
    </div>
<?php endif; ?>
```

### Imagen con Tamaño Específico

```php
// Para listados
the_post_thumbnail('atareao-medium');

// Para posts individuales
the_post_thumbnail('large');

// Para thumbnails pequeños
the_post_thumbnail('thumbnail');
```

---

## 🎨 Clases CSS Disponibles

### Contenedores de Imagen

```css
.post-thumbnail          /* Contenedor de miniaturas */
.single .post-thumbnail  /* Miniaturas en posts individuales */
```

### Alineaciones

```css
.alignleft      /* Flotar a la izquierda */
.alignright     /* Flotar a la derecha */
.aligncenter    /* Centrar */
.alignfull      /* Ancho completo */
.alignwide      /* Ancho extendido */
```

### Galerías

```css
.wp-block-gallery   /* Galería de Gutenberg */
.gallery            /* Galería clásica */
```

---

## 🔧 Configuración Avanzada

### Cambiar Aspect Ratios

En `style.css` o `custom-post-types.css`:

```css
.type-tutorial .post-thumbnail img {
  aspect-ratio: 16 / 9; /* Cambiar a 4/3, 1/1, etc. */
}
```

### Personalizar Sizes para Srcset

La política de `sizes` del sitio **no vive en el tema**: la aplica el plugin `atareao-functionality` en `SEO::imageSizes` (`includes/class-seo.php`). Para cambiarla, edita ese callback.

El fragmento siguiente es solo la **técnica de personalización** genérica (el valor mostrado es un ejemplo, no el del sitio) y va en un plugin o mu-plugin, **nunca** en el tema:

```php
// En un plugin o mu-plugin — NO en el tema.
add_filter('wp_calculate_image_sizes', function ($sizes) {
    return '(max-width: 768px) 100vw, 800px'; // valor de ejemplo
});
```

### Añadir Nuevo Tamaño de Imagen

En `functions.php`:

```php
add_image_size('mi-tamaño', 600, 400, true);
```

Luego usar:

```php
the_post_thumbnail('mi-tamaño');
```

---

## 📊 Rendimiento

### Optimizaciones Aplicadas

1. **Lazy Loading Nativo**

   ```html
   <img src="..." loading="lazy" />
   ```

2. **Srcset Automático**

   WordPress genera el `srcset`; el `sizes` lo añade el plugin `atareao-functionality`, con un valor distinto según el contexto (página singular o listado/archivo). El valor exacto está en la sección «Política de imágenes» del README del plugin.

   ```html
   <img
     srcset="img-300.jpg 300w, img-768.jpg 768w, img-1024.jpg 1024w"
     sizes="(max-width: 768px) 100vw, …"
   />
   ```

3. **Object-fit para Proporciones**

   ```css
   img {
     object-fit: cover;
   }
   ```

4. **Compresión JPEG Optimizada** (la fija el plugin `atareao-functionality`)
   - Calidad: 85%
   - Balance entre peso y calidad visual

### Recomendaciones

✅ **Usa imágenes de alta calidad** - El plugin `atareao-functionality` las optimizará automáticamente
✅ **Sube imágenes grandes** - WordPress generará todas las versiones necesarias
✅ **Formato recomendado**: JPEG para fotos, PNG para gráficos
✅ **Ancho recomendado**: Mínimo 1200px para imágenes destacadas

---

## 🐛 Solución de Problemas

### Las imágenes no se ven responsive

1. Limpiar caché del navegador
2. Regenerar miniaturas: usar plugin "Regenerate Thumbnails"
3. Verificar que el plugin `atareao-functionality` está activo

### Las imágenes se ven borrosas en pantallas grandes

1. Subir imágenes más grandes (mínimo 1200px)
2. Aumentar el umbral de imagen grande en el plugin `atareao-functionality` (`SEO::bigImageThreshold`, `includes/class-seo.php`)

### Lazy loading no funciona

- Comprobar que el plugin `atareao-functionality` está **activo**: el tema no registra el filtro de lazy loading, lo aplica el plugin.
- Descartado lo anterior, verificar que el navegador soporte `loading="lazy"` (Chrome 76+, Firefox 75+); para soporte antiguo, considerar un polyfill como lazysizes.js.

---

## 📚 Recursos Adicionales

- [WordPress Responsive Images](https://make.wordpress.org/core/2015/11/10/responsive-images-in-wordpress-4-4/)
- [MDN - Responsive Images](https://developer.mozilla.org/en-US/docs/Learn/HTML/Multimedia_and_embedding/Responsive_images)
- [Native Lazy Loading](https://web.dev/browser-level-image-lazy-loading/)

---

## ✨ Resumen

El tema Atareao y el plugin `atareao-functionality` implementan juntos un sistema completo de imágenes responsive que:

✅ Funciona automáticamente sin configuración adicional
✅ Optimiza el rendimiento con lazy loading
✅ Se adapta a todos los tamaños de pantalla
✅ Mantiene la calidad visual
✅ Reduce el tiempo de carga
✅ Mejora el SEO y la experiencia del usuario

**No necesitas hacer nada especial** - simplemente sube tus imágenes y el tema y el plugin se encargan del resto.
