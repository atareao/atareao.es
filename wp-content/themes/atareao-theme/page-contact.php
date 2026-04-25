<?php
/**
 * Template Name: Contact Page
 * Description: Custom contact page with form.
 *
 * @package Atareao_Theme
 */

$show_success = false;
$show_error   = false;
$error_msg    = '';

$contact_name_email = '';
$contact_content    = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    $contact_name_email = isset( $_POST['contact_name_email'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name_email'] ) ) : '';
    $contact_content    = isset( $_POST['contact_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['contact_content'] ) ) : '';

    $honeypot       = isset( $_POST['atareao_hp'] ) ? trim( wp_unslash( $_POST['atareao_hp'] ) ) : '';
    $captcha_answer = isset( $_POST['atareao_captcha_answer'] ) ? intval( $_POST['atareao_captcha_answer'] ) : null;
    $captcha_a      = isset( $_POST['atareao_captcha_a'] ) ? intval( $_POST['atareao_captcha_a'] ) : 0;
    $captcha_b      = isset( $_POST['atareao_captcha_b'] ) ? intval( $_POST['atareao_captcha_b'] ) : 0;
    $captcha_sig    = isset( $_POST['atareao_captcha_sig'] ) ? sanitize_text_field( wp_unslash( $_POST['atareao_captcha_sig'] ) ) : '';
    $form_time      = isset( $_POST['atareao_form_time'] ) ? intval( $_POST['atareao_form_time'] ) : 0;

    $now         = time();
    $min_seconds = 3;
    $max_seconds = 3600;

    $expected_sig = hash_hmac( 'sha256', $captcha_a . ':' . $captcha_b, wp_salt( 'nonce' ) );

    if ( ! isset( $_POST['atareao_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atareao_contact_nonce'] ) ), 'atareao_contact_form' ) ) {
        $show_error = true;
        $error_msg  = esc_html__( 'Token de seguridad invalido.', 'atareao-theme' );
    } elseif ( empty( $contact_name_email ) || empty( $contact_content ) ) {
        $show_error = true;
        $error_msg  = esc_html__( 'Completa todos los campos obligatorios.', 'atareao-theme' );
    } elseif ( ! empty( $honeypot ) ) {
        $show_error = true;
        $error_msg  = esc_html__( 'Error de validacion.', 'atareao-theme' );
    } elseif ( ! $form_time || ( $now - $form_time ) < $min_seconds ) {
        $show_error = true;
        $error_msg  = esc_html__( 'Formulario enviado demasiado rapido.', 'atareao-theme' );
    } elseif ( ( $now - $form_time ) > $max_seconds ) {
        $show_error = true;
        $error_msg  = esc_html__( 'El formulario ha expirado. Recarga la pagina.', 'atareao-theme' );
    } elseif ( ! hash_equals( $expected_sig, $captcha_sig ) ) {
        $show_error = true;
        $error_msg  = esc_html__( 'No se pudo validar el captcha. Recarga la pagina.', 'atareao-theme' );
    } elseif ( $captcha_answer !== ( $captcha_a + $captcha_b ) ) {
        $show_error = true;
        $error_msg  = esc_html__( 'Captcha incorrecto. Intentalo de nuevo.', 'atareao-theme' );
    } else {
        $matrix_url   = esc_url_raw( get_option( 'atareao_matrix_url' ) );
        $matrix_token = sanitize_text_field( get_option( 'atareao_matrix_token' ) );
        $matrix_room  = sanitize_text_field( get_option( 'atareao_matrix_room' ) );

        if ( empty( $matrix_url ) || empty( $matrix_token ) || empty( $matrix_room ) ) {
            $show_error = true;
            $error_msg  = esc_html__( 'El formulario no esta configurado correctamente.', 'atareao-theme' );
        } else {
            $host    = parse_url( home_url(), PHP_URL_HOST ) ? parse_url( home_url(), PHP_URL_HOST ) : 'atareao.es';
            $message = sprintf( "Contacto de %s en %s\n%s", $contact_name_email, $host, $contact_content );
            $txn_id  = uniqid( 'wp_', true );
            $endpoint = rtrim( $matrix_url, '/' ) . "/_matrix/client/v3/rooms/$matrix_room/send/m.room.message/$txn_id";

            $response = wp_remote_request(
                $endpoint,
                array(
                    'method'  => 'PUT',
                    'body'    => wp_json_encode(
                        array(
                            'msgtype' => 'm.text',
                            'body'    => $message,
                        )
                    ),
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $matrix_token,
                        'Content-Type'  => 'application/json',
                    ),
                    'timeout' => 10,
                )
            );

            if ( is_wp_error( $response ) ) {
                $show_error = true;
                $error_msg  = $response->get_error_message();
            } else {
                $response_code = intval( wp_remote_retrieve_response_code( $response ) );
                if ( $response_code >= 200 && $response_code < 300 ) {
                    $show_success       = true;
                    $contact_name_email = '';
                    $contact_content    = '';
                } else {
                    $show_error = true;
                    $error_msg  = wp_remote_retrieve_body( $response );
                }
            }
        }
    }
}

