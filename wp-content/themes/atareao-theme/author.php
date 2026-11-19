<?php
/**
 * Archivo de autor indexable con identidad (bio, foto y perfiles externos).
 *
 * Superficie de la capability `author-page`: `/author/{nicename}/` responde 200
 * con un único H1 con el nombre del autor, su biografía, su fotografía y sus
 * perfiles externos. Los mismos perfiles se publican como `sameAs` en la entidad
 * `Person` del grafo (capability `structured-data`).
 *
 * @package Atareao_Theme
 */

get_header();

$atareao_author      = get_queried_object();
$atareao_author_id   = $atareao_author instanceof WP_User ? (int) $atareao_author->ID : (int) get_query_var('author');
$atareao_author_name = get_the_author_meta('display_name', $atareao_author_id);
$atareao_author_bio  = get_the_author_meta('description', $atareao_author_id);

$atareao_profiles = array();
if (class_exists('\Atareao\SEO') && method_exists('\Atareao\SEO', 'socialProfiles')) {
    $atareao_profiles = \Atareao\SEO::socialProfiles();
}
?>

<header class="page-header author-header">
    <h1 class="page-title"><?php echo esc_html($atareao_author_name); ?></h1>

    <div class="author-card">
        <div class="author-avatar">
            <?php echo get_avatar($atareao_author_id, 150); ?>
        </div>

        <?php if ($atareao_author_bio) : ?>
            <div class="author-bio">
                <?php echo wp_kses_post(wpautop($atareao_author_bio)); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($atareao_profiles)) : ?>
            <ul class="author-profiles">
                <?php foreach ($atareao_profiles as $atareao_profile) : ?>
                    <li><a href="<?php echo esc_url($atareao_profile); ?>" rel="me noopener"><?php echo esc_html($atareao_profile); ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</header>

<div class="posts-grid posts-grid-podcast">
    <?php
    if (have_posts()) :
        while (have_posts()) :
            the_post();
            get_template_part('template-parts/content', get_post_type());
        endwhile;
    else :
        get_template_part('template-parts/content', 'none');
    endif;
    ?>
</div>

<?php
get_footer();
