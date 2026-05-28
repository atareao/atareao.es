<?php
/**
 * Clase para el bloque de reproductor de podcast
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class PodcastBlock
{

    /**
     * Inicializar el bloque
     */
    public static function init()
    {
        self::registerAssets();
        self::registerBlock();
    }

    /**
     * Registrar assets del bloque
     */
    public static function registerAssets()
    {
        wp_register_script(
            'atareao-podcast-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/podcast-player/index.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-api-fetch'),
            ATAREAO_PLUGIN_VERSION,
            false
        );

        wp_register_style(
            'atareao-podcast-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/podcast-player/editor.css',
            array('wp-edit-blocks'),
            ATAREAO_PLUGIN_VERSION
        );

        wp_register_style(
            'atareao-podcast-block-style',
            ATAREAO_PLUGIN_URL . 'assets/blocks/podcast-player/style.css',
            array(),
            ATAREAO_PLUGIN_VERSION
        );

        wp_register_script(
            'atareao-podcast-player',
            ATAREAO_PLUGIN_URL . 'assets/blocks/podcast-player/podcast-player.js',
            array(),
            ATAREAO_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Registrar el bloque de podcast
     */
    public static function registerBlock()
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(
            ATAREAO_PLUGIN_DIR . 'assets/blocks/podcast-player',
            array(
                'editor_script' => 'atareao-podcast-block-editor',
                'editor_style' => 'atareao-podcast-block-editor',
                'style' => 'atareao-podcast-block-style',
                'render_callback' => array(__CLASS__, 'renderPodcastPlayer'),
            )
        );
    }

    /**
     * Renderizar el bloque en el frontend
     */
    public static function renderPodcastPlayer($attributes)
    {
        $audio_url = isset($attributes['audioUrl']) ? esc_url($attributes['audioUrl']) : '';
        $title = isset($attributes['title']) ? esc_html($attributes['title']) : '';
        $description = isset($attributes['description']) ? esc_html($attributes['description']) : '';
        $podcast_id = isset($attributes['podcastId']) ? intval($attributes['podcastId']) : 0;

        if ($podcast_id > 0) {
            $podcast = get_post($podcast_id);
            if ($podcast && $podcast->post_type === 'podcast') {
                $title = $title ?: get_the_title($podcast_id);
                $description = $description ?: get_the_excerpt($podcast_id);
                $audio_meta = get_post_meta($podcast_id, 'mp3-url', true);
                $audio_url = $audio_url ?: $audio_meta;
            }
        }

        if (empty($audio_url)) {
            return '<div class="atareao-podcast-player-placeholder">'
                . __('Por favor, selecciona un podcast o añade una URL de audio.', 'atareao-functionality')
                . '</div>';
        }

        $player_id = 'podcast-player-' . uniqid();

        wp_enqueue_script('atareao-podcast-player');

        ob_start();
        ?>
        <div class="atareao-podcast-player" data-player-id="<?php echo $player_id; ?>">
            <div class="podcast-player-custom">
                <audio id="<?php echo $player_id; ?>" preload="metadata" class="podcast-audio-element">
                    <source src="<?php echo $audio_url; ?>" type="audio/mpeg">
                </audio>

                <div class="podcast-controls">
                    <div class="podcast-controls-left">
                        <div class="podcast-controls-all">
                            <select class="podcast-speed" aria-label="<?php _e('Velocidad', 'atareao-functionality'); ?>">
                                <option value="0.5">0.5×</option>
                                <option value="0.75">0.75×</option>
                                <option value="1" selected>1×</option>
                                <option value="1.25">1.25×</option>
                                <option value="1.5">1.5×</option>
                                <option value="2">2×</option>
                            </select>

                            <button class="podcast-btn podcast-skip podcast-backward" aria-label="<?php _e('Retroceder 30 segundos', 'atareao-functionality'); ?>" title="<?php _e('Retroceder 30s', 'atareao-functionality'); ?>">
                                <span class="dashicons dashicons-controls-back"></span>
                                <span class="podcast-skip-label">30</span>
                            </button>

                            <button class="podcast-btn podcast-play-pause" aria-label="<?php _e('Reproducir/Pausar', 'atareao-functionality'); ?>">
                                <span class="dashicons dashicons-controls-play"></span>
                            </button>

                            <button class="podcast-btn podcast-skip podcast-forward" aria-label="<?php _e('Avanzar 30 segundos', 'atareao-functionality'); ?>" title="<?php _e('Avanzar 30s', 'atareao-functionality'); ?>">
                                <span class="dashicons dashicons-controls-forward"></span>
                                <span class="podcast-skip-label">30</span>
                            </button>

                            <div class="podcast-volume-control">
                                <button class="podcast-btn podcast-mute" aria-label="<?php _e('Volumen', 'atareao-functionality'); ?>">
                                    <span class="dashicons dashicons-controls-volumeon"></span>
                                </button>
                                <input type="range" class="podcast-volume" min="0" max="100" value="100" aria-label="<?php _e('Volumen', 'atareao-functionality'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="podcast-controls-center">
                        <div class="podcast-progress-container">
                            <div class="podcast-progress-bar">
                                <div class="podcast-progress-filled"></div>
                            </div>
                        </div>
                    </div>

                    <div class="podcast-controls-right">
                        <div class="podcast-controls-time">
                            <span class="podcast-time podcast-current-time">0:00</span>
                            <span class="podcast-time-separator">/</span>
                            <span class="podcast-time podcast-duration">0:00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}
