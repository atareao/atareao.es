<?php
/**
 * Arnés procedural para scripts/podcast-transcripts/wp-backfill.php.
 *
 * `wp-backfill.php` es el helper que reescribe (y revierte) el fichero del adjunto
 * de la transcripción en producción. Es el punto más frágil del backfill: manipula
 * ficheros reales junto al adjunto y, si se hiciera mal, podría perder el SRT de un
 * episodio o cambiar su ID o su meta `transcript-srt`. Este arnés lo ejercita sin
 * WordPress real, con dobles en memoria de `get_post_meta`/`get_attached_file` y
 * `.srt` reales en un directorio temporal, y captura de la salida (solo JSON).
 *
 * Cubre: no escribe la meta `transcript-srt`; copia `<fichero>.orig.srt` ANTES de
 * sobrescribir; conserva esa copia en la segunda pasada; `revert` restaura el
 * original; el ID de adjunto se mantiene; la escritura no deja ficheros temporales
 * (escritura atómica); y todo fallo se devuelve como JSON `error` sin tocar nada.
 *
 * Se ejecuta con `just test-transcripts` (y en CI). Devuelve 0 si pasan todas las
 * aserciones y 1 en caso contrario.
 *
 * @package Atareao\Scripts\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

define(
    'TT_BACKFILL_HELPER',
    dirname(__DIR__, 2) . '/scripts/podcast-transcripts/wp-backfill.php'
);

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/fake-wp-backfill/');
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
 * Filter stub (mirrors `apply_filters()`).
 *
 * The helper exposes a single injection point, `atareao_backfill_force_error`, so
 * the harness can force failures that cannot be reproduced with real files (a
 * `copy`/`rename`/read failure). Without a forced error the default value (`''`) is
 * returned, exactly like production, where no filter is registered.
 *
 * @param string $tag   Filter tag.
 * @param mixed  $value Default value.
 * @param string $step  Step the helper wants to force (extra filter argument).
 * @return mixed Filtered value.
 */
function apply_filters($tag, $value, $step = '')
{
    if ('atareao_backfill_force_error' === $tag
        && isset($GLOBALS['mock_force_error'][$step])
        && '' !== $GLOBALS['mock_force_error'][$step]
    ) {
        return $GLOBALS['mock_force_error'][$step];
    }

    return $value;
}

/**
 * Record any attempt to persist post metadata (mirrors `update_post_meta()`).
 *
 * The helper MUST NOT write the `transcript-srt` meta, so this stub only records
 * the call: the assertions check that the record stays empty.
 *
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Value to store.
 * @param mixed  $prev_value Previous value required (ignored).
 * @return bool
 */
function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
{
    $GLOBALS['meta_updates'][] = array((int) $post_id, (string) $meta_key, $meta_value);

    return true;
}

/**
 * Read post metadata (mirrors `get_post_meta()`), from the mock store.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key ('' returns the whole store).
 * @param bool   $single  Whether to return a single value.
 * @return mixed
 */
