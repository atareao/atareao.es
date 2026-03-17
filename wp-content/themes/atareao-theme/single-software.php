<?php
/**
 * Template para mostrar software individual
 *
 * @package Atareao_Theme
 */

get_header();
?>

<?php
while (have_posts()) :
    the_post();
    
    // Obtener metadatos
    $download_url = get_post_meta(get_the_ID(), '_download_url', true);
    $repository_url = get_post_meta(get_the_ID(), '_repository_url', true);
    $version = get_post_meta(get_the_ID(), '_version', true);
    $platforms = get_the_terms(get_the_ID(), 'platform');
    $difficulty = get_the_terms(get_the_ID(), 'difficulty');
    ?>
    
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
            
            <div class="entry-meta">
                <?php
                atareao_theme_posted_on();
                
                if ($version) {
                    echo '<span class="version"><strong>' . __('Versión:', 'atareao-theme') . '</strong> ' . esc_html($version) . '</span>';
                }
                
                if ($platforms && !is_wp_error($platforms)) {
                    $platform_names = array();
                    foreach ($platforms as $platform) {
                        $platform_names[] = $platform->name;
                    }
                    echo '<span class="platforms"><strong>' . __('Plataformas:', 'atareao-theme') . '</strong> ' . esc_html(implode(', ', $platform_names)) . '</span>';
                }
                
                if ($difficulty && !is_wp_error($difficulty)) {
                    echo '<span class="difficulty"><strong>' . __('Dificultad:', 'atareao-theme') . '</strong> ' . esc_html($difficulty[0]->name) . '</span>';
                }
                ?>
            </div>
            
            <div class="software-actions">
                <?php if ($download_url) : ?>
                    <a href="<?php echo esc_url($download_url); ?>" class="btn btn-primary" target="_blank" rel="noopener">
                        <?php _e('Descargar', 'atareao-theme'); ?>
                    </a>
                <?php endif; ?>
                
                <?php if ($repository_url) : ?>
                    <a href="<?php echo esc_url($repository_url); ?>" class="btn btn-secondary" target="_blank" rel="noopener">
                        <?php _e('Ver Repositorio', 'atareao-theme'); ?>
                    </a>
                <?php endif; ?>
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
            $categories_list = get_the_term_list(get_the_ID(), 'software_category', '', ', ');
            if ($categories_list) {
                printf('<div class="post-categories"><strong>%s:</strong> %s</div>', 
                    __('Categorías', 'atareao-theme'), 
                    $categories_list
                );
            }
            ?>
        </footer>
    </article>

    <?php
    // Navegación entre posts
    the_post_navigation(array(
        'prev_text' => '<span class="nav-subtitle">' . __('Software anterior:', 'atareao-theme') . '</span> <span class="nav-title">%title</span>',
        'next_text' => '<span class="nav-subtitle">' . __('Software siguiente:', 'atareao-theme') . '</span> <span class="nav-title">%title</span>',
    ));

    // Comentarios
    if (comments_open() || get_comments_number()) :
        comments_template();
    endif;

endwhile;
?>

<?php
get_footer();
