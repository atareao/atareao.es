<?php
/**
 * Metaboxes personalizados
 *
 * @package Atareao_Functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atareao_Metaboxes {
    
    /**
     * Inicializar
     */
    public static function init() {
        // Registrar hooks para metaboxes
        add_action('add_meta_boxes', array(__CLASS__, 'add_metaboxes'));
        add_action('save_post', array(__CLASS__, 'save_metaboxes'));
        // Contador de visitas en frontend
        add_action('template_redirect', array(__CLASS__, 'count_post_views'));
        // AJAX: obtener siguiente numero de capitulo
        add_action('wp_ajax_atareao_get_next_numero_capitulo', array(__CLASS__, 'ajax_get_next_numero_capitulo'));
        // Enqueue admin scripts for editing posts (to update numero-capitulo when tutorial changes)
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_edit_scripts'));
        
        // Registrar metadatos en REST API
        add_action('init', array(__CLASS__, 'register_meta_fields'));
        // Registrar hooks admin para columnas de vistas
        add_action('admin_init', array(__CLASS__, 'register_views_admin_hooks'));
        // Mostrar vistas en el frontend junto al título
        add_filter('the_title', array(__CLASS__, 'the_title_with_views'), 10, 2);
        // Registrar all_mateadata en REST API para depuración (opcional, se puede quitar si no se necesita)
        add_action('rest_api_init', array(__CLASS__, 'register_rest_fields'));
    }

    public static function register_rest_fields() {
        register_rest_field('podcast', 'all_metadata', [
            'get_callback' => function($post_array) {
                return get_post_meta($post_array['id']);
            },
          'schema' => null,
        ]);
    }
    /**
     * Registrar campos meta en REST API
     */
    public static function register_meta_fields() {
        register_rest_field('podcast', 'metadata', array(
            'get_callback' => function ( $data ) {
                return get_post_meta( $data['id'], '', '' );
            }
        ));

        // Registrar mp3-url para acceso desde el editor de bloques
        register_post_meta('podcast', 'mp3-url', array(
            'object_subtype' => 'podcast',
            'type' => 'string',
            'description' => __('URL del archivo MP3', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        
        // Registrar episode_number para REST API
        register_post_meta('podcast', 'number', array(
            'object_subtype' => 'podcast',
            'type' => 'string',
            'description' => __('Número de episodio', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        // Registrar season para REST API
        register_post_meta('podcast', 'season', array(
            'type' => 'string',
            'description' => __('Temporada', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // Registrar numero-capitulo para REST API
        register_post_meta('capitulo', 'numero-capitulo', array(
            'type' => 'string',
            'description' => __('Número de capítulo', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // Registrar tutorial-id para REST API
        register_post_meta('capitulo', 'tutorial-id', array(
            'type' => 'string',
            'description' => __('ID del tutorial al que pertenece', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // Registrar post_views_count para varios tipos (conteo de visitas)
        $types = array('post', 'podcast', 'capitulo', 'tutorial', 'aplicacion', 'application', 'software');
        foreach ($types as $t) {
            register_post_meta($t, 'post_views_count', array(
                'type' => 'integer',
                'description' => __('Número de visitas del post', 'atareao-functionality'),
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'intval',
            ));
        }

        // Registrar metadatos con guión bajo usados por los metaboxes
        // (URL de descarga y repositorio, versión) para que estén disponibles vía REST
        $app_types = array('application', 'software');
        register_post_meta($app_types, '_download_url', array(
            'type' => 'string',
            'description' => __('URL de descarga (meta interno)', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ));

        register_post_meta($app_types, '_repository_url', array(
            'type' => 'string',
            'description' => __('URL del repositorio (meta interno)', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ));

        register_post_meta($app_types, '_version', array(
            'type' => 'string',
            'description' => __('Versión (meta interno)', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ));
    }
    
    /**
     * Añadir metaboxes
     */
    public static function add_metaboxes() {
        // Metabox para URL de descarga (Aplicaciones y Software)
        add_meta_box(
            'download_url',
            __('URL de Descarga', 'atareao-functionality'),
            array(__CLASS__, 'render_download_url_metabox'),
            array('application', 'software'),
            'normal',
            'high'
        );
        
        // Metabox para URL de repositorio (Aplicaciones y Software)
        add_meta_box(
            'repository_url',
            __('Repositorio', 'atareao-functionality'),
            array(__CLASS__, 'render_repository_url_metabox'),
            array('application', 'software'),
            'normal',
            'high'
        );
        
        // Metabox para URL de audio (Podcast)
        add_meta_box(
            'mp3_url',
            __('Audio del Podcast', 'atareao-functionality'),
            array(__CLASS__, 'render_audio_url_metabox'),
            'podcast',
            'normal',
            'high'
        );
        
        
        // Metabox para número de episodio (Podcast)
        add_meta_box(
            'episode_number',
            __('Número de Episodio', 'atareao-functionality'),
            array(__CLASS__, 'render_episode_number_metabox'),
            'podcast',
            'side',
            'high'
        );
        
        // Metabox para temporada (Podcast)
        add_meta_box(
            'season',
            __('Temporada', 'atareao-functionality'),
            array(__CLASS__, 'render_season_metabox'),
            'podcast',
            'side',
            'high'
        );
        
        // Metabox para versión (Aplicaciones y Software)
        add_meta_box(
            'version',
            __('Versión', 'atareao-functionality'),
            array(__CLASS__, 'render_version_metabox'),
            array('application', 'software'),
            'side',
            'default'
        );

        // Metabox para número de capítulo
        add_meta_box(
            'numero_capitulo',
            __('Número de Capítulo', 'atareao-functionality'),
            array(__CLASS__, 'render_numero_capitulo_metabox'),
            'capitulo',
            'side',
            'high'
        );

        // Metabox para ID del tutorial
        add_meta_box(
            'tutorial_id',
            __('Tutorial', 'atareao-functionality'),
            array(__CLASS__, 'render_tutorial_id_metabox'),
            'capitulo',
            'side',
            'high'
        );

        // Metabox para mostrar vistas en varios tipos
        $view_types = array('post', 'podcast', 'capitulo', 'tutorial', 'aplicacion', 'application', 'software');
        add_meta_box(
            'post_views',
            __('Vistas', 'atareao-functionality'),
            array(__CLASS__, 'render_post_views_metabox'),
            $view_types,
            'side',
            'default'
        );

        // Evitar duplicados mostrados por el metabox de "Custom Fields" en 'capitulo'
        // (quita la caja por defecto que lista todas las metadatos)
        remove_meta_box('postcustom', 'capitulo', 'normal');
        // Quitar también la caja de "Custom Fields" para otros CPT gestionados por el plugin
        //remove_meta_box('postcustom', 'podcast', 'normal');
        remove_meta_box('postcustom', 'tutorial', 'normal');
        remove_meta_box('postcustom', 'aplicacion', 'normal');
        remove_meta_box('postcustom', 'application', 'normal');
        remove_meta_box('postcustom', 'software', 'normal');
    }
    
    /**
     * Renderizar metabox de URL de descarga
     */
    public static function render_download_url_metabox($post) {
        wp_nonce_field('download_url_nonce', 'download_url_nonce_field');
        
        $value = get_post_meta($post->ID, '_download_url', true);
        
        echo '<label for="download_url">';
        _e('URL de descarga:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="url" id="download_url" name="download_url" value="' . esc_attr($value) . '" style="width: 100%;" />';
        echo '<p class="description">' . __('Introduce la URL desde donde se puede descargar la aplicación o software.', 'atareao-functionality') . '</p>';
    }
    
    /**
     * Renderizar metabox de URL de repositorio
     */
    public static function render_repository_url_metabox($post) {
        wp_nonce_field('repository_url_nonce', 'repository_url_nonce_field');
        
        $value = get_post_meta($post->ID, '_repository_url', true);
        
        echo '<label for="repository_url">';
        _e('URL del repositorio:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="url" id="repository_url" name="repository_url" value="' . esc_attr($value) . '" style="width: 100%;" />';
        echo '<p class="description">' . __('Introduce la URL del repositorio (GitHub, GitLab, etc.).', 'atareao-functionality') . '</p>';
    }
    
    /**
     * Renderizar metabox de URL de audio MP3
     */
    public static function render_audio_url_metabox($post) {
        wp_nonce_field('mp3_url_nonce', 'mp3_url_nonce_field');

        $value = get_post_meta($post->ID, 'mp3-url', true);

        echo '<label for="mp3_url">';
        _e('URL del archivo de audio:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="url" id="mp3_url" name="mp3-url" value="' . esc_attr($value) . '" style="width: 100%;" />';
        echo '<p class="description">' . __('Introduce la URL del archivo MP3 u otro formato de audio.', 'atareao-functionality') . '</p>';

        // Mostrar reproductor si hay URL
        if ($value) {
            echo '<div style="margin-top: 15px;">';
            echo '<audio controls style="width: 100%;">';
            echo '<source src="' . esc_url($value) . '" type="audio/mpeg">';
            echo __('Tu navegador no soporta el elemento de audio.', 'atareao-functionality');
            echo '</audio>';
            echo '</div>';
        }
    }
    
    
    /**
     * Renderizar metabox de número de episodio
     */
    public static function render_episode_number_metabox($post) {
        wp_nonce_field('episode_number_nonce', 'episode_number_nonce_field');
        
        $value = get_post_meta($post->ID, 'number', true);

        if (empty($value)) {
            $next = self::get_next_podcast_number($post->ID);
            $value = $next;
        }

        echo '<label for="episode_number">';
        _e('Número:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="text" id="episode_number" name="episode_number" value="' . esc_attr($value) . '" style="width: 100%;" placeholder="#123" />';
        echo '<p class="description">' . __('Número del episodio (ej: 123, #123, Ep. 45, etc.)', 'atareao-functionality') . '</p>';
    }

    /**
     * Obtener el siguiente número de episodio (max existente + 1)
     * Excluye opcionalmente un post por ID (útil al editar)
     */
    public static function get_next_podcast_number($exclude_id = 0) {
        $args = array(
            'post_type'      => 'podcast',
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'private', 'draft', 'pending', 'future'),
            'fields'         => 'ids',
        );

        $posts = get_posts($args);
        $max = 0;
        foreach ($posts as $pid) {
            if ($exclude_id && $pid == $exclude_id) {
                continue;
            }
            $meta = get_post_meta($pid, 'number', true);
            if (empty($meta)) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', $meta);
            if ($digits === '') {
                continue;
            }
            $num = intval($digits);
            if ($num > $max) {
                $max = $num;
            }
        }

        return $max + 1;
    }

    /**
     * Obtener el siguiente número de capítulo para un tutorial dado (max + 1).
     * Busca posts del tipo 'capitulo' que pertenezcan al tutorial y calcula
     * el siguiente número a partir de la meta 'numero-capitulo'.
     */
    public static function get_next_numero_capitulo($tutorial_id, $exclude_id = 0) {
        if (empty($tutorial_id)) {
            return 1;
        }

        $args = array(
            'post_type'      => 'capitulo',
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'private', 'draft', 'pending', 'future'),
            'meta_key'       => 'tutorial-id',
            'meta_value'     => $tutorial_id,
            'fields'         => 'ids',
        );

        $posts = get_posts($args);
        $max = 0;
        foreach ($posts as $pid) {
            if ($exclude_id && $pid == $exclude_id) {
                continue;
            }
            $meta = get_post_meta($pid, 'numero-capitulo', true);
            if (empty($meta)) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', $meta);
            if ($digits === '') {
                continue;
            }
            $num = intval($digits);
            if ($num > $max) {
                $max = $num;
            }
        }

        return $max + 1;
    }

    /**
     * Calcular la temporada actual basada en el año y la fecha.
     * Una nueva temporada comienza el 1 de septiembre.
     * Fórmula: season = (current_year - BASE_YEAR) + (month >= 9 ? 1 : 0)
     * BASE_YEAR está fijado en 2018 para que, por ejemplo, marzo 2026 => temporada 8.
     */
    public static function get_current_season($exclude_id = 0) {
        $base_year = 2018;
        $year = intval(date('Y'));
        $month = intval(date('n'));

        $season = ($year - $base_year) + ($month >= 9 ? 1 : 0);
        if ($season < 1) {
            $season = 1;
        }

        return $season;
    }
    
    /**
     * Renderizar metabox de temporada
     */
    public static function render_season_metabox($post) {
        wp_nonce_field('season_nonce', 'season_nonce_field');
        
        $value = get_post_meta($post->ID, 'season', true);

        if (empty($value)) {
            $value = self::get_current_season($post->ID);
        }

        echo '<label for="season">';
        _e('Temporada:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="text" id="season" name="season" value="' . esc_attr($value) . '" style="width: 100%;" placeholder="1" />';
        echo '<p class="description">' . __('Número de temporada (1, 2, 3, etc.)', 'atareao-functionality') . '</p>';
    }
    
    /**
     * Renderizar metabox de versión
     */
    public static function render_version_metabox($post) {
        wp_nonce_field('version_nonce', 'version_nonce_field');
        
        $value = get_post_meta($post->ID, '_version', true);
        
        echo '<label for="version">';
        _e('Versión:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="text" id="version" name="version" value="' . esc_attr($value) . '" style="width: 100%;" placeholder="1.0.0" />';
        echo '<p class="description">' . __('Versión actual del software/aplicación.', 'atareao-functionality') . '</p>';
    }

    /**
     * Renderizar metabox de número de capítulo
     */
    public static function render_numero_capitulo_metabox($post) {
        wp_nonce_field('numero_capitulo_nonce', 'numero_capitulo_nonce_field');

        $value = get_post_meta($post->ID, 'numero-capitulo', true);

        if (empty($value)) {
            $tutorial_id = get_post_meta($post->ID, 'tutorial-id', true);
            if (!empty($tutorial_id)) {
                $value = self::get_next_numero_capitulo($tutorial_id, $post->ID);
            }
        }

        echo '<label for="numero_capitulo">';
        _e('Número:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="text" id="numero_capitulo" name="numero_capitulo" value="' . esc_attr($value) . '" style="width: 100%;" placeholder="1" />';
        echo '<p class="description">' . __('Número de capítulo dentro del tutorial.', 'atareao-functionality') . '</p>';
    }

    /**
     * Renderizar metabox de ID de tutorial
     */
    public static function render_tutorial_id_metabox($post) {
        wp_nonce_field('tutorial_id_nonce', 'tutorial_id_nonce_field');

        $value = get_post_meta($post->ID, 'tutorial-id', true);

        // Listar tutoriales disponibles como select
        $tutorials = get_posts(array(
            'post_type'      => 'tutorial',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ));

        echo '<label for="tutorial_id">';
        _e('Tutorial:', 'atareao-functionality');
        echo '</label><br>';
        echo '<select id="tutorial_id" name="tutorial_id" style="width: 100%;">';
        echo '<option value="">' . __('— Selecciona un tutorial —', 'atareao-functionality') . '</option>';
        foreach ($tutorials as $tutorial) {
            $selected = selected($value, $tutorial->ID, false);
            echo '<option value="' . esc_attr($tutorial->ID) . '"' . $selected . '>' . esc_html($tutorial->post_title) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Tutorial al que pertenece este capítulo.', 'atareao-functionality') . '</p>';
    }

    /**
     * Renderizar metabox que muestra las vistas del post
     */
    public static function render_post_views_metabox($post) {
        // Use the single post meta value; do not normalize or alter DB here.
        $single = get_post_meta($post->ID, 'post_views_count', true);
        $digits = preg_replace('/\D+/', '', (string) $single);
        $count = ($digits === '') ? 0 : intval($digits);

        echo '<p>' . __('Vistas totales:', 'atareao-functionality') . ' <strong>' . esc_html($count) . '</strong></p>';

        if (current_user_can('manage_options')) {
            echo '<p class="description">' . __('Raw meta:', 'atareao-functionality') . ' ' . esc_html($single) . '</p>';
        }
    }

    /**
     * AJAX handler: return next numero-capitulo for a tutorial
     */
    public static function ajax_get_next_numero_capitulo() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('forbidden', 403);
        }

        $tutorial_id = isset($_POST['tutorial_id']) ? intval($_POST['tutorial_id']) : 0;
        $exclude_id = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : 0;

        if (empty($tutorial_id)) {
            wp_send_json_error('missing_tutorial', 400);
        }

        $next = self::get_next_numero_capitulo($tutorial_id, $exclude_id);
        wp_send_json_success(array('next' => $next));
    }

    /**
     * Enqueue admin JS on post edit screens to auto-update numero-capitulo
     */
    public static function enqueue_admin_edit_scripts($hook) {
        // Only on post edit/new screens
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : null;
        // If not provided via GET, try global post
        if (!$post_type) {
            global $post;
            $post_type = isset($post->post_type) ? $post->post_type : null;
        }
        if ($post_type !== 'capitulo') {
            return;
        }

        wp_register_script('atareao-capitulo-edit', '', array('jquery'), false, true);
        wp_enqueue_script('atareao-capitulo-edit');
        $inline = <<<'JS'
jQuery(function($){
    $(document).on('change', '#tutorial_id', function(){
        var tutorial = $(this).val();
        var post_id = $('#post_ID').val() || '';
        if (!tutorial) { return; }
        $.post(ajaxurl, { action: 'atareao_get_next_numero_capitulo', tutorial_id: tutorial, exclude_id: post_id }, function(resp){
            if (resp && resp.success && resp.data.next) {
                var $num = $('#numero_capitulo');
                if ($num.length) { $num.val(resp.data.next); }
            }
        });
    });
});
JS;

        wp_add_inline_script('atareao-capitulo-edit', $inline);
    }

    /**
     * Contador de visitas: incrementa `post_views` en visitas front-end.
     * Evita contar múltiples veces usando una cookie por 12 horas.
     */
    public static function count_post_views() {
        if (is_admin()) {
            return;
        }
        if (!is_singular()) {
            return;
        }
        if (is_preview()) {
            return;
        }

        global $post;
        if (empty($post) || empty($post->ID)) {
            return;
        }

        $post_id = $post->ID;
        $cookie_name = 'atareao_post_view_' . $post_id;

        if (isset($_COOKIE[$cookie_name])) {
            return;
        }

        // Use single post meta value; do not attempt to normalize multiple entries.
        $single = get_post_meta($post_id, 'post_views_count', true);
        $digits = preg_replace('/\D+/', '', (string) $single);
        $count = ($digits === '') ? 0 : intval($digits);
        $count++;
        update_post_meta($post_id, 'post_views_count', $count);

        // Establecer cookie para evitar múltiples conteos (12 horas)
        $expire = time() + 12 * 3600;
        setcookie($cookie_name, '1', $expire, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
        // También setear en $_COOKIE para la misma petición
        $_COOKIE[$cookie_name] = '1';
    }

    /**
     * Añadir columna de vistas a las listas de posts para tipos comunes.
     */
    public static function register_views_admin_hooks() {
        $types = array('post', 'podcast', 'capitulo', 'tutorial', 'aplicacion', 'application', 'software');
        foreach ($types as $type) {
            add_filter("manage_{$type}_posts_columns", array(__CLASS__, 'add_views_column'));
            add_action("manage_{$type}_posts_custom_column", array(__CLASS__, 'render_views_column'), 10, 2);
            add_filter("manage_edit-{$type}_sortable_columns", array(__CLASS__, 'make_views_sortable'));
        }

        // Ordenación por meta post_views_count
        add_action('pre_get_posts', array(__CLASS__, 'views_pre_get_posts'));
    }

    public static function add_views_column($columns) {
        // Insert after title
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['post_views_count'] = __('Vistas', 'atareao-functionality');
            }
        }
        if (!isset($new['post_views_count'])) {
            $new['post_views_count'] = __('Vistas', 'atareao-functionality');
        }
        return $new;
    }

    public static function render_views_column($column, $post_id) {
        if ($column !== 'post_views_count') {
            return;
        }
        // Use only the single post meta value
        $single = get_post_meta($post_id, 'post_views_count', true);
        $digits = preg_replace('/\D+/', '', (string) $single);
        $count = ($digits === '') ? 0 : intval($digits);
        echo esc_html($count);
    }

    /**
     * Append an eye + views count to the post title on single views for supported types.
     */
    public static function the_title_with_views($title, $post_id) {
        if (is_admin()) {
            return $title;
        }

        // Only on singular views and when the title belongs to the main queried post
        if (!is_singular()) {
            return $title;
        }
        global $post;
        if (empty($post) || $post->ID !== $post_id) {
            return $title;
        }

        $types = array('post', 'podcast', 'capitulo', 'tutorial', 'aplicacion', 'application', 'software');
        $pt = get_post_type($post_id);
        if (!in_array($pt, $types, true)) {
            return $title;
        }

        $single = get_post_meta($post_id, 'post_views_count', true);
        $digits = preg_replace('/\D+/', '', (string) $single);
        $count = ($digits === '') ? 0 : intval($digits);

        $icon = '<svg class="atareao-views-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5c-7 0-11 6-11 7s4 7 11 7 11-6 11-7-4-7-11-7zm0 11a4 4 0 1 1 .001-8.001A4 4 0 0 1 12 16zm0-6a2 2 0 1 0 .001 4.001A2 2 0 0 0 12 10z"/></svg>';
        $aria_label = sprintf(__("Vistas: %d", 'atareao-functionality'), $count);
        $html = ' <span class="atareao-views" role="status" aria-label="' . esc_attr($aria_label) . '">'
            . '<span class="atareao-views-inner">' . $icon . '<span class="atareao-views-count" aria-hidden="true">' . esc_html($count) . '</span></span>'
            . '<span class="screen-reader-text">' . esc_html($aria_label) . '</span>'
            . '</span>';

        return $title . $html;
    }

    // Frontend styles/scripts for views are intentionally not enqueued by the plugin.
    // Styles have been moved to the theme's style.css and DOM-moving JS should live in theme assets.

    public static function make_views_sortable($columns) {
        $columns['post_views_count'] = 'post_views_count';
        return $columns;
    }

    public static function views_pre_get_posts($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        $orderby = $query->get('orderby');
        if ($orderby === 'post_views_count') {
            $query->set('meta_key', 'post_views_count');
            $query->set('orderby', 'meta_value_num');
        }
    }
    
    /**
     * Guardar metaboxes
     */
    public static function save_metaboxes($post_id) {
        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar URL de descarga
        if (isset($_POST['download_url_nonce_field']) && 
            wp_verify_nonce($_POST['download_url_nonce_field'], 'download_url_nonce')) {
            if (isset($_POST['download_url'])) {
                update_post_meta($post_id, '_download_url', esc_url_raw($_POST['download_url']));
            }
        }
        
        // Guardar URL de repositorio
        if (isset($_POST['repository_url_nonce_field']) && 
            wp_verify_nonce($_POST['repository_url_nonce_field'], 'repository_url_nonce')) {
            if (isset($_POST['repository_url'])) {
                update_post_meta($post_id, '_repository_url', esc_url_raw($_POST['repository_url']));
            }
        }
        
        // Guardar URL de audio MP3
        if (isset($_POST['mp3_url_nonce_field']) && 
            wp_verify_nonce($_POST['mp3_url_nonce_field'], 'mp3_url_nonce')) {
            if (isset($_POST['mp3-url'])) {
                update_post_meta($post_id, 'mp3-url', esc_url_raw($_POST['mp3-url']));
            }
        }
        
        
        
        // Guardar número de episodio
        if (isset($_POST['episode_number_nonce_field']) && 
            wp_verify_nonce($_POST['episode_number_nonce_field'], 'episode_number_nonce')) {
            if (isset($_POST['episode_number'])) {
                $posted = sanitize_text_field($_POST['episode_number']);

                if (trim($posted) === '') {
                    $next = self::get_next_podcast_number($post_id);
                    update_post_meta($post_id, 'number', (string) $next);
                } else {
                    update_post_meta($post_id, 'number', $posted);
                }
            } else {
                // If field not present at all, still ensure a number for new posts
                $next = self::get_next_podcast_number($post_id);
                update_post_meta($post_id, 'number', (string) $next);
            }
        }
        
        // Guardar temporada
        if (isset($_POST['season_nonce_field']) && 
            wp_verify_nonce($_POST['season_nonce_field'], 'season_nonce')) {
            if (isset($_POST['season'])) {
                $posted = sanitize_text_field($_POST['season']);

                if (trim($posted) === '') {
                    $season = self::get_current_season($post_id);
                    update_post_meta($post_id, 'season', (string) $season);
                } else {
                    update_post_meta($post_id, 'season', $posted);
                }
            } else {
                // Field missing: ensure we set a sensible default season
                $season = self::get_current_season($post_id);
                update_post_meta($post_id, 'season', (string) $season);
            }
        }
        
        // Guardar versión
        if (isset($_POST['version_nonce_field']) && 
            wp_verify_nonce($_POST['version_nonce_field'], 'version_nonce')) {
            if (isset($_POST['version'])) {
                update_post_meta($post_id, '_version', sanitize_text_field($_POST['version']));
            }
        }

        // Guardar número de capítulo
        if (isset($_POST['numero_capitulo_nonce_field']) &&
            wp_verify_nonce($_POST['numero_capitulo_nonce_field'], 'numero_capitulo_nonce')) {
            if (isset($_POST['numero_capitulo'])) {
                update_post_meta($post_id, 'numero-capitulo', sanitize_text_field($_POST['numero_capitulo']));
            }
        }

        // Guardar ID de tutorial
        if (isset($_POST['tutorial_id_nonce_field']) &&
            wp_verify_nonce($_POST['tutorial_id_nonce_field'], 'tutorial_id_nonce')) {
            if (isset($_POST['tutorial_id'])) {
                update_post_meta($post_id, 'tutorial-id', sanitize_text_field($_POST['tutorial_id']));
            }
        }

        // Si es un 'capitulo' y no tiene 'numero-capitulo', asignar el siguiente
        if (get_post_type($post_id) === 'capitulo') {
            $current = get_post_meta($post_id, 'numero-capitulo', true);
            if (empty($current)) {
                $tutorial_id = '';
                if (isset($_POST['tutorial_id'])) {
                    $tutorial_id = sanitize_text_field($_POST['tutorial_id']);
                }
                if (empty($tutorial_id)) {
                    $tutorial_id = get_post_meta($post_id, 'tutorial-id', true);
                }

                if (!empty($tutorial_id)) {
                    $next = self::get_next_numero_capitulo($tutorial_id, $post_id);
                    update_post_meta($post_id, 'numero-capitulo', (string) $next);
                }
            }
        }
    }
}