function get_post_meta($post_id, $key = '', $single = false)
{
    $store = isset($GLOBALS['mock_meta'][(int) $post_id])
        ? $GLOBALS['mock_meta'][(int) $post_id]
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
 * Absolute path of an attached file (mirrors `get_attached_file()`).
 *
 * @param int  $attachment_id Attachment ID.
 * @param bool $unfiltered    Unused.
 * @return string
 */
function get_attached_file($attachment_id, $unfiltered = false)
{
    $attachment_id = (int) $attachment_id;

    return isset($GLOBALS['mock_files'][$attachment_id])
        ? (string) $GLOBALS['mock_files'][$attachment_id]
        : '';
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
 * Create the temporary directory tree the harness needs.
 *
 * @return void
 */
function tt_prepare_environment()
{
    $base = sys_get_temp_dir() . '/podcast-backfill-tests-' . getmypid();
    $GLOBALS['tt_base'] = $base;
    $GLOBALS['tt_uploads'] = $base . '/uploads';

    foreach (array($GLOBALS['tt_uploads']) as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}

/**
 * Reset every piece of mock state to a known baseline.
 *
 * @return void
 */
function tt_reset()
{
    $GLOBALS['mock_meta'] = array();
    $GLOBALS['mock_files'] = array();
    $GLOBALS['meta_updates'] = array();
    $GLOBALS['mock_force_error'] = array();

    // Limpia los ficheros de escenarios previos para no arrastrar `.orig.srt`.
    foreach ((array) glob($GLOBALS['tt_uploads'] . '/*') as $leftover) {
        @unlink($leftover);
    }
}

/**
 * Force the helper to fail a given step (see the `apply_filters` stub).
 *
 * @param string $step Step to force: 'read', 'copy' or 'write'.
 * @return void
 */
function tt_force_error($step)
{
    $GLOBALS['mock_force_error'][$step] = 'forced by harness';
}

/**
 * Create an attachment file and register it in the mock store.
 *
 * @param int    $episode_id    Episode ID.
 * @param int    $attachment_id Attachment ID.
 * @param string $contents      Initial file contents.
 * @param string $name          Base file name.
 * @return string Absolute path of the attachment file.
 */
function tt_attachment($episode_id, $attachment_id, $contents, $name = 'transcript.srt')
{
    $path = $GLOBALS['tt_uploads'] . '/' . $name;
    file_put_contents($path, $contents);
    $GLOBALS['mock_files'][$attachment_id] = $path;
    $GLOBALS['mock_meta'][$episode_id] = array('transcript-srt' => $attachment_id);

    return $path;
}

/**
 * Write a real `.srt` file to use as a `put` source.
 *
 * @param string $name     File name.
 * @param string $contents File contents.
 * @return string Absolute path.
 */
function tt_source($name, $contents)
{
    $path = $GLOBALS['tt_base'] . '/' . $name;
    file_put_contents($path, $contents);

    return $path;
}

/**
 * Run the helper with positional arguments (WP-CLI exposes them in `$args`).
 *
 * `require` is used (not `require_once`) so every scenario re-executes the file;
 * the helper guards its function definition with `function_exists` precisely to
 * allow this. Output is buffered so the caller can inspect the JSON-only stream.
 *
 * @param array $args Positional arguments, e.g. array('put', 101, '/tmp/a.srt').
 * @return string Captured output.
 */
function tt_run_backfill(array $args)
{
    ob_start();
    require TT_BACKFILL_HELPER;
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
 * Read back the `transcript-srt` meta of a post from the mock store.
 *
 * @param int $post_id Post ID.
 * @return mixed Stored value, or null when absent.
 */
function tt_meta_value($post_id)
{
    return isset($GLOBALS['mock_meta'][(int) $post_id]['transcript-srt'])
        ? $GLOBALS['mock_meta'][(int) $post_id]['transcript-srt']
        : null;
}

/**
 * Sorted list of base names present in the uploads directory.
 *
 * @return array<int,string>
 */
function tt_upload_entries()
{
    $entries = array();
    foreach ((array) scandir($GLOBALS['tt_uploads']) as $entry) {
        if ('.' === $entry || '..' === $entry) {
            continue;
        }
        $entries[] = $entry;
    }
    sort($entries);

    return $entries;
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

$original = "1\n00:00:00,000 --> 00:00:01,000\nhola mundo\n";
$processed = "1\n00:00:00,000 --> 00:00:01,000\nHola mundo.\n";

echo "=== Running wp-backfill.php Test Suite ===\n";

// ---------------------------------------------------------------------------
// Scenario: `get` devuelve base64 y el ID del adjunto sin tocar la meta
// ---------------------------------------------------------------------------
echo "\n--- Scenario: get devuelve el contenido y el ID del adjunto ---\n";
tt_reset();
tt_attachment(101, 4242, $original);
$out = tt_run_backfill(array('get', 101));
$res = tt_decode($out);
assert_equals(4242, isset($res['attachment_id']) ? (int) $res['attachment_id'] : -1, 'get devuelve el ID del adjunto');
assert_equals(
    $original,
    base64_decode((string) $res['content']),
    'get devuelve el contenido del SRT en base64'
);
assert_equals(array(), $GLOBALS['meta_updates'], 'get no escribe la meta transcript-srt');

// ---------------------------------------------------------------------------
// Scenario: `get` sin transcripción enlazada
// ---------------------------------------------------------------------------
echo "\n--- Scenario: get sin transcripción devuelve attachment_id 0 ---\n";
tt_reset();
$out = tt_run_backfill(array('get', 55));
$res = tt_decode($out);
assert_equals(0, (int) $res['attachment_id'], 'get sin meta devuelve attachment_id 0');
assert_true(!isset($res['content']), 'get sin transcripción no inventa contenido');

// ---------------------------------------------------------------------------
// Scenario: `put` conserva el original en `.orig.srt` antes de sobrescribir
// ---------------------------------------------------------------------------
echo "\n--- Scenario: put guarda el original antes de sobrescribir ---\n";
tt_reset();
$file = tt_attachment(101, 4242, $original);
$source = tt_source('nuevo-a.srt', $processed);
$out = tt_run_backfill(array('put', 101, $source));
$res = tt_decode($out);
assert_equals(4242, (int) $res['attachment_id'], 'put devuelve el MISMO ID de adjunto');
assert_true(!empty($res['changed']), 'put reporta changed=true');
assert_equals($processed, file_get_contents($file), 'put sobrescribe el fichero del adjunto');
assert_true(file_exists($file . '.orig.srt'), 'put crea la copia <fichero>.orig.srt');
assert_equals(
    $original,
    file_get_contents($file . '.orig.srt'),
    'la copia .orig.srt conserva el SRT ORIGINAL'
);
assert_equals(
    array('transcript.srt', 'transcript.srt.orig.srt'),
    tt_upload_entries(),
    'tras el put no quedan ficheros temporales en el directorio (escritura atómica)'
);
assert_equals(4242, tt_meta_value(101), 'la meta transcript-srt no cambia tras el put');
assert_equals(array(), $GLOBALS['meta_updates'], 'put no escribe la meta transcript-srt');

// ---------------------------------------------------------------------------
// Scenario: segunda pasada conserva la copia del original
// ---------------------------------------------------------------------------
echo "\n--- Scenario: la segunda pasada conserva el original ---\n";
$source2 = tt_source('nuevo-b.srt', "1\n00:00:00,000 --> 00:00:01,000\nHola mundo!.\n");
$out = tt_run_backfill(array('put', 101, $source2));
$res = tt_decode($out);
assert_true(!empty($res['changed']), 'la segunda pasada reporta changed=true');
assert_equals(
    $original,
    file_get_contents($file . '.orig.srt'),
    'la segunda pasada NO pisa la copia .orig.srt (sigue el original real)'
);
assert_equals(
    array('transcript.srt', 'transcript.srt.orig.srt'),
    tt_upload_entries(),
    'la segunda pasada tampoco deja ficheros temporales'
);

// ---------------------------------------------------------------------------
// Scenario: `put` no cambia nada si el contenido es idéntico
// ---------------------------------------------------------------------------
echo "\n--- Scenario: put idempotente no reporta cambio ---\n";
tt_reset();
$file = tt_attachment(101, 4242, $original);
$source = tt_source('igual.srt', $original);
$out = tt_run_backfill(array('put', 101, $source));
$res = tt_decode($out);
assert_true(empty($res['changed']), 'put con el mismo contenido reporta changed=false');

// ---------------------------------------------------------------------------
// Scenario: `revert` restaura el original y mantiene ID y meta
// ---------------------------------------------------------------------------
echo "\n--- Scenario: revert restaura el original ---\n";
tt_reset();
$file = tt_attachment(101, 4242, $original);
$source = tt_source('nuevo-c.srt', $processed);
tt_run_backfill(array('put', 101, $source));
$out = tt_run_backfill(array('revert', 101));
$res = tt_decode($out);
assert_equals(true, $res['reverted'], 'revert reporta reverted=true');
assert_equals(4242, (int) $res['attachment_id'], 'revert mantiene el ID de adjunto');
assert_equals($original, file_get_contents($file), 'revert restaura el SRT original');
assert_equals(4242, tt_meta_value(101), 'revert mantiene la meta transcript-srt');
assert_equals(array(), $GLOBALS['meta_updates'], 'revert no escribe la meta transcript-srt');

// ---------------------------------------------------------------------------
// Scenario: `revert` sin copia del original no restaura nada
// ---------------------------------------------------------------------------
echo "\n--- Scenario: revert sin copia del original devuelve reverted=false ---\n";
tt_reset();
tt_attachment(101, 4242, $original);
$out = tt_run_backfill(array('revert', 101));
$res = tt_decode($out);
assert_equals(false, $res['reverted'], 'revert sin copia reporta reverted=false');
assert_true(!isset($res['error']), 'revert sin copia no es un error');

// ---------------------------------------------------------------------------
// Scenario: `put` con un SRT nuevo ilegible no toca nada
// ---------------------------------------------------------------------------
echo "\n--- Scenario: put con SRT nuevo ilegible devuelve error y no toca nada ---\n";
tt_reset();
$file = tt_attachment(101, 4242, $original);
$out = tt_run_backfill(array('put', 101, $GLOBALS['tt_base'] . '/no-existe.srt'));
$res = tt_decode($out);
assert_true(isset($res['error']), 'put con fuente ilegible devuelve un error JSON');
assert_equals($original, file_get_contents($file), 'put fallido no modifica el adjunto');
assert_true(!file_exists($file . '.orig.srt'), 'put fallido no crea la copia .orig.srt');
assert_equals(array(), $GLOBALS['meta_updates'], 'put fallido no escribe la meta');

// ---------------------------------------------------------------------------
// Scenario: `put` sin adjunto enlazado no escribe nada
// ---------------------------------------------------------------------------
echo "\n--- Scenario: put sin adjunto enlazado devuelve error ---\n";
tt_reset();
$source = tt_source('huerfano.srt', $processed);
$out = tt_run_backfill(array('put', 77, $source));
$res = tt_decode($out);
assert_true(isset($res['error']), 'put sin adjunto devuelve un error JSON');
assert_equals(array(), tt_upload_entries(), 'put sin adjunto no crea ficheros');
assert_equals(array(), $GLOBALS['meta_updates'], 'put sin adjunto no escribe la meta');

// ---------------------------------------------------------------------------
// Scenario: un fallo de escritura a mitad de `put` NO toca el SRT destino
// ---------------------------------------------------------------------------
echo "\n--- Scenario: fallo de escritura en put deja el adjunto intacto ---\n";
tt_reset();
$file = tt_attachment(101, 4242, $original);
$source = tt_source('fallo-escritura.srt', $processed);
tt_force_error('write');
$out = tt_run_backfill(array('put', 101, $source));
$res = tt_decode($out);
assert_true(isset($res['error']), 'un fallo de escritura en put devuelve un error JSON');
assert_equals(
    $original,
    file_get_contents($file),
    'un fallo de escritura en put deja el SRT destino INTACTO'
);
assert_equals(
    array('transcript.srt', 'transcript.srt.orig.srt'),
    tt_upload_entries(),
    'un fallo de escritura no deja ficheros temporales huérfanos'
);

// ---------------------------------------------------------------------------
// Scenario: un fallo al conservar el original aborta el `put` sin escribir
// ---------------------------------------------------------------------------
echo "\n--- Scenario: fallo al copiar el original aborta el put ---\n";
tt_reset();
$file = tt_attachment(101, 4242, $original);
$source = tt_source('fallo-copia.srt', $processed);
tt_force_error('copy');
$out = tt_run_backfill(array('put', 101, $source));
$res = tt_decode($out);
assert_true(isset($res['error']), 'un fallo al copiar el original devuelve un error JSON');
assert_equals(
    $original,
    file_get_contents($file),
    'sin copia del original el put no sobrescribe el adjunto'
);
assert_true(
    !file_exists($file . '.orig.srt'),
    'un fallo al copiar el original no deja una copia a medias'
);

// ---------------------------------------------------------------------------
// Scenario: un fallo de escritura en `revert` no toca el SRT destino
// ---------------------------------------------------------------------------
echo "\n--- Scenario: fallo de escritura en revert deja el adjunto intacto ---\n";
tt_reset();
$file = tt_attachment(101, 4242, $original);
$source = tt_source('fallo-revert.srt', $processed);
tt_run_backfill(array('put', 101, $source));
tt_force_error('write');
$out = tt_run_backfill(array('revert', 101));
$res = tt_decode($out);
assert_true(isset($res['error']), 'un fallo de escritura en revert devuelve un error JSON');
assert_equals(
    $processed,
    file_get_contents($file),
    'un fallo de escritura en revert deja el SRT destino INTACTO'
);
assert_equals(
    array('transcript.srt', 'transcript.srt.orig.srt'),
    tt_upload_entries(),
    'un revert fallido no deja ficheros temporales huérfanos'
);

// ---------------------------------------------------------------------------
// Scenario: `get` con adjunto ilegible no inventa transcripción
// ---------------------------------------------------------------------------
echo "\n--- Scenario: get con adjunto ilegible devuelve attachment_id 0 ---\n";
tt_reset();
$GLOBALS['mock_meta'][101] = array('transcript-srt' => 4242);
$GLOBALS['mock_files'][4242] = $GLOBALS['tt_base'] . '/no-existe.srt';
$out = tt_run_backfill(array('get', 101));
$res = tt_decode($out);
assert_equals(0, (int) $res['attachment_id'], 'un adjunto ilegible no devuelve contenido');

// ---------------------------------------------------------------------------
// Scenario: `get` con fallo de lectura devuelve error
// ---------------------------------------------------------------------------
echo "\n--- Scenario: get con fallo de lectura devuelve un error JSON ---\n";
tt_reset();
tt_attachment(101, 4242, $original);
tt_force_error('read');
$out = tt_run_backfill(array('get', 101));
$res = tt_decode($out);
assert_true(isset($res['error']), 'un fallo de lectura en get devuelve un error JSON');

// ---------------------------------------------------------------------------
// Scenario: `revert` con fallo de lectura del original devuelve error
// ---------------------------------------------------------------------------
echo "\n--- Scenario: revert con fallo de lectura del original devuelve error ---\n";
tt_reset();
$file = tt_attachment(101, 4242, $original);
$source = tt_source('fallo-lectura-orig.srt', $processed);
tt_run_backfill(array('put', 101, $source));
tt_force_error('read');
$out = tt_run_backfill(array('revert', 101));
$res = tt_decode($out);
assert_true(isset($res['error']), 'un fallo de lectura del original devuelve un error JSON');
assert_equals(
    $processed,
    file_get_contents($file),
    'un revert con fallo de lectura no toca el adjunto'
);

// ---------------------------------------------------------------------------
// Scenario: acción no reconocida y ID inválido
// ---------------------------------------------------------------------------
echo "\n--- Scenario: acción no reconocida e ID inválido ---\n";
tt_reset();
$out = tt_run_backfill(array('borrar', 101));
$res = tt_decode($out);
assert_true(isset($res['error']), 'una acción no reconocida devuelve un error JSON');

tt_reset();
$out = tt_run_backfill(array('get', 0));
$res = tt_decode($out);
assert_true(isset($res['error']), 'un ID de episodio inválido devuelve un error JSON');

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
