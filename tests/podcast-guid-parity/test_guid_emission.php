<?php
/**
 * Arnés de la fase RED del change `2026-11-20-podcast-guid-parity`: emisión
 * literal del `<guid>` del feed del podcast.
 *
 * Ejercita el contrato del delta `podcast-feed` (requisito «Emisión literal del
 * guid del episodio»): el `<guid>` del item es el valor persistido **verbatim**,
 * escapado para XML y **no** como URL. El núcleo registra
 * `add_filter( 'the_guid', 'esc_url' )`, y `esc_url()` antepone `http://` a
 * cualquier cadena sin esquema; el filtro del plugin, con prioridad **posterior**
 * a 10, vuelve a fijar el guid persistido solo dentro del feed del podcast.
 *
 * Mismo estilo que `tests/podcast-feed-soberania/test_guid_mapping.php`. Se
 * ejecuta con `just test-guid-parity`.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('PGE_ROOT', dirname(__DIR__, 2));
define('PGE_AUDIO', PGE_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-audio.php');
define('PGE_FEED', PGE_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php');
define('PGE_GUID', PGE_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-guid.php');
define('PGE_ORIGIN', __DIR__ . '/fixtures/anchor-rss-missing.xml');

foreach (array(PGE_AUDIO, PGE_FEED, PGE_GUID) as $pge_bootstrap_file) {
    if (is_file($pge_bootstrap_file)) {
        require_once $pge_bootstrap_file;
    }
}

$GLOBALS['pge_passes']   = 0;
$GLOBALS['pge_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pge_pass($description)
{
    $GLOBALS['pge_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pge_fail($description)
{
    $GLOBALS['pge_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function pge_assert_true($condition, $description)
{
    if ($condition) {
        pge_pass($description);

        return;
    }

    pge_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function pge_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        pge_pass($description);

        return;
    }

    pge_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

/**
 * Emula el `esc_url` que el núcleo registra en `the_guid`.
 *
 * WordPress registra `add_filter( 'the_guid', 'esc_url' )` y `esc_url()` antepone
 * `http://` a cualquier cadena sin esquema: un UUID desnudo se convierte en
 * `http://<uuid>`. Es un doble independiente del código bajo prueba, que
 * reproduce el defecto para poder observar la corrección.
 *
 * @param string $guid Guid de entrada.
 * @return string Guid escapado como URL.
 */
function pge_core_the_guid_esc_url($guid)
{
    $guid = (string) $guid;
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $guid) === 1) {
        return $guid;
    }

    return 'http://' . $guid;
}

/**
 * Prioridad con la que el feed registró su callback en `the_guid`.
 *
 * @return int|null Prioridad, o `null` cuando no hay callback registrado.
 */
function pge_registered_priority()
{
    $filters = isset($GLOBALS['mock_filters']['the_guid']) ? $GLOBALS['mock_filters']['the_guid'] : array();
    $found   = null;

    foreach ($filters as $filter) {
        $callback = isset($filter['callback']) ? $filter['callback'] : null;
        $isTarget = is_array($callback)
            && isset($callback[0], $callback[1])
            && $callback[0] === 'Atareao\PodcastFeed'
            && $callback[1] === 'filterGuidEmission';

        if ($isTarget) {
            $found = (int) $filter['priority'];
        }
    }

    return $found;
}

echo "podcast-guid-parity — emisión literal del guid del feed\n";

pge_assert_true(is_file(PGE_FEED), 'existe includes/class-podcast-feed.php');
pge_assert_true(class_exists('\Atareao\PodcastFeed'), 'la clase \Atareao\PodcastFeed está disponible');

$pge_has_callback = class_exists('\Atareao\PodcastFeed')
    && method_exists('\Atareao\PodcastFeed', 'filterGuidEmission');
pge_assert_true($pge_has_callback, 'existe el callback filterGuidEmission()');

