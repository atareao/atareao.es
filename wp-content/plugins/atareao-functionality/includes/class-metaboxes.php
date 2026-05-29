<?php
/**
 * Metaboxes personalizados
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class Metaboxes
{

    /**
     * Inicializar
     */
    public static function init()
    {
        add_action('add_meta_boxes', array(__CLASS__, 'addMetaboxes'));
        add_action('save_post', array(__CLASS__, 'saveMetaboxes'));
        add_action('save_post_podcast', array(__CLASS__, 'clearPodcastSeasonCache'));
        add_action('wp_ajax_atareao_get_next_numero_capitulo', array(__CLASS__, 'ajaxGetNextNumeroCapitulo'));
        add_action('wp_ajax_atareao_track_view', array(__CLASS__, 'handleTrackViewAjax'));
        add_action('wp_ajax_nopriv_atareao_track_view', array(__CLASS__, 'handleTrackViewAjax'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueueAdminEditScripts'));

        add_action('init', array(__CLASS__, 'registerMetaFields'));
        add_action('admin_init', array(__CLASS__, 'registerViewsAdminHooks'));
        add_filter('the_title', array(__CLASS__, 'theTitleWithViews'), 10, 2);
        add_action('rest_api_init', array(__CLASS__, 'registerRestFields'));
    }

    public static function registerRestFields()
    {
        register_rest_field(
            'podcast',
            'all_metadata',
            array(
                'get_callback' => function ($post_array) {
                    return get_post_meta($post_array['id']);
                },
                'schema' => null,
            )
        );
    }

    /**
     * Registrar campos meta en REST API
     */
    public static function registerMetaFields()
    {
        register_rest_field(
            'podcast',
            'metadata',
            array(
                'get_callback' => function ($data) {
                    return get_post_meta($data['id'], '', '');
                },
            )
        );

        register_post_meta('podcast', 'mp3-url', array(
            'object_subtype' => 'podcast',
            'type' => 'string',
            'description' => __('URL del archivo MP3', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        register_post_meta('podcast', 'number', array(
            'object_subtype' => 'podcast',
            'type' => 'string',
            'description' => __('Número de episodio', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_post_meta('podcast', 'season', array(
            'type' => 'string',
            'description' => __('Temporada', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_post_meta('capitulo', 'numero-capitulo', array(
            'type' => 'string',
            'description' => __('Número de capítulo', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_post_meta('capitulo', 'tutorial-id', array(
            'type' => 'string',
            'description' => __('ID del tutorial al que pertenece', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ));

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

        $app_types = array('application', 'software');
        register_post_meta($app_types, '_download_url', array(
            'type' => 'string',
            'description' => __('URL de descarga (meta interno)', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        register_post_meta($app_types, '_repository_url', array(
            'type' => 'string',
            'description' => __('URL del repositorio (meta interno)', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        register_post_meta($app_types, '_version', array(
            'type' => 'string',
            'description' => __('Versión (meta interno)', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }

    /**
     * Añadir metaboxes
     */
    public static function addMetaboxes()
    {
        add_meta_box(
            'download_url',
            __('URL de Descarga', 'atareao-functionality'),
            array(__CLASS__, 'renderDownloadUrlMetabox'),
            array('application', 'software'),
            'normal',
            'high'
        );

        add_meta_box(
            'repository_url',
            __('Repositorio', 'atareao-functionality'),
            array(__CLASS__, 'renderRepositoryUrlMetabox'),
            array('application', 'software'),
            'normal',
            'high'
        );

        add_meta_box(
            'mp3_url',
            __('Audio del Podcast', 'atareao-functionality'),
            array(__CLASS__, 'renderAudioUrlMetabox'),
            'podcast',
            'normal',
            'high'
        );

        add_meta_box(
            'episode_number',
            __('Número de Episodio', 'atareao-functionality'),
            array(__CLASS__, 'renderEpisodeNumberMetabox'),
            'podcast',
            'side',
            'high'
        );

        add_meta_box(
            'season',
            __('Temporada', 'atareao-functionality'),
            array(__CLASS__, 'renderSeasonMetabox'),
            'podcast',
            'side',
            'high'
        );

        add_meta_box(
            'version',
            __('Versión', 'atareao-functionality'),
            array(__CLASS__, 'renderVersionMetabox'),
            array('application', 'software'),
            'side',
            'default'
        );

        add_meta_box(
            'numero_capitulo',
            __('Número de Capítulo', 'atareao-functionality'),
            array(__CLASS__, 'renderNumeroCapituloMetabox'),
            'capitulo',
            'side',
            'high'
        );

        add_meta_box(
            'tutorial_id',
            __('Tutorial', 'atareao-functionality'),
            array(__CLASS__, 'renderTutorialIdMetabox'),
            'capitulo',
            'side',
            'high'
        );

        $view_types = array('post', 'podcast', 'capitulo', 'tutorial', 'aplicacion', 'application', 'software');
        add_meta_box(
            'post_views',
            __('Vistas', 'atareao-functionality'),
            array(__CLASS__, 'renderPostViewsMetabox'),
            $view_types,
            'side',
            'default'
        );

        remove_meta_box('postcustom', 'capitulo', 'normal');
        remove_meta_box('postcustom', 'tutorial', 'normal');
        remove_meta_box('postcustom', 'aplicacion', 'normal');
        remove_meta_box('postcustom', 'application', 'normal');
        remove_meta_box('postcustom', 'software', 'normal');
    }

    /**
     * Renderizar metabox de URL de descarga
     */
    public static function renderDownloadUrlMetabox($post)
    {
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
    public static function renderRepositoryUrlMetabox($post)
    {
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
    public static function renderAudioUrlMetabox($post)
    {
        wp_nonce_field('mp3_url_nonce', 'mp3_url_nonce_field');

        $value = get_post_meta($post->ID, 'mp3-url', true);

        echo '<label for="mp3_url">';
        _e('URL del archivo de audio:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="url" id="mp3_url" name="mp3-url" value="' . esc_attr($value) . '" style="width: 100%;" />';
        echo '<p class="description">' . __('Introduce la URL del archivo MP3 u otro formato de audio.', 'atareao-functionality') . '</p>';

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
    public static function renderEpisodeNumberMetabox($post)
    {
        wp_nonce_field('episode_number_nonce', 'episode_number_nonce_field');

        $value = get_post_meta($post->ID, 'number', true);

        if (empty($value)) {
            $next = self::getNextPodcastNumber($post->ID);
            $value = $next;
        }

        echo '<label for="episode_number">';
        _e('Número:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="text" id="episode_number" name="episode_number" value="' . esc_attr($value) . '" style="width: 100%;" placeholder="#123" />';
        echo '<p class="description">' . __('Número del episodio (ej: 123, #123, Ep. 45, etc.)', 'atareao-functionality') . '</p>';
    }

    /**
     * Obtener el siguiente número de episodio
     */
    public static function getNextPodcastNumber($exclude_id = 0)
    {
        global $wpdb;
        $max = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(REPLACE(pm.meta_value, '#', '') AS UNSIGNED))
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'number'
            AND p.post_type = 'podcast'
            AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'future')
            AND p.ID != %d",
            $exclude_id ?: 0
        ));
        return intval($max) + 1;
    }

    /**
     * Obtener el siguiente número de capítulo para un tutorial dado
     */
    public static function getNextNumeroCapitulo($tutorial_id, $exclude_id = 0)
    {
        if (empty($tutorial_id)) {
            return 1;
        }

        global $wpdb;
        $max = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(pm2.meta_value AS UNSIGNED))
            FROM {$wpdb->postmeta} pm1
            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
            INNER JOIN {$wpdb->posts} p ON p.ID = pm1.post_id
            WHERE pm1.meta_key = 'tutorial-id'
            AND pm1.meta_value = %s
            AND pm2.meta_key = 'numero-capitulo'
            AND p.post_type = 'capitulo'
            AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'future')
            AND p.ID != %d",
            (string) $tutorial_id,
            $exclude_id ?: 0
        ));
        return intval($max) + 1;
    }

    /**
     * Calcular la temporada actual basada en el año y la fecha
     */
    public static function getCurrentSeason($exclude_id = 0)
    {
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
    public static function renderSeasonMetabox($post)
    {
        wp_nonce_field('season_nonce', 'season_nonce_field');

        $value = get_post_meta($post->ID, 'season', true);

        if (empty($value)) {
            $value = self::getCurrentSeason($post->ID);
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
    public static function renderVersionMetabox($post)
    {
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
    public static function renderNumeroCapituloMetabox($post)
    {
        wp_nonce_field('numero_capitulo_nonce', 'numero_capitulo_nonce_field');

        $value = get_post_meta($post->ID, 'numero-capitulo', true);

        if (empty($value)) {
            $tutorial_id = get_post_meta($post->ID, 'tutorial-id', true);
            if (!empty($tutorial_id)) {
                $value = self::getNextNumeroCapitulo($tutorial_id, $post->ID);
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
    public static function renderTutorialIdMetabox($post)
    {
        wp_nonce_field('tutorial_id_nonce', 'tutorial_id_nonce_field');

        $value = get_post_meta($post->ID, 'tutorial-id', true);

        $tutorials = get_posts(array(
            'post_type'      => 'tutorial',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ));

        echo '<label for="tutorial_id">';
        _e('Tutorial:', 'atareao-functionality');
        echo '</label><br>';
        echo '<select id="tutorial_id" name="tutorial_id" style="width: 100%;">';
        echo '<option value="">' . __('— Selecciona un tutorial —', 'atareao-functionality') . '</option>';
        foreach ($tutorials as $tid) {
            $selected = selected($value, $tid, false);
            echo '<option value="' . esc_attr($tid) . '"' . $selected . '>' . esc_html(get_the_title($tid)) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Tutorial al que pertenece este capítulo.', 'atareao-functionality') . '</p>';
    }

    /**
     * Renderizar metabox que muestra las vistas del post
     */
    public static function renderPostViewsMetabox($post)
    {
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
    public static function ajaxGetNextNumeroCapitulo()
    {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('forbidden', 403);
        }

        $tutorial_id = isset($_POST['tutorial_id']) ? intval($_POST['tutorial_id']) : 0;
        $exclude_id = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : 0;

        if (empty($tutorial_id)) {
            wp_send_json_error('missing_tutorial', 400);
        }

        $next = self::getNextNumeroCapitulo($tutorial_id, $exclude_id);
        wp_send_json_success(array('next' => $next));
    }

    /**
     * Enqueue admin JS on post edit screens
     */
    public static function enqueueAdminEditScripts($hook)
    {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : null;
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
     * AJAX handler: track a post view asynchronously
     */
    public static function handleTrackViewAjax()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'atareao_track_view_nonce')) {
            wp_send_json_error('invalid_nonce', 403);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('missing_post_id', 400);
        }

        $post = get_post($post_id);
        if (!$post || !is_singular($post->post_type)) {
            wp_send_json_error('invalid_post', 400);
        }

        $cookie_name = 'atareao_post_view_' . $post_id;
        if (isset($_COOKIE[$cookie_name])) {
            wp_send_json_success(array('cached' => true));
        }

        $single = get_post_meta($post_id, 'post_views_count', true);
        $digits = preg_replace('/\D+/', '', (string) $single);
        $count = ($digits === '') ? 0 : intval($digits);
        $count++;
        update_post_meta($post_id, 'post_views_count', $count);

        $expire = time() + 12 * 3600;
        setcookie($cookie_name, '1', $expire, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);

        wp_send_json_success(array('views' => $count));
    }

    /**
     * Añadir columna de vistas a las listas de posts
     */
    public static function registerViewsAdminHooks()
    {
        $types = array('post', 'podcast', 'capitulo', 'tutorial', 'aplicacion', 'application', 'software');
        foreach ($types as $type) {
            add_filter("manage_{$type}_posts_columns", array(__CLASS__, 'addViewsColumn'));
            add_action("manage_{$type}_posts_custom_column", array(__CLASS__, 'renderViewsColumn'), 10, 2);
            add_filter("manage_edit-{$type}_sortable_columns", array(__CLASS__, 'makeViewsSortable'));
        }

        add_action('pre_get_posts', array(__CLASS__, 'viewsPreGetPosts'));
    }

    public static function addViewsColumn($columns)
    {
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

    public static function renderViewsColumn($column, $post_id)
    {
        if ($column !== 'post_views_count') {
            return;
        }
        $single = get_post_meta($post_id, 'post_views_count', true);
        $digits = preg_replace('/\D+/', '', (string) $single);
        $count = ($digits === '') ? 0 : intval($digits);
        echo esc_html($count);
    }

    /**
     * Append views count to the post title on single views
     */
    public static function theTitleWithViews($title, $post_id)
    {
        if (is_admin()) {
            return $title;
        }

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

    public static function makeViewsSortable($columns)
    {
        $columns['post_views_count'] = 'post_views_count';
        return $columns;
    }

    public static function viewsPreGetPosts($query)
    {
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
     * Limpiar cache de temporadas al guardar un podcast
     */
    public static function clearPodcastSeasonCache($post_id)
    {
        delete_transient('atareao_podcast_seasons');
    }

    /**
     * Guardar metaboxes
     */
    public static function saveMetaboxes($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['download_url_nonce_field']) &&
            wp_verify_nonce($_POST['download_url_nonce_field'], 'download_url_nonce')) {
            if (isset($_POST['download_url'])) {
                update_post_meta($post_id, '_download_url', esc_url_raw($_POST['download_url']));
            }
        }

        if (isset($_POST['repository_url_nonce_field']) &&
            wp_verify_nonce($_POST['repository_url_nonce_field'], 'repository_url_nonce')) {
            if (isset($_POST['repository_url'])) {
                update_post_meta($post_id, '_repository_url', esc_url_raw($_POST['repository_url']));
            }
        }

        if (isset($_POST['mp3_url_nonce_field']) &&
            wp_verify_nonce($_POST['mp3_url_nonce_field'], 'mp3_url_nonce')) {
            if (isset($_POST['mp3-url'])) {
                update_post_meta($post_id, 'mp3-url', esc_url_raw($_POST['mp3-url']));
            }
        }

        if (isset($_POST['episode_number_nonce_field']) &&
            wp_verify_nonce($_POST['episode_number_nonce_field'], 'episode_number_nonce')) {
            if (isset($_POST['episode_number'])) {
                $posted = sanitize_text_field($_POST['episode_number']);

                if (trim($posted) === '') {
                    $next = self::getNextPodcastNumber($post_id);
                    update_post_meta($post_id, 'number', (string) $next);
                } else {
                    update_post_meta($post_id, 'number', $posted);
                }
            } else {
                $next = self::getNextPodcastNumber($post_id);
                update_post_meta($post_id, 'number', (string) $next);
            }
        }

        if (isset($_POST['season_nonce_field']) &&
            wp_verify_nonce($_POST['season_nonce_field'], 'season_nonce')) {
            if (isset($_POST['season'])) {
                $posted = sanitize_text_field($_POST['season']);

                if (trim($posted) === '') {
                    $season = self::getCurrentSeason($post_id);
                    update_post_meta($post_id, 'season', (string) $season);
                } else {
                    update_post_meta($post_id, 'season', $posted);
                }
            } else {
                $season = self::getCurrentSeason($post_id);
                update_post_meta($post_id, 'season', (string) $season);
            }
        }

        if (isset($_POST['version_nonce_field']) &&
            wp_verify_nonce($_POST['version_nonce_field'], 'version_nonce')) {
            if (isset($_POST['version'])) {
                update_post_meta($post_id, '_version', sanitize_text_field($_POST['version']));
            }
        }

        if (isset($_POST['numero_capitulo_nonce_field']) &&
            wp_verify_nonce($_POST['numero_capitulo_nonce_field'], 'numero_capitulo_nonce')) {
            if (isset($_POST['numero_capitulo'])) {
                update_post_meta($post_id, 'numero-capitulo', sanitize_text_field($_POST['numero_capitulo']));
            }
        }

        if (isset($_POST['tutorial_id_nonce_field']) &&
            wp_verify_nonce($_POST['tutorial_id_nonce_field'], 'tutorial_id_nonce')) {
            if (isset($_POST['tutorial_id'])) {
                update_post_meta($post_id, 'tutorial-id', sanitize_text_field($_POST['tutorial_id']));
            }
        }

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
                    $next = self::getNextNumeroCapitulo($tutorial_id, $post_id);
                    update_post_meta($post_id, 'numero-capitulo', (string) $next);
                }
            }
        }
    }
}
