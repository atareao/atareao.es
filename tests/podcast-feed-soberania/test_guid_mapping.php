<?php
/**
 * Arnés procedural de la fase RED del mapeo de GUID del change
 * `podcast-feed-soberania` (requisito «Guid del episodio compatible con el
 * origen»).
 *
 * Ejercita el contrato del módulo `Atareao\PodcastGuid` (nuevo): mapeo
 * determinista desde el RSS de Anchor (identidad del fichero de audio primero,
 * número como respaldo) y persistencia del UUID en el metadato del episodio.
 *
 * Mismo estilo que `tests/podcast-srt-upload/test_srt_upload.php`. Se ejecuta
 * con `just test-feed-soberania`.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('PG_ROOT', dirname(__DIR__, 2));
define('PG_MODULE', PG_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-guid.php');
define('PG_AUDIO', PG_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-audio.php');
define('PG_FEED', PG_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php');
define('PG_FIXTURE', __DIR__ . '/fixtures/anchor-rss-sample.xml');

foreach (array(PG_AUDIO, PG_FEED, PG_MODULE) as $pg_bootstrap_file) {
    if (is_file($pg_bootstrap_file)) {
        require_once $pg_bootstrap_file;
    }
}

$GLOBALS['pg_passes']   = 0;
$GLOBALS['pg_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pg_pass($description)
{
    $GLOBALS['pg_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pg_fail($description)
{
    $GLOBALS['pg_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function pg_assert_true($condition, $description)
{
    if ($condition) {
        pg_pass($description);

        return;
    }

    pg_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function pg_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        pg_pass($description);

        return;
    }

    pg_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

echo "podcast-feed-soberania — mapeo de GUID desde el RSS de Anchor\n";

pg_assert_true(is_file(PG_MODULE), 'existe includes/class-podcast-guid.php');
pg_assert_true(class_exists('\Atareao\PodcastGuid'), 'la clase \Atareao\PodcastGuid está disponible');

if (!class_exists('\Atareao\PodcastGuid')) {
    echo "\nResumen: {$GLOBALS['pg_passes']} PASS, " . count($GLOBALS['pg_failures']) . " FAIL\n";
    exit(1);
}

$pg_fixture = (string) file_get_contents(PG_FIXTURE);

// ---------------------------------------------------------------------------
// 1. El parser extrae guid, duración, identidad y número.
// ---------------------------------------------------------------------------
echo "\n--- Parser del RSS de origen ---\n";

$pg_items = \Atareao\PodcastGuid::parseOriginFeed($pg_fixture);
pg_assert_equals(3, count($pg_items), 'el parser lee los tres items del fixture');
if (isset($pg_items[0])) {
    pg_assert_equals(
        '11111111-1111-5111-8111-111111111111',
        $pg_items[0]['guid'],
        'el parser extrae el guid de Anchor'
    );
    pg_assert_equals('00:30:00', $pg_items[0]['duration'], 'el parser extrae la duración de Anchor');
    pg_assert_equals('ata849.mp3', $pg_items[0]['identity'], 'el parser normaliza la identidad del audio');
    pg_assert_equals(849, $pg_items[0]['number'], 'el parser deduce el número del título');
}
pg_assert_equals(array(), \Atareao\PodcastGuid::parseOriginFeed(''), 'un XML vacío no produce items');

// ---------------------------------------------------------------------------
// 2. El índice empareja por identidad y por número.
// ---------------------------------------------------------------------------
$pg_index = \Atareao\PodcastGuid::buildIndex($pg_items);
pg_assert_true(isset($pg_index['by_identity']['ata848.mp3']), 'el índice tiene la identidad ata848.mp3');
pg_assert_true(isset($pg_index['by_number'][839]), 'el índice tiene el número 839');

// ---------------------------------------------------------------------------
// 3. planMapping: identidad primero, número como respaldo.
// ---------------------------------------------------------------------------
$pg_plan = \Atareao\PodcastGuid::planMapping(
    array(
        'id' => 1,
        'mp3_url' => 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2026/1/ata849.mp3',
        'number' => '849',
    ),
    $pg_index
);
pg_assert_equals('mapped', $pg_plan['class'], 'la identidad del audio resuelve el episodio');
pg_assert_equals(
    '11111111-1111-5111-8111-111111111111',
    $pg_plan['guid'],
    'la identidad resuelve el guid correcto'
);

$pg_plan_number = \Atareao\PodcastGuid::planMapping(
    array('id' => 2, 'mp3_url' => '', 'number' => '839'),
    $pg_index
);
pg_assert_equals('mapped', $pg_plan_number['class'], 'sin audio, el número resuelve el episodio');
pg_assert_equals(
    '33333333-3333-5333-8333-333333333333',
    $pg_plan_number['guid'],
    'el respaldo por número resuelve el guid correcto'
);

$pg_plan_none = \Atareao\PodcastGuid::planMapping(
    array(
        'id' => 3,
        'mp3_url' => 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2026/1/desconocido.mp3',
        'number' => '',
    ),
    $pg_index
);
pg_assert_equals('unmatched', $pg_plan_none['class'], 'un episodio sin par queda sin mapear');
pg_assert_equals('', $pg_plan_none['guid'], 'un episodio sin par no inventa guid');

// ---------------------------------------------------------------------------
// 4. mapEpisodes persiste el guid en el metadato del episodio.
// ---------------------------------------------------------------------------
echo "\n--- Persistencia del mapeo ---\n";

harness_reset_state();
$GLOBALS['mock_is_feed'] = false;

$GLOBALS['mock_posts'] = array(
    new \WP_Post(array('ID' => 7001, 'post_type' => 'podcast', 'post_status' => 'publish')),
    new \WP_Post(array('ID' => 7002, 'post_type' => 'podcast', 'post_status' => 'publish')),
    new \WP_Post(array('ID' => 7003, 'post_type' => 'podcast', 'post_status' => 'publish')),
    new \WP_Post(array('ID' => 7004, 'post_type' => 'podcast', 'post_status' => 'publish')),
);
$GLOBALS['mock_post_meta'] = array(
    7001 => array(
        'mp3-url' => 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2026/1/ata849.mp3',
        'number'  => '849',
    ),
    7002 => array(
        'mp3-url' => 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2026/1/ata848.mp3',
        'number'  => '848',
    ),
    7003 => array('number' => '839'),
    7004 => array(
        'mp3-url' => 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2026/1/desconocido.mp3',
    ),
);

$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 200),
    'headers'  => array(),
    'body'     => $pg_fixture,
);

$GLOBALS['mock_transients'] = array();
$pg_report = \Atareao\PodcastGuid::mapEpisodes(array('dry_run' => false));
pg_assert_equals(
    '11111111-1111-5111-8111-111111111111',
    get_post_meta(7001, \Atareao\PodcastGuid::GUID_META, true),
    'el guid de Anchor se persiste en el episodio emparejado por audio'
);
pg_assert_equals(
    '22222222-2222-5222-8222-222222222222',
    get_post_meta(7002, \Atareao\PodcastGuid::GUID_META, true),
    'el guid se persiste en el segundo episodio emparejado'
);
pg_assert_equals(
    '33333333-3333-5333-8333-333333333333',
    get_post_meta(7003, \Atareao\PodcastGuid::GUID_META, true),
    'el respaldo por número persiste el guid'
);
pg_assert_equals('', get_post_meta(7004, \Atareao\PodcastGuid::GUID_META, true), 'un episodio sin par no recibe guid');
pg_assert_equals(3, (int) $pg_report['mapped'], 'el informe cuenta tres mapeos');
pg_assert_equals(1, (int) $pg_report['unmatched'], 'el informe cuenta un episodio sin par');

// ---------------------------------------------------------------------------
// 5. Idempotencia: la segunda pasada no reescribe ni duplica.
// ---------------------------------------------------------------------------
$pg_report_again = \Atareao\PodcastGuid::mapEpisodes(array('dry_run' => false));
pg_assert_equals(
    '11111111-1111-5111-8111-111111111111',
    get_post_meta(7001, \Atareao\PodcastGuid::GUID_META, true),
    'la segunda pasada conserva el guid'
);
pg_assert_equals(3, (int) $pg_report_again['ok'], 'la segunda pasada marca los tres como ya resueltos');

// ---------------------------------------------------------------------------
// 6. El ensayo no escribe nada.
// ---------------------------------------------------------------------------
$GLOBALS['mock_post_meta'][7101] = array(
    'mp3-url' => 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2026/1/ata849.mp3',
    'number'  => '849',
);
$GLOBALS['mock_posts'][] = new \WP_Post(array('ID' => 7101, 'post_type' => 'podcast', 'post_status' => 'publish'));
$GLOBALS['mock_transients'] = array();
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 200),
    'headers'  => array(),
    'body'     => $pg_fixture,
);
\Atareao\PodcastGuid::mapEpisodes(array('dry_run' => true));
pg_assert_equals('', get_post_meta(7101, \Atareao\PodcastGuid::GUID_META, true), 'el ensayo no escribe el guid');

// ---------------------------------------------------------------------------
// 7. El feed consume el guid persistido (integración con PodcastFeed).
// ---------------------------------------------------------------------------
if (class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'itemGuid')) {
    pg_assert_equals(
        '11111111-1111-5111-8111-111111111111',
        \Atareao\PodcastFeed::itemGuid(7001),
        'el feed lee el guid persistido por el mapeo'
    );
}

echo "\nResumen: {$GLOBALS['pg_passes']} PASS, " . count($GLOBALS['pg_failures']) . " FAIL\n";

if (!empty($GLOBALS['pg_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['pg_failures'] as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

exit(0);
