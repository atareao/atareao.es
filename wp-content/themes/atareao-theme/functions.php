<?php
/**
 * Atareao Theme Functions
 *
 * @package Atareao_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuración del tema
 */
function atareao_theme_setup() {
    // Soporte para título dinámico
    add_theme_support('title-tag');
    
    // Soporte para imágenes destacadas
    add_theme_support('post-thumbnails');
    
    // Tamaños de imagen personalizados
    add_image_size('atareao-thumbnail', 400, 300, true);
    add_image_size('atareao-medium', 800, 600, true);
    add_image_size('atareao-large', 1200, 800, true);
    
    // Soporte para HTML5
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script'
    ));
    
    // Soporte para feed automático
    add_theme_support('automatic-feed-links');
    
    // Soporte para logo personalizado
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ));
    
    // Soporte para menús
    register_nav_menus(array(
        'primary' => __('Menú Principal', 'atareao-theme'),
        'footer'  => __('Menú Footer', 'atareao-theme'),
    ));
    
    // Soporte para editor de bloques
    add_theme_support('align-wide');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    add_editor_style('css/editor-style.css');
    
    // Habilitar responsive images
    add_theme_support('post-thumbnails');
    set_post_thumbnail_size(1200, 9999);
}
add_action('after_setup_theme', 'atareao_theme_setup');

/**
 * Configurar tamaños de imágenes responsive
 */
function atareao_theme_image_sizes() {
    // WordPress generará estas versiones automáticamente
    update_option('thumbnail_size_w', 150);
    update_option('thumbnail_size_h', 150);
    update_option('thumbnail_crop', 1);
    
    update_option('medium_size_w', 300);
    update_option('medium_size_h', 300);
    update_option('medium_crop', 0);
    
    update_option('medium_large_size_w', 768);
    update_option('medium_large_size_h', 0);
    
    update_option('large_size_w', 1024);
    update_option('large_size_h', 1024);
    update_option('large_crop', 0);
}
add_action('after_setup_theme', 'atareao_theme_image_sizes');

/**
 * Añadir atributo loading="lazy" a las imágenes
 */
function atareao_theme_add_lazy_loading($attr, $attachment, $size) {
    $attr['loading'] = 'lazy';
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'atareao_theme_add_lazy_loading', 10, 3);

/**
 * Customizar los breakpoints de srcset
 */
function atareao_theme_custom_srcset_sizes($sizes, $size, $image_src, $image_meta, $attachment_id) {
    if (is_singular()) {
        // En páginas individuales
        $sizes = '(max-width: 768px) 100vw, (max-width: 1024px) 80vw, 1200px';
    } else {
        // En listados
        $sizes = '(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw';
    }
    return $sizes;
}
add_filter('wp_calculate_image_sizes', 'atareao_theme_custom_srcset_sizes', 10, 5);

/**
 * Mejorar la calidad de las imágenes JPEG
 */
function atareao_theme_jpeg_quality($quality, $context) {
    return 85; // Balance entre calidad y tamaño de archivo
}
add_filter('jpeg_quality', 'atareao_theme_jpeg_quality', 10, 2);
add_filter('wp_editor_set_quality', 'atareao_theme_jpeg_quality', 10, 2);

/**
 * Deshabilitar el threshold de imágenes grandes para preservar calidad
 */
function atareao_theme_big_image_threshold($threshold, $imagesize, $file, $attachment_id) {
    // Permitir imágenes hasta 2560px
    return 2560;
}
add_filter('big_image_size_threshold', 'atareao_theme_big_image_threshold', 10, 4);

/**
 * Registrar y cargar scripts y estilos
 */
