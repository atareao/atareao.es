<?php
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
function atareao_theme_setup()
{
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

    // Responsive images
    set_post_thumbnail_size(1200, 9999);
}
add_action('after_setup_theme', 'atareao_theme_setup');

/**
 * Configurar tamaños de imágenes responsive
 */
function atareao_theme_image_sizes()
{
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
function atareao_theme_add_lazy_loading($attr, $attachment, $size)
{
    $attr['loading'] = 'lazy';
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'atareao_theme_add_lazy_loading', 10, 3);

/**
 * Customizar los breakpoints de srcset
 */
function atareao_theme_custom_srcset_sizes($sizes, $size, $image_src, $image_meta, $attachment_id)
{
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
function atareao_theme_jpeg_quality($quality, $context)
{
    return 85; // Balance entre calidad y tamaño de archivo
}
add_filter('jpeg_quality', 'atareao_theme_jpeg_quality', 10, 2);
add_filter('wp_editor_set_quality', 'atareao_theme_jpeg_quality', 10, 2);

/**
 * Deshabilitar el threshold de imágenes grandes para preservar calidad
 */
function atareao_theme_big_image_threshold($threshold, $imagesize, $file, $attachment_id)
{
    // Permitir imágenes hasta 2560px
    return 2560;
}
add_filter('big_image_size_threshold', 'atareao_theme_big_image_threshold', 10, 4);

/**
 * Eliminar cabeceras innecesarias de WordPress
 */
function atareao_theme_clean_head()
{
    remove_action('wp_head', 'rsd_link');                     // RSD
    remove_action('wp_head', 'wlwmanifest_link');             // WLW
    remove_action('wp_head', 'wp_shortlink_wp_head');         // Shortlink
    remove_action('wp_head', 'wp_generator');                 // WP version
    add_filter('xmlrpc_enabled', '__return_false');           // XML-RPC
}
add_action('init', 'atareao_theme_clean_head');

/**
 * Registrar y cargar scripts y estilos
 */
function atareao_theme_scripts()
{
    $theme_version = wp_get_theme()->get('Version');

    // Estilo principal (render-blocking, normal)
    wp_enqueue_style('atareao-style', get_template_directory_uri() . '/style.css', array(), $theme_version);

    // Estilos para custom post types (solo en páginas relevantes)
    if (is_singular(array('podcast', 'tutorial', 'aplicacion', 'software', 'capitulo', 'chapter')) ||
        is_post_type_archive(array('podcast', 'tutorial', 'aplicacion', 'software'))) {
        wp_enqueue_style('atareao-cpt-style', get_template_directory_uri() . '/css/custom-post-types.css', array('atareao-style'), $theme_version);
    }

    // Dashicons solo en páginas que usan el reproductor de podcast
    if (is_singular('podcast') || has_block('atareao/podcast-player')) {
        wp_enqueue_style('dashicons');
    }

    // Script principal
    wp_enqueue_script('atareao-script', get_template_directory_uri() . '/js/main.js', array(), $theme_version, true);

    // Pass AJAX URL and nonce to the frontend for async view tracking
    wp_localize_script('atareao-script', 'atareao_track', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('atareao_track_view_nonce'),
    ));

    // Script de navegación
    wp_enqueue_script('atareao-navigation', get_template_directory_uri() . '/js/navigation.js', array(), $theme_version, true);

    // Script para comentarios si es necesario
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'atareao_theme_scripts');

// Enqueue and localize AJAX comment script solo en páginas con comentarios
add_action('wp_enqueue_scripts', function () {
    if (!is_singular() || !comments_open()) {
        return;
    }
    $theme_version = wp_get_theme()->get('Version');
    wp_enqueue_script('atareao-comment-ajax', get_template_directory_uri() . '/js/comment-ajax.js', array(), $theme_version, true);
    wp_localize_script('atareao-comment-ajax', 'atareao_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('atareao_comment_nonce'),
    ));
});

// AJAX handler for comment submissions to avoid a full page reload
add_action('wp_ajax_nopriv_atareao_submit_comment', 'atareao_ajax_submit_comment');
add_action('wp_ajax_atareao_submit_comment', 'atareao_ajax_submit_comment');

