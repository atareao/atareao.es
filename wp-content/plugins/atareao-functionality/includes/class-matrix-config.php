<?php
/**
 * Matrix API Configuration & Notifications
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class MatrixConfig
{

    /**
     * Inicializar
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'addConfigPage'));
        add_action('comment_post', array(__CLASS__, 'notifyOnComment'), 10, 2);
    }

    /**
     * Add Matrix API configuration page
     */
    public static function addConfigPage()
    {
        add_options_page(
            __('Matrix API Configuración', 'atareao-functionality'),
            __('Matrix API', 'atareao-functionality'),
            'manage_options',
            'atareao-matrix-config',
            array(__CLASS__, 'renderConfigPage')
        );
    }

    /**
     * Render Matrix API configuration page
     */
    public static function renderConfigPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_POST['atareao_matrix_save'])) {
            update_option('atareao_matrix_url', sanitize_text_field($_POST['atareao_matrix_url']));
            update_option('atareao_matrix_token', sanitize_text_field($_POST['atareao_matrix_token']));
            update_option('atareao_matrix_room', sanitize_text_field($_POST['atareao_matrix_room']));
            echo '<div class="updated"><p>' . esc_html__(
                'Configuración guardada.',
                'atareao-functionality'
            ) . '</p></div>';
        }
        $url = get_option('atareao_matrix_url', '');
        $token = get_option('atareao_matrix_token', '');
        $room = get_option('atareao_matrix_room', '');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración Matrix API', 'atareao-functionality'); ?></h1>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="atareao_matrix_url">Matrix URL</label></th>
                        <td><input type="text" id="atareao_matrix_url" name="atareao_matrix_url" value="<?php echo esc_attr($url); ?>" style="width:400px;"></td>
                    </tr>
                    <tr>
                        <th><label for="atareao_matrix_token">Access Token</label></th>
                        <td><input type="text" id="atareao_matrix_token" name="atareao_matrix_token" value="<?php echo esc_attr($token); ?>" style="width:400px;"></td>
                    </tr>
                    <tr>
                        <th><label for="atareao_matrix_room">Room ID</label></th>
                        <td><input type="text" id="atareao_matrix_room" name="atareao_matrix_room" value="<?php echo esc_attr($room); ?>" style="width:400px;"></td>
                    </tr>
                </table>
                <p><input type="submit" name="atareao_matrix_save" class="button button-primary" value="<?php esc_attr_e('Guardar', 'atareao-functionality'); ?>"></p>
            </form>
        </div>
        <?php
    }

    /**
     * Send Matrix notification when a comment is posted
     */
    public static function notifyOnComment($comment_id, $comment_approved)
    {
        if (intval($comment_approved) !== 1 && $comment_approved !== '1') {
            return;
        }
        $comment = get_comment($comment_id);
        if (!$comment) {
            return;
        }

        $author = get_comment_author($comment);
        $content = wp_strip_all_tags(get_comment_text($comment));
        $host = parse_url(home_url(), PHP_URL_HOST) ? parse_url(home_url(), PHP_URL_HOST) : 'atareao.es';
        $post_url = get_permalink($comment->comment_post_ID);
        if (!$post_url) {
            $post_url = home_url('/');
        }
        $message = sprintf(
            "Comentario de %s en %s\n%s\n%s",
            $author,
            $host,
            $post_url,
            $content
        );

        $matrix_url = sanitize_text_field(get_option('atareao_matrix_url'));
        $matrix_token = sanitize_text_field(get_option('atareao_matrix_token'));
        $matrix_room = sanitize_text_field(get_option('atareao_matrix_room'));

        if (empty($matrix_url) || empty($matrix_token) || empty($matrix_room)) {
            return;
        }

        $txn_id = uniqid('wp_comment_', true);
        $endpoint = rtrim($matrix_url, '/') . "/_matrix/client/v3/rooms/$matrix_room/send/m.room.message/$txn_id";
        $payload = array(
            'msgtype' => 'm.text',
            'body' => $message,
        );
        $args = array(
            'method' => 'PUT',
            'body' => wp_json_encode($payload),
            'headers' => array(
                'Authorization' => 'Bearer ' . $matrix_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 10,
        );

        wp_remote_request($endpoint, $args);
    }
}
