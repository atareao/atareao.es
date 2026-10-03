<?php
/**
 * Theme Options — social links and podcast feed settings
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class ThemeOptions
{

    /**
     * Inicializar
     */
    public static function init()
    {
        // Prioridad 20: ThemeOptions::init() corre dentro del callback de `init`
        // (prioridad 10) del bootstrap; registrar a la prioridad en curso no se
        // ejecutaría y las opciones (con show_in_rest) quedarían sin registrar.
        add_action('init', array(__CLASS__, 'registerSettings'), 20);
    }

    /**
     * Register settings for social links and podcast feed
     */
    public static function registerSettings()
    {
        $social_keys = array('youtube', 'ivoox', 'spotify', 'apple', 'telegram', 'x', 'mastodon', 'github', 'linkedin');
        foreach ($social_keys as $key) {
            register_setting(
                'atareao_options_group',
                'atareao_social_' . $key,
                array('sanitize_callback' => 'esc_url_raw')
            );
        }
        register_setting(
            'atareao_options_group',
            'atareao_podcast_feed',
            array('sanitize_callback' => 'esc_url_raw')
        );

        register_setting(
            'atareao_options_group',
            'atareao_opengist_server',
            array(
                'sanitize_callback' => 'esc_url_raw',
                'show_in_rest' => true,
                'default' => '',
            )
        );

        register_setting(
            'atareao_options_group',
            'atareao_opengist_username',
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => true,
                'default' => '',
            )
        );

        register_setting(
            'atareao_options_group',
            'atareao_opengist_allowed_hosts',
            array(
                'sanitize_callback' => array(__CLASS__, 'sanitizeAllowedHosts'),
                'show_in_rest' => true,
                'default' => '',
            )
        );
    }

    /**
     * Sanear la lista de hosts permitidos: una entrada por línea o coma,
     * saneada con `sanitize_text_field` por entrada.
     *
     * @param string $value Valor recibido del formulario.
     * @return string Entradas limpias separadas por saltos de línea.
     */
    public static function sanitizeAllowedHosts($value)
    {
        $clean = array();
        foreach (preg_split('/[\r\n,]+/', (string) $value) as $entry) {
            $entry = sanitize_text_field(trim($entry));
            if ($entry !== '') {
                $clean[] = $entry;
            }
        }
        return implode("\n", $clean);
    }

    /**
     * Render Theme Options page
     */
    public static function renderOptionsPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        settings_errors();
        $social = array(
            'youtube' => 'YouTube',
            'ivoox'   => 'iVoox',
            'spotify' => 'Spotify',
            'apple'   => 'Apple Podcasts',
            'telegram' => 'Telegram',
            'x'       => 'X',
            'mastodon' => 'Mastodon',
            'github'  => 'GitHub',
            'linkedin' => 'LinkedIn',
        );

        ?>
        <form method="post" action="options.php">
            <?php settings_fields('atareao_options_group'); ?>
            <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(Settings::tabUrl('tema')); ?>" />
            <table class="form-table" role="presentation">
                <tbody>
                <?php foreach ($social as $key => $label) :
                    $option_name = 'atareao_social_' . $key;
                    $value = esc_url(get_option($option_name)); ?>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?> URL</label></th>
                        <td>
                            <input name="<?php echo esc_attr($option_name); ?>" type="url" id="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" />
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php $podcast_feed_val = esc_url(get_option('atareao_podcast_feed')); ?>
                <tr>
                    <th scope="row"><label for="atareao_podcast_feed"><?php esc_html_e('Podcast feed URL', 'atareao-functionality'); ?></label></th>
                    <td>
                        <input name="atareao_podcast_feed" type="url" id="atareao_podcast_feed" value="<?php echo esc_attr($podcast_feed_val); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Optional: override the automatic podcast archive feed URL.', 'atareao-functionality'); ?></p>
                    </td>
                </tr>

                <?php $opengist_server_val = esc_url(get_option('atareao_opengist_server')); ?>
                <tr>
                    <th scope="row"><label for="atareao_opengist_server"><?php esc_html_e('OpenGist Server URL', 'atareao-functionality'); ?></label></th>
                    <td>
                        <input name="atareao_opengist_server" type="url" id="atareao_opengist_server" value="<?php echo esc_attr($opengist_server_val); ?>" class="regular-text" placeholder="https://gist.atareao.es" />
                        <p class="description"><?php esc_html_e('Default OpenGist server URL for the Gist block.', 'atareao-functionality'); ?></p>
                    </td>
                </tr>

                <?php $opengist_username_val = get_option('atareao_opengist_username'); ?>
                <tr>
                    <th scope="row"><label for="atareao_opengist_username"><?php esc_html_e('OpenGist Username', 'atareao-functionality'); ?></label></th>
                    <td>
                        <input name="atareao_opengist_username" type="text" id="atareao_opengist_username" value="<?php echo esc_attr($opengist_username_val); ?>" class="regular-text" placeholder="atareao" />
                        <p class="description"><?php esc_html_e('Default OpenGist username for the Gist block.', 'atareao-functionality'); ?></p>
                    </td>
                </tr>

                <?php $opengist_allowed_val = get_option('atareao_opengist_allowed_hosts'); ?>
                <tr>
                    <th scope="row"><label for="atareao_opengist_allowed_hosts">
                        <?php esc_html_e('Allowed Hosts', 'atareao-functionality'); ?></label></th>
                    <td>
                        <textarea name="atareao_opengist_allowed_hosts" id="atareao_opengist_allowed_hosts"
                            rows="3" class="regular-text"><?php echo esc_textarea($opengist_allowed_val); ?></textarea>
                        <p class="description"><?php esc_html_e('Listed hosts only.', 'atareao-functionality'); ?></p>
                    </td>
                </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }
}
