<?php
/**
 * WordPress stubs and harness helpers for the procedural Valet test suite.
 *
 * The production code under test is written against the WordPress API. This
 * file declares faithful, in-memory replacements so the *real* plugin code can
 * be exercised without a WordPress installation, plus the small helpers the
 * test script (tests/test-valet.php) uses to run subprocess scenarios and
 * assert results.
 *
 * This file only *declares* symbols: every piece of mutable state lives in
 * globals initialised by tests/test-valet.php (see harness_reset_state()).
 *
 * phpcs note: WordPress' public API uses global functions and the global
 * `WP_Error` class, whose names cannot be changed without losing fidelity, so
 * a handful of PSR-1 sniffs are disabled here on purpose. This is the only
 * file in tests/ that needs them.
 *
 * @package Atareao_Functionality\Tests
 */

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

// The plugin files abort unless WordPress' ABSPATH is defined. Declaring it
// here (a "symbol", not a side effect) keeps this file side-effect free.
define('ABSPATH', dirname(__DIR__) . '/');

/**
 * Options store (get_option/update_option).
 *
 * @param string $key     Option name.
 * @param mixed  $default Value returned when the option is missing.
 * @return mixed
 */
function get_option($key, $default = false)
{
    $options = isset($GLOBALS['mock_options']) ? $GLOBALS['mock_options'] : array();

    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Persist an option in the in-memory store.
 *
 * @param string $key   Option name.
 * @param mixed  $value Option value.
 * @return bool Always true.
 */
function update_option($key, $value)
{
    $GLOBALS['mock_options'][$key] = $value;

    return true;
}

/**
 * Faithful-enough replacement for WordPress' esc_url_raw().
 *
 * WordPress runs the URL through `wp_kses_bad_protocol()` (protocol
 * whitelist) after encoding spaces and stripping characters that are not
 * valid in a URL. This stub reproduces both steps: it encodes literal spaces
 * as `%20`, removes disallowed characters and rejects any non-whitelisted
 * scheme (e.g. `javascript:`), which the previous `filter_var()` based stub
 * silently allowed through. It is still an approximation, not `esc_url()`.
 *
 * @param mixed $url Raw URL.
 * @return string Sanitised URL, or '' when it must be rejected.
 */
function esc_url_raw($url)
{
    if (!is_string($url) || '' === $url) {
        return '';
    }

    $url = str_replace(' ', '%20', ltrim($url));
    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url);

    if (!is_string($url) || '' === $url) {
        return '';
    }

    // Reject dangerous schemes, as wp_kses_bad_protocol() does. The list must
    // mirror WordPress' wp_allowed_protocols() exactly; a narrower list gives
    // false confidence (see the esc_url_raw fidelity test).
    $allowed = array(
        'http',
        'https',
        'ftp',
        'ftps',
        'mailto',
        'tel',
        'gopher',
        'news',
        'irc',
        'irc6',
        'ircs',
        'nntp',
        'feed',
        'telnet',
        'mms',
        'rtsp',
        'sms',
        'svn',
        'fax',
        'xmpp',
        'webcal',
        'urn',
    );
    if (preg_match('|^([a-z0-9+.-]+):|i', $url, $matches)) {
        if (!in_array(strtolower($matches[1]), $allowed, true)) {
            return '';
        }
    }

    return $url;
}

/**
 * Sanitise a single-line text field.
 *
 * @param mixed $text Raw text.
 * @return string
 */
function sanitize_text_field($text)
{
    $text = is_string($text) ? strip_tags($text) : '';
    $text = preg_replace('/[\r\n\t ]+/', ' ', (string) $text);

    return trim((string) $text);
}

/**
 * Sanitise a multi-line text field (keeps line breaks).
 *
 * @param mixed $text Raw text.
 * @return string
 */
function sanitize_textarea_field($text)
{
    $text = is_string($text) ? strip_tags($text) : '';

    return trim((string) $text);
}

/**
 * Sanitise a key down to lowercase alphanumerics, dashes and underscores.
 *
 * @param mixed $key Raw key.
 * @return string
 */
function sanitize_key($key)
{
    if (!is_string($key)) {
        return '';
    }

    return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $key));
}

/**
 * Return a translated, HTML-escaped string.
 *
 * @param string $text   Text.
 * @param string $domain Text domain (unused).
 * @return string
 */
function esc_html__($text, $domain = 'default')
{
    return $text;
}

/**
 * Echo a translated, HTML-escaped string.
 *
 * @param string $text   Text.
 * @param string $domain Text domain (unused).
 * @return void
 */
