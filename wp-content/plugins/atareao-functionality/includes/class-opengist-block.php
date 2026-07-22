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

        wp_register_style(
            'atareao-opengist-block-style',
            ATAREAO_PLUGIN_URL . 'assets/blocks/opengist/style.css',
            array(),
            ATAREAO_PLUGIN_VERSION
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
                'style' => 'atareao-opengist-block-style',
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

        $server = rtrim($server, '/');
        $gist_url = $server . '/' . $username . '/' . $gist_id;

        // Construir la URL del script embed
        $script_url = $gist_url . '.js';
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

        // Intentar obtener el contenido del gist via API REST de OpenGist
        $api_url = $server . '/api/v1/gists/' . $gist_id;
        $response = wp_remote_get($api_url, array('timeout' => 10));
        $gist_content = '';
        $gist_files = array();

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['files']) && is_array($body['files'])) {
                foreach ($body['files'] as $fname => $fdata) {
                    if (!empty($file) && $fname !== $file) {
                        continue;
                    }
                    $gist_files[$fname] = array(
                        'content' => isset($fdata['content']) ? $fdata['content'] : '',
                        'language' => isset($fdata['language']) ? $fdata['language'] : '',
                        'size' => isset($fdata['size']) ? $fdata['size'] : 0,
                    );
                }
            }
        }

        ob_start();
        ?>
        <div class="atareao-opengist">
            <?php if (!empty($gist_files)) : ?>
                <div class="atareao-opengist-files">
                    <?php foreach ($gist_files as $fname => $fdata) : ?>
                        <div class="atareao-opengist-file">
                            <div class="atareao-opengist-file-header">
                                <span class="atareao-opengist-filename"><?php echo esc_html($fname); ?></span>
                                <?php if (!empty($fdata['size'])) : ?>
                                    <span class="atareao-opengist-filesize"><?php echo esc_html(size_format($fdata['size'])); ?></span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url($gist_url . '/raw/' . $fname); ?>" target="_blank" rel="noopener noreferrer" class="atareao-opengist-raw-link"><?php esc_html_e('view raw', 'atareao-functionality'); ?></a>
                            </div>
                            <pre class="atareao-opengist-code"><code><?php echo esc_html($fdata['content']); ?></code></pre>
                        </div>
                    <?php endforeach; ?>
                    <div class="atareao-opengist-footer">
                        <a href="<?php echo esc_url($gist_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Ver gist en OpenGist', 'atareao-functionality'); ?>
                        </a>
                    </div>
                </div>
            <?php else : ?>
                <script src="<?php echo esc_url($script_url); ?>"></script>
                <noscript>
                    <p>
                        <a href="<?php echo esc_url($gist_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Ver gist en OpenGist', 'atareao-functionality'); ?>
                        </a>
                    </p>
                </noscript>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }
}