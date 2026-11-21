<?php
/**
 * Arnés guardián de `@synopsis` en los comandos WP-CLI del plugin.
 *
 * Verifica el contrato del change `2026-11-21-wp-cli-synopsis`: cada callback
 * que el plugin registra con `WP_CLI::add_command()` y acepta argumentos o
 * flags declara un `@synopsis` visible en `wp <comando> --help`. La lista
 * verificada vive en `verified-synopsis.php` y la guardia
 * (`wp_cli_synopsis_violations()`) se ejecuta sin WP-CLI instalado, como en CI.
 *
 * Mismo estilo que `tests/t10-residuals/test_seo_tsf_integration.php`. Se
 * ejecuta con `just test` (receta `test-wp-cli-synopsis`) y en el paso de
 * arneses PHP de CI.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable Generic.Files.LineLength.TooLong

require_once __DIR__ . '/verified-synopsis.php';

define('WP_CLI_SYNOPSIS_ROOT', dirname(__DIR__, 2));
define('WP_CLI_SYNOPSIS_PLUGIN', WP_CLI_SYNOPSIS_ROOT . '/wp-content/plugins/atareao-functionality');

$GLOBALS['cs_passes']   = 0;
$GLOBALS['cs_failures'] = array();

/**
 * Asserts that two values are identical, recording the outcome.
 *
 * @param mixed  $expected    Expected value.
 * @param mixed  $actual      Actual value.
 * @param string $description Human readable description.
 * @return void
 */
function cs_assert_same($expected, $actual, $description)
{
    if ($expected === $actual) {
        $GLOBALS['cs_passes']++;
        echo "  PASS: {$description}\n";

        return;
    }

    $message = $description . ' (Expected: ' . var_export($expected, true)
        . ', got: ' . var_export($actual, true) . ')';
    $GLOBALS['cs_failures'][] = $message;
    echo "  FAIL: {$message}\n";
}

/**
 * Asserts that a condition holds, recording the outcome.
 *
 * @param bool   $condition   Condition to check.
 * @param string $description Human readable description.
 * @return void
 */
function cs_assert_true($condition, $description)
{
    cs_assert_same(true, (bool) $condition, $description);
}

/**
 * Prints the summary and exits with the harness status.
 *
 * @return void
 */
function cs_finish()
{
    echo "\nResumen: {$GLOBALS['cs_passes']} PASS, " . count($GLOBALS['cs_failures']) . " FAIL\n";

    if (!empty($GLOBALS['cs_failures'])) {
        echo "\nFallos:\n";
        foreach ($GLOBALS['cs_failures'] as $failure) {
            echo "  - {$failure}\n";
        }

        exit(1);
    }

    exit(0);
}

echo "=== Guardia de @synopsis de los comandos WP-CLI (wp-cli-help) ===\n";

// ---------------------------------------------------------------------------
echo "\n--- Scenario: los comandos registrados cumplen la lista verificada ---\n";
// ---------------------------------------------------------------------------
$cs_policy = wp_cli_synopsis_policy();
cs_assert_same(9, count($cs_policy), 'la lista verificada cubre los nueve comandos registrados');

$cs_violations = wp_cli_synopsis_violations(WP_CLI_SYNOPSIS_PLUGIN);
foreach ($cs_violations as $cs_violation) {
    echo "  VIOLACIÓN: {$cs_violation}\n";
}
cs_assert_same(array(), $cs_violations, 'cada callback anuncia en @synopsis exactamente los flags que lee');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: la guardia no deja pasar un synopsis ausente o desviado ---\n";
// ---------------------------------------------------------------------------
/*
 * La guardia se ejercita sobre una fuente de mentira inyectada por el lector:
 * así se comprueban (a) el registro, (b) la presencia del @synopsis y (c) la
 * coincidencia exacta de flags sin depender del estado real del plugin.
 */
$cs_fixture_ok = <<<'PHP'
<?php
namespace Atareao;
class Demo
{
    public static function init()
    {
        \WP_CLI::add_command('atareao demo-run', array(__CLASS__, 'cliRun'));
    }

    /**
     * Run.
     *
     * @synopsis [--dry-run] [--what=<x>]
     *
     * @param array $args  Positional.
     * @param array $assoc Named.
     * @return void
     */
    public static function cliRun($args = array(), $assoc = array()): void
    {
        $dry  = !empty($assoc['dry-run']);
        $what = $assoc['what'] ?? '';
    }
}
PHP;

$cs_demo_policy = array(
    array(
        'command'  => 'atareao demo-run',
        'file'     => 'demo.php',
        'callback' => 'cliRun',
        'synopsis' => '[--dry-run] [--what=<x>]',
    ),
);

$cs_reader = function ($relative) {
    return $GLOBALS['cs_fixture'];
};

$GLOBALS['cs_fixture'] = $cs_fixture_ok;
cs_assert_same(
    array(),
    wp_cli_synopsis_violations('', $cs_demo_policy, $cs_reader),
    'una fuente correcta no produce infracciones'
);

$GLOBALS['cs_fixture'] = str_replace(
    '     * @synopsis [--dry-run] [--what=<x>]' . "\n",
    '',
    $cs_fixture_ok
);
cs_assert_true(
    count(wp_cli_synopsis_violations('', $cs_demo_policy, $cs_reader)) > 0,
    'la guardia detecta un callback sin el @synopsis esperado'
);

$GLOBALS['cs_fixture'] = str_replace('[--dry-run] [--what=<x>]', '[--dry-run]', $cs_fixture_ok);
cs_assert_true(
    count(wp_cli_synopsis_violations('', $cs_demo_policy, $cs_reader)) > 0,
    'la guardia detecta un flag leído que falta en el @synopsis'
);

$GLOBALS['cs_fixture'] = str_replace('        $what = $assoc[\'what\'] ?? \'\';' . "\n", '', $cs_fixture_ok);
cs_assert_true(
    count(wp_cli_synopsis_violations('', $cs_demo_policy, $cs_reader)) > 0,
    'la guardia detecta un flag anunciado que el callback no lee'
);

$cs_other_policy = array(
    array(
        'command'  => 'atareao demo-run',
        'file'     => 'demo.php',
        'callback' => 'cliOtro',
        'synopsis' => '',
    ),
);
$GLOBALS['cs_fixture'] = $cs_fixture_ok;
cs_assert_true(
    count(wp_cli_synopsis_violations('', $cs_other_policy, $cs_reader)) > 0,
    'la guardia detecta un comando registrado con otro callback distinto del verificado'
);

// ---------------------------------------------------------------------------
echo "\n--- Scenario: los comandos sin args ni flags no exigen @synopsis ---\n";
// ---------------------------------------------------------------------------
$cs_policy_without_synopsis = array_values(array_filter($cs_policy, function ($cs_entry) {
    return '' === trim((string) $cs_entry['synopsis']);
}));
cs_assert_same(2, count($cs_policy_without_synopsis), 'la lista marca dos comandos sin args ni flags');
foreach ($cs_policy_without_synopsis as $cs_entry) {
    cs_assert_same(
        array(),
        wp_cli_synopsis_flags($cs_entry['synopsis']),
        "el comando {$cs_entry['command']} no anuncia flags"
    );
}

cs_finish();
