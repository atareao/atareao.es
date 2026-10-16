<?php
/**
 * Valet API Configuration & Notifications
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Valet Notifications integration handler.
 */
class ValetConfig
{
    /**
     * Presupuesto máximo del cuerpo de la notificación, en bytes.
     *
     * Coincide con el límite duro que impone `sendValetNotification()`.
     *
     * @var int
     */
    const MESSAGE_MAX_BYTES = 4096;

    /**
     * Inicializar hooks de Valet.
     *
     * Se registran dos rutas complementarias para cubrir los dos caminos por
     * los que un comentario puede quedar aprobado:
     *
     * - `comment_post`: comentario insertado ya aprobado.
     * - `transition_comment_status`: comentario pendiente que se aprueba
     *   después desde el panel (usa `wp_set_comment_status()`).
     *
     * @return void
     */
    public static function init()
    {
        add_action('comment_post', array(__CLASS__, 'notifyOnComment'), 10, 2);
        add_action('transition_comment_status', array(__CLASS__, 'notifyOnCommentTransition'), 10, 3);
    }

    /**
     * Render Valet API configuration page in settings hub.
     *
     * @return void
     */
    public static function renderConfigPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['atareao_valet_save'])) {
            check_admin_referer('atareao_valet_config', 'atareao_valet_nonce');
            $url = isset($_POST['atareao_valet_url']) ? esc_url_raw(wp_unslash($_POST['atareao_valet_url'])) : '';
            $token = isset($_POST['atareao_valet_token'])
                ? sanitize_text_field(wp_unslash($_POST['atareao_valet_token']))
                : '';
            update_option('atareao_valet_url', $url);
            /*
             * El formulario nunca reimprime el token almacenado (campo
             * `password` sin `value`), así que enviarlo vacío no puede
             * significar «borrar». Un POST vacío o con solo espacios
             * conserva el token anterior; solo se actualiza si llega valor.
             */
            if (trim($token) !== '') {
                update_option('atareao_valet_token', $token);
            }
            echo '<div class="updated"><p>' . esc_html__(
                'Configuración guardada.',
                'atareao-functionality'
            ) . '</p></div>';
        }

        if (isset($_POST['atareao_valet_test'])) {
            check_admin_referer('atareao_valet_config', 'atareao_valet_nonce');
            $result = self::sendTestMessage();
            if ($result === true) {
                echo '<div class="updated"><p>' . esc_html__(
                    'Mensaje de prueba enviado correctamente a Valet.',
                    'atareao-functionality'
                ) . '</p></div>';
            } else {
                echo '<div class="error"><p>' . esc_html($result) . '</p></div>';
            }
        }

        $url = get_option('atareao_valet_url', '');
        ?>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>
                        <label for="atareao_valet_url">
                            <?php esc_html_e('Valet URL', 'atareao-functionality'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="atareao_valet_url"
                            name="atareao_valet_url"
                            value="<?php echo esc_attr($url); ?>"
                            style="width:400px;"
                            placeholder="https://valet.example.com"
                        >
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="atareao_valet_token">
                            <?php esc_html_e('Access Token', 'atareao-functionality'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="atareao_valet_token"
                            name="atareao_valet_token"
                            autocomplete="new-password"
                            placeholder="••••••••"
                            style="width:400px;"
                        >
                    </td>
                </tr>
            </table>
            <?php wp_nonce_field('atareao_valet_config', 'atareao_valet_nonce'); ?>
            <p>
                <input
                    type="submit"
                    name="atareao_valet_save"
                    class="button button-primary"
                    value="<?php esc_attr_e('Guardar', 'atareao-functionality'); ?>"
                >
                <input
                    type="submit"
                    name="atareao_valet_test"
                    class="button"
                    value="<?php esc_attr_e('Enviar mensaje de prueba', 'atareao-functionality'); ?>"
                >
            </p>
        </form>
        <?php
    }

    /**
     * Send a notification to the configured Valet server.
     *
     * @param string $message The notification message body.
     * @param string $origin  Optional origin identifier (max 64 bytes).
     * @param string $author  Optional author identifier (max 128 bytes).
     * @param string $level   Optional level: info, warning, critical.
     * @return true|string True on success, error message string on failure.
     */
    public static function sendValetNotification($message, $origin = '', $author = '', $level = 'info')
    {
        $valet_url = esc_url_raw(get_option('atareao_valet_url', ''));
        $valet_token = sanitize_text_field(get_option('atareao_valet_token', ''));

        if (empty($valet_url) || empty($valet_token)) {
            return __('Configura la URL y el Token de Valet antes de enviar.', 'atareao-functionality');
        }

        $trimmed_message = trim($message);
        if ($trimmed_message === '') {
            return __('El mensaje es obligatorio.', 'atareao-functionality');
        }

        if (strlen($trimmed_message) > 4096) {
            return __('El mensaje no puede superar los 4096 bytes.', 'atareao-functionality');
        }

        $origin = trim($origin);
        if (empty($origin)) {
            $origin = parse_url(home_url(), PHP_URL_HOST) ?: 'atareao.es';
        }
        if (strlen($origin) > 64) {
            $origin = substr($origin, 0, 64);
        }

        $author = trim($author);
        if (strlen($author) > 128) {
            $author = substr($author, 0, 128);
        }

        $valid_levels = array('info', 'warning', 'critical');
        if (!in_array($level, $valid_levels, true)) {
            $level = 'info';
        }

        $endpoint = rtrim($valet_url, '/') . '/api/notifications';
        $payload = array(
            'message' => $trimmed_message,
            'origin'  => $origin,
            'author'  => $author,
            'level'   => $level,
        );

        $args = array(
            'method'  => 'POST',
            'body'    => wp_json_encode($payload),
            'headers' => array(
                'Authorization' => 'Bearer ' . $valet_token,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 10,
        );

        $response = wp_remote_post($endpoint, $args);

        $error_msg = __(
            'Error al enviar la notificación a Valet. Inténtalo de nuevo más tarde.',
            'atareao-functionality'
        );

        if (is_wp_error($response)) {
            return $error_msg;
        }

        $response_code = intval(wp_remote_retrieve_response_code($response));
        if ($response_code >= 200 && $response_code < 300) {
            return true;
        }

        return $error_msg;
    }

    /**
     * Send a test notification to the configured Valet server.
     *
     * @return true|string
     */
    public static function sendTestMessage()
    {
        $host = parse_url(home_url(), PHP_URL_HOST) ?: 'atareao.es';
        $message = sprintf(
            "Mensaje de prueba desde %s\nSi ves esto, la integración con Valet funciona correctamente.",
            $host
        );

        return self::sendValetNotification($message, $host, 'WordPress', 'info');
    }

    /**
     * Send Valet notification when an approved comment is posted.
     *
     * Enganchado a `comment_post`, que WordPress dispara al **insertar** el
     * comentario. Un comentario insertado ya aprobado pasa por aquí y nunca
     * por `transition_comment_status`; uno insertado pendiente y aprobado más
     * tarde no pasa por aquí con `1` y lo cubre `notifyOnCommentTransition()`.
     * Por eso ambas rutas son mutuamente excluyentes y no hay doble aviso.
     *
     * @param int        $comment_id       Comment ID.
     * @param int|string $comment_approved 1 if approved, 0 if pending, 'spam' if spam.
     * @return void
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

        self::notifyApprovedComment($comment);
    }

    /**
     * Send Valet notification when a comment becomes approved.
     *
     * Enganchado a `transition_comment_status`, la acción que WordPress
     * dispara desde `wp_set_comment_status()` cuando un comentario cambia de
     * estado (p. ej. al aprobarlo desde el panel de administración). Sin este
     * hook, los comentarios moderados nunca notificarían: `comment_post` solo
     * se dispara en la inserción y en ese momento el estado era `0`.
     *
     * Solo notifica en la transición hacia `approved`; cualquier otro cambio
     * (a `spam`, `trash`, `hold`…) o la repetición de `approved` se ignora.
     *
     * @param string            $new_status New comment status.
     * @param string            $old_status Previous comment status.
     * @param \WP_Comment|mixed $comment    Comment object passed by WordPress.
     * @return void
     */
    public static function notifyOnCommentTransition($new_status, $old_status, $comment)
    {
        if ($new_status !== 'approved' || $old_status === 'approved') {
            return;
        }

        if (!is_object($comment)) {
            return;
        }

        self::notifyApprovedComment($comment);
    }

    /**
     * Notify Valet about an approved comment.
     *
     * El mensaje se construye garantizando por construcción que no supera
     * `MESSAGE_MAX_BYTES` bytes: primero la cabecera (autor, host, permalink)
     * y después el contenido recortado al presupuesto restante, con sufijo
     * `…` cuando se recorta. Así nunca se alcanza el límite duro de
     * `sendValetNotification()` y la notificación no se pierde.
     *
     * Si el envío falla, el error solo se registra cuando `WP_DEBUG` está
     * activo, y nunca incluye el token.
     *
     * @param \WP_Comment|object $comment Comment object.
     * @return void
     */
    private static function notifyApprovedComment($comment)
    {
        if (!is_object($comment)) {
            return;
        }

        $author = get_comment_author($comment);
        $content = wp_strip_all_tags(get_comment_text($comment));
        $host = parse_url(home_url(), PHP_URL_HOST) ?: 'atareao.es';
        $post_url = get_permalink($comment->comment_post_ID) ?: home_url('/');

        $header = sprintf("Comentario de %s en %s\n%s\n", $author, $host, $post_url);
        $suffix = "\xE2\x80\xA6"; // U+2026 HORIZONTAL ELLIPSIS (3 bytes en UTF-8).
        $budget = self::MESSAGE_MAX_BYTES - strlen($header) - strlen($suffix);

        if ($budget > 0) {
            $truncated = self::truncateBytes($content, $budget);
            if ($truncated !== $content) {
                $truncated .= $suffix;
            }
            $message = $header . $truncated;
        } else {
            // La propia cabecera agota el presupuesto: se recorta sin contenido.
            $message = self::truncateBytes($header, self::MESSAGE_MAX_BYTES);
        }

        $result = self::sendValetNotification($message, $host, $author, 'info');

        if (is_string($result) && defined('WP_DEBUG') && WP_DEBUG) {
            $comment_id = isset($comment->comment_ID) ? (int) $comment->comment_ID : 0;
            error_log(
                sprintf(
                    'Valet: no se pudo notificar el comentario %d: %s',
                    $comment_id,
                    $result
                )
            );
        }
    }

    /**
     * Truncate a UTF-8 string to a maximum number of bytes.
     *
     * Nunca parte un carácter multibyte: usa `mb_strcut()` si mbstring está
     * disponible y, en su defecto, descarta la secuencia incompleta final.
     *
     * @param string $text      Text to truncate.
     * @param int    $max_bytes Maximum byte length of the result.
     * @return string Truncated text (never longer than $max_bytes bytes).
     */
    private static function truncateBytes($text, $max_bytes)
    {
        $max_bytes = (int) $max_bytes;
        if ($max_bytes <= 0) {
            return '';
        }

        if (strlen($text) <= $max_bytes) {
            return $text;
        }

        if (function_exists('mb_strcut')) {
            return (string) mb_strcut($text, 0, $max_bytes, 'UTF-8');
        }

        $cut = substr($text, 0, $max_bytes);
        for ($i = 0; $i < 3 && $cut !== ''; $i++) {
            if (preg_match('//u', $cut) === 1) {
                break;
            }
            $cut = substr($cut, 0, -1);
        }

        return $cut;
    }
}
