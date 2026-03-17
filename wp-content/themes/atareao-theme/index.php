<?php
/**
 * Template principal
 *
 * @package Atareao_Theme
 */

get_header();
?>

<div class="posts-grid">
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
