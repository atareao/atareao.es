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
                printf(_x('Un comentario en &ldquo;%s&rdquo;', 'comments title', 'atareao-theme'), get_the_title());
            } else {
                printf(
                    _nx(
                        '%1$s comentario en &ldquo;%2$s&rdquo;',
                        '%1$s comentarios en &ldquo;%2$s&rdquo;',
                        $comments_number,
                        'comments title',
                        'atareao-theme'
                    ),
                    number_format_i18n($comments_number),
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
    $commenter = wp_get_current_commenter();
    comment_form(array(
        'title_reply_before'  => '<h3 id="reply-title" class="comment-reply-title">',
        'title_reply_after'   => '</h3>',
        'comment_notes_before' => '',
        'comment_notes_after'  => '',
        'comment_field'       => '<p class="comment-form-comment"><label for="comment">' . _x('Comentario', 'noun', 'atareao-theme') . ' <span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required></textarea></p>',
        'class_submit'        => 'submit button',
        'label_submit'        => __('Publicar comentario', 'atareao-theme'),
        'submit_button'       => '<button type="submit" name="%1$s" id="%2$s" class="%3$s">%4$s</button>',
        'fields'              => array(
            'author' => '<p class="comment-form-author"><label for="author">' . __('Nombre', 'atareao-theme') . ' <span class="required">*</span></label><input id="author" name="author" type="text" value="' . esc_attr(isset($commenter['comment_author']) ? $commenter['comment_author'] : '') . '" size="30" maxlength="245" autocomplete="name" required /></p>',
        ),
    ));
    ?>

</div><!-- #comments -->

<?php
/**
 * Callback personalizado para mostrar comentarios individuales
 */
function atareao_comment_callback($comment, $args, $depth) {
    $tag = ('div' === $args['style']) ? 'div' : 'li';

    // Build initials (up to 2 chars) from author name
    $author_name = $comment->comment_author ?: 'An';
    $parts       = explode(' ', trim($author_name));
    $initials    = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr($parts[1], 0, 1));
    } else {
        $initials .= strtoupper(substr($parts[0], 1, 1));
    }

    // Deterministic background color from author name
    $palette     = ['#e74c3c','#e67e22','#d4a017','#2ecc71','#1abc9c','#3498db','#9b59b6','#e91e63'];
    $bg_color    = $palette[abs(crc32($author_name)) % count($palette)];
    ?>
    <<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class(empty($args['has_children']) ? '' : 'parent'); ?>>
        <article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
            <footer class="comment-meta">
                <div class="comment-author vcard">
                    <?php if (0 != $args['avatar_size']) : ?>
                        <div class="comment-avatar-wrapper">
                            <div class="comment-avatar-initials" style="background-color:<?php echo esc_attr($bg_color); ?>"><?php echo esc_html($initials); ?></div>
                            <?php
                            // Request Gravatar with default=404: the URL returns HTTP 404 when no
                            // real Gravatar exists, which triggers onerror and reveals the initials.
                            $avatar_url = get_avatar_url($comment, ['size' => 32, 'default' => '404']);
                            if ($avatar_url) :
                            ?>
                            <img src="<?php echo esc_url($avatar_url); ?>"
                                 width="32" height="32" alt=""
                                 onerror="this.style.display='none'">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="comment-author-info">
                        <?php printf('<b class="fn">%s</b>', get_comment_author_link()); ?>
                        <div class="comment-metadata">
                            <a href="<?php echo esc_url(get_comment_link($comment, $args)); ?>">
                                <time datetime="<?php comment_time('c'); ?>">
                                    <?php printf(
                                        _x('%1$s a las %2$s', '1: date, 2: time', 'atareao-theme'),
                                        get_comment_date('', $comment),
                                        get_comment_time()
                                    ); ?>
                                </time>
                            </a>
                            <?php edit_comment_link(__('Editar', 'atareao-theme'), '<span class="edit-link">', '</span>'); ?>
                        </div><!-- .comment-metadata -->
                    </div><!-- .comment-author-info -->
                </div><!-- .comment-author -->

                <?php if ('0' == $comment->comment_approved) : ?>
                    <p class="comment-awaiting-moderation"><?php _e('Tu comentario está pendiente de moderación.', 'atareao-theme'); ?></p>
                <?php endif; ?>
            </footer><!-- .comment-meta -->

            <div class="comment-content">
                <?php comment_text(); ?>
            </div><!-- .comment-content -->

            <?php
            comment_reply_link(array_merge($args, array(
                'add_below' => 'div-comment',
                'depth'     => $depth,
                'max_depth' => $args['max_depth'],
                'before'    => '<div class="reply">',
                'after'     => '</div>',
            )));
            ?>
        </article><!-- .comment-body -->
    <?php
}
