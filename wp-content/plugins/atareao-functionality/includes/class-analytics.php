<?php
/**
 * Analítica (Umami)
 *
 * Emite el script del tracker de Umami en el pie de página, expone sus ajustes
 * bajo Ajustes → Analítica y permite migrar la configuración del plugin legado
 * «Integrate Umami» sin perder datos. Módulo `\Atareao\Analytics`.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestión de la analítica Umami del sitio.
 */
class Analytics
{
    /**
     * Prefijo de todas las claves de opción del módulo.
     */
    public const OPTION_PREFIX = 'atareao_umami_';

    /**
     * Ajuste del plugin legado del que se importa.
     */
    private const LEGACY_OPTION = 'integrate_umami_options';

    /**
     * Copia propia de la configuración legada (NO forma parte de defaults()).
     */
    private const SNAPSHOT_OPTION = 'atareao_umami_legacy_snapshot';

    /**
     * Inicializar: solo engancha hooks. Idempotente.
     *
     * @return void
     */
    public static function init()
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;

        add_action('wp_footer', array(__CLASS__, 'renderScript'));
        add_filter('comment_form_submit_button', array(__CLASS__, 'filterCommentSubmitButton'));
        add_action('admin_init', array(__CLASS__, 'maybeSaveSettings'));
        add_action('admin_init', array(__CLASS__, 'maybeSnapshotLegacySettings'));
    }

    /**
     * Valores por defecto de las 15 claves.
     *
     * @return array
     */
    public static function defaults()
    {
        return array(
            'enabled' => 0,
            'script_url' => '',
            'website_id' => '',
            'host_url' => '',
            'use_host_url' => 0,
            'integrity' => '',
            'ignore_admins' => 1,
            'auto_track' => 1,
            'do_not_track' => 1,
            'cache' => 0,
            'track_comments' => 0,
            'exclude_search' => 0,
            'exclude_hash' => 0,
            'skip_404' => 0,
            'skip_search' => 0,
        );
    }

    /**
     * Claves tratadas como URL.
     *
     * @return string[]
     */
    private static function urlKeys()
    {
        return array('script_url', 'host_url');
    }

    /**
     * Claves tratadas como texto libre.
     *
     * @return string[]
     */
    private static function textKeys()
    {
        return array('website_id', 'integrity');
    }

    /**
     * Claves tratadas como banderas 0/1.
     *
     * @return string[]
     */
    private static function flagKeys()
    {
        return array(
            'enabled',
            'use_host_url',
            'ignore_admins',
            'auto_track',
            'do_not_track',
            'cache',
            'track_comments',
            'exclude_search',
            'exclude_hash',
            'skip_404',
            'skip_search',
        );
    }

    /**
     * Claves del plugin legado que se copian/importan (mapeo 1:1).
     *
     * @return string[]
     */
    private static function legacyMapping()
    {
        return array(
            'enabled',
            'script_url',
            'website_id',
            'host_url',
            'use_host_url',
            'ignore_admins',
            'auto_track',
            'do_not_track',
            'cache',
            'track_comments',
        );
    }

    /**
     * Normaliza un valor de bandera a 0 o 1.
     *
     * @param mixed $value Valor de entrada.
     * @return int
     */
    private static function toFlag($value)
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value) || is_float($value)) {
            return ((int) $value) ? 1 : 0;
        }
        if (!is_scalar($value)) {
            return 0;
        }
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, array('1', 'on', 'true', 'yes'), true)) {
            return 1;
        }
        return 0;
    }

    /**
     * Sanea una única clave según su tipo (URL, texto o bandera).
     *
     * Los valores no escalares (arrays) se normalizan a '' o 0.
     *
     * @param string $key   Clave corta.
     * @param mixed  $value Valor de entrada.
     * @return int|string
     */
    private static function sanitizeValue($key, $value)
    {
        if (in_array($key, self::urlKeys(), true)) {
            return is_scalar($value) ? esc_url_raw(trim((string) $value)) : '';
        }
        if (in_array($key, self::textKeys(), true)) {
            return is_scalar($value) ? sanitize_text_field((string) $value) : '';
        }
        return self::toFlag($value);
    }

    /**
     * Lee los ajustes almacenados, completados con los defaults y normalizados.
     *
     * @return array
     */
    public static function getSettings()
    {
        $settings = self::defaults();
        foreach ($settings as $key => $default) {
            $stored = get_option(self::OPTION_PREFIX . $key, $default);
            if (in_array($key, self::urlKeys(), true)) {
                $settings[$key] = is_scalar($stored) ? esc_url_raw((string) $stored) : '';
            } elseif (in_array($key, self::textKeys(), true)) {
                $settings[$key] = is_scalar($stored) ? sanitize_text_field((string) $stored) : '';
            } else {
                $settings[$key] = is_scalar($stored) ? self::toFlag($stored === '' ? $default : $stored) : 0;
            }
        }
        return $settings;
    }

    /**
     * Sanea una entrada según el tipo de cada clave y normaliza las banderas.
     *
     * @param array $input Datos de entrada (claves cortas).
     * @return array
     */
    public static function sanitizeSettings(array $input)
    {
        $settings = self::defaults();

        foreach (self::urlKeys() as $key) {
            if (array_key_exists($key, $input)) {
                $settings[$key] = self::sanitizeValue($key, $input[$key]);
            }
        }
        foreach (self::textKeys() as $key) {
            if (array_key_exists($key, $input)) {
                $settings[$key] = self::sanitizeValue($key, $input[$key]);
            }
        }
        foreach (self::flagKeys() as $key) {
            $settings[$key] = array_key_exists($key, $input) ? self::sanitizeValue($key, $input[$key]) : 0;
        }

        return $settings;
    }

    /**
     * ¿Procede emitir el script con estos ajustes y en este contexto?
     *
     * @param array $settings Ajustes normalizados.
     * @return bool
     */
    public static function shouldEmit(array $settings)
    {
        if (empty($settings['enabled'])) {
            return false;
        }
        if (empty($settings['script_url']) || empty($settings['website_id'])) {
            return false;
        }
        if (self::legacyWillEmit()) {
            return false;
        }
        if (is_admin() || is_feed() || is_preview() || is_customize_preview()) {
            return false;
        }
        if (wp_is_json_request()) {
            return false;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        if (function_exists('is_robots') && is_robots()) {
            return false;
        }
        if (!empty($settings['skip_404']) && is_404()) {
            return false;
        }
        if (!empty($settings['skip_search']) && is_search()) {
            return false;
        }
        if (!empty($settings['ignore_admins']) && current_user_can('manage_options')) {
            return false;
        }
        return true;
    }

    /**
     * Construye el HTML de la etiqueta del tracker. Método puro, sin echo.
     *
     * @param array $settings Ajustes normalizados.
     * @return string HTML válido o cadena vacía si la configuración no procede.
     */
    public static function buildTag(array $settings)
    {
        if (empty($settings['enabled']) || empty($settings['script_url']) || empty($settings['website_id'])) {
            return '';
        }

        $attributes = array(
            'async',
            'defer',
            'src="' . esc_url($settings['script_url']) . '"',
            'data-website-id="' . esc_attr($settings['website_id']) . '"',
        );

        if (!empty($settings['do_not_track'])) {
            $attributes[] = 'data-do-not-track="true"';
        }
        if (isset($settings['auto_track']) && !$settings['auto_track']) {
            $attributes[] = 'data-auto-track="false"';
        }
        if (!empty($settings['cache'])) {
            $attributes[] = 'data-cache="true"';
        }
        if (!empty($settings['use_host_url']) && !empty($settings['host_url'])) {
            $attributes[] = 'data-host-url="' . esc_attr($settings['host_url']) . '"';
        }
        if (!empty($settings['exclude_search'])) {
            $attributes[] = 'data-exclude-search="true"';
        }
        if (!empty($settings['exclude_hash'])) {
            $attributes[] = 'data-exclude-hash="true"';
        }
        if (!empty($settings['integrity'])) {
            $attributes[] = 'integrity="' . esc_attr($settings['integrity']) . '"';
            $attributes[] = 'crossorigin="anonymous"';
        }

        $tag = '<!-- Atareao Analytics (Umami) -->' . "\n"
            . '<script ' . implode(' ', $attributes) . '></script>' . "\n"
            . '<!-- /Atareao Analytics (Umami) -->';

        return $tag;
    }

    /**
     * Enganche de wp_footer: imprime la etiqueta si procede.
     *
     * @return void
     */
    public static function renderScript()
    {
        $settings = self::getSettings();
        if (!self::shouldEmit($settings)) {
            return;
        }
        echo self::buildTag($settings);
    }

    /**
     * ¿Está cargado el plugin legado «Integrate Umami»?
     *
     * @return bool
     */
    public static function legacyPluginLoaded()
    {
        return class_exists('\Ancozockt\Umami\Manager');
    }

    /**
     * ¿Existe la configuración del plugin legado?
     *
     * @return bool
     */
    public static function legacySettingsExist()
    {
        return !empty(get_option(self::LEGACY_OPTION));
    }

    /**
     * ¿El plugin legado está cargado Y va a emitir su propio script?
     *
     * Solo entonces debemos abstenernos para no duplicar. Si está cargado pero
     * inactivo (o incompleto), su script no se emite y el nuestro sí debe hacerlo.
     *
     * @return bool
     */
    public static function legacyWillEmit()
    {
        if (!self::legacyPluginLoaded()) {
            return false;
        }
        $legacy = get_option(self::LEGACY_OPTION);
        if (!is_array($legacy)) {
            return false;
        }
        return !empty($legacy['enabled'])
            && !empty($legacy['script_url'])
            && !empty($legacy['website_id']);
    }

    /**
     * Guarda/actualiza una copia propia de la configuración legada, si existe y cambió.
     *
     * Imprescindible porque el plugin legado borra su opción al desactivarse.
     * No escribe nada si no hay configuración legada o si el snapshot no cambia.
     *
     * @return void
     */
    public static function maybeSnapshotLegacySettings()
    {
        $legacy = get_option(self::LEGACY_OPTION);
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }

        $snapshot = array();
        foreach (self::legacyMapping() as $key) {
            if (array_key_exists($key, $legacy)) {
                $snapshot[$key] = self::sanitizeValue($key, $legacy[$key]);
            }
        }
        if (empty($snapshot)) {
            return;
        }
        if (get_option(self::SNAPSHOT_OPTION) === $snapshot) {
            return;
        }
        update_option(self::SNAPSHOT_OPTION, $snapshot);
    }

    /**
     * Importa la configuración legada (viva o desde el snapshot propio) sin borrar nada.
     *
     * @return int Número de ajustes importados.
     */
    public static function importLegacy()
    {
        $legacy = get_option(self::LEGACY_OPTION);
        if (!is_array($legacy) || empty($legacy)) {
            $legacy = get_option(self::SNAPSHOT_OPTION);
        }
        if (!is_array($legacy) || empty($legacy)) {
            return 0;
        }

        $input = array();
        foreach (self::legacyMapping() as $key) {
            if (array_key_exists($key, $legacy)) {
                $input[$key] = $legacy[$key];
            }
        }
        if (empty($input)) {
            return 0;
        }

        foreach ($input as $key => $value) {
            update_option(self::OPTION_PREFIX . $key, self::sanitizeValue($key, $value));
        }

        return count($input);
    }

    /**
     * ¿Debe mostrarse el aviso de apagón silencioso? (Decisión 9)
     *
     * Verdadero cuando (a) no vamos a emitir para un visitante normal, (b) hay
     * evidencia de configuración legada (snapshot propio o ajuste vivo) y (c) el
     * plugin legado no va a emitir por su cuenta.
     *
     * @return bool
     */
    public static function shouldWarnAnalyticsOff()
    {
        $settings = self::getSettings();
        $wouldEmit = !empty($settings['enabled'])
            && !empty($settings['script_url'])
            && !empty($settings['website_id']);
        if ($wouldEmit) {
            return false;
        }
        if (self::legacyWillEmit()) {
            return false;
        }

        $snapshot = get_option(self::SNAPSHOT_OPTION);
        $live = get_option(self::LEGACY_OPTION);
        return (!empty($snapshot) && is_array($snapshot)) || (!empty($live) && is_array($live));
    }

    /**
     * Trunca el título a 50 caracteres (si procede) sin partir caracteres multibyte.
     *
     * @param string $title Título original.
     * @return string
     */
    private static function truncateTitle($title)
    {
        $title = (string) $title;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($title, 'UTF-8') > 50) {
                return mb_substr($title, 0, 50, 'UTF-8') . '…';
            }
            return $title;
        }

        if (preg_match('/^.{0,50}/us', $title, $matches) === 1) {
            $matched = $matches[0];
            return $matched === $title ? $title : $matched . '…';
        }

        return $title;
    }

    /**
     * Añade los atributos de evento sobre el elemento existente del formulario.
     *
     * Inserta justo después del nombre de la etiqueta, de modo que funciona
     * igual con `<button …>` y con `<input type="submit" … />` sin dejar un `/`
     * suelto en medio de la etiqueta.
     *
     * @param string $button HTML del botón de envío de comentarios.
     * @return string
     */
    public static function filterCommentSubmitButton($button)
    {
        $settings = self::getSettings();
        if (empty($settings['track_comments']) || !is_singular()) {
            return $button;
        }

        $post_id = (int) get_the_ID();
        $title = self::truncateTitle((string) get_the_title($post_id));

        $attributes = ' data-umami-event="comment"'
            . ' data-umami-event-post-id="' . esc_attr($post_id) . '"'
            . ' data-umami-event-post-title="' . esc_attr($title) . '"';

        $result = preg_replace_callback(
            '/^<(button|input)\b/i',
            static function ($matches) use ($attributes) {
                return '<' . $matches[1] . $attributes;
            },
            $button,
            1
        );

        return is_string($result) ? $result : $button;
    }

    /**
     * Guarda los ajustes enviados por POST (nonce + manage_options).
     *
     * @return void
     */
    public static function maybeSaveSettings()
    {
        if (!isset($_POST['atareao_analytics_save'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('atareao_analytics_save', 'atareao_analytics_nonce');

        $input = array();
        foreach (array_keys(self::defaults()) as $key) {
            $field = self::OPTION_PREFIX . $key;
            if (array_key_exists($field, $_POST)) {
                $input[$key] = wp_unslash($_POST[$field]);
            }
        }

        $settings = self::sanitizeSettings($input);
        foreach ($settings as $key => $value) {
            update_option(self::OPTION_PREFIX . $key, $value);
        }
        set_transient('atareao_analytics_notice', 'saved', 30);
    }

    /**
     * Definición de los campos agrupados del panel.
     *
     * @return array
     */
    private static function fieldGroups()
    {
        return array(
            'script' => array(
                'label' => __('Script e identificación', 'atareao-functionality'),
                'fields' => array(
                    'enabled' => array(
                        'type' => 'checkbox',
                        'label' => __('Activar la analítica', 'atareao-functionality'),
                    ),
                    'script_url' => array(
                        'type' => 'url',
                        'label' => __('URL del script', 'atareao-functionality'),
                    ),
                    'website_id' => array(
                        'type' => 'text',
                        'label' => __('Website ID', 'atareao-functionality'),
                    ),
                    'host_url' => array(
                        'type' => 'url',
                        'label' => __('Host URL', 'atareao-functionality'),
                    ),
                    'use_host_url' => array(
                        'type' => 'checkbox',
                        'label' => __('Usar Host URL', 'atareao-functionality'),
                    ),
                    'integrity' => array(
                        'type' => 'text',
                        'label' => __('Integrity (SRI)', 'atareao-functionality'),
                    ),
                ),
            ),
            'behaviour' => array(
                'label' => __('Comportamiento del tracker', 'atareao-functionality'),
                'fields' => array(
                    'ignore_admins' => array(
                        'type' => 'checkbox',
                        'label' => __('No contar a administradores', 'atareao-functionality'),
                    ),
                    'auto_track' => array(
                        'type' => 'checkbox',
                        'label' => __('Auto-track', 'atareao-functionality'),
                    ),
                    'do_not_track' => array(
                        'type' => 'checkbox',
                        'label' => __('Do Not Track', 'atareao-functionality'),
                    ),
                    'cache' => array(
                        'type' => 'checkbox',
                        'label' => __('Cache', 'atareao-functionality'),
                    ),
                ),
            ),
            'exclusions' => array(
                'label' => __('Exclusiones', 'atareao-functionality'),
                'fields' => array(
                    'skip_404' => array(
                        'type' => 'checkbox',
                        'label' => __('No emitir en páginas 404', 'atareao-functionality'),
                    ),
                    'skip_search' => array(
                        'type' => 'checkbox',
                        'label' => __('No emitir en búsquedas', 'atareao-functionality'),
                    ),
                    'exclude_search' => array(
                        'type' => 'checkbox',
                        'label' => __('Excluir búsquedas del tracker', 'atareao-functionality'),
                    ),
                    'exclude_hash' => array(
                        'type' => 'checkbox',
                        'label' => __('Excluir fragmentos (#) del tracker', 'atareao-functionality'),
                    ),
                ),
            ),
            'comments' => array(
                'label' => __('Comentarios', 'atareao-functionality'),
                'fields' => array(
                    'track_comments' => array(
                        'type' => 'checkbox',
                        'label' => __('Evento al enviar comentarios', 'atareao-functionality'),
                    ),
                ),
            ),
        );
    }

    /**
     * Pinta un campo del formulario.
     *
     * @param string $key   Clave corta.
     * @param array  $field Definición del campo.
     * @param mixed  $value Valor actual.
     * @return void
     */
    private static function renderField($key, array $field, $value)
    {
        $option = self::OPTION_PREFIX . $key;
        echo '<tr><th scope="row"><label for="' . esc_attr($option) . '">'
            . esc_html($field['label']) . '</label></th><td>';

        if ($field['type'] === 'checkbox') {
            $checked = ((int) $value) === 1 ? ' checked="checked"' : '';
            echo '<label><input type="checkbox" id="' . esc_attr($option) . '" name="' . esc_attr($option)
                . '" value="1"' . $checked . ' /> ' . esc_html($field['label']) . '</label>';
        } else {
            echo '<input type="' . esc_attr($field['type']) . '" class="regular-text" id="' . esc_attr($option)
                . '" name="' . esc_attr($option) . '" value="' . esc_attr($value) . '" />';
        }

        echo '</td></tr>';
    }

    /**
     * Renderiza la página Ajustes → Analítica (formulario, importación y avisos).
     *
     * @return void
     */
    public static function renderSettingsPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $message = '';
        if (isset($_POST['atareao_analytics_import'])) {
            check_admin_referer('atareao_analytics_import', 'atareao_analytics_import_nonce');
            $count = self::importLegacy();
            if ($count > 0) {
                $message = sprintf(
                    __('Se importaron %d ajustes desde Integrate Umami.', 'atareao-functionality'),
                    $count
                );
            } else {
                $message = __('No se encontró configuración de Integrate Umami que importar.', 'atareao-functionality');
            }
        } elseif (get_transient('atareao_analytics_notice') === 'saved') {
            delete_transient('atareao_analytics_notice');
            $message = __('Ajustes guardados.', 'atareao-functionality');
        }

        $settings = self::getSettings();

        if ($message !== '') {
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        }
        if (self::legacyPluginLoaded()) {
            echo '<div class="notice notice-warning"><p>' . esc_html__(
                'El plugin Integrate Umami sigue activo. Desactívalo cuando termines la migración.',
                'atareao-functionality'
            ) . '</p></div>';
        }
        if (self::shouldWarnAnalyticsOff()) {
            echo '<div class="notice notice-error"><p>' . esc_html__(
                'La analítica está desactivada o incompleta y hay una copia guardada de la configuración de '
                . 'Integrate Umami. Puedes importarla o activar los ajustes para no perder el registro de '
                . 'visitas en silencio.',
                'atareao-functionality'
            ) . '</p></div>';
        }

        echo '<form method="post">';
        wp_nonce_field('atareao_analytics_save', 'atareao_analytics_nonce');
        foreach (self::fieldGroups() as $group) {
            echo '<h2>' . esc_html($group['label']) . '</h2>';
            echo '<table class="form-table" role="presentation"><tbody>';
            foreach ($group['fields'] as $key => $field) {
                self::renderField($key, $field, $settings[$key]);
            }
            echo '</tbody></table>';
        }
        echo '<p><input type="submit" name="atareao_analytics_save" class="button button-primary" value="'
            . esc_attr__('Guardar', 'atareao-functionality') . '"></p>';
        echo '</form>';

        echo '<form method="post">';
        wp_nonce_field('atareao_analytics_import', 'atareao_analytics_import_nonce');
        echo '<p><input type="submit" name="atareao_analytics_import" class="button" value="'
            . esc_attr__('Importar ajustes de Integrate Umami', 'atareao-functionality') . '"></p>';
        echo '</form>';

        echo '<p class="description">' . esc_html__(
            'Desactivar este plugin no borra los ajustes: las opciones atareao_umami_* permanecen.',
            'atareao-functionality'
        ) . '</p>';
    }
}
