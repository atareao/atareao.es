<?php
/**
 * Arnés guardián del change `2026-11-21-release-bump-server-json`.
 *
 * Verifica la coherencia de versión del manifiesto del servidor MCP
 * (`server.json`) con el resto del árbol de release:
 *
 * 1. `.vampus.yml` declara una entrada `replaces` cuyo `file` es `server.json`
 *    y cuyo `pattern` cubre su clave `version`, usando el placeholder
 *    `{{current_version}}`. Es el único punto de verdad del bump.
 * 2. La `version` de `server.json`, la de `.vampus.yml` (`current_version`) y la
 *    del plugin (`ATAREAO_PLUGIN_VERSION`) coinciden.
 * 3. El contrato del manifiesto no se rompe: sigue siendo un objeto JSON válido,
 *    no enumera herramientas/recursos/prompts y no expone secretos.
 *
 * Mismo estilo que `tests/t10-residuals/test_feed_explicit.php`. Se ejecuta con
 * `just test` (receta `test-release`) y en el paso de arneses PHP de CI.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable Generic.Files.LineLength.TooLong

$GLOBALS['rb_passes']   = 0;
$GLOBALS['rb_failures'] = array();

/**
 * Asserts that two values are identical, recording the outcome.
 *
 * @param mixed  $expected    Expected value.
 * @param mixed  $actual      Actual value.
 * @param string $description Human readable description.
 * @return void
 */
function rb_assert_same($expected, $actual, $description)
{
    if ($expected === $actual) {
        $GLOBALS['rb_passes']++;
        echo "  PASS: {$description}\n";

        return;
    }

    $message = $description . ' (Expected: ' . var_export($expected, true)
        . ', got: ' . var_export($actual, true) . ')';
    $GLOBALS['rb_failures'][] = $message;
    echo "  FAIL: {$message}\n";
}

/**
 * Asserts that a condition holds, recording the outcome.
 *
 * @param bool   $condition   Condition to check.
 * @param string $description Human readable description.
 * @return void
 */
function rb_assert_true($condition, $description)
{
    rb_assert_same(true, (bool) $condition, $description);
}

/**
 * Prints the summary and exits with the harness status.
 *
 * @return void
 */
function rb_finish()
{
    echo "\nResumen: {$GLOBALS['rb_passes']} PASS, " . count($GLOBALS['rb_failures']) . " FAIL\n";

    if (!empty($GLOBALS['rb_failures'])) {
        echo "\nFallos:\n";
        foreach ($GLOBALS['rb_failures'] as $failure) {
            echo "  - {$failure}\n";
        }

        exit(1);
    }

    exit(0);
}

/**
 * Reads a file as a string, returning an empty string when unreadable.
 *
 * @param string $path Absolute path.
 * @return string
 */
function rb_read($path)
{
    return is_readable($path) ? (string) file_get_contents($path) : '';
}

/**
 * Extracts `current_version` from the `.vampus.yml` contents.
 *
 * @param string $yaml Contents of `.vampus.yml`.
 * @return string|null
 */
function rb_current_version($yaml)
{
    return 1 === preg_match('/^current_version:\s*(\S+)\s*$/m', $yaml, $match)
        ? $match[1]
        : null;
}

/**
 * Parses the `replaces` entries of `.vampus.yml` into file/pattern pairs.
 *
 * @param string $yaml Contents of `.vampus.yml`.
 * @return array<int, array{file: string, pattern: string}>
 */
function rb_replaces_entries($yaml)
{
    $entries = array();
    if (false === preg_match_all('/-\s*file:\s*(\S+)\s*\R\s*pattern:\s*(.+)/', $yaml, $matches, PREG_SET_ORDER)) {
        return $entries;
    }

    foreach ($matches as $match) {
        $pattern = trim($match[2]);
        // Despoja las comillas simples de YAML, si las hubiera.
        if (strlen($pattern) >= 2 && "'" === $pattern[0] && "'" === substr($pattern, -1)) {
            $pattern = substr($pattern, 1, -1);
        }

        $entries[] = array(
            'file'    => trim($match[1]),
            'pattern' => $pattern,
        );
    }

    return $entries;
}

