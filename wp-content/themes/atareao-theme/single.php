<?php
/**
 * Template para entradas individuales
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
                ?>
            </div>
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
            $categories_list = get_the_category_list(', ');
            if ($categories_list) {
                printf('<div class="post-categories"><strong>%s:</strong> %s</div>', 
                    __('Categorías', 'atareao-theme'), 
                    $categories_list
                );
            }

            $tags_list = get_the_tag_list('', ', ');
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
    // Navegación entre posts
    the_post_navigation(array(
        'prev_text' => '<span class="nav-subtitle">' . __('Anterior:', 'atareao-theme') . '</span> <span class="nav-title">%title</span>',
        'next_text' => '<span class="nav-subtitle">' . __('Siguiente:', 'atareao-theme') . '</span> <span class="nav-title">%title</span>',
    ));

    // Comentarios
    if (comments_open() || get_comments_number()) :
        comments_template();
    endif;

endwhile;
?>

<?php
get_footer();
