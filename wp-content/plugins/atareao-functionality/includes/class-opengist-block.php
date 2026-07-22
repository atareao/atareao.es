<?php
/**
 * Clase para el bloque de OpenGist
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class OpengistBlock
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
            'atareao-opengist-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/opengist/index.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-api-fetch'),
            ATAREAO_PLUGIN_VERSION,
            false
        );
    }

    /**
     * Registrar el bloque de OpenGist
     */
    public static function registerBlock()
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(
            ATAREAO_PLUGIN_DIR . 'assets/blocks/opengist',
            array(
                'editor_script' => 'atareao-opengist-block-editor',
                'render_callback' => array(__CLASS__, 'renderOpengist'),
            )
        );
    }

    /**
     * Renderizar el bloque en el frontend
     */
    public static function renderOpengist($attributes)
    {
        $server = isset($attributes['server']) && !empty($attributes['server'])
            ? esc_url($attributes['server'])
            : get_option('atareao_opengist_server', '');
        $username = isset($attributes['username']) && !empty($attributes['username'])
            ? esc_attr($attributes['username'])
            : get_option('atareao_opengist_username', '');
        $gist_id = isset($attributes['gistId']) ? esc_attr($attributes['gistId']) : '';
        $file = isset($attributes['file']) ? esc_attr($attributes['file']) : '';
        $theme = isset($attributes['theme']) ? esc_attr($attributes['theme']) : 'auto';

        if (empty($server) || empty($username) || empty($gist_id)) {
            return '<div class="atareao-opengist-placeholder">'
                . __('Configuración de OpenGist incompleta. Revisa el servidor, usuario e ID del gist.', 'atareao-functionality')
                . '</div>';
        }

        // Construir la URL del script embed
        $server = rtrim($server, '/');
        $script_url = $server . '/' . $username . '/' . $gist_id . '.js';

        // Añadir parámetros opcionales
        $params = array();
        if (!empty($file)) {
            $params['file'] = $file;
        }
        if (!empty($theme) && 'auto' !== $theme) {
            $params[] = $theme;
        }

        if (!empty($params)) {
            $script_url .= '?' . implode('&', $params);
        }

        $gist_url = $server . '/' . $username . '/' . $gist_id;

        ob_start();
        ?>
        <div class="atareao-opengist">
            <script src="<?php echo esc_url($script_url); ?>"></script>
            <noscript>
                <p>
                    <a href="<?php echo esc_url($gist_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Ver gist en OpenGist', 'atareao-functionality'); ?>
                    </a>
                </p>
            </noscript>
        </div>
        <?php

        return ob_get_clean();
    }
}