<?php
require_once get_template_directory() . '/includes/matrix-config.php';
/**
 * Atareao Theme Functions
 *
 * @package Atareao_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuración del tema
 */
function atareao_theme_setup() {
    // Soporte para título dinámico
    add_theme_support('title-tag');
    
    // Soporte para imágenes destacadas
    add_theme_support('post-thumbnails');
    
    // Tamaños de imagen personalizados
    add_image_size('atareao-thumbnail', 400, 300, true);
    add_image_size('atareao-medium', 800, 600, true);
    add_image_size('atareao-large', 1200, 800, true);
    
    // Soporte para HTML5
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script'
    ));
    
    // Soporte para feed automático
    add_theme_support('automatic-feed-links');
    
    // Soporte para logo personalizado
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ));
    
    // Soporte para menús
    register_nav_menus(array(
        'primary' => __('Menú Principal', 'atareao-theme'),
        'footer'  => __('Menú Footer', 'atareao-theme'),
    ));
    
    // Soporte para editor de bloques
    add_theme_support('align-wide');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    add_editor_style('css/editor-style.css');
    
    // Habilitar responsive images
    add_theme_support('post-thumbnails');
    set_post_thumbnail_size(1200, 9999);
}
add_action('after_setup_theme', 'atareao_theme_setup');

/**
 * Configurar tamaños de imágenes responsive
 */
function atareao_theme_image_sizes() {
    // WordPress generará estas versiones automáticamente
    update_option('thumbnail_size_w', 150);
    update_option('thumbnail_size_h', 150);
    update_option('thumbnail_crop', 1);
    
    update_option('medium_size_w', 300);
    update_option('medium_size_h', 300);
    update_option('medium_crop', 0);
    
    update_option('medium_large_size_w', 768);
    update_option('medium_large_size_h', 0);
    
    update_option('large_size_w', 1024);
    update_option('large_size_h', 1024);
    update_option('large_crop', 0);
}
add_action('after_setup_theme', 'atareao_theme_image_sizes');

/**
 * Añadir atributo loading="lazy" a las imágenes
 */
function atareao_theme_add_lazy_loading($attr, $attachment, $size) {
    $attr['loading'] = 'lazy';
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'atareao_theme_add_lazy_loading', 10, 3);

/**
 * Customizar los breakpoints de srcset
 */
function atareao_theme_custom_srcset_sizes($sizes, $size, $image_src, $image_meta, $attachment_id) {
    if (is_singular()) {
        // En páginas individuales
        $sizes = '(max-width: 768px) 100vw, (max-width: 1024px) 80vw, 1200px';
    } else {
        // En listados
        $sizes = '(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw';
    }
    return $sizes;
}
add_filter('wp_calculate_image_sizes', 'atareao_theme_custom_srcset_sizes', 10, 5);

/**
 * Mejorar la calidad de las imágenes JPEG
 */
function atareao_theme_jpeg_quality($quality, $context) {
    return 85; // Balance entre calidad y tamaño de archivo
}
add_filter('jpeg_quality', 'atareao_theme_jpeg_quality', 10, 2);
add_filter('wp_editor_set_quality', 'atareao_theme_jpeg_quality', 10, 2);

/**
 * Deshabilitar el threshold de imágenes grandes para preservar calidad
 */
function atareao_theme_big_image_threshold($threshold, $imagesize, $file, $attachment_id) {
    // Permitir imágenes hasta 2560px
    return 2560;
}
add_filter('big_image_size_threshold', 'atareao_theme_big_image_threshold', 10, 4);

/**
 * Registrar y cargar scripts y estilos
 */
