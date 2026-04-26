<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Full tools catalog.
 *
 * @return array<string, array<string, string>>
 */
function atareao_tools_catalog() {
    return array(
        'crontab' => array(
            'label' => 'Crontab Helper',
            'description' => 'Interpreta expresiones cron de 5 campos y calcula proximas ejecuciones por zona horaria.',
            'template' => 'tools-crontab.php',
        ),
        'subnetting' => array(
            'label' => 'IPv4 Subnet Calculator',
            'description' => 'Calcula network, broadcast, mascara, wildcard y rango de hosts para una IPv4 con prefijo CIDR.',
            'template' => 'tools-subnetting.php',
        ),
        'regex' => array(
            'label' => 'Regex Tester',
            'description' => 'Prueba patrones regex, flags y grupos capturados con resaltado de coincidencias en vivo.',
            'template' => 'tools-regex.php',
        ),
        'json' => array(
            'label' => 'JSON Formatter y Validator',
            'description' => 'Valida sintaxis JSON, formatea con indentacion configurable y minifica payloads.',
            'template' => 'tools-json.php',
        ),
    );
}

/**
 * Tool routes handled by the plugin.
 *
 * @return array<string, string>
 */
function atareao_tools_route_map() {
    $catalog = atareao_tools_catalog();
    $routes = array();

    foreach ($catalog as $slug => $item) {
        if (!empty($item['template'])) {
            $routes[$slug] = $item['template'];
        }
    }

    return $routes;
}

/**
 * Featured tools for quick access button bar.
 *
 * @return array<string, string>
 */
function atareao_tools_featured_map() {
    $catalog = atareao_tools_catalog();
    $featured = array('tools' => 'Todas');

    foreach ($catalog as $slug => $item) {
        $featured[$slug] = $item['label'];
    }

    return $featured;
}

/**
 * Render quick access tools button bar.
 *
 * @param string $current_slug Active tool slug.
 * @return void
 */
function atareao_tools_render_featured_nav($current_slug = '') {
    $tools = atareao_tools_featured_map();

    echo '<nav class="atareao-tools-nav" aria-label="Herramientas destacadas">';
    echo '<span class="atareao-tools-nav-title">Herramientas:</span>';

    foreach ($tools as $slug => $label) {
        $url = ($slug === 'tools') ? home_url('/tools/') : home_url('/tools/' . $slug . '/');
        $active_class = ($slug === $current_slug) ? ' is-active' : '';
        echo '<a class="atareao-tools-nav-item' . esc_attr($active_class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }

    echo '</nav>';
}

/**
 * Render tools breadcrumb with landing link + current tool selector.
 *
 * @param string $current_slug Active tool slug.
 * @return void
 */
function atareao_tools_render_breadcrumb($current_slug = '') {
    $catalog = atareao_tools_catalog();

    echo '<nav class="atareao-tools-breadcrumb" aria-label="Breadcrumb de herramientas">';
    echo '<a class="atareao-tools-breadcrumb-link" href="' . esc_url(home_url('/tools/')) . '">Tools</a>';
    echo '<span class="atareao-tools-breadcrumb-sep" aria-hidden="true">/</span>';
    echo '<label class="screen-reader-text" for="atareao_tools_breadcrumb_select">Seleccionar herramienta</label>';
    echo '<select id="atareao_tools_breadcrumb_select" class="atareao-tools-breadcrumb-select" onchange="if (this.value) { window.location.href = this.value; }">';

    foreach ($catalog as $slug => $item) {
        $selected = selected($current_slug, $slug, false);
        $url = home_url('/tools/' . $slug . '/');
        echo '<option value="' . esc_url($url) . '" ' . $selected . '>' . esc_html($item['label']) . '</option>';
    }

    echo '</select>';
    echo '</nav>';
}

/**
 * Register /tools/* utility routes.
 */
function atareao_tools_register_rewrites() {
    add_rewrite_rule('^tools/?$', 'index.php?atareao_tool=index', 'top');

    $routes = atareao_tools_route_map();
    foreach ($routes as $slug => $_template) {
        add_rewrite_rule('^tools/' . $slug . '/?$', 'index.php?atareao_tool=' . $slug, 'top');
    }
}
add_action('init', 'atareao_tools_register_rewrites');

/**
 * Add custom query var used by tool routes.
 *
 * @param array $vars Existing vars.
 * @return array
 */
function atareao_tools_add_query_vars($vars) {
    $vars[] = 'atareao_tool';
    return $vars;
}
add_filter('query_vars', 'atareao_tools_add_query_vars');

/**
 * Resolve tool template file path by slug.
 *
 * @param string $tool_slug Tool slug.
 * @return string
 */
function atareao_tools_resolve_template_path($tool_slug) {
    if ($tool_slug === 'index') {
        $index_template = ATAREAO_PLUGIN_DIR . 'templates/tools-index.php';
        if (file_exists($index_template)) {
            return $index_template;
        }
        return '';
    }

    $routes = atareao_tools_route_map();
    if (!isset($routes[$tool_slug])) {
        return '';
    }

    $template = ATAREAO_PLUGIN_DIR . 'templates/' . $routes[$tool_slug];
    if (!file_exists($template)) {
        return '';
    }

    return $template;
}

/**
 * Load dedicated template for /tools/* from the plugin.
 *
 * @param string $template Resolved template.
 * @return string
 */
function atareao_tools_template_include($template) {
    $tool_slug = get_query_var('atareao_tool');
    if (empty($tool_slug)) {
        return $template;
    }

    $custom_template = atareao_tools_resolve_template_path($tool_slug);
    if (!empty($custom_template)) {
        return $custom_template;
    }

    return $template;
}
add_filter('template_include', 'atareao_tools_template_include');

/**
 * Fallback router for environments where rewrite rules are not flushed yet.
 */
function atareao_tools_template_redirect_fallback() {
    if (is_admin()) {
        return;
    }

    $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($request_path === 'tools') {
        $index_template = atareao_tools_resolve_template_path('index');
        if (!empty($index_template)) {
            status_header(200);
            nocache_headers();
            include $index_template;
            exit;
        }
        return;
    }

    if (strpos($request_path, 'tools/') !== 0) {
        return;
    }

    $parts = explode('/', $request_path);
    if (count($parts) < 2) {
        return;
    }

    $tool_slug = sanitize_key($parts[1]);
    $custom_template = atareao_tools_resolve_template_path($tool_slug);

    if (empty($custom_template)) {
        return;
    }

    status_header(200);
    nocache_headers();
    include $custom_template;
    exit;
}
add_action('template_redirect', 'atareao_tools_template_redirect_fallback', 0);
