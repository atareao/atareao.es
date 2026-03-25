<?php
/**
 * Front page (init page) personalizado
 * Three rows:
 * 1) Logo and text
 * 2) Three buttons (Podcast, Tutoriales, Aplicaciones)
 * 3) Two latest publications
 *
 * @package Atareao_Theme
 */

get_header();
?>

<main class="front-init">
    <section class="init-row init-row--brand">
        <div class="init-inner">
            <div class="init-logo">
                <?php if ( function_exists( 'the_custom_logo' ) ) { the_custom_logo(); } ?>
            </div>
            <div class="init-text">
                <h1 class="site-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
                <p class="site-tagline"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
            </div>
        </div>
    </section>

    <section class="init-row init-row--nav">
        <div class="init-inner init-buttons">
            <a href="<?php echo esc_url( get_post_type_archive_link( 'podcast' ) ?: home_url( '/podcast/' ) ); ?>" class="init-btn">
                <span class="init-btn__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v5a3 3 0 0 0 3 3z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M19 11a7 7 0 0 1-14 0" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity="0.6"/>
                        <path d="M12 17v4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                        <path d="M8 21h8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="init-btn__label"><?php esc_html_e( 'Podcast', 'atareao-theme' ); ?></span>
            </a>

            <a href="<?php echo esc_url( get_post_type_archive_link( 'tutorial' ) ?: home_url( '/tutoriales/' ) ); ?>" class="init-btn">
                <span class="init-btn__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M21 3v14a2 2 0 0 1-2 2H7.5A2.5 2.5 0 0 0 5 21.5V6a2 2 0 0 1 2-2h12z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 7h10" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="init-btn__label"><?php esc_html_e( 'Tutoriales', 'atareao-theme' ); ?></span>
            </a>

            <a href="<?php echo esc_url( get_post_type_archive_link( 'aplicacion' ) ?: home_url( '/aplicaciones/' ) ); ?>" class="init-btn">
                <span class="init-btn__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.2"/>
                        <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.2"/>
                        <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.2"/>
                        <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.2"/>
                    </svg>
                </span>
                <span class="init-btn__label"><?php esc_html_e( 'Aplicaciones', 'atareao-theme' ); ?></span>
            </a>
        </div>
    </section>

    <section class="init-row init-row--label">
        <div class="init-inner">
            <h2 class="init-latest-heading"><?php esc_html_e( 'Lo último...', 'atareao-theme' ); ?></h2>
        </div>
    </section>

    <section class="init-row init-row--latest">
        <div class="init-inner">
            <div class="latest-grid">
                <?php
                $post_types = get_post_types( array( 'public' => true ), 'names' );
                if ( isset( $post_types['attachment'] ) ) {
                    unset( $post_types['attachment'] );
                }

                $latest = new WP_Query( array(
                    'posts_per_page'      => 2,
                    'post_status'         => 'publish',
                    'post_type'           => $post_types,
                    'ignore_sticky_posts' => true,
                ) );

                if ( $latest->have_posts() ) :
                    while ( $latest->have_posts() ) : $latest->the_post(); ?>

                        <article id="post-<?php the_ID(); ?>" <?php post_class( 'podcast-card' ); ?> data-url="<?php the_permalink(); ?>">
                            <div class="podcast-archive-layout">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <div class="podcast-thumbnail">
                                        <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'thumbnail' ); ?></a>
                                    </div>
                                <?php endif; ?>

                                <div class="podcast-info">
                                    <header class="entry-header">
                                        <div class="podcast-header-row">
                                            <div class="podcast-left-col">
                                                <div class="podcast-title-row">
                                                    <?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
                                                </div>
                                                <div class="podcast-date">
                                                    <?php echo get_the_date(); ?>
                                                </div>
                                                <div class="entry-meta"></div>
                                            </div>
                                        </div>
                                    </header>
                                </div>
                            </div>

                            <div class="podcast-description">
                                <?php the_excerpt(); ?>
                            </div>
                        </article>

                    <?php endwhile;
                    wp_reset_postdata();
                else : ?>
                    <p><?php esc_html_e( 'No hay publicaciones todavía.', 'atareao-theme' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="init-row init-row--more">
        <div class="init-inner">
            <a href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/' ) ); ?>" class="init-more-btn"><?php esc_html_e( 'Mucho más...', 'atareao-theme' ); ?></a>
        </div>
    </section>
</main>

<?php
get_footer();