function atareao_theme_scripts() {
    // Estilo principal
    wp_enqueue_style('atareao-style', get_stylesheet_uri(), array(), '1.0.0');
    
    // Estilos para custom post types
    wp_enqueue_style('atareao-cpt-style', get_template_directory_uri() . '/css/custom-post-types.css', array('atareao-style'), '1.0.0');
    
    // Dashicons para el reproductor de podcast
    wp_enqueue_style('dashicons');
    
    // Script principal
    wp_enqueue_script('atareao-script', get_template_directory_uri() . '/js/main.js', array(), '1.0.0', true);
    
    // Script de navegación
    wp_enqueue_script('atareao-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '1.0.0', true);
    
    // Script para comentarios si es necesario
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'atareao_theme_scripts');

// Enqueue and localize AJAX comment script
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('atareao-comment-ajax', get_template_directory_uri() . '/js/comment-ajax.js', array(), '1.0.0', true);
    wp_localize_script('atareao-comment-ajax', 'atareao_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('atareao_comment_nonce'),
    ));
});

// AJAX handler for comment submissions to avoid a full page reload
add_action('wp_ajax_nopriv_atareao_submit_comment', 'atareao_ajax_submit_comment');
add_action('wp_ajax_atareao_submit_comment', 'atareao_ajax_submit_comment');

function atareao_ajax_submit_comment() {
    check_ajax_referer('atareao_comment_nonce', 'nonce');

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $error = '';

    $post_id = isset($_POST['comment_post_ID']) ? intval($_POST['comment_post_ID']) : 0;
    $parent  = isset($_POST['comment_parent']) ? intval($_POST['comment_parent']) : 0;
    $author  = isset($_POST['author']) ? sanitize_text_field(wp_unslash($_POST['author'])) : '';
    $email   = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $url     = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
    $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
    $user_captcha = isset($_POST['atareao_comment_captcha']) ? intval($_POST['atareao_comment_captcha']) : null;
    $expected_captcha = isset($_SESSION['atareao_comment_captcha']) ? intval($_SESSION['atareao_comment_captcha']) : null;
    $honeypot = isset($_POST['atareao_comment_hp']) ? trim(wp_unslash($_POST['atareao_comment_hp'])) : '';
    $form_time = isset($_POST['atareao_comment_form_time']) ? intval($_POST['atareao_comment_form_time']) : 0;
    $now = time();

    if (!empty($honeypot)) {
        $error = __('Error de validación.', 'atareao-theme');
    } elseif (null === $user_captcha || $user_captcha !== $expected_captcha) {
        $error = __('Captcha incorrecto. Inténtalo de nuevo.', 'atareao-theme');
    } elseif ($form_time && ($now - $form_time) < 2) {
        $error = __('Formulario enviado demasiado rápido.', 'atareao-theme');
    }

    // Generate a fresh captcha (replace the previous one) and store time
    $new_a = rand(1, 9);
    $new_b = rand(1, 9);
    $_SESSION['atareao_comment_captcha'] = $new_a + $new_b;
    $_SESSION['atareao_comment_captcha_a'] = $new_a;
    $_SESSION['atareao_comment_captcha_b'] = $new_b;
    $_SESSION['atareao_comment_form_time'] = time();

    if (!empty($error)) {
        wp_send_json_error(array('message' => $error, 'new_a' => $new_a, 'new_b' => $new_b, 'new_time' => $_SESSION['atareao_comment_form_time']));
    }

    // Build comment data and insert
    $commentdata = array(
        'comment_post_ID' => $post_id,
        'comment_parent'  => $parent,
        'comment_author'  => $author,
        'comment_author_email' => $email,
        'comment_author_url' => $url,
        'comment_content' => $comment,
        'user_ID'         => get_current_user_id(),
    );

    $comment_id = wp_new_comment($commentdata);
    if (is_wp_error($comment_id)) {
        wp_send_json_error(array('message' => $comment_id->get_error_message(), 'new_a' => $new_a, 'new_b' => $new_b, 'new_time' => $_SESSION['atareao_comment_form_time']));
    }

    $comment_obj = get_comment($comment_id);
    // Capture the rendered HTML for this comment using the theme callback
    ob_start();
    // Ensure global args consistent with wp_list_comments
    $args = array(
        'style' => 'ol',
        'avatar_size' => 50,
        'short_ping' => true,
        'has_children' => false,
        'max_depth' => intval(get_option('thread_comments_depth', 5)),
    );
    // Make sure global $comment is set so template tags output correctly
    $prev_comment = isset($GLOBALS['comment']) ? $GLOBALS['comment'] : null;
    $GLOBALS['comment'] = $comment_obj;
    // The theme's comment callback should be defined; call it to echo markup
    if (function_exists('atareao_comment_callback')) {
        atareao_comment_callback($comment_obj, $args, 1);
    } else {
        // Fallback: simple HTML using explicit functions that accept the comment object
        echo '<li id="comment-' . esc_attr($comment_obj->comment_ID) . '" class="comment">';
        echo '<div class="comment-body"><div class="comment-author"><b class="fn">' . esc_html(get_comment_author($comment_obj)) . '</b></div>';
        if ($comment_obj->comment_approved == '0') {
            echo '<p class="comment-awaiting-moderation">' . __('Tu comentario está pendiente de moderación.', 'atareao-theme') . '</p>';
        }
        echo '<div class="comment-content">' . get_comment_text($comment_obj) . '</div></div>';
        echo '</li>';
    }
    // restore previous global comment
    if (null !== $prev_comment) {
        $GLOBALS['comment'] = $prev_comment;
    } else {
        unset($GLOBALS['comment']);
    }
    $comment_html = ob_get_clean();

    wp_send_json_success(array(
        'message' => __('Comentario enviado. Gracias.', 'atareao-theme'),
        'comment_html' => $comment_html,
        'parent' => intval($comment_obj->comment_parent),
        'new_a' => $new_a,
        'new_b' => $new_b,
        'new_time' => $_SESSION['atareao_comment_form_time']
    ));
}

