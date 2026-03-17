<?php
/**
 * Template para archivo de podcasts
 *
 * @package Atareao_Theme
 */

get_header();

// Obtener temporada actual de URL o usar la más reciente
$current_season = isset($_GET['season']) ? intval($_GET['season']) : null;

// Obtener todas las temporadas disponibles
global $wpdb;
$seasons = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT meta_value 
    FROM {$wpdb->postmeta} pm
    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
    WHERE pm.meta_key = %s 
    AND p.post_type = %s 
    AND p.post_status = %s
    AND pm.meta_value != ''
    ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC",
    'season',
    'podcast',
    'publish'
));

// Si no se especificó temporada, usar la más reciente
if ($current_season === null && !empty($seasons)) {
    $current_season = intval($seasons[0]);
}

// Modificar la consulta principal para filtrar por temporada
if ($current_season) {
    global $wp_query;
    $wp_query = new WP_Query(array(
        'post_type' => 'podcast',
        'posts_per_page' => -1, // Mostrar todos los episodios de la temporada
        'meta_key' => 'season',
        'meta_value' => (string)$current_season,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
}
?>

<header class="page-header">
    <div class="podcast-archive-intro">
        <p>El podcast de Linux y Open Source, donde encontrarás desde que es Self Hosting, pasando como montar un servidor de música o de archivos, o cualquier otro servicio que puedas imaginar hasta como exprimir al máximo tu entorno de escritorio Linux. Vamos, cualquier cosa quieras hacer con Linux, seguro, seguro, seguro que la encontrarás aquí.</p>
    </div>
</header>

<?php if (!empty($seasons)) : ?>
    <nav class="season-navigation" aria-label="<?php esc_attr_e('Navegación por temporadas', 'atareao-theme'); ?>">
        <?php
        $current_index = array_search((string)$current_season, $seasons);
        $prev_season = ($current_index !== false && isset($seasons[$current_index + 1])) ? intval($seasons[$current_index + 1]) : null;
        $next_season = ($current_index !== false && isset($seasons[$current_index - 1])) ? intval($seasons[$current_index - 1]) : null;
        ?>
        
        <div class="season-controls">
            <div class="season-col season-col-prev">
                <?php if ($prev_season) : ?>
                    <a href="<?php echo esc_url(add_query_arg('season', $prev_season, get_post_type_archive_link('podcast'))); ?>" class="season-btn season-prev" aria-label="<?php printf(esc_attr__('Temporada %d', 'atareao-theme'), $prev_season); ?>">&lt;</a>
                <?php else : ?>
                    <span class="season-btn season-prev disabled" aria-disabled="true">&lt;</span>
                <?php endif; ?>
            </div>
            
            <div class="season-col season-col-center">
                <?php if (count($seasons) > 1) : ?>
                    <select id="season-dropdown" class="season-dropdown" onchange="if(this.value) window.location.href=this.value;">
                        <?php foreach ($seasons as $season) : ?>
                            <option value="<?php echo esc_url(add_query_arg('season', $season, get_post_type_archive_link('podcast'))); ?>" 
                                    <?php selected($current_season, intval($season)); ?>>
                                <?php printf(__('Temporada %d', 'atareao-theme'), intval($season)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <span class="current-season"><?php printf(__('Temporada %d', 'atareao-theme'), $current_season); ?></span>
                <?php endif; ?>
                <?php if ($current_season && have_posts()) :
                    global $wp_query;
                    $episode_count = $wp_query->found_posts;
                ?>
                <p class="season-info"><?php echo sprintf(
                    _n('%d episodio', '%d episodios', $episode_count, 'atareao-theme'),
                    $episode_count
                ); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="season-col season-col-next">
                <?php if ($next_season) : ?>
                    <a href="<?php echo esc_url(add_query_arg('season', $next_season, get_post_type_archive_link('podcast'))); ?>" class="season-btn season-next" aria-label="<?php printf(esc_attr__('Temporada %d', 'atareao-theme'), $next_season); ?>">&gt;</a>
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
            get_template_part('template-parts/content', 'podcast');
        endwhile;
    else :
        get_template_part('template-parts/content', 'none');
    endif;
    ?>
</div>

<?php
wp_reset_postdata();
get_footer();