function atareao_ajax_submit_comment()
{
    $result = \Atareao\CommentSecurity::processAjaxComment();

    if ('error' === $result['status']) {
        wp_send_json_error(array(
            'message' => $result['message'],
            'new_a' => $result['new_a'],
            'new_b' => $result['new_b'],
            'new_time' => $result['new_time'],
        ));
    }

    $comment_obj = $result['comment'];
    $new_a = $result['new_a'];
    $new_b = $result['new_b'];
    $new_sig = $result['new_sig'];
    $new_time = $result['new_time'];

    ob_start();
    $args = array(
        'style' => 'ol',
        'avatar_size' => 50,
        'short_ping' => true,
        'has_children' => false,
        'max_depth' => intval(get_option('thread_comments_depth', 5)),
    );
    $prev_comment = isset($GLOBALS['comment']) ? $GLOBALS['comment'] : null;
    $GLOBALS['comment'] = $comment_obj;
    if (function_exists('atareao_comment_callback')) {
        atareao_comment_callback($comment_obj, $args, 1);
    } else {
        echo '<li id="comment-' . esc_attr($comment_obj->comment_ID) . '" class="comment">';
        echo '<div class="comment-body"><div class="comment-author"><b class="fn">' . esc_html(get_comment_author($comment_obj)) . '</b></div>';
        if ($comment_obj->comment_approved == '0') {
            echo '<p class="comment-awaiting-moderation">' . __('Tu comentario está pendiente de moderación.', 'atareao-theme') . '</p>';
        }
        echo '<div class="comment-content">' . get_comment_text($comment_obj) . '</div></div>';
        echo '</li>';
    }
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
        'new_sig' => $new_sig,
        'new_time' => $new_time,
    ));
}

/**
 * Registrar áreas de widgets
 */
