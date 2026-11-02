<?php
/**
 * Arnés procedural para scripts/podcast-transcripts/wp-attach.php.
 *
 * `wp-attach.php` es el único componente del pipeline de transcripciones que
 * escribe en la base de datos real: crea adjuntos en la biblioteca de medios y
 * guarda su ID en la meta `transcript-srt` de episodios de producción. Este
 * arnés lo ejercita sin WordPress real, con dobles en memoria de la API que
 * usa, manifiestos y `.srt` reales en un directorio temporal, y captura de la
 * salida (que debe ser solo JSON).
 *
 * Se ejecuta con `just test-transcripts`. Devuelve 0 si pasan todas las
 * aserciones y 1 en caso contrario.
 *
 * @package Atareao\Scripts\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

define(
    'TT_HELPER_PATH',
    dirname(__DIR__, 2) . '/scripts/podcast-transcripts/wp-attach.php'
);

/**
 * Minimal in-memory replacement for the global `WP_Error` class.
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
     * Optional error data.
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
     * Error code.
     *
     * @return string
     */
    public function get_error_code()
    {
        return $this->code;
    }

    /**
     * Error message.
     *
     * @return string
     */
    public function get_error_message()
    {
        return $this->message;
    }

    /**
     * Error data.
     *
     * @return mixed
     */
    public function get_error_data()
    {
        return $this->data;
    }
}

/**
 * Whether a value is a WP_Error (mirrors `is_wp_error()`).
 *
 * @param mixed $thing Value to inspect.
 * @return bool
 */
function is_wp_error($thing)
{
    return $thing instanceof WP_Error;
}

/**
 * JSON-encode data (mirrors `wp_json_encode()`).
 *
 * @param mixed $data Data to encode.
 * @return string|false
 */
function wp_json_encode($data)
{
    return json_encode($data);
}

/**
 * Import bytes as an upload (mirrors the happy path of `wp_upload_bits()`).
 *
 * Writes a real temporary file so the helper's later `is_readable()` check is
 * meaningful, unless the scenario forced a transport error.
 *
 * @param string $name       Base file name.
 * @param null   $deprecated Unused (WordPress legacy argument).
 * @param string $bits       File contents.
 * @return array Upload result with `file`, `url`, `type` and `error` keys.
 */
function wp_upload_bits($name, $deprecated, $bits)
{
    if (isset($GLOBALS['mock_upload_error']) && $GLOBALS['mock_upload_error'] !== null) {
        return array('error' => (string) $GLOBALS['mock_upload_error']);
    }

    $file = $GLOBALS['mock_upload_dir'] . '/' . (string) $name;
    file_put_contents($file, (string) $bits);

    return array(
        'file'  => $file,
        'url'   => 'https://example.test/uploads/' . (string) $name,
        'type'  => 'application/x-subrip',
        'error' => false,
    );
}

/**
 * Insert an attachment (mirrors `wp_insert_attachment()`).
 *
 * Returns a `WP_Error`, `0` or a positive ID depending on the scenario's
 * control globals.
 *
 * @param array  $attachment Attachment fields.
 * @param string $file       Attached file path.
 * @param int    $parent     Parent post ID.
 * @param bool   $wp_error   Whether to return a WP_Error on failure.
 * @return int|WP_Error
 */
function wp_insert_attachment($attachment, $file = '', $parent = 0, $wp_error = false)
{
    if (isset($GLOBALS['mock_insert_error']) && $GLOBALS['mock_insert_error'] instanceof WP_Error) {
        return $GLOBALS['mock_insert_error'];
    }

    if (!empty($GLOBALS['mock_insert_zero'])) {
        return 0;
    }

    $id = (int) $GLOBALS['mock_attachment_next_id'];
    $GLOBALS['mock_attachment_next_id'] = $id + 1;
    $GLOBALS['mock_attachments'][$id] = array(
        'file'   => (string) $file,
        'url'    => '',
        'parent' => (int) $parent,
    );

    return $id;
}

/**
 * Generate attachment metadata (mirrors `wp_generate_attachment_metadata()`).
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $file          Attached file path.
 * @return array
 */
function wp_generate_attachment_metadata($attachment_id, $file)
{
    return array('file' => (string) $file);
}

