<?php
/**
 * Custom Post Types
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class PostTypes
{

    /**
     * Inicializar
     */
    public static function init()
    {
        add_rewrite_tag('%tutorial_slug%', '([^/]+)');
        add_rewrite_tag('%season%', '([0-9]+)');

        add_rewrite_rule(
            '^tutorial/([^/]+)/([^/]+)/?$',
            'index.php?capitulo=$matches[2]&tutorial_slug=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^podcast/temporada/([0-9]+)/?$',
            'index.php?post_type=podcast&season=$matches[1]',
            'top'
        );

        add_action('template_redirect', array(__CLASS__, 'redirectChapterByNumber'));
        add_action('template_redirect', array(__CLASS__, 'redirectPodcastByNumber'));

        self::registerPostTypes();

        add_filter('manage_capitulo_posts_columns', array(__CLASS__, 'capituloColumns'));
        add_action('manage_capitulo_posts_custom_column', array(__CLASS__, 'capituloCustomColumn'), 10, 2);
        add_filter('manage_edit-capitulo_sortable_columns', array(__CLASS__, 'capituloSortableColumns'));
        add_action('pre_get_posts', array(__CLASS__, 'capituloPreGetPosts'));
        add_action('restrict_manage_posts', array(__CLASS__, 'capituloFilterDropdown'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueueAdminScripts'));

        add_filter('manage_podcast_posts_columns', array(__CLASS__, 'podcastColumns'));
        add_action('manage_podcast_posts_custom_column', array(__CLASS__, 'podcastCustomColumn'), 10, 2);
        add_filter('manage_edit-podcast_sortable_columns', array(__CLASS__, 'podcastSortableColumns'));
        add_action('pre_get_posts', array(__CLASS__, 'podcastPreGetPosts'));
        add_action('restrict_manage_posts', array(__CLASS__, 'podcastFilterDropdown'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueuePodcastAdminScripts'));

        add_filter('post_type_link', array(__CLASS__, 'capituloPermalink'), 10, 2);
    }

    /**
     * Columnas personalizadas para la lista de 'capitulo'
     */
    public static function capituloColumns($columns)
    {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['tutorial'] = __('Tutorial', 'atareao-functionality');
                $new['numero_capitulo_col'] = __('Nº Capítulo', 'atareao-functionality');
            }
        }
        return $new;
    }

    /**
     * Rellenar las columnas personalizadas en la lista de 'capitulo'
     */
    public static function capituloCustomColumn($column, $post_id)
    {
        if ($column === 'tutorial') {
            $tutorial_id = get_post_meta($post_id, 'tutorial-id', true);
            if (!empty($tutorial_id)) {
                $tutorial = get_post($tutorial_id);
                if ($tutorial) {
                    $edit_link = get_edit_post_link($tutorial_id);
                    $title = esc_html($tutorial->post_title);
                    if ($edit_link) {
                        echo '<a href="' . esc_url($edit_link) . '">' . $title . '</a>';
                    } else {
                        echo $title;
                    }
                } else {
                    echo esc_html($tutorial_id);
                }
            } else {
                echo '&ndash;';
            }
        } elseif ($column === 'numero_capitulo_col') {
            $num = get_post_meta($post_id, 'numero-capitulo', true);
            if ($num === '') {
                echo '&ndash;';
            } else {
                echo esc_html($num);
            }
        }
    }

    /**
     * Registrar todos los Custom Post Types
     */
    public static function registerPostTypes()
    {
        self::registerTutorial();
        self::registerChapter();
        self::registerApplication();
        self::registerPodcast();
        self::registerSoftware();
    }

    /**
     * Columnas personalizadas para la lista de 'podcast'
     */
    public static function podcastColumns($columns)
    {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['season'] = __('Temporada', 'atareao-functionality');
                $new['number'] = __('Número', 'atareao-functionality');
                $new['mp3_url_col'] = __('URL Audio', 'atareao-functionality');
            }
        }
        return $new;
    }

    /**
     * Renderizar las columnas personalizadas de podcast
     */
    public static function podcastCustomColumn($column, $post_id)
    {
        if ($column === 'season') {
            $season = get_post_meta($post_id, 'season', true);
            echo $season !== '' ? esc_html($season) : '&ndash;';
        } elseif ($column === 'number') {
            $num = get_post_meta($post_id, 'number', true);
            echo $num !== '' ? esc_html($num) : '&ndash;';
        } elseif ($column === 'mp3_url_col') {
            $url = get_post_meta($post_id, 'mp3-url', true);
            if (!empty($url)) {
                echo '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a>';
            } else {
                echo '&ndash;';
            }
        }
    }

    /**
     * Declarar columnas ordenables para podcast
     */
    public static function podcastSortableColumns($columns)
    {
        $columns['season'] = 'season';
        $columns['number'] = 'number';
        $columns['mp3_url_col'] = 'mp3_url_col';
        return $columns;
    }

    /**
     * Ajustar la query admin para podcast
     */
    public static function podcastPreGetPosts($query)
    {
        if (is_admin() && $query->is_main_query()) {
            $post_type = $query->get('post_type');
            if ($post_type !== 'podcast') {
                return;
            }

            if (isset($_GET['season_filter']) && $_GET['season_filter'] !== '') {
                $season = sanitize_text_field($_GET['season_filter']);
                $meta_query = $query->get('meta_query');
                if (!is_array($meta_query)) {
                    $meta_query = array();
                }
                $meta_query[] = array(
                    'key' => 'season',
                    'value' => (string) $season,
                    'compare' => '=',
                );
                $query->set('meta_query', $meta_query);
            }

            $orderby = $query->get('orderby');
            if ($orderby === 'season') {
                $query->set('meta_key', 'season');
                $query->set('orderby', 'meta_value_num');
            } elseif ($orderby === 'number') {
                $query->set('meta_key', 'number');
                $query->set('orderby', 'meta_value_num');
            } elseif ($orderby === 'mp3_url_col') {
                $query->set('meta_key', 'mp3-url');
                $query->set('orderby', 'meta_value');
            }

            return;
        }

        if (!$query->is_main_query()) {
            return;
        }
        if (is_post_type_archive('podcast') || ($query->get('post_type') === 'podcast')) {
            $season = get_query_var('season');
            if ($season !== '') {
                $season = sanitize_text_field($season);
                $meta_query = $query->get('meta_query');
                if (!is_array($meta_query)) {
                    $meta_query = array();
                }
                $meta_query[] = array(
                    'key' => 'season',
                    'value' => (string) $season,
                    'compare' => '=',
                );
                $query->set('meta_query', $meta_query);
            }
        }
    }

    /**
     * Dropdown filter for podcast season
     */
    public static function podcastFilterDropdown()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'podcast') {
            return;
        }

        $selected = isset($_GET['season_filter']) ? sanitize_text_field($_GET['season_filter']) : '';

        global $wpdb;
        $seasons = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'season'
            AND p.post_type = 'podcast'
            AND p.post_status IN ('publish', 'draft', 'private', 'pending', 'future')
            AND pm.meta_value != ''
            ORDER BY CAST(pm.meta_value AS UNSIGNED)"
        ));

        echo '<select name="season_filter" id="season_filter" style="margin-left:8px;">';
        echo '<option value="">' . esc_html__('— Todas las temporadas —', 'atareao-functionality') . '</option>';
        foreach ($seasons as $s) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($s),
                selected($selected, $s, false),
                esc_html($s)
            );
        }
        echo '</select>';
    }

    /**
     * Enqueue admin JS for podcast list
     */
    public static function enqueuePodcastAdminScripts($hook)
    {
        if ($hook !== 'edit.php') {
            return;
        }
        if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'podcast') {
            return;
        }

        wp_register_script('atareao-podcast-admin', '', array('jquery'), false, true);
        wp_enqueue_script('atareao-podcast-admin');
        $inline = "jQuery(function($){ $('#season_filter').on('change', function(){ $(this).closest('form').submit(); }); });";
        wp_add_inline_script('atareao-podcast-admin', $inline);
    }

    /**
     * Declarar columnas ordenables
     */
    public static function capituloSortableColumns($columns)
    {
        $columns['tutorial'] = 'tutorial';
        $columns['numero_capitulo_col'] = 'numero_capitulo_col';
        return $columns;
    }

    /**
     * Ajustar la query de admin para soportar orden por meta
     */
    public static function capituloPreGetPosts($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        $post_type = $query->get('post_type');
        if ($post_type !== 'capitulo') {
            return;
        }
        if (isset($_GET['tutorial_filter']) && $_GET['tutorial_filter'] !== '') {
            $tutorial_id = intval($_GET['tutorial_filter']);
            $meta_query = $query->get('meta_query');
            if (!is_array($meta_query)) {
                $meta_query = array();
            }
            $meta_query[] = array(
                'key' => 'tutorial-id',
                'value' => (string) $tutorial_id,
                'compare' => '=',
            );
            $query->set('meta_query', $meta_query);
        }

        $orderby = $query->get('orderby');
        if ($orderby === 'tutorial') {
            $query->set('meta_key', 'tutorial-id');
            $query->set('orderby', 'meta_value_num');
        } elseif ($orderby === 'numero_capitulo_col') {
            $query->set('meta_key', 'numero-capitulo');
            $query->set('orderby', 'meta_value_num');
        }
    }

    /**
     * Output a dropdown to filter Capítulos by Tutorial in admin list
     */
    public static function capituloFilterDropdown()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'capitulo') {
            return;
        }

        $selected = isset($_GET['tutorial_filter']) ? intval($_GET['tutorial_filter']) : '';
        $tutorials = get_posts(array(
            'post_type' => 'tutorial',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish',
            'fields' => 'ids',
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ));

        echo '<select name="tutorial_filter" id="tutorial_filter" style="margin-left:8px;">';
        echo '<option value="">' . esc_html__('— Todos los tutoriales —', 'atareao-functionality') . '</option>';
        foreach ($tutorials as $tid) {
            printf(
                '<option value="%d" %s>%s</option>',
                $tid,
                selected($selected, $tid, false),
                esc_html(get_the_title($tid))
            );
        }
        echo '</select>';
    }

    /**
     * Enqueue admin scripts for capitulo admin screens
     */
    public static function enqueueAdminScripts($hook)
    {
        if ($hook !== 'edit.php') {
            return;
        }
        if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'capitulo') {
            return;
        }

        wp_register_script('atareao-capitulo-admin', '', array('jquery'), false, true);
        wp_enqueue_script('atareao-capitulo-admin');
        $inline = "jQuery(function($){ $('#tutorial_filter').on('change', function(){ $(this).closest('form').submit(); }); });";
        wp_add_inline_script('atareao-capitulo-admin', $inline);
    }

    /**
     * CPT: Tutoriales
     */
    private static function registerTutorial()
    {
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
            'description'        => __('Tutoriales sobre Linux, Open Source y tecnología.', 'atareao-functionality'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tutorial'),
            'capability_type'    => array('podcast', 'podcasts'),
            'map_meta_cap'       => true,
            'capabilities'       => array(
                'publish_posts'       => 'publish_podcasts',
                'edit_posts'          => 'edit_podcasts',
                'edit_others_posts'   => 'edit_others_podcasts',
                'delete_posts'        => 'delete_podcasts',
                'delete_others_posts' => 'delete_others_podcasts',
                'read_private_posts'  => 'read_private_podcasts',
                'edit_post'           => 'edit_podcast',
                'delete_post'         => 'delete_podcast',
                'read_post'           => 'read_podcast',
            ),
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
     * CPT: Capítulos
     */
    private static function registerChapter()
    {
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
    private static function registerApplication()
    {
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
            'description'        => __('Aplicaciones para Linux, Open Source y tecnología.', 'atareao-functionality'),
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
    private static function registerPodcast()
    {
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
            'description'        => __('El podcast de Linux y Open Source', 'atareao-functionality'),
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
    private static function registerSoftware()
    {
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
            'description'        => __('Software para Linux, Open Source y tecnología.', 'atareao-functionality'),
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

    /**
     * Redirección desde /tutorial/{tutorial-slug}/{numero}/ al permalink real del capítulo
     */
    public static function redirectChapterByNumber()
    {
        if (!is_404()) {
            return;
        }

        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (!preg_match('#^tutorial/([^/]+)/(\d+)$#', $path, $m)) {
            return;
        }

        $tutorial_slug = $m[1];
        $chapter_number = $m[2];

        $tutorial = get_page_by_path($tutorial_slug, OBJECT, 'tutorial');
        if (!$tutorial) {
            return;
        }

        $chapters = get_posts(array(
            'post_type'      => 'capitulo',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'AND',
                array('key' => 'tutorial-id', 'value' => (string) $tutorial->ID),
                array('key' => 'numero-capitulo', 'value' => (string) $chapter_number),
            ),
        ));

        if (empty($chapters)) {
            return;
        }

        wp_redirect(get_permalink($chapters[0]->ID), 301);
        exit;
    }

    /**
     * Redirige /podcast/{número} → permalink del episodio (301)
     */
    public static function redirectPodcastByNumber()
    {
        if (!is_404()) {
            return;
        }

        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (!preg_match('#^podcast/(\d+)$#', $path, $m)) {
            return;
        }

        $episode_number = $m[1];

        $episodes = get_posts(array(
            'post_type'      => 'podcast',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array('key' => 'number', 'value' => (string) $episode_number),
            ),
        ));

        if (empty($episodes)) {
            return;
        }

        wp_redirect(get_permalink($episodes[0]->ID), 301);
        exit;
    }

    /**
     * Resuelve %tutorial_slug% en el permalink de un capítulo
     */
    public static function capituloPermalink($permalink, $post)
    {
        if (!is_object($post) || $post->post_type !== 'capitulo') {
            return $permalink;
        }
        if (strpos($permalink, '%tutorial_slug%') === false) {
            return $permalink;
        }
        $tutorial_id = get_post_meta($post->ID, 'tutorial-id', true);
        $tutorial_slug = 'sin-tutorial';
        if ($tutorial_id) {
            $tutorial = get_post($tutorial_id);
            if ($tutorial && $tutorial->post_name) {
                $tutorial_slug = $tutorial->post_name;
            }
        }
        return str_replace('%tutorial_slug%', $tutorial_slug, $permalink);
    }
}
