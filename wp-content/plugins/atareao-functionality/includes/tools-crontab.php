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
