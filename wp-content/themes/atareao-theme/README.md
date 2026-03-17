# Atareao Theme

Tema minimalista y responsive para WordPress, diseñado con enfoque mobile-first, optimizado para rapidez y simplicidad.

## Características

- **Mobile-First**: Diseño optimizado para dispositivos móviles desde el principio
- **Responsive**: Se adapta perfectamente a todos los tamaños de pantalla
- **Minimalista**: Diseño limpio y sencillo enfocado en el contenido
- **Rápido**: Código optimizado para carga rápida
- **Accesible**: Cumple con estándares de accesibilidad web
- **Modo Oscuro**: Soporte automático para modo oscuro del sistema
- **Gutenberg Ready**: Compatible con el editor de bloques

## Requisitos

- WordPress 6.0 o superior
- PHP 7.4 o superior

## Instalación

1. Descarga el tema
2. Ve a WordPress Admin > Apariencia > Temas
3. Haz clic en "Añadir nuevo" y luego en "Subir tema"
4. Selecciona el archivo ZIP del tema
5. Haz clic en "Instalar ahora"
6. Activa el tema

## Soporte para Custom Post Types

Este tema está diseñado para trabajar con el plugin **Atareao Functionality** que proporciona los siguientes custom post types:

- **Tutoriales**: Para publicar tutoriales completos
- **Capítulos**: Capítulos individuales relacionados con tutoriales
- **Mis Aplicaciones**: Para mostrar aplicaciones desarrolladas
- **Podcast**: Para publicar episodios de podcast
- **Software**: Para mostrar software y herramientas

## Personalización

### Menús

El tema soporta dos ubicaciones de menú:

- **Menú Principal**: Menú de navegación principal en el header
- **Menú Footer**: Menú en el pie de página

### Widgets

Áreas de widgets disponibles:

- **Sidebar Principal**: Barra lateral para contenido adicional
- **Footer 1**: Primera columna del footer
- **Footer 2**: Segunda columna del footer

### Logo Personalizado

Puedes añadir tu logo desde:
Apariencia > Personalizar > Identidad del sitio > Logo

## Estructura de Archivos

```
atareao-theme/
├── style.css           # Estilos principales
├── functions.php       # Funciones del tema
├── header.php          # Cabecera
├── footer.php          # Pie de página
├── index.php           # Template principal
├── single.php          # Entradas individuales
├── page.php            # Páginas
├── archive.php         # Archivos
├── search.php          # Resultados de búsqueda
├── single-tutorial.php # Template para tutoriales
├── single-chapter.php  # Template para capítulos
├── single-application.php # Template para aplicaciones
├── single-podcast.php  # Template para podcasts
├── single-software.php # Template para software
├── archive-tutorial.php # Archivo de tutoriales
├── js/
│   ├── main.js         # JavaScript principal
│   └── navigation.js   # JavaScript de navegación
└── template-parts/
    ├── content.php     # Template de contenido
    ├── content-none.php # Sin resultados
    ├── content-tutorial.php # Contenido de tutoriales
    └── content-podcast.php # Contenido de podcasts
```

## Créditos

- Desarrollado por: Atareao
- URL: https://atareao.es
- Licencia: GPL v2 o posterior

## Soporte

Para soporte y dudas, visita [atareao.es](https://atareao.es)
