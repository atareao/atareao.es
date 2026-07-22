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
        add_action('admin_init', array(__CLASS__, 'registerSettings'));
        add_action('admin_menu', array(__CLASS__, 'addOptionsPage'));
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
    }

    /**
     * Add Theme Options page to Appearance menu
     */
    public static function addOptionsPage()
    {
        add_theme_page(
            __('Atareao Theme Options', 'atareao-functionality'),
            __('Theme Options', 'atareao-functionality'),
            'manage_options',
            'atareao-theme-options',
            array(__CLASS__, 'renderOptionsPage')
        );
    }

    /**
     * Render Theme Options page
     */
    public static function renderOptionsPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
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
        <div class="wrap">
            <h1><?php esc_html_e('Atareao Theme Options', 'atareao-functionality'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('atareao_options_group'); ?>
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
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
