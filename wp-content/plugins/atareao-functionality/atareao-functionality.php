<?php
/**
 * Plugin Name: Atareao Functionality
 * Plugin URI: https://atareao.es
 * Description: Plugin con todas las funcionalidades personalizadas para Atareao (Custom Post Types, Taxonomías y más)
 * Version: 1.14.0
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

define('ATAREAO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ATAREAO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ATAREAO_PLUGIN_VERSION', '1.14.0');

require_once ATAREAO_PLUGIN_DIR . 'includes/class-post-types.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-taxonomies.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-metaboxes.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-podcast-block.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-opengist-block.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-crontab-block.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-timestamp-block.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-matrix-config.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-comment-security.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-theme-options.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-contact-form.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-mcp.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-webmcp.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-seo.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-cache-purge.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-pocketid-login.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-analytics.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-mastodon-replies.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-settings.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/tools-crontab.php';

function atareao_functionality_init()
{
    \Atareao\PostTypes::init();
    \Atareao\Taxonomies::init();
    \Atareao\Metaboxes::init();
    \Atareao\PodcastBlock::init();
    \Atareao\OpengistBlock::init();
    \Atareao\CrontabBlock::init();
    \Atareao\TimestampBlock::init();
    \Atareao\MatrixConfig::init();
    \Atareao\ThemeOptions::init();
    \Atareao\ContactForm::init();
    \Atareao\MCP::init();
    \Atareao\WebMCP::init();
    \Atareao\SEO::init();
    \Atareao\CachePurge::init();
    \Atareao\PocketIDLogin::init();
    \Atareao\Analytics::init();
    \Atareao\MastodonReplies::init();
    \Atareao\Settings::init();
    // Only initialize comment security on the frontend public-facing site
    if (!is_admin()) {
        \Atareao\CommentSecurity::init();
    }
}
add_action('init', 'atareao_functionality_init');

function atareao_functionality_disable_rest_comment_endpoint($endpoints)
{
    if (isset($endpoints['/wp/v2/comments'])) {
        unset($endpoints['/wp/v2/comments']);
    }
    return $endpoints;
}
add_filter('rest_endpoints', 'atareao_functionality_disable_rest_comment_endpoint');

/**
 * Resuelve la ruta REST efectiva de la petición actual.
 *
 * Usa la misma precedencia que WordPress: `rest_route` del cuerpo POST, luego
 * `rest_route` de la query, y por último la forma reescrita `/wp-json/<ruta>`.
 * El *query string* nunca forma parte de la ruta resultante y la barra final se
 * normaliza.
 *
 * @return string Ruta REST normalizada, o cadena vacía si no se puede resolver.
 */
function atareao_functionality_rest_route_from_request()
{
    $raw = '';
    if (isset($_POST['rest_route']) && is_string($_POST['rest_route']) && $_POST['rest_route'] !== '') {
        $raw = $_POST['rest_route'];
    } elseif (isset($_GET['rest_route']) && is_string($_GET['rest_route']) && $_GET['rest_route'] !== '') {
        $raw = $_GET['rest_route'];
    }
    if ($raw !== '') {
        return rtrim('/' . ltrim($raw, '/'), '/');
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($request_uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '';
    }
    $prefix = '/' . rest_get_url_prefix() . '/';
    $position = strpos($path, $prefix);
    if ($position === false) {
        return '';
    }
    return rtrim('/' . substr($path, $position + strlen($prefix)), '/');
}

/**
 * Resuelve el método HTTP efectivo de la petición actual.
 *
 * Sigue la semántica de WordPress: el *override* por `$_GET['_method']` tiene
 * precedencia, después la cabecera `X-HTTP-Method-Override` y, en su defecto,
 * `REQUEST_METHOD`. El resultado se normaliza a mayúsculas.
 *
 * @return string Método HTTP efectivo en mayúsculas, o cadena vacía.
 */
function atareao_functionality_rest_effective_method()
{
    if (isset($_GET['_method']) && is_string($_GET['_method']) && $_GET['_method'] !== '') {
        return strtoupper($_GET['_method']);
    }
    $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '';
    if (is_string($override) && $override !== '') {
        return strtoupper($override);
    }
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
}

function atareao_functionality_rest_auth_errors($result)
{
    if (!empty($result)) {
        return $result;
    }
    if (!is_user_logged_in()) {
        $method = atareao_functionality_rest_effective_method();
        $readable = array('GET', 'HEAD', 'OPTIONS');
        if (in_array($method, $readable, true)) {
            return $result;
        }
        $public_routes = array(
            '/atareao/v1/mcp',
        );
        $route = atareao_functionality_rest_route_from_request();
        foreach ($public_routes as $public_route) {
            if ($route !== '' && $route === rtrim($public_route, '/')) {
                return $result;
            }
        }
        return new WP_Error(
            'rest_not_logged_in',
            __('You must be logged in to access the REST API.', 'atareao-functionality'),
            array('status' => 401)
        );
    }
    return $result;
}
add_filter('rest_authentication_errors', 'atareao_functionality_rest_auth_errors');

function atareao_functionality_disable_xmlrpc_comment($methods)
{
    unset($methods['wp.newComment']);
    return $methods;
}
add_filter('xmlrpc_methods', 'atareao_functionality_disable_xmlrpc_comment');

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
