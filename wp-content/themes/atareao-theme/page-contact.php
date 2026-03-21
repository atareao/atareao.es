<?php
// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * Template Name: Contact Page
 * Description: Custom contact page with form.
 * @package Atareao_Theme
 */

get_header();
?>


<main id="primary" class="site-main">
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <h1 class="entry-title" style="text-align:center;margin-bottom:2rem;letter-spacing:1px;">
                <?php esc_html_e('Contactar', 'atareao-theme'); ?>
            </h1>
        </header>
        <?php
        // Simple math captcha
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $captcha_a = rand(1, 9);
            $captcha_b = rand(1, 9);
            $captcha_sum = $captcha_a + $captcha_b;
            $_SESSION['atareao_captcha'] = $captcha_sum;
            $_SESSION['atareao_captcha_a'] = $captcha_a;
            $_SESSION['atareao_captcha_b'] = $captcha_b;
        } else {
            $captcha_a = isset($_SESSION['atareao_captcha_a']) ? $_SESSION['atareao_captcha_a'] : rand(1, 9);
            $captcha_b = isset($_SESSION['atareao_captcha_b']) ? $_SESSION['atareao_captcha_b'] : rand(1, 9);
        }
        // CSRF token
        if (empty($_SESSION['atareao_csrf_token'])) {
            $_SESSION['atareao_csrf_token'] = bin2hex(random_bytes(32));
        }
        // Time-based anti-spam: store form render time
        $_SESSION['atareao_form_time'] = time();
        ?>
        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <?php
                // Display the page content from the editor (if any)
                the_content();
                ?>
            </div>
            <form method="post" action="" class="atareao-contact-form">
                <input type="hidden" name="atareao_csrf_token" value="<?php echo esc_attr($_SESSION['atareao_csrf_token']); ?>">
                <input type="hidden" name="atareao_form_time" value="<?php echo esc_attr($_SESSION['atareao_form_time']); ?>">
                <div>
                    <label for="contact_name_email">
                        <?php esc_html_e('Nombre o Email', 'atareao-theme'); ?>
                    </label>
                    <input type="text" id="contact_name_email" name="contact_name_email" required>
                </div>
                <div>
                    <label for="contact_content">
                        <?php esc_html_e('Contenido', 'atareao-theme'); ?>
                    </label>
                    <textarea id="contact_content" name="contact_content" rows="6" required></textarea>
                </div>
                <!-- Honeypot anti-spam field (hidden from users) -->
                <div style="display:none;">
                    <label for="atareao_hp">Dejar vacío</label>
                    <input type="text" id="atareao_hp" name="atareao_hp" autocomplete="off">
                </div>
                <div>
                    <label for="atareao_captcha_answer">
                        <?php echo esc_html__('¿Cuánto es ', 'atareao-theme') . $captcha_a . ' + ' . $captcha_b . '?'; ?>
                    </label>
                    <input type="number" id="atareao_captcha_answer" name="atareao_captcha_answer" required>
                </div>
                <div style="text-align:center;">
                    <button type="submit">
                        <?php esc_html_e('Enviar', 'atareao-theme'); ?>
                    </button>
                </div>
                <?php
                $show_success = false;
                $show_error = false;
                $error_msg = '';
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $user_captcha = isset($_POST['atareao_captcha_answer']) ? intval($_POST['atareao_captcha_answer']) : null;
                    $expected_captcha = isset($_SESSION['atareao_captcha']) ? intval($_SESSION['atareao_captcha']) : null;
                    $honeypot = isset($_POST['atareao_hp']) ? trim($_POST['atareao_hp']) : '';
                    $csrf_token = isset($_POST['atareao_csrf_token']) ? $_POST['atareao_csrf_token'] : '';
                    $expected_token = isset($_SESSION['atareao_csrf_token']) ? $_SESSION['atareao_csrf_token'] : '';
                    $form_time = isset($_POST['atareao_form_time']) ? intval($_POST['atareao_form_time']) : 0;
                    $now = time(); 
                    $min_seconds = 3;
                    $max_seconds = 3600;
                    $valid = true;
                    if (!hash_equals($expected_token, $csrf_token)) {
                        $valid = false;
                        $error_msg = esc_html__('Token de seguridad inválido.', 'atareao-theme');
                    } elseif ($now - $form_time < $min_seconds) {
                        $valid = false;
                        $error_msg = esc_html__('Formulario enviado demasiado rápido.', 'atareao-theme');
                    } elseif ($now - $form_time > $max_seconds) {
                        $valid = false;
                        $error_msg = esc_html__('El formulario ha expirado. Recarga la página.', 'atareao-theme');
                    } elseif (!empty($honeypot)) {
                        $valid = false;
                        $error_msg = esc_html__('Error de validación.', 'atareao-theme');
                    } elseif ($user_captcha !== $expected_captcha) {
                        $valid = false;
                        $error_msg = esc_html__('Captcha incorrecto. Inténtalo de nuevo.', 'atareao-theme');
                    }
                    if ($valid) {
                        // Send to Matrix API
                        $matrix_url = sanitize_text_field(get_option('atareao_matrix_url'));
                        $matrix_token = sanitize_text_field(get_option('atareao_matrix_token'));
                        $matrix_room = sanitize_text_field(get_option('atareao_matrix_room'));
                        $name_email = isset($_POST['contact_name_email']) ? sanitize_text_field($_POST['contact_name_email']) : '';
                        $content = isset($_POST['contact_content']) ? sanitize_textarea_field($_POST['contact_content']) : '';
                        $author = $name_email;
                        $host = parse_url(home_url(), PHP_URL_HOST) ? parse_url(home_url(), PHP_URL_HOST) : 'atareao.es';
                        $message = sprintf("Contacto de %s en %s\n%s", $author, $host, $content);
                        $txn_id = uniqid('wp_', true);
                        $endpoint = rtrim($matrix_url, '/') . "/_matrix/client/v3/rooms/$matrix_room/send/m.room.message/$txn_id";
                        $payload = array(
                            'msgtype' => 'm.text',
                            'body' => $message
                        );
                        $args = array(
                            'method' => 'PUT',
                            'body' => json_encode($payload),
                            'headers' => array(
                                'Authorization' => 'Bearer ' . $matrix_token,
                                'Content-Type' => 'application/json'
                            ),
                            'timeout' => 10,
                        );
                        $response = wp_remote_request($endpoint, $args);
                        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                            $show_success = true;
                        } else {
                            $show_error = true;
                            if (is_wp_error($response)) {
                                $error_msg = $response->get_error_message();
                            } else {
                                $error_msg = wp_remote_retrieve_body($response);
                            }
                        }
                    }
                }
                if ($show_success) {
                    echo '<div class="atareao-feedback-success" style="color:green;text-align:center;margin-bottom:1rem;">'.esc_html__('Mensaje enviado', 'atareao-theme').'</div>';
                } elseif ($show_error) {
                    echo '<div class="atareao-feedback-error" style="color:red;text-align:center;margin-bottom:1rem;">'.esc_html__('No se pudo enviar el mensaje', 'atareao-theme').'<br><small>' . esc_html($error_msg) . '</small></div>';
                }
                if ($show_success || $show_error) {
                ?>
                <script>
                    setTimeout(function() {
                        var msg = document.querySelector('.atareao-feedback-success, .atareao-feedback-error');
                        if(msg) msg.style.display = 'none';
                    }, 5000);
                </script>
                <?php }
                ?>
            </form>
        </div>
    </article>
