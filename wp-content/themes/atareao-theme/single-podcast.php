<?php
/**
 * Template para mostrar un podcast individual
 *
 * @package Atareao_Theme
 */

get_header();
?>

<?php
while (have_posts()) :
    the_post();
    
    // Obtener metadatos
    $audio_url = get_post_meta(get_the_ID(), 'mp3-url', true);
    $duration = get_post_meta(get_the_ID(), '_duration', true);
    ?>
    
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
            <?php atareao_theme_post_views(); ?>

            <?php
            // Breadcrumb: Podcast / Temporada (placed after title for better reading order)
            $season = get_post_meta(get_the_ID(), 'season', true);
            $archive_link = get_post_type_archive_link('podcast');
            ?>
            <nav class="entry-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'atareao-theme'); ?>">
                <a class="breadcrumb-home" href="<?php echo esc_url($archive_link); ?>"><?php esc_html_e('Podcast', 'atareao-theme'); ?></a>
                <?php if (!empty($season)) :
                    $season_url = esc_url(add_query_arg(array( 'season' => $season ), $archive_link)); ?>
                    <span class="breadcrumb-sep">/</span>
                    <a class="podcast-season" href="<?php echo $season_url; ?>" rel="nofollow noopener"><?php printf(esc_html__('Temporada %s', 'atareao-theme'), esc_html($season)); ?></a>
                <?php endif; ?>
            </nav>

            <div class="entry-meta">
                <?php
                atareao_theme_posted_on();
                atareao_theme_posted_by();

                // Fecha de modificación (si difiere de la de publicación)
                $published = get_the_date();
                $modified  = get_the_modified_date();
                if ($modified && $modified !== $published) {
                    echo '<span class="modified-date">';
                    echo '<strong>' . esc_html__('Modificado:', 'atareao-theme') . '</strong> ' . esc_html($modified);
                    echo '</span>';
                }

                if ($duration) {
                    echo '<span class="duration"><strong>' . __('Duración:', 'atareao-theme') . '</strong> ' . esc_html($duration) . '</span>';
                }

                atareao_theme_comment_count();
                ?>
            </div>
            <?php echo atareao_share_links(get_the_ID()); ?>
        </header>

        <div class="entry-content">
            <?php
            $raw = get_the_content();
            $player = '';

            // Strip existing player block from raw Gutenberg content
            $raw = preg_replace(
                '#<!--\s*wp:atareao/podcast-player\s*-->.*?<!--\s*/wp:atareao/podcast-player\s*-->#is',
                '',
                $raw
            );

            // Build output: player → thumbnail → content
            $parts = array();

            if (!empty($audio_url)) {
                if (class_exists('\Atareao\PodcastBlock') && method_exists('\Atareao\PodcastBlock', 'renderPodcastPlayer')) {
                    if (wp_style_is('atareao-podcast-block-style', 'registered')) {
                        wp_enqueue_style('atareao-podcast-block-style');
                    }
                    $parts[] = \Atareao\PodcastBlock::renderPodcastPlayer(array(
                        'audioUrl'    => $audio_url,
                        'title'       => get_the_title(),
                        'description' => get_the_excerpt(),
                        'podcastId'   => 0,
                    ));
                } else {
                    $player = '<div class="atareao-podcast-player">';
                    $player .= '<h3 class="podcast-player-title">' . esc_html(get_the_title()) . '</h3>';
                    $player .= '<p class="podcast-player-description">' . esc_html(get_the_excerpt()) . '</p>';
                    $player .= '<div class="podcast-player-controls">';
                    $player .= '<audio controls preload="metadata" class="podcast-audio">';
                    $player .= '<source src="' . esc_url($audio_url) . '" type="audio/mpeg">';
                    $player .= __('Tu navegador no soporta el elemento de audio.', 'atareao-functionality');
                    $player .= '</audio></div></div>';
                    $parts[] = $player;
                }
            }

            if (has_post_thumbnail()) {
                $parts[] = '<div class="post-thumbnail">' . get_the_post_thumbnail(null, 'large', array('loading' => 'lazy', 'fetchpriority' => false)) . '</div>';
            }

            $parts[] = $raw;
            $raw = implode("\n", $parts);

            echo apply_filters('the_content', $raw);

            wp_link_pages(array(
                'before' => '<div class="page-links">' . __('Páginas:', 'atareao-theme'),
                'after'  => '</div>',
            ));
            ?>
        </div>

        <footer class="entry-footer">
            <?php
            $categories_list = get_the_term_list(get_the_ID(), 'podcast_category', '', ', ');
            if ($categories_list && ! is_wp_error($categories_list)) {
                printf(
                    '<div class="post-categories"><strong>%s:</strong> %s</div>',
                    __('Categorías', 'atareao-theme'),
                    $categories_list
                );
            }
            ?>
        </footer>
    </article>

    <?php
    // Navegación entre episodios con número y meta descripción
    $prev_post = get_previous_post();
    $next_post = get_next_post();

    $get_podcast_meta = function ($post) {
        return array(
            'num'  => get_post_meta($post->ID, 'number', true),
            'desc' => atareao_get_seo_description($post),
        );
    };

    if ($prev_post || $next_post) : ?>
    <nav class="post-navigation" aria-label="<?php esc_attr_e('Navegación entre episodios', 'atareao-theme'); ?>">
        <div class="nav-links">
            <?php if ($prev_post) :
                $prev_meta = $get_podcast_meta($prev_post);
                ?>
            <div class="nav-previous">
                <a href="<?php echo esc_url(get_permalink($prev_post->ID)); ?>">
                    <span class="nav-arrow">&lt;</span>
                    <span class="nav-content">
                        <span class="nav-subtitle"><?php esc_html_e('Anterior', 'atareao-theme'); ?></span>
                        <span class="nav-title">
                            <?php if ($prev_meta['num']) : ?>
                                <span class="chapter-number-badge"><?php echo esc_html($prev_meta['num']); ?></span>
                            <?php endif; ?>
                            <?php echo esc_html($prev_post->post_title); ?>
                        </span>
                        <?php if ($prev_meta['desc']) : ?>
                            <span class="nav-desc"><?php echo esc_html($prev_meta['desc']); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($next_post) :
                $next_meta = $get_podcast_meta($next_post);
                ?>
            <div class="nav-next">
                <a href="<?php echo esc_url(get_permalink($next_post->ID)); ?>">
                    <span class="nav-content">
                        <span class="nav-subtitle"><?php esc_html_e('Siguiente', 'atareao-theme'); ?></span>
                        <span class="nav-title">
                            <?php if ($next_meta['num']) : ?>
                                <span class="chapter-number-badge"><?php echo esc_html($next_meta['num']); ?></span>
                            <?php endif; ?>
                            <?php echo esc_html($next_post->post_title); ?>
                        </span>
                        <?php if ($next_meta['desc']) : ?>
                            <span class="nav-desc"><?php echo esc_html($next_meta['desc']); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="nav-arrow">&gt;</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>

    <?php
    // Comentarios
    if (comments_open() || get_comments_number()) :
        comments_template();
    endif;
endwhile;
?>

<?php
get_footer();
