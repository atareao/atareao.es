<?php
/**
 * Plantilla de comentarios
 *
 * @package Atareao_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Si la entrada actual está protegida por una contraseña y
 * el visitante aún no ha introducido la contraseña,
 * no mostrar los comentarios.
 */
if (post_password_required()) {
    return;
}
?>

<div id="comments" class="comments-area">

    <?php if (have_comments()) : ?>
        <h2 class="comments-title">
            <?php
            $comments_number = get_comments_number();
            if ('1' === $comments_number) {
                printf(
                    _x('%1$s comentario en &ldquo;%2$s&rdquo;', 'comments title', 'atareao-theme'),
                    '<span class="comments-count">' . number_format_i18n($comments_number) . '</span>',
                    get_the_title()
                );
            } else {
                printf(
                    _nx(
                        '%1$s comentario en &ldquo;%2$s&rdquo;',
                        '%1$s comentarios en &ldquo;%2$s&rdquo;',
                        $comments_number,
                        'comments title',
                        'atareao-theme'
                    ),
                    '<span class="comments-count">' . number_format_i18n($comments_number) . '</span>',
                    get_the_title()
                );
            }
            ?>
        </h2>

        <ol class="comment-list">
            <?php
            wp_list_comments(array(
                'style'       => 'ol',
                'short_ping'  => true,
                'avatar_size' => 50,
                'callback'    => 'atareao_comment_callback',
            ));
            ?>
        </ol>

        <?php
        // Navegación de paginación de comentarios
        the_comments_navigation(array(
            'prev_text' => __('← Comentarios anteriores', 'atareao-theme'),
            'next_text' => __('Comentarios siguientes →', 'atareao-theme'),
        ));
        ?>

        <?php if (!comments_open() && get_comments_number()) : ?>
            <p class="no-comments"><?php _e('Los comentarios están cerrados.', 'atareao-theme'); ?></p>
        <?php endif; ?>

    <?php endif; // have_comments() ?>

    <?php
    // Formulario de comentarios
    // Prepare a simple math captcha + honeypot for comment form
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    // Generate a new math captcha when displaying the form (GET).
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $a = rand(1, 9);
        $b = rand(1, 9);
        $_SESSION['atareao_comment_captcha'] = $a + $b;
        $_SESSION['atareao_comment_captcha_a'] = $a;
        $_SESSION['atareao_comment_captcha_b'] = $b;
    } else {
        $a = isset($_SESSION['atareao_comment_captcha_a']) ? $_SESSION['atareao_comment_captcha_a'] : rand(1, 9);
        $b = isset($_SESSION['atareao_comment_captcha_b']) ? $_SESSION['atareao_comment_captcha_b'] : rand(1, 9);
    }
    // store form time
    $_SESSION['atareao_comment_form_time'] = time();

    $commenter = wp_get_current_commenter();
    $comment_field = '<p class="comment-form-comment"><label for="comment">' . _x('Comentario', 'noun', 'atareao-theme') . ' <span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required tabindex="2"></textarea></p>';
    $comment_field .= '<div style="display:none;"><label for="atareao_comment_hp">Dejar vacío</label><input type="text" id="atareao_comment_hp" name="atareao_comment_hp" autocomplete="off" tabindex="-1"></div>';

    // Captcha and form time will be shown immediately above the submit button
    $captcha_html  = '<div class="comment-form-captcha" style="margin-top:0.5rem;">';
    $captcha_html .= '<label for="atareao_comment_captcha">' . sprintf(esc_html__('¿Cuánto es %d + %d?', 'atareao-theme'), $a, $b) . ' <span class="required">*</span></label>';
    $captcha_html .= '<input type="number" id="atareao_comment_captcha" name="atareao_comment_captcha" required style="margin-left:0.5rem;" tabindex="3">';
    $captcha_html .= '</div>';
    $captcha_html .= '<input type="hidden" name="atareao_comment_form_time" value="' . esc_attr($_SESSION['atareao_comment_form_time']) . '">';

    $submit_field = $captcha_html . '<p class="form-submit">%1$s %2$s</p>';

    comment_form(array(
        'title_reply_before'  => '<h3 id="reply-title" class="comment-reply-title">',
        'title_reply_after'   => '</h3>',
        'comment_notes_before' => '<div id="atareao-comment-message" role="status" aria-live="polite"></div>',
        'comment_notes_after'  => '',
        'comment_field'       => $comment_field,
        'class_submit'        => 'submit button',
        'label_submit'        => __('Publicar comentario', 'atareao-theme'),
        'submit_button'       => '<button type="submit" name="%1$s" id="%2$s" class="%3$s" tabindex="4">%4$s</button>',
        'submit_field'        => $submit_field,
        'fields'              => array(
            'author' => '<p class="comment-form-author"><label for="author">' . __('Nombre', 'atareao-theme') . ' <span class="required">*</span></label><input id="author" name="author" type="text" value="' . esc_attr(isset($commenter['comment_author']) ? $commenter['comment_author'] : '') . '" size="30" maxlength="245" autocomplete="name" required tabindex="1" /></p>',
        ),
    ));
    ?>

    <?php
    // Show temporary error message from captcha validation (stored in session)
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (!empty($_SESSION['atareao_comment_error'])) {
        $msg = esc_html($_SESSION['atareao_comment_error']);
        unset($_SESSION['atareao_comment_error']);
        ?>
        <div class="atareao-comment-error" style="color:#b30000;text-align:center;margin-top:1rem;">
            <?php echo $msg; ?>
        </div>
        <script>
            (function(){
                var el = document.querySelector('.atareao-comment-error');
                if (el) {
                    try { location.hash = '#respond'; } catch(e) {}
                    try { el.scrollIntoView({behavior: 'smooth', block: 'center'}); } catch(e) {}
                    el.focus && el.focus();
                    setTimeout(function(){ if (el) el.style.display = 'none'; }, 5000);
                }
            })();
        </script>
        <?php
    }
    ?>

    <script>
    // Enforce tab order within the comment form: name -> comment -> captcha -> submit
    (function(){
        document.addEventListener('DOMContentLoaded', function(){
            var author = document.getElementById('author');
            var comment = document.getElementById('comment');
            var captcha = document.getElementById('atareao_comment_captcha');
            var submit = document.querySelector('#respond .form-submit button, #respond button[type="submit"]');
            if (!author || !comment || !captcha) return;
            function handleTab(nextEl){
                return function(e){
                    if (e.key === 'Tab' && !e.shiftKey) {
                        e.preventDefault();
                        nextEl.focus();
                    }
                };
            }
            author.addEventListener('keydown', handleTab(comment));
            comment.addEventListener('keydown', handleTab(captcha));
            captcha.addEventListener('keydown', handleTab(submit || captcha));
        });
    })();
    </script>

</div><!-- #comments -->

<?php