</main>


<?php
// Captcha, honeypot, CSRF, and time-based validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // session_start() already called at top
    $user_captcha = isset($_POST['atareao_captcha_answer']) ? intval($_POST['atareao_captcha_answer']) : null;
    $expected_captcha = isset($_SESSION['atareao_captcha']) ? intval($_SESSION['atareao_captcha']) : null;
    $honeypot = isset($_POST['atareao_hp']) ? trim($_POST['atareao_hp']) : '';
    $csrf_token = isset($_POST['atareao_csrf_token']) ? $_POST['atareao_csrf_token'] : '';
    $expected_token = isset($_SESSION['atareao_csrf_token']) ? $_SESSION['atareao_csrf_token'] : '';
    $form_time = isset($_POST['atareao_form_time']) ? intval($_POST['atareao_form_time']) : 0;
    $now = time();
    $min_seconds = 3; // Minimum time to fill form (anti-bot)
    $max_seconds = 3600; // 1 hour max
    if (!hash_equals($expected_token, $csrf_token)) {
        echo '<div style="color:red;text-align:center;margin-top:1rem;">'.esc_html__('Token de seguridad inválido.', 'atareao-theme').'</div>';
    } elseif ($now - $form_time < $min_seconds) {
        echo '<div style="color:red;text-align:center;margin-top:1rem;">'.esc_html__('Formulario enviado demasiado rápido.', 'atareao-theme').'</div>';
    } elseif ($now - $form_time > $max_seconds) {
        echo '<div style="color:red;text-align:center;margin-top:1rem;">'.esc_html__('El formulario ha expirado. Recarga la página.', 'atareao-theme').'</div>';
    } elseif (!empty($honeypot)) {
        echo '<div style="color:red;text-align:center;margin-top:1rem;">'.esc_html__('Error de validación.', 'atareao-theme').'</div>';
    } elseif ($user_captcha !== $expected_captcha) {
        // Captcha error handled above button
    }
}
?>


<?php get_footer(); ?>
