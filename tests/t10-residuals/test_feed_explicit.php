<?php
/**
 * Arnés del change `2026-11-20-t10-residual-fixes`: `itunes:explicit` conforme a
 * Apple.
 *
 * Ejercita el delta `podcast-feed`: el canal del feed del podcast publica
 * `itunes:explicit` con un valor booleano (`true` o `false`), nunca `yes`, `no`,
 * `clean` ni una cadena vacía. Los valores heredados del ajuste
 * `atareao_podcast_explicit` se normalizan (`yes` → `true`; el resto →
 * `false`), y el ajuste ausente produce `false`.
 *
 * Mismo estilo que `tests/podcast-guid-parity/test_guid_emission.php`. Se
 * ejecuta con `just test-t10` y en el job `lint` de CI.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';
require_once __DIR__ . '/assertions.php';

define('T10F_ROOT', dirname(__DIR__, 2));
define('T10F_FEED_FILE', T10F_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php');
define('T10F_THEME_FILE', T10F_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-theme-options.php');
define('T10F_SETTINGS_FILE', T10F_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-settings.php');

if (!is_file(T10F_FEED_FILE)) {
    t10_assert_true(false, 'existe includes/class-podcast-feed.php');
    t10_finish();
}
require_once T10F_FEED_FILE;

if (!is_file(T10F_THEME_FILE)) {
    t10_assert_true(false, 'existe includes/class-theme-options.php');
    t10_finish();
}
require_once T10F_THEME_FILE;
require_once T10F_SETTINGS_FILE;

/**
 * Compone los elementos del canal del podcast con un ajuste de explicit dado.
 *
 * @param string|null $explicit Valor del ajuste, o `null` para dejarlo ausente.
 * @return string XML emitido (lo que el método imprime y devuelve).
 */
function t10f_channel($explicit = null)
{
    harness_reset_state();
    $GLOBALS['mock_is_feed'] = true;
    $GLOBALS['mock_post_type_archive'] = 'podcast';
    $GLOBALS['mock_options'] = array(
        'atareao_podcast_author' => 'atareao',
        'atareao_podcast_category' => 'Technology',
        'atareao_podcast_owner_name' => 'Lorenzo Carbonell',
        'atareao_podcast_owner_email' => 'lorenzo@atareao.es',
    );
    if (null !== $explicit) {
        $GLOBALS['mock_options']['atareao_podcast_explicit'] = $explicit;
    }

    ob_start();
    \Atareao\PodcastFeed::channelElements();
    $echoed = (string) ob_get_clean();

    return $echoed;
}

/**
 * Extrae el valor de `itunes:explicit` del XML emitido.
 *
 * @param string $xml Channel elements.
 * @return string|null
 */
function t10f_explicit($xml)
{
    return 1 === preg_match('#<itunes:explicit>([^<]*)</itunes:explicit>#', $xml, $match)
        ? $match[1]
        : null;
}

/**
 * Renderiza la pestaña *Tema* con un valor guardado de `atareao_podcast_explicit`.
 *
 * @param string $stored Valor guardado en la opción.
 * @return string HTML del `<select>` de contenido explícito.
 */
function t10f_render_explicit_select($stored)
{
    harness_reset_state();
    $GLOBALS['mock_options'] = array('atareao_podcast_explicit' => $stored);

    ob_start();
    \Atareao\ThemeOptions::renderOptionsPage();
    $html = (string) ob_get_clean();

    if (1 !== preg_match('#<select[^>]*id="atareao_podcast_explicit"[^>]*>(.*?)</select>#s', $html, $match)) {
        return '';
    }

    return $match[1];
}

/**
 * Indica si una opción del `<select>` está marcada.
 *
 * @param string $select_html Contenido del `<select>`.
 * @param string $value       Valor de la opción (`true` o `false`).
 * @return bool
 */
function t10f_option_selected($select_html, $value)
{
    if (1 !== preg_match('#<option value="' . preg_quote($value, '#') . '"([^>]*)>#', $select_html, $match)) {
        return false;
    }

    return false !== strpos($match[1], 'selected');
}

