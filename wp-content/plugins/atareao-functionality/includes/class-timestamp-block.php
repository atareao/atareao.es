<?php
/**
 * Clase para el bloque de Timestamp Helper
 *
 * Bloque Gutenberg dinamico para convertir Unix timestamps
 * a fecha legible y viceversa, con soporte de zonas horarias,
 * historial local y URLs compartibles.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class TimestampBlock
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
            'atareao-timestamp-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/timestamp-helper/index.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-api-fetch'),
            ATAREAO_PLUGIN_VERSION,
            false
        );

        wp_register_style(
            'atareao-timestamp-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/timestamp-helper/editor.css',
            array('wp-edit-blocks'),
            ATAREAO_PLUGIN_VERSION
        );

        wp_register_style(
            'atareao-timestamp-block-style',
            ATAREAO_PLUGIN_URL . 'assets/blocks/timestamp-helper/style.css',
            array(),
            ATAREAO_PLUGIN_VERSION
        );

        wp_register_script(
            'atareao-timestamp-frontend',
            ATAREAO_PLUGIN_URL . 'assets/blocks/timestamp-helper/timestamp-frontend.js',
            array(),
            ATAREAO_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Registrar el bloque de Timestamp Helper
     */
    public static function registerBlock()
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(
            ATAREAO_PLUGIN_DIR . 'assets/blocks/timestamp-helper',
            array(
                'editor_script' => 'atareao-timestamp-block-editor',
                'editor_style' => 'atareao-timestamp-block-editor',
                'style' => 'atareao-timestamp-block-style',
                'render_callback' => array(__CLASS__, 'renderTimestampHelper'),
            )
        );
    }

    /**
     * Renderizar el bloque en el frontend
     *
     * @param array $attributes Atributos del bloque.
     * @return string HTML del bloque.
     */
    public static function renderTimestampHelper($attributes)
    {
        $timestamp = isset($attributes['timestamp']) ? esc_attr($attributes['timestamp']) : '';
        $unit = isset($attributes['unit']) ? esc_attr($attributes['unit']) : 's';
        $timezone = isset($attributes['timezone']) ? esc_attr($attributes['timezone']) : 'local';
        $show_history = !empty($attributes['showHistory']) ? '1' : '0';

        $container_id = 'atareao-timestamp-' . uniqid();

        // Enqueue frontend script
        wp_enqueue_script('atareao-timestamp-frontend');

        ob_start();
        ?>
        <div class="atareao-timestamp-helper"
             id="<?php echo esc_attr($container_id); ?>"
             data-timestamp="<?php echo $timestamp; ?>"
             data-unit="<?php echo $unit; ?>"
             data-timezone="<?php echo $timezone; ?>"
             data-show-history="<?php echo $show_history; ?>">
            <div class="atareao-timestamp-loading">
                <span class="atareao-timestamp-spinner"></span>
                <?php esc_html_e('Cargando Timestamp Helper...', 'atareao-functionality'); ?>
            </div>
        </div>
        <noscript>
            <div class="atareao-timestamp-noscript">
                <p><?php esc_html_e('El Timestamp Helper necesita JavaScript para funcionar.', 'atareao-functionality'); ?></p>
                <?php if (!empty($timestamp)) : ?>
                    <p><?php esc_html_e('Timestamp:', 'atareao-functionality'); ?>
                    <code><?php echo $timestamp; ?></code></p>
                <?php endif; ?>
            </div>
        </noscript>
        <?php
        return ob_get_clean();
    }
}
