<?php
/**
 * Template para 404
 *
 * @package Atareao_Theme
 */

get_header();
?>

<section class="error-404 not-found">
    <header class="page-header">
        <h1 class="page-title"><?php _e('Oops! Página no encontrada', 'atareao-theme'); ?></h1>
    </header>

    <div class="page-content">
        <p><?php _e('Parece que no pudimos encontrar lo que buscabas. Puede que alguna de estas opciones te ayude:', 'atareao-theme'); ?></p>

        <?php get_search_form(); ?>

        <div class="error-404-links" style="margin-top: 2rem;">
            <h2><?php _e('Enlaces útiles', 'atareao-theme'); ?></h2>
            <ul>
                <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php _e('Página de inicio', 'atareao-theme'); ?></a></li>
                <?php
                // Listar archivos de custom post types
                $post_types = array('tutorial', 'application', 'podcast', 'software');
                foreach ($post_types as $post_type) {
                    $post_type_obj = get_post_type_object($post_type);
                    if ($post_type_obj && $post_type_obj->has_archive) {
                        printf(
                            '<li><a href="%s">%s</a></li>',
                            esc_url(get_post_type_archive_link($post_type)),
                            esc_html($post_type_obj->labels->name)
                        );
                    }
                }
                ?>
            </ul>
        </div>
    </div>
</section>

<?php
get_footer();
