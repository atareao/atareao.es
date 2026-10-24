<?php
/**
 * Plugin Name: Atareao Functionality
 * Plugin URI: https://atareao.es
 * Description: Plugin con todas las funcionalidades personalizadas para Atareao (Custom Post Types, Taxonomías y más)
 * Version: 1.17.0
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
define('ATAREAO_PLUGIN_VERSION', '1.17.0');

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
require_once ATAREAO_PLUGIN_DIR . 'includes/class-valet-config.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-post-type-switcher.php';
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
    \Atareao\ValetConfig::init();
    \Atareao\PostTypeSwitcher::init();
    \Atareao\Settings::init();
    // Only initialize comment security on the frontend public-facing site
    if (!is_admin()) {
        \Atareao\CommentSecurity::init();
    }
}
add_action('init', 'atareao_functionality_init');

/**
 * Resuelve la superficie REST de la colección de comentarios por identidad.
 *
 * Históricamente esta colección se eliminaba **incondicionalmente**, lo que
 * dejaba muerta la vía de respuesta del asistente: el publicador de Valet hace
 * `POST /wp-json/wp/v2/comments` y recibía `404 rest_no_route`, de modo que el
 * contrato de respuesta (*valet-replies*) era insatisfacible. El cierre pasa a
 * decidirse **por identidad del usuario ya autenticado**.
 *
 * **Forma del árbol que llega al filtro.** `rest_endpoints` **no** recibe
 * `ruta => método => manejador`. Recibe, por ruta, la lista de **grupos de
 * manejador con clave numérica** —el método vive **dentro** de cada grupo, en
 * `methods`— más las **opciones de ruta** en claves no numéricas (`schema`,
 * `namespace`, `args`). Es la forma que guarda `register_route()`
 * (`class-wp-rest-server.php:928-932`) y sobre la que `get_routes()` aplica
 * este filtro (`:974`); la normalización por método ocurre **después de** él
 * (`:998` mueve las opciones y `:1009-1010` acepta `methods` como cadena con
 * comas o como array). Por eso los grupos se recorren por **clave numérica** y
 * se lee `methods`; nunca se indexa por nombre de método.
 *
 * Por qué es posible decidir aquí: `rest_endpoints` se aplica desde
 * `WP_REST_Server::get_routes()`, y `serve_request()` ejecuta
 * `check_authentication()` **antes** de `dispatch()`; dentro del filtro la
 * identidad ya está resuelta, así que la decisión no depende de nada que
 * aporte el cliente (ni parámetro, ni cabecera, ni cuerpo).
 *
 * Reglas (falla a **cerrado**):
 *
 * - **Sin usuario de servicio** configurado —opción ausente, vacía o `0`— la
 *   colección se elimina **entera**: sin identidad de servicio no hay
 *   consumidor legítimo de esa vía.
 * - **Usuario de servicio**: se conservan **verbatim** las claves no numéricas
 *   (opciones de ruta) y, de los grupos numéricos, se conserva **solo** un
 *   grupo cuyo `methods` contiene `POST` y **no** contiene `GET` (puede
 *   publicar, no puede enumerar). Si no sobrevive ningún grupo numérico, la
 *   ruta se elimina **entera**. El cierre a la **enumeración** es una garantía
 *   **real del servidor**; en cambio, que el asistente retire «solo lo que
 *   llegó por notificación» **no** lo es: es **disciplina del consumidor**
 *   (los identificadores solo llegan en el `metadata` de la notificación) más
 *   la **aprobación explícita** por herramienta, porque la ruta de **elemento
 *   único** —que este filtro conserva a propósito— solo exige
 *   `moderate_comments`.
 * - **Cualquier otro usuario** —autenticado o anónimo— la colección sigue
 *   eliminada **entera**, exactamente como antes.
 *
 * La ruta de **elemento único** (`/wp/v2/comments/<id>`) no se toca aquí: es la
 * que sostiene la lectura previa y el borrado reversible de *valet-moderation*.
 * La identidad de servicio se lee del propio módulo
 * (`ValetConfig::isServiceRequest()`), con una única fuente de verdad para la
 * superficie, el sellado y la cota de escritura.
 *
 * **Riesgo residual (aceptado, no mitigado en este change).** Puesto que la
 * ruta de elemento único se conserva y su permiso es `moderate_comments`, la
 * credencial de servicio —aunque no pueda enumerar la colección— **sí** puede
 * recorrer identificadores y mandar a la papelera **cualquier** comentario, no
 * solo los notificados, y sin cota. Es una condición **preexistente** (el
 * elemento nunca se filtró) y el control *server-side* queda como trabajo
 * siguiente, fuera de este change. El borrado es reversible **30 días**.
 *
 * @param array $endpoints Árbol de rutas REST: `ruta => [grupos de manejador
 *                          con clave numérica + opciones de ruta]`.
 * @return array Árbol de rutas con la colección de comentarios resuelta.
 */
function atareao_functionality_disable_rest_comment_endpoint($endpoints)
{
    if (!isset($endpoints['/wp/v2/comments'])) {
        return $endpoints;
    }

    if (!\Atareao\ValetConfig::isServiceRequest()) {
        unset($endpoints['/wp/v2/comments']);

        return $endpoints;
    }

    $route = $endpoints['/wp/v2/comments'];
    if (!is_array($route)) {
        unset($endpoints['/wp/v2/comments']);

        return $endpoints;
    }

    $kept = array();
    $kept_groups = 0;

    foreach ($route as $key => $group) {
        if (!is_numeric($key)) {
            // Route option (schema, namespace, args…): kept verbatim.
            $kept[$key] = $group;
            continue;
        }

        if (!is_array($group) || !array_key_exists('methods', $group)) {
            continue;
        }

        $methods = $group['methods'];
        if (is_string($methods)) {
            $methods = explode(',', $methods);
        } elseif (!is_array($methods)) {
            $methods = array();
        }

        $methods = array_map(
            static function ($method) {
                return strtoupper(trim((string) $method));
            },
            $methods
        );

        if (in_array('POST', $methods, true) && !in_array('GET', $methods, true)) {
            $kept[] = $group;
            $kept_groups++;
        }
    }

    if ($kept_groups === 0) {
        // No dispatchable handler would survive: remove the route whole.
        unset($endpoints['/wp/v2/comments']);

        return $endpoints;
    }

    $endpoints['/wp/v2/comments'] = $kept;

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
