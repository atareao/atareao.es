<?php
/**
 * Template para resultados de búsqueda
 *
 * @package Atareao_Theme
 */

get_header();

global $wp_query;
$max      = intval( $wp_query->max_num_pages );
$paged    = get_query_var('paged') ? absint( get_query_var('paged') ) : 1;
$prev_url = ( $paged > 1    ) ? get_previous_posts_page_link()       : null;
$next_url = ( $paged < $max ) ? get_next_posts_page_link( $max )     : null;
?>

<header class="page-header">
    <div class="archive-intro">
        <p>
            <?php
            printf(
                /* translators: %s: search query */
                __( 'Resultados de búsqueda para: <strong>%s</strong>', 'atareao-theme' ),
                esc_html( get_search_query() )
            );
            if ( $wp_query->found_posts ) {
                printf(
                    /* translators: %d: number of results */
                    _n( ' — %d resultado', ' — %d resultados', $wp_query->found_posts, 'atareao-theme' ),
                    $wp_query->found_posts
                );
            }
            ?>
        </p>
    </div>
</header>

<div class="posts-grid posts-grid-podcast">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>

            <?php
            $post_type_obj = get_post_type_object( get_post_type() );
            $type_label    = $post_type_obj ? esc_html( $post_type_obj->labels->singular_name ) : '';
            ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class( 'podcast-card' ); ?> data-url="<?php the_permalink(); ?>">
                <div class="podcast-archive-layout">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="podcast-thumbnail">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail( 'thumbnail' ); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="podcast-info">
                        <header class="entry-header">
                            <div class="podcast-header-row">
                                <div class="podcast-left-col">
                                    <?php if ( $type_label ) : ?>
                                        <div class="post-type-label"><?php echo $type_label; ?></div>
                                    <?php endif; ?>
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

        <?php endwhile; ?>
    <?php else : ?>
        <?php get_template_part( 'template-parts/content', 'none' ); ?>
    <?php endif; ?>
</div>

<?php if ( $max > 1 ) : ?>
    <nav class="page-navigation" aria-label="<?php esc_attr_e( 'Paginación', 'atareao-theme' ); ?>">
        <div class="page-controls">
            <div class="page-col page-col-prev">
                <?php if ( $prev_url ) : ?>
                    <a href="<?php echo esc_url( $prev_url ); ?>" class="page-btn page-prev" aria-label="<?php esc_attr_e( 'Página anterior', 'atareao-theme' ); ?>">&lt;</a>
                <?php else : ?>
                    <span class="page-btn page-prev disabled" aria-disabled="true">&lt;</span>
                <?php endif; ?>
            </div>
            <div class="page-col page-col-center">
                <select class="page-dropdown" onchange="if(this.value) window.location.href=this.value;">
                    <?php for ( $i = 1; $i <= $max; $i++ ) : ?>
                        <option value="<?php echo esc_url( get_pagenum_link( $i ) ); ?>" <?php selected( $paged, $i ); ?>>
                            <?php printf( __( 'Página %d de %d', 'atareao-theme' ), $i, $max ); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="page-col page-col-next">
                <?php if ( $next_url ) : ?>
                    <a href="<?php echo esc_url( $next_url ); ?>" class="page-btn page-next" aria-label="<?php esc_attr_e( 'Página siguiente', 'atareao-theme' ); ?>">&gt;</a>
                <?php else : ?>
                    <span class="page-btn page-next disabled" aria-disabled="true">&gt;</span>
                <?php endif; ?>
            </div>
        </div>
    </nav>
<?php endif; ?>

<?php
get_footer();
