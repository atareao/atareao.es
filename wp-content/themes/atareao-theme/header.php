<?php
// Read theme preference from cookie; fall back to 'dark'
$atareao_theme = 'dark';
if ( isset( $_COOKIE['atareao-theme'] ) && in_array( $_COOKIE['atareao-theme'], array( 'light', 'dark' ), true ) ) {
    $atareao_theme = $_COOKIE['atareao-theme'];
}

$atareao_request_path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
$atareao_is_tools = ( 'tools' === $atareao_request_path || 0 === strpos( $atareao_request_path, 'tools/' ) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="<?php echo esc_attr( $atareao_theme ); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php wp_head(); ?>
    <!-- Fallback: sync data-theme from cookie/localStorage before first paint (covers JS-only / CDN-cached pages) -->
    <script>
        (function(){
            var t = (document.cookie.match(/(?:^|;\s*)atareao-theme=([^;]+)/) || [])[1]
                 || localStorage.getItem('atareao-theme')
                 || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="site-container">
    <header class="site-header">
        <div class="site-header-inner">

            <!-- ── LEFT: logo + site name ── -->
            <div class="site-branding">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="site-branding-link" rel="home">
                    <?php if (has_custom_logo()) : ?>
                        <div class="site-logo"><?php the_custom_logo(); ?></div>
                    <?php else : ?>
                        <div class="site-logo site-logo-fallback">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" aria-hidden="true" focusable="false">
                                <circle cx="20" cy="20" r="20" fill="var(--color-primary)"/>
                                <text x="20" y="27" text-anchor="middle" fill="#fff" font-size="18" font-weight="700" font-family="sans-serif">a</text>
                            </svg>
                        </div>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="site-branding-link" rel="home">
                    <div class="site-name">
                        <span class="site-name-main">atareao</span>
                        <span class="site-name-sub">con Linux</span>
                    </div>
                </a>
            </div>

            <!-- ── RIGHT: navigation ── -->
            <nav class="main-navigation" role="navigation" aria-label="<?php esc_attr_e('Menú principal', 'atareao-theme'); ?>">
                <ul class="main-nav-list">

                    <!-- Search -->
                    <li class="nav-item nav-item--search">
                        <button class="nav-btn nav-search-toggle" aria-label="<?php esc_attr_e('Buscar', 'atareao-theme'); ?>" aria-expanded="false">
                            <span class="nav-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                            </span>
                        </button>
                        <div class="nav-search-box" role="search" aria-hidden="true">
                            <form method="get" action="<?php echo esc_url(home_url('/')); ?>">
                                <input type="search" name="s" class="nav-search-input"
                                       placeholder="<?php esc_attr_e('Buscar…', 'atareao-theme'); ?>"
                                       value="<?php echo esc_attr(get_search_query()); ?>"
                                       aria-label="<?php esc_attr_e('Buscar', 'atareao-theme'); ?>">
                                <button type="submit" class="nav-search-submit" aria-label="<?php esc_attr_e('Enviar búsqueda', 'atareao-theme'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </li>

                    <!-- Podcast -->
                    <li class="nav-item">
                        <a href="<?php echo esc_url(home_url('/podcast')); ?>" class="nav-link <?php echo (is_post_type_archive('podcast') || get_query_var('post_type') === 'podcast') ? 'is-active' : ''; ?>">
                            <span class="nav-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3z"/>
                                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>
                                </svg>
                            </span>
                            <span class="nav-label"><?php esc_html_e('Podcast', 'atareao-theme'); ?></span>
                        </a>
                    </li>

                    <!-- Blog -->
                    <li class="nav-item">
                        <a href="<?php echo esc_url(home_url('/blog')); ?>" class="nav-link <?php echo (is_home() || is_category() || (is_singular('post') && !is_post_type_archive())) ? 'is-active' : ''; ?>">
                            <span class="nav-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                                </svg>
                            </span>
                            <span class="nav-label"><?php esc_html_e('Blog', 'atareao-theme'); ?></span>
                        </a>
                    </li>

                    <!-- Contactar -->
                    <li class="nav-item">
                        <a href="<?php echo esc_url(home_url('/contactar')); ?>" class="nav-link <?php echo is_page('contactar') ? 'is-active' : ''; ?>">
                            <span class="nav-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </span>
                            <span class="nav-label"><?php esc_html_e('Contactar', 'atareao-theme'); ?></span>
                        </a>
                    </li>

                    <!-- Quién soy -->
                    <li class="nav-item">
                        <a href="<?php echo esc_url(home_url('/quien-soy')); ?>" class="nav-link <?php echo is_page('quien-soy') ? 'is-active' : ''; ?>">
                            <span class="nav-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </span>
                            <span class="nav-label"><?php esc_html_e('Quién soy', 'atareao-theme'); ?></span>
                        </a>
                    </li>

                    <!-- Tools -->
                    <li class="nav-item">
                        <a href="<?php echo esc_url(home_url('/tools/')); ?>" class="nav-link <?php echo $atareao_is_tools ? 'is-active' : ''; ?>" aria-label="tools" title="tools">
                            <span class="nav-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14.7 6.3a4 4 0 1 0 3 3l-6.4 6.4a2 2 0 0 1-2.8 0l-.2-.2a2 2 0 0 1 0-2.8z"/>
                                    <path d="M6 18l-1.5 1.5"/>
                                </svg>
                            </span>
                        </a>
                    </li>

                    <!-- Theme toggle -->
                    <li class="nav-item">
                        <button class="nav-btn nav-theme-toggle" aria-label="<?php esc_attr_e('Cambiar tema', 'atareao-theme'); ?>">
                            <!-- Moon: shown in light mode -->
                            <span class="nav-icon theme-icon-dark" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                                </svg>
                            </span>
                            <!-- Sun: shown in dark mode -->
                            <span class="nav-icon theme-icon-light" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="5"/>
                                    <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                                    <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                                </svg>
                            </span>
                        </button>
                    </li>

                </ul>
            </nav>

        </div>
    </header>

    <main class="site-main" id="main">