function esc_html_e($text, $domain = 'default')
{
    echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Echo a translated, attribute-escaped string.
 *
 * @param string $text   Text.
 * @param string $domain Text domain (unused).
 * @return void
 */
function esc_attr_e($text, $domain = 'default')
{
    echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape text for HTML output.
 *
 * @param mixed $text Raw text.
 * @return string
 */
function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape text for an HTML attribute.
 *
 * @param mixed $text Raw text.
 * @return string
 */
function esc_attr($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape a URL for HTML output.
 *
 * @param mixed $url Raw URL.
 * @return string
 */
function esc_url($url)
{
    return esc_url_raw((string) $url);
}

/**
 * Translate a string (identity in tests).
 *
 * @param string $text   Text.
 * @param string $domain Text domain (unused).
 * @return string
 */
function __($text, $domain = 'default')
{
    return $text;
}

/**
 * Verify the admin-referer nonce.
 *
 * Mirrors WordPress semantics: on failure `wp_die()` aborts execution and
 * never returns. Here that branch throws WpDieSignal, which the tests catch,
 * so the harness can prove that `renderConfigPage()` never reaches
 * `update_option()` when the nonce is invalid or missing.
 *
 * @param string $action Nonce action.
 * @param string $name   POST field name.
 * @return int 1 on success.
 * @throws WpDieSignal When the nonce is not valid.
 */
function check_admin_referer($action, $name = '_wpnonce')
{
    if (isset($GLOBALS['mock_admin_nonce_valid']) && $GLOBALS['mock_admin_nonce_valid'] === false) {
        throw new WpDieSignal('check_admin_referer failed: ' . $action . '/' . $name);
    }

    return 1;
}

/**
 * Emit a nonce field (fixed valid value in tests).
 *
 * @param string $action Nonce action.
 * @param string $name   Field name.
 * @return void
 */
function wp_nonce_field($action, $name)
{
    echo '<input type="hidden" name="' . esc_attr($name) . '" value="valid_nonce" />';
}

/**
 * Capability check with a configurable result.
 *
 * Resolution order:
 *
 * 1. `$GLOBALS['mock_user_caps'][ $capability ]` when the scenario set it — the
 *    per-scenario override the canonical permission scenarios need to **revoke**
 *    a capability.
 * 2. The legacy `mock_user_can_manage_options` switch for `manage_options`, kept
 *    so the pre-existing scenarios keep their behaviour.
 * 3. `moderate_comments` is **granted by default** for an authenticated request,
 *    which is what every scenario of this suite presupposes today: the retirement
 *    paths only measure the plugin's provenance guard, and the core's
 *    permission layer (`check_edit_permission()` below) is not in the way. A
 *    scenario that wants to measure that layer revokes it explicitly through
 *    `mock_user_caps`.
 * 4. Everything else is denied, exactly as before.
 *
 * The capability is never read as granted for an anonymous request: the core's
 * `check_edit_permission()` bails out on `0 === get_current_user_id()` before
 * looking at `moderate_comments` (`class-wp-rest-comments-controller.php:1977-1987`),
 * and the double mirrors that order.
 *
 * @param string $capability Capability name.
 * @return bool
 */
function current_user_can($capability)
{
    if (isset($GLOBALS['mock_user_caps']) && is_array($GLOBALS['mock_user_caps'])
        && array_key_exists($capability, $GLOBALS['mock_user_caps'])) {
        return (bool) $GLOBALS['mock_user_caps'][$capability];
    }

    if ('manage_options' === $capability) {
        return !isset($GLOBALS['mock_user_can_manage_options'])
            || $GLOBALS['mock_user_can_manage_options'] !== false;
    }

    if ('moderate_comments' === $capability) {
        return true;
    }

    return false;
}

/**
 * Site home URL.
 *
 * @param string $path Optional path.
 * @return string
 */
function home_url($path = '')
{
    return 'https://atareao.es' . $path;
}

/**
 * Whether the current request is served over TLS (mirrors `is_ssl()`).
 *
 * The harness runs against the `https://` home URL that `home_url()` returns,
 * so the double always answers true: the canonical resource URI of a registry
 * document must carry the scheme the site actually serves. It deliberately does
 * not read `$_SERVER['HTTPS']`, which the harness never sets.
 *
 * @return bool Always true.
 */
function is_ssl()
{
    return true;
}

/**
 * Admin URL.
 *
 * @param string $path Optional path.
 * @return string
 */
function admin_url($path = '')
{
    return 'https://atareao.es/wp-admin/' . $path;
}

/**
 * REST URL for a route (mirrors rest_url()).
 *
 * @param string $path Route path.
 * @return string
 */
function rest_url($path = '')
{
    return 'https://atareao.es/wp-json/' . ltrim((string) $path, '/');
}

/**
 * REST URL prefix (mirrors rest_get_url_prefix()).
 *
 * @return string
 */
function rest_get_url_prefix()
{
    return 'wp-json';
}

/**
 * Filesystem path to a plugin directory (mirrors plugin_dir_path()).
 *
 * @param string $file Plugin file path.
 * @return string Trailing-slash terminated directory path.
 */
function plugin_dir_path($file)
{
    return rtrim(dirname((string) $file), '/\\') . '/';
}

/**
 * URL of a plugin directory (mirrors plugin_dir_url()).
 *
 * @param string $file Plugin file path.
 * @return string Trailing-slash terminated plugin directory URL.
 */
function plugin_dir_url($file)
{
    return 'https://atareao.es/wp-content/plugins/' . basename(dirname((string) $file)) . '/';
}

/**
 * Record (and ignore) an activation hook.
 *
 * The plugin bootstrap registers its activation/deactivation callbacks at file
 * level; the harness only needs the declarations to exist, since neither hook
 * fires during a test run.
 *
 * @param string        $file     Plugin file path.
 * @param callable|null $callback Activation callback.
 * @return void
 */
function register_activation_hook($file, $callback = null)
{
}

/**
 * Record (and ignore) a deactivation hook.
 *
 * @param string        $file     Plugin file path.
 * @param callable|null $callback Deactivation callback.
 * @return void
 */
function register_deactivation_hook($file, $callback = null)
{
}

/**
 * JSON-encode data (mirrors wp_json_encode()).
 *
 * @param mixed $data Data to encode.
 * @return string|false
 */
function wp_json_encode($data)
{
    return json_encode($data);
}

/**
 * Parse a URL (mirrors `wp_parse_url()`).
 *
 * WordPress' wrapper exists mainly to paper over `parse_url()` returning false
 * on malformed input; production code may call either one, so the double
 * delegates to the native function. Component-addressed reads (`PHP_URL_HOST`
 * and friends) and the full-array read behave exactly as in the core.
 *
 * @param string $url       URL to parse.
 * @param int    $component Optional. One of the `PHP_URL_*` constants.
 * @return mixed Parsed component, array of components, or false.
 */
function wp_parse_url($url, $component = -1)
{
    if (-1 === $component) {
        return parse_url((string) $url);
    }

    return parse_url((string) $url, $component);
}

/**
 * Minimal WP_Error replacement.
 */
class WP_Error
{
    /**
     * Machine readable error code.
     *
     * @var string
     */
    private $code;

    /**
     * Human readable error message.
     *
     * @var string
     */
    private $message;

    /**
     * Optional error data (mirrors the core's `$data` argument, used here to
     * carry the HTTP status the REST server would send back).
     *
     * @var mixed
     */
    private $data;

    /**
     * Constructor.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param mixed  $data    Optional error data.
     */
    public function __construct($code = '', $message = '', $data = null)
    {
        $this->code = (string) $code;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * Return the error code.
     *
     * @return string
     */
    public function get_error_code()
    {
        return $this->code;
    }

    /**
     * Return the error message.
     *
     * @return string
     */
    public function get_error_message()
    {
        return $this->message;
    }

    /**
     * Return the error data.
     *
     * @return mixed
     */
    public function get_error_data()
    {
        return $this->data;
    }
}

/**
 * Minimal in-memory double of `WP_REST_Response`.
 */
class WP_REST_Response
{
    /**
     * Response payload.
     *
     * @var mixed
     */
    private $data;

    /**
     * HTTP status.
     *
     * @var int
     */
    private $status;

    /**
     * Response headers.
     *
     * @var array
     */
    private $headers = array();

    /**
     * Constructor.
     *
     * @param mixed $data    Payload.
     * @param int   $status  HTTP status.
     * @param array $headers Headers.
     */
    public function __construct($data = null, $status = 200, $headers = array())
    {
        $this->data = $data;
        $this->status = (int) $status;
        $this->headers = (array) $headers;
    }

    /**
     * Payload.
     *
     * @return mixed
     */
    public function get_data()
    {
        return $this->data;
    }

    /**
     * Set the payload.
     *
     * @param mixed $data Payload.
     * @return void
     */
    public function set_data($data)
    {
        $this->data = $data;
    }

    /**
     * HTTP status.
     *
     * @return int
     */
    public function get_status()
    {
        return $this->status;
    }

    /**
     * Set the HTTP status.
     *
     * @param int $status Status.
     * @return void
     */
    public function set_status($status)
    {
        $this->status = (int) $status;
    }

    /**
     * Response headers.
     *
     * @return array
     */
    public function get_headers()
    {
        return $this->headers;
    }

    /**
     * Set a header, mirroring the core implementation.
     *
     * Aligned with `WP_REST_Response::header()` in
     * `wp-includes/class-wp-http-response.php`: when no replacement is asked for
     * the existing value is concatenated with `', '` instead of being ignored,
     * and the default value of `$replace` is `true`.
     *
     * @param string $key     Header name.
     * @param string $value   Header value.
     * @param bool   $replace Replace.
     * @return void
     */
    public function header($key, $value, $replace = true)
    {
        if ($replace || !isset($this->headers[$key])) {
            $this->headers[$key] = $value;
        } else {
            $this->headers[$key] .= ', ' . $value;
        }
    }
}

/**
 * Minimal in-memory double of `WP_REST_Server` method constants.
 */
class WP_REST_Server
{
    const READABLE = 'GET';
    const CREATABLE = 'POST';
    const EDITABLE = 'POST, PUT, PATCH';
    const DELETABLE = 'DELETE';
    const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
}

/**
 * Signal thrown when the harness must emulate wp_die().
 */
class WpDieSignal extends RuntimeException
{
}

/**
 * Faithful, in-memory double of the core `WP_REST_Request` parameter buckets.
 *
 * Reproduces the two pieces of the core that the retirement guard depends on,
 * line by line from `wp-includes/rest-api/class-wp-rest-request.php`:
 *
 * - the bucket priority order of `get_parameter_order()` (`:1003-1034`), used by
 *   `get_param()`/`has_param()`: the JSON body (only when the request advertises
 *   a JSON content type) first, then the URL-encoded body for the methods that
 *   accept one (`POST`/`PUT`/`PATCH`/`DELETE`), then the query string, the URL
 *   params, and the route `defaults` **last**;
 * - the distribution of `set_param()` (`:456-470`): it rewrites the key in
 *   **every** non-`defaults` bucket that already holds it and, when no such
 *   bucket does, creates it in the first bucket of the order.
 *
 * Because `defaults` is consulted last and is never written by `set_param()`, a
 * server-side rewrite always wins over the route default. That is exactly the
 * property the `force` degradation relies on: the real controller declares
 * `force` with `'default' => false` (`class-wp-rest-comments-controller.php:101-104`)
 * and reads it back with `$request['force']` at `:1071`, so rewriting `force`
 * after the default was populated still turns the destructive branch off.
 *
 * Fidelity notes: the JSON media-type test is delegated to
 * `harness_wp_is_json_media_type()` (it does not consult
 * `get_allowed_mime_types()`, see its docblock); HTTP files, multi-value headers
 * and `ArrayAccess` removals beyond the core's `offsetUnset()` are out of scope,
 * because no scenario in this suite uses them.
 */
class WP_REST_Request implements ArrayAccess
{
    /**
     * HTTP method (uppercased).
     *
     * @var string
     */
    protected $method = '';

    /**
     * Parameter buckets, in the core's shape (`URL`, `GET`, `POST`, `FILES`,
     * `JSON`, `defaults`).
     *
     * @var array<string, mixed>
     */
    protected $params;

    /**
     * Request headers, keys canonicalised as the core does.
     *
     * @var array<string, array>
     */
    protected $headers = array();

    /**
     * Raw request body.
     *
     * @var string|null
     */
    protected $body = null;

    /**
     * Matched route.
     *
     * @var string
     */
    protected $route = '';

    /**
     * Route attributes (the handler group `args`, among others).
     *
     * @var array
     */
    protected $attributes = array();

    /**
     * Whether the JSON body has already been parsed.
     *
     * @var bool
     */
    protected $parsed_json = false;

    /**
     * Whether the URL-encoded body has already been parsed.
     *
     * @var bool
     */
    protected $parsed_body = false;

    /**
     * Constructor (mirrors the core).
     *
     * @param string $method     Optional. HTTP method. Default empty.
     * @param string $route      Optional. Matched route. Default empty.
     * @param array  $attributes Optional. Route attributes. Default empty.
     */
    public function __construct($method = '', $route = '', $attributes = array())
    {
        $this->params = array(
            'URL'      => array(),
            'GET'      => array(),
            'POST'     => array(),
            'FILES'    => array(),
            'JSON'     => null,
            'defaults' => array(),
        );

        $this->set_method($method);
        $this->set_route($route);
        $this->set_attributes($attributes);
    }

    /**
     * HTTP method (uppercased).
     *
     * @return string
     */
    public function get_method()
    {
        return $this->method;
    }

    /**
     * Set the HTTP method (uppercased).
     *
     * @param string $method HTTP method.
     * @return void
     */
    public function set_method($method)
    {
        $this->method = strtoupper((string) $method);
    }

    /**
     * Matched route.
     *
     * @return string
     */
    public function get_route()
    {
        return $this->route;
    }

    /**
     * Set the matched route.
     *
     * @param string $route Route regex.
     * @return void
     */
    public function set_route($route)
    {
        $this->route = (string) $route;
    }

    /**
     * Route attributes.
     *
     * @return array
     */
    public function get_attributes()
    {
        return $this->attributes;
    }

    /**
     * Set the route attributes.
     *
     * @param array $attributes Attributes.
     * @return void
     */
    public function set_attributes($attributes)
    {
        $this->attributes = (array) $attributes;
    }

    /**
     * Canonicalise a header name (dashes and lowercase).
     *
     * @param string $key Header name.
     * @return string
     */
    public static function canonicalize_header_name($key)
    {
        return str_replace('-', '_', strtolower((string) $key));
    }

    /**
     * Read a header value.
     *
     * @param string $key Header name.
     * @return string|null
     */
    public function get_header($key)
    {
        $key = self::canonicalize_header_name($key);
        if (!isset($this->headers[$key])) {
            return null;
        }

        return implode(',', $this->headers[$key]);
    }

    /**
     * Set a header value.
     *
     * @param string $key   Header name.
     * @param mixed  $value Header value, or list of values.
     * @return void
     */
    public function set_header($key, $value)
    {
        $key = self::canonicalize_header_name($key);
        $this->headers[$key] = (array) $value;
    }

    /**
     * Parsed Content-Type header (mirrors the core).
     *
     * @return array|null Map with `value`, `type`, `subtype`, `parameters`.
     */
    public function get_content_type()
    {
        $value = $this->get_header('Content-Type');
        if (empty($value)) {
            return null;
        }

        $parameters = '';
        if (false !== strpos($value, ';')) {
            list($value, $parameters) = explode(';', $value, 2);
        }

        $value = strtolower((string) $value);
        if (false === strpos($value, '/')) {
            return null;
        }

        list($type, $subtype) = explode('/', $value, 2);

        return array_map('trim', compact('value', 'type', 'subtype', 'parameters'));
    }

    /**
     * Whether the request advertises a JSON content type.
     *
     * @return bool
     */
    public function is_json_content_type()
    {
        $content_type = $this->get_content_type();

        return isset($content_type['value']) && harness_wp_is_json_media_type($content_type['value']);
    }

    /**
     * URL parameters (parsed from the route regex).
     *
     * @return array
     */
    public function get_url_params()
    {
        return $this->params['URL'];
    }

    /**
     * Set the URL parameters.
     *
     * @param array $params Parameters.
     * @return void
     */
    public function set_url_params($params)
    {
        $this->params['URL'] = (array) $params;
    }

    /**
     * Query-string parameters.
     *
     * @return array
     */
    public function get_query_params()
    {
        return $this->params['GET'];
    }

    /**
     * Set the query-string parameters.
     *
     * @param array $params Parameters.
     * @return void
     */
    public function set_query_params($params)
    {
        $this->params['GET'] = (array) $params;
    }

    /**
     * Body (URL-encoded) parameters.
     *
     * @return array
     */
    public function get_body_params()
    {
        return $this->params['POST'];
    }

    /**
     * Set the body parameters.
     *
     * @param array $params Parameters.
     * @return void
     */
    public function set_body_params($params)
    {
        $this->params['POST'] = (array) $params;
    }

    /**
     * Route default parameters.
     *
     * @return array
     */
    public function get_default_params()
    {
        return $this->params['defaults'];
    }

    /**
     * Set the route default parameters.
     *
     * @param array $params Parameters.
     * @return void
     */
    public function set_default_params($params)
    {
        $this->params['defaults'] = (array) $params;
    }

    /**
     * Raw request body.
     *
     * @return string|null
     */
    public function get_body()
    {
        return $this->body;
    }

    /**
     * Set the raw request body and reset the lazy parsing.
     *
     * @param string $data Body.
     * @return void
     */
    public function set_body($data)
    {
        $this->body = $data;
        $this->parsed_json = false;
        $this->parsed_body = false;
        $this->params['JSON'] = null;
    }

    /**
     * JSON body parameters.
     *
     * @return array|null
     */
    public function get_json_params()
    {
        $this->parse_json_params();

        return $this->params['JSON'];
    }

    /**
     * Parameter priority order (mirrors the core).
     *
     * @return string[]
     */
    protected function get_parameter_order()
    {
        $order = array();

        if ($this->is_json_content_type()) {
            $order[] = 'JSON';
        }

        $this->parse_json_params();

        $body = $this->get_body();
        if ('POST' !== $this->method && !empty($body)) {
            $this->parse_body_params();
        }

        $accepts_body_data = array('POST', 'PUT', 'PATCH', 'DELETE');
        if (in_array($this->method, $accepts_body_data, true)) {
            $order[] = 'POST';
        }

        $order[] = 'GET';
        $order[] = 'URL';
        $order[] = 'defaults';

        return apply_filters('rest_request_parameter_order', $order, $this);
    }

    /**
     * Read a parameter, walking the buckets in priority order.
     *
     * @param string $key Parameter name.
     * @return mixed|null
     */
    public function get_param($key)
    {
        foreach ($this->get_parameter_order() as $type) {
            if (isset($this->params[$type][$key])) {
                return $this->params[$type][$key];
            }
        }

        return null;
    }

    /**
     * Whether a parameter exists in any bucket.
     *
     * @param string $key Parameter name.
     * @return bool
     */
    public function has_param($key)
    {
        foreach ($this->get_parameter_order() as $type) {
            if (is_array($this->params[$type]) && array_key_exists($key, $this->params[$type])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rewrite a parameter: every non-`defaults` bucket that holds the key is
     * updated; when none does, the key is created in the first bucket.
     *
     * `defaults` is deliberately excluded, exactly as in the core, so a
     * server-side rewrite cannot be shadowed by the route default.
     *
     * @param string $key   Parameter name.
     * @param mixed  $value Parameter value.
     * @return void
     */
    public function set_param($key, $value)
    {
        $order     = $this->get_parameter_order();
        $found_key = false;

        foreach ($order as $type) {
            if ('defaults' !== $type
                && is_array($this->params[$type])
                && array_key_exists($key, $this->params[$type])
            ) {
                $this->params[$type][$key] = $value;
                $found_key = true;
            }
        }

        if (!$found_key) {
            $this->params[$order[0]][$key] = $value;
        }
    }

    /**
     * All parameters merged in reverse priority order (mirrors the core).
     *
     * @return array
     */
    public function get_params()
    {
        $order  = array_reverse($this->get_parameter_order(), true);
        $params = array();
        foreach ($order as $type) {
            foreach ((array) $this->params[$type] as $key => $value) {
                $params[$key] = $value;
            }
        }

        if (!get_option('permalink_structure')) {
            unset($params['rest_route']);
        }

        return $params;
    }

    /**
     * Lazy JSON body parsing (mirrors the core).
     *
     * @return true|WP_Error
     */
    protected function parse_json_params()
    {
        if ($this->parsed_json) {
            return true;
        }

        $this->parsed_json = true;

        if (!$this->is_json_content_type()) {
            return true;
        }

        $body = $this->get_body();
        if (empty($body)) {
            return true;
        }

        $params = json_decode($body, true);
        if (null === $params && JSON_ERROR_NONE !== json_last_error()) {
            $this->parsed_json = false;

            return new WP_Error(
                'rest_invalid_json',
                'Invalid JSON body passed.',
                array('status' => 400, 'json_error_code' => json_last_error())
            );
        }

        $this->params['JSON'] = $params;

        return true;
    }

    /**
     * Lazy URL-encoded body parsing (mirrors the core).
     *
     * @return void
     */
    protected function parse_body_params()
    {
        if ($this->parsed_body) {
            return;
        }

        $this->parsed_body = true;

        $content_type = $this->get_content_type();
        if (!empty($content_type) && 'application/x-www-form-urlencoded' !== $content_type['value']) {
            return;
        }

        parse_str((string) $this->get_body(), $params);
        $this->params['POST'] = array_merge($params, $this->params['POST']);
    }

    /**
     * Whether a parameter is set (ArrayAccess).
     *
     * @param string $offset Parameter name.
     * @return bool
     */
    #[ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        foreach ($this->get_parameter_order() as $type) {
            if (isset($this->params[$type][$offset])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read a parameter (ArrayAccess).
     *
     * @param string $offset Parameter name.
     * @return mixed|null
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get_param($offset);
    }

    /**
     * Write a parameter (ArrayAccess).
     *
     * @param string $offset Parameter name.
     * @param mixed  $value  Parameter value.
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->set_param($offset, $value);
    }

    /**
     * Remove a parameter from every bucket (ArrayAccess).
     *
     * @param string $offset Parameter name.
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        foreach ($this->get_parameter_order() as $type) {
            unset($this->params[$type][$offset]);
        }
    }
}

/**
 * Doble mínimo en memoria de `WP_Post`.
 *
 * Solo declara las propiedades que el plugin lee al dar formato a una entrada
 * (`MCP::formatPost()` y las funciones de consulta de este mismo fichero). No
 * reimplementa la entrada de WordPress: no hay filtros de contenido, revisiones
 * ni jerarquía de páginas.
 */
class WP_Post
{
    /**
     * Identificador de la entrada.
     *
     * @var int
     */
    public $ID = 0;

    /**
     * Identificador del autor.
     *
     * @var int
     */
    public $post_author = 0;

    /**
     * Fecha de publicación (`Y-m-d H:i:s`).
     *
     * @var string
     */
    public $post_date = '';

    /**
     * Fecha de modificación (`Y-m-d H:i:s`; cadena vacía si nunca se modificó).
     *
     * @var string
     */
    public $post_modified = '';

    /**
     * Fecha de publicación en GMT (`Y-m-d H:i:s`).
     *
     * @var string
     */
    public $post_date_gmt = '';

    /**
     * Fecha de modificación en GMT (`Y-m-d H:i:s`).
     *
     * @var string
     */
    public $post_modified_gmt = '';

    /**
     * Contenido de la entrada.
     *
     * @var string
     */
    public $post_content = '';

    /**
     * Título de la entrada.
     *
     * @var string
     */
    public $post_title = '';

    /**
     * Extracto de la entrada.
     *
     * @var string
     */
    public $post_excerpt = '';

    /**
     * Estado de publicación.
     *
     * @var string
     */
    public $post_status = 'publish';

    /**
     * Slug de la entrada.
     *
     * @var string
     */
    public $post_name = '';

    /**
     * Tipo de contenido.
     *
     * @var string
     */
    public $post_type = 'post';

    /**
     * Contraseña de la entrada (cadena vacía si no está protegida).
     *
     * @var string
     */
    public $post_password = '';

    /**
     * Construye la entrada a partir de un mapa campo => valor.
     *
     * Solo se copian las claves declaradas; el resto se ignora, de modo que un
     * array con campos de más no introduce propiedades dinámicas.
     *
     * @param array $fields Mapa asociativo de campos de la entrada.
     */
    public function __construct($fields = array())
    {
        foreach (get_object_vars($this) as $field => $default) {
            if (isset($fields[$field])) {
                $this->$field = $fields[$field];
            }
        }
    }
}

/**
 * Doble mínimo en memoria de `WP_Query`.
 *
 * **No reimplementa WordPress.** Solo cubre la consulta que ejecuta
 * `MCP::getLatestPosts()`: lee las entradas precargadas en
 * `$GLOBALS['mock_posts']`, filtra por `post_type` (cadena o array) y por
 * `post_status` cuando vienen en los argumentos, y recorta a `posts_per_page`.
 * No hay SQL, orden, paginación, búsqueda, taxonomías ni metas.
 */
class WP_Query
{
    /**
     * Argumentos de la consulta tal como se recibieron.
     *
     * @var array
     */
    public $query_vars = array();

    /**
     * Entradas que satisfacen la consulta.
     *
     * @var WP_Post[]
     */
    public $posts = array();

    /**
     * Entrada actual del bucle (`the_post()`).
     *
     * @var WP_Post|null
     */
    public $current_post = null;

    /**
     * Índice de la siguiente entrada del bucle.
     *
     * @var int
     */
    private $index = 0;

    /**
     * Ejecuta la consulta simulada contra `$GLOBALS['mock_posts']`.
     *
     * @param array $args Argumentos de la consulta.
     */
    public function __construct($args = array())
    {
        $this->query_vars = is_array($args) ? $args : array();
        $available = isset($GLOBALS['mock_posts']) && is_array($GLOBALS['mock_posts'])
            ? $GLOBALS['mock_posts']
            : array();

        $types = isset($this->query_vars['post_type']) ? $this->query_vars['post_type'] : '';
        $types = is_array($types) ? $types : ('' === $types ? array() : array($types));

        $statuses = isset($this->query_vars['post_status']) ? $this->query_vars['post_status'] : '';
        $statuses = is_array($statuses) ? $statuses : ('' === $statuses ? array() : array($statuses));

        foreach ($available as $post) {
            if (!empty($types) && !in_array($post->post_type, $types, true)) {
                continue;
            }

            if (!empty($statuses) && !in_array($post->post_status, $statuses, true)) {
                continue;
            }

            $this->posts[] = $post;
        }

        $per_page = isset($this->query_vars['posts_per_page']) ? (int) $this->query_vars['posts_per_page'] : 0;
        if ($per_page > 0) {
            $this->posts = array_slice($this->posts, 0, $per_page);
        }
    }

    /**
     * Indica si quedan entradas por recorrer en el bucle.
     *
     * @return bool
     */
    public function have_posts()
    {
        return $this->index < count($this->posts);
    }

    /**
     * Avanza el bucle a la siguiente entrada.
     *
     * No establece las variables globales del bucle (fuera del alcance del
     * simulador); solo actualiza `current_post` y el índice interno.
     *
     * @return void
     */
    public function the_post()
    {
        if (!$this->have_posts()) {
            $this->current_post = null;
            return;
        }

        $this->current_post = $this->posts[$this->index];
        $this->index++;
    }
}

/**
 * Whether a value is a WP_Error.
 *
 * @param mixed $thing Value to inspect.
 * @return bool
 */
function is_wp_error($thing)
{
    return $thing instanceof WP_Error;
}

/**
 * Validate an absolute HTTP(S) URL (mirrors wp_http_validate_url()).
 *
 * Faithful to WordPress on the checks that matter for the notification
 * hardening: it accepts only absolute `http`/`https` URLs without userinfo,
 * with a plausible host, and rejects hosts that are private or reserved IPv4
 * literals. Unlike the real function it does not perform DNS resolution, so a
 * hostname that resolves to a private address is not rejected; tests use
 * literal addresses or embedded credentials to exercise the rejection path.
 *
 * @param mixed $url Raw URL.
 * @return string|false The URL when valid, false otherwise.
 */
function wp_http_validate_url($url)
{
    if (!is_string($url) || '' === $url) {
        return false;
    }

    $parsed = parse_url($url);
    if (!is_array($parsed) || empty($parsed['host']) || empty($parsed['scheme'])) {
        return false;
    }

    if (!in_array(strtolower($parsed['scheme']), array('http', 'https'), true)) {
        return false;
    }

    if (isset($parsed['user']) || isset($parsed['pass'])) {
        return false;
    }

    $host = trim($parsed['host'], '.');
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $public = filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
        if (false === $public) {
            return false;
        }
    }

    return $url;
}

/**
 * Record and answer an HTTP POST with the configured mock response.
 *
 * @param string $url  Request URL.
 * @param array  $args Request arguments.
 * @return mixed Configured mock response.
 */
function wp_remote_post($url, $args = array())
{
    $GLOBALS['mock_remote_requests'][] = array('url' => $url, 'args' => $args);

    return isset($GLOBALS['mock_remote_response'])
        ? $GLOBALS['mock_remote_response']
        : array('response' => array('code' => 201));
}

/**
 * Extract the HTTP status code from a mock response.
 *
 * @param mixed $response Mock response.
 * @return int
 */
function wp_remote_retrieve_response_code($response)
{
    if (is_wp_error($response) || !isset($response['response']['code'])) {
        return 0;
    }

    return intval($response['response']['code']);
}

/*
 * Cola de respuestas para las peticiones simuladas. Cada llamada a
 * `wp_safe_remote_head`/`wp_safe_remote_get` consume la siguiente entrada
 * (FIFO). Cuando la cola se agota se responde `mock_remote_response` y, si
 * tampoco está definida, una respuesta neutra `200` SIN cabeceras: así una
 * longitud ausente significa "no determinable", nunca un número inventado.
 */
function atareao_mock_remote_request($url, $args)
{
    $GLOBALS['mock_remote_requests'][] = array('url' => $url, 'args' => $args);

    if (isset($GLOBALS['mock_remote_queue']) && is_array($GLOBALS['mock_remote_queue'])
        && count($GLOBALS['mock_remote_queue']) > 0) {
        return array_shift($GLOBALS['mock_remote_queue']);
    }

    if (isset($GLOBALS['mock_remote_response'])) {
        return $GLOBALS['mock_remote_response'];
    }

    return array('response' => array('code' => 200), 'headers' => array(), 'body' => '');
}

function wp_safe_remote_head($url, $args = array())
{
    return atareao_mock_remote_request($url, $args);
}

function wp_safe_remote_get($url, $args = array())
{
    return atareao_mock_remote_request($url, $args);
}

function wp_remote_retrieve_header($response, $header)
{
    if (is_wp_error($response) || !isset($response['headers']) || !is_array($response['headers'])) {
        return '';
    }

    foreach ($response['headers'] as $name => $value) {
        if (strcasecmp((string) $name, (string) $header) === 0) {
            return is_array($value) ? (string) reset($value) : (string) $value;
        }
    }

    return '';
}

function wp_remote_retrieve_body($response)
{
    if (is_wp_error($response) || !isset($response['body'])) {
        return '';
    }

    return (string) $response['body'];
}

/**
 * Register a hook and record it.
 *
 * @param string   $tag           Hook name.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Accepted argument count.
 * @return void
 */
function add_action($tag, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['mock_actions'][$tag][] = array(
        'callback' => $callback,
        'priority' => $priority,
        'args' => $accepted_args,
    );
}

/**
 * Dispatch a recorded action, mirroring `do_action()`.
 *
 * Calls every callback registered for the tag, slicing the argument list to
 * the accepted-argument count it declared (exactly as the core does). Used by
 * the REST-insert simulation so the test exercises the *registered* hooks
 * rather than calling the callbacks by hand.
 *
 * The empty-argument quirk is deliberate: `wp-includes/plugin.php` (WP 7.1.x)
 * runs, before dispatching,
 *
 *     if ( empty( $arg ) ) {
 *         $arg[] = '';
 *     }
 *
 * so a hook fired with no arguments still hands its callbacks ONE empty
 * string. Without it this double is *more lenient* than the core: a callback
 * typed `?int $postId` receives `''` in production (a fatal `TypeError`, which
 * is what truncated the podcast feed) but zero arguments here (silently fine).
 * A kinder double than the core yields a green suite over a broken feed.
 *
 * @param string $tag       Hook name.
 * @param mixed  ...$args   Arguments passed to the callbacks.
 * @return void
 */
function do_action($tag, ...$args)
{
    $actions = isset($GLOBALS['mock_actions'][$tag]) ? $GLOBALS['mock_actions'][$tag] : array();

    if (empty($args)) {
        $args[] = '';
    }

    foreach ($actions as $action) {
        $accepted = max(0, (int) $action['args']);
        call_user_func_array($action['callback'], array_slice($args, 0, $accepted));
    }
}

/**
 * ID of the user the current request is authenticated as.
 *
 * @return int Current user ID, or 0 for an anonymous request.
 */
function get_current_user_id()
{
    return isset($GLOBALS['mock_current_user_id']) ? (int) $GLOBALS['mock_current_user_id'] : 0;
}

/**
 * Whether the current request is authenticated (mirrors `is_user_logged_in()`).
 *
 * Needed by `rest_authorization_required_code()`: the double projects the same
 * 403/401 distinction the core makes, so a permission failure lands on 403 for
 * an authenticated identity.
 *
 * @return bool True when a user is logged in.
 */
function is_user_logged_in()
{
    return get_current_user_id() > 0;
}

/**
 * Current user object (mirrors `wp_get_current_user()`).
 *
 * @return object Minimal WP_User with the fields the code under test reads.
 */
function wp_get_current_user()
{
    $id = get_current_user_id();

    return (object) array(
        'ID' => $id,
        'display_name' => 'user-' . $id,
        'user_email' => 'user' . $id . '@example.com',
        'user_url' => '',
    );
}

/**
 * Persist a comment meta value (mirrors `add_comment_meta()`).
 *
 * @param int    $comment_id Comment ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value.
 * @param bool   $unique     Whether to refuse overwriting an existing value.
 * @return bool True when the value was stored.
 */
function add_comment_meta($comment_id, $meta_key, $meta_value, $unique = false)
{
    $comment_id = (int) $comment_id;
    if ($unique && isset($GLOBALS['mock_comment_meta'][$comment_id][$meta_key])) {
        return false;
    }

    $GLOBALS['mock_comment_meta'][$comment_id][$meta_key] = $meta_value;

    return true;
}

/**
 * Read a comment meta value (mirrors `get_comment_meta()`).
 *
 * Returns `''` when the key is missing and `$single` is true, exactly as the
 * core does, so callers can compare the value without guarding the lookup.
 *
 * @param int    $comment_id Comment ID.
 * @param string $meta_key   Meta key ('' returns the whole store).
 * @param bool   $single     Whether to return a single value.
 * @return mixed Stored value.
 */
function get_comment_meta($comment_id, $meta_key = '', $single = false)
{
    $comment_id = (int) $comment_id;
    $store = isset($GLOBALS['mock_comment_meta'][$comment_id])
        ? $GLOBALS['mock_comment_meta'][$comment_id]
        : array();

    if ('' === $meta_key) {
        return $single ? $store : array_map(static fn($value) => array($value), $store);
    }

    if (!array_key_exists($meta_key, $store)) {
        return $single ? '' : array();
    }

    return $single ? $store[$meta_key] : array($store[$meta_key]);
}

/**
 * Fetch a comment from the in-memory store.
 *
 * @param int $comment_id Comment ID.
 * @return object|null
 */
function get_comment($comment_id)
{
    $comments = isset($GLOBALS['mock_comments']) ? $GLOBALS['mock_comments'] : array();
    if (!isset($comments[$comment_id])) {
        return null;
    }

    return (object) $comments[$comment_id];
}

/**
 * Comment author name.
 *
 * @param object $comment Comment object.
 * @return string
 */
function get_comment_author($comment)
{
    return isset($comment->comment_author) ? $comment->comment_author : '';
}

/**
 * Comment raw text.
 *
 * @param object $comment Comment object.
 * @return string
 */
function get_comment_text($comment)
{
    return isset($comment->comment_content) ? $comment->comment_content : '';
}

/**
 * Comment type (mirrors get_comment_type()).
 *
 * An empty `comment_type` means a regular comment in WordPress, which is what
 * lets the notification route distinguish `comment` from `pingback` and
 * `trackback`.
 *
 * @param object $comment Comment object.
 * @return string Comment type, defaulting to 'comment'.
 */
function get_comment_type($comment = null)
{
    if (is_object($comment) && isset($comment->comment_type) && '' !== $comment->comment_type) {
        return (string) $comment->comment_type;
    }

    return 'comment';
}

/**
 * Coarse status of a comment (mirrors `wp_get_comment_status()`).
 *
 * Maps the stored `comment_approved` value to the string the retirement path
 * compares against: `'1'` → `approved`, `'0'` → `unapproved`, and the `spam` /
 * `trash` markers verbatim.
 *
 * @param int $comment_id Comment ID.
 * @return string|false Status, or false when the comment does not exist.
 */
function wp_get_comment_status($comment_id)
{
    $comment = get_comment($comment_id);
    if (!$comment) {
        return false;
    }

    $approved = isset($comment->comment_approved) ? $comment->comment_approved : '';
    if ('1' == $approved) {
        return 'approved';
    }
    if ('0' == $approved) {
        return 'unapproved';
    }
    if ('spam' === $approved) {
        return 'spam';
    }
    if ('trash' === $approved) {
        return 'trash';
    }

    return false;
}

/**
 * Change a comment's approval status (mirrors `wp_set_comment_status()`).
 *
 * Only the two transitions the harness exercises are modelled (`approve` → `1`,
 * `hold` → `0`); it returns false when the target status is neither of those nor
 * one of the stored markers, exactly as the core does.
 *
 * @param int    $comment_id     Comment ID.
 * @param string $comment_status Requested status ('approve', 'hold', '0', '1',
 *                               'spam' or 'trash').
 * @return bool Whether the status was changed.
 */
function wp_set_comment_status($comment_id, $comment_status)
{
    $comment_id = (int) $comment_id;
    if (!isset($GLOBALS['mock_comments'][$comment_id])) {
        return false;
    }

    switch ($comment_status) {
        case 'hold':
            $comment_status = '0';
            break;
        case 'approve':
            $comment_status = '1';
            break;
        default:
            if (!in_array($comment_status, array('0', '1', 'spam', 'trash'), true)) {
                return false;
            }
            break;
    }

    $old_status = wp_get_comment_status($comment_id);
    $GLOBALS['mock_comments'][$comment_id]['comment_approved'] = $comment_status;
    do_action('transition_comment_status', $comment_status, $old_status, get_comment($comment_id));

    return true;
}

/**
 * Trash a comment (mirrors the observable side of `wp_trash_comment()`).
 *
 * Stores the previous approval in `_wp_trash_meta_status` (so `untrash` can
 * restore it, as the core does) and fires `trash_comment` followed by the
 * `transition_comment_status` action the notifications module listens to.
 *
 * @param int $comment_id Comment ID.
 * @return bool Whether the comment was trashed.
 */
function wp_trash_comment($comment_id)
{
    $comment_id = (int) $comment_id;
    if (!isset($GLOBALS['mock_comments'][$comment_id])) {
        return false;
    }

    $old_status = wp_get_comment_status($comment_id);
    $comment    = get_comment($comment_id);

    $GLOBALS['mock_comment_meta'][$comment_id]['_wp_trash_meta_status'] = $comment->comment_approved;
    $GLOBALS['mock_comment_meta'][$comment_id]['_wp_trash_meta_time']   = time();
    $GLOBALS['mock_comments'][$comment_id]['comment_approved']          = 'trash';

    do_action('trash_comment', $comment_id, get_comment($comment_id));
    do_action('transition_comment_status', 'trash', $old_status, get_comment($comment_id));

    return true;
}

/**
 * Untrash a comment (mirrors the observable side of `wp_untrash_comment()`).
 *
 * @param int $comment_id Comment ID.
 * @return bool Whether the comment was untrashed.
 */
function wp_untrash_comment($comment_id)
{
    $comment_id = (int) $comment_id;
    if (!isset($GLOBALS['mock_comments'][$comment_id])) {
        return false;
    }

    $old_status = wp_get_comment_status($comment_id);
    $restore    = '1';
    if (isset($GLOBALS['mock_comment_meta'][$comment_id]['_wp_trash_meta_status'])) {
        $restore = (string) $GLOBALS['mock_comment_meta'][$comment_id]['_wp_trash_meta_status'];
    }

    $GLOBALS['mock_comments'][$comment_id]['comment_approved'] = $restore;
    unset(
        $GLOBALS['mock_comment_meta'][$comment_id]['_wp_trash_meta_status'],
        $GLOBALS['mock_comment_meta'][$comment_id]['_wp_trash_meta_time']
    );

    do_action('untrash_comment', $comment_id, get_comment($comment_id));
    do_action('transition_comment_status', wp_get_comment_status($comment_id), $old_status, get_comment($comment_id));

    return true;
}

/**
 * Mark a comment as spam (mirrors the observable side of `wp_spam_comment()`).
 *
 * @param int $comment_id Comment ID.
 * @return bool Whether the comment was marked as spam.
 */
function wp_spam_comment($comment_id)
{
    $comment_id = (int) $comment_id;
    if (!isset($GLOBALS['mock_comments'][$comment_id])) {
        return false;
    }

    $old_status = wp_get_comment_status($comment_id);
    $comment    = get_comment($comment_id);

    $GLOBALS['mock_comment_meta'][$comment_id]['_wp_spam_meta_status'] = $comment->comment_approved;
    $GLOBALS['mock_comments'][$comment_id]['comment_approved']         = 'spam';

    do_action('spam_comment', $comment_id, get_comment($comment_id));
    do_action('transition_comment_status', 'spam', $old_status, get_comment($comment_id));

    return true;
}

/**
 * Restore a comment marked as spam (mirrors the observable side of `wp_unspam_comment()`).
 *
 * @param int $comment_id Comment ID.
 * @return bool Whether the comment was unspammed.
 */
function wp_unspam_comment($comment_id)
{
    $comment_id = (int) $comment_id;
    if (!isset($GLOBALS['mock_comments'][$comment_id])) {
        return false;
    }

    $old_status = wp_get_comment_status($comment_id);
    $restore    = '1';
    if (isset($GLOBALS['mock_comment_meta'][$comment_id]['_wp_spam_meta_status'])) {
        $restore = (string) $GLOBALS['mock_comment_meta'][$comment_id]['_wp_spam_meta_status'];
    }

    $GLOBALS['mock_comments'][$comment_id]['comment_approved'] = $restore;
    unset($GLOBALS['mock_comment_meta'][$comment_id]['_wp_spam_meta_status']);

    do_action('unspam_comment', $comment_id, get_comment($comment_id));
    do_action('transition_comment_status', wp_get_comment_status($comment_id), $old_status, get_comment($comment_id));

    return true;
}

/**
 * Delete a comment (mirrors `wp_delete_comment()`).
 *
 * With `$force_delete` false it trashes the comment, as the core does; with it
 * true it removes the row and its meta and records the destruction in
 * `$GLOBALS['mock_deleted_comments']`, which is the observable the suite uses to
 * prove the destructive branch did (or did not) run.
 *
 * @param int  $comment_id   Comment ID.
 * @param bool $force_delete Whether to bypass the trash and delete permanently.
 * @return bool Whether the comment was deleted (or trashed).
 */
function wp_delete_comment($comment_id, $force_delete = false)
{
    $comment_id = (int) $comment_id;
    if (!isset($GLOBALS['mock_comments'][$comment_id])) {
        return false;
    }

    if (!$force_delete) {
        return wp_trash_comment($comment_id);
    }

    $comment = get_comment($comment_id);
    unset($GLOBALS['mock_comments'][$comment_id], $GLOBALS['mock_comment_meta'][$comment_id]);
    $GLOBALS['mock_deleted_comments'][] = $comment_id;

    do_action('delete_comment', $comment_id, $comment);

    return true;
}

/**
 * Strip all HTML tags (mirrors wp_strip_all_tags()).
 *
 * @param mixed $text          Raw text.
 * @param bool  $remove_breaks Collapse line breaks when true.
 * @return string
 */
function wp_strip_all_tags($text, $remove_breaks = false)
{
    $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text);
    $text = strip_tags((string) $text);
    if ($remove_breaks) {
        $text = preg_replace('/[\r\n\t ]+/', ' ', (string) $text);
    }

    return trim((string) $text);
}

/**
 * Tipos de contenido registrados, como mapa nombre => nombre.
 *
 * Igual que `get_post_types()` con `$output = 'names'`: el plugin lo llama con
 * `array('public' => true)` y después aplica `array_values()`, así que basta el
 * mapa. El runtime de pruebas no modela los argumentos ni otras salidas.
 *
 * @param array  $args     Argumentos de filtrado (ignorados).
 * @param string $output   Formato de salida (ignorado).
 * @param string $operator Operador lógico (ignorado).
 * @return array<string, string>
 */
function get_post_types($args = array(), $output = 'names', $operator = 'and')
{
    return isset($GLOBALS['mock_post_types']) && is_array($GLOBALS['mock_post_types'])
        ? $GLOBALS['mock_post_types']
        : array('post' => 'post', 'page' => 'page', 'podcast' => 'podcast');
}

/**
 * Título de una entrada, aceptando el objeto o su identificador.
 *
 * @param WP_Post|int $post Entrada o identificador.
 * @return string
 */
function get_the_title($post)
{
    if (is_object($post)) {
        return isset($post->post_title) ? (string) $post->post_title : '';
    }

    $posts = isset($GLOBALS['mock_posts']) && is_array($GLOBALS['mock_posts']) ? $GLOBALS['mock_posts'] : array();
    foreach ($posts as $candidate) {
        if ((int) $candidate->ID === (int) $post) {
            return (string) $candidate->post_title;
        }
    }

    return '';
}

/**
 * Extracto de una entrada, aceptando el objeto o su identificador.
 *
 * @param WP_Post|int $post Entrada o identificador.
 * @return string Extracto, o cadena vacía si no lo tiene.
 */
function get_the_excerpt($post)
{
    if (is_object($post)) {
        return isset($post->post_excerpt) ? (string) $post->post_excerpt : '';
    }

    $posts = isset($GLOBALS['mock_posts']) && is_array($GLOBALS['mock_posts']) ? $GLOBALS['mock_posts'] : array();
    foreach ($posts as $candidate) {
        if ((int) $candidate->ID === (int) $post) {
            return (string) $candidate->post_excerpt;
        }
    }

    return '';
}

/**
 * Metadatos de una entrada, leídos del almacén en memoria.
 *
 * @param int    $post_id Identificador de la entrada.
 * @param string $key     Clave del metadato.
 * @param bool   $single  Si es `true`, devuelve el valor; si no, un array.
 * @return mixed Valor del metadato, `''` o `array()` si no existe.
 */
function get_post_meta($post_id, $key = '', $single = false)
{
    $meta = isset($GLOBALS['mock_post_meta']) && is_array($GLOBALS['mock_post_meta'])
        ? $GLOBALS['mock_post_meta']
        : array();

    if (isset($meta[$post_id]) && is_array($meta[$post_id]) && array_key_exists($key, $meta[$post_id])) {
        return $meta[$post_id][$key];
    }

    return $single ? '' : array();
}

/**
 * Taxonomías asociadas a un tipo de contenido.
 *
 * El simulador no modela taxonomías: siempre devuelve un array vacío, de modo
 * que `MCP::publicTaxonomies()` corta antes de necesitar `get_the_terms()`.
 *
 * @param string $post_type Tipo de contenido.
 * @param string $output    Formato de salida (ignorado).
 * @return array
 */
function get_object_taxonomies($post_type, $output = 'names')
{
    return array();
}

/**
 * Permalink de una entrada.
 *
 * @param int|WP_Post $post_id Identificador o entrada.
 * @return string
 */
function get_permalink($post_id = 0)
{
    if (is_object($post_id)) {
        $post_id = isset($post_id->ID) ? $post_id->ID : 0;
    }

    return 'https://atareao.es/post/' . $post_id;
}

/**
 * Resuelve la entrada actual a partir del argumento que reciba una función de
 * consulta de WordPress.
 *
 * Modela la semántica real del core: un `WP_Post` se devuelve tal cual, un
 * identificador se busca en `$GLOBALS['mock_posts']` y `null`/`0` cae al objeto
 * consultado. Así cualquier acceso plausible (`get_post()`, `get_post_type()`,
 * un `$post` explícito…) devuelve la MISMA fixture y el test no depende de cuál
 * elija la implementación.
 *
 * @param WP_Post|int|null $post Entrada, identificador o `null`.
 * @return WP_Post|null Entrada resuelta, o `null` si no existe.
 */
function sd_mock_resolve_post($post)
{
    if ($post instanceof WP_Post) {
        return $post;
    }

    if (null === $post || 0 === (int) $post) {
        return get_queried_object();
    }

    $id = (int) $post;
    $posts = isset($GLOBALS['mock_posts']) && is_array($GLOBALS['mock_posts'])
        ? $GLOBALS['mock_posts']
        : array();
    foreach ($posts as $candidate) {
        if ($candidate instanceof WP_Post && (int) $candidate->ID === $id) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Whether the current request is a single content view (mirrors `is_singular()`).
 *
 * Cuando se pasa `$types`, exige además que el tipo del objeto consultado esté
 * entre los aceptados (cadena o array), igual que el core. El *modo* se modela
 * con `mock_singular` y el *tipo* contra el objeto consultado, de modo que una
 * implementación que restrinja por tipo o que confíe en la consulta obtiene el
 * mismo veredicto. Sin objeto consultado no puede haber singular.
 *
 * @param string|string[] $types Tipos de contenido aceptados (opcional).
 * @return bool
 */
function is_singular($types = '')
{
    if (empty($GLOBALS['mock_singular'])) {
        return false;
    }

    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return false;
    }

    if ('' === $types || array() === $types) {
        return true;
    }

    $wanted = is_array($types) ? $types : array($types);

    return in_array((string) $post->post_type, $wanted, true);
}

/**
 * Whether the current request is an archive (mirrors `is_archive()`).
 *
 * @return bool
 */
function is_archive()
{
    return !empty($GLOBALS['mock_archive']);
}

/**
 * Whether the current request is the blog home (mirrors `is_home()`).
 *
 * @return bool
 */
function is_home()
{
    return !empty($GLOBALS['mock_home']);
}

/**
 * Whether the current request is a search (mirrors `is_search()`).
 *
 * @return bool
 */
function is_search()
{
    return !empty($GLOBALS['mock_search']);
}

/**
 * Whether the current request is a 404 (mirrors `is_404()`).
 *
 * @return bool
 */
function is_404()
{
    return !empty($GLOBALS['mock_is_404']);
}

/**
 * Whether the current request is the front page (mirrors `is_front_page()`).
 *
 * @return bool
 */
function is_front_page()
{
    return !empty($GLOBALS['mock_front_page']);
}

/**
 * Whether the current request is a feed (mirrors `is_feed()`).
 *
 * @return bool
 */
function is_feed()
{
    return !empty($GLOBALS['mock_is_feed']);
}

/**
 * The object of the current query (mirrors `get_queried_object()`).
 *
 * @return WP_Post|null
 */
function get_queried_object()
{
    return isset($GLOBALS['mock_queried_object']) ? $GLOBALS['mock_queried_object'] : null;
}

/**
 * Identifier of the object of the current query (mirrors `get_queried_object_id()`).
 *
 * @return int
 */
function get_queried_object_id()
{
    $object = get_queried_object();

    return $object instanceof WP_Post ? (int) $object->ID : 0;
}

/**
 * Identifier of the current post in the loop (mirrors `get_the_ID()`).
 *
 * @return int
 */
function get_the_ID()
{
    return get_queried_object_id();
}

/**
 * Retrieve a post (mirrors `get_post()`).
 *
 * Sin identificador devuelve el objeto consultado, y un `WP_Post` se devuelve
 * tal cual: todas las vías apuntan a la misma fixture.
 *
 * @param WP_Post|int|null $id Entrada, identificador o `null`.
 * @return WP_Post|null
 */
function get_post($id = 0)
{
    return sd_mock_resolve_post($id);
}

/**
 * Type of a post (mirrors `get_post_type()`).
 *
 * @param WP_Post|int|null $post Entrada, identificador o `null`.
 * @return string|false
 */
function get_post_type($post = null)
{
    $resolved = sd_mock_resolve_post($post);

    return $resolved instanceof WP_Post ? (string) $resolved->post_type : false;
}

/**
 * Status of a post (mirrors `get_post_status()`).
 *
 * @param WP_Post|int|null $post Entrada, identificador o `null`.
 * @return string|false
 */
function get_post_status($post = null)
{
    $resolved = sd_mock_resolve_post($post);

    return $resolved instanceof WP_Post ? (string) $resolved->post_status : false;
}

/**
 * Whether the current post is password protected (mirrors `post_password_required()`).
 *
 * Responde `true` por las dos vías coherentes: la bandera de entorno
 * `mock_password_required` y la contraseña de la propia entrada, de modo que
 * una implementación que consulte cualquiera de las dos obtiene el mismo
 * veredicto.
 *
 * @param WP_Post|int|null $post Entrada, identificador o `null`.
 * @return bool
 */
function post_password_required($post = null)
{
    if (!empty($GLOBALS['mock_password_required'])) {
        return true;
    }

    $resolved = sd_mock_resolve_post($post);

    return $resolved instanceof WP_Post && '' !== (string) $resolved->post_password;
}

/**
 * Format a fixture date with a WordPress-style format (private helper).
 *
 * @param string $format PHP date format.
 * @param string $raw    Raw `Y-m-d H:i:s` value.
 * @return string Formatted date, or '' when there is no date.
 */
function sd_mock_format_date($format, $raw)
{
    if ('' === (string) $raw) {
        return '';
    }

    $timestamp = strtotime((string) $raw);
    if (false === $timestamp) {
        return (string) $raw;
    }

    if ('' === (string) $format) {
        return (string) $raw;
    }

    return date((string) $format, $timestamp);
}

/**
 * Raw publication or modification date of a fixture post (private helper).
 *
 * La fecha de modificación cae a la de publicación cuando la entrada no
 * declara una, igual que las funciones de WordPress que se modelan: así una
 * implementación que omita `dateModified` comparando ambas obtiene el mismo
 * veredicto.
 *
 * @param WP_Post|int|null $post     Entrada, identificador o `null`.
 * @param bool             $modified Whether to read `post_modified`.
 * @return string Raw `Y-m-d H:i:s` value, or ''.
 */
function sd_mock_raw_date($post, $modified)
{
    $resolved = sd_mock_resolve_post($post);
    if (!$resolved instanceof WP_Post) {
        return '';
    }

    $raw = $modified ? (string) $resolved->post_modified : (string) $resolved->post_date;
    if ($modified && '' === $raw) {
        $raw = (string) $resolved->post_date;
    }

    return $raw;
}

/**
 * Publication date of a post (mirrors `get_the_date()`).
 *
 * @param string           $format PHP date format.
 * @param WP_Post|int|null $post   Entrada, identificador o `null`.
 * @return string
 */
function get_the_date($format = '', $post = null)
{
    return sd_mock_format_date($format, sd_mock_raw_date($post, false));
}

/**
 * Modification date of a post (mirrors `get_the_modified_date()`).
 *
 * @param string           $format PHP date format.
 * @param WP_Post|int|null $post   Entrada, identificador o `null`.
 * @return string
 */
function get_the_modified_date($format = '', $post = null)
{
    return sd_mock_format_date($format, sd_mock_raw_date($post, true));
}

/**
 * Publication time of a post (mirrors `get_post_time()`).
 *
 * @param string           $format PHP date format.
 * @param bool             $gmt    Whether to use the GMT date (ignored: the fixture holds no separate GMT value).
 * @param WP_Post|int|null $post   Entrada, identificador o `null`.
 * @return string
 */
function get_post_time($format = 'U', $gmt = false, $post = null)
{
    return sd_mock_format_date($format, sd_mock_raw_date($post, false));
}

/**
 * Modification time of a post (mirrors `get_post_modified_time()`).
 *
 * @param string           $format PHP date format.
 * @param bool             $gmt    Whether to use the GMT date (ignored).
 * @param WP_Post|int|null $post   Entrada, identificador o `null`.
 * @return string
 */
function get_post_modified_time($format = 'U', $gmt = false, $post = null)
{
    return sd_mock_format_date($format, sd_mock_raw_date($post, true));
}

/**
 * Convert a MySQL date to a PHP format (mirrors `mysql2date()`).
 *
 * @param string $format    PHP date format.
 * @param string $date      MySQL `Y-m-d H:i:s` value.
 * @param bool   $translate Whether to translate (ignored).
 * @return string|false
 */
function mysql2date($format, $date, $translate = true)
{
    if ('' === (string) $date) {
        return false;
    }

    $timestamp = strtotime((string) $date);

    return false === $timestamp ? false : date((string) $format, $timestamp);
}

/**
 * Site locale (mirrors `get_locale()`).
 *
 * Lo usa `SEO::addHreflang()`, que se dispara en `wp_head`.
 *
 * @return string
 */
function get_locale()
{
    return 'es_ES';
}

/**
 * Featured image identifier of a post (mirrors `get_post_thumbnail_id()`).
 *
 * Lee `_thumbnail_id` de `$GLOBALS['mock_post_meta']`, como el core guarda la
 * imagen destacada.
 *
 * @param WP_Post|int|null $post Entrada, identificador o `null`.
 * @return int|false
 */
function get_post_thumbnail_id($post = null)
{
    $resolved = sd_mock_resolve_post($post);
    if (!$resolved instanceof WP_Post) {
        return false;
    }

    $id = get_post_meta($resolved->ID, '_thumbnail_id', true);

    return '' === $id || null === $id ? false : (int) $id;
}

/**
 * Whether a post has a featured image (mirrors `has_post_thumbnail()`).
 *
 * @param WP_Post|int|null $post Entrada, identificador o `null`.
 * @return bool
 */
function has_post_thumbnail($post = null)
{
    return false !== get_post_thumbnail_id($post);
}

/**
 * Image source data of an attachment (mirrors `wp_get_attachment_image_src()`).
 *
 * Devuelve `[url, width, height]` leídos de `$GLOBALS['mock_attachments']`, o
 * `false` cuando el adjunto no está en la fixture: la implementación que lo
 * consulte omite la propiedad en vez de inventarla.
 *
 * @param int    $attachment_id Attachment identifier.
 * @param string $size          Image size (ignored).
 * @param bool   $icon          Whether to use an icon (ignored).
 * @return array|false
 */
function wp_get_attachment_image_src($attachment_id, $size = 'thumbnail', $icon = false)
{
    $attachment_id = (int) $attachment_id;
    if (!isset($GLOBALS['mock_attachments'][$attachment_id])) {
        return false;
    }

    $attachment = $GLOBALS['mock_attachments'][$attachment_id];
    $url = isset($attachment['url']) ? (string) $attachment['url'] : '';
    if ('' === $url) {
        return false;
    }

    return array(
        $url,
        isset($attachment['width']) ? (int) $attachment['width'] : 0,
        isset($attachment['height']) ? (int) $attachment['height'] : 0,
    );
}

/**
 * Absolute URL of an attachment (mirrors `wp_get_attachment_url()`).
 *
 * @param int $attachment_id Attachment identifier.
 * @return string|false
 */
function wp_get_attachment_url($attachment_id)
{
    $attachment_id = (int) $attachment_id;

    return isset($GLOBALS['mock_attachments'][$attachment_id]['url'])
        ? (string) $GLOBALS['mock_attachments'][$attachment_id]['url']
        : false;
}

/**
 * Metadata of an attachment (mirrors `wp_get_attachment_metadata()`).
 *
 * @param int  $attachment_id Attachment identifier.
 * @param bool $unfiltered    Whether to skip filters (ignored).
 * @return array|false
 */
function wp_get_attachment_metadata($attachment_id, $unfiltered = false)
{
    $attachment_id = (int) $attachment_id;
    if (!isset($GLOBALS['mock_attachments'][$attachment_id])) {
        return false;
    }

    $attachment = $GLOBALS['mock_attachments'][$attachment_id];
    if (!isset($attachment['width'], $attachment['height'])) {
        return false;
    }

    return array(
        'width' => (int) $attachment['width'],
        'height' => (int) $attachment['height'],
    );
}

/**
 * Site name, description, language and URL (mirrors `get_bloginfo()`).
 *
 * Los valores por defecto modelan el NÚCLEO, no la implementación: quien quiera
 * otro valor lo fija en `$GLOBALS['mock_bloginfo']`. Se incluye el idioma del
 * sitio (`es-ES`) porque el feed del podcast lo usa como valor por defecto
 * cuando no hay ajuste de idioma configurado.
 *
 * @param string $show   Requested field (`name`, `description`, `language`…).
 * @param string $filter Filter applied (ignored: the core returns the raw value).
 * @return string
 */
function get_bloginfo($show = '', $filter = 'raw')
{
    $defaults = array(
        'name' => 'atareao con Linux',
        'description' => 'Un podcast sobre Linux, software libre y desarrollo.',
        'language' => 'es-ES',
        'charset' => 'UTF-8',
        'url' => home_url(),
        'wpurl' => home_url(),
    );
    $bloginfo = isset($GLOBALS['mock_bloginfo']) && is_array($GLOBALS['mock_bloginfo'])
        ? $GLOBALS['mock_bloginfo']
        : array();
    $bloginfo = array_merge($defaults, $bloginfo);

    return isset($bloginfo[$show]) ? (string) $bloginfo[$show] : '';
}

/**
 * URL of a post type archive (mirrors `get_post_type_archive_link()`).
 *
 * Sin entrada explícita en `$GLOBALS['mock_archive_links']` se deriva del slug,
 * igual que el núcleo resuelve el archivo por sus reglas de reescritura.
 *
 * @param string $post_type Post type name.
 * @return string|false
 */
function get_post_type_archive_link($post_type)
{
    if (isset($GLOBALS['mock_archive_links'][$post_type])) {
        return (string) $GLOBALS['mock_archive_links'][$post_type];
    }

    return 'https://atareao.es/' . trim((string) $post_type, '/') . '/';
}

/**
 * URL of a post type archive feed (mirrors `get_post_type_archive_feed_link()`).
 *
 * Repliega a la URL del archivo más `feed/`, exactamente como el núcleo cuando
 * no hay una entrada explícita en `$GLOBALS['mock_archive_feed_links']`: ese
 * repliegue es justamente el valor por defecto del ajuste del feed del podcast.
 *
 * @param string $post_type Post type name.
 * @param string $feed      Feed name ('' for the default feed).
 * @return string|false
 */
function get_post_type_archive_feed_link($post_type, $feed = '')
{
    if (isset($GLOBALS['mock_archive_feed_links'][$post_type])) {
        return (string) $GLOBALS['mock_archive_feed_links'][$post_type];
    }

    return trailingslashit(get_post_type_archive_link($post_type)) . 'feed/';
}

/**
 * Filesystem path of an attachment (mirrors `get_attached_file()`).
 *
 * Devuelve `''` cuando el adjunto no está en la fixture, de modo que una
 * comprobación de accesibilidad no puede dar un falso positivo: el núcleo
 * devuelve `false` o la ruta, y aquí la ausencia se representa con la cadena
 * vacía, que `file_exists()`/`is_readable()` también rechazan.
 *
 * @param int  $attachment_id Attachment identifier.
 * @param bool $unfiltered    Whether to skip filters (ignored).
 * @return string
 */
function get_attached_file($attachment_id, $unfiltered = false)
{
    $attachment_id = (int) $attachment_id;

    return isset($GLOBALS['mock_attachments'][$attachment_id]['file'])
        ? (string) $GLOBALS['mock_attachments'][$attachment_id]['file']
        : '';
}

/**
 * Reverse lookup of an attachment by URL (mirrors `attachment_url_to_postid()`).
 *
 * @param string $url Attachment URL.
 * @return int Attachment identifier, or `0` when nothing matches.
 */
function attachment_url_to_postid($url)
{
    $attachments = isset($GLOBALS['mock_attachments']) && is_array($GLOBALS['mock_attachments'])
        ? $GLOBALS['mock_attachments']
        : array();
    foreach ($attachments as $attachment_id => $attachment) {
        if (isset($attachment['url']) && (string) $attachment['url'] === (string) $url) {
            return (int) $attachment_id;
        }
    }

    return 0;
}

/**
 * Append a trailing slash (mirrors `trailingslashit()`).
 *
 * @param string $string Input string.
 * @return string
 */
function trailingslashit($string)
{
    return rtrim((string) $string, '/\\') . '/';
}

/**
 * Absolute integer value (mirrors `absint()`).
 *
 * @param mixed $maybeint Input value.
 * @return int
 */
function absint($maybeint)
{
    return abs((int) $maybeint);
}

/**
 * Sanitise post content (mirrors `wp_kses_post()`).
 *
 * @param string $data Content.
 * @return string
 */
function wp_kses_post($data)
{
    return (string) $data;
}

/**
 * Query variable (mirrors `get_query_var()`).
 *
 * @param string $var     Variable name.
 * @param mixed  $default Value returned when the variable is absent.
 * @return mixed
 */
function get_query_var($var, $default = '')
{
    $vars = isset($GLOBALS['mock_query_vars']) && is_array($GLOBALS['mock_query_vars'])
        ? $GLOBALS['mock_query_vars']
        : array();

    return array_key_exists($var, $vars) ? $vars[$var] : $default;
}

/**
 * Whether the request is a post type archive (mirrors `is_post_type_archive()`).
 *
 * El escenario lo gobierna `$GLOBALS['mock_post_type_archive']` (`false`, una
 * cadena o un array). Con argumento compara contra él; sin argumento responde
 * si hay algún archivo de tipo de contenido activo, igual que el núcleo.
 *
 * @param string|string[] $post_type Post type(s) to check (optional).
 * @return bool
 */
function is_post_type_archive($post_type = '')
{
    $current = isset($GLOBALS['mock_post_type_archive']) ? $GLOBALS['mock_post_type_archive'] : false;
    if ('' === $post_type || array() === $post_type) {
        return !empty($current);
    }

    $current_types = is_array($current) ? $current : array($current);
    $wanted = is_array($post_type) ? $post_type : array($post_type);
    foreach ($wanted as $type) {
        if (in_array($type, $current_types, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Persist a post meta value (mirrors `update_post_meta()`).
 *
 * Escribe con la MISMA forma que lee `get_post_meta()` (`$meta[$post_id][$key]`)
 * para que el guardado de la caja y la lectura del feed compartan el almacén en
 * memoria. Devuelve `false` cuando no hay clave, como el núcleo.
 *
 * @param int    $post_id    Post identifier.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Value to store.
 * @param mixed  $prev_value Previous value required (ignored).
 * @return bool
 */
function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
{
    if ('' === (string) $meta_key) {
        return false;
    }

    $GLOBALS['mock_post_meta'][(int) $post_id][$meta_key] = $meta_value;

    return true;
}

/**
 * Author meta of a user (mirrors `get_the_author_meta()`).
 *
 * Sin `$user_id` cae al autor de la entrada consultada, como en el bucle. Lee
 * de `$GLOBALS['mock_authors']` y devuelve `''` cuando no hay autor, de modo
 * que la vía de respaldo no inventa datos.
 *
 * @param string    $field   Meta field (`display_name`, `url`…).
 * @param int|false $user_id Author identifier, or false for the queried post's author.
 * @return string
 */
function get_the_author_meta($field = '', $user_id = false)
{
    if (false === $user_id || '' === $user_id) {
        $post = get_queried_object();
        $user_id = $post instanceof WP_Post ? (int) $post->post_author : 0;
    }

    $user_id = (int) $user_id;
    if (!isset($GLOBALS['mock_authors'][$user_id])) {
        return '';
    }

    $author = $GLOBALS['mock_authors'][$user_id];

    return isset($author[$field]) ? (string) $author[$field] : '';
}

/**
 * Author archive URL (mirrors `get_author_posts_url()`).
 *
 * @param int    $author_id       Author identifier.
 * @param string $author_nicename Author nicename (ignored).
 * @return string
 */
function get_author_posts_url($author_id, $author_nicename = '')
{
    $author_id = (int) $author_id;

    return isset($GLOBALS['mock_authors'][$author_id]['url'])
        ? (string) $GLOBALS['mock_authors'][$author_id]['url']
        : '';
}

/**
 * User data of an author (mirrors `get_userdata()`).
 *
 * @param int $user_id Author identifier.
 * @return object|false
 */
function get_userdata($user_id)
{
    $user_id = (int) $user_id;
    if (!isset($GLOBALS['mock_authors'][$user_id])) {
        return false;
    }

    $author = $GLOBALS['mock_authors'][$user_id];

    return (object) array(
        'ID' => $user_id,
        'display_name' => isset($author['display_name']) ? (string) $author['display_name'] : '',
        'user_url' => isset($author['url']) ? (string) $author['url'] : '',
        'user_nicename' => isset($author['nicename']) ? (string) $author['nicename'] : 'user-' . $user_id,
    );
}

/**
 * Whether the current page uses a template.
 *
 * @param string $template Template name.
 * @return bool
 */
function is_page_template($template)
{
    return true;
}

/**
 * Whether a string looks like an email address.
 *
 * @param string $email Candidate email.
 * @return bool
 */
function is_email($email)
{
    // Fidelity matters here: the contact-form branch only fires for a value
    // that contains '@' *and* is not a valid address, so the check must reject
    // malformed candidates such as 'user@' instead of accepting any '@'.
    return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Read a transient from the in-memory store.
 *
 * @param string $key Transient key.
 * @return mixed
 */
function get_transient($key)
{
    $transients = isset($GLOBALS['mock_transients']) ? $GLOBALS['mock_transients'] : array();

    return isset($transients[$key]) ? $transients[$key] : false;
}

/**
 * Write a transient to the in-memory store.
 *
 * @param string $key        Transient key.
 * @param mixed  $value      Transient value.
 * @param int    $expiration TTL in seconds.
 * @return bool Always true.
 */
function set_transient($key, $value, $expiration = 0)
{
    $GLOBALS['mock_transients'][$key] = $value;

    return true;
}

/**
 * Verify a nonce with a configurable result.
 *
 * @param string $nonce  Nonce value.
 * @param string $action Nonce action.
 * @return bool
 */
function wp_verify_nonce($nonce, $action)
{
    return !isset($GLOBALS['mock_verify_nonce_result'])
        || $GLOBALS['mock_verify_nonce_result'] !== false;
}

/**
 * Salt used for HMAC and hashing (fixed in tests).
 *
 * @param string $scheme Salt scheme.
 * @return string
 */
function wp_salt($scheme = 'auth')
{
    return 'test-salt-secret-key';
}

/**
 * Remove slashes added by WordPress.
 *
 * @param mixed $value Raw value.
 * @return mixed
 */
function wp_unslash($value)
{
    return is_string($value) ? stripslashes($value) : $value;
}

/**
 * Add query arguments to a URL (mirrors add_query_arg()).
 *
 * Supports both `add_query_arg( $key, $value, $url )` and
 * `add_query_arg( array $args, $url )`.
 *
 * @param mixed ...$args Variadic arguments.
 * @return string
 */
function add_query_arg(...$args)
{
    if (count($args) < 2) {
        return '';
    }

    if (is_array($args[0])) {
        $query = $args[0];
        $url = (string) $args[1];
    } else {
        $query = array($args[0] => $args[1]);
        $url = isset($args[2]) ? (string) $args[2] : '';
    }

    $separator = (strpos($url, '?') === false) ? '?' : '&';

    return $url . $separator . http_build_query($query);
}

/**
 * Record a redirect instead of performing it.
 *
 * @param string $location Target URL.
 * @return void
 */
function wp_safe_redirect($location)
{
    $GLOBALS['last_redirect'] = $location;
}

/**
 * Register a filter callback (WordPress semantics, simplified).
 *
 * @param string   $hook_name     Filter name.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority (recorded, not sorted).
 * @param int      $accepted_args Accepted argument count.
 * @return void
 */
function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['mock_filters'][$hook_name][] = array(
        'callback' => $callback,
        'priority' => $priority,
        'args' => $accepted_args,
    );
}

/**
 * Whether a hook has a callback registered (WordPress semantics).
 *
 * Mirrors `has_filter()`: with no callback it answers whether the hook has any
 * callback at all; with a callback it answers whether that exact callable is
 * registered. Used by the suite to measure the *registered* `rest_endpoints`
 * hook of the plugin bootstrap instead of re-registering it by hand.
 *
 * @param string          $hook_name Hook name.
 * @param callable|false  $callback  Optional. Callback to check for.
 * @return bool True when the hook (or the callback) is registered.
 */
function has_filter($hook_name, $callback = false)
{
    $filters = isset($GLOBALS['mock_filters'][$hook_name]) ? $GLOBALS['mock_filters'][$hook_name] : array();

    foreach ($filters as $filter) {
        if (false === $callback) {
            return true;
        }

        if (isset($filter['callback']) && $filter['callback'] === $callback) {
            return true;
        }
    }

    return false;
}

/**
 * Apply the filters registered for a hook.
 *
 * With no callbacks registered (the default) this is the identity, exactly as
 * in production when no plugin has hooked the filter. The harness uses this
 * to prove the rate-limit filters are honoured.
 *
 * @param string $hook_name Filter name.
 * @param mixed  $value     Value to filter.
 * @param mixed  ...$args   Extra arguments passed to the callback.
 * @return mixed Filtered value.
 */
function apply_filters($hook_name, $value, ...$args)
{
    $filters = isset($GLOBALS['mock_filters'][$hook_name]) ? $GLOBALS['mock_filters'][$hook_name] : array();

    foreach ($filters as $filter) {
        $value = call_user_func_array(
            $filter['callback'],
            array_merge(array($value), $args)
        );
    }

    return $value;
}

/**
 * Print a success value (not exercised by these tests).
 *
 * @param mixed $data Response data.
 * @return void
 */
function wp_send_json_success($data)
{
    echo json_encode(array('success' => true, 'data' => $data));
}

/**
 * Print an error value (not exercised by these tests).
 *
 * @param mixed $data Response data.
 * @return void
 */
function wp_send_json_error($data)
{
    echo json_encode(array('success' => false, 'data' => $data));
}

/**
 * Assert that a condition is true.
 *
 * @param bool   $condition   Condition under test.
 * @param string $description Human readable description.
 * @return void
 */
function assert_true($condition, $description)
{
    if ($condition) {
        $GLOBALS['passes']++;
        echo "  PASS: $description\n";

        return;
    }

    $GLOBALS['failures'][] = $description;
    echo "  FAIL: $description\n";
}

/**
 * Assert that two values are identical.
 *
 * @param mixed  $expected    Expected value.
 * @param mixed  $actual      Actual value.
 * @param string $description Human readable description.
 * @return void
 */
function assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        $GLOBALS['passes']++;
        echo "  PASS: $description\n";

        return;
    }

    $message = $description . ' (Expected: ' . var_export($expected, true)
        . ', got: ' . var_export($actual, true) . ')';
    $GLOBALS['failures'][] = $message;
    echo "  FAIL: $message\n";
}

/**
 * Enable WordPress debug mode for the running test process.
 *
 * WordPress normally defines WP_DEBUG in wp-config.php before any plugin
 * code runs, so the harness must be able to flip it on at runtime. Kept in a
 * helper so the `define()` call lives inside a function body instead of at the
 * top level of a script that also has side effects (PSR1.Files.SideEffects).
 *
 * @return void
 */
function harness_enable_wp_debug()
{
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
}

/**
 * Reset every piece of mock state to a known baseline.
 *
 * @return void
 */
function harness_reset_state()
{
    $GLOBALS['mock_options'] = array();
    $GLOBALS['mock_remote_requests'] = array();
    $GLOBALS['mock_remote_response'] = array(
        'response' => array('code' => 201),
        'body' => '{"id":"test-uuid"}',
    );
    $GLOBALS['mock_actions'] = array();
    $GLOBALS['mock_comments'] = array();
    $GLOBALS['mock_comment_meta'] = array();
    /*
     * Fixture de entradas y de sus metadatos: la ruta `get_latest_posts` pasa
     * por `WP_Query`, que lee `$GLOBALS['mock_posts']`.
     */
    $GLOBALS['mock_posts'] = array();
    $GLOBALS['mock_post_meta'] = array();
    /*
     * Estado de la consulta principal para los dobles de plantilla. Todos los
     * escenarios parten de una petición sin contexto: sin singular, sin objeto
     * consultado, sin adjuntos ni autores en la fixture.
     */
    $GLOBALS['mock_singular'] = false;
    $GLOBALS['mock_front_page'] = false;
    $GLOBALS['mock_home'] = false;
    $GLOBALS['mock_archive'] = false;
    $GLOBALS['mock_search'] = false;
    $GLOBALS['mock_is_404'] = false;
    $GLOBALS['mock_is_feed'] = false;
    $GLOBALS['mock_queried_object'] = null;
    $GLOBALS['mock_password_required'] = false;
    $GLOBALS['mock_attachments'] = array();
    $GLOBALS['mock_authors'] = array();
    /*
     * Datos del sitio y de los archivos de tipo de contenido. Se parten del
     * valor por defecto del núcleo (idioma `es-ES`, URL del archivo derivada del
     * slug) y `mock_post_type_archive` a `false` (ninguna petición es archivo).
     */
    $GLOBALS['mock_bloginfo'] = array();
    $GLOBALS['mock_archive_links'] = array();
    $GLOBALS['mock_archive_feed_links'] = array();
    $GLOBALS['mock_query_vars'] = array();
    $GLOBALS['mock_post_type_archive'] = false;
    /*
     * Comments destroyed through `wp_delete_comment( $id, true )`. The bare-core
     * retirement simulator records here every destructive deletion, so a
     * scenario can prove the plugin's `force` degradation kept the branch off.
     */
    $GLOBALS['mock_deleted_comments'] = array();
    $GLOBALS['mock_current_user_id'] = 0;
    $GLOBALS['mock_transients'] = array();
    $GLOBALS['mock_matrix_calls'] = array();
    $GLOBALS['mock_matrix_send_result'] = true;
    /*
     * The plugin bootstrap registers the `rest_endpoints` filter at load time
     * (`atareao-functionality.php:204`). Preserve exactly that registration
     * across resets so the suite measures the *site's* hook instead of a
     * hand-registered copy: deleting the production `add_filter()` must turn
     * the suite red. Every other hook is wiped, as before, so scenarios that
     * rely on a clean filter table keep their behaviour.
     */
    $registered_endpoints_filters = isset($GLOBALS['mock_filters']['rest_endpoints'])
        ? $GLOBALS['mock_filters']['rest_endpoints']
        : null;
    $GLOBALS['mock_filters'] = array();
    if (null !== $registered_endpoints_filters) {
        $GLOBALS['mock_filters']['rest_endpoints'] = $registered_endpoints_filters;
    }
    $GLOBALS['mock_error_log_file'] = null;
    $GLOBALS['last_redirect'] = null;
    $GLOBALS['mock_verify_nonce_result'] = true;
    $GLOBALS['mock_admin_nonce_valid'] = true;
    $GLOBALS['mock_user_can_manage_options'] = true;
    $GLOBALS['passes'] = 0;
    $GLOBALS['failures'] = array();
}

/**
 * Build the POST payload and options for a contact-form child scenario.
 *
 * @param string $scenario Scenario identifier.
 * @return array{options:array,post:array,verify_nonce:bool}
 */
function harness_child_config($scenario)
{
    $captcha_a = 2;
    $captcha_b = 3;
    $form_time = time() - 10;

    $post = array(
        'atareao_contact_form' => '1',
        'atareao_contact_nonce' => 'valid_nonce',
        'contact_name_email' => 'user@example.com',
        'contact_content' => 'Hola, esto es una prueba para Valet.',
        'atareao_website' => '',
        'atareao_captcha_answer' => '5',
        'atareao_captcha_a' => (string) $captcha_a,
        'atareao_captcha_b' => (string) $captcha_b,
        'atareao_captcha_sig' => '',
        'atareao_form_time' => (string) $form_time,
    );

    $options = array(
        'atareao_matrix_url' => '',
        'atareao_matrix_token' => '',
        'atareao_matrix_room' => '',
        'atareao_valet_url' => 'https://valet.myhost.local',
        'atareao_valet_token' => 'my-secret-key-456',
    );

    $verify_nonce = true;

    if ('valet_and_matrix_success' === $scenario) {
        $options['atareao_matrix_url'] = 'https://matrix.myhost.local';
        $options['atareao_matrix_token'] = 'matrix-token';
        $options['atareao_matrix_room'] = '!room:myhost.local';
    } elseif ('valet_failure_matrix_success' === $scenario) {
        // Matrix succeeds and Valet fails: the Valet failure must not be silent.
        $options['atareao_matrix_url'] = 'https://matrix.myhost.local';
        $options['atareao_matrix_token'] = 'matrix-token';
        $options['atareao_matrix_room'] = '!room:myhost.local';
    } elseif ('long_contact_message' === $scenario) {
        $post['contact_name_email'] = str_repeat('n', 200);
        $post['contact_content'] = str_repeat('a', 5000);
    } elseif ('long_contact_name_message' === $scenario) {
        // A sender long enough that the *prologue alone* exhausts the byte
        // budget, exercising the contact-form route of composeMessage()'s
        // else branch (the user body is dropped).
        $post['contact_name_email'] = str_repeat('n', 4200);
        $post['contact_content'] = 'CUERPO_DE_CONTACTO_QUE_NO_DEBE_APARECER';
    } elseif ('invalid_nonce' === $scenario) {
        $verify_nonce = false;
    } elseif ('honeypot_filled' === $scenario) {
        $post['atareao_website'] = 'http://spam.example';
    } elseif ('bad_captcha' === $scenario) {
        $post['atareao_captcha_answer'] = '99';
    } elseif ('no_channel_configured' === $scenario) {
        // No Matrix nor Valet credentials: no dispatch channel is available.
        $options['atareao_valet_url'] = '';
        $options['atareao_valet_token'] = '';
    } elseif ('rate_limited' === $scenario) {
        // The over-limit transient is seeded in harness_run_child_scenario().
    } elseif ('empty_fields' === $scenario) {
        $post['contact_content'] = '';
    } elseif ('invalid_email' === $scenario) {
        // Contains '@' yet is not a valid address: the is_email() branch fires.
        $post['contact_name_email'] = 'user@';
    } elseif ('spam_keyword' === $scenario) {
        $post['contact_content'] = 'Compra en casino ahora mismo';
    } elseif ('link_only' === $scenario) {
        $post['contact_content'] = 'https://spam.example/promo';
    } elseif ('form_expired' === $scenario) {
        $form_time = 0;
        $post['atareao_form_time'] = '0';
    } elseif ('form_too_fast' === $scenario) {
        $form_time = time();
        $post['atareao_form_time'] = (string) $form_time;
    }

    // The HMAC must match the final operands/time, including the branches that
    // move the form timestamp, so it is signed once at the end.
    $post['atareao_captcha_sig'] = hash_hmac(
        'sha256',
        $captcha_a . ':' . $captcha_b . ':' . $form_time,
        wp_salt('nonce')
    );

    return array(
        'options' => $options,
        'post' => $post,
        'verify_nonce' => $verify_nonce,
    );
}

/**
 * Emit the observed child state as one JSON line on shutdown.
 *
 * Registered as a shutdown function so it runs after the `exit` performed by
 * the code under test (ContactForm::handleSubmission()).
 *
 * @return void
 */
function harness_child_report()
{
    $requests = array();
    if (isset($GLOBALS['mock_remote_requests'])) {
        foreach ($GLOBALS['mock_remote_requests'] as $request) {
            $requests[] = array(
                'url' => isset($request['url']) ? $request['url'] : '',
                'body' => isset($request['args']['body']) ? $request['args']['body'] : '',
            );
        }
    }

    $payload = array(
        'redirect' => isset($GLOBALS['last_redirect']) ? $GLOBALS['last_redirect'] : null,
        'valet_requests' => $requests,
        'matrix_calls' => isset($GLOBALS['mock_matrix_calls']) ? $GLOBALS['mock_matrix_calls'] : array(),
        'error_log' => '',
    );

    if (isset($GLOBALS['mock_error_log_file']) && is_file($GLOBALS['mock_error_log_file'])) {
        $payload['error_log'] = (string) file_get_contents($GLOBALS['mock_error_log_file']);
        // The child created this file only to hand its error_log over; remove
        // it now that the content has been captured (no orphan temp files).
        @unlink($GLOBALS['mock_error_log_file']);
    }

    echo "\n###CHILD_JSON###" . json_encode($payload) . "\n";
}

/**
 * Run one contact-form scenario in this process and exit.
 *
 * @param string $scenario Scenario identifier.
 * @return void
 */
function harness_run_child_scenario($scenario)
{
    harness_reset_state();
    $config = harness_child_config($scenario);

    foreach ($config['options'] as $key => $value) {
        update_option($key, $value);
    }

    if ('rate_limited' === $scenario) {
        // Seed the fixed-window counter over the limit. The key embeds a
        // time bucket, so both the current and the next bucket are primed to
        // avoid a flake if the hour boundary is crossed mid-call.
        $bucket = (int) floor(time() / 3600);
        $hash = hash('sha256', '127.0.0.1|' . wp_salt('nonce'));
        set_transient('atareao_contact_rl_' . $hash . '_' . $bucket, 5, 7200);
        set_transient('atareao_contact_rl_' . $hash . '_' . ($bucket + 1), 5, 7200);
    }

    $GLOBALS['mock_verify_nonce_result'] = $config['verify_nonce'];
    $GLOBALS['mock_remote_response'] = array(
        'response' => array('code' => 201),
        'body' => '{"id":"child-request"}',
    );

    // Capture error_log output so the parent process can assert on it.
    $error_log_file = tempnam(sys_get_temp_dir(), 'valet-child-log-');
    if (false !== $error_log_file) {
        ini_set('log_errors', '1');
        ini_set('error_log', $error_log_file);
        $GLOBALS['mock_error_log_file'] = $error_log_file;
    }

    if ('valet_failure_matrix_success' === $scenario) {
        harness_enable_wp_debug();
        $GLOBALS['mock_remote_response'] = array(
            'response' => array('code' => 500),
            'body' => '{"error":"valet down"}',
        );
    }

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_POST = $config['post'];

    register_shutdown_function('harness_child_report');

    \Atareao\ContactForm::handleSubmission();

    exit(0);
}

/**
 * Re-invoke the test script as a child process and decode its JSON report.
 *
 * @param string $script   Absolute path to the test script.
 * @param string $scenario Scenario identifier.
 * @return array|null Decoded report, or null when the child failed.
 */
/**
 * Transient entries whose key starts with a prefix.
 *
 * Window counters are keyed by a salted hash, so a test cannot build the key
 * by hand without re-implementing it. This helper lets the assertions work on
 * the counter the code under test actually wrote (its value, and the shape of
 * its key) without duplicating the derivation.
 *
 * @param string $prefix Key prefix.
 * @return array<string,mixed> Matching `key => value` pairs.
 */
function harness_transients_with_prefix($prefix)
{
    $transients = isset($GLOBALS['mock_transients']) ? $GLOBALS['mock_transients'] : array();
    $matches = array();

    foreach ($transients as $key => $value) {
        if (strpos((string) $key, (string) $prefix) === 0) {
            $matches[$key] = $value;
        }
    }

    return $matches;
}

/**
 * Build the REST route table with the *real* shape the `rest_endpoints` filter
 * receives — the one `WP_REST_Server` hands over, NOT an invented
 * `route => method => handler` map.
 *
 * The shape comes from the core, line by line:
 *
 * - `register_route()` stores the route args as they arrive and merges on
 *   re-registration: `$this->endpoints[ $route ] = $route_args;` /
 *   `array_merge( $this->endpoints[ $route ], $route_args )`
 *   (`wp-includes/rest-api/class-wp-rest-server.php:928-932`). The route entry
 *   is therefore an **indexed array of handler groups**, and the HTTP method
 *   lives **inside** each group in its `methods` field — never in the group's
 *   key. `register_route()` also appends the `namespace` route option (`:927`).
 * - `get_routes()` applies the filter **on that shape** (`:974`) and only
 *   afterwards normalises it: non-numeric keys are moved to route options and
 *   dropped from the handler list (`:998`), numeric keys keep a handler group
 *   whose `methods` may be a comma-separated string (`explode` at `:1009`) or
 *   an array (`:1010`).
 * - `WP_REST_Comments_Controller::register_routes()` registers exactly that:
 *   collection `[0 => methods 'GET', 1 => methods 'POST', 'schema' => …,
 *   'namespace' => 'wp/v2']` (`class-wp-rest-comments-controller.php:48-63`)
 *   and single item `['args' => …, 0 => 'GET', 1 => 'POST, PUT, PATCH',
 *   2 => 'DELETE', 'schema' => …]` (`:68-99`). The comma list of the item's
 *   second group is `WP_REST_Server::EDITABLE`; the collection's second group
 *   is `WP_REST_Server::CREATABLE` = `'POST'` (`class-wp-rest-server.php:32,40`).
 * - The item's third group (`WP_REST_Server::DELETABLE`, `'DELETE'`) declares
 *   its own `args` — including `force` with `'default' => false` (`:101-104`),
 *   the value `match_request_to_handler()` copies into the request defaults and
 *   `delete_item()` reads back at `:1071`. Route-level `args` (`id`) are route
 *   options the matcher never reads: the handler defaults come from the group.
 *   That group also carries its real `permission_callback`
 *   (`delete_item_permissions_check`, `:99`), so the core's permission layer is
 *   part of the modelled route rather than a hand-wired shortcut; the other
 *   groups keep the neutral placeholder because no scenario measures theirs.
 *
 * Sharing this builder keeps the collection scenario (the surface decision)
 * and the single-item scenario (the reversible deletion contract) describing
 * the same table the core filters.
 *
 * @return array<string, array<int|string, mixed>> Route table.
 */
function harness_rest_route_table()
{
    $handler = static function () {
        return null;
    };

    $group = static function ($methods, $args = array(), $permission_callback = null) use ($handler) {
        return array(
            'methods'             => $methods,
            'callback'            => $handler,
            'permission_callback' => null === $permission_callback ? $handler : $permission_callback,
            'args'                => (array) $args,
        );
    };

    return array(
        '/wp/v2/comments' => array(
            0           => $group('GET'),
            1           => $group('POST'),
            'schema'    => $handler,
            'namespace' => 'wp/v2',
        ),
        '/wp/v2/comments/(?P<id>[\d]+)' => array(
            'args'      => array('id' => array('type' => 'integer')),
            0           => $group('GET'),
            1           => $group('POST, PUT, PATCH'),
            2           => $group(
                'DELETE',
                array(
                    'force'    => array(
                        'type'    => 'boolean',
                        'default' => false,
                    ),
                    'password' => array('type' => 'string'),
                ),
                /*
                 * The DELETE group is registered with its REAL permission
                 * callback in the core: `'permission_callback' => array( $this,
                 * 'delete_item_permissions_check' )`
                 * (`class-wp-rest-comments-controller.php:99`). Modelling it is
                 * what lets a scenario measure the layer the plugin's guard
                 * precedes, without inventing a placeholder.
                 */
                'harness_rest_delete_item_permissions_check'
            ),
            'schema'    => $handler,
            'namespace' => 'wp/v2',
        ),
    );
}

/**
 * Translate a route entry to the dispatchable map the core derives from it.
 *
 * Mirrors the normalisation `get_routes()` runs **after** the filter
 * (`class-wp-rest-server.php:985-1013`): it walks only the numeric keys, treats
 * `methods` as a comma list when it is a string (`:1009`) and as-is when it is
 * an array (`:1010`), uppercases and trims each entry, and returns a
 * `METHOD => true` map. Non-numeric keys are route options and are ignored, so
 * the result is what `serve_request()` would actually dispatch.
 *
 * @param array|null $route Route entry (handler groups plus route options).
 * @return array<string, true> Dispatchable methods (e.g. `['POST' => true]`).
 */
function harness_rest_dispatchable_methods($route)
{
    if (!is_array($route)) {
        return array();
    }

    $dispatchable = array();
    foreach ($route as $key => $group) {
        if (!is_numeric($key) || !is_array($group) || !array_key_exists('methods', $group)) {
            continue;
        }

        $methods = $group['methods'];
        if (is_string($methods)) {
            $methods = explode(',', $methods);
        } elseif (!is_array($methods)) {
            $methods = array();
        }

        foreach ($methods as $method) {
            $dispatchable[strtoupper(trim((string) $method))] = true;
        }
    }

    return $dispatchable;
}

/**
 * Number of numeric handler groups in a route entry.
 *
 * Non-numeric keys are route options (they are not handler groups), matching
 * the branch `get_routes()` takes at `class-wp-rest-server.php:998`.
 *
 * @param array|null $route Route entry.
 * @return int Group count (0 when the entry is not an array).
 */
function harness_rest_handler_group_count($route)
{
    if (!is_array($route)) {
        return 0;
    }

    $count = 0;
    foreach (array_keys($route) as $key) {
        if (is_numeric($key)) {
            $count++;
        }
    }

    return $count;
}

/**
 * Raw entry left on the comment collection route of a route table.
 *
 * @param array $table Route table (typically the filtered one).
 * @return array|null Route entry, or null when the route is absent.
 */
function harness_rest_collection_route($table)
{
    if (!isset($table['/wp/v2/comments']) || !is_array($table['/wp/v2/comments'])) {
        return null;
    }

    return $table['/wp/v2/comments'];
}

/**
 * Methods the core would dispatch on the comment collection route.
 *
 * @param array $table Route table (typically the filtered one).
 * @return array<string, true>|null Dispatchable map, or null when absent.
 */
function harness_rest_collection_handlers($table)
{
    $route = harness_rest_collection_route($table);
    if (null === $route) {
        return null;
    }

    return harness_rest_dispatchable_methods($route);
}

/**
 * Methods the core would dispatch on the single comment route.
 *
 * @param array $table Route table (typically the filtered one).
 * @return array<string, true>|null Dispatchable map, or null when absent.
 */
function harness_rest_item_handlers($table)
{
    if (!isset($table['/wp/v2/comments/(?P<id>[\d]+)'])
        || !is_array($table['/wp/v2/comments/(?P<id>[\d]+)'])
    ) {
        return null;
    }

    return harness_rest_dispatchable_methods($table['/wp/v2/comments/(?P<id>[\d]+)']);
}

/**
 * Whether a media type is JSON (mirrors `wp_is_json_media_type()`).
 *
 * The core accepts `application/json`, `application/ld+json` and every allowed
 * MIME type that carries the `+json` suffix. The harness resolves the fixed pair
 * and the `+json` suffix without consulting `get_allowed_mime_types()`, which is
 * not stubbed; that is the only approximation here.
 *
 * @param string $media_type Media type.
 * @return bool
 */
function harness_wp_is_json_media_type($media_type)
{
    if (!is_string($media_type) || '' === $media_type) {
        return false;
    }

    if (in_array($media_type, array('application/json', 'application/ld+json'), true)) {
        return true;
    }

    return false !== strpos($media_type, '+json');
}

/**
 * Reversibility window of the trash, in days.
 *
 * This repository never defines `EMPTY_TRASH_DAYS`, so the value is the core
 * default of 30 days (`wp-includes/default-constants.php:387-388`). Resolved
 * through a helper so a scenario could define the constant on purpose and check
 * the `501 rest_trash_not_supported` branch.
 *
 * @return int
 */
function harness_empty_trash_days()
{
    return defined('EMPTY_TRASH_DAYS') ? (int) EMPTY_TRASH_DAYS : 30;
}

/**
 * Resolve a single comment the way the controller does (or its error).
 *
 * Mirrors `WP_REST_Comments_Controller::get_comment()`
 * (`class-wp-rest-comments-controller.php`): an unknown identifier is the
 * documented `404 rest_comment_invalid_id`, not null.
 *
 * @param mixed $id Comment identifier.
 * @return object|WP_Error Comment object, or the invalid-id error.
 */
function harness_rest_get_comment($id)
{
    $comment = get_comment((int) $id);
    if (null === $comment) {
        return new WP_Error('rest_comment_invalid_id', 'Invalid comment ID.', array('status' => 404));
    }

    return $comment;
}

/**
 * Status code for an authorization failure (mirrors `rest_authorization_required_code()`).
 *
 * `wp-includes/rest-api.php`: `return is_user_logged_in() ? 403 : 401;`. This is
 * the status the core puts on `rest_cannot_delete` when
 * `delete_item_permissions_check()` refuses an authenticated identity.
 *
 * @return int 403 for an authenticated request, 401 for an anonymous one.
 */
function harness_rest_authorization_required_code()
{
    return is_user_logged_in() ? 403 : 401;
}

/**
 * Whether the identity may edit or delete that comment.
 *
 * Mirrors `WP_REST_Comments_Controller::check_edit_permission()`
 * (`class-wp-rest-comments-controller.php:1977-1987`): an anonymous request is
 * refused first, then `moderate_comments` grants, and failing that
 * `edit_comment` on the comment is the fallback.
 *
 * @param object $comment Comment object.
 * @return bool Whether the current identity may edit or delete it.
 */
function harness_check_edit_permission($comment)
{
    if (0 === (int) get_current_user_id()) {
        return false;
    }

    if (current_user_can('moderate_comments')) {
        return true;
    }

    return current_user_can('edit_comment', $comment->comment_ID);
}

/**
 * Simulate `WP_REST_Comments_Controller::delete_item_permissions_check()`.
 *
 * Mirrors (`class-wp-rest-comments-controller.php:1041-1057`) literally: the
 * comment is resolved first — so an unknown identifier keeps resolving the
 * `404 rest_comment_invalid_id` — and only then the identity is checked, with
 * `rest_cannot_delete` (`:1017-1021`) at the status
 * `rest_authorization_required_code()` computes.
 *
 * This is the layer the plugin's provenance guard deliberately **precedes**:
 * `rest_request_before_callbacks` runs before this `permission_callback`
 * (`class-wp-rest-server.php:1261` and `:1264-1275`, verified on
 * `wordpress:6.9-branch`). With the service identity the retirement guard
 * answers first with its own code; with an authenticated identity that cannot
 * moderate, this double answers `rest_cannot_delete`.
 *
 * @param WP_REST_Request $request Request whose `id` resolves the comment.
 * @return true|WP_Error True when allowed, the core error otherwise.
 */
function harness_rest_delete_item_permissions_check($request)
{
    $comment = harness_rest_get_comment($request['id']);
    if (is_wp_error($comment)) {
        return $comment;
    }

    if (!harness_check_edit_permission($comment)) {
        return new WP_Error(
            'rest_cannot_delete',
            'Sorry, you are not allowed to delete this comment.',
            array('status' => harness_rest_authorization_required_code())
        );
    }

    return true;
}

/**
 * Run the matched handler's `permission_callback` exactly where the core does.
 *
 * Mirrors `WP_REST_Server::respond_to_request()`
 * (`class-wp-rest-server.php:1264-1275`): after the
 * `rest_request_before_callbacks` filter and before the controller callback
 * (`:1298`). A `WP_Error` short-circuits with that error; `false`/`null`
 * resolves the core's generic `rest_forbidden` (401).
 *
 * @param array           $handler Matched handler group.
 * @param WP_REST_Request $request Matched request.
 * @return WP_Error|null The short-circuiting error, or null when allowed.
 */
function harness_rest_permission_check($handler, $request)
{
    if (!is_array($handler) || empty($handler['permission_callback'])) {
        return null;
    }

    $permission = call_user_func($handler['permission_callback'], $request);

    if (is_wp_error($permission)) {
        return $permission;
    }

    if (false === $permission || null === $permission) {
        return new WP_Error(
            'rest_forbidden',
            'Sorry, you are not allowed to do that.',
            array('status' => 401)
        );
    }

    return null;
}

/**
 * Approximate `prepare_item_for_response()` for the comment fields the contract
 * observes.
 *
 * The harness only needs the identity, the post, the author, the raw content and
 * the `status` field (which is the stored `comment_approved`), so this is a
 * deliberately narrow projection, not the full REST schema.
 *
 * @param object $comment Comment object.
 * @return array Prepared projection.
 */
function harness_rest_prepare_comment($comment)
{
    return array(
        'id'          => (int) $comment->comment_ID,
        'post'        => (int) $comment->comment_post_ID,
        'author_name' => isset($comment->comment_author) ? $comment->comment_author : '',
        'content'     => array('raw' => isset($comment->comment_content) ? $comment->comment_content : ''),
        'status'      => (string) $comment->comment_approved,
    );
}

/**
 * Simulate `WP_REST_Comments_Controller::delete_item()` — the *bare* core path.
 *
 * Reproduces the single-item `DELETE`, line by line from
 * `class-wp-rest-comments-controller.php`, **without** the plugin's retirement
 * guard, so a scenario can apply `harness_apply_rest_request_before_callbacks()`
 * first and measure exactly the delta the site introduces:
 *
 * - unknown identifier → `404 rest_comment_invalid_id` (`:1066-1069`);
 * - `$force = isset( $request['force'] ) ? (bool) $request['force'] : false`
 *   (`:1071`): with `force` true the previous representation is captured and
 *   `wp_delete_comment( $id, true )` runs, answering
 *   `{deleted:true, previous:…}` (`:1087-1089`);
 * - otherwise the trash branch: `501 rest_trash_not_supported` when
 *   `rest_comment_trashable` is false (`:1099-1105`), `410 rest_already_trashed`
 *   when the comment is already in the trash (`:1108-1113`), and
 *   `wp_trash_comment()` → `200` with the trashed comment (`:1116-1118`).
 *
 * The return contract is a plain array `{status, data}` (the double has no
 * `WP_REST_Response` with `get_data()`); errors are `WP_Error` instances, whose
 * status lives in `get_error_data()['status']`.
 *
 * @param WP_REST_Request $request Full request (reads `id` and `force` through
 *                                the bucket order, as the controller does).
 * @return array|WP_Error `{status, data}` on success, `WP_Error` on failure.
 */
function harness_rest_delete_comment($request)
{
    $comment = harness_rest_get_comment($request['id']);
    if (is_wp_error($comment)) {
        return $comment;
    }

    $force = isset($request['force']) ? (bool) $request['force'] : false;

    $supports_trash = apply_filters('rest_comment_trashable', (harness_empty_trash_days() > 0), $comment);

    $request->set_param('context', 'edit');

    if ($force) {
        $previous = harness_rest_prepare_comment($comment);
        $result   = wp_delete_comment($comment->comment_ID, true);

        if (!$result) {
            return new WP_Error('rest_cannot_delete', 'The comment cannot be deleted.', array('status' => 500));
        }

        return array(
            'status' => 200,
            'data'   => array(
                'deleted'  => true,
                'previous' => $previous,
            ),
        );
    }

    if (!$supports_trash) {
        return new WP_Error(
            'rest_trash_not_supported',
            "The comment does not support trashing. Set 'force=true' to delete.",
            array('status' => 501)
        );
    }

    if ('trash' === $comment->comment_approved) {
        return new WP_Error(
            'rest_already_trashed',
            'The comment has already been trashed.',
            array('status' => 410)
        );
    }

    $result = wp_trash_comment($comment->comment_ID);
    if (!$result) {
        return new WP_Error('rest_cannot_delete', 'The comment cannot be deleted.', array('status' => 500));
    }

    return array(
        'status' => 200,
        'data'   => harness_rest_prepare_comment(get_comment($comment->comment_ID)),
    );
}

/**
 * Simulate `WP_REST_Comments_Controller::handle_status_param()` — the second
 * retirement door, on single-item `POST`/`PUT`/`PATCH`.
 *
 * Mirrors the dispatch at `class-wp-rest-comments-controller.php:1845-1879`:
 * `trash` calls `wp_trash_comment()` (`:1869`), `spam` calls `wp_spam_comment()`
 * (`:1863`), while `approve`/`hold` change the status without retiring and
 * `untrash`/`unspam` restore it. An identical target status returns false, as
 * the core does at `:1848-1850`.
 *
 * @param string $new_status Requested `status` value.
 * @param int    $comment_id Comment ID.
 * @return bool Whether the status was changed.
 */
function harness_rest_handle_status_param($new_status, $comment_id)
{
    $old_status = wp_get_comment_status($comment_id);

    if ($new_status === $old_status) {
        return false;
    }

    switch ($new_status) {
        case 'approved':
        case 'approve':
        case '1':
            $changed = wp_set_comment_status($comment_id, 'approve');
            break;
        case 'hold':
        case '0':
            $changed = wp_set_comment_status($comment_id, 'hold');
            break;
        case 'spam':
            $changed = wp_spam_comment($comment_id);
            break;
        case 'unspam':
            $changed = wp_unspam_comment($comment_id);
            break;
        case 'trash':
            $changed = wp_trash_comment($comment_id);
            break;
        case 'untrash':
            $changed = wp_untrash_comment($comment_id);
            break;
        default:
            $changed = false;
            break;
    }

    return $changed;
}

/**
 * Match a request to its route handler exactly as the core does.
 *
 * Reproduces `WP_REST_Server::match_request_to_handler()`
 * (`class-wp-rest-server.php:1151-1215`) against a route table in the shape
 * `register_rest_route()` stores: the **keys are route regexes** while the
 * request carries the **concrete path**. `WP_REST_Request::get_route()` is set
 * only from the constructor (`class-wp-rest-request.php:127`) with the
 * `PATH_INFO` / `rest_route` the server passes it (`:376`), never with the
 * matched pattern — so the regex is what the table is keyed on and the concrete
 * path is what the request answers. For the first route whose pattern matches
 * `@^<route>$@i` against `$request->get_route()`, the matching handler group
 * populates three buckets in the core's order (`:1202-1214`):
 *
 * - `set_url_params()` with the **named captures** only (`id`);
 * - `set_attributes()` with the handler group;
 * - `set_default_params()` with `$handler['args'][ <arg> ]['default']` (for the
 *   `DELETE` group, `force => false`).
 *
 * The return is `array( $route, $handler )` with the **pattern** as first
 * element; the core returns the pattern apart and never re-assigns it to the
 * request, so a request's `get_route()` keeps being the concrete path after a
 * successful match. `null` is this double's equivalent of the core's
 * `WP_Error rest_no_route` (`:1216-1220`).
 *
 * @param WP_REST_Request $request Request whose `get_route()` is a concrete path.
 * @param array|null      $table   Route table (defaults to the core shape).
 * @return array|null `array( $route, $handler )`, or null when no route matches.
 */
function harness_match_request_to_handler($request, $table = null)
{
    if (null === $table) {
        $table = harness_rest_route_table();
    }

    if (!is_object($request) || !method_exists($request, 'get_route')) {
        return null;
    }

    $method = method_exists($request, 'get_method') ? strtoupper((string) $request->get_method()) : '';
    $path   = (string) $request->get_route();

    foreach ($table as $route => $handlers) {
        if (1 !== preg_match('@^' . $route . '$@i', $path, $matches)) {
            continue;
        }

        $args = array();
        foreach ($matches as $param => $value) {
            if (!is_int($param)) {
                $args[$param] = $value;
            }
        }

        if (!is_array($handlers)) {
            continue;
        }

        foreach ($handlers as $key => $handler) {
            if (!is_numeric($key) || !is_array($handler) || !array_key_exists('methods', $handler)) {
                continue;
            }

            $dispatchable = harness_rest_dispatchable_methods(array($handler));
            if (!isset($dispatchable[$method])) {
                continue;
            }

            $request->set_url_params($args);
            $request->set_attributes($handler);

            $defaults = array();
            if (isset($handler['args']) && is_array($handler['args'])) {
                foreach ($handler['args'] as $arg => $options) {
                    if (is_array($options) && array_key_exists('default', $options)) {
                        $defaults[$arg] = $options['default'];
                    }
                }
            }
            $request->set_default_params($defaults);

            return array($route, $handler);
        }
    }

    return null;
}

/**
 * Apply the `rest_request_before_callbacks` hook exactly where the core does.
 *
 * The core runs `apply_filters( 'rest_request_before_callbacks', $response,
 * $handler, $request )` and reassigns `$response` with the result
 * (`wp-includes/rest-api/class-wp-rest-server.php:1256`); a filter that returns
 * a `WP_Error` therefore short-circuits the request before the controller
 * callback — that is the hook the retirement guard registers on.
 *
 * @param mixed $response Result to send (usually null at this point).
 * @param array $handler  Route handler group for the matched request.
 * @param mixed $request  Request object.
 * @return mixed The filtered response, or the `WP_Error` a filter returned.
 */
function harness_apply_rest_request_before_callbacks($response, $handler, $request)
{
    return apply_filters('rest_request_before_callbacks', $response, $handler, $request);
}

/**
 * Decode the JSON body of a captured outbound request.
 *
 * Support utility for the payload assertions: `wp_remote_post()` records the
 * raw body in `$GLOBALS['mock_remote_requests']`, so every payload test has to
 * decode it. Returns an empty array when the request carried no JSON body, so
 * a missing payload fails an assertion instead of raising a warning.
 *
 * @param array $request Captured request entry (`url`, `args`), or a child
 *                       report entry that carries the raw body at `body`.
 * @return array Decoded payload, or an empty array when there is none.
 */
function harness_json_body($request)
{
    if (isset($request['args']['body'])) {
        $body = (string) $request['args']['body'];
    } elseif (isset($request['body'])) {
        $body = (string) $request['body'];
    } else {
        $body = '';
    }

    $decoded = json_decode($body, true);

    return is_array($decoded) ? $decoded : array();
}

/**
 * Whether a decoded payload carries a `metadata` object.
 *
 * @param array $body Decoded payload.
 * @return bool
 */
function harness_has_metadata($body)
{
    return isset($body['metadata']) && is_array($body['metadata']);
}

/**
 * Read a nested field of the `metadata` object of a decoded payload.
 *
 * Returns null both when `metadata` is absent (or not an object) and when the
 * field is absent, so callers can assert on the value without guarding the
 * array access themselves.
 *
 * @param array  $body Decoded payload.
 * @param string $key  Field name inside the `metadata` object.
 * @return mixed Field value, or null when there is no such field.
 */
function harness_metadata_field($body, $key)
{
    if (!harness_has_metadata($body)) {
        return null;
    }

    return array_key_exists($key, $body['metadata']) ? $body['metadata'][$key] : null;
}

/**
 * Simulate the core REST comment-creation pipeline.
 *
 * Reproduces, in the SAME order as `WP_REST_Comments_Controller::create_item()`
 * (`wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php`):
 *
 *   1. `rest_pre_insert_comment` filter — the server-side sealing hook (and,
 *      for the reply path, the write-quota check whose `WP_Error` makes the
 *      controller bail out at `class-wp-rest-comments-controller.php:698`).
 *   2. Row insert plus `comment_meta` persistence, then `wp_insert_comment`
 *      (fired inside `wp_insert_comment()` after the meta is stored,
 *      `comment.php:2072`) and `comment_post` with the insert-time approval.
 *   3. `handle_status_param()`: the requested `status` may approve a comment
 *      that was inserted pending, firing `transition_comment_status`.
 *   4. `rest_after_insert_comment`, the last hook.
 *
 * The order must not be inverted: persisting the mark in step 4 (as a
 * `rest_after_insert_comment` handler would) leaves it invisible during steps
 * 2 and 3, and the tests would then pass for a seal that arrives too late.
 * Likewise, an `is_wp_error()` result from the filter must abort **before**
 * the insertion — that is exactly what the core does, and it is what makes a
 * rejected request leave no row behind.
 *
 * @param array  $prepared_comment Comment data for `wp_insert_comment()`.
 * @param string $status           Requested REST `status` parameter.
 * @return int|WP_Error Inserted comment ID, or the filter's error.
 */
function harness_rest_insert_comment($prepared_comment, $status = '')
{
    // 1. rest_pre_insert_comment (the hook where the server seals).
    $prepared_comment = apply_filters('rest_pre_insert_comment', $prepared_comment, $status);

    // 1b. create_item() aborts on a filter error, before any insertion.
    if (is_wp_error($prepared_comment)) {
        return $prepared_comment;
    }

    $comment_id = (int) $prepared_comment['comment_ID'];
    $approved = isset($prepared_comment['comment_approved']) ? $prepared_comment['comment_approved'] : 0;

    $row = $prepared_comment;
    unset($row['comment_meta']);
    $row['comment_approved'] = $approved;
    $GLOBALS['mock_comments'][$comment_id] = $row;

    // 2. wp_insert_comment() persists comment_meta, then fires the action.
    if (isset($prepared_comment['comment_meta']) && is_array($prepared_comment['comment_meta'])) {
        foreach ($prepared_comment['comment_meta'] as $meta_key => $meta_value) {
            add_comment_meta($comment_id, $meta_key, $meta_value);
        }
    }

    do_action('wp_insert_comment', $comment_id, get_comment($comment_id));
    do_action('comment_post', $comment_id, (string) $approved);

    // 3. handle_status_param(): a requested approval on a pending comment
    //    transitions it, firing transition_comment_status.
    if ('approved' === (string) $status && '1' !== (string) $approved) {
        do_action('transition_comment_status', 'approved', 'hold', get_comment($comment_id));
        $GLOBALS['mock_comments'][$comment_id]['comment_approved'] = '1';
    }

    // 4. rest_after_insert_comment (last).
    do_action('rest_after_insert_comment', get_comment($comment_id), $status, true);

    return $comment_id;
}

/**
 * Run a child scenario in a separate PHP process and return its JSON report.
 *
 * @param string $script   Path to the test script.
 * @param string $scenario Child scenario name.
 * @return array|null Decoded report, or null when the child produced none.
 */
function harness_run_child($script, $scenario)
{
    $command = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg($script)
        . ' --child=' . escapeshellarg($scenario);

    $output = (string) shell_exec($command);
    $marker = '###CHILD_JSON###';
    $position = strpos($output, $marker);

    if ($position === false) {
        return null;
    }

    $json = trim(substr($output, $position + strlen($marker)));
    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : null;
}
