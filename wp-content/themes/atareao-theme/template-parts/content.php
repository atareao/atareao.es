<?php
/**
 * Template part para mostrar posts
 *
 * @package Atareao_Theme
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <?php if (has_post_thumbnail() && !is_single()) : ?>
        <div class="post-thumbnail">
            <a href="<?php the_permalink(); ?>">
                <?php the_post_thumbnail('atareao-medium'); ?>
            </a>
        </div>
    <?php endif; ?>

    <header class="entry-header">
        <?php
        if (is_singular()) :
            the_title('<h1 class="entry-title">', '</h1>');
        else :
            the_title('<h2 class="entry-title"><a href="' . esc_url(get_permalink()) . '" rel="bookmark">', '</a></h2>');
        endif;
        ?>
        
        <div class="entry-meta">
            <?php
            atareao_theme_posted_on();
            atareao_theme_posted_by();
            ?>
        </div>
    </header>

    <div class="entry-content">
        <?php
        if (is_singular()) {
            the_content();
        } else {
            the_excerpt();
            ?>
            <a href="<?php the_permalink(); ?>" class="more-link">
                <?php _e('Leer más', 'atareao-theme'); ?>
            </a>
            <?php
        }
        ?>
    </div>

    <?php if (!is_singular()) : ?>
        <footer class="entry-footer">
            <?php
            $categories_list = get_the_category_list(', ');
            if ($categories_list) {
                printf('<div class="post-categories">%s</div>', $categories_list);
            }
            ?>
        </footer>
    <?php endif; ?>
</article>
