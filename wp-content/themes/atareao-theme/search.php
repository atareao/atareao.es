<?php
/**
 * Template para resultados de búsqueda
 *
 * @package Atareao_Theme
 */

get_header();
?>

<header class="page-header">
    <h1 class="page-title">
        <?php
        printf(
            __('Resultados de búsqueda para: %s', 'atareao-theme'),
            '<span>' . get_search_query() . '</span>'
        );
        ?>
    </h1>
</header>

<div class="posts-grid">
    <?php
    if (have_posts()) :
        while (have_posts()) :
            the_post();
            
            // Mostrar tipo de post
            $post_type_obj = get_post_type_object(get_post_type());
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <?php if (has_post_thumbnail()) : ?>
                    <div class="post-thumbnail">
                        <a href="<?php the_permalink(); ?>">
                            <?php the_post_thumbnail('atareao-medium'); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <header class="entry-header">
                    <div class="post-type-label">
                        <?php echo esc_html($post_type_obj->labels->singular_name); ?>
                    </div>
                    
                    <?php the_title('<h2 class="entry-title"><a href="' . esc_url(get_permalink()) . '" rel="bookmark">', '</a></h2>'); ?>
                    
                    <div class="entry-meta">
                        <?php atareao_theme_posted_on(); ?>
                    </div>
                </header>

                <div class="entry-content">
                    <?php the_excerpt(); ?>
                    <a href="<?php the_permalink(); ?>" class="more-link">
                        <?php _e('Leer más', 'atareao-theme'); ?>
                    </a>
                </div>
            </article>
            <?php
        endwhile;
        
        atareao_theme_pagination();
    else :
        get_template_part('template-parts/content', 'none');
    endif;
    ?>
</div>

<?php
get_footer();