/**
 * Start PHP session for captcha and form tokens if not started
 */
function atareao_start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
}
add_action('init', 'atareao_start_session', 1);

/**
 * Validate comment submission: captcha, honeypot and timing
 */
function atareao_validate_comment($commentdata) {
    // Only validate for non-admin AJAX/cron contexts
    if (is_admin()) {
        return $commentdata;
    }

    // Ensure session exists
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $error = '';

    $user_captcha = isset($_POST['atareao_comment_captcha']) ? intval($_POST['atareao_comment_captcha']) : null;
    $expected_captcha = isset($_SESSION['atareao_comment_captcha']) ? intval($_SESSION['atareao_comment_captcha']) : null;
    $honeypot = isset($_POST['atareao_comment_hp']) ? trim($_POST['atareao_comment_hp']) : '';
    $form_time = isset($_POST['atareao_comment_form_time']) ? intval($_POST['atareao_comment_form_time']) : 0;
    $now = time();

    if (!empty($honeypot)) {
        $error = __('Error de validación.', 'atareao-theme');
    } elseif (null === $user_captcha || $user_captcha !== $expected_captcha) {
        $error = __('Captcha incorrecto. Inténtalo de nuevo.', 'atareao-theme');
    } elseif ($form_time && ($now - $form_time) < 2) {
        $error = __('Formulario enviado demasiado rápido.', 'atareao-theme');
    }

    if (!empty($error)) {
        // Store error in session and redirect back to the referrer so we can show it inline
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['atareao_comment_error'] = $error;
        // Redirect back to the referrer (post) anchor to the comment form
        $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
        wp_safe_redirect($ref . '#respond');
        exit;
    }

    return $commentdata;
}
add_filter('preprocess_comment', 'atareao_validate_comment', 1);

/**
 * Registrar áreas de widgets
 */