$captcha_a   = wp_rand( 1, 9 );
$captcha_b   = wp_rand( 1, 9 );
$captcha_sig = hash_hmac( 'sha256', $captcha_a . ':' . $captcha_b, wp_salt( 'nonce' ) );
$form_time   = time();

get_header();
?>


<main id="primary" class="site-main">
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <h1 class="entry-title" style="text-align:center;margin-bottom:2rem;letter-spacing:1px;">
                <?php esc_html_e('Contactar', 'atareao-theme'); ?>
            </h1>
        </header>
        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <?php
                // Display the page content from the editor (if any)
                the_content();
                ?>
            </div>
            <?php if ( $show_success ) : ?>
                <div class="atareao-feedback-success" style="color:green;text-align:center;margin-bottom:1rem;">
                    <?php esc_html_e( 'Mensaje enviado', 'atareao-theme' ); ?>
                </div>
            <?php elseif ( $show_error ) : ?>
                <div class="atareao-feedback-error" style="color:red;text-align:center;margin-bottom:1rem;">
                    <?php esc_html_e( 'No se pudo enviar el mensaje', 'atareao-theme' ); ?><br>
                    <small><?php echo esc_html( $error_msg ); ?></small>
                </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( get_permalink() ); ?>" class="atareao-contact-form">
                <?php wp_nonce_field( 'atareao_contact_form', 'atareao_contact_nonce' ); ?>
                <input type="hidden" name="atareao_form_time" value="<?php echo esc_attr( $form_time ); ?>">
                <input type="hidden" name="atareao_captcha_a" value="<?php echo esc_attr( $captcha_a ); ?>">
                <input type="hidden" name="atareao_captcha_b" value="<?php echo esc_attr( $captcha_b ); ?>">
                <input type="hidden" name="atareao_captcha_sig" value="<?php echo esc_attr( $captcha_sig ); ?>">
                <div>
                    <label for="contact_name_email">
                        <?php esc_html_e( 'Nombre o Email', 'atareao-theme' ); ?>
                    </label>
                    <input type="text" id="contact_name_email" name="contact_name_email" value="<?php echo esc_attr( $contact_name_email ); ?>" required>
                </div>
                <div>
                    <label for="contact_content">
                        <?php esc_html_e( 'Contenido', 'atareao-theme' ); ?>
                    </label>
                    <textarea id="contact_content" name="contact_content" rows="6" required><?php echo esc_textarea( $contact_content ); ?></textarea>
                </div>
                <!-- Honeypot anti-spam field (hidden from users) -->
                <div style="display:none;">
                    <label for="atareao_hp">Dejar vacío</label>
                    <input type="text" id="atareao_hp" name="atareao_hp" autocomplete="off">
                </div>
                <div>
                    <label for="atareao_captcha_answer">
                        <?php echo esc_html__( '¿Cuánto es ', 'atareao-theme' ) . intval( $captcha_a ) . ' + ' . intval( $captcha_b ) . '?'; ?>
                    </label>
                    <input type="number" id="atareao_captcha_answer" name="atareao_captcha_answer" required>
                </div>
                <div style="text-align:center;">
                    <button type="submit">
                        <?php esc_html_e( 'Enviar', 'atareao-theme' ); ?>
                    </button>
                </div>
                <?php if ( $show_success || $show_error ) : ?>
                <script>
                    setTimeout(function() {
                        var msg = document.querySelector('.atareao-feedback-success, .atareao-feedback-error');
                        if(msg) msg.style.display = 'none';
                    }, 5000);
                </script>
                <?php endif; ?>
            </form>
        </div>
    </article>
</main>


<?php get_footer(); ?>
