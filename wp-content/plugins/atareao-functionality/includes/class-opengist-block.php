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
        $attribute_server = isset($attributes['server']) ? (string) $attributes['server'] : '';
        $default_server = (string) get_option('atareao_opengist_server', '');

        // El atributo `server` solo se honra si su host está permitido.
        $server = '';
        if ($attribute_server !== '' && self::isServerAllowed($attribute_server)) {
            $server = $attribute_server;
        }
        if ($server === '') {
            $server = $default_server;
        }

        $username = isset($attributes['username']) && !empty($attributes['username'])
            ? (string) $attributes['username']
            : (string) get_option('atareao_opengist_username', '');
        $gist_id = isset($attributes['gistId']) ? (string) $attributes['gistId'] : '';
        $file_filter = isset($attributes['file']) ? (string) $attributes['file'] : '';
        $theme = isset($attributes['theme']) ? (string) $attributes['theme'] : 'auto';

        // Servidor efectivo válido: esquema http(s), host no vacío y permitido.
        $parts = wp_parse_url($server);
        if (empty($username) || empty($gist_id)
            || !is_array($parts)
            || empty($parts['host'])
            || !isset($parts['scheme'])
            || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)
            || !self::isServerAllowed($server)
        ) {
            return '<div class="atareao-opengist-placeholder">'
                . __('Configuración de OpenGist incompleta. Revisa el servidor, usuario e ID del gist.', 'atareao-functionality')
                . '</div>';
        }

        $server = rtrim($server, '/');
        $gist_url = $server . '/' . rawurlencode($username) . '/' . rawurlencode($gist_id);

        // Obtener los archivos del gist y su contenido
        $gist_files = self::fetchGistFiles($gist_url, $file_filter);

        if (!empty($gist_files)) {
            return self::renderGistHtml($gist_files, $gist_url);
        }

        // Fallback: renderizar con el script embed solo si el host es permitido.
        if (!self::isServerAllowed($server)) {
            return '<div class="atareao-opengist-placeholder">'
                . __('El servidor de OpenGist configurado no está permitido.', 'atareao-functionality')
                . '</div>';
        }

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
     * Hosts permitidos: unión del host de `atareao_opengist_server` y de las
     * entradas de `atareao_opengist_allowed_hosts`. Cada entrada se normaliza
     * (minúsculas, puerto si se indicó). Una entrada sin esquema explícito se
     * admite solo sobre `https`.
     *
     * @return array<int,array{scheme:string,host:string,port:?int}>
     */
    private static function getAllowedHosts()
    {
        $entries = array();
        $server = (string) get_option('atareao_opengist_server', '');
        if ($server !== '') {
            $entries[] = $server;
        }

        $raw = (string) get_option('atareao_opengist_allowed_hosts', '');
        foreach (preg_split('/[\r\n,]+/', $raw) as $entry) {
            $entry = trim($entry);
            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        $allowed = array();
        foreach ($entries as $entry) {
            $has_scheme = stripos($entry, '://') !== false;
            $target = $has_scheme ? $entry : 'https://' . ltrim($entry, '/');
            $parts = wp_parse_url($target);
            if (!is_array($parts) || empty($parts['host'])) {
                continue;
            }
            $allowed[] = array(
                'scheme' => $has_scheme && isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https',
                'host'   => strtolower($parts['host']),
                'port'   => isset($parts['port']) ? (int) $parts['port'] : null,
            );
        }
        return $allowed;
    }

    /**
     * ¿Está permitido el host de la URL indicada (mismo esquema, host y puerto
     * cuando se especificó)?
     */
    private static function isServerAllowed($url)
    {
        $parts = wp_parse_url((string) $url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        foreach (self::getAllowedHosts() as $allowed) {
            if ($allowed['host'] !== $host) {
                continue;
            }
            if ($allowed['port'] !== null && $port !== $allowed['port']) {
                continue;
            }
            if ($scheme !== $allowed['scheme']) {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * ¿Es el host una dirección interna/reservada que no debe contactarse?
     */
    private static function isInternalHost($host)
    {
        $host = strtolower((string) $host);
        if (in_array($host, array('localhost', '127.0.0.1', '169.254.169.254', '::1'), true)) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false;
        }
        return false;
    }

    /**
     * Obtener los archivos de un gist
     *
     * 1. Fetch del embed script (.js) que funciona para gists públicos y unlisted
     * 2. Extraer nombres de archivo de las URLs raw/HEAD/ en el JS
     * 3. Fetch del raw content para cada archivo
     *
     * Las peticiones usan `wp_safe_remote_get` con `redirection => 0` y un
     * `timeout` acotado. Nunca se contacta un host interno/reservado.
     */
    private static function fetchGistFiles($gist_url, $file_filter = '')
    {
        $parts = wp_parse_url($gist_url);
        if (!is_array($parts) || empty($parts['host']) || self::isInternalHost($parts['host'])) {
            return array();
        }

        $request_args = array(
            'timeout'     => 10,
            'redirection' => 0,
        );

        $script_url = $gist_url . '.js';
        $response = wp_safe_remote_get($script_url, $request_args);

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
            $raw_response = wp_safe_remote_get($raw_url, $request_args);

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
        $container_id = 'atareao-opengist-' . uniqid();
        $expand_text = esc_js(__('Expand', 'atareao-functionality'));
        $collapse_text = esc_js(__('Collapse', 'atareao-functionality'));
        ob_start();
        ?>
        <div class="atareao-opengist" id="<?php echo esc_attr($container_id); ?>">
            <?php foreach ($gist_files as $fname => $fdata) : ?>
                <div class="atareao-opengist-file">
                    <div class="atareao-opengist-header">
                        <div class="atareao-opengist-header-left">
                            <span class="atareao-opengist-dot atareao-opengist-dot-red"></span>
                            <span class="atareao-opengist-dot atareao-opengist-dot-yellow"></span>
                            <span class="atareao-opengist-dot atareao-opengist-dot-green"></span>
                        </div>
                        <button class="atareao-opengist-toggle" type="button" aria-expanded="false" onclick="
                            var file = this.closest('.atareao-opengist-file');
                            var container = file.querySelector('.atareao-opengist-code-container');
                            var isExpanded = container.classList.toggle('expanded');
                            file.querySelectorAll('.atareao-opengist-toggle').forEach(function(btn) {
                                btn.classList.toggle('expanded', isExpanded);
                                btn.setAttribute('aria-expanded', isExpanded);
                                btn.querySelector('.atareao-opengist-toggle-text').textContent = isExpanded ? '<?php echo $collapse_text; ?>' : '<?php echo $expand_text; ?>';
                            });
                        ">
                            <span class="atareao-opengist-toggle-icon">&#9660;</span>
                            <span class="atareao-opengist-toggle-text"><?php esc_html_e('Expand', 'atareao-functionality'); ?></span>
                        </button>
                        <a class="atareao-opengist-filename" href="<?php echo esc_url($gist_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($fname); ?>
                        </a>
                    </div>
                    <div class="atareao-opengist-code-container">
                        <pre class="atareao-opengist-code"><code><?php echo esc_html($fdata['content']); ?></code></pre>
                    </div>
                    <div class="atareao-opengist-footer">
                        <div></div>
                        <button class="atareao-opengist-toggle" type="button" aria-expanded="false" onclick="
                            var file = this.closest('.atareao-opengist-file');
                            var container = file.querySelector('.atareao-opengist-code-container');
                            var isExpanded = container.classList.toggle('expanded');
                            file.querySelectorAll('.atareao-opengist-toggle').forEach(function(btn) {
                                btn.classList.toggle('expanded', isExpanded);
                                btn.setAttribute('aria-expanded', isExpanded);
                                btn.querySelector('.atareao-opengist-toggle-text').textContent = isExpanded ? '<?php echo $collapse_text; ?>' : '<?php echo $expand_text; ?>';
                            });
                        ">
                            <span class="atareao-opengist-toggle-icon">&#9660;</span>
                            <span class="atareao-opengist-toggle-text"><?php esc_html_e('Expand', 'atareao-functionality'); ?></span>
                        </button>
                        <a class="atareao-opengist-filename" href="<?php echo esc_url($gist_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($fname); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}