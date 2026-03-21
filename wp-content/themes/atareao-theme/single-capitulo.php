<?php
/**
 * Template para mostrar un capítulo individual
 *
 * @package Atareao_Theme
 */

get_header();
?>

<?php
while (have_posts()) :
    the_post();
    
    // Obtener el tutorial padre
    $tutorial_id = get_post_meta(get_the_ID(), 'tutorial-id', true);
    $tutorial = $tutorial_id ? get_post($tutorial_id) : null;
    ?>
    
    <?php
    $chapter_number = get_post_meta( get_the_ID(), 'numero-capitulo', true );
    ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <div class="chapter-title-row">
                <?php if ( $chapter_number ) : ?>
                    <span class="chapter-number-badge"><?php echo esc_html( $chapter_number ); ?></span>
                <?php endif; ?>
                <div class="chapter-title-group">
                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                    <?php if ($tutorial) : ?>
                        <div class="tutorial-breadcrumb">
                            Tutorial: <a href="<?php echo esc_url(get_permalink($tutorial->ID)); ?>"><?php echo esc_html($tutorial->post_title); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="entry-meta">
                <?php
                atareao_theme_posted_on();
                atareao_theme_posted_by();
                ?>
            </div>
            <?php echo atareao_share_links( get_the_ID() ); ?>
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
            $categories_list = get_the_term_list(get_the_ID(), 'tutorial_category', '', ', ');
            if ($categories_list) {
                printf('<div class="post-categories"><strong>%s:</strong> %s</div>', 
                    __('Categorías', 'atareao-theme'), 
                    $categories_list
                );
            }

            $tags_list = get_the_term_list(get_the_ID(), 'tutorial_tag', '', ', ');
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
    // Navegación entre capítulos del mismo tutorial
    if ($tutorial_id) {
        $chapters = new WP_Query(array(
            'post_type'      => 'capitulo',
            'posts_per_page' => -1,
            'meta_key'       => 'numero-capitulo',
            'meta_query'     => array(
                array(
                    'key'   => 'tutorial-id',
                    'value' => $tutorial_id,
                ),
            ),
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
        ));
        
        $chapter_ids = array();
        while ($chapters->have_posts()) {
            $chapters->the_post();
            $chapter_ids[] = get_the_ID();
        }
        wp_reset_postdata();
        
        $current_index = array_search(get_the_ID(), $chapter_ids);
        
        if ($current_index !== false) {
            $prev_chapter = isset($chapter_ids[$current_index - 1]) ? get_post($chapter_ids[$current_index - 1]) : null;
            $next_chapter = isset($chapter_ids[$current_index + 1]) ? get_post($chapter_ids[$current_index + 1]) : null;

            // Helper: get chapter number + description for a given post
            $get_chapter_meta = function($post) {
                $num  = get_post_meta($post->ID, 'numero-capitulo', true);
                $desc = get_post_meta($post->ID, '_genesis_description', true);
                if ( empty($desc) ) $desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
                if ( empty($desc) ) $desc = get_post_meta($post->ID, 'rank_math_description', true);
                if ( empty($desc) ) $desc = get_post_meta($post->ID, '_aioseo_description', true);
                if ( empty($desc) ) $desc = $post->post_excerpt;
                return array('num' => $num, 'desc' => $desc);
            };

            if ($prev_chapter || $next_chapter) :
            ?>
            <nav class="post-navigation" aria-label="<?php esc_attr_e('Navegación entre capítulos', 'atareao-theme'); ?>">
                <div class="nav-links">
                    <?php if ($prev_chapter) :
                        $prev_meta = $get_chapter_meta($prev_chapter);
                    ?>
                    <div class="nav-previous">
                        <a href="<?php echo esc_url(get_permalink($prev_chapter->ID)); ?>">
                            <span class="nav-arrow">&lt;</span>
                            <span class="nav-content">
                                <span class="nav-subtitle"><?php esc_html_e('Anterior', 'atareao-theme'); ?></span>
                                <span class="nav-title">
                                    <?php if ($prev_meta['num']) : ?>
                                        <span class="chapter-number-badge"><?php echo esc_html($prev_meta['num']); ?></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($prev_chapter->post_title); ?>
                                </span>
                                <?php if ($prev_meta['desc']) : ?>
                                    <span class="nav-desc"><?php echo esc_html($prev_meta['desc']); ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if ($next_chapter) :
                        $next_meta = $get_chapter_meta($next_chapter);
                    ?>
                    <div class="nav-next">
                        <a href="<?php echo esc_url(get_permalink($next_chapter->ID)); ?>">
                            <span class="nav-content">
                                <span class="nav-subtitle"><?php esc_html_e('Siguiente', 'atareao-theme'); ?></span>
                                <span class="nav-title">
                                    <?php if ($next_meta['num']) : ?>
                                        <span class="chapter-number-badge"><?php echo esc_html($next_meta['num']); ?></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($next_chapter->post_title); ?>
                                </span>
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
    }

    // Comentarios
    if (comments_open() || get_comments_number()) :
        comments_template();
    endif;

endwhile;
?>

<?php
get_footer();
