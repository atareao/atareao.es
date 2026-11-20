<?php
/**
 * Arnés de la degradación explícita del importador sin `PodcastDuration`.
 *
 * Carga el módulo del guid sin cargar `class-podcast-duration.php` y comprueba
 * que crear un episodio con duración **no revienta**: se escribe el metadato
 * canónico `duration` y se omite el espejo `_duration`, porque sin el módulo no
 * hay punto único que garantice la coherencia. La degradación es deliberada y
 * está documentada; lo que se prohíbe es el «Class not found».
 *
 * Se ejecuta con `just test-duration`.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('PBD_DEG_ROOT', dirname(__DIR__, 2));
define('PBD_DEG_INCLUDES', PBD_DEG_ROOT . '/wp-content/plugins/atareao-functionality/includes');
define('PBD_DEG_MISSING', dirname(__DIR__) . '/podcast-guid-parity/fixtures/anchor-rss-missing.xml');

foreach (array('class-podcast-audio.php', 'class-podcast-feed.php', 'class-podcast-guid.php') as $pbd_deg_file) {
    require_once PBD_DEG_INCLUDES . '/' . $pbd_deg_file;
}

$GLOBALS['pbd_deg_passes']   = 0;
$GLOBALS['pbd_deg_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pbd_deg_pass($description)
{
    $GLOBALS['pbd_deg_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pbd_deg_fail($description)
{
    $GLOBALS['pbd_deg_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function pbd_deg_assert_true($condition, $description)
{
    if ($condition) {
        pbd_deg_pass($description);

        return;
    }

    pbd_deg_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function pbd_deg_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        pbd_deg_pass($description);

        return;
    }

    pbd_deg_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

echo "podcast-duration — importador sin PodcastDuration\n";

pbd_deg_assert_true(
    !class_exists('\Atareao\PodcastDuration'),
    'el arnés arranca sin cargar PodcastDuration'
);

harness_reset_state();
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 200),
    'headers'  => array(),
    'body'     => (string) file_get_contents(PBD_DEG_MISSING),
);

$pbd_deg_report = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
pbd_deg_assert_equals(3, (int) ($pbd_deg_report['created'] ?? 0), 'el importador crea los tres episodios sin fatal');

$pbd_deg_post = null;
foreach (isset($GLOBALS['mock_inserted_posts']) ? $GLOBALS['mock_inserted_posts'] : array() as $pbd_deg_candidate) {
    if ($pbd_deg_candidate instanceof \WP_Post
        && (string) get_post_meta((int) $pbd_deg_candidate->ID, 'number', true) === '386'
    ) {
        $pbd_deg_post = $pbd_deg_candidate;
    }
}

pbd_deg_assert_true($pbd_deg_post instanceof \WP_Post, 'el episodio 386 existe tras la importación');
if ($pbd_deg_post instanceof \WP_Post) {
    $pbd_deg_id = (int) $pbd_deg_post->ID;
    pbd_deg_assert_equals(
        '00:22:04',
        get_post_meta($pbd_deg_id, 'duration', true),
        'sin el módulo se conserva el canónico'
    );
    pbd_deg_assert_equals(
        '',
        get_post_meta($pbd_deg_id, '_duration', true),
        'sin el módulo se omite el espejo (degradación documentada)'
    );
}

echo "\nResumen: {$GLOBALS['pbd_deg_passes']} PASS, "
    . count($GLOBALS['pbd_deg_failures']) . " FAIL\n";

if (!empty($GLOBALS['pbd_deg_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['pbd_deg_failures'] as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

exit(0);
