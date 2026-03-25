<?php
/**
 * Template part para aplicaciones
 *
 * @package Atareao_Theme
 */

$categories = get_the_terms(get_the_ID(), 'application_category');
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('podcast-card'); ?> <?php if (!is_singular()) : ?>data-url="<?php the_permalink(); ?>"<?php endif; ?>>
    <?php if (!is_singular()) : ?>
        <!-- Layout de archivo: imagen + info, igual que tutorial -->
        <div class="podcast-archive-layout">
            <?php if (has_post_thumbnail()) : ?>
                <div class="podcast-thumbnail">
                    <a href="<?php the_permalink(); ?>">
                        <?php the_post_thumbnail('thumbnail'); ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="podcast-info">
                <header class="entry-header">
                    <div class="podcast-header-row">
                        <div class="podcast-left-col">
                            <div class="podcast-title-row">
                                <?php the_title('<h2 class="entry-title"><a href="' . esc_url(get_permalink()) . '" rel="bookmark">', '</a></h2>'); ?>
                            </div>
                            <div class="podcast-date">
                                <?php echo get_the_date(); ?>
                            </div>
                            <div class="entry-meta"></div>
                        </div>
                    </div>
                </header>
            </div>
        </div>

        <div class="podcast-description">
            <?php the_excerpt(); ?>
        </div>

        <?php if ($categories && !is_wp_error($categories)) : ?>
        <div class="podcast-meta">
            <span class="duration">
                <strong><?php _e('Categoría:', 'atareao-theme'); ?></strong>
                <?php echo esc_html($categories[0]->name); ?>
            </span>
        </div>
        <?php endif; ?>

    <?php else : ?>
        <!-- Layout singular -->
        <?php if (has_post_thumbnail()) : ?>
            <div class="post-thumbnail">
                <?php the_post_thumbnail('large'); ?>
            </div>
        <?php endif; ?>

        <header class="entry-header">
            <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
            <div class="entry-meta">
                <?php
                atareao_theme_posted_on();
                if ($categories && !is_wp_error($categories)) {
                    echo '<span class="category"><strong>' . __('Categoría:', 'atareao-theme') . '</strong> ' . esc_html($categories[0]->name) . '</span>';
                }
                ?>
            </div>
        </header>
    <?php endif; ?>

    <?php if (is_singular()) : ?>
        <div class="entry-content">
            <?php the_content(); ?>
        </div>
        <footer class="entry-footer">
            <?php
            $categories_list = get_the_term_list(get_the_ID(), 'application_category', '', ', ');
            if ($categories_list && ! is_wp_error( $categories_list )) {
                printf('<div class="post-categories">%s</div>', $categories_list);
            }
            ?>
        </footer>
    <?php endif; ?>
</article>