function atareao_theme_widgets_init()
{
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
function atareao_theme_excerpt_length($length)
{
    return 25;
}
add_filter('excerpt_length', 'atareao_theme_excerpt_length');

function atareao_theme_excerpt_more($more)
{
    return '...';
}
add_filter('excerpt_more', 'atareao_theme_excerpt_more');

/**
 * Añadir clases al body
 */
function atareao_theme_body_classes($classes)
{
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
function atareao_theme_pagination()
{
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
 * Helper wrapper for Metaboxes::getViewsHtml for use in templates.
 */
function atareao_theme_post_views()
{
    if (class_exists('\Atareao\Metaboxes') && method_exists('\Atareao\Metaboxes', 'getViewsHtml')) {
        echo \Atareao\Metaboxes::getViewsHtml(get_the_ID());
    }
}

/**
 * Mostrar número de comentarios o enlace para comentar si es 0.
 */
function atareao_theme_comment_count()
{
    $count = get_comments_number();
    $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';

    if ($count > 0) {
        $plural = $count === 1 ? __('%d comentario', 'atareao-theme') : __('%d comentarios', 'atareao-theme');
        printf(
            '<span class="entry-comments"><a href="%s#comments" class="entry-comments-inner">%s<span class="entry-comments-count">%s</span></a></span>',
            esc_url(get_permalink()),
            $icon,
            sprintf($plural, $count)
        );
    } else {
        printf(
            '<span class="entry-comments"><a href="%s#respond" class="entry-comments-inner entry-comments-link">%s<span class="entry-comments-count">%s</span></a></span>',
            esc_url(get_permalink()),
            $icon,
            __('Comenta', 'atareao-theme')
        );
    }
}

/**
 * Función helper para mostrar la fecha de publicación
 */
function atareao_theme_posted_on()
{
    $time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
    
    $time_string = sprintf(
        $time_string,
        esc_attr(get_the_date(DATE_W3C)),
        esc_html(get_the_date())
    );
    
    printf('<span class="posted-on">%s</span>', $time_string);
}

/**
 * Función helper para mostrar el autor
 */
function atareao_theme_posted_by()
{
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
function atareao_share_links($post = null)
{
    if (is_null($post)) {
        global $post;
    }

    $post_id = is_object($post) ? $post->ID : intval($post);
    if (! $post_id) {
        return array( 'x' => '', 'mastodon' => '', 'telegram' => '' );
    }

    $permalink = rawurlencode(get_permalink($post_id));
    $title = rawurlencode(html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'));

    // X (Twitter) intent
    $x_url = "https://twitter.com/intent/tweet?text={$title}&url={$permalink}";

    // Telegram share
    $tg_url = "https://t.me/share/url?url={$permalink}&text={$title}";

    // Mastodon: if user set a mastodon URL option use its host for share endpoint
    $mastodon_opt = esc_url_raw(get_option('atareao_social_mastodon'));
    $mastodon_share_base = 'https://mastodon.social/share';
    if (! empty($mastodon_opt)) {
        $host = parse_url($mastodon_opt, PHP_URL_HOST);
        if ($host) {
            $mastodon_share_base = 'https://' . $host . '/share';
        }
    }
    $md_url = $mastodon_share_base . '?text=' . $title . '%20' . $permalink;

    $share = array(
        'x' => array(
            'url'  => esc_url_raw($x_url),
            'icon' => '<svg class="svg-icon"><use href="#x"/></svg>',
        ),
        'mastodon' => array(
            'url'  => esc_url_raw($md_url),
            'icon' => '<svg class="svg-icon"><use href="#mastodon"/></svg>',
        ),
        'telegram' => array(
            'url'  => esc_url_raw($tg_url),
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
function atareao_theme_tutorial_posts_per_page($query)
{
    if (! is_admin() && $query->is_main_query() && $query->is_post_type_archive('tutorial')) {
        $query->set('posts_per_page', 8);
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
    }
}
add_action('pre_get_posts', 'atareao_theme_tutorial_posts_per_page');

/**
 * Mostrar en la página del blog entradas de todos los post types públicos
 */
function atareao_theme_blog_all_post_types($query)
{
    if (! is_admin() && $query->is_main_query() && $query->is_home()) {
        $post_types = get_post_types(array( 'public' => true ), 'names');
        // Keep attachments out of the blog listing
        if (isset($post_types['attachment'])) {
            unset($post_types['attachment']);
        }

        $query->set('post_type', $post_types);
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
    }
}
add_action('pre_get_posts', 'atareao_theme_blog_all_post_types');

/**
 * Obtener los tipos de contenido que deben aparecer en el feed principal.
 * Incluye posts y todos los CPT públicos, excluyendo páginas y adjuntos.
 *
 * @return array<string>
 */
function atareao_theme_get_main_feed_post_types()
{
    $custom_post_types = get_post_types(
        array(
            'public'   => true,
            '_builtin' => false,
        ),
        'names'
    );

    return array_merge(array( 'post' ), array_values($custom_post_types));
}

/**
 * Mostrar en el feed principal entradas y custom post types ordenados por fecha descendente.
 */
function atareao_theme_main_feed_all_post_types($query)
{
    if (is_admin() || ! $query->is_main_query() || ! $query->is_feed() || $query->is_comment_feed()) {
        return;
    }

    // Do not override feeds tied to a specific post type archive.
    if ($query->is_post_type_archive()) {
        return;
    }

    $query->set('post_type', atareao_theme_get_main_feed_post_types());
    $query->set('post_status', 'publish');
    $query->set('orderby', 'date');
    $query->set('order', 'DESC');
}
add_action('pre_get_posts', 'atareao_theme_main_feed_all_post_types');

/**
 * Obtener descripción SEO de un post desde cualquier plugin SEO (con cache estática).
 *
 * @param int|WP_Post $post Post ID or object.
 * @return string
 */
function atareao_get_seo_description($post)
{
    $post_id = is_object($post) ? $post->ID : intval($post);
    static $cache = array();

    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }

    $meta = get_metadata('post', $post_id, '', true);

    $keys = array('_genesis_description', '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description');
    foreach ($keys as $key) {
        if (!empty($meta[$key][0])) {
            $cache[$post_id] = $meta[$key][0];
            return $cache[$post_id];
        }
    }

    $post_obj = get_post($post_id);
    $cache[$post_id] = $post_obj ? $post_obj->post_excerpt : '';
    return $cache[$post_id];
}

/**
 * Envuelve iframes de YouTube/Vimeo en un contenedor responsive 16:9
 */
function atareao_theme_responsive_embeds($content)
{
    if (! is_singular()) {
        return $content;
    }
    $pattern     = '/<iframe(?![^>]*class="[^"]*video-responsive)[^>]*(youtube\.com|youtu\.be|vimeo\.com)[^>]*>.*?<\/iframe>/is';
    $replacement = '<div class="video-responsive">$0</div>';
    return preg_replace($pattern, $replacement, $content);
}
add_filter('the_content', 'atareao_theme_responsive_embeds');

/**
 * Callback personalizado para mostrar comentarios individuales (disponible para AJAX)
 */
function atareao_comment_callback($comment, $args, $depth)
{
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
