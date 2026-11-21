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
     * Campo del formulario que transporta el ID de la imagen del episodio.
     */
    public const EPISODE_IMAGE_FIELD = 'atareao_episode_image';

    /**
     * Campo del formulario que transporta el ID del documento de capítulos.
     */
    public const CHAPTERS_FIELD = 'atareao_podcast_chapters';

    /**
     * Campo del formulario que transporta el nonce del metabox multimedia.
     */
    public const MEDIA_NONCE_FIELD = 'atareao_podcast_media_nonce';

    /**
     * Acción del nonce del metabox multimedia.
     */
    public const MEDIA_NONCE_ACTION = 'atareao_podcast_media';

    /**
     * Slug del aviso de validación del guardado multimedia.
     */
    private const MEDIA_SETTINGS_GROUP = 'atareao_podcast_media';

    /**
     * Inicializar
     */
    public static function init()
    {
        add_action('add_meta_boxes', array(__CLASS__, 'addMetaboxes'));
        add_action('save_post', array(__CLASS__, 'saveMetaboxes'));
        add_action('save_post_podcast', array(__CLASS__, 'saveEpisodeMediaFromPost'));
        add_action('save_post_podcast', array(__CLASS__, 'clearPodcastSeasonCache'));
        add_action('wp_ajax_atareao_get_next_numero_capitulo', array(__CLASS__, 'ajaxGetNextNumeroCapitulo'));
        add_action('wp_ajax_atareao_track_view', array(__CLASS__, 'handleTrackViewAjax'));
        add_action('wp_ajax_nopriv_atareao_track_view', array(__CLASS__, 'handleTrackViewAjax'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueueAdminEditScripts'));

        // `registerMetaFields()` se engancha en `init` con prioridad 20: el
        // bootstrap del plugin corre en `init` prioridad 10, y un callback
        // añadido a la prioridad que se está procesando no llega a ejecutarse
        // (WP_Hook toma una instantánea de los callbacks por prioridad).
        add_action('init', array(__CLASS__, 'registerMetaFields'), 20);
        add_action('init', array(__CLASS__, 'registerEpisodeMediaMeta'), 20);
        add_action('admin_init', array(__CLASS__, 'registerViewsAdminHooks'));
        add_action('rest_api_init', array(__CLASS__, 'registerRestFields'));
    }

    public static function registerRestFields()
    {
        // 1. Maintain your existing custom podcast field mapping
        register_rest_field(
            'podcast',
            'all_metadata',
            array(
                'get_callback' => function ($post_array) {
                    $public_keys = array('mp3-url', 'number', 'season', 'post_views_count');
                    $public = array();
                    foreach ($public_keys as $meta_key) {
                        $value = get_post_meta($post_array['id'], $meta_key, true);
                        if ($value !== '' && $value !== null) {
                            $public[$meta_key] = $value;
                        }
                    }
                    return $public;
                },
                'schema' => null,
            )
        );
        // 2. Add the dynamic alternative mapping hook for SEO Framework descriptions
        $seo_endpoints = array('post', 'page', 'podcast', 'capitulo', 'tutorial', 'aplicacion', 'software');
        foreach ($seo_endpoints as $endpoint) {
            register_rest_field(
                $endpoint,
                'seo_description',
                array(
                    'get_callback' => function ($post_array) {
                        $description = get_post_meta($post_array['id'], '_genesis_description', true);
                        return $description ? sanitize_text_field($description) : '';
                    },
                    'schema' => array(
                        'description' => __('The SEO Framework custom meta description.', 'atareao-functionality'),
                        'type'        => 'string',
                    ),
                )
            );
        }
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
                    $public_keys = array('mp3-url', 'number', 'season', 'post_views_count');
                    $public = array();
                    foreach ($public_keys as $meta_key) {
                        $value = get_post_meta($data['id'], $meta_key, true);
                        if ($value !== '' && $value !== null) {
                            $public[$meta_key] = $value;
                        }
                    }
                    return $public;
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
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        register_post_meta('podcast', 'season', array(
            'type' => 'string',
            'description' => __('Temporada', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        /*
         * Vídeo propio del episodio (respaldo del embed en el contenido). La
         * clave vive en `SEO::YOUTUBE_URL_META` y la escribe el backfill de
         * `YoutubeVideo`; aquí solo se declara para el CPT `podcast`, el único
         * tipo de `SEO::PODCAST_EPISODE_TYPES`. NO se expone en la API REST
         * (`show_in_rest` desactivado): WordPress no aplica `auth_callback` en
         * las lecturas REST, así que con `show_in_rest` activo un anónimo
         * podría leer un metadato con URL; sin él solo lo rellena WP-CLI. El
         * saneado exige esquema `http(s)` (rechaza `ftp:`).
         */
        register_post_meta('podcast', \Atareao\SEO::YOUTUBE_URL_META, array(
            'object_subtype' => 'podcast',
            'type' => 'string',
            'description' => __('URL del vídeo del episodio en YouTube', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => array('\Atareao\SEO', 'sanitizeYoutubeUrl'),
        ));

        register_post_meta('podcast', \Atareao\PodcastFeed::TRANSCRIPT_META, array(
            'object_subtype' => 'podcast',
            'type' => 'integer',
            'description' => __('ID del adjunto con la transcripción SRT', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            // Mismo contrato que la caja y que el feed: solo el ID de un adjunto
            // positivo. Se reutiliza la única fuente de verdad del módulo del feed.
            'sanitize_callback' => array('\Atareao\PodcastFeed', 'normalizeTranscriptAttachment'),
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        register_post_meta('capitulo', 'numero-capitulo', array(
            'type' => 'string',
            'description' => __('Número de capítulo', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        register_post_meta('capitulo', 'tutorial-id', array(
            'type' => 'string',
            'description' => __('ID del tutorial al que pertenece', 'atareao-functionality'),
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        $types = array('post', 'podcast', 'capitulo', 'tutorial', 'aplicacion', 'software');
        foreach ($types as $t) {
            register_post_meta($t, 'post_views_count', array(
                'type' => 'integer',
                'description' => __('Número de visitas del post', 'atareao-functionality'),
                'single' => true,
                'show_in_rest' => true,
                // `sanitize_meta()` de core invoca el callback con CUATRO
                // argumentos ($value, $meta_key, $meta_type, $object_subtype).
                // `intval` es función interna y en PHP 8 lanza
                // `ArgumentCountError` («expects at most 2 arguments, 4 given»),
                // provocando un error fatal 500 en cada `update_post_meta()`.
                // El cierre ignora los argumentos extra y sanea a un entero no
                // negativo: un valor negativo o no numérico persiste como 0.
                'sanitize_callback' => static function ($value) {
                    return max(0, intval($value));
                },
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }

        // Metas internas (prefijo `_`): se registran por tipo string —nunca un
        // array, que core usaría como clave y provocaría un TypeError fatal— y
        // con `show_in_rest => false` para no divulgarlas por la API REST.
        $app_types = array('aplicacion', 'software');
        foreach ($app_types as $app_type) {
            register_post_meta($app_type, '_download_url', array(
                'type' => 'string',
                'description' => __('URL de descarga (meta interno)', 'atareao-functionality'),
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'esc_url_raw',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));

            register_post_meta($app_type, '_repository_url', array(
                'type' => 'string',
                'description' => __('URL del repositorio (meta interno)', 'atareao-functionality'),
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'esc_url_raw',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));

            register_post_meta($app_type, '_version', array(
                'type' => 'string',
                'description' => __('Versión (meta interno)', 'atareao-functionality'),
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }
    }

    /**
     * Registra los metadatos de la imagen y de los capítulos del episodio.
     *
     * Ambas claves guardan el ID de un adjunto (entero no negativo) y NO se
     * exponen en la API REST: la lectura pública de un metadato no pasa por
     * `auth_callback`, así que exponerlas revelaría IDs internos. El contrato
     * de las claves y del formato lo fija `\Atareao\PodcastFeed`, que es quien
     * las consume en el feed.
     */
    public static function registerEpisodeMediaMeta(): void
    {
        $keys = array(
            array(
                \Atareao\PodcastFeed::EPISODE_IMAGE_META,
                __('ID del adjunto con la imagen del episodio', 'atareao-functionality'),
            ),
            array(
                \Atareao\PodcastFeed::CHAPTERS_META,
                __('ID del adjunto con el documento de capítulos', 'atareao-functionality'),
            ),
        );

        foreach ($keys as $key) {
            list($meta_key, $description) = $key;
            register_post_meta('podcast', $meta_key, array(
                'object_subtype' => 'podcast',
                'type' => 'integer',
                'description' => $description,
                'single' => true,
                'show_in_rest' => false,
                // `sanitize_meta()` invoca el callback con cuatro argumentos;
                // `absint` es una función de usuario y los ignora sin error.
                'sanitize_callback' => 'absint',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }
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
            array('aplicacion', 'software'),
            'normal',
            'high'
        );

        add_meta_box(
            'repository_url',
            __('Repositorio', 'atareao-functionality'),
            array(__CLASS__, 'renderRepositoryUrlMetabox'),
            array('aplicacion', 'software'),
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
            'podcast_transcript',
            __('Transcripción (SRT)', 'atareao-functionality'),
            array(__CLASS__, 'renderTranscriptMetabox'),
            'podcast',
            'normal',
            'high'
        );

        add_meta_box(
            'podcast_episode_media',
            __('Multimedia del episodio', 'atareao-functionality'),
            array(__CLASS__, 'renderEpisodeMediaMetabox'),
            'podcast',
            'normal',
            'default'
        );

        add_meta_box(
            'version',
            __('Versión', 'atareao-functionality'),
            array(__CLASS__, 'renderVersionMetabox'),
            array('aplicacion', 'software'),
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

        $view_types = array('post', 'podcast', 'capitulo', 'tutorial', 'aplicacion', 'software');
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
     * Renderizar metabox de transcripción SRT
     *
     * El contrato del SRT es único y vive en `\Atareao\PodcastFeed`: el valor es
     * el ID del adjunto (entero positivo). Esta caja no inventa su propia norma;
     * solo lo captura con la biblioteca de medios y lo deja pasar por la misma
     * normalización en el guardado.
     */
    public static function renderTranscriptMetabox($post)
    {
        wp_nonce_field('transcript_srt_nonce', 'transcript_srt_nonce_field');

        $value = \Atareao\PodcastFeed::normalizeTranscriptAttachment(
            get_post_meta($post->ID, \Atareao\PodcastFeed::TRANSCRIPT_META, true)
        );

        $name = self::transcriptAttachmentName($value);

        echo '<label for="transcript_srt">';
        _e('Transcripción SRT:', 'atareao-functionality');
        echo '</label> ';
        echo '<input type="hidden" id="transcript_srt" name="transcript_srt" value="'
            . esc_attr($value > 0 ? (string) $value : '') . '" /> ';
        echo '<button type="button" class="button" id="atareao_transcript_select">'
            . esc_html__('Seleccionar SRT', 'atareao-functionality') . '</button> ';
        echo '<button type="button" class="button" id="atareao_transcript_remove">'
            . esc_html__('Quitar', 'atareao-functionality') . '</button> ';
        echo '<span id="atareao_transcript_name">' . esc_html($name) . '</span>';
        echo '<p class="description">'
            . __('Adjunto de la biblioteca de medios con la transcripción SRT.', 'atareao-functionality')
            . ' ' . __('El feed de podcast publicará su URL.', 'atareao-functionality')
            . '</p>';
    }

    /**
     * Nombre del fichero del adjunto SRT, para mostrarlo junto al campo.
     *
     * @param int $attachmentId ID del adjunto (0 cuando no hay ninguno).
     * @return string Nombre del fichero, o su título, o cadena vacía.
     */
    private static function transcriptAttachmentName(int $attachmentId): string
    {
        if ($attachmentId <= 0) {
            return '';
        }

        $file = get_attached_file($attachmentId);
        if (is_string($file) && $file !== '') {
            return (string) wp_basename($file);
        }

        $title = get_the_title($attachmentId);

        return is_string($title) ? $title : '';
    }

    /**
     * Renderiza el metabox de multimedia del episodio.
     *
     * Dos campos ocultos guardan el ID del adjunto (imagen y documento de
     * capítulos) que el script de la biblioteca de medios rellena. Todo el
     * marcado se escapa y los valores se normalizan con `absint` antes de
     * imprimirlos: un metadato corrupto no puede inyectar atributos.
     *
     * @param \WP_Post $post Entrada en edición.
     * @return void
     */
    public static function renderEpisodeMediaMetabox(\WP_Post $post): void
    {
        $image_id = absint(get_post_meta($post->ID, \Atareao\PodcastFeed::EPISODE_IMAGE_META, true));
        $chapters_id = absint(get_post_meta($post->ID, \Atareao\PodcastFeed::CHAPTERS_META, true));

        wp_nonce_field(self::MEDIA_NONCE_ACTION, self::MEDIA_NONCE_FIELD);

        self::renderEpisodeMediaField(
            self::EPISODE_IMAGE_FIELD,
            __('Imagen del episodio:', 'atareao-functionality'),
            __('Seleccionar imagen', 'atareao-functionality'),
            'image',
            $image_id,
            __('Imagen que sustituye a la portada del programa en este episodio.', 'atareao-functionality')
        );

        self::renderEpisodeMediaField(
            self::CHAPTERS_FIELD,
            __('Documento de capítulos:', 'atareao-functionality'),
            __('Seleccionar JSON', 'atareao-functionality'),
            'chapters',
            $chapters_id,
            __('Adjunto JSON con el array «chapters» del episodio (Podcasting 2.0).', 'atareao-functionality')
        );
    }

    /**
     * Imprime un campo oculto con su vista previa y sus botones.
     *
     * Solo se pinta el botón de quitar cuando hay un valor: con el campo vacío
     * no hay nada que limpiar.
     *
     * @param string $field       Nombre del campo del formulario.
     * @param string $label       Etiqueta visible.
     * @param string $select_text Texto del botón de selección.
     * @param string $kind        `image` o `chapters`.
     * @param int    $value       ID del adjunto (0 cuando no hay ninguno).
     * @param string $description Texto de ayuda.
     * @return void
     */
    private static function renderEpisodeMediaField(
        string $field,
        string $label,
        string $select_text,
        string $kind,
        int $value,
        string $description
    ): void {
        echo '<p><label for="' . esc_attr($field) . '">' . esc_html($label) . '</label></p>';
        echo '<input type="hidden" id="' . esc_attr($field) . '" name="' . esc_attr($field)
            . '" data-atareao-kind="' . esc_attr($kind)
            . '" value="' . esc_attr($value > 0 ? (string) $value : '') . '" />';
        echo '<div class="atareao-media-preview" data-atareao-preview="' . esc_attr($field) . '">';

        if ($value > 0) {
            if (self::mediaAttachmentExists($value)) {
                if ('image' === $kind) {
                    echo wp_get_attachment_image($value, 'thumbnail');
                }

                echo '<span class="atareao-media-name">'
                    . esc_html(self::mediaAttachmentName($value)) . '</span>';
            } else {
                echo '<span class="atareao-media-warning">'
                    . esc_html__(
                        'El adjunto indicado ya no existe. Selecciona otro archivo o quita el valor.',
                        'atareao-functionality'
                    )
                    . '</span>';
            }
        }

        echo '</div>';
        echo '<button type="button" class="button atareao-media-select"'
            . ' data-atareao-target="' . esc_attr($field) . '"'
            . ' data-atareao-kind="' . esc_attr($kind) . '">'
            . esc_html($select_text) . '</button>';

        if ($value > 0) {
            echo ' <button type="button" class="button atareao-media-remove"'
                . ' data-atareao-target="' . esc_attr($field) . '"'
                . ' data-atareao-preview="' . esc_attr($field) . '">'
                . esc_html__('Quitar', 'atareao-functionality') . '</button>';
        }

        echo '<p class="description">' . esc_html($description) . '</p>';
    }

    /**
     * Indica si un identificador corresponde a un adjunto existente.
     *
     * @param int $attachment_id ID del adjunto.
     * @return bool
     */
    private static function mediaAttachmentExists(int $attachment_id): bool
    {
        if ($attachment_id <= 0) {
            return false;
        }

        return false !== get_post_mime_type($attachment_id);
    }

    /**
     * Nombre visible del adjunto (fichero o título).
     *
     * @param int $attachment_id ID del adjunto.
     * @return string
     */
    private static function mediaAttachmentName(int $attachment_id): string
    {
        if ($attachment_id <= 0) {
            return '';
        }

        $file = get_attached_file($attachment_id);
        if (is_string($file) && $file !== '') {
            return (string) wp_basename($file);
        }

        $title = get_the_title($attachment_id);

        return is_string($title) ? $title : '';
    }

    /**
     * Valida que el ID corresponda a un adjunto de imagen.
     *
     * @param int $attachment_id ID del adjunto.
     * @return bool
     */
    public static function isValidEpisodeImage(int $attachment_id): bool
    {
        if (!self::mediaAttachmentExists($attachment_id)) {
            return false;
        }

        $mime = get_post_mime_type($attachment_id);

        return is_string($mime) && strpos($mime, 'image/') === 0;
    }

    /**
     * Valida el documento de capítulos: adjunto JSON/texto con «chapters».
     *
     * El MIME por sí solo no basta (un `.txt` puede ser cualquier cosa), así
     * que se exige además que el fichero exista, sea legible y contenga un
     * array `chapters` no vacío.
     *
     * @param int $attachment_id ID del adjunto.
     * @return bool
     */
    public static function isValidChaptersDocument(int $attachment_id): bool
    {
        if (!self::mediaAttachmentExists($attachment_id)) {
            return false;
        }

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, array('application/json', 'text/plain'), true)) {
            return false;
        }

        $file = get_attached_file($attachment_id);
        if (!is_string($file) || $file === '' || !is_readable($file)) {
            return false;
        }

        $contents = file_get_contents($file);
        if (!is_string($contents)) {
            return false;
        }

        $document = json_decode($contents, true);
        if (!is_array($document) || !isset($document['chapters']) || !is_array($document['chapters'])) {
            return false;
        }

        return !empty($document['chapters']);
    }

    /**
     * Callback de `save_post_podcast`: guarda los campos desde `$_POST`.
     *
     * @param int $post_id Entrada guardada.
     * @return void
     */
    public static function saveEpisodeMediaFromPost($post_id): void
    {
        if (!isset($_POST[self::MEDIA_NONCE_FIELD])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::MEDIA_NONCE_FIELD]));
        $input = array();

        foreach (array(self::EPISODE_IMAGE_FIELD, self::CHAPTERS_FIELD) as $field) {
            if (isset($_POST[$field])) {
                $input[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
            }
        }

        self::saveEpisodeMedia((int) $post_id, $input, $nonce);
    }

    /**
     * Guarda la imagen y los capítulos del episodio.
     *
     * Comprueba en este orden nonce, autosave, revisión, tipo de contenido y
     * permiso: si alguna falla no toca ningún metadato. Con las comprobaciones
     * superadas, un campo vacío limpia el metadato, un valor no numérico se
     * rechaza y un adjunto que no supera `isValidEpisodeImage()` /
     * `isValidChaptersDocument()` se rechaza conservando el metadato anterior.
     * Todo rechazo registra un aviso en español con `add_settings_error()`.
     *
     * @param int    $post_id Entrada.
     * @param array  $input   Campos del formulario (clave => valor).
     * @param string $nonce   Nonce recibido.
     * @return array{saved: string[], rejected: string[]}
     */
    public static function saveEpisodeMedia(int $post_id, array $input, string $nonce): array
    {
        $fields = array(
            \Atareao\PodcastFeed::EPISODE_IMAGE_META => array(
                'field' => self::EPISODE_IMAGE_FIELD,
                'validator' => 'isValidEpisodeImage',
                'error' => __(
                    'La imagen del episodio debe ser un adjunto de imagen existente en la biblioteca de medios.',
                    'atareao-functionality'
                ),
            ),
            \Atareao\PodcastFeed::CHAPTERS_META => array(
                'field' => self::CHAPTERS_FIELD,
                'validator' => 'isValidChaptersDocument',
                'error' => __(
                    'El documento de capítulos debe ser un adjunto JSON o de texto con un array «chapters» no vacío.',
                    'atareao-functionality'
                ),
            ),
        );

        $result = array('saved' => array(), 'rejected' => array());

        if (!self::canSaveEpisodeMedia($post_id, $nonce)) {
            // Sin permiso ni nonce válido los campos enviados se declaran
            // rechazados: el llamador sabe que su valor no se aplicó.
            foreach ($fields as $meta_key => $definition) {
                if (array_key_exists($definition['field'], $input)) {
                    $result['rejected'][] = $meta_key;
                }
            }

            return $result;
        }

        foreach ($fields as $meta_key => $definition) {
            $field = $definition['field'];

            if (!array_key_exists($field, $input)) {
                continue;
            }

            $raw = is_scalar($input[$field]) ? trim((string) $input[$field]) : '';

            if ('' === $raw) {
                delete_post_meta($post_id, $meta_key);
                $result['saved'][] = $meta_key;

                continue;
            }

            $attachment_id = absint($raw);
            if ($attachment_id <= 0) {
                $result['rejected'][] = $meta_key;
                add_settings_error(
                    self::MEDIA_SETTINGS_GROUP,
                    'atareao_podcast_media_' . $meta_key,
                    sprintf(
                        /* translators: %s: nombre del campo del formulario. */
                        __(
                            'El valor de «%s» debe ser el identificador de un adjunto de la biblioteca.',
                            'atareao-functionality'
                        ),
                        $field
                    )
                );

                continue;
            }

            // El adjunto debe cumplir el contrato del campo: un ID existente no
            // basta (el spec exige MIME de imagen y un JSON con «chapters»).
            $validator = $definition['validator'];
            if (!self::$validator($attachment_id)) {
                $result['rejected'][] = $meta_key;
                add_settings_error(
                    self::MEDIA_SETTINGS_GROUP,
                    'atareao_podcast_media_' . $meta_key,
                    (string) $definition['error']
                );

                continue;
            }

            update_post_meta($post_id, $meta_key, $attachment_id);
            $result['saved'][] = $meta_key;
        }

        return $result;
    }

    /**
     * Comprueba las precondiciones del guardado multimedia.
     *
     * @param int    $post_id Entrada.
     * @param string $nonce   Nonce recibido.
     * @return bool
     */
    private static function canSaveEpisodeMedia(int $post_id, string $nonce): bool
    {
        if ('' === $nonce || !wp_verify_nonce($nonce, self::MEDIA_NONCE_ACTION)) {
            return false;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return false;
        }

        if ('podcast' !== get_post_type($post_id)) {
            return false;
        }

        return current_user_can('edit_post', $post_id);
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
        if (!check_ajax_referer('atareao_get_next_numero_capitulo', 'nonce', false)) {
            wp_send_json_error('invalid_nonce', 403);
        }

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
     *
     * Cada tipo de contenido con editor propio aporta su script: `capitulo`
     * autocompleta el número de capítulo y `podcast` abre la biblioteca de medios
     * para elegir el SRT de la transcripción.
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

        if ($post_type === 'capitulo') {
            self::enqueueCapituloEditScript();
        }

        if ($post_type === 'podcast') {
            self::enqueueTranscriptPickerScript();
            self::enqueueEpisodeMediaScript();
        }
    }

    /**
     * Script de edición de `capitulo`: autocompleta el número de capítulo.
     */
    private static function enqueueCapituloEditScript()
    {
        wp_register_script('atareao-capitulo-edit', '', array('jquery'), false, true);
        wp_enqueue_script('atareao-capitulo-edit');
        $nonce = wp_create_nonce('atareao_get_next_numero_capitulo');
        $inline = <<<'JS'
jQuery(function($){
    $(document).on('change', '#tutorial_id', function(){
        var tutorial = $(this).val();
        var post_id = $('#post_ID').val() || '';
        if (!tutorial) { return; }
        $.post(ajaxurl, {
            action: 'atareao_get_next_numero_capitulo',
            tutorial_id: tutorial,
            exclude_id: post_id,
            nonce: '__ATAREAO_CAPITULO_NONCE__'
        }, function(resp){
            if (resp && resp.success && resp.data.next) {
                var $num = $('#numero_capitulo');
                if ($num.length) { $num.val(resp.data.next); }
            }
        });
    });
});
JS;
        $inline = str_replace('__ATAREAO_CAPITULO_NONCE__', esc_js($nonce), $inline);

        wp_add_inline_script('atareao-capitulo-edit', $inline);
    }

    /**
     * Tipos de la biblioteca que ofrece el selector de transcripción.
     *
     * Hacen falta los dos: el plugin registra `srt` como
     * `application/x-subrip` (el tipo del pipeline y del feed), pero el
     * selector debe listar también un `.srt` que el host haya guardado como
     * `text/plain` (libmagic varía entre sistemas). Sin ambos, una
     * transcripción válida puede subirse y seguir siendo invisible aquí.
     *
     * @return array<int, string>
     */
    public static function transcriptLibraryTypes(): array
    {
        return array(
            \Atareao\PodcastFeed::TRANSCRIPT_MEDIA_TYPE,
            'text/plain',
        );
    }

    /**
     * Script de edición de `podcast`: elige o quita el SRT con `wp.media`.
     *
     * El campo oculto `transcript_srt` sigue llevando el ID del adjunto: el
     * selector solo lo rellena, y el guardado lo normaliza con la única fuente
     * de verdad (`\Atareao\PodcastFeed::normalizeTranscriptAttachment`), así que
     * la vía manual y la del pipeline escriben exactamente el mismo metadato.
     */
    private static function enqueueTranscriptPickerScript()
    {
        wp_enqueue_media();

        wp_register_script('atareao-podcast-transcript', '', array('jquery'), false, true);
        wp_enqueue_script('atareao-podcast-transcript');
        $inline = <<<'JS'
jQuery(function($){
    var frame = null;
    $('#atareao_transcript_select').on('click', function(e){
        e.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({
            title: 'Seleccionar transcripción SRT',
            button: { text: 'Usar este archivo' },
            library: { type: __ATAREAO_TRANSCRIPT_TYPES__ },
            multiple: false
        });
        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            $('#transcript_srt').val(attachment.id);
            $('#atareao_transcript_name').text(attachment.filename || attachment.title || '');
        });
        frame.open();
    });
    $('#atareao_transcript_remove').on('click', function(e){
        e.preventDefault();
        $('#transcript_srt').val('');
        $('#atareao_transcript_name').text('');
    });
});
JS;

        $inline = str_replace(
            '__ATAREAO_TRANSCRIPT_TYPES__',
            (string) wp_json_encode(self::transcriptLibraryTypes()),
            $inline
        );

        wp_add_inline_script('atareao-podcast-transcript', $inline);
    }

    /**
     * Encola la biblioteca de medios y el script del metabox multimedia.
     *
     * Solo en la pantalla de edición de `podcast`: el script escucha los
     * botones del metabox, así que fuera de esa pantalla no aporta nada.
     */
    private static function enqueueEpisodeMediaScript(): void
    {
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!is_object($screen) || !isset($screen->post_type) || 'podcast' !== $screen->post_type) {
            return;
        }

        wp_enqueue_media();

        $base_url = defined('ATAREAO_PLUGIN_URL')
            ? ATAREAO_PLUGIN_URL
            : plugin_dir_url(dirname(__FILE__));
        wp_enqueue_script(
            'atareao-podcast-media',
            rtrim($base_url, '/') . '/assets/js/podcast-media.js',
            array('jquery'),
            defined('ATAREAO_PLUGIN_VERSION') ? ATAREAO_PLUGIN_VERSION : false,
            true
        );
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
        if (!$post) {
            wp_send_json_error('invalid_post', 400);
        }

        $cookie_name = 'atareao_post_view_' . $post_id;

        $single = get_post_meta($post_id, 'post_views_count', true);
        $digits = preg_replace('/\D+/', '', (string) $single);
        $count = ($digits === '') ? 0 : intval($digits);

        if (isset($_COOKIE[$cookie_name])) {
            wp_send_json_success(array('cached' => true, 'views' => $count));
        }

        // Antiabuse: server-side dedupe per post + hashed client IP (FR-02).
        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $seen_key  = 'atareao_view_seen_' . $post_id . '_'
            . hash('sha256', $client_ip . '|' . wp_salt('nonce'));
        if (get_transient($seen_key)) {
            wp_send_json_success(array('cached' => true, 'views' => $count));
        }

        $count++;
        update_post_meta($post_id, 'post_views_count', $count);

        set_transient($seen_key, 1, 12 * 3600);

        $expire = time() + 12 * 3600;
        setcookie($cookie_name, '1', $expire, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);

        wp_send_json_success(array('views' => $count));
    }

    /**
     * Añadir columna de vistas a las listas de posts
     */
    public static function registerViewsAdminHooks()
    {
        $types = array('post', 'podcast', 'capitulo', 'tutorial', 'aplicacion', 'software');
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
     * Get views count HTML for use directly in templates.
     */
    public static function getViewsHtml($post_id)
    {
        $single = get_post_meta($post_id, 'post_views_count', true);
        $digits = preg_replace('/\D+/', '', (string) $single);
        $count = ($digits === '') ? 0 : intval($digits);

        $icon = '<svg class="atareao-views-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5c-7 0-11 6-11 7s4 7 11 7 11-6 11-7-4-7-11-7zm0 11a4 4 0 1 1 .001-8.001A4 4 0 0 1 12 16zm0-6a2 2 0 1 0 .001 4.001A2 2 0 0 0 12 10z"/></svg>';
        $aria_label = sprintf(__("Vistas: %d", 'atareao-functionality'), $count);
        return ' <span class="atareao-views" role="status" aria-label="' . esc_attr($aria_label) . '">'
            . '<span class="atareao-views-inner">' . $icon . '<span class="atareao-views-count" aria-hidden="true">' . esc_html($count) . '</span></span>'
            . '<span class="screen-reader-text">' . esc_html($aria_label) . '</span>'
            . '</span>';
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

        if (isset($_POST['transcript_srt_nonce_field']) &&
            wp_verify_nonce($_POST['transcript_srt_nonce_field'], 'transcript_srt_nonce')) {
            if (isset($_POST['transcript_srt'])) {
                update_post_meta(
                    $post_id,
                    \Atareao\PodcastFeed::TRANSCRIPT_META,
                    \Atareao\PodcastFeed::normalizeTranscriptAttachment($_POST['transcript_srt'])
                );
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
