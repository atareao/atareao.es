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

    // Reject dangerous schemes, as wp_kses_bad_protocol() does.
    $allowed = array('http', 'https', 'ftp', 'ftps', 'mailto', 'tel');
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
 * @param string $capability Capability name.
 * @return bool
 */
function current_user_can($capability)
{
    if ('manage_options' === $capability) {
        return !isset($GLOBALS['mock_user_can_manage_options'])
            || $GLOBALS['mock_user_can_manage_options'] !== false;
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
 * Minimal WP_Error replacement.
 */
class WP_Error
{
    /**
     * Human readable error message.
     *
     * @var string
     */
    private $message;

    /**
     * Constructor.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     */
    public function __construct($code = '', $message = '')
    {
        $this->message = $message;
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
}

/**
 * Signal thrown when the harness must emulate wp_die().
 */
class WpDieSignal extends RuntimeException
{
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
 * Permalink for a post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function get_permalink($post_id = 0)
{
    return 'https://atareao.es/post/' . $post_id;
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
    return is_string($email) && strpos($email, '@') !== false;
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
    $GLOBALS['mock_redirect_count'] = (int) $GLOBALS['mock_redirect_count'] + 1;
}

/**
 * Apply a filter (identity in tests).
 *
 * @param string $hook_name Filter name.
 * @param mixed  $value     Value to filter.
 * @return mixed
 */
function apply_filters($hook_name, $value)
{
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
    $GLOBALS['mock_transients'] = array();
    $GLOBALS['mock_matrix_calls'] = array();
    $GLOBALS['mock_matrix_send_result'] = true;
    $GLOBALS['last_redirect'] = null;
    $GLOBALS['mock_redirect_count'] = 0;
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
    $form_time = time() - 10;
    $captcha_a = 2;
    $captcha_b = 3;
    $signature = hash_hmac(
        'sha256',
        $captcha_a . ':' . $captcha_b . ':' . $form_time,
        wp_salt('nonce')
    );

    $post = array(
        'atareao_contact_form' => '1',
        'atareao_contact_nonce' => 'valid_nonce',
        'contact_name_email' => 'user@example.com',
        'contact_content' => 'Hola, esto es una prueba para Valet.',
        'atareao_website' => '',
        'atareao_captcha_answer' => '5',
        'atareao_captcha_a' => (string) $captcha_a,
        'atareao_captcha_b' => (string) $captcha_b,
        'atareao_captcha_sig' => $signature,
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
    } elseif ('invalid_nonce' === $scenario) {
        $verify_nonce = false;
    } elseif ('honeypot_filled' === $scenario) {
        $post['atareao_website'] = 'http://spam.example';
    } elseif ('bad_captcha' === $scenario) {
        $post['atareao_captcha_answer'] = '99';
    }

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
        'redirect_count' => isset($GLOBALS['mock_redirect_count']) ? $GLOBALS['mock_redirect_count'] : 0,
        'valet_requests' => $requests,
        'matrix_calls' => isset($GLOBALS['mock_matrix_calls']) ? $GLOBALS['mock_matrix_calls'] : array(),
        'options' => isset($GLOBALS['mock_options']) ? $GLOBALS['mock_options'] : array(),
    );

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

    $GLOBALS['mock_verify_nonce_result'] = $config['verify_nonce'];
    $GLOBALS['mock_remote_response'] = array(
        'response' => array('code' => 201),
        'body' => '{"id":"child-request"}',
    );

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
function harness_run_child($script, $scenario)
{
    $command = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg($script)
        . ' --child=' . escapeshellarg($scenario)
        . ' 2>/dev/null';

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
