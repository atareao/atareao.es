<?php
/**
 * Template part para podcast
 *
 * @package Atareao_Theme
 */

$duration = get_post_meta(get_the_ID(), '_duration', true);
$episode_number = get_post_meta(get_the_ID(), 'number', true);
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('podcast-card'); ?> <?php if (!is_singular()) : ?>data-url="<?php the_permalink(); ?>"<?php endif; ?>>
    <?php if (!is_singular()) : ?>
        <!-- Layout de archivo: imagen 80x80 + info -->
        <div class="podcast-archive-layout">
            <?php if (has_post_thumbnail()) : ?>
                <div class="podcast-thumbnail">
                    <a href="<?php the_permalink(); ?>">
                        <?php the_post_thumbnail('thumbnail'); ?>
                    </a>
                    <?php if ($episode_number) : ?>
                        <span class="episode-number episode-number-overlay"><?php echo esc_html($episode_number); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="podcast-info">
                <header class="entry-header">
                    <div class="podcast-header-row">
                        <div class="podcast-left-col">
                            <div class="podcast-title-row">
                                <?php the_title('<h2 class="entry-title"><a href="' . esc_url(get_permalink()) . '" rel="bookmark">', '</a></h2>'); ?>
                                <?php
                                $audio_url = get_post_meta(get_the_ID(), 'mp3-url', true);
                                if (!empty($audio_url)) {
                                    echo '<button class="toggle-player-btn" data-post-id="' . get_the_ID() . '" aria-expanded="false" aria-label="' . esc_attr__('Mostrar reproductor', 'atareao-theme') . '">';
                                    echo '<span class="dashicons dashicons-arrow-down-alt2"></span>';
                                    echo '</button>';
                                }
                                ?>
                            </div>
                            <div class="podcast-date">
                                <?php echo get_the_date(); ?>
                            </div>
                        </div>
                    </div>
                </header>
            </div>
        </div>

        <div class="podcast-description">
            <?php
            $seo_description = '';
            if (empty($seo_description)) {
                $seo_description = get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true);
            }
            if (empty($seo_description)) {
                $seo_description = get_post_meta(get_the_ID(), 'rank_math_description', true);
            }
            if (empty($seo_description)) {
                $seo_description = get_post_meta(get_the_ID(), '_aioseo_description', true);
            }
            if (empty($seo_description)) {
                $seo_description = get_post_meta(get_the_ID(), '_genesis_description', true);
            }
            if (!empty($seo_description)) {
                echo '<p>' . esc_html($seo_description) . '</p>';
            } else {
                the_excerpt();
            }
            ?>
        </div>

        <div class="podcast-meta">
            <?php
            if ($duration) {
                echo '<span class="duration">';
                echo '<strong>' . __('Duración:', 'atareao-theme') . '</strong> ';
                echo esc_html($duration);
                echo '</span>';
            }
            ?>
        </div>
        
        <?php
        // Reproductor de audio oculto
        $audio_url = get_post_meta(get_the_ID(), 'mp3-url', true);
        if (!empty($audio_url)) :
            $player_id = 'podcast-player-' . get_the_ID();
        ?>
        <div class="podcast-player-container" id="player-container-<?php echo get_the_ID(); ?>" style="display: none;">
            <div class="podcast-player-wrapper">
                <audio id="<?php echo $player_id; ?>" preload="metadata" class="podcast-audio">
                    <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
                </audio>
                
                <div class="simple-podcast-controls">
                    <button class="podcast-play-btn" data-player-id="<?php echo $player_id; ?>">
                        <span class="dashicons dashicons-controls-play"></span>
                    </button>
                    <div class="podcast-progress">
                        <div class="podcast-progress-bar">
                            <div class="podcast-progress-filled"></div>
                        </div>
                    </div>
                    <span class="podcast-time-display">0:00 / 0:00</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php else : ?>
        <!-- Layout singular: tradicional -->
        <?php if (has_post_thumbnail()) : ?>
            <div class="post-thumbnail">
                <?php the_post_thumbnail('large'); ?>
            </div>
        <?php endif; ?>

        <header class="entry-header">
            <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
            
            <div class="entry-meta">
                <?php
                atareao_theme_posted_on();
                
                if ($duration) {
                    echo '<span class="duration">';
                    echo '<strong>' . __('Duración:', 'atareao-theme') . '</strong> ';
                    echo esc_html($duration);
                    echo '</span>';
                }
                ?>
            </div>
        </header>
    <?php endif; ?>

    <?php if (is_singular()) : ?>
        <div class="entry-content">
            <?php the_content(); ?>
        </div>
        
        <footer class="entry-footer">
            <?php
            $categories_list = get_the_term_list(get_the_ID(), 'podcast_category', '', ', ');
            if ($categories_list) {
                printf('<div class="post-categories">%s</div>', $categories_list);
            }
            ?>
        </footer>
    <?php endif; ?>
</article>