echo "=== itunes:explicit conforme a Apple (t10) ===\n";

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el valor por defecto es válido para Apple ---\n";
// ---------------------------------------------------------------------------
$t10f_default = t10f_channel();
t10_assert_same('false', t10f_explicit($t10f_default), 'sin ajuste, itunes:explicit vale false');
t10_assert_true(
    false !== strpos($t10f_default, '<itunes:explicit>false</itunes:explicit>'),
    'el canal emite el elemento itunes:explicit'
);

t10_assert_same('false', t10f_explicit(t10f_channel('')), 'un ajuste vacío se normaliza a false');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: los valores heredados se normalizan ---\n";
// ---------------------------------------------------------------------------
$t10f_cases = array(
    'yes'   => 'true',
    'no'    => 'false',
    'clean' => 'false',
    'true'  => 'true',
    'otro'  => 'false',
    '0'     => 'false',
);
foreach ($t10f_cases as $t10f_stored => $t10f_expected) {
    $t10f_xml = t10f_channel($t10f_stored);
    t10_assert_same($t10f_expected, t10f_explicit($t10f_xml), "el valor heredado «{$t10f_stored}» se emite como {$t10f_expected}");
    t10_assert_true(
        1 !== preg_match('#<itunes:explicit>(no|yes|clean)?</itunes:explicit>#', $t10f_xml),
        "el valor heredado «{$t10f_stored}» nunca se emite literal"
    );
}

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el feed principal no lleva el tag ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
$GLOBALS['mock_is_feed'] = true;
$GLOBALS['mock_post_type_archive'] = false;
$GLOBALS['mock_options'] = array('atareao_podcast_explicit' => 'yes');
ob_start();
$t10f_main = \Atareao\PodcastFeed::channelElements();
$t10f_main_echoed = (string) ob_get_clean();
t10_assert_same('', $t10f_main, 'el feed principal no emite los elementos del podcast');
t10_assert_same('', $t10f_main_echoed, 'el feed principal no imprime itunes:explicit');
t10_assert_true(false === strpos($t10f_main_echoed, 'itunes:explicit'), 'el feed principal no contiene itunes:explicit');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el saneador normaliza todas las formas heredadas ---\n";
// ---------------------------------------------------------------------------
$t10f_sanitize_cases = array(
    'true'   => 'true',
    'yes'    => 'true',
    '1'      => 'true',
    'on'     => 'true',
    'false'  => 'false',
    'no'     => 'false',
    'clean'  => 'false',
    '0'      => 'false',
    ''       => 'false',
    '  '     => 'false',
    'basura' => 'false',
);
foreach ($t10f_sanitize_cases as $t10f_stored => $t10f_expected) {
    t10_assert_same(
        $t10f_expected,
        \Atareao\ThemeOptions::sanitizePodcastExplicit($t10f_stored),
        "sanitizePodcastExplicit('{$t10f_stored}') devuelve {$t10f_expected}"
    );
}
t10_assert_same(
    'false',
    \Atareao\ThemeOptions::sanitizePodcastExplicit(null),
    'sanitizePodcastExplicit(null) devuelve false'
);

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el <select> refleja el valor heredado guardado ---\n";
// ---------------------------------------------------------------------------
$t10f_select_cases = array(
    'yes'   => true,
    'true'  => true,
    '1'     => true,
    'on'    => true,
    'no'    => false,
    'clean' => false,
    'false' => false,
    '0'     => false,
);
foreach ($t10f_select_cases as $t10f_stored => $t10f_expect_true) {
    $t10f_select = t10f_render_explicit_select($t10f_stored);
    t10_assert_true('' !== $t10f_select, "el select se renderiza con el valor «{$t10f_stored}»");
    t10_assert_same(
        $t10f_expect_true,
        t10f_option_selected($t10f_select, 'true'),
        "el valor «{$t10f_stored}» marca la opción true " . ($t10f_expect_true ? 'sí' : 'no')
    );
    t10_assert_same(
        !$t10f_expect_true,
        t10f_option_selected($t10f_select, 'false'),
        "el valor «{$t10f_stored}» marca la opción false " . ($t10f_expect_true ? 'no' : 'sí')
    );
}
$t10f_select_default = t10f_render_explicit_select('false');
t10_assert_same(
    true,
    t10f_option_selected($t10f_select_default, 'false'),
    'sin valor heredado (false) el select marca la opción false'
);

t10_finish();