/**
 * Persist attachment metadata (mirrors `wp_update_attachment_metadata()`).
 *
 * @param int   $attachment_id Attachment ID.
 * @param array $metadata      Metadata.
 * @return bool
 */
function wp_update_attachment_metadata($attachment_id, $metadata)
{
    return true;
}

/**
 * Absolute path of an attached file (mirrors `get_attached_file()`).
 *
 * @param int  $attachment_id Attachment ID.
 * @param bool $unfiltered    Unused.
 * @return string
 */
function get_attached_file($attachment_id, $unfiltered = false)
{
    if (array_key_exists('mock_attached_file', $GLOBALS) && $GLOBALS['mock_attached_file'] !== null) {
        return (string) $GLOBALS['mock_attached_file'];
    }

    $attachment_id = (int) $attachment_id;

    return isset($GLOBALS['mock_attachments'][$attachment_id]['file'])
        ? (string) $GLOBALS['mock_attachments'][$attachment_id]['file']
        : '';
}

/**
 * Resolve a post reference to an integer ID (helper for the two post getters).
 *
 * @param mixed $post Post object, numeric ID or null.
 * @return int
 */
function tt_resolve_post_id($post)
{
    if (is_object($post) && isset($post->ID)) {
        return (int) $post->ID;
    }

    if (is_numeric($post)) {
        return (int) $post;
    }

    return 0;
}

/**
 * Type of a post (mirrors `get_post_type()`), read from the mock store.
 *
 * @param mixed $post Post object, numeric ID or null.
 * @return string|false
 */
function get_post_type($post = null)
{
    $id = tt_resolve_post_id($post);
    if ($id <= 0 || !isset($GLOBALS['mock_posts'][$id])) {
        return false;
    }

    return (string) $GLOBALS['mock_posts'][$id]['post_type'];
}

/**
 * Status of a post (mirrors `get_post_status()`), read from the mock store.
 *
 * @param mixed $post Post object, numeric ID or null.
 * @return string|false
 */
function get_post_status($post = null)
{
    $id = tt_resolve_post_id($post);
    if ($id <= 0 || !isset($GLOBALS['mock_posts'][$id])) {
        return false;
    }

    return (string) $GLOBALS['mock_posts'][$id]['post_status'];
}

/**
 * Persist post metadata (mirrors `update_post_meta()`).
 *
 * A scenario can force the failure branch through `mock_meta_update_result`,
 * which is exactly the return value the helper currently ignores.
 *
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Value to store.
 * @param mixed  $prev_value Previous value required (ignored).
 * @return bool
 */
function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
{
    if (isset($GLOBALS['mock_meta_update_result']) && $GLOBALS['mock_meta_update_result'] === false) {
        return false;
    }

    $GLOBALS['mock_post_meta'][(int) $post_id][$meta_key] = $meta_value;

    return true;
}

/**
 * Read post metadata (mirrors `get_post_meta()`).
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key ('' returns the whole store).
 * @param bool   $single  Whether to return a single value.
 * @return mixed
 */
