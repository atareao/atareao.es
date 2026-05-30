<?php
/**
 * Template para mostrar una aplicación individual
 *
 * @package Atareao_Theme
 */

get_header();
?>

<?php
while (have_posts()) :
    the_post();

    $download_url   = get_post_meta(get_the_ID(), '_download_url', true);
    $repository_url = get_post_meta(get_the_ID(), '_repository_url', true);
    $version        = get_post_meta(get_the_ID(), '_version', true);
    $platforms      = get_the_terms(get_the_ID(), 'platform');
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <div class="chapter-title-row">
                <div class="chapter-title-group">
                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                    <?php if ($version) : ?>
                        <div class="tutorial-breadcrumb">
                            <?php echo esc_html__('Versión:', 'atareao-theme') . ' ' . esc_html($version); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="entry-meta">
                <?php
                atareao_theme_posted_on();
                atareao_theme_posted_by();

                if ($platforms && !is_wp_error($platforms)) {
                    $platform_names = array();
                    foreach ($platforms as $platform) {
                        $platform_names[] = $platform->name;
                    }
                    echo '<span class="platforms"><strong>' . __('Plataformas:', 'atareao-theme') . '</strong> ' . esc_html(implode(', ', $platform_names)) . '</span>';
                }

                atareao_theme_comment_count();
                ?>
            </div>

            <?php if ($download_url || $repository_url) : ?>
            <div class="app-actions">
                <?php if ($download_url) : ?>
                    <a href="<?php echo esc_url($download_url); ?>" class="btn btn-primary" target="_blank" rel="noopener">
                        <?php _e('Descargar', 'atareao-theme'); ?>
                    </a>
                <?php endif; ?>
                <?php if ($repository_url) : ?>
                    <a href="<?php echo esc_url($repository_url); ?>" class="btn btn-secondary" target="_blank" rel="noopener">
                        <?php _e('Ver Repositorio', 'atareao-theme'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php echo atareao_share_links(get_the_ID()); ?>
        </header>

        <?php if (has_post_thumbnail()) : ?>
            <div class="post-thumbnail">
                <?php the_post_thumbnail('large'); ?>
            </div>
        <?php endif; ?>

        <div class="entry-content">
            <?php
            the_content();

            wp_link_pages(array(
                'before' => '<div class="page-links">' . __('Páginas:', 'atareao-theme'),
                'after'  => '</div>',
            ));
            ?>
        </div>

        <footer class="entry-footer">
            <?php
            $categories_list = get_the_term_list(get_the_ID(), 'application_category', '', ', ');
            if ($categories_list && ! is_wp_error($categories_list)) {
                printf(
                    '<div class="post-categories"><strong>%s:</strong> %s</div>',
                    __('Categorías', 'atareao-theme'),
                    $categories_list
                );
            }

            $tags_list = get_the_term_list(get_the_ID(), 'post_tag', '', ', ');
            if ($tags_list) {
                printf(
                    '<div class="post-tags"><strong>%s:</strong> %s</div>',
                    __('Etiquetas', 'atareao-theme'),
                    $tags_list
                );
            }
            ?>
        </footer>
    </article>

    <?php
    // Navegación entre aplicaciones
    $prev_app = get_adjacent_post(false, '', true, '');
    $next_app = get_adjacent_post(false, '', false, '');

    $get_app_meta = function ($post) {
        return array('desc' => atareao_get_seo_description($post));
    };

    if ($prev_app || $next_app) :
        ?>
    <nav class="post-navigation" aria-label="<?php esc_attr_e('Navegación entre aplicaciones', 'atareao-theme'); ?>">
        <div class="nav-links">
            <?php if ($prev_app) :
                $prev_meta = $get_app_meta($prev_app);
                ?>
            <div class="nav-previous">
                <a href="<?php echo esc_url(get_permalink($prev_app->ID)); ?>">
                    <span class="nav-arrow">&lt;</span>
                    <span class="nav-content">
                        <span class="nav-subtitle"><?php esc_html_e('Anterior', 'atareao-theme'); ?></span>
                        <span class="nav-title"><?php echo esc_html($prev_app->post_title); ?></span>
                        <?php if ($prev_meta['desc']) : ?>
                            <span class="nav-desc"><?php echo esc_html($prev_meta['desc']); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($next_app) :
                $next_meta = $get_app_meta($next_app);
                ?>
            <div class="nav-next">
                <a href="<?php echo esc_url(get_permalink($next_app->ID)); ?>">
                    <span class="nav-content">
                        <span class="nav-subtitle"><?php esc_html_e('Siguiente', 'atareao-theme'); ?></span>
                        <span class="nav-title"><?php echo esc_html($next_app->post_title); ?></span>
                        <?php if ($next_meta['desc']) : ?>
                            <span class="nav-desc"><?php echo esc_html($next_meta['desc']); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="nav-arrow">&gt;</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    <?php
    endif;

    // Comentarios
    if (comments_open() || get_comments_number()) :
        comments_template();
    endif;
endwhile;
?>

<?php
get_footer();
