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
        
        // Registrar metadatos en REST API
        add_action('init', array(__CLASS__, 'register_meta_fields'));
    }
    
    /**
     * Registrar campos meta en REST API
     */
    public static function register_meta_fields() {
        // Registrar mp3-url para acceso desde el editor de bloques
        register_post_meta('podcast', 'mp3-url', array(
            'type' => 'string',
            'description' => __('URL del archivo MP3', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        // Registrar _duration para REST API
        register_post_meta('podcast', '_duration', array(
            'type' => 'string',
            'description' => __('Duración del podcast', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        // Registrar episode_number para REST API
        register_post_meta('podcast', 'number', array(
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
            'audio_url',
            __('Audio del Podcast', 'atareao-functionality'),
            array(__CLASS__, 'render_audio_url_metabox'),
            'podcast',
            'normal',
            'high'
        );
        
        // Metabox para duración (Podcast)
        add_meta_box(
            'duration',
            __('Duración', 'atareao-functionality'),
            array(__CLASS__, 'render_duration_metabox'),
            'podcast',
            'side',
            'default'
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
        wp_nonce_field('audio_url_nonce', 'audio_url_nonce_field');
        
        $value = get_post_meta($post->ID, 'mp3-url', true);
        
        echo '<label for="audio_url">';
        _e('URL del archivo de audio:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="url" id="audio_url" name="audio_url" value="' . esc_attr($value) . '" style="width: 100%;" />';
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
     * Renderizar metabox de duración
     */
    public static function render_duration_metabox($post) {
        wp_nonce_field('duration_nonce', 'duration_nonce_field');
        
        $value = get_post_meta($post->ID, '_duration', true);
        
        echo '<label for="duration">';
        _e('Duración:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="text" id="duration" name="duration" value="' . esc_attr($value) . '" style="width: 100%;" placeholder="00:00:00" />';
        echo '<p class="description">' . __('Formato: HH:MM:SS (ej: 01:23:45)', 'atareao-functionality') . '</p>';
    }
    
    /**
     * Renderizar metabox de número de episodio
     */
    public static function render_episode_number_metabox($post) {
        wp_nonce_field('episode_number_nonce', 'episode_number_nonce_field');
        
        $value = get_post_meta($post->ID, 'number', true);
        
        echo '<label for="episode_number">';
        _e('Número:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="text" id="episode_number" name="episode_number" value="' . esc_attr($value) . '" style="width: 100%;" placeholder="#123" />';
        echo '<p class="description">' . __('Número del episodio (ej: #123, Ep. 45, etc.)', 'atareao-functionality') . '</p>';
    }
    
    /**
     * Renderizar metabox de temporada
     */
    public static function render_season_metabox($post) {
        wp_nonce_field('season_nonce', 'season_nonce_field');
        
        $value = get_post_meta($post->ID, 'season', true);
        
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
        if (isset($_POST['audio_url_nonce_field']) && 
            wp_verify_nonce($_POST['audio_url_nonce_field'], 'audio_url_nonce')) {
            if (isset($_POST['audio_url'])) {
                update_post_meta($post_id, 'mp3-url', esc_url_raw($_POST['audio_url']));
            }
        }
        
        // Guardar duración
        if (isset($_POST['duration_nonce_field']) && 
            wp_verify_nonce($_POST['duration_nonce_field'], 'duration_nonce')) {
            if (isset($_POST['duration'])) {
                update_post_meta($post_id, '_duration', sanitize_text_field($_POST['duration']));
            }
        }
        
        // Guardar número de episodio
        if (isset($_POST['episode_number_nonce_field']) && 
            wp_verify_nonce($_POST['episode_number_nonce_field'], 'episode_number_nonce')) {
            if (isset($_POST['episode_number'])) {
                update_post_meta($post_id, 'number', sanitize_text_field($_POST['episode_number']));
            }
        }
        
        // Guardar temporada
        if (isset($_POST['season_nonce_field']) && 
            wp_verify_nonce($_POST['season_nonce_field'], 'season_nonce')) {
            if (isset($_POST['season'])) {
                update_post_meta($post_id, 'season', sanitize_text_field($_POST['season']));
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
    }
}
