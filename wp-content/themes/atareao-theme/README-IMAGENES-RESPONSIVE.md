# 📸 Guía de Imágenes Responsive - Tema Atareao

## ✅ Implementación Completa

El tema Atareao ahora tiene soporte completo para imágenes responsive con las siguientes características:

### 🎯 Características Principales

#### 1. **Imágenes Automáticamente Responsive**

- Todas las imágenes tienen `max-width: 100%` y `height: auto`
- Generación automática de atributos `srcset` y `sizes`
- Lazy loading nativo (`loading="lazy"`)
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

✅ **Lazy Loading**: Las imágenes se cargan cuando entran en el viewport
✅ **Object-fit**: Mantiene proporciones sin distorsionar
✅ **Calidad JPEG**: 85% (balance calidad/tamaño)
✅ **Big Image Threshold**: Hasta 2560px de ancho
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

En `functions.php`:

```php
function mi_custom_srcset_sizes($sizes) {
    return '(max-width: 768px) 100vw, 50vw';
}
add_filter('wp_calculate_image_sizes', 'mi_custom_srcset_sizes');
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

   ```html
   <img
     srcset="img-300.jpg 300w, img-768.jpg 768w, img-1024.jpg 1024w"
     sizes="(max-width: 768px) 100vw, 50vw"
   />
   ```

3. **Object-fit para Proporciones**

   ```css
   img {
     object-fit: cover;
   }
   ```

4. **Compresión JPEG Optimizada**
   - Calidad: 85%
   - Balance entre peso y calidad visual

### Recomendaciones

✅ **Usa imágenes de alta calidad** - El tema las optimizará automáticamente
✅ **Sube imágenes grandes** - WordPress generará todas las versiones necesarias
✅ **Formato recomendado**: JPEG para fotos, PNG para gráficos
✅ **Ancho recomendado**: Mínimo 1200px para imágenes destacadas

---

## 🐛 Solución de Problemas

### Las imágenes no se ven responsive

1. Limpiar caché del navegador
2. Regenerar miniaturas: usar plugin "Regenerate Thumbnails"
3. Verificar que el tema está actualizado

### Las imágenes se ven borrosas en pantallas grandes

1. Subir imágenes más grandes (mínimo 1200px)
2. Aumentar el threshold: `add_filter('big_image_size_threshold', function() { return 3000; });`

### Lazy loading no funciona

- Verificar que el navegador soporte `loading="lazy"` (Chrome 76+, Firefox 75+)
- Para soporte antiguo, considerar usar un polyfill como lazysizes.js

---

## 📚 Recursos Adicionales

- [WordPress Responsive Images](https://make.wordpress.org/core/2015/11/10/responsive-images-in-wordpress-4-4/)
- [MDN - Responsive Images](https://developer.mozilla.org/en-US/docs/Learn/HTML/Multimedia_and_embedding/Responsive_images)
- [Native Lazy Loading](https://web.dev/browser-level-image-lazy-loading/)

---

## ✨ Resumen

El tema Atareao implementa un sistema completo de imágenes responsive que:

✅ Funciona automáticamente sin configuración adicional
✅ Optimiza el rendimiento con lazy loading
✅ Se adapta a todos los tamaños de pantalla
✅ Mantiene la calidad visual
✅ Reduce el tiempo de carga
✅ Mejora el SEO y la experiencia del usuario

**No necesitas hacer nada especial** - simplemente sube tus imágenes y el tema se encarga del resto.
