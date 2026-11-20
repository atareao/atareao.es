<?php
/**
 * Contadores y aserciones comunes a los arneses del change
 * `2026-11-20-t10-residual-fixes`.
 *
 * Los tres arneses de `tests/t10-residuals/` comparten la misma forma: cargan
 * `tests/wp-stubs.php`, ejecutan escenarios y terminan con `exit(0)` si todo
 * pasa o `exit(1)` si algo falla. Este fichero concentra el recuento y el
 * formato del resumen para que los tres se lean igual.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable Generic.Files.LineLength.TooLong

$GLOBALS['t10_passes']   = 0;
$GLOBALS['t10_failures'] = array();

/**
 * Asserts that two values are identical, recording the outcome.
 *
 * @param mixed  $expected    Expected value.
 * @param mixed  $actual      Actual value.
 * @param string $description Human readable description.
 * @return void
 */
function t10_assert_same($expected, $actual, $description)
{
    if ($expected === $actual) {
        $GLOBALS['t10_passes']++;
        echo "  PASS: {$description}\n";

        return;
    }

    $message = $description . ' (Expected: ' . var_export($expected, true)
        . ', got: ' . var_export($actual, true) . ')';
    $GLOBALS['t10_failures'][] = $message;
    echo "  FAIL: {$message}\n";
}

/**
 * Asserts that a condition holds, recording the outcome.
 *
 * @param bool   $condition   Condition to check.
 * @param string $description Human readable description.
 * @return void
 */
function t10_assert_true($condition, $description)
{
    t10_assert_same(true, (bool) $condition, $description);
}

/**
 * Prints the summary and exits with the harness status.
 *
 * @return void
 */
function t10_finish()
{
    echo "\nResumen: {$GLOBALS['t10_passes']} PASS, " . count($GLOBALS['t10_failures']) . " FAIL\n";

    if (!empty($GLOBALS['t10_failures'])) {
        echo "\nFallos:\n";
        foreach ($GLOBALS['t10_failures'] as $failure) {
            echo "  - {$failure}\n";
        }

        exit(1);
    }

    exit(0);
}