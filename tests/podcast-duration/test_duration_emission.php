<?php
/**
 * Arnés de la fase RED del change `2026-11-20-podcast-duration-backfill`:
 * normalización y emisión del `<itunes:duration>` del feed del podcast.
 *
 * Ejercita el contrato del delta `podcast-feed` (requisito «Duración del
 * episodio en el feed»): `PodcastFeed::normalizeDuration()` devuelve el valor
 * literal cuando es `HH:MM:SS`, `MM:SS` o un entero de segundos, y la cadena
 * vacía cuando el valor está vacío, malformado o fuera de rango; la emisión
 * escribe el valor normalizado y omite el tag cuando la normalización devuelve
 * la cadena vacía.
 *
 * Los valores esperados se escriben literales: nunca se calculan con la función
 * bajo prueba. Se ejecuta con `just test-duration`.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('PDE_ROOT', dirname(__DIR__, 2));
define('PDE_FEED', PDE_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php');

if (is_file(PDE_FEED)) {
    require_once PDE_FEED;
}

$GLOBALS['pde_passes']   = 0;
$GLOBALS['pde_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pde_pass($description)
{
    $GLOBALS['pde_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pde_fail($description)
{
    $GLOBALS['pde_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function pde_assert_true($condition, $description)
{
    if ($condition) {
        pde_pass($description);

        return;
    }

    pde_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function pde_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        pde_pass($description);

        return;
    }

    pde_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

/**
 * Envuelve un fragmento de item en un documento con los namespaces del feed.
 *
 * @param string $body Fragmento XML.
 * @return \DOMDocument|null
 */
function pde_xml($body)
{
    if (!is_string($body) || trim($body) === '') {
        return null;
    }

    libxml_use_internal_errors(true);
    $document = new \DOMDocument();
    $ok = $document->loadXML(
        '<root xmlns:podcast="https://podcastindex.org/namespace/1.0"'
        . ' xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">'
        . $body . '</root>'
    );
    libxml_clear_errors();

    return $ok ? $document : null;
}

/**
 * Elementos `itunes:duration` de un fragmento de item.
 *
 * @param \DOMDocument|null $document Documento del item.
 * @return \DOMElement[]
 */
function pde_durations($document)
{
    if (!$document instanceof \DOMDocument) {
        return array();
    }

    $list  = $document->getElementsByTagNameNS(
        'http://www.itunes.com/dtds/podcast-1.0.dtd',
        'duration'
    );
    $items = array();
    foreach ($list as $element) {
        $items[] = $element;
    }

    return $items;
}

/**
 * Emite el item de un episodio y devuelve sus tags `itunes:duration`.
 *
 * @param int $post_id Episodio.
 * @return \DOMElement[]
 */
function pde_emit($post_id)
{
    return pde_durations(pde_xml(\Atareao\PodcastFeed::itemElements($post_id)));
}

echo "podcast-duration — normalización y emisión de la duración\n";

pde_assert_true(is_file(PDE_FEED), 'existe includes/class-podcast-feed.php');
pde_assert_true(class_exists('\Atareao\PodcastFeed'), 'la clase \Atareao\PodcastFeed está disponible');
pde_assert_true(
    class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'normalizeDuration'),
    'existe PodcastFeed::normalizeDuration()'
);