/**
 * Extracts `ATAREAO_PLUGIN_VERSION` from the plugin bootstrap.
 *
 * @param string $plugin_php Contents of the plugin file.
 * @return string|null
 */
function rb_plugin_version($plugin_php)
{
    return 1 === preg_match("/define\(\s*'ATAREAO_PLUGIN_VERSION'\s*,\s*'([^']+)'\s*\)/", $plugin_php, $match)
        ? $match[1]
        : null;
}

define('RB_ROOT', dirname(__DIR__));

$rb_vampus_raw    = rb_read(RB_ROOT . '/.vampus.yml');
$rb_server_raw    = rb_read(RB_ROOT . '/server.json');
$rb_plugin_raw    = rb_read(RB_ROOT . '/wp-content/plugins/atareao-functionality/atareao-functionality.php');
$rb_server        = json_decode($rb_server_raw, true);
$rb_current       = rb_current_version($rb_vampus_raw);
$rb_plugin        = rb_plugin_version($rb_plugin_raw);
$rb_replaces      = rb_replaces_entries($rb_vampus_raw);

echo "=== Coherencia de versión del manifiesto MCP (release-bump) ===\n";

// ---------------------------------------------------------------------------
echo "\n--- Scenario: .vampus.yml declara el bump de server.json ---\n";
// ---------------------------------------------------------------------------
rb_assert_true('' !== $rb_vampus_raw, '.vampus.yml existe y es legible');
rb_assert_true(null !== $rb_current, '.vampus.yml declara current_version');

$rb_server_entry = null;
foreach ($rb_replaces as $rb_entry) {
    if ('server.json' === $rb_entry['file']) {
        $rb_server_entry = $rb_entry;
        break;
    }
}
rb_assert_true(
    null !== $rb_server_entry,
    '.vampus.yml declara una entrada replaces cuyo file es server.json'
);

if (null !== $rb_server_entry) {
    $rb_pattern = $rb_server_entry['pattern'];
    rb_assert_true(
        false !== strpos($rb_pattern, '{{current_version}}'),
        'la entrada de server.json usa el placeholder {{current_version}}'
    );
    rb_assert_true(
        false !== stripos($rb_pattern, 'version'),
        'la entrada de server.json cubre su clave version'
    );

    $rb_regex = '#'
        . str_replace('{{current_version}}', preg_quote((string) $rb_current, '#'), $rb_pattern)
        . '#';
    rb_assert_true(
        1 === @preg_match($rb_regex, $rb_server_raw),
        'el pattern aplicado a server.json casa su version vigente'
    );
}

// ---------------------------------------------------------------------------
echo "\n--- Scenario: las tres versiones coinciden ---\n";
// ---------------------------------------------------------------------------
rb_assert_true(is_array($rb_server), 'server.json existe y es un objeto JSON válido');
rb_assert_true(null !== $rb_plugin, 'el plugin define ATAREAO_PLUGIN_VERSION');

$rb_server_version = is_array($rb_server) ? ($rb_server['version'] ?? null) : null;
rb_assert_same($rb_plugin, $rb_current, 'current_version de .vampus.yml coincide con la del plugin');
rb_assert_same($rb_plugin, $rb_server_version, 'server.json.version coincide con la del plugin');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el contrato del manifiesto se preserva ---\n";
// ---------------------------------------------------------------------------
if (is_array($rb_server)) {
    rb_assert_true(!array_key_exists('tools', $rb_server), 'server.json no enumera tools');
    rb_assert_true(!array_key_exists('resources', $rb_server), 'server.json no enumera resources');
    rb_assert_true(!array_key_exists('prompts', $rb_server), 'server.json no enumera prompts');
}
rb_assert_true(
    false === strpos($rb_server_raw, 'client_secret')
        && false === strpos($rb_server_raw, 'token')
        && false === strpos($rb_server_raw, 'password'),
    'server.json no expone secretos ni credenciales'
);

rb_finish();