function get_post_meta($post_id, $key = '', $single = false)
{
    $store = isset($GLOBALS['mock_post_meta'][(int) $post_id])
        ? $GLOBALS['mock_post_meta'][(int) $post_id]
        : array();

    if ('' === $key) {
        return $single ? $store : array_map(static fn($value) => array($value), $store);
    }

    if (!array_key_exists($key, $store)) {
        return $single ? '' : array();
    }

    return $single ? $store[$key] : array($store[$key]);
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
 * Create the temporary directory tree the helper needs (ABSPATH + uploads).
 *
 * The helper does `require_once ABSPATH . 'wp-admin/includes/{file,image,media}.php'`,
 * so those three files must exist under the fake ABSPATH or the include is a
 * fatal error.
 *
 * @return void
 */
function tt_prepare_environment()
{
    $base = sys_get_temp_dir() . '/podcast-attach-tests-' . getmypid();
    $GLOBALS['tt_base'] = $base;
    $GLOBALS['tt_manifests'] = $base . '/manifests';
    $GLOBALS['tt_srts'] = $base . '/srt';
    $GLOBALS['tt_upload_dir'] = $base . '/uploads';
    $GLOBALS['tt_abspath'] = $base . '/wp/';

    $directories = array(
        $GLOBALS['tt_manifests'],
        $GLOBALS['tt_srts'],
        $GLOBALS['tt_upload_dir'],
        $GLOBALS['tt_abspath'] . 'wp-admin/includes',
    );
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    foreach (array('file.php', 'image.php', 'media.php') as $include) {
        file_put_contents($GLOBALS['tt_abspath'] . 'wp-admin/includes/' . $include, "<?php\n");
    }

    if (!defined('ABSPATH')) {
        define('ABSPATH', $GLOBALS['tt_abspath']);
    }
}

/**
 * Reset every piece of mock state to a known baseline.
 *
 * @return void
 */
function tt_reset()
{
    $GLOBALS['mock_posts'] = array();
    $GLOBALS['mock_post_meta'] = array();
    $GLOBALS['mock_attachments'] = array();
    $GLOBALS['mock_attachment_next_id'] = 5000;
    $GLOBALS['mock_upload_dir'] = $GLOBALS['tt_upload_dir'];
    $GLOBALS['mock_upload_error'] = null;
    $GLOBALS['mock_insert_error'] = null;
    $GLOBALS['mock_insert_zero'] = false;
    $GLOBALS['mock_attached_file'] = null;
    $GLOBALS['mock_meta_update_result'] = true;
}

/**
 * Write a real manifest file.
 *
 * @param array|string $entries Manifest data, or a raw string for malformed input.
 * @param string       $name    File name.
 * @return string Absolute path.
 */
function tt_manifest($entries, $name)
{
    if (!is_string($entries)) {
        $entries = json_encode($entries);
    }

    $path = $GLOBALS['tt_manifests'] . '/' . $name;
    file_put_contents($path, (string) $entries);

    return $path;
}

/**
 * Write a real `.srt` file.
 *
 * @param string $name     File name.
 * @param string $contents File contents.
 * @return string Absolute path.
 */
function tt_srt($name, $contents)
{
    $path = $GLOBALS['tt_srts'] . '/' . $name;
    file_put_contents($path, (string) $contents);

    return $path;
}

/**
 * Run the helper against a manifest and capture its output.
 *
 * WP-CLI exposes positional arguments in `$args`, so the helper is required in
 * a scope where `$args` holds the manifest path. Output is buffered so the
 * caller can inspect the (JSON-only) stream.
 *
 * @param string $manifest_path Manifest path.
 * @return string Captured output.
 */
function tt_run_attach($manifest_path)
{
    $args = array($manifest_path);

    ob_start();
    require TT_HELPER_PATH;
    $output = ob_get_clean();

    return (string) $output;
}

/**
 * Decode the helper's JSON output.
 *
 * @param string $output Raw output.
 * @return mixed Decoded value.
 */
function tt_decode($output)
{
    return json_decode($output, true);
}

/**
 * Fetch a per-episode entry handling both integer and string keys.
 *
 * @param mixed $results Decoded results.
 * @param mixed $id      Episode ID (int or numeric string).
 * @return mixed Entry, or null when missing.
 */
function tt_pick($results, $id)
{
    if (!is_array($results)) {
        return null;
    }

    if (array_key_exists($id, $results)) {
        return $results[$id];
    }

    if (array_key_exists((string) $id, $results)) {
        return $results[(string) $id];
    }

    return null;
}

/**
 * Read back the `transcript-srt` meta of a post from the mock store.
 *
 * @param int $post_id Post ID.
 * @return mixed Stored value, or null when absent.
 */
function tt_meta($post_id)
{
    return isset($GLOBALS['mock_post_meta'][(int) $post_id]['transcript-srt'])
        ? $GLOBALS['mock_post_meta'][(int) $post_id]['transcript-srt']
        : null;
}

/**
 * Recursively remove a directory.
 *
 * @param string $dir Directory path.
 * @return void
 */
function tt_rmdir_recursive($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $entries = scandir($dir);
    foreach ((array) $entries as $entry) {
        if ('.' === $entry || '..' === $entry) {
            continue;
        }

        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            tt_rmdir_recursive($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
$GLOBALS['passes'] = 0;
$GLOBALS['failures'] = array();

tt_prepare_environment();

$valid_srt = "1\n00:00:00,000 --> 00:00:01,000\nHola mundo\n";

echo "=== Running wp-attach.php Test Suite ===\n";

// ---------------------------------------------------------------------------
// Scenario: no se escribe meta en un post que no es podcast (I1)
// ---------------------------------------------------------------------------
echo "\n--- Scenario: No se escribe meta en un post que no es podcast ---\n";
tt_reset();
$GLOBALS['mock_posts'][18828] = array('post_type' => 'post', 'post_status' => 'publish');
$srt = tt_srt('s1.srt', $valid_srt);
$out = tt_run_attach(tt_manifest(array('18828' => $srt), 'm1.json'));
$res = tt_decode($out);
$entry = tt_pick($res, 18828);
assert_true(
    is_array($entry) && isset($entry['error']),
    'Un post que no es podcast devuelve un resultado con error'
);
assert_true(
    null === tt_meta(18828),
    'Un post que no es podcast: la meta transcript-srt NO se escribe'
);

// ---------------------------------------------------------------------------
// Scenario: no se escribe meta en un podcast en borrador (I1)
// ---------------------------------------------------------------------------
echo "\n--- Scenario: No se escribe meta en un podcast en borrador ---\n";
tt_reset();
$GLOBALS['mock_posts'][18829] = array('post_type' => 'podcast', 'post_status' => 'draft');
$srt = tt_srt('s2.srt', $valid_srt);
$out = tt_run_attach(tt_manifest(array('18829' => $srt), 'm2.json'));
$res = tt_decode($out);
$entry = tt_pick($res, 18829);
assert_true(
    is_array($entry) && isset($entry['error']),
    'Un podcast en borrador devuelve un resultado con error'
);
assert_true(
    null === tt_meta(18829),
    'Un podcast en borrador: la meta transcript-srt NO se escribe'
);

// ---------------------------------------------------------------------------
// Scenario: no se escribe meta si update_post_meta falla (I4)
// ---------------------------------------------------------------------------
echo "\n--- Scenario: No se escribe meta si update_post_meta falla ---\n";
tt_reset();
$GLOBALS['mock_posts'][18830] = array('post_type' => 'podcast', 'post_status' => 'publish');
$GLOBALS['mock_meta_update_result'] = false;
$srt = tt_srt('s3.srt', $valid_srt);
$out = tt_run_attach(tt_manifest(array('18830' => $srt), 'm3.json'));
$res = tt_decode($out);
$entry = tt_pick($res, 18830);
assert_true(
    is_array($entry) && isset($entry['error']),
    'Si update_post_meta falla el resultado trae error'
);
assert_true(
    is_array($entry) && !isset($entry['attachment_id']),
    'Si update_post_meta falla no se declara un attachment_id como éxito'
);
assert_true(
    null === tt_meta(18830),
    'Si update_post_meta falla ninguna meta queda escrita'
);

// ---------------------------------------------------------------------------
// Scenario: ID de episodio <= 0
// ---------------------------------------------------------------------------
echo "\n--- Scenario: ID de episodio <= 0 ---\n";
tt_reset();
$srt = tt_srt('s4.srt', $valid_srt);
$out = tt_run_attach(tt_manifest(array('-5' => $srt, '0' => $srt), 'm4.json'));
$res = tt_decode($out);
$negative = tt_pick($res, -5);
$zero = tt_pick($res, 0);
assert_true(
    is_array($negative) && isset($negative['error']),
    'Un ID de episodio negativo devuelve un resultado con error'
);
assert_true(
    is_array($zero) && isset($zero['error']),
    'Un ID de episodio cero devuelve un resultado con error'
);
assert_true(
    null === tt_meta(-5) && null === tt_meta(0),
    'Con IDs <= 0 no se escribe ninguna meta'
);

// ---------------------------------------------------------------------------
// Scenario: SRT inexistente o ilegible
// ---------------------------------------------------------------------------
echo "\n--- Scenario: SRT inexistente o ilegible ---\n";
tt_reset();
$GLOBALS['mock_posts'][18831] = array('post_type' => 'podcast', 'post_status' => 'publish');
$missing = $GLOBALS['tt_srts'] . '/no-existe.srt';
$out = tt_run_attach(tt_manifest(array('18831' => $missing), 'm5.json'));
$res = tt_decode($out);
$entry = tt_pick($res, 18831);
assert_true(
    is_array($entry) && isset($entry['error']),
    'Un SRT inexistente devuelve un resultado con error'
);
assert_true(
    null === tt_meta(18831),
    'Un SRT inexistente: la meta transcript-srt NO se escribe'
);

// ---------------------------------------------------------------------------
// Scenario: wp_upload_bits devuelve error
// ---------------------------------------------------------------------------
echo "\n--- Scenario: wp_upload_bits devuelve error ---\n";
tt_reset();
$GLOBALS['mock_posts'][18832] = array('post_type' => 'podcast', 'post_status' => 'publish');
$GLOBALS['mock_upload_error'] = 'espacio insuficiente';
$srt = tt_srt('s6.srt', $valid_srt);
$out = tt_run_attach(tt_manifest(array('18832' => $srt), 'm6.json'));
$res = tt_decode($out);
$entry = tt_pick($res, 18832);
assert_true(
    is_array($entry) && isset($entry['error']),
    'Un fallo de wp_upload_bits devuelve un resultado con error'
);
assert_true(
    null === tt_meta(18832),
    'Un fallo de wp_upload_bits: la meta transcript-srt NO se escribe'
);

// ---------------------------------------------------------------------------
// Scenario: wp_insert_attachment devuelve WP_Error
// ---------------------------------------------------------------------------
echo "\n--- Scenario: wp_insert_attachment devuelve WP_Error ---\n";
tt_reset();
$GLOBALS['mock_posts'][18833] = array('post_type' => 'podcast', 'post_status' => 'publish');
$GLOBALS['mock_insert_error'] = new WP_Error('insert_failed', 'fallo al insertar');
$srt = tt_srt('s7.srt', $valid_srt);
$out = tt_run_attach(tt_manifest(array('18833' => $srt), 'm7.json'));
$res = tt_decode($out);
$entry = tt_pick($res, 18833);
assert_true(
    is_array($entry) && isset($entry['error']),
    'Si wp_insert_attachment devuelve WP_Error el resultado trae error'
);
assert_true(
    null === tt_meta(18833),
    'Si wp_insert_attachment falla no se escribe la meta'
);

// ---------------------------------------------------------------------------
// Scenario: el adjunto creado no es legible
// ---------------------------------------------------------------------------
echo "\n--- Scenario: El adjunto creado no es legible ---\n";
tt_reset();
$GLOBALS['mock_posts'][18834] = array('post_type' => 'podcast', 'post_status' => 'publish');
$GLOBALS['mock_attached_file'] = $GLOBALS['tt_upload_dir'] . '/no-legible.srt';
$srt = tt_srt('s8.srt', $valid_srt);
$out = tt_run_attach(tt_manifest(array('18834' => $srt), 'm8.json'));
$res = tt_decode($out);
$entry = tt_pick($res, 18834);
assert_true(
    is_array($entry) && isset($entry['error']),
    'Un adjunto no legible devuelve un resultado con error'
);
assert_true(
    null === tt_meta(18834),
    'Un adjunto no legible: la meta transcript-srt NO se escribe'
);

// ---------------------------------------------------------------------------
// Scenario: camino feliz
// ---------------------------------------------------------------------------
echo "\n--- Scenario: Camino feliz ---\n";
tt_reset();
$GLOBALS['mock_posts'][18835] = array('post_type' => 'podcast', 'post_status' => 'publish');
$srt = tt_srt('s9.srt', $valid_srt);
$out = tt_run_attach(tt_manifest(array('18835' => $srt), 'm9.json'));
$res = tt_decode($out);
$entry = tt_pick($res, 18835);
assert_true(
    is_array($entry) && isset($entry['attachment_id']),
    'El camino feliz devuelve un attachment_id'
);
$attachment_id = is_array($entry) && isset($entry['attachment_id'])
    ? (int) $entry['attachment_id']
    : 0;
assert_true(
    $attachment_id > 0,
    'El attachment_id devuelto es un entero positivo'
);
assert_equals(
    $attachment_id,
    tt_meta(18835),
    'La meta transcript-srt del episodio guarda exactamente ese attachment_id'
);

// ---------------------------------------------------------------------------
// Scenario: manifiesto ilegible o JSON inválido
// ---------------------------------------------------------------------------
echo "\n--- Scenario: Manifiesto ilegible o JSON inválido ---\n";
tt_reset();
$out = tt_run_attach($GLOBALS['tt_manifests'] . '/no-existe.json');
$res = tt_decode($out);
assert_true(
    is_array($res) && isset($res['error']),
    'Un manifiesto ilegible devuelve un JSON con error'
);
assert_true(
    empty($GLOBALS['mock_post_meta']),
    'Un manifiesto ilegible no toca ninguna meta'
);

tt_reset();
$bad_manifest = tt_manifest('esto no es json {', 'bad.json');
$out = tt_run_attach($bad_manifest);
$res = tt_decode($out);
assert_true(
    is_array($res) && isset($res['error']),
    'Un manifiesto con JSON inválido devuelve un JSON con error'
);
assert_true(
    empty($GLOBALS['mock_post_meta']),
    'Un manifiesto con JSON inválido no toca ninguna meta'
);

// ---------------------------------------------------------------------------
// Scenario: la salida del helper es solo JSON
// ---------------------------------------------------------------------------
echo "\n--- Scenario: La salida del helper es solo JSON ---\n";
tt_reset();
$GLOBALS['mock_posts'][18836] = array('post_type' => 'podcast', 'post_status' => 'publish');
$srt = tt_srt('s11.srt', $valid_srt);
$out = tt_run_attach(tt_manifest(array('18836' => $srt), 'm11.json'));
assert_true(
    is_array(json_decode($out, true)),
    'La salida decodifica como JSON'
);
assert_equals(
    JSON_ERROR_NONE,
    json_last_error(),
    'La salida es JSON válido (sin error de decodificación)'
);
$first = strpos($out, '{');
$last = strrpos($out, '}');
$json_span = false === $first || false === $last
    ? ''
    : substr($out, $first, $last - $first + 1);
assert_equals(
    $out,
    $json_span,
    'La salida es solo el objeto JSON, sin ruido alrededor'
);

// ---------------------------------------------------------------------------
// Scenario: un fallo no impide resolver otro episodio del mismo manifiesto
// ---------------------------------------------------------------------------
echo "\n--- Scenario: Un episodio que falla no impide resolver otro ---\n";
tt_reset();
$GLOBALS['mock_posts'][18837] = array('post_type' => 'podcast', 'post_status' => 'publish');
$GLOBALS['mock_posts'][18838] = array('post_type' => 'podcast', 'post_status' => 'publish');
$good_srt = tt_srt('s12-good.srt', $valid_srt);
$missing_srt = $GLOBALS['tt_srts'] . '/s12-missing.srt';
$out = tt_run_attach(
    tt_manifest(array('18837' => $missing_srt, '18838' => $good_srt), 'm12.json')
);
$res = tt_decode($out);
$bad = tt_pick($res, 18837);
$good = tt_pick($res, 18838);
assert_true(
    is_array($bad) && isset($bad['error']),
    'El episodio con SRT ilegible reporta su error'
);
assert_true(
    is_array($good) && isset($good['attachment_id']),
    'El otro episodio del mismo manifiesto se resuelve correctamente'
);
$good_id = is_array($good) && isset($good['attachment_id']) ? (int) $good['attachment_id'] : 0;
assert_equals(
    $good_id,
    tt_meta(18838),
    'El episodio bueno guarda su propia meta con su attachment_id'
);
assert_true(
    null === tt_meta(18837),
    'El episodio fallido no deja meta escrita'
);

// ---------------------------------------------------------------------------
// Limpieza y resumen
// ---------------------------------------------------------------------------
tt_rmdir_recursive($GLOBALS['tt_base']);

echo "\n============================================\n";
echo "Total passed: {$GLOBALS['passes']}, Total failed: " . count($GLOBALS['failures']) . "\n";
if (count($GLOBALS['failures']) > 0) {
    echo "FAILED TESTS:\n";
    foreach ($GLOBALS['failures'] as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "All tests passed successfully!\n";
exit(0);
