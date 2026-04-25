<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the /tools/crontab utility route.
 */
function atareao_tools_register_crontab_rewrite() {
    add_rewrite_rule('^tools/crontab/?$', 'index.php?atareao_tool=crontab', 'top');
}
add_action('init', 'atareao_tools_register_crontab_rewrite');

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
 * Load dedicated template for /tools/crontab from the plugin.
 *
 * @param string $template Resolved template.
 * @return string
 */
function atareao_tools_template_include($template) {
    if (get_query_var('atareao_tool') !== 'crontab') {
        return $template;
    }

    $custom_template = ATAREAO_PLUGIN_DIR . 'templates/tools-crontab.php';
    if (file_exists($custom_template)) {
        return $custom_template;
    }

    return $template;
}
add_filter('template_include', 'atareao_tools_template_include');

/**
 * Fallback router for environments where rewrite rules are not flushed yet.
 */
function atareao_tools_crontab_template_redirect() {
    if (is_admin()) {
        return;
    }

    $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($request_path !== 'tools/crontab') {
        return;
    }

    $custom_template = ATAREAO_PLUGIN_DIR . 'templates/tools-crontab.php';
    if (!file_exists($custom_template)) {
        return;
    }

    status_header(200);
    nocache_headers();
    include $custom_template;
    exit;
}
add_action('template_redirect', 'atareao_tools_crontab_template_redirect', 0);
