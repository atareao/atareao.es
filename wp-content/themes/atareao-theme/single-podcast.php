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
            
            <div class="entry-meta">
                <?php
                atareao_theme_posted_on();
                atareao_theme_posted_by();
                
                if ($duration) {
                    echo '<span class="duration"><strong>' . __('Duración:', 'atareao-theme') . '</strong> ' . esc_html($duration) . '</span>';
                }
                ?>
            </div>
            <?php echo atareao_share_links( get_the_ID() ); ?>
        </header>

        <?php if (has_post_thumbnail()) : ?>
            <div class="post-thumbnail">
                <?php the_post_thumbnail('large'); ?>
            </div>
        <?php endif; ?>

        <div class="entry-content">
            <?php
            // Obtener el contenido procesado
            $content = apply_filters('the_content', get_the_content());

            // Extraer el bloque atareao-podcast-player usando DOMDocument
            $player_html = '';
            $doc = new DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $content . '</body></html>');
            libxml_clear_errors();

            $xpath = new DOMXPath($doc);

            // Extraer el bloque atareao-podcast-player
            $nodes = $xpath->query('//*[contains(@class,"atareao-podcast-player")]');
            if ($nodes->length > 0) {
                $node = $nodes->item(0);
                $player_html = $doc->saveHTML($node);
                $node->parentNode->removeChild($node);
            }

            // Reconstruir el contenido
            $body = $doc->getElementsByTagName('body')->item(0);
            $content = '';
            foreach ($body->childNodes as $child) {
                $content .= $doc->saveHTML($child);
            }

            // Insertar el player después del primer </p> de texto
            if ($player_html) {
                $pos = strpos($content, '</p>');
                if ($pos !== false) {
                    $content = substr($content, 0, $pos + 4) . "\n" . $player_html . "\n" . substr($content, $pos + 4);
                } else {
                    $content = $player_html . $content;
                }
            } else {
                // Si no hay bloque específico en el contenido, pero existe meta mp3-url,
                // renderizamos el mismo markup que el bloque `atareao-podcast-player`
                if (!empty($audio_url)) {
                    // If the plugin's block renderer is available, use it so markup, JS and styles match exactly
                    if (class_exists('Atareao_Podcast_Block') && method_exists('Atareao_Podcast_Block', 'render_podcast_player')) {
                        // Ensure block frontend style is enqueued if registered
                        if (wp_style_is('atareao-podcast-block-style', 'registered')) {
                            wp_enqueue_style('atareao-podcast-block-style');
                        }

                        $attributes = array(
                            'audioUrl' => $audio_url,
                            'title' => get_the_title(),
                            'description' => get_the_excerpt(),
                            'podcastId' => 0,
                        );

                        $player_html = Atareao_Podcast_Block::render_podcast_player($attributes);
                    } else {
                        // Fallback simple player
                        $player_title = get_the_title();
                        $player_description = get_the_excerpt();
                        $player_html = '<div class="atareao-podcast-player">';
                        if (!empty($player_title)) {
                            $player_html .= '<h3 class="podcast-player-title">' . esc_html($player_title) . '</h3>';
                        }
                        if (!empty($player_description)) {
                            $player_html .= '<p class="podcast-player-description">' . esc_html($player_description) . '</p>';
                        }
                        $player_html .= '<div class="podcast-player-controls">';
                        $player_html .= '<audio controls preload="metadata" class="podcast-audio">';
                        $player_html .= '<source src="' . esc_url($audio_url) . '" type="audio/mpeg">';
                        $player_html .= __('Tu navegador no soporta el elemento de audio.', 'atareao-functionality');
                        $player_html .= '</audio>';
                        $player_html .= '</div>'; // .podcast-player-controls
                        $player_html .= '</div>'; // .atareao-podcast-player
                    }

                    $pos = strpos($content, '</p>');
                    if ($pos !== false) {
                        $content = substr($content, 0, $pos + 4) . "\n" . $player_html . "\n" . substr($content, $pos + 4);
                    } else {
                        $content = $player_html . $content;
                    }
                }
            }

            echo $content;

            wp_link_pages(array(
                'before' => '<div class="page-links">' . __('Páginas:', 'atareao-theme'),
                'after'  => '</div>',
            ));
            ?>
        </div>

        <footer class="entry-footer">
            <?php
            $categories_list = get_the_term_list(get_the_ID(), 'podcast_category', '', ', ');
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
    // Navegación entre episodios con número y meta descripción
    $prev_post = get_previous_post();
    $next_post = get_next_post();

    $get_podcast_meta = function($post) {
        $num  = get_post_meta($post->ID, 'number', true);
        $desc = get_post_meta($post->ID, '_genesis_description', true);
        if ( empty($desc) ) $desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if ( empty($desc) ) $desc = get_post_meta($post->ID, 'rank_math_description', true);
        if ( empty($desc) ) $desc = get_post_meta($post->ID, '_aioseo_description', true);
        if ( empty($desc) ) $desc = $post->post_excerpt;
        return array('num' => $num, 'desc' => $desc);
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
