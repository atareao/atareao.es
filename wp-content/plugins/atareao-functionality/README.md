# Atareao Functionality Plugin

Plugin de funcionalidades personalizadas para WordPress que proporciona Custom Post Types, Taxonomías y Metaboxes para el sitio Atareao.

## Características

### Custom Post Types

1. **Tutoriales**
   - Slug: `tutorial`
   - Icono: Libro
   - Taxonomías: Categorías, Etiquetas, Dificultad
   - Campos personalizados opcionales

2. **Capítulos**
   - Slug: `chapter`
   - Icono: Documento
   - Relacionado con Tutoriales (relación padre-hijo)
   - Taxonomías: Categorías, Etiquetas (compartidas con Tutoriales)
   - Ordenable por orden de menú

3. **Mis Aplicaciones**
   - Slug: `application`
   - Icono: Smartphone
   - Taxonomías: Categorías, Plataformas
   - Campos: URL de descarga, Repositorio, Versión

4. **Podcast**
   - Slug: `podcast`
   - Icono: Micrófono
   - Taxonomías: Categorías
   - Campos: URL de audio, Duración

5. **Software**
   - Slug: `software`
   - Icono: Escritorio
   - Taxonomías: Categorías, Plataformas, Dificultad
   - Campos: URL de descarga, Repositorio, Versión

### Taxonomías

#### Específicas por Post Type

- **Categorías de Tutoriales** (`tutorial_category`)
  - Para Tutoriales y Capítulos
  - Jerárquica

- **Etiquetas de Tutoriales** (`tutorial_tag`)
  - Para Tutoriales y Capítulos
  - No jerárquica

- **Categorías de Aplicaciones** (`application_category`)
  - Para Aplicaciones
  - Jerárquica

- **Categorías de Podcasts** (`podcast_category`)
  - Para Podcasts
  - Jerárquica

- **Categorías de Software** (`software_category`)
  - Para Software
  - Jerárquica

#### Taxonomías Compartidas

- **Dificultad** (`difficulty`)
  - Para Tutoriales y Software
  - No jerárquica
  - Términos predeterminados: Principiante, Intermedio, Avanzado, Experto

- **Plataforma** (`platform`)
  - Para Aplicaciones y Software
  - No jerárquica
  - Términos predeterminados: Linux, Windows, macOS, Android, iOS, Web

### Metaboxes

#### Para Aplicaciones y Software

- URL de Descarga
- URL de Repositorio (GitHub, GitLab, etc.)
- Versión

#### Para Podcasts

- URL del archivo de audio
- Duración (formato HH:MM:SS)
- Reproductor de audio en el editor

#### Para Capítulos

- Selector de Tutorial padre (para vincular capítulos con tutoriales)

### Bloques de Gutenberg

#### Bloque: Reproductor de Podcast

Un bloque personalizado para el editor de Gutenberg que permite insertar un reproductor de audio para podcasts.

**Características:**

- Selector de podcasts existentes
- Campo de URL personalizada para archivos externos
- Título y descripción editables
- Reproductor HTML5 nativo
- Responsive (móvil, tablet, desktop)
- Enlace automático a la página del podcast
- Soporte para alineaciones (normal, wide, full)
- Personalización de colores y espaciado

**Uso:**

1. En el editor de Gutenberg, haz clic en [+]
2. Busca "Reproductor de Podcast"
3. Selecciona un podcast de la lista o introduce una URL manual
4. Personaliza título y descripción si lo deseas
5. El reproductor se mostrará automáticamente en el frontend

**Documentación completa:** Ver [BLOQUE-PODCAST.md](BLOQUE-PODCAST.md)

## Requisitos

- WordPress 6.0 o superior
- PHP 7.4 o superior

## Instalación

1. Descarga el plugin
2. Sube la carpeta `atareao-functionality` a `/wp-content/plugins/`
3. Activa el plugin desde el panel de WordPress
4. Los Custom Post Types aparecerán automáticamente en el menú del admin

O bien:

