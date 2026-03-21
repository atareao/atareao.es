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
        <?php if ( is_singular() || ! is_home() ) :
            $share = atareao_get_share_links( get_the_ID() );
        ?>
        <div class="entry-share">
            <span class="share-label"><?php esc_html_e( 'Compartir:', 'atareao-theme' ); ?></span>
            <a class="share-btn share-x" href="<?php echo esc_url( $share['x']['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on X">
                <span class="social-icon" aria-hidden="true"><?php echo $share['x']['icon']; ?></span>
            </a>
            <a class="share-btn share-mastodon" href="<?php echo esc_url( $share['mastodon']['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on Mastodon">
                <span class="social-icon" aria-hidden="true"><?php echo $share['mastodon']['icon']; ?></span>
            </a>
            <a class="share-btn share-telegram" href="<?php echo esc_url( $share['telegram']['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on Telegram">
                <span class="social-icon" aria-hidden="true"><?php echo $share['telegram']['icon']; ?></span>
            </a>
        </div>
        <?php endif; ?>
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