function atareao_theme_scripts() {
    // Estilo principal
    wp_enqueue_style('atareao-style', get_stylesheet_uri(), array(), '1.0.0');
    
    // Estilos para custom post types
    wp_enqueue_style('atareao-cpt-style', get_template_directory_uri() . '/css/custom-post-types.css', array('atareao-style'), '1.0.0');
    
    // Dashicons para el reproductor de podcast
    wp_enqueue_style('dashicons');
    
    // Script principal
    wp_enqueue_script('atareao-script', get_template_directory_uri() . '/js/main.js', array(), '1.0.0', true);
    
    // Script de navegación
    wp_enqueue_script('atareao-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '1.0.0', true);
    
    // Script para comentarios si es necesario
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'atareao_theme_scripts');

/**
 * Registrar áreas de widgets
 */
function atareao_theme_widgets_init() {
    register_sidebar(array(
        'name'          => __('Sidebar Principal', 'atareao-theme'),
        'id'            => 'sidebar-1',
        'description'   => __('Widgets para el sidebar principal', 'atareao-theme'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
    
    register_sidebar(array(
        'name'          => __('Footer 1', 'atareao-theme'),
        'id'            => 'footer-1',
        'description'   => __('Widgets para el footer área 1', 'atareao-theme'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
    
    register_sidebar(array(
        'name'          => __('Footer 2', 'atareao-theme'),
        'id'            => 'footer-2',
        'description'   => __('Widgets para el footer área 2', 'atareao-theme'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
}
add_action('widgets_init', 'atareao_theme_widgets_init');

/**
 * Personalizar el excerpt
 */
function atareao_theme_excerpt_length($length) {
    return 25;
}
add_filter('excerpt_length', 'atareao_theme_excerpt_length');

function atareao_theme_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'atareao_theme_excerpt_more');

/**
 * Añadir clases al body
 */
function atareao_theme_body_classes($classes) {
    if (!is_singular()) {
        $classes[] = 'hfeed';
    }
    
    if (is_active_sidebar('sidebar-1')) {
        $classes[] = 'has-sidebar';
    }
    
    return $classes;
}
add_filter('body_class', 'atareao_theme_body_classes');

/**
 * Función para mostrar la paginación
 */
function atareao_theme_pagination() {
    if (is_singular()) {
        return;
    }
    
    global $wp_query;
    
    if ($wp_query->max_num_pages <= 1) {
        return;
    }
    
    $paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
    $max   = intval($wp_query->max_num_pages);
    
    if ($paged >= 1) {
        $links[] = $paged;
    }
    
    if ($paged >= 3) {
        $links[] = $paged - 1;
        $links[] = $paged - 2;
    }
    
    if (($paged + 2) <= $max) {
        $links[] = $paged + 2;
        $links[] = $paged + 1;
    }
    
    echo '<div class="pagination"><span class="pages">Página ' . $paged . ' de ' . $max . '</span>';
    
    if (get_previous_posts_link()) {
        printf('<a href="%s">%s</a>', get_previous_posts_page_link(), '&laquo; Anterior');
    }
    
    if (!in_array(1, $links)) {
        $class = 1 == $paged ? ' current' : '';
        printf('<a href="%s" class="%s">%s</a>', esc_url(get_pagenum_link(1)), $class, '1');
        
        if (!in_array(2, $links)) {
            echo '<span>...</span>';
        }
    }
    
    sort($links);
    foreach ((array) $links as $link) {
        $class = $paged == $link ? ' current' : '';
        printf('<a href="%s" class="%s">%s</a>', esc_url(get_pagenum_link($link)), $class, $link);
    }
    
    if (!in_array($max, $links)) {
        if (!in_array($max - 1, $links)) {
            echo '<span>...</span>';
        }
        
        $class = $paged == $max ? ' current' : '';
        printf('<a href="%s" class="%s">%s</a>', esc_url(get_pagenum_link($max)), $class, $max);
    }
    
    if (get_next_posts_link()) {
        printf('<a href="%s">%s</a>', get_next_posts_page_link(), 'Siguiente &raquo;');
    }
    
    echo '</div>';
}

/**
 * Función helper para mostrar la fecha de publicación
 */
function atareao_theme_posted_on() {
    $time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
    
    $time_string = sprintf($time_string,
        esc_attr(get_the_date(DATE_W3C)),
        esc_html(get_the_date())
    );
    
    printf('<span class="posted-on">%s</span>', $time_string);
}

/**
 * Función helper para mostrar el autor
 */
function atareao_theme_posted_by() {
    printf(
        '<span class="author vcard"><a class="url fn n" href="%1$s">%2$s</a></span>',
        esc_url(get_author_posts_url(get_the_author_meta('ID'))),
        esc_html(get_the_author())
    );
}

/**
 * 8 tutoriales por página en el archivo de tutoriales
 */
function atareao_theme_tutorial_posts_per_page( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( 'tutorial' ) ) {
        $query->set( 'posts_per_page', 8 );
        $query->set( 'orderby', 'date' );
        $query->set( 'order', 'DESC' );
    }
}
add_action( 'pre_get_posts', 'atareao_theme_tutorial_posts_per_page' );

/**
 * Envuelve iframes de YouTube/Vimeo en un contenedor responsive 16:9
 */
function atareao_theme_responsive_embeds( $content ) {
    if ( ! is_singular() ) {
        return $content;
    }
    $pattern     = '/<iframe(?![^>]*class="[^"]*video-responsive)[^>]*(youtube\.com|youtu\.be|vimeo\.com)[^>]*>.*?<\/iframe>/is';
    $replacement = '<div class="video-responsive">$0</div>';
    return preg_replace( $pattern, $replacement, $content );
}
add_filter( 'the_content', 'atareao_theme_responsive_embeds' );