1. Ve a WordPress Admin > Plugins > Añadir nuevo
2. Haz clic en "Subir plugin"
3. Selecciona el archivo ZIP del plugin
4. Haz clic en "Instalar ahora"
5. Activa el plugin

## Uso

### Crear un Tutorial con Capítulos

1. Ve a **Tutoriales > Añadir nuevo**
2. Crea tu tutorial con título, contenido e imagen destacada
3. Asigna categorías, etiquetas y nivel de dificultad
4. Publica el tutorial
5. Ve a **Capítulos > Añadir nuevo**
6. Crea cada capítulo del tutorial
7. En el metabox "Tutorial" de la derecha, selecciona el tutorial padre
8. Utiliza el campo "Orden" en "Atributos de página" para ordenar los capítulos

### Añadir una Aplicación

1. Ve a **Mis Aplicaciones > Añadir nueva**
2. Añade título, descripción e imagen destacada
3. Completa los campos:
   - URL de Descarga
   - URL de Repositorio
   - Versión
4. Selecciona las plataformas compatibles
5. Publica

### Publicar un Podcast

1. Ve a **Podcasts > Añadir nuevo**
2. Añade título, descripción e imagen destacada
3. En el metabox "Audio del Podcast":
   - Introduce la URL del archivo MP3
   - Verifica con el reproductor que funciona
4. Añade la duración en formato HH:MM:SS
5. Asigna categorías
6. Publica

### Añadir Software

1. Ve a **Software > Añadir nuevo**
2. Similar a Aplicaciones, pero incluye:
   - Nivel de dificultad
   - Plataformas compatibles
   - URLs de descarga y repositorio
   - Versión

## Estructura de Archivos

```
atareao-functionality/
├── atareao-functionality.php  # Archivo principal del plugin
├── includes/
│   ├── class-post-types.php   # Registro de CPTs
│   ├── class-taxonomies.php   # Registro de taxonomías
│   ├── class-metaboxes.php    # Metaboxes personalizados
│   └── class-podcast-block.php # Bloque de reproductor de podcast
├── assets/
│   └── blocks/
│       └── podcast-player/    # Bloque de Gutenberg
│           ├── block.json     # Configuración del bloque
│           ├── index.js       # JavaScript del editor
│           ├── style.css      # Estilos del frontend
│           ├── editor.css     # Estilos del editor
│           └── README.md      # Documentación del bloque
├── languages/                  # Archivos de traducción
├── README.md                   # Este archivo
└── BLOQUE-PODCAST.md          # Guía rápida del bloque
```

## Desarrollo

### Hooks Disponibles

El plugin ejecuta las siguientes acciones que puedes utilizar:

- `init` - Registro de post types y taxonomías
- `add_meta_boxes` - Añadir metaboxes personalizados
- `save_post` - Guardar datos de metaboxes

### Funciones de Utilidad

Puedes obtener capítulos de un tutorial con:

```php
$chapters = new WP_Query(array(
    'post_type' => 'chapter',
    'meta_key' => '_tutorial_id',
    'meta_value' => $tutorial_id,
    'orderby' => 'menu_order',
    'order' => 'ASC'
));
```

## Changelog

### Versión 1.0.0

- Lanzamiento inicial
- 5 Custom Post Types
- 7 Taxonomías personalizadas
- Metaboxes para campos adicionales
- Relación Tutorial-Capítulo
- **NUEVO**: Bloque de Gutenberg "Reproductor de Podcast"
  - Selector de podcasts existentes
  - URL personalizada para archivos externos
  - Reproductor HTML5 responsive
  - Integración con REST API de WordPress

## Créditos

- Desarrollado por: Atareao
- URL: https://atareao.es
- Licencia: GPL v2 o posterior

## Soporte

Para soporte y dudas, visita [atareao.es](https://atareao.es)

## Licencia

Este plugin es software libre; puedes redistribuirlo y/o modificarlo bajo los términos de la Licencia Pública General de GNU según lo publicado por la Free Software Foundation; ya sea la versión 2 de la Licencia, o (a tu elección) cualquier versión posterior.
