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
        $file_filter = isset($attributes['file']) ? esc_attr($attributes['file']) : '';
        $theme = isset($attributes['theme']) ? esc_attr($attributes['theme']) : 'auto';

        if (empty($server) || empty($username) || empty($gist_id)) {
            return '<div class="atareao-opengist-placeholder">'
                . __('Configuración de OpenGist incompleta. Revisa el servidor, usuario e ID del gist.', 'atareao-functionality')
                . '</div>';
        }

        $server = rtrim($server, '/');
        $gist_url = $server . '/' . $username . '/' . $gist_id;

        // Obtener los archivos del gist y su contenido
        $gist_files = self::fetchGistFiles($gist_url, $file_filter);

        if (!empty($gist_files)) {
            return self::renderGistHtml($gist_files, $gist_url);
        }

        // Fallback: renderizar con el script embed
        $script_url = $gist_url . '.js';
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

    /**
     * Obtener los archivos de un gist
     *
     * 1. Fetch del embed script (.js) que funciona para gists públicos y unlisted
     * 2. Extraer nombres de archivo de las URLs raw/HEAD/ en el JS
     * 3. Fetch del raw content para cada archivo
     */
    private static function fetchGistFiles($gist_url, $file_filter = '')
    {
        $script_url = $gist_url . '.js';
        $response = wp_remote_get($script_url, array('timeout' => 15));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $js = wp_remote_retrieve_body($response);

        // Extraer nombres de archivo de las URLs raw/HEAD/ en el JS
        // En el JS aparecen como: raw/HEAD/tmux.conf\"
        preg_match_all('/raw\/HEAD\/[^"\\\\]+/', $js, $matches);

        $file_names = array();
        foreach ($matches[0] as $path) {
            // Extraer solo el nombre de archivo (después de raw/HEAD/)
            $fname = basename($path);
            if (!empty($fname)) {
                $file_names[$fname] = true;
            }
        }

        if (empty($file_names)) {
            return array();
        }

        $gist_files = array();
        foreach (array_keys($file_names) as $fname) {
            if (!empty($file_filter) && $fname !== $file_filter) {
                continue;
            }

            // Fetch raw content
            $raw_url = $gist_url . '/raw/HEAD/' . rawurlencode($fname);
            $raw_response = wp_remote_get($raw_url, array('timeout' => 15));

            if (!is_wp_error($raw_response) && wp_remote_retrieve_response_code($raw_response) === 200) {
                $gist_files[$fname] = array(
                    'content' => wp_remote_retrieve_body($raw_response),
                );
            }
        }

        return $gist_files;
    }

    /**
     * Renderizar HTML de los archivos del gist
     */
    private static function renderGistHtml($gist_files, $gist_url)
    {
        ob_start();
        ?>
        <div class="atareao-opengist">
            <div class="atareao-opengist-files">
                <?php foreach ($gist_files as $fname => $fdata) : ?>
                    <div class="atareao-opengist-file">
                        <div class="atareao-opengist-file-header">
                            <span class="atareao-opengist-filename"><?php echo esc_html($fname); ?></span>
                            <a href="<?php echo esc_url($gist_url . '/raw/HEAD/' . rawurlencode($fname)); ?>" target="_blank" rel="noopener noreferrer" class="atareao-opengist-raw-link"><?php esc_html_e('view raw', 'atareao-functionality'); ?></a>
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
        </div>
        <?php
        return ob_get_clean();
    }
}