    </main>

    <footer class="site-footer">
        <div class="footer-content">
            <?php
            // Keep existing widget areas and footer menu if present
            if (is_active_sidebar('footer-1') || is_active_sidebar('footer-2')) {
                ?>
                <div class="footer-widgets">
                    <?php if (is_active_sidebar('footer-1')) : ?>
                        <div class="footer-widget-area">
                            <?php dynamic_sidebar('footer-1'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (is_active_sidebar('footer-2')) : ?>
                        <div class="footer-widget-area">
                            <?php dynamic_sidebar('footer-2'); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }

            if (has_nav_menu('footer')) {
                wp_nav_menu(array(
                    'theme_location' => 'footer',
                    'menu_id'        => 'footer-menu',
                    'container'      => 'nav',
                    'container_class' => 'footer-navigation',
                    'depth'          => 1,
                ));
            }
            ?>

            <?php
            // Inline the SVG sprite to ensure symbol <use> references work across browsers
            $sprite_file = get_template_directory() . '/assets/images/sprite.svg';
            if ( file_exists( $sprite_file ) ) {
                // Output safely (file contains SVG <symbol> elements and is authored by the theme)
                echo file_get_contents( $sprite_file );
            }

            ?>

            <div class="footer-flex">
                <!-- Column 1: License / copyright / author / legal -->
                <div class="footer-col footer-col--legal">
                    <div class="footer-logo-license">
                        <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">
                            <span class="social-icon cc" aria-hidden="true">
                                <svg class="svg-icon"><use href="#cc"/></svg>
                            </span>
                        </a>
                    </div>
                    <div class="footer-copyright">
                        &copy; 2010 - <?php echo date('Y'); ?> 
                    </div>
                    <div class="footer-author">
                        <a href="https://lorenzocarbonell.com/" target="_blank" rel="noopener noreferrer">Lorenzo Carbonell</a>
                    </div>
                    <?php if ( $li = get_option( 'atareao_social_linkedin' ) ) : ?>
                        <div class="footer-author-linkedin">
                            <a href="<?php echo esc_url( $li ); ?>" target="_blank" rel="noopener">
                                <span class="social-icon" aria-hidden="true">
                                    <svg class="svg-icon"><use href="#linkedin"/></svg>
                                </span>
                                <span class="social-label">LinkedIn</span>
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="footer-legal">
                        <?php
                        $aviso = get_page_by_path( 'aviso-legal' );
                        if ( $aviso ) {
                            echo '<a href="' . esc_url( get_permalink( $aviso ) ) . '">Aviso legal</a>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Column 2: Podcast / platforms -->
                <div class="footer-col footer-col--podcast">
                    <h4><?php esc_html_e( 'Escucha', 'atareao-theme' ); ?></h4>
                    <ul class="footer-links footer-links--podcast">
                        <?php
                        // podcast RSS (prefer explicit option if set)
                        $podcast_feed = '';
                        $podcast_feed_opt = esc_url( get_option( 'atareao_podcast_feed' ) );
                        if ( ! empty( $podcast_feed_opt ) ) {
                            $podcast_feed = $podcast_feed_opt;
                        } else {
                            $podcast_archive = get_post_type_archive_link( 'podcast' );
                            if ( $podcast_archive ) {
                                $podcast_feed = trailingslashit( $podcast_archive ) . 'feed/';
                            }
                        }

                        if ( ! empty( $podcast_feed ) ) :
                        ?>
                            <li>
                                <a href="<?php echo esc_url( $podcast_feed ); ?>">
                                    <span class="nav-icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3z"/>
                                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                                            <line x1="12" y1="19" x2="12" y2="23"/>
                                            <line x1="8" y1="23" x2="16" y2="23"/>
                                        </svg>
                                    </span>
                                    <span class="social-label">RSS podcast</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ( $yt = get_option( 'atareao_social_youtube' ) ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $yt ); ?>" target="_blank" rel="noopener">
                                    <span class="social-icon" aria-hidden="true">
                                        <svg class="svg-icon"><use href="#youtube"/></svg>
                                    </span>
                                    <span class="social-label">YouTube</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ( $iv = get_option( 'atareao_social_ivoox' ) ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $iv ); ?>" target="_blank" rel="noopener">
                                    <span class="social-icon" aria-hidden="true">
                                        <svg class="svg-icon"><use href="#ivoox"/></svg>
                                    </span>
                                    <span class="social-label">iVoox</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ( $sp = get_option( 'atareao_social_spotify' ) ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $sp ); ?>" target="_blank" rel="noopener">
                                    <span class="social-icon" aria-hidden="true">
                                        <svg class="svg-icon"><use href="#spotify"/></svg>
                                    </span>
                                    <span class="social-label">Spotify</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ( $ap = get_option( 'atareao_social_apple' ) ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $ap ); ?>" target="_blank" rel="noopener">
                                    <span class="social-icon" aria-hidden="true">
                                        <svg class="svg-icon"><use href="#apple"/></svg>
                                    </span>
                                    <span class="social-label">Apple Podcasts</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <!-- Column 3: Social / follow -->
                <div class="footer-col footer-col--social">
                    <h4><?php esc_html_e( 'Sígueme', 'atareao-theme' ); ?></h4>
                    <ul class="footer-links footer-links--social">
                        <li>
                            <a href="<?php echo esc_url( get_bloginfo('rss2_url') ); ?>">
                                <span class="social-icon" aria-hidden="true">
                                    <svg class="svg-icon"><use href="#rss"/></svg>
                                </span>
                                <span class="social-label">RSS</span>
                            </a>
                        </li>
                        <?php if ( $tg = get_option( 'atareao_social_telegram' ) ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $tg ); ?>" target="_blank" rel="noopener">
                                    <span class="social-icon" aria-hidden="true">
                                        <svg class="svg-icon"><use href="#telegram"/></svg>
                                    </span>
                                    <span class="social-label">Telegram</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ( $x = get_option( 'atareao_social_x' ) ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $x ); ?>" target="_blank" rel="noopener">
                                    <span class="social-icon" aria-hidden="true">
                                        <svg class="svg-icon"><use href="#x"/></svg>
                                    </span>
                                    <span class="social-label">X</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ( $md = get_option( 'atareao_social_mastodon' ) ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $md ); ?>" target="_blank" rel="noopener">
                                    <span class="social-icon" aria-hidden="true">
                                        <svg class="svg-icon"><use href="#mastodon"/></svg>
                                    </span>
                                    <span class="social-label">Mastodon</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ( $gh = get_option( 'atareao_social_github' ) ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $gh ); ?>" target="_blank" rel="noopener">
                                    <span class="social-icon" aria-hidden="true">
                                        <svg class="svg-icon"><use href="#github"/></svg>
                                    </span>
                                    <span class="social-label">GitHub</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                    </ul>
                </div>
            </div>
        </div>
    </footer>
</div>

<!-- Botón flotante para subir -->
<button id="back-to-top" class="back-to-top" aria-label="<?php esc_attr_e('Volver arriba', 'atareao-theme'); ?>" title="<?php esc_attr_e('Volver arriba', 'atareao-theme'); ?>">
    <span class="dashicons dashicons-arrow-up-alt2"></span>
</button>

<?php wp_footer(); ?>
</body>
</html>
