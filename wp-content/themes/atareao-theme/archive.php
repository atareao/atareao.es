<?php
/**
 * Template para archivos en general
 *
 * @package Atareao_Theme
 */

get_header();
?>

<header class="page-header">
    <?php
    the_archive_title('<h1 class="page-title">', '</h1>');
    the_archive_description('<div class="archive-description">', '</div>');
    ?>
</header>

<?php
// Añadir clase dinámica basada en el post type
$post_type = get_post_type();
$grid_class = 'posts-grid';
if ($post_type) {
    $grid_class .= ' posts-grid-' . $post_type;
}
?>
<div class="<?php echo esc_attr($grid_class); ?>">
    <?php
    if (have_posts()) :
        while (have_posts()) :
            the_post();
            get_template_part('template-parts/content', get_post_type());
        endwhile;
        
        atareao_theme_pagination();
    else :
        get_template_part('template-parts/content', 'none');
    endif;
    ?>
</div>

<?php
get_footer();
