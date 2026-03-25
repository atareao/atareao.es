<?php
/**
 * Taxonomías personalizadas
 *
 * @package Atareao_Functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atareao_Taxonomies {
    
    /**
     * Inicializar
     */
    public static function init() {
        // Registrar taxonomías directamente
        self::register_taxonomies();
    }
    
    /**
     * Registrar todas las taxonomías
     */
    public static function register_taxonomies() {
        self::register_tutorial_category();
        self::register_tutorial_tag();
        self::register_application_category();
        // Removed podcast categories registration to keep podcast posts free of that taxonomy
        self::register_software_category();
        self::register_difficulty();
        self::register_platform();
    }
    
    /**
     * Taxonomía: Categorías de Tutoriales
     */
    private static function register_tutorial_category() {
        $labels = array(
            'name'                       => _x('Categorías de Tutoriales', 'taxonomy general name', 'atareao-functionality'),
            'singular_name'              => _x('Categoría de Tutorial', 'taxonomy singular name', 'atareao-functionality'),
            'search_items'               => __('Buscar Categorías', 'atareao-functionality'),
            'popular_items'              => __('Categorías Populares', 'atareao-functionality'),
            'all_items'                  => __('Todas las Categorías', 'atareao-functionality'),
            'parent_item'                => __('Categoría Padre', 'atareao-functionality'),
            'parent_item_colon'          => __('Categoría Padre:', 'atareao-functionality'),
            'edit_item'                  => __('Editar Categoría', 'atareao-functionality'),
            'update_item'                => __('Actualizar Categoría', 'atareao-functionality'),
            'add_new_item'               => __('Añadir Nueva Categoría', 'atareao-functionality'),
            'new_item_name'              => __('Nombre de Nueva Categoría', 'atareao-functionality'),
            'separate_items_with_commas' => __('Separar categorías con comas', 'atareao-functionality'),
            'add_or_remove_items'        => __('Añadir o eliminar categorías', 'atareao-functionality'),
            'choose_from_most_used'      => __('Elegir de las más usadas', 'atareao-functionality'),
            'not_found'                  => __('No se encontraron categorías.', 'atareao-functionality'),
            'menu_name'                  => __('Categorías', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'show_tagcloud'     => true,
            'rewrite'           => array('slug' => 'tutorial-categoria'),
        );
        
        register_taxonomy('tutorial_category', array('tutorial', 'chapter'), $args);
    }
    
    /**
     * Taxonomía: Etiquetas de Tutoriales
     */
    private static function register_tutorial_tag() {
        $labels = array(
            'name'                       => _x('Etiquetas de Tutoriales', 'taxonomy general name', 'atareao-functionality'),
            'singular_name'              => _x('Etiqueta', 'taxonomy singular name', 'atareao-functionality'),
            'search_items'               => __('Buscar Etiquetas', 'atareao-functionality'),
            'popular_items'              => __('Etiquetas Populares', 'atareao-functionality'),
            'all_items'                  => __('Todas las Etiquetas', 'atareao-functionality'),
            'edit_item'                  => __('Editar Etiqueta', 'atareao-functionality'),
            'update_item'                => __('Actualizar Etiqueta', 'atareao-functionality'),
            'add_new_item'               => __('Añadir Nueva Etiqueta', 'atareao-functionality'),
            'new_item_name'              => __('Nombre de Nueva Etiqueta', 'atareao-functionality'),
            'separate_items_with_commas' => __('Separar etiquetas con comas', 'atareao-functionality'),
            'add_or_remove_items'        => __('Añadir o eliminar etiquetas', 'atareao-functionality'),
            'choose_from_most_used'      => __('Elegir de las más usadas', 'atareao-functionality'),
            'not_found'                  => __('No se encontraron etiquetas.', 'atareao-functionality'),
            'menu_name'                  => __('Etiquetas', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'show_tagcloud'     => true,
            'rewrite'           => array('slug' => 'tutorial-etiqueta'),
        );
        
        register_taxonomy('tutorial_tag', array('tutorial', 'chapter'), $args);
    }
    
    /**
     * Taxonomía: Categorías de Aplicaciones
     */
    private static function register_application_category() {
        $labels = array(
            'name'              => _x('Categorías de Aplicaciones', 'taxonomy general name', 'atareao-functionality'),
            'singular_name'     => _x('Categoría', 'taxonomy singular name', 'atareao-functionality'),
            'search_items'      => __('Buscar Categorías', 'atareao-functionality'),
            'all_items'         => __('Todas las Categorías', 'atareao-functionality'),
            'parent_item'       => __('Categoría Padre', 'atareao-functionality'),
            'parent_item_colon' => __('Categoría Padre:', 'atareao-functionality'),
            'edit_item'         => __('Editar Categoría', 'atareao-functionality'),
            'update_item'       => __('Actualizar Categoría', 'atareao-functionality'),
            'add_new_item'      => __('Añadir Nueva Categoría', 'atareao-functionality'),
            'new_item_name'     => __('Nombre de Nueva Categoría', 'atareao-functionality'),
            'menu_name'         => __('Categorías', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'aplicacion-categoria'),
        );
        
        register_taxonomy('application_category', 'application', $args);
    }
    
    /**
     * Taxonomía: Categorías de Podcast
     */
    private static function register_podcast_category() {
        $labels = array(
            'name'              => _x('Categorías de Podcasts', 'taxonomy general name', 'atareao-functionality'),
            'singular_name'     => _x('Categoría', 'taxonomy singular name', 'atareao-functionality'),
            'search_items'      => __('Buscar Categorías', 'atareao-functionality'),
            'all_items'         => __('Todas las Categorías', 'atareao-functionality'),
            'parent_item'       => __('Categoría Padre', 'atareao-functionality'),
            'parent_item_colon' => __('Categoría Padre:', 'atareao-functionality'),
            'edit_item'         => __('Editar Categoría', 'atareao-functionality'),
            'update_item'       => __('Actualizar Categoría', 'atareao-functionality'),
            'add_new_item'      => __('Añadir Nueva Categoría', 'atareao-functionality'),
            'new_item_name'     => __('Nombre de Nueva Categoría', 'atareao-functionality'),
            'menu_name'         => __('Categorías', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'podcast-categoria'),
        );
        
        register_taxonomy('podcast_category', 'podcast', $args);
    }
    
    /**
     * Taxonomía: Categorías de Software
     */
    private static function register_software_category() {
        $labels = array(
            'name'              => _x('Categorías de Software', 'taxonomy general name', 'atareao-functionality'),
            'singular_name'     => _x('Categoría', 'taxonomy singular name', 'atareao-functionality'),
            'search_items'      => __('Buscar Categorías', 'atareao-functionality'),
            'all_items'         => __('Todas las Categorías', 'atareao-functionality'),
            'parent_item'       => __('Categoría Padre', 'atareao-functionality'),
            'parent_item_colon' => __('Categoría Padre:', 'atareao-functionality'),
            'edit_item'         => __('Editar Categoría', 'atareao-functionality'),
            'update_item'       => __('Actualizar Categoría', 'atareao-functionality'),
            'add_new_item'      => __('Añadir Nueva Categoría', 'atareao-functionality'),
            'new_item_name'     => __('Nombre de Nueva Categoría', 'atareao-functionality'),
            'menu_name'         => __('Categorías', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'software-categoria'),
        );
        
        register_taxonomy('software_category', 'software', $args);
    }
    
    /**
     * Taxonomía: Nivel de Dificultad (para tutoriales y software)
     */
    private static function register_difficulty() {
        $labels = array(
            'name'                       => _x('Niveles de Dificultad', 'taxonomy general name', 'atareao-functionality'),
            'singular_name'              => _x('Nivel de Dificultad', 'taxonomy singular name', 'atareao-functionality'),
            'search_items'               => __('Buscar Niveles', 'atareao-functionality'),
            'popular_items'              => __('Niveles Populares', 'atareao-functionality'),
            'all_items'                  => __('Todos los Niveles', 'atareao-functionality'),
            'edit_item'                  => __('Editar Nivel', 'atareao-functionality'),
            'update_item'                => __('Actualizar Nivel', 'atareao-functionality'),
            'add_new_item'               => __('Añadir Nuevo Nivel', 'atareao-functionality'),
            'new_item_name'              => __('Nombre de Nuevo Nivel', 'atareao-functionality'),
            'separate_items_with_commas' => __('Separar niveles con comas', 'atareao-functionality'),
            'add_or_remove_items'        => __('Añadir o eliminar niveles', 'atareao-functionality'),
            'choose_from_most_used'      => __('Elegir de los más usados', 'atareao-functionality'),
            'not_found'                  => __('No se encontraron niveles.', 'atareao-functionality'),
            'menu_name'                  => __('Dificultad', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'dificultad'),
        );
        
        register_taxonomy('difficulty', array('tutorial', 'software'), $args);
        
        // Insertar términos predeterminados
        self::insert_default_difficulty_terms();
    }
    
    /**
     * Taxonomía: Plataforma (para aplicaciones y software)
     */
    private static function register_platform() {
        $labels = array(
            'name'                       => _x('Plataformas', 'taxonomy general name', 'atareao-functionality'),
            'singular_name'              => _x('Plataforma', 'taxonomy singular name', 'atareao-functionality'),
            'search_items'               => __('Buscar Plataformas', 'atareao-functionality'),
            'popular_items'              => __('Plataformas Populares', 'atareao-functionality'),
            'all_items'                  => __('Todas las Plataformas', 'atareao-functionality'),
            'edit_item'                  => __('Editar Plataforma', 'atareao-functionality'),
            'update_item'                => __('Actualizar Plataforma', 'atareao-functionality'),
            'add_new_item'               => __('Añadir Nueva Plataforma', 'atareao-functionality'),
            'new_item_name'              => __('Nombre de Nueva Plataforma', 'atareao-functionality'),
            'separate_items_with_commas' => __('Separar plataformas con comas', 'atareao-functionality'),
            'add_or_remove_items'        => __('Añadir o eliminar plataformas', 'atareao-functionality'),
            'choose_from_most_used'      => __('Elegir de las más usadas', 'atareao-functionality'),
            'not_found'                  => __('No se encontraron plataformas.', 'atareao-functionality'),
            'menu_name'                  => __('Plataformas', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'plataforma'),
        );
        
        register_taxonomy('platform', array('application', 'software'), $args);
        
        // Insertar términos predeterminados
        self::insert_default_platform_terms();
    }
    
    /**
     * Insertar términos de dificultad predeterminados
     */
    private static function insert_default_difficulty_terms() {
        $terms = array(
            'principiante' => 'Principiante',
            'intermedio'   => 'Intermedio',
            'avanzado'     => 'Avanzado',
            'experto'      => 'Experto',
        );
        
        foreach ($terms as $slug => $name) {
            if (!term_exists($slug, 'difficulty')) {
                wp_insert_term($name, 'difficulty', array('slug' => $slug));
            }
        }
    }
    
    /**
     * Insertar términos de plataforma predeterminados
     */
    private static function insert_default_platform_terms() {
        $terms = array(
            'linux'   => 'Linux',
            'windows' => 'Windows',
            'macos'   => 'macOS',
            'android' => 'Android',
            'ios'     => 'iOS',
            'web'     => 'Web',
        );
        
        foreach ($terms as $slug => $name) {
            if (!term_exists($slug, 'platform')) {
                wp_insert_term($name, 'platform', array('slug' => $slug));
            }
        }
    }
}
