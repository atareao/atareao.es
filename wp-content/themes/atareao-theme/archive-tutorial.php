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
    <?php $archive_desc = get_the_archive_description(); ?>
    <?php if ($archive_desc) : ?>
    <div class="archive-intro">
        <p><?php echo wp_kses_post($archive_desc); ?></p>
    </div>
    <?php endif; ?>
</header>

<?php /* pagination moved to bottom of the archive so it appears before the footer */ ?>

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
<?php if ($max > 1) : ?>
    <nav class="page-navigation" aria-label="<?php esc_attr_e('Paginación', 'atareao-theme'); ?>">
        <div class="page-controls">
            <div class="page-col page-col-prev">
                <?php if ($prev_url) : ?>
                    <a href="<?php echo esc_url($prev_url); ?>" class="page-btn page-prev" aria-label="<?php esc_attr_e('Página anterior', 'atareao-theme'); ?>">&lt;</a>
                <?php else : ?>
                    <span class="page-btn page-prev disabled" aria-disabled="true">&lt;</span>
                <?php endif; ?>
            </div>

            <div class="page-col page-col-center">
                <select class="page-dropdown" onchange="if(this.value) window.location.href=this.value;">
                    <?php for ($i = 1; $i <= $max; $i++) : ?>
                        <option value="<?php echo esc_url(get_pagenum_link($i)); ?>" <?php selected($paged, $i); ?>>
                            <?php printf(__('Página %d de %d', 'atareao-theme'), $i, $max); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="page-col page-col-next">
                <?php if ($next_url) : ?>
                    <a href="<?php echo esc_url($next_url); ?>" class="page-btn page-next" aria-label="<?php esc_attr_e('Página siguiente', 'atareao-theme'); ?>">&gt;</a>
                <?php else : ?>
                    <span class="page-btn page-next disabled" aria-disabled="true">&gt;</span>
                <?php endif; ?>
            </div>
        </div>
    </nav>
<?php endif; ?>

<?php
get_footer();
