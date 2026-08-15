<?php
/**
 * Clase para el bloque de Crontab Helper
 *
 * Bloque Gutenberg dinamico para analizar expresiones cron,
 * generar descripciones, calcular proximas ejecuciones,
 * mostrar calendario termico y exportar a multiples formatos.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class CrontabBlock
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
            'atareao-crontab-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/crontab-helper/index.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-api-fetch'),
            ATAREAO_PLUGIN_VERSION,
            false
        );

        wp_register_style(
            'atareao-crontab-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/crontab-helper/editor.css',
            array('wp-edit-blocks'),
            ATAREAO_PLUGIN_VERSION
        );

        wp_register_style(
            'atareao-crontab-block-style',
            ATAREAO_PLUGIN_URL . 'assets/blocks/crontab-helper/style.css',
            array(),
            ATAREAO_PLUGIN_VERSION
        );

        wp_register_script(
            'atareao-crontab-qrcode',
            ATAREAO_PLUGIN_URL . 'assets/blocks/crontab-helper/qrcode.min.js',
            array(),
            ATAREAO_PLUGIN_VERSION,
            true
        );

        wp_register_script(
            'atareao-crontab-frontend',
            ATAREAO_PLUGIN_URL . 'assets/blocks/crontab-helper/crontab-frontend.js',
            array('atareao-crontab-qrcode'),
            ATAREAO_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Registrar el bloque de Crontab Helper
     */
    public static function registerBlock()
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(
            ATAREAO_PLUGIN_DIR . 'assets/blocks/crontab-helper',
            array(
                'editor_script' => 'atareao-crontab-block-editor',
                'editor_style' => 'atareao-crontab-block-editor',
                'style' => 'atareao-crontab-block-style',
                'render_callback' => array(__CLASS__, 'renderCrontabHelper'),
            )
        );
    }

    /**
     * Renderizar el bloque en el frontend
     *
     * @param array $attributes Atributos del bloque.
     * @return string HTML del bloque.
     */
    public static function renderCrontabHelper($attributes)
    {
        $expression = isset($attributes['expression']) ? esc_attr($attributes['expression']) : '';
        $timezone = isset($attributes['timezone']) ? esc_attr($attributes['timezone']) : 'UTC';
        $mode = isset($attributes['mode']) ? esc_attr($attributes['mode']) : 'expert';
        $show_calendar = !empty($attributes['showCalendar']) ? '1' : '0';
        $next_count = isset($attributes['nextExecutions']) ? intval($attributes['nextExecutions']) : 5;
        $use_seconds = !empty($attributes['useSeconds']) ? '1' : '0';

        $container_id = 'atareao-crontab-' . uniqid();

        // Enqueue frontend script
        wp_enqueue_script('atareao-crontab-frontend');

        ob_start();
        ?>
        <div class="atareao-crontab-helper"
             id="<?php echo esc_attr($container_id); ?>"
             data-expression="<?php echo $expression; ?>"
             data-timezone="<?php echo $timezone; ?>"
             data-mode="<?php echo $mode; ?>"
             data-show-calendar="<?php echo $show_calendar; ?>"
             data-next-executions="<?php echo $next_count; ?>"
             data-use-seconds="<?php echo $use_seconds; ?>">
            <div class="atareao-crontab-loading">
                <span class="atareao-crontab-spinner"></span>
                <?php esc_html_e('Cargando Crontab Helper...', 'atareao-functionality'); ?>
            </div>
        </div>
        <noscript>
            <div class="atareao-crontab-noscript">
                <p><?php esc_html_e('El Crontab Helper necesita JavaScript para funcionar.', 'atareao-functionality'); ?></p>
                <?php if (!empty($expression)) : ?>
                    <p><?php esc_html_e('Expresion cron:', 'atareao-functionality'); ?>
                    <code><?php echo $expression; ?></code></p>
                <?php endif; ?>
            </div>
        </noscript>
        <?php
        return ob_get_clean();
    }
}