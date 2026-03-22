<?php
/**
 * Template para mostrar software individual
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
    $difficulty     = get_the_terms(get_the_ID(), 'difficulty');
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

                if ($difficulty && !is_wp_error($difficulty)) {
                    echo '<span class="difficulty"><strong>' . __('Dificultad:', 'atareao-theme') . '</strong> ' . esc_html($difficulty[0]->name) . '</span>';
                }
                ?>
            </div>

            <?php if ($download_url || $repository_url) : ?>
            <div class="software-actions">
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
            <?php echo atareao_share_links( get_the_ID() ); ?>
        </header>

        <?php if (has_post_thumbnail()) : ?>
            <div class="post-thumbnail">
                <?php the_post_thumbnail('large'); ?>
            </div>
        <?php endif; ?>

        <div class="entry-content">
            <?php
            $content = apply_filters('the_content', get_the_content());

            $doc = new DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $content . '</body></html>');
            libxml_clear_errors();

            $xpath = new DOMXPath($doc);
            $featured_nodes = $xpath->query('//*[contains(@class,"wp-block-post-featured-image")]');
            foreach ($featured_nodes as $fn) {
                $fn->parentNode->removeChild($fn);
            }

            $body = $doc->getElementsByTagName('body')->item(0);
            $content = '';
            foreach ($body->childNodes as $child) {
                $content .= $doc->saveHTML($child);
            }

            echo $content;

            wp_link_pages(array(
                'before' => '<div class="page-links">' . __('Páginas:', 'atareao-theme'),
                'after'  => '</div>',
            ));
            ?>
        </div>

        <footer class="entry-footer">
            <?php
            $categories_list = get_the_term_list(get_the_ID(), 'software_category', '', ', ');
            if ($categories_list) {
                printf('<div class="post-categories"><strong>%s:</strong> %s</div>',
                    __('Categorías', 'atareao-theme'),
                    $categories_list
                );
            }

            $tags_list = get_the_term_list(get_the_ID(), 'post_tag', '', ', ');
            if ($tags_list) {
                printf('<div class="post-tags"><strong>%s:</strong> %s</div>',
                    __('Etiquetas', 'atareao-theme'),
                    $tags_list
                );
            }
            ?>
        </footer>
    </article>

    <?php
    // Navegación entre software
    $softwares = new WP_Query(array(
        'post_type'      => 'software',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ));

    $software_ids = $softwares->posts;
    wp_reset_postdata();

    $current_index = array_search(get_the_ID(), $software_ids);

    if ($current_index !== false) {
        $prev_sw = isset($software_ids[$current_index + 1]) ? get_post($software_ids[$current_index + 1]) : null;
        $next_sw = isset($software_ids[$current_index - 1]) ? get_post($software_ids[$current_index - 1]) : null;

        $get_sw_meta = function($post) {
            $desc = get_post_meta($post->ID, '_genesis_description', true);
            if (empty($desc)) $desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
            if (empty($desc)) $desc = get_post_meta($post->ID, 'rank_math_description', true);
            if (empty($desc)) $desc = get_post_meta($post->ID, '_aioseo_description', true);
            if (empty($desc)) $desc = $post->post_excerpt;
            return array('desc' => $desc);
        };

        if ($prev_sw || $next_sw) :
        ?>
        <nav class="post-navigation" aria-label="<?php esc_attr_e('Navegación entre software', 'atareao-theme'); ?>">
            <div class="nav-links">
                <?php if ($prev_sw) :
                    $prev_meta = $get_sw_meta($prev_sw);
                ?>
                <div class="nav-previous">
                    <a href="<?php echo esc_url(get_permalink($prev_sw->ID)); ?>">
                        <span class="nav-arrow">&lt;</span>
                        <span class="nav-content">
                            <span class="nav-subtitle"><?php esc_html_e('Anterior', 'atareao-theme'); ?></span>
                            <span class="nav-title"><?php echo esc_html($prev_sw->post_title); ?></span>
                            <?php if ($prev_meta['desc']) : ?>
                                <span class="nav-desc"><?php echo esc_html($prev_meta['desc']); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($next_sw) :
                    $next_meta = $get_sw_meta($next_sw);
                ?>
                <div class="nav-next">
                    <a href="<?php echo esc_url(get_permalink($next_sw->ID)); ?>">
                        <span class="nav-content">
                            <span class="nav-subtitle"><?php esc_html_e('Siguiente', 'atareao-theme'); ?></span>
                            <span class="nav-title"><?php echo esc_html($next_sw->post_title); ?></span>
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
    }

    // Comentarios
    if (comments_open() || get_comments_number()) :
        comments_template();
    endif;

endwhile;
?>

<?php
get_footer();
