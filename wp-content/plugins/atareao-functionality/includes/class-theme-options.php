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
            'atareao_podcast_cover',
            array('sanitize_callback' => 'esc_url_raw')
        );
        register_setting(
            'atareao_options_group',
            'atareao_podcast_author',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'atareao_options_group',
            'atareao_podcast_language',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'atareao_options_group',
            'atareao_podcast_category',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'atareao_options_group',
            'atareao_podcast_owner_name',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'atareao_options_group',
            'atareao_podcast_owner_email',
            array('sanitize_callback' => 'sanitize_email')
        );
        register_setting(
            'atareao_options_group',
            'atareao_podcast_type',
            array('sanitize_callback' => array(__CLASS__, 'sanitizePodcastType'))
        );
        register_setting(
            'atareao_options_group',
            'atareao_podcast_explicit',
            array('sanitize_callback' => array(__CLASS__, 'sanitizePodcastExplicit'))
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

        register_setting(
            'atareao_options_group',
            'atareao_mcp_registry_proof',
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
                'default'           => '',
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
     * Sanear el tipo del programa: solo `serial` o `episodic`.
     *
     * @param string $value Valor recibido del formulario.
     * @return string
     */
    public static function sanitizePodcastType($value)
    {
        $value = sanitize_title((string) $value);

        return in_array($value, array('serial', 'episodic'), true) ? $value : 'serial';
    }

    /**
     * Sanear el flag de contenido explícito: `no`, `yes` o `clean`.
     *
     * @param string $value Valor recibido del formulario.
     * @return string
     */
    public static function sanitizePodcastExplicit($value)
    {
        $value = sanitize_title((string) $value);

        return in_array($value, array('no', 'yes', 'clean'), true) ? $value : 'no';
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

                <?php $podcast_cover_val = esc_url(get_option('atareao_podcast_cover')); ?>
                <tr>
                    <th scope="row"><label for="atareao_podcast_cover">
                        <?php esc_html_e('Podcast cover URL', 'atareao-functionality'); ?></label></th>
                    <td>
                        <input
                            name="atareao_podcast_cover"
                            type="url"
                            id="atareao_podcast_cover"
                            value="<?php echo esc_attr($podcast_cover_val); ?>"
                            class="regular-text"
                            placeholder="https://atareao.es/portada.jpg"
                        />
                        <p class="description">
                        <?php
                        esc_html_e(
                            'Absolute HTTPS URL of the program cover (itunes:image).',
                            'atareao-functionality'
                        );
                        ?>
                        </p>
                    </td>
                </tr>

                <?php $podcast_author_val = get_option('atareao_podcast_author'); ?>
                <tr>
                    <th scope="row"><label for="atareao_podcast_author">
                        <?php esc_html_e('Podcast author', 'atareao-functionality'); ?></label></th>
                    <td>
                        <input
                            name="atareao_podcast_author"
                            type="text"
                            id="atareao_podcast_author"
                            value="<?php echo esc_attr($podcast_author_val); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                        <?php
                        esc_html_e(
                            'Defaults to the site name (itunes:author).',
                            'atareao-functionality'
                        );
                        ?>
                        </p>
                    </td>
                </tr>

                <?php $podcast_language_val = get_option('atareao_podcast_language'); ?>
                <tr>
                    <th scope="row"><label for="atareao_podcast_language">
                        <?php esc_html_e('Podcast language', 'atareao-functionality'); ?></label></th>
                    <td>
                        <input
                            name="atareao_podcast_language"
                            type="text"
                            id="atareao_podcast_language"
                            value="<?php echo esc_attr($podcast_language_val); ?>"
                            class="regular-text"
                            placeholder="es-ES"
                        />
                        <p class="description">
                        <?php
                        esc_html_e(
                            'Defaults to the site language (channel language and transcript language).',
                            'atareao-functionality'
                        );
                        ?>
                        </p>
                    </td>
                </tr>

                <?php $podcast_category_val = get_option('atareao_podcast_category'); ?>
                <tr>
                    <th scope="row"><label for="atareao_podcast_category">
                        <?php esc_html_e('Podcast category', 'atareao-functionality'); ?></label></th>
                    <td>
                        <input
                            name="atareao_podcast_category"
                            type="text"
                            id="atareao_podcast_category"
                            value="<?php echo esc_attr($podcast_category_val); ?>"
                            class="regular-text"
                            placeholder="Technology"
                        />
                        <p class="description">
                        <?php
                        esc_html_e(
                            'Defaults to Technology (itunes:category).',
                            'atareao-functionality'
                        );
                        ?>
                        </p>
                    </td>
                </tr>

                <?php $podcast_owner_name_val = get_option('atareao_podcast_owner_name'); ?>
                <tr>
                    <th scope="row"><label for="atareao_podcast_owner_name">
                        <?php esc_html_e('Owner name', 'atareao-functionality'); ?></label></th>
                    <td>
                        <input
                            name="atareao_podcast_owner_name"
                            type="text"
                            id="atareao_podcast_owner_name"
                            value="<?php echo esc_attr($podcast_owner_name_val); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                        <?php
                        esc_html_e(
                            'Defaults to the site name (itunes:owner/itunes:name).',
                            'atareao-functionality'
                        );
                        ?>
                        </p>
                    </td>
                </tr>

                <?php $podcast_owner_email_val = get_option('atareao_podcast_owner_email'); ?>
                <tr>
                    <th scope="row"><label for="atareao_podcast_owner_email">
                        <?php esc_html_e('Owner email', 'atareao-functionality'); ?></label></th>
                    <td>
                        <input
                            name="atareao_podcast_owner_email"
                            type="email"
                            id="atareao_podcast_owner_email"
                            value="<?php echo esc_attr($podcast_owner_email_val); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                        <?php
                        esc_html_e(
                            'Defaults to the administrator email (itunes:owner/itunes:email and podcast:locked owner).',
                            'atareao-functionality'
                        );
                        ?>
                        </p>
                    </td>
                </tr>

                <?php $podcast_type_val = get_option('atareao_podcast_type'); ?>
                <tr>
                    <th scope="row"><label for="atareao_podcast_type">
                        <?php esc_html_e('Podcast type', 'atareao-functionality'); ?></label></th>
                    <td>
                        <select name="atareao_podcast_type" id="atareao_podcast_type">
                            <option value="serial" <?php selected($podcast_type_val, 'serial'); ?>>
                                <?php esc_html_e('serial', 'atareao-functionality'); ?>
                            </option>
                            <option value="episodic" <?php selected($podcast_type_val, 'episodic'); ?>>
                                <?php esc_html_e('episodic', 'atareao-functionality'); ?>
                            </option>
                        </select>
                        <p class="description">
                        <?php
                        esc_html_e(
                            'Defaults to serial (itunes:type).',
                            'atareao-functionality'
                        );
                        ?>
                        </p>
                    </td>
                </tr>

                <?php $podcast_explicit_val = get_option('atareao_podcast_explicit'); ?>
                <tr>
                    <th scope="row"><label for="atareao_podcast_explicit">
                        <?php esc_html_e('Explicit content', 'atareao-functionality'); ?></label></th>
                    <td>
                        <select name="atareao_podcast_explicit" id="atareao_podcast_explicit">
                            <option value="no" <?php selected($podcast_explicit_val, 'no'); ?>>
                                <?php esc_html_e('no', 'atareao-functionality'); ?>
                            </option>
                            <option value="yes" <?php selected($podcast_explicit_val, 'yes'); ?>>
                                <?php esc_html_e('yes', 'atareao-functionality'); ?>
                            </option>
                            <option value="clean" <?php selected($podcast_explicit_val, 'clean'); ?>>
                                <?php esc_html_e('clean', 'atareao-functionality'); ?>
                            </option>
                        </select>
                        <p class="description">
                        <?php
                        esc_html_e(
                            'Explicit flag required by directories (itunes:explicit). Defaults to no.',
                            'atareao-functionality'
                        );
                        ?>
                        </p>
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

                <?php $mcp_registry_proof_val = get_option('atareao_mcp_registry_proof'); ?>
                <tr>
                    <th scope="row"><label for="atareao_mcp_registry_proof">
                        <?php esc_html_e('MCP registry proof', 'atareao-functionality'); ?></label></th>
                    <td>
                        <input
                            name="atareao_mcp_registry_proof"
                            type="text"
                            id="atareao_mcp_registry_proof"
                            value="<?php echo esc_attr($mcp_registry_proof_val); ?>"
                            class="regular-text"
                            placeholder="v=MCPv1; k=ed25519; p=..."
                        />
                        <p class="description">
                            <?php esc_html_e('Public key only; never the private key.', 'atareao-functionality'); ?>
                        </p>
                    </td>
                </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }
}