if (!$pge_has_callback) {
    echo "\nResumen: {$GLOBALS['pge_passes']} PASS, " . count($GLOBALS['pge_failures']) . " FAIL\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Fixture: dos episodios del podcast, uno con UUID desnudo y otro con URL.
// ---------------------------------------------------------------------------
$pge_uuid    = '773cd246-f771-4edb-9760-33dd3c775836';
$pge_url     = 'https://anchor.fm/s/5a5b39c/podcast/773cd246';
$pge_other   = '04fcb3a7-a18b-4fce-a5c7-368f12d46780';

harness_reset_state();
$GLOBALS['mock_is_feed']           = true;
$GLOBALS['mock_post_type_archive'] = 'podcast';
$GLOBALS['mock_posts']             = array(
    new WP_Post(array('ID' => 6001, 'post_type' => 'podcast', 'post_status' => 'publish')),
    new WP_Post(array('ID' => 6002, 'post_type' => 'podcast', 'post_status' => 'publish')),
    new WP_Post(array('ID' => 6003, 'post_type' => 'podcast', 'post_status' => 'publish')),
);
$GLOBALS['mock_post_meta']         = array(
    6001 => array('anchor-guid' => $pge_uuid),
    6002 => array('anchor-guid' => $pge_url),
    6003 => array('anchor-guid' => $pge_other),
);

// El núcleo registra `esc_url` en `the_guid` antes que el plugin: se emula en
// ese orden, que es el de prioridad real (10 antes que > 10).
add_filter('the_guid', 'pge_core_the_guid_esc_url', 10, 1);
\Atareao\PodcastFeed::init();

// ---------------------------------------------------------------------------
// 1. Registro: el callback existe, escucha `the_guid` y va después del núcleo.
// ---------------------------------------------------------------------------
echo "\n--- Registro del filtro ---\n";

$pge_priority = pge_registered_priority();
pge_assert_true($pge_priority !== null, 'el feed registra un callback en the_guid');
pge_assert_true(
    $pge_priority !== null && $pge_priority > 10,
    'la prioridad del callback es estrictamente mayor que 10 (posterior a esc_url)'
);

// ---------------------------------------------------------------------------
// 2. Emisión: el guid persistido se emite verbatim, sin esquema añadido.
// ---------------------------------------------------------------------------
echo "\n--- Emisión del guid ---\n";

$pge_core_value = pge_core_the_guid_esc_url($pge_uuid);
pge_assert_equals(
    'http://' . $pge_uuid,
    $pge_core_value,
    'la emulación del núcleo convierte el UUID desnudo en http://<uuid> (contexto del defecto)'
);

$pge_emitted = apply_filters('the_guid', $pge_uuid, 6001);
pge_assert_equals($pge_uuid, $pge_emitted, 'el guid desnudo se emite sin esquema');
pge_assert_true(strpos($pge_emitted, 'http://') === false, 'el guid emitido no lleva el prefijo http://');

pge_assert_equals(
    $pge_uuid,
    apply_filters('the_guid', $pge_core_value, 6001),
    'la corrección es posterior al escape del núcleo: restituye el valor persistido'
);

pge_assert_equals(
    $pge_url,
    apply_filters('the_guid', $pge_url, 6002),
    'un guid ya-URL se emite verbatim, sin re-escaparse'
);

// ---------------------------------------------------------------------------
// 3. Fuera del feed del podcast no hay efecto.
// ---------------------------------------------------------------------------
echo "\n--- Fuera del feed del podcast ---\n";

$GLOBALS['mock_is_feed']           = false;
$GLOBALS['mock_post_type_archive'] = false;

pge_assert_equals(
    'https://atareao.es/hello-world/',
    apply_filters('the_guid', 'https://atareao.es/hello-world/', 6001),
    'fuera del feed, el guid de una entrada normal no cambia'
);
pge_assert_equals(
    'http://' . $pge_uuid,
    apply_filters('the_guid', $pge_uuid, 6001),
    'fuera del feed, el callback no deshace el escape del núcleo'
);

// ---------------------------------------------------------------------------
// 4. Estabilidad y unicidad del guid.
// ---------------------------------------------------------------------------
echo "\n--- Estabilidad y unicidad ---\n";

$GLOBALS['mock_is_feed']           = true;
$GLOBALS['mock_post_type_archive'] = 'podcast';

$pge_first  = apply_filters('the_guid', $pge_uuid, 6001);
$pge_second = apply_filters('the_guid', $pge_uuid, 6001);
pge_assert_equals($pge_first, $pge_second, 'el guid es idéntico entre dos composiciones');
pge_assert_true(
    apply_filters('the_guid', $pge_uuid, 6001) !== apply_filters('the_guid', $pge_uuid, 6003),
    'dos episodios no comparten guid'
);

// ---------------------------------------------------------------------------
// 5. Paridad con el item homólogo del fixture de origen.
// ---------------------------------------------------------------------------
echo "\n--- Paridad con el origen ---\n";

$pge_origin_guid = '';
if (is_file(PGE_ORIGIN)) {
    foreach (\Atareao\PodcastGuid::parseOriginFeed((string) file_get_contents(PGE_ORIGIN)) as $pge_origin_item) {
        if (isset($pge_origin_item['number']) && (int) $pge_origin_item['number'] === 386) {
            $pge_origin_guid = (string) $pge_origin_item['guid'];
        }
    }
}

pge_assert_equals(
    '3974e7ad-1194-4fac-a0f9-b5995e1be1bd',
    $pge_origin_guid,
    'el fixture de origen declara el guid del episodio 386'
);

$GLOBALS['mock_post_meta'][6004] = array('anchor-guid' => $pge_origin_guid);
$GLOBALS['mock_posts'][]         = new WP_Post(array('ID' => 6004, 'post_type' => 'podcast', 'post_status' => 'publish'));
pge_assert_equals(
    $pge_origin_guid,
    apply_filters('the_guid', $pge_origin_guid, 6004),
    'el guid emitido para el episodio homólogo es idéntico al del origen'
);

echo "\nResumen: {$GLOBALS['pge_passes']} PASS, " . count($GLOBALS['pge_failures']) . " FAIL\n";

if (!empty($GLOBALS['pge_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['pge_failures'] as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

exit(0);
