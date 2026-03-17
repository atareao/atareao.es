<?php
/**
 * Template para archivo de tutoriales
 *
 * @package Atareao_Theme
 */

get_header();

global $wp_query;
$max   = intval($wp_query->max_num_pages);
$paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
$prev_url = ($paged > 1)    ? get_previous_posts_page_link() : null;
$next_url = ($paged < $max) ? get_next_posts_page_link($max) : null;
?>

<header class="page-header">
    <div class="podcast-archive-intro">
        <p><?php _e('Tutoriales sobre Linux, Open Source y tecnología. Desde los primeros pasos hasta técnicas avanzadas, aquí encontrarás guías completas para aprender a tu ritmo.', 'atareao-theme'); ?></p>
    </div>
</header>

<?php if ($max > 1) : ?>
    <nav class="season-navigation" aria-label="<?php esc_attr_e('Paginación', 'atareao-theme'); ?>">
        <div class="season-controls">
            <div class="season-col season-col-prev">
                <?php if ($prev_url) : ?>
                    <a href="<?php echo esc_url($prev_url); ?>" class="season-btn season-prev" aria-label="<?php esc_attr_e('Página anterior', 'atareao-theme'); ?>">&lt;</a>
                <?php else : ?>
                    <span class="season-btn season-prev disabled" aria-disabled="true">&lt;</span>
                <?php endif; ?>
            </div>

            <div class="season-col season-col-center">
                <span class="current-season">
                    <?php printf(__('Página %d de %d', 'atareao-theme'), $paged, $max); ?>
                </span>
            </div>

            <div class="season-col season-col-next">
                <?php if ($next_url) : ?>
                    <a href="<?php echo esc_url($next_url); ?>" class="season-btn season-next" aria-label="<?php esc_attr_e('Página siguiente', 'atareao-theme'); ?>">&gt;</a>
                <?php else : ?>
                    <span class="season-btn season-next disabled" aria-disabled="true">&gt;</span>
                <?php endif; ?>
            </div>
        </div>
    </nav>
<?php endif; ?>

<div class="posts-grid posts-grid-podcast">
    <?php
    if (have_posts()) :
        while (have_posts()) :
            the_post();
            get_template_part('template-parts/content', 'tutorial');
        endwhile;
    else :
        get_template_part('template-parts/content', 'none');
    endif;
    ?>
</div>

<?php
get_footer();