function atareao_theme_widgets_init() {
    register_sidebar(array(
        'name'          => __('Sidebar Principal', 'atareao-theme'),
        'id'            => 'sidebar-1',
        'description'   => __('Widgets para el sidebar principal', 'atareao-theme'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
    
    register_sidebar(array(
        'name'          => __('Footer 1', 'atareao-theme'),
        'id'            => 'footer-1',
        'description'   => __('Widgets para el footer área 1', 'atareao-theme'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
    
    register_sidebar(array(
        'name'          => __('Footer 2', 'atareao-theme'),
        'id'            => 'footer-2',
        'description'   => __('Widgets para el footer área 2', 'atareao-theme'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
}
add_action('widgets_init', 'atareao_theme_widgets_init');

/**
 * Personalizar el excerpt
 */
function atareao_theme_excerpt_length($length) {
    return 25;
}
add_filter('excerpt_length', 'atareao_theme_excerpt_length');

function atareao_theme_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'atareao_theme_excerpt_more');

/**
 * Añadir clases al body
 */
function atareao_theme_body_classes($classes) {
    if (!is_singular()) {
        $classes[] = 'hfeed';
    }
    
    if (is_active_sidebar('sidebar-1')) {
        $classes[] = 'has-sidebar';
    }
    
    return $classes;
}
add_filter('body_class', 'atareao_theme_body_classes');

/**
 * Función para mostrar la paginación
 */
function atareao_theme_pagination() {
    if (is_singular()) {
        return;
    }
    
    global $wp_query;
    
    if ($wp_query->max_num_pages <= 1) {
        return;
    }
    
    $paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
    $max   = intval($wp_query->max_num_pages);
    
    if ($paged >= 1) {
        $links[] = $paged;
    }
    
    if ($paged >= 3) {
        $links[] = $paged - 1;
        $links[] = $paged - 2;
    }
    
    if (($paged + 2) <= $max) {
        $links[] = $paged + 2;
        $links[] = $paged + 1;
    }
    
    echo '<div class="pagination"><span class="pages">Página ' . $paged . ' de ' . $max . '</span>';
    
    if (get_previous_posts_link()) {
        printf('<a href="%s">%s</a>', get_previous_posts_page_link(), '&laquo; Anterior');
    }
    
    if (!in_array(1, $links)) {
        $class = 1 == $paged ? ' current' : '';
        printf('<a href="%s" class="%s">%s</a>', esc_url(get_pagenum_link(1)), $class, '1');
        
        if (!in_array(2, $links)) {
            echo '<span>...</span>';
        }
    }
    
    sort($links);
    foreach ((array) $links as $link) {
        $class = $paged == $link ? ' current' : '';
        printf('<a href="%s" class="%s">%s</a>', esc_url(get_pagenum_link($link)), $class, $link);
    }
    
    if (!in_array($max, $links)) {
        if (!in_array($max - 1, $links)) {
            echo '<span>...</span>';
        }
        
        $class = $paged == $max ? ' current' : '';
        printf('<a href="%s" class="%s">%s</a>', esc_url(get_pagenum_link($max)), $class, $max);
    }
    
    if (get_next_posts_link()) {
        printf('<a href="%s">%s</a>', get_next_posts_page_link(), 'Siguiente &raquo;');
    }
    
    echo '</div>';
}

/**
 * Función helper para mostrar la fecha de publicación
 */
function atareao_theme_posted_on() {
    $time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
    
    $time_string = sprintf($time_string,
        esc_attr(get_the_date(DATE_W3C)),
        esc_html(get_the_date())
    );
    
    printf('<span class="posted-on">%s</span>', $time_string);
}

/**
 * Función helper para mostrar el autor
 */
function atareao_theme_posted_by() {
    printf(
        '<span class="author vcard"><a class="url fn n" href="%1$s">%2$s</a></span>',
        esc_url(get_author_posts_url(get_the_author_meta('ID'))),
        esc_html(get_the_author())
    );
}

/**
 * Build share URLs for a post for X, Mastodon and Telegram.
 *
 * @param int|WP_Post|null $post Post ID or WP_Post object. Defaults to global post.
 * @return array Associative array with keys 'x','mastodon','telegram'
 */
function atareao_share_links( $post = null ) {
    if ( is_null( $post ) ) {
        global $post;
    }

    $post_id = is_object( $post ) ? $post->ID : intval( $post );
    if ( ! $post_id ) {
        return array( 'x' => '', 'mastodon' => '', 'telegram' => '' );
    }

    $permalink = rawurlencode( get_permalink( $post_id ) );
    $title = rawurlencode( html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) );

    // X (Twitter) intent
    $x_url = "https://twitter.com/intent/tweet?text={$title}&url={$permalink}";

    // Telegram share
    $tg_url = "https://t.me/share/url?url={$permalink}&text={$title}";

    // Mastodon: if user set a mastodon URL option use its host for share endpoint
    $mastodon_opt = esc_url_raw( get_option( 'atareao_social_mastodon' ) );
    $mastodon_share_base = 'https://mastodon.social/share';
    if ( ! empty( $mastodon_opt ) ) {
        $host = parse_url( $mastodon_opt, PHP_URL_HOST );
        if ( $host ) {
            $mastodon_share_base = 'https://' . $host . '/share';
        }
    }
    $md_url = $mastodon_share_base . '?text=' . $title . '%20' . $permalink;

    $share = array(
        'x' => array(
            'url'  => esc_url_raw( $x_url ),
            'icon' => '<svg class="svg-icon"><use href="#x"/></svg>',
        ),
        'mastodon' => array(
            'url'  => esc_url_raw( $md_url ),
            'icon' => '<svg class="svg-icon"><use href="#mastodon"/></svg>',
        ),
        'telegram' => array(
            'url'  => esc_url_raw( $tg_url ),
            'icon' => '<svg class="svg-icon"><use href="#telegram"/></svg>',
        ),
    );
    return "<div class=\"entry-share\">
            <span class=\"share-label\">Comparte en</span>
            <a class=\"share-btn share-x\" href=\"{$share['x']['url']}\" target=\"_blank\" rel=\"noopener noreferrer\" aria-label=\"Share on X\">
                <span class=\"social-icon\" aria-hidden=\"true\">{$share['x']['icon']}</span>
            </a>
            <a class=\"share-btn share-mastodon\" href=\"{$share['mastodon']['url']}\" target=\"_blank\" rel=\"noopener noreferrer\" aria-label=\"Share on Mastodon\">
                <span class=\"social-icon\" aria-hidden=\"true\">{$share['mastodon']['icon']}</span>
            </a>
            <a class=\"share-btn share-telegram\" href=\"{$share['telegram']['url']}\" target=\"_blank\" rel=\"noopener noreferrer\" aria-label=\"Share on Telegram\">
                <span class=\"social-icon\" aria-hidden=\"true\">{$share['telegram']['icon']}</span>
            </a>
        </div>
    ";
}

/**
 * 8 tutoriales por página en el archivo de tutoriales
 */
function atareao_theme_tutorial_posts_per_page( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( 'tutorial' ) ) {
        $query->set( 'posts_per_page', 8 );
        $query->set( 'orderby', 'date' );
        $query->set( 'order', 'DESC' );
    }
}
add_action( 'pre_get_posts', 'atareao_theme_tutorial_posts_per_page' );

/**
 * Mostrar en la página del blog entradas de todos los post types públicos
 */
function atareao_theme_blog_all_post_types( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_home() ) {
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        // Keep attachments out of the blog listing
        if ( isset( $post_types['attachment'] ) ) {
            unset( $post_types['attachment'] );
        }

        $query->set( 'post_type', $post_types );
        $query->set( 'orderby', 'date' );
        $query->set( 'order', 'DESC' );
    }
}
add_action( 'pre_get_posts', 'atareao_theme_blog_all_post_types' );

/**
 * Theme Options page: register settings and add admin page for social links
 */
function atareao_register_settings() {
    $social_keys = array( 'youtube','ivoox','spotify','apple','telegram','x','mastodon','github','linkedin' );
    foreach ( $social_keys as $key ) {
        register_setting( 'atareao_options_group', 'atareao_social_' . $key, array( 'sanitize_callback' => 'esc_url_raw' ) );
    }
    // Podcast feed URL option
    register_setting( 'atareao_options_group', 'atareao_podcast_feed', array( 'sanitize_callback' => 'esc_url_raw' ) );
}
add_action( 'admin_init', 'atareao_register_settings' );

function atareao_theme_options_page() {
    add_theme_page(
        __( 'Atareao Theme Options', 'atareao-theme' ),
        __( 'Theme Options', 'atareao-theme' ),
        'manage_options',
        'atareao-theme-options',
        'atareao_theme_options_page_html'
    );
}
add_action( 'admin_menu', 'atareao_theme_options_page' );

function atareao_theme_options_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $social = array(
        'youtube' => 'YouTube',
        'ivoox'   => 'iVoox',
        'spotify' => 'Spotify',
        'apple'   => 'Apple Podcasts',
        'telegram'=> 'Telegram',
        'x'       => 'X',
        'mastodon'=> 'Mastodon',
        'github'  => 'GitHub',
        'linkedin'=> 'LinkedIn',
    );

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Atareao Theme Options', 'atareao-theme' ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'atareao_options_group' ); ?>
            <table class="form-table" role="presentation">
                <tbody>
                <?php foreach ( $social as $key => $label ) :
                    $option_name = 'atareao_social_' . $key;
                    $value = esc_url( get_option( $option_name ) );
                    ?>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr( $option_name ); ?>"><?php echo esc_html( $label ); ?> URL</label></th>
                        <td>
                            <input name="<?php echo esc_attr( $option_name ); ?>" type="url" id="<?php echo esc_attr( $option_name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                        </td>
                    </tr>
                <?php endforeach; ?>

                <!-- Podcast feed URL -->
                <?php $podcast_feed_val = esc_url( get_option( 'atareao_podcast_feed' ) ); ?>
                <tr>
                    <th scope="row"><label for="atareao_podcast_feed"><?php esc_html_e( 'Podcast feed URL', 'atareao-theme' ); ?></label></th>
                    <td>
                        <input name="atareao_podcast_feed" type="url" id="atareao_podcast_feed" value="<?php echo esc_attr( $podcast_feed_val ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Optional: override the automatic podcast archive feed URL.', 'atareao-theme' ); ?></p>
                    </td>
                </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Envuelve iframes de YouTube/Vimeo en un contenedor responsive 16:9
 */
function atareao_theme_responsive_embeds( $content ) {
    if ( ! is_singular() ) {
        return $content;
    }
    $pattern     = '/<iframe(?![^>]*class="[^"]*video-responsive)[^>]*(youtube\.com|youtu\.be|vimeo\.com)[^>]*>.*?<\/iframe>/is';
    $replacement = '<div class="video-responsive">$0</div>';
    return preg_replace( $pattern, $replacement, $content );
}
add_filter( 'the_content', 'atareao_theme_responsive_embeds' );

/**
 * Notify Matrix room when a new comment is posted.
 * Sends a simple text message: "Comentario de <autor>\n<contenido>"
 */
function atareao_notify_matrix_on_comment($comment_id, $comment_approved) {
    // Only send when comment is approved/published
    if (intval($comment_approved) !== 1 && $comment_approved !== '1') {
        return;
    }
    // Load comment
    $comment = get_comment($comment_id);
    if (!$comment) {
        return;
    }

    // Prepare message
    $author = get_comment_author($comment);
    $content = wp_strip_all_tags(get_comment_text($comment));
    $host = parse_url(home_url(), PHP_URL_HOST) ? parse_url(home_url(), PHP_URL_HOST) : 'atareao.es';
    $message = sprintf("Comentario de %s en %s\n%s", $author, $host, $content);

    // Matrix settings from options
    $matrix_url = sanitize_text_field(get_option('atareao_matrix_url'));
    $matrix_token = sanitize_text_field(get_option('atareao_matrix_token'));
    $matrix_room = sanitize_text_field(get_option('atareao_matrix_room'));

    if (empty($matrix_url) || empty($matrix_token) || empty($matrix_room)) {
        return; // not configured
    }

    $txn_id = uniqid('wp_comment_', true);
    $endpoint = rtrim($matrix_url, '/') . "/_matrix/client/v3/rooms/$matrix_room/send/m.room.message/$txn_id";
    $payload = array(
        'msgtype' => 'm.text',
        'body' => $message,
    );
    $args = array(
        'method' => 'PUT',
        'body' => wp_json_encode($payload),
        'headers' => array(
            'Authorization' => 'Bearer ' . $matrix_token,
            'Content-Type' => 'application/json',
        ),
        'timeout' => 10,
    );

    // Fire and forget; don't interrupt comment flow on failure
    $response = wp_remote_request($endpoint, $args);
    // optional: could log failures with error_log or monitoring
}
add_action('comment_post', 'atareao_notify_matrix_on_comment', 10, 2);

/**
 * Callback personalizado para mostrar comentarios individuales (disponible para AJAX)
 */
function atareao_comment_callback($comment, $args, $depth) {
    $tag = ('div' === $args['style']) ? 'div' : 'li';

    // Build initials (up to 2 chars) from author name
    $author_name = $comment->comment_author ?: 'An';
    $parts       = explode(' ', trim($author_name));
    $initials    = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr($parts[1], 0, 1));
    } else {
        $initials .= strtoupper(substr($parts[0], 1, 1));
    }

    // Deterministic background color from author name
    $palette     = ['#e74c3c','#e67e22','#d4a017','#2ecc71','#1abc9c','#3498db','#9b59b6','#e91e63'];
    $bg_color    = $palette[abs(crc32($author_name)) % count($palette)];
    ?>
    <<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class(empty($args['has_children']) ? '' : 'parent'); ?>>
        <article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
            <footer class="comment-meta">
                <div class="comment-author vcard">
                    <?php if (0 != $args['avatar_size']) : ?>
                        <div class="comment-avatar-wrapper">
                            <div class="comment-avatar-initials" style="background-color:<?php echo esc_attr($bg_color); ?>"><?php echo esc_html($initials); ?></div>
                            <?php
                            $avatar_url = get_avatar_url($comment, ['size' => 32, 'default' => '404']);
                            if ($avatar_url) :
                            ?>
                            <img src="<?php echo esc_url($avatar_url); ?>"
                                 width="32" height="32" alt=""
                                 onerror="this.style.display='none'">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="comment-author-info">
                        <?php printf('<b class="fn">%s</b>', get_comment_author_link()); ?>
                        <div class="comment-metadata">
                            <a href="<?php echo esc_url(get_comment_link($comment, $args)); ?>">
                                <time datetime="<?php comment_time('c'); ?>">
                                    <?php printf(
                                        _x('%1$s a las %2$s', '1: date, 2: time', 'atareao-theme'),
                                        get_comment_date('', $comment),
                                        get_comment_time()
                                    ); ?>
                                </time>
                            </a>
                            <?php edit_comment_link(__('Editar', 'atareao-theme'), '<span class="edit-link">', '</span>'); ?>
                        </div><!-- .comment-metadata -->
                    </div><!-- .comment-author-info -->
                </div><!-- .comment-author -->

                <?php if ('0' == $comment->comment_approved) : ?>
                    <p class="comment-awaiting-moderation"><?php _e('Tu comentario está pendiente de moderación.', 'atareao-theme'); ?></p>
                <?php endif; ?>
            </footer><!-- .comment-meta -->

            <div class="comment-content">
                <?php comment_text(); ?>
            </div><!-- .comment-content -->

            <?php
            comment_reply_link(array_merge($args, array(
                'add_below' => 'div-comment',
                'depth'     => $depth,
                'max_depth' => $args['max_depth'],
                'before'    => '<div class="reply">',
                'after'     => '</div>',
            )));
            ?>
        </article><!-- .comment-body -->
    <?php
}
