<?php
/**
 * Template Name: Contact Page
 * Description: Custom contact page with form.
 *
 * @package Atareao_Theme
 */

$show_success = isset($_GET['atareao_contact']) && 'success' === $_GET['atareao_contact'];
$show_error   = isset($_GET['atareao_contact']) && 'error' === $_GET['atareao_contact'];
$error_msg    = $show_error && isset($_GET['atareao_msg']) ? sanitize_text_field(rawurldecode($_GET['atareao_msg'])) : '';

$captcha_a   = wp_rand(1, 9);
$captcha_b   = wp_rand(1, 9);
$captcha_sig = hash_hmac('sha256', $captcha_a . ':' . $captcha_b, wp_salt('nonce'));
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
                the_content();
                ?>
            </div>
            <?php if ($show_success) : ?>
                <div class="atareao-feedback-success" style="color:green;text-align:center;margin-bottom:1rem;">
                    <?php esc_html_e('Mensaje enviado', 'atareao-theme'); ?>
                </div>
            <?php elseif ($show_error) : ?>
                <div class="atareao-feedback-error" style="color:red;text-align:center;margin-bottom:1rem;">
                    <?php esc_html_e('No se pudo enviar el mensaje', 'atareao-theme'); ?><br>
                    <small><?php echo esc_html($error_msg); ?></small>
                </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(get_permalink()); ?>" class="atareao-contact-form">
                <?php wp_nonce_field('atareao_contact_form', 'atareao_contact_nonce'); ?>
                <input type="hidden" name="atareao_contact_form" value="1">
                <input type="hidden" name="atareao_form_time" value="<?php echo esc_attr($form_time); ?>">
                <input type="hidden" name="atareao_captcha_a" value="<?php echo esc_attr($captcha_a); ?>">
                <input type="hidden" name="atareao_captcha_b" value="<?php echo esc_attr($captcha_b); ?>">
                <input type="hidden" name="atareao_captcha_sig" value="<?php echo esc_attr($captcha_sig); ?>">
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
                <div style="position: absolute; left: -9999px; top: -9999px;" aria-hidden="true">
                    <input type="text" name="atareao_website" id="atareao_website" autocomplete="off">
                </div>
                <div>
                    <label for="atareao_captcha_answer">
                        <?php echo esc_html__('¿Cuanto es ', 'atareao-theme') . intval($captcha_a) . ' + ' . intval($captcha_b) . '?'; ?>
                    </label>
                    <input type="number" id="atareao_captcha_answer" name="atareao_captcha_answer" required>
                </div>
                <div style="text-align:center;">
                    <button type="submit">
                        <?php esc_html_e('Enviar', 'atareao-theme'); ?>
                    </button>
                </div>
                <?php if ($show_success || $show_error) : ?>
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
