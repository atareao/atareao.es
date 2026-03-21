<?php
/**
 * Template para mostrar un tutorial individual
 *
 * @package Atareao_Theme
 */

get_header();
?>

<?php
while (have_posts()) :
    the_post();
    ?>
    
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
            
            <div class="entry-meta">
                <?php
                atareao_theme_posted_on();
                atareao_theme_posted_by();
                
                // Mostrar nivel de dificultad
                $difficulty = get_the_terms(get_the_ID(), 'difficulty');
                if ($difficulty && !is_wp_error($difficulty)) {
                    echo '<span class="difficulty">';
                    echo '<strong>' . __('Dificultad:', 'atareao-theme') . '</strong> ';
                    echo esc_html($difficulty[0]->name);
                    echo '</span>';
                }
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
    // Caja de capítulos — fuera del article, como bloque independiente
    $tutorial_id = get_the_ID();
    $chapters = new WP_Query(array(
        'post_type'      => 'capitulo',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_key'       => 'numero-capitulo',
        'meta_query'     => array(
            array(
                'key'     => 'tutorial-id',
                'value'   => (string) $tutorial_id,
                'compare' => '=',
            ),
        ),
        'orderby' => 'meta_value_num',
        'order'   => 'ASC',
    ));

    if ($chapters->have_posts()) :
        $total_chapters = $chapters->found_posts;
        ?>
        <div class="tutorial-chapters">
            <div class="tutorial-chapters__header">
                <span class="tutorial-chapters__icon" aria-hidden="true">&#9776;</span>
                <h2 class="tutorial-chapters__title"><?php _e('Contenido del tutorial', 'atareao-theme'); ?></h2>
                <span class="tutorial-chapters__count">
                    <?php echo sprintf(
                        _n('%d capítulo', '%d capítulos', $total_chapters, 'atareao-theme'),
                        $total_chapters
                    ); ?>
                </span>
            </div>
            <ol class="chapters-list">
                <?php
                while ($chapters->have_posts()) :
                    $chapters->the_post();
                    $chapter_num = get_post_meta(get_the_ID(), 'numero-capitulo', true);
                    // Meta description: probar distintos plugins SEO y excerpt como fallback
                    $desc = get_post_meta(get_the_ID(), '_genesis_description', true);
                    if ( empty($desc) ) $desc = get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true);
                    if ( empty($desc) ) $desc = get_post_meta(get_the_ID(), 'rank_math_description', true);
                    if ( empty($desc) ) $desc = get_post_meta(get_the_ID(), '_aioseo_description', true);
                    if ( empty($desc) ) $desc = get_the_excerpt();
                    ?>
                    <li class="chapters-list__item">
                        <span class="chapters-list__num"><?php echo esc_html($chapter_num ?: '·'); ?></span>
                        <div class="chapters-list__body">
                            <a class="chapters-list__link" href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                            <?php if ( $desc ) : ?>
                                <p class="chapters-list__excerpt"><?php echo esc_html($desc); ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php
                endwhile;
                wp_reset_postdata();
                ?>
            </ol>
        </div>
        <?php
    endif;
    ?>

    <?php
    // Navegación entre posts
    the_post_navigation(array(
        'prev_text' => '<span class="nav-subtitle">' . __('Tutorial anterior:', 'atareao-theme') . '</span> <span class="nav-title">%title</span>',
        'next_text' => '<span class="nav-subtitle">' . __('Tutorial siguiente:', 'atareao-theme') . '</span> <span class="nav-title">%title</span>',
    ));

    // Comentarios
    if (comments_open() || get_comments_number()) :
        comments_template();
    endif;

endwhile;
?>

<?php
get_footer();
