<?php
/**
 * Custom Post Types
 *
 * @package Atareao_Functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atareao_Post_Types {
    
    /**
     * Inicializar
     */
    public static function init() {
        // Registrar etiqueta de reescritura para el slug del tutorial en la URL del capítulo
        add_rewrite_tag('%tutorial_slug%', '([^/]+)');

        // Regla de reescritura para /tutorial/{tutorial-slug}/{capitulo-slug}/
        // Debe ir antes ('top') que las reglas del CPT tutorial
        add_rewrite_rule(
            '^tutorial/([^/]+)/([^/]+)/?$',
            'index.php?capitulo=$matches[2]&tutorial_slug=$matches[1]',
            'top'
        );

        // Manejar la redirección desde /tutorial/{slug}/{numero}/ al capítulo real
        add_action('template_redirect', array(__CLASS__, 'redirect_chapter_by_number'));

        // Manejar la redirección desde /podcast/{numero}/ al episodio real
        add_action('template_redirect', array(__CLASS__, 'redirect_podcast_by_number'));

        // Registrar post types directamente
        self::register_post_types();
        
        // Registrar metaboxes y relaciones
        add_action('add_meta_boxes', array(__CLASS__, 'add_tutorial_metabox'));
        add_action('save_post_capitulo', array(__CLASS__, 'save_tutorial_metabox'));

        // Filtro para resolver %tutorial_slug% en los permalinks de capítulos
        add_filter('post_type_link', array(__CLASS__, 'capitulo_permalink'), 10, 2);
    }
    
    /**
     * Registrar todos los Custom Post Types
     */
    public static function register_post_types() {
        self::register_tutorial();
        self::register_chapter();
        self::register_application();
        self::register_podcast();
        self::register_software();
    }
    
    /**
     * CPT: Tutoriales
     */
    private static function register_tutorial() {
        $labels = array(
            'name'                  => _x('Tutoriales', 'Post type general name', 'atareao-functionality'),
            'singular_name'         => _x('Tutorial', 'Post type singular name', 'atareao-functionality'),
            'menu_name'             => _x('Tutoriales', 'Admin Menu text', 'atareao-functionality'),
            'name_admin_bar'        => _x('Tutorial', 'Add New on Toolbar', 'atareao-functionality'),
            'add_new'               => __('Añadir nuevo', 'atareao-functionality'),
            'add_new_item'          => __('Añadir nuevo Tutorial', 'atareao-functionality'),
            'new_item'              => __('Nuevo Tutorial', 'atareao-functionality'),
            'edit_item'             => __('Editar Tutorial', 'atareao-functionality'),
            'view_item'             => __('Ver Tutorial', 'atareao-functionality'),
            'all_items'             => __('Todos los Tutoriales', 'atareao-functionality'),
            'search_items'          => __('Buscar Tutoriales', 'atareao-functionality'),
            'parent_item_colon'     => __('Tutorial padre:', 'atareao-functionality'),
            'not_found'             => __('No se encontraron tutoriales.', 'atareao-functionality'),
            'not_found_in_trash'    => __('No se encontraron tutoriales en la papelera.', 'atareao-functionality'),
            'featured_image'        => _x('Imagen destacada', 'tutorial', 'atareao-functionality'),
            'set_featured_image'    => _x('Establecer imagen destacada', 'tutorial', 'atareao-functionality'),
            'remove_featured_image' => _x('Eliminar imagen destacada', 'tutorial', 'atareao-functionality'),
            'use_featured_image'    => _x('Usar como imagen destacada', 'tutorial', 'atareao-functionality'),
            'archives'              => _x('Archivo de Tutoriales', 'tutorial', 'atareao-functionality'),
            'insert_into_item'      => _x('Insertar en tutorial', 'tutorial', 'atareao-functionality'),
            'uploaded_to_this_item' => _x('Subido a este tutorial', 'tutorial', 'atareao-functionality'),
            'filter_items_list'     => _x('Filtrar lista de tutoriales', 'tutorial', 'atareao-functionality'),
            'items_list_navigation' => _x('Navegación de lista de tutoriales', 'tutorial', 'atareao-functionality'),
            'items_list'            => _x('Lista de tutoriales', 'tutorial', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'             => $labels,
            'description'        => __('Tutoriales sobre Linux, Open Source y tecnología. Desde los primeros pasos hasta técnicas avanzadas, aquí encontrarás guías completas para aprender a tu ritmo.', 'atareao-functionality'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tutorial'),
            'capability_type'    => 'post',
            'has_archive'        => 'tutoriales',
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-book-alt',
            'show_in_rest'       => true,
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions', 'custom-fields'),
        );
        
        register_post_type('tutorial', $args);
    }
    
    /**
     * CPT: Capítulos (relacionado con Tutoriales)
     */
    private static function register_chapter() {
        $labels = array(
            'name'                  => _x('Capítulos', 'Post type general name', 'atareao-functionality'),
            'singular_name'         => _x('Capítulo', 'Post type singular name', 'atareao-functionality'),
            'menu_name'             => _x('Capítulos', 'Admin Menu text', 'atareao-functionality'),
            'name_admin_bar'        => _x('Capítulo', 'Add New on Toolbar', 'atareao-functionality'),
            'add_new'               => __('Añadir nuevo', 'atareao-functionality'),
            'add_new_item'          => __('Añadir nuevo Capítulo', 'atareao-functionality'),
            'new_item'              => __('Nuevo Capítulo', 'atareao-functionality'),
            'edit_item'             => __('Editar Capítulo', 'atareao-functionality'),
            'view_item'             => __('Ver Capítulo', 'atareao-functionality'),
            'all_items'             => __('Todos los Capítulos', 'atareao-functionality'),
            'search_items'          => __('Buscar Capítulos', 'atareao-functionality'),
            'not_found'             => __('No se encontraron capítulos.', 'atareao-functionality'),
            'not_found_in_trash'    => __('No se encontraron capítulos en la papelera.', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tutorial/%tutorial_slug%', 'with_front' => false),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-media-document',
            'show_in_rest'       => true,
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions', 'custom-fields', 'page-attributes'),
        );
        
        register_post_type('capitulo', $args);
    }
    
    /**
     * CPT: Mis aplicaciones
     */
    private static function register_application() {
        $labels = array(
            'name'                  => _x('Aplicaciones', 'Post type general name', 'atareao-functionality'),
            'singular_name'         => _x('Aplicación', 'Post type singular name', 'atareao-functionality'),
            'menu_name'             => _x('Mis Aplicaciones', 'Admin Menu text', 'atareao-functionality'),
            'name_admin_bar'        => _x('Aplicación', 'Add New on Toolbar', 'atareao-functionality'),
            'add_new'               => __('Añadir nueva', 'atareao-functionality'),
            'add_new_item'          => __('Añadir nueva aplicación', 'atareao-functionality'),
            'new_item'              => __('Nueva aplicación', 'atareao-functionality'),
            'edit_item'             => __('Editar aplicación', 'atareao-functionality'),
            'view_item'             => __('Ver aplicación', 'atareao-functionality'),
            'all_items'             => __('Todas las aplicaciones', 'atareao-functionality'),
            'search_items'          => __('Buscar aplicaciones', 'atareao-functionality'),
            'not_found'             => __('No se encontraron aplicaciones.', 'atareao-functionality'),
            'not_found_in_trash'    => __('No se encontraron aplicaciones en la papelera.', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'             => $labels,
            'description'        => __('Aplicaciones para Linux, Open Source y tecnología. Descubre las mejores herramientas y programas para sacar el máximo partido a tu sistema.', 'atareao-functionality'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'aplicacion'),
            'capability_type'    => 'post',
            'has_archive'        => 'aplicaciones',
            'hierarchical'       => false,
            'menu_position'      => 7,
            'menu_icon'          => 'dashicons-smartphone',
            'show_in_rest'       => true,
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions', 'custom-fields'),
        );
        
        register_post_type('aplicacion', $args);
    }
    
    /**
     * CPT: Podcast
     */
    private static function register_podcast() {
        $labels = array(
            'name'                  => _x('Podcasts', 'Post type general name', 'atareao-functionality'),
            'singular_name'         => _x('Podcast', 'Post type singular name', 'atareao-functionality'),
            'menu_name'             => _x('Podcasts', 'Admin Menu text', 'atareao-functionality'),
            'name_admin_bar'        => _x('Podcast', 'Add New on Toolbar', 'atareao-functionality'),
            'add_new'               => __('Añadir nuevo', 'atareao-functionality'),
            'add_new_item'          => __('Añadir nuevo Podcast', 'atareao-functionality'),
            'new_item'              => __('Nuevo Podcast', 'atareao-functionality'),
            'edit_item'             => __('Editar Podcast', 'atareao-functionality'),
            'view_item'             => __('Ver Podcast', 'atareao-functionality'),
            'all_items'             => __('Todos los Podcasts', 'atareao-functionality'),
            'search_items'          => __('Buscar Podcasts', 'atareao-functionality'),
            'not_found'             => __('No se encontraron podcasts.', 'atareao-functionality'),
            'not_found_in_trash'    => __('No se encontraron podcasts en la papelera.', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'             => $labels,
            'description'        => __('El podcast de Linux y Open Source, donde encontrarás desde que es Self Hosting, pasando como montar un servidor de música o de archivos, o cualquier otro servicio que puedas imaginar hasta como exprimir al máximo tu entorno de escritorio Linux. Vamos, cualquier cosa quieras hacer con Linux, seguro, seguro, seguro que la encontrarás aquí.', 'atareao-functionality'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'podcast'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 8,
            'menu_icon'          => 'dashicons-microphone',
            'show_in_rest'       => true,
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions', 'custom-fields'),
        );
        
        register_post_type('podcast', $args);
    }
    
    /**
     * CPT: Software
     */
    private static function register_software() {
        $labels = array(
            'name'                  => _x('Software', 'Post type general name', 'atareao-functionality'),
            'singular_name'         => _x('Software', 'Post type singular name', 'atareao-functionality'),
            'menu_name'             => _x('Software', 'Admin Menu text', 'atareao-functionality'),
            'name_admin_bar'        => _x('Software', 'Add New on Toolbar', 'atareao-functionality'),
            'add_new'               => __('Añadir nuevo', 'atareao-functionality'),
            'add_new_item'          => __('Añadir nuevo Software', 'atareao-functionality'),
            'new_item'              => __('Nuevo Software', 'atareao-functionality'),
            'edit_item'             => __('Editar Software', 'atareao-functionality'),
            'view_item'             => __('Ver Software', 'atareao-functionality'),
            'all_items'             => __('Todo el Software', 'atareao-functionality'),
            'search_items'          => __('Buscar Software', 'atareao-functionality'),
            'not_found'             => __('No se encontró software.', 'atareao-functionality'),
            'not_found_in_trash'    => __('No se encontró software en la papelera.', 'atareao-functionality'),
        );
        
        $args = array(
            'labels'             => $labels,
            'description'        => __('Software para Linux, Open Source y tecnología. Descubre herramientas, utilidades y programas recomendados para potenciar tu sistema.', 'atareao-functionality'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'software'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 9,
            'menu_icon'          => 'dashicons-desktop',
            'show_in_rest'       => true,
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions', 'custom-fields'),
        );
        
        register_post_type('software', $args);
    }
    

    
    /**     * Redirección desde /tutorial/{tutorial-slug}/{numero}/ al permalink real del capítulo
     */
    public static function redirect_chapter_by_number() {
        // Solo actuar en 404 — /tutorial/{tutorial-slug}/{número}
        if ( ! is_404() ) {
            return;
        }

        $path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
        if ( ! preg_match( '#^tutorial/([^/]+)/(\d+)$#', $path, $m ) ) {
            return;
        }

        $tutorial_slug  = $m[1];
        $chapter_number = $m[2];

        $tutorial = get_page_by_path( $tutorial_slug, OBJECT, 'tutorial' );
        if ( ! $tutorial ) {
            return;
        }

        $chapters = get_posts( array(
            'post_type'      => 'capitulo',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'AND',
                array( 'key' => 'tutorial-id',     'value' => (string) $tutorial->ID ),
                array( 'key' => 'numero-capitulo', 'value' => (string) $chapter_number ),
            ),
        ) );

        if ( empty( $chapters ) ) {
            return;
        }

        wp_redirect( get_permalink( $chapters[0]->ID ), 301 );
        exit;
    }

    /**
     * Redirige /podcast/{número} → permalink del episodio (301).
     */
    public static function redirect_podcast_by_number() {
        if ( ! is_404() ) {
            return;
        }

        $path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
        if ( ! preg_match( '#^podcast/(\d+)$#', $path, $m ) ) {
            return;
        }

        $episode_number = $m[1];

        $episodes = get_posts( array(
            'post_type'      => 'podcast',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array( 'key' => 'number', 'value' => (string) $episode_number ),
            ),
        ) );

        if ( empty( $episodes ) ) {
            return;
        }

        wp_redirect( get_permalink( $episodes[0]->ID ), 301 );
        exit;
    }

    /**     * Resuelve %tutorial_slug% en el permalink de un capítulo.
     * Resultado: /capitulo/{tutorial-slug}/{capitulo-slug}/
     */
    public static function capitulo_permalink($permalink, $post) {
        if ( ! is_object($post) || $post->post_type !== 'capitulo' ) {
            return $permalink;
        }
        if ( strpos($permalink, '%tutorial_slug%') === false ) {
            return $permalink;
        }
        $tutorial_id = get_post_meta($post->ID, 'tutorial-id', true);
        $tutorial_slug = 'sin-tutorial';
        if ( $tutorial_id ) {
            $tutorial = get_post($tutorial_id);
            if ( $tutorial && $tutorial->post_name ) {
                $tutorial_slug = $tutorial->post_name;
            }
        }
        return str_replace('%tutorial_slug%', $tutorial_slug, $permalink);
    }

    /**
     * Añadir metabox para vincular capítulo con tutorial
     */
    public static function add_tutorial_metabox() {
        add_meta_box(
            'chapter_tutorial',
            __('Tutorial', 'atareao-functionality'),
            array(__CLASS__, 'render_tutorial_metabox'),
            'capitulo',
            'side',
            'high'
        );
    }
    
    /**
     * Renderizar metabox de tutorial
     */
    public static function render_tutorial_metabox($post) {
        wp_nonce_field('chapter_tutorial_nonce', 'chapter_tutorial_nonce_field');
        
        $selected_tutorial = get_post_meta($post->ID, 'tutorial-id', true);
        
        $tutorials = get_posts(array(
            'post_type'      => 'tutorial',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
        
        echo '<select name="tutorial_id" style="width: 100%;">';
        echo '<option value="">' . __('Seleccionar Tutorial', 'atareao-functionality') . '</option>';
        
        foreach ($tutorials as $tutorial) {
            printf(
                '<option value="%d"%s>%s</option>',
                $tutorial->ID,
                selected($selected_tutorial, $tutorial->ID, false),
                esc_html($tutorial->post_title)
            );
        }
        
        echo '</select>';
        
        echo '<p class="description">' . __('Selecciona el tutorial al que pertenece este capítulo.', 'atareao-functionality') . '</p>';
    }
    
    /**
     * Guardar metabox de tutorial
     */
    public static function save_tutorial_metabox($post_id) {
        // Verificar nonce
        if (!isset($_POST['chapter_tutorial_nonce_field']) || 
            !wp_verify_nonce($_POST['chapter_tutorial_nonce_field'], 'chapter_tutorial_nonce')) {
            return;
        }
        
        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar el tutorial ID
        if (isset($_POST['tutorial_id'])) {
            update_post_meta($post_id, 'tutorial-id', sanitize_text_field($_POST['tutorial_id']));
        }
    }
}