if (!class_exists('\Atareao\PodcastFeed') || !method_exists('\Atareao\PodcastFeed', 'normalizeDuration')) {
    echo "\nResumen: {$GLOBALS['pde_passes']} PASS, " . count($GLOBALS['pde_failures']) . " FAIL\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// 1. Contrato puro de la normalización.
// ---------------------------------------------------------------------------
echo "\n--- Contrato de normalizeDuration() ---\n";

$pde_cases = array(
    // HH:MM:SS literal.
    array('00:22:11', '00:22:11', 'HH:MM:SS con horas a dos dígitos se devuelve literal'),
    array('1:02:03', '1:02:03', 'HH:MM:SS con una hora se devuelve literal, sin rellenar'),
    array('12:00:00', '12:00:00', 'HH:MM:SS con horas de dos dígitos se devuelve literal'),
    // MM:SS literal.
    array('22:11', '22:11', 'MM:SS válido se devuelve literal'),
    array('00:00', '00:00', 'MM:SS en cero se devuelve literal'),
    array('59:59', '59:59', 'MM:SS en el límite se devuelve literal'),
    // Entero no negativo literal.
    array('1331', '1331', 'el entero de segundos se devuelve literal'),
    array('0', '0', 'el entero cero se devuelve literal'),
    // Espacios exteriores.
    array(' 00:22:11 ', '00:22:11', 'el trim solo recorta los espacios exteriores'),
    // Vacío y malformados.
    array('', '', 'la cadena vacía devuelve la cadena vacía'),
    array('   ', '', 'solo espacios devuelve la cadena vacía'),
    array('abc', '', 'un valor no numérico devuelve la cadena vacía'),
    array('1:2:3', '', 'un componente de un dígito devuelve la cadena vacía'),
    array('00:22:11x', '', 'un sufijo devuelve la cadena vacía'),
    array('--5', '', 'un valor con signo devuelve la cadena vacía'),
    array('-5', '', 'un entero negativo devuelve la cadena vacía'),
    array('00 22 11', '', 'espacios internos devuelven la cadena vacía'),
    array('00:22:11:00', '', 'más de tres componentes devuelve la cadena vacía'),
    array('1:2', '', 'MM:SS con minutos de un dígito devuelve la cadena vacía'),
    // Fuera de rango.
    array('00:75:00', '', 'minutos 75 en HH:MM:SS devuelven la cadena vacía'),
    array('22:75', '', 'segundos 75 en MM:SS devuelven la cadena vacía'),
    array('00:22:99', '', 'segundos 99 en HH:MM:SS devuelven la cadena vacía'),
    array('99:00', '', 'minutos 99 en MM:SS devuelven la cadena vacía'),
);

foreach ($pde_cases as $pde_case) {
    pde_assert_equals(
        $pde_case[1],
        \Atareao\PodcastFeed::normalizeDuration($pde_case[0]),
        $pde_case[2]
    );
}

// ---------------------------------------------------------------------------
// 2. Emisión: el feed escribe el valor normalizado y omite el tag inválido.
// ---------------------------------------------------------------------------
echo "\n--- Emisión del itunes:duration ---\n";

harness_reset_state();
$GLOBALS['mock_is_feed']           = true;
$GLOBALS['mock_post_type_archive'] = 'podcast';

// Sin presupuesto remoto: la emisión no debe hacer peticiones de red.
add_filter('atareao_podcast_audio_length_budget_seconds', function () {
    return 0;
}, 10, 1);

$pde_episode = function ($id, $meta) {
    return array(
        'post' => new \WP_Post(array(
            'ID'          => $id,
            'post_type'   => 'podcast',
            'post_status' => 'publish',
            'post_title'  => 'Episodio ' . $id,
        )),
        'meta' => $meta,
    );
};

$pde_fixture = array(
    7001 => array('mp3-url' => 'https://example.test/a.mp3', 'number' => '1', 'duration' => '00:22:11'),
    7002 => array('mp3-url' => 'https://example.test/b.mp3', 'number' => '2', 'duration' => '22:11'),
    7003 => array('mp3-url' => 'https://example.test/c.mp3', 'number' => '3', 'duration' => '1331'),
    7004 => array('mp3-url' => 'https://example.test/d.mp3', 'number' => '4', 'duration' => '00:75:00'),
    7005 => array('mp3-url' => 'https://example.test/e.mp3', 'number' => '5', 'duration' => '22:75'),
    7006 => array('mp3-url' => 'https://example.test/f.mp3', 'number' => '6', 'duration' => '00:22:99'),
    7007 => array('mp3-url' => 'https://example.test/g.mp3', 'number' => '7', 'duration' => 'abc'),
    7008 => array('mp3-url' => 'https://example.test/h.mp3', 'number' => '8', 'duration' => '1:2:3'),
    7009 => array('mp3-url' => 'https://example.test/i.mp3', 'number' => '9', 'duration' => '00:22:11x'),
    7010 => array('mp3-url' => 'https://example.test/j.mp3', 'number' => '10', 'duration' => '--5'),
    7011 => array('mp3-url' => 'https://example.test/k.mp3', 'number' => '11'),
    7012 => array(
        'mp3-url'   => 'https://example.test/l.mp3',
        'number'    => '12',
        'duration'  => '00:30:00',
        '_duration' => '00:31:00',
    ),
    7013 => array(
        'mp3-url'   => 'https://example.test/m.mp3',
        'number'    => '13',
        '_duration' => '00:30:00',
    ),
);

$GLOBALS['mock_posts']     = array();
$GLOBALS['mock_post_meta'] = array();
foreach ($pde_fixture as $pde_id => $pde_meta) {
    $GLOBALS['mock_posts'][]             = new \WP_Post(array(
        'ID'          => $pde_id,
        'post_type'   => 'podcast',
        'post_status' => 'publish',
        'post_title'  => 'Episodio ' . $pde_id,
    ));
    $GLOBALS['mock_post_meta'][$pde_id] = $pde_meta;
}

// HH:MM:SS literal.
$pde_tags = pde_emit(7001);
pde_assert_equals(1, count($pde_tags), 'el item declara exactamente un itunes:duration');
pde_assert_equals('00:22:11', count($pde_tags) === 1 ? trim($pde_tags[0]->textContent) : '', 'HH:MM:SS se emite tal cual');

// MM:SS literal.
$pde_tags = pde_emit(7002);
pde_assert_equals('22:11', count($pde_tags) === 1 ? trim($pde_tags[0]->textContent) : '', 'MM:SS se emite tal cual');

// Entero de segundos literal, sin convertir a HH:MM:SS ni multiplicar.
$pde_tags = pde_emit(7003);
pde_assert_equals('1331', count($pde_tags) === 1 ? trim($pde_tags[0]->textContent) : '', 'el entero de segundos se emite tal cual');

// Fuera de rango: sin tag y XML válido.
foreach (array(7004 => '00:75:00', 7005 => '22:75', 7006 => '00:22:99') as $pde_id => $pde_value) {
    pde_assert_equals(0, count(pde_emit($pde_id)), 'un componente fuera de rango (' . $pde_value . ') omite el tag');
}

// Malformados: sin tag.
foreach (array(7007 => 'abc', 7008 => '1:2:3', 7009 => '00:22:11x', 7010 => '--5') as $pde_id => $pde_value) {
    pde_assert_equals(0, count(pde_emit($pde_id)), 'un valor malformado (' . $pde_value . ') omite el tag');
}

// Sin dato: sin tag, pero el resto del item se emite.
$pde_item = \Atareao\PodcastFeed::itemElements(7011);
pde_assert_equals(0, count(pde_durations(pde_xml($pde_item))), 'un episodio sin duración omite el tag');
pde_assert_true(strpos((string) $pde_item, '<enclosure ') !== false, 'sin duración el resto del item se emite con normalidad');

// Precedencia: el canónico gana y `_duration` solo es respaldo.
$pde_tags = pde_emit(7012);
pde_assert_equals('00:30:00', count($pde_tags) === 1 ? trim($pde_tags[0]->textContent) : '', 'si canónico y espejo difieren, gana el canónico');
$pde_tags = pde_emit(7013);
pde_assert_equals('00:30:00', count($pde_tags) === 1 ? trim($pde_tags[0]->textContent) : '', '_duration se usa solo como respaldo cuando el canónico está vacío');

// La emisión nunca escribe: el contrato es de lectura.
pde_assert_equals('00:22:11', $GLOBALS['mock_post_meta'][7001]['duration'], 'la emisión no modifica el metadato canónico');
pde_assert_true(!isset($GLOBALS['mock_post_meta'][7013]['duration']), 'la emisión no rellena el canónico desde el espejo');

echo "\nResumen: {$GLOBALS['pde_passes']} PASS, " . count($GLOBALS['pde_failures']) . " FAIL\n";

if (!empty($GLOBALS['pde_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['pde_failures'] as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

exit(0);
