<?php
/**
 * Plugin Name: Atareao Functionality
 * Plugin URI: https://atareao.es
 * Description: Plugin con todas las funcionalidades personalizadas para Atareao (Custom Post Types, Taxonomías y más)
 * Version: 1.2.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Atareao
 * Author URI: https://atareao.es
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: atareao-functionality
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ATAREAO_PLUGIN_VERSION', '1.2.5');
define('ATAREAO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ATAREAO_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ATAREAO_PLUGIN_DIR . 'includes/class-post-types.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-taxonomies.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-metaboxes.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-podcast-block.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-matrix-config.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-comment-security.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-theme-options.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-contact-form.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-mcp.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/tools-crontab.php';

function atareao_functionality_init()
{
    \Atareao\PostTypes::init();
    \Atareao\Taxonomies::init();
    \Atareao\Metaboxes::init();
    \Atareao\PodcastBlock::init();
    \Atareao\MatrixConfig::init();
    \Atareao\CommentSecurity::init();
    \Atareao\ThemeOptions::init();
    \Atareao\ContactForm::init();
    \Atareao\MCP::init();
}
add_action('init', 'atareao_functionality_init');

function atareao_functionality_activate()
{
    \Atareao\PostTypes::init();
    \Atareao\Taxonomies::init();

    $caps = array(
        'publish_podcasts',
        'edit_podcasts',
        'edit_others_podcasts',
        'delete_podcasts',
        'delete_others_podcasts',
        'read_private_podcasts',
        'edit_podcast',
        'delete_podcast',
        'read_podcast',
    );
    $roles_to_update = array('editor', 'administrator');
    foreach ($roles_to_update as $r) {
        $role = get_role($r);
        if ($role) {
            foreach ($caps as $cap) {
                $role->add_cap($cap);
            }
        }
    }

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'atareao_functionality_activate');

function atareao_functionality_deactivate()
{
    $caps = array(
        'publish_podcasts',
        'edit_podcasts',
        'edit_others_podcasts',
        'delete_podcasts',
        'delete_others_podcasts',
        'read_private_podcasts',
        'edit_podcast',
        'delete_podcast',
        'read_podcast',
    );
    $role = get_role('editor');
    if ($role) {
        foreach ($caps as $cap) {
            $role->remove_cap($cap);
        }
    }

    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'atareao_functionality_deactivate');

function atareao_functionality_load_textdomain()
{
    load_plugin_textdomain('atareao-functionality', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'atareao_functionality_load_textdomain');
