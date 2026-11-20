<?php
/**
 * Arnés de la fase RED del change `2026-11-20-podcast-guid-parity`: importador
 * idempotente del episodio ausente del origen.
 *
 * Ejercita el contrato del delta `podcast-guid` (detección del episodio ausente,
 * creación con sus metadatos, idempotencia y simulación): el subcomando WP-CLI
 * `wp atareao podcast-guid-import-missing [--dry-run] [--author=<id>]` compara el
 * RSS de Anchor con el archivo de WordPress, por guid y por número, y crea las
 * entradas del CPT `podcast` que falten con sus metadatos.
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

define('PGI_ROOT', dirname(__DIR__, 2));
define('PGI_AUDIO', PGI_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-audio.php');
define('PGI_FEED', PGI_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php');
define('PGI_GUID', PGI_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-guid.php');
define('PGI_MISSING', __DIR__ . '/fixtures/anchor-rss-missing.xml');
define('PGI_EDGE', __DIR__ . '/fixtures/anchor-rss-edge.xml');

foreach (array(PGI_AUDIO, PGI_FEED, PGI_GUID) as $pgi_bootstrap_file) {
    if (is_file($pgi_bootstrap_file)) {
        require_once $pgi_bootstrap_file;
    }
}

$GLOBALS['pgi_passes']   = 0;
$GLOBALS['pgi_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pgi_pass($description)
{
    $GLOBALS['pgi_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pgi_fail($description)
{
    $GLOBALS['pgi_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function pgi_assert_true($condition, $description)
{
    if ($condition) {
        pgi_pass($description);

        return;
    }

    pgi_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function pgi_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        pgi_pass($description);

        return;
    }

    pgi_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

/**
 * Sirve un fixture como respuesta del RSS de Anchor.
 *
 * @param string $fixture_path Ruta del fixture.
 * @return void
 */
function pgi_set_origin($fixture_path)
{
    $GLOBALS['mock_remote_response'] = array(
        'response' => array('code' => 200),
        'headers'  => array(),
        'body'     => (string) file_get_contents($fixture_path),
    );
    $GLOBALS['mock_transients'] = array();
}

/**
 * Siembra el archivo de WordPress con los episodios ya presentes.
 *
 * @param array[] $episodes Lista de `id`, `number`, `guid`, `author` y `date`.
 * @return void
 */
function pgi_seed_wordpress(array $episodes)
{
    $GLOBALS['mock_posts']     = array();
    $GLOBALS['mock_post_meta'] = array();

    foreach ($episodes as $episode) {
        $GLOBALS['mock_posts'][] = new WP_Post(array(
            'ID'          => $episode['id'],
            'post_type'   => 'podcast',
            'post_status' => 'publish',
            'post_author' => $episode['author'],
            'post_date'   => $episode['date'],
        ));
        $GLOBALS['mock_post_meta'][$episode['id']] = array(
            'anchor-guid' => $episode['guid'],
            'number'      => $episode['number'],
        );
    }
}

/**
 * Entrada recién insertada con un número de episodio dado.
 *
 * @param int $number Número de episodio.
 * @return \WP_Post|null
 */
function pgi_inserted_by_number($number)
{
    $inserted = isset($GLOBALS['mock_inserted_posts']) ? $GLOBALS['mock_inserted_posts'] : array();
    foreach ($inserted as $post) {
        $isTarget = $post instanceof \WP_Post
            && (int) get_post_meta($post->ID, \Atareao\PodcastFeed::NUMBER_META, true) === (int) $number;

        if ($isTarget) {
            return $post;
        }
    }

    return null;
}

/**
 * Mensajes de un tipo dados por el doble de WP-CLI.
 *
 * @param string $type Tipo (`log`, `success`, `warning` o `error`).
 * @return string[]
 */
function pgi_cli_messages($type)
{
    $messages = array();
    $output   = isset($GLOBALS['mock_cli_output']) ? $GLOBALS['mock_cli_output'] : array();
    foreach ($output as $entry) {
        if (isset($entry['type'], $entry['message']) && $entry['type'] === $type) {
            $messages[] = (string) $entry['message'];
        }
    }

    return $messages;
}

/**
 * `true` cuando el doble de WP-CLI registró algún mensaje de un tipo.
 *
 * @param string $type Tipo.
 * @return bool
 */
function pgi_cli_has($type)
{
    return pgi_cli_messages($type) !== array();
}

/**
 * Sembrado habitual: el 385 y el 387 ya están en WordPress (falta el 386).
 *
 * @return void
 */
function pgi_seed_neighbours()
{
    pgi_seed_wordpress(array(
        array('id' => 16215, 'number' => 385, 'guid' => 'aaaaaaaa-1111-5111-8111-aaaaaaaaaaaa', 'author' => 3, 'date' => '2022-05-12 05:00:00'),
        array('id' => 16217, 'number' => 387, 'guid' => 'cccccccc-1111-5111-8111-cccccccccccc', 'author' => 7, 'date' => '2022-05-26 05:00:00'),
    ));
}

echo "podcast-guid-parity — importador del episodio ausente\n";

pgi_assert_true(is_file(PGI_GUID), 'existe includes/class-podcast-guid.php');
pgi_assert_true(class_exists('\Atareao\PodcastGuid'), 'la clase \Atareao\PodcastGuid está disponible');

// ---------------------------------------------------------------------------
// 0. El parser expone temporada y fecha del origen (contrato de reutilización).
// ---------------------------------------------------------------------------
echo "\n--- Parser del origen ---\n";

$pgi_parser_ok = class_exists('\Atareao\PodcastGuid') && method_exists('\Atareao\PodcastGuid', 'parseOriginFeed');
pgi_assert_true($pgi_parser_ok, 'existe parseOriginFeed()');

if ($pgi_parser_ok) {
    $pgi_items = \Atareao\PodcastGuid::parseOriginFeed((string) file_get_contents(PGI_MISSING));
    $pgi_386   = null;
    foreach ($pgi_items as $pgi_item) {
        if (isset($pgi_item['guid']) && $pgi_item['guid'] === '3974e7ad-1194-4fac-a0f9-b5995e1be1bd') {
            $pgi_386 = $pgi_item;
        }
    }

    pgi_assert_true($pgi_386 !== null, 'el parser encuentra el item 386 por su guid');
    if ($pgi_386 !== null) {
        pgi_assert_equals(4, $pgi_386['season'], 'el parser extrae la temporada del origen');
        pgi_assert_equals(
            'Thu, 19 May 2022 03:27:16 GMT',
            $pgi_386['pub_date'],
            'el parser extrae la fecha del origen'
        );
        pgi_assert_equals('00:22:04', $pgi_386['duration'], 'el parser extrae la duración verbatim');
        pgi_assert_true(
            isset($pgi_386['description']) && strpos((string) $pgi_386['description'], 'Notas del episodio 386') !== false,
            'el parser extrae la descripción del origen'
        );
    }
}

$pgi_has_import = class_exists('\Atareao\PodcastGuid')
    && method_exists('\Atareao\PodcastGuid', 'importMissingEpisodes');
pgi_assert_true($pgi_has_import, 'existe el método importMissingEpisodes()');

if (!$pgi_has_import) {
    echo "\nResumen: {$GLOBALS['pgi_passes']} PASS, " . count($GLOBALS['pgi_failures']) . " FAIL\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// 1. Detección y creación del episodio ausente (386).
// ---------------------------------------------------------------------------
echo "\n--- Detección y creación ---\n";

harness_reset_state();
pgi_set_origin(PGI_MISSING);
pgi_seed_neighbours();

$pgi_report = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
pgi_assert_equals(1, (int) $pgi_report['missing'], 'se detecta un único episodio ausente');
pgi_assert_equals(1, (int) $pgi_report['created'], 'se crea un episodio');
pgi_assert_equals(1, count($GLOBALS['mock_inserted_posts']), 'el almacén registra una única inserción');

$pgi_created = pgi_inserted_by_number(386);
pgi_assert_true($pgi_created instanceof \WP_Post, 'el episodio creado es el 386');

if ($pgi_created instanceof \WP_Post) {
    pgi_assert_equals('podcast', $pgi_created->post_type, 'el episodio importado es del CPT podcast');
    pgi_assert_equals('publish', $pgi_created->post_status, 'el episodio importado se publica');
    pgi_assert_equals(
        'ATA386 Aventuras y desventuras con Docker y las actualizaciones automáticas',
        $pgi_created->post_title,
        'el título es el del origen'
    );
    pgi_assert_equals('2022-05-19 05:27:16', $pgi_created->post_date, 'la fecha se interpreta en Europe/Madrid');
    pgi_assert_equals(7, (int) $pgi_created->post_author, 'el autor se hereda del episodio más reciente');
    pgi_assert_equals(
        '3974e7ad-1194-4fac-a0f9-b5995e1be1bd',
        get_post_meta($pgi_created->ID, \Atareao\PodcastFeed::GUID_META, true),
        'se persiste el anchor-guid verbatim'
    );
    pgi_assert_equals(
        386,
        (int) get_post_meta($pgi_created->ID, \Atareao\PodcastFeed::NUMBER_META, true),
        'se persiste el número del episodio'
    );
    pgi_assert_equals(
        4,
        (int) get_post_meta($pgi_created->ID, \Atareao\PodcastFeed::SEASON_META, true),
        'se persiste la temporada'
    );
    pgi_assert_equals(
        '00:22:04',
        (string) get_post_meta($pgi_created->ID, \Atareao\PodcastFeed::DURATION_META, true),
        'la duración es verbatim de Anchor'
    );
    pgi_assert_equals(
        'https://anchor.fm/s/5a5b39c/podcast/play/52219442/https%3A%2F%2Fd3ctxlq1ktw2nl.cloudfront.net%2Fstaging%2F2022-4-19%2F182a1c9b-7a6b-acf0-0609-b70bafd40328.mp3',
        get_post_meta($pgi_created->ID, \Atareao\PodcastFeed::AUDIO_META, true),
        'se persiste la URL del audio verbatim'
    );
    pgi_assert_equals(
        '',
        (string) get_post_meta($pgi_created->ID, \Atareao\PodcastFeed::TRANSCRIPT_META, true),
        'sin transcript en el origen no se escribe transcript-srt'
    );
    pgi_assert_true(
        strpos((string) $pgi_created->post_content, 'Notas del episodio 386') !== false,
        'el contenido del episodio es la descripción del origen'
    );
    pgi_assert_true(
        strpos((string) $pgi_created->post_content, '<script') === false,
        'el marcado peligroso del origen no llega al contenido (wp_kses_post)'
    );
    pgi_assert_true(
        !empty($GLOBALS['mock_kses_post_calls'])
        && strpos((string) $GLOBALS['mock_kses_post_calls'][0], '<script') !== false,
        'el contenido crudo del origen pasa por wp_kses_post()'
    );
    pgi_assert_equals(0, count($GLOBALS['mock_object_terms']), 'el episodio importado no recibe términos de taxonomía');
}

// ---------------------------------------------------------------------------
// 2. Idempotencia: la segunda pasada no inserta nada.
// ---------------------------------------------------------------------------
echo "\n--- Idempotencia ---\n";

$pgi_again = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
pgi_assert_equals(0, (int) $pgi_again['missing'], 'la segunda pasada no detecta ausentes');
pgi_assert_equals(0, (int) $pgi_again['created'], 'la segunda pasada no crea nada');
pgi_assert_equals(1, count($GLOBALS['mock_inserted_posts']), 'la segunda pasada no duplica la entrada');

// ---------------------------------------------------------------------------
// 3. El ensayo no escribe nada.
// ---------------------------------------------------------------------------
echo "\n--- Ensayo (--dry-run) ---\n";

harness_reset_state();
pgi_set_origin(PGI_MISSING);
pgi_seed_neighbours();

$pgi_dry = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => true));
pgi_assert_equals(1, (int) $pgi_dry['missing'], 'el ensayo detecta el episodio ausente');
pgi_assert_equals(0, (int) $pgi_dry['created'], 'el ensayo no crea nada');
pgi_assert_equals(0, count($GLOBALS['mock_inserted_posts']), 'el ensayo no inserta ninguna entrada');
pgi_assert_equals(2, count($GLOBALS['mock_posts']), 'el archivo no cambia con el ensayo');

// Ensayo seguido de ejecución real, sin reiniciar el estado entre medias: el
// ensayo no envenena el archivo y la caché del origen se reutiliza.
$pgi_after_dry = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
pgi_assert_equals(1, (int) $pgi_after_dry['created'], 'tras el ensayo, la ejecución real crea exactamente el episodio reportado');
pgi_assert_equals(1, count($GLOBALS['mock_inserted_posts']), 'el ensayo no escribió y la ejecución real escribió una entrada');
pgi_assert_true(pgi_inserted_by_number(386) instanceof \WP_Post, 'la ejecución real crea el 386 que el ensayo había reportado');

// ---------------------------------------------------------------------------
// 4. El subcomando WP-CLI: registro, recuentos, --author, --dry-run y error.
// ---------------------------------------------------------------------------
echo "\n--- Subcomando WP-CLI ---\n";

harness_reset_state();
pgi_set_origin(PGI_MISSING);
pgi_seed_neighbours();

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}
\Atareao\PodcastGuid::init();

$pgi_command = 'atareao podcast-guid-import-missing';
pgi_assert_true(
    isset($GLOBALS['mock_cli_commands'][$pgi_command]),
    'el subcomando atareao podcast-guid-import-missing queda registrado'
);

if (isset($GLOBALS['mock_cli_commands'][$pgi_command])) {
    $pgi_callback = $GLOBALS['mock_cli_commands'][$pgi_command];

    // 4.1 Ejecución real: recuentos exactos y --author.
    $GLOBALS['mock_cli_output'] = array();
    call_user_func($pgi_callback, array(), array('author' => 9));

    $pgi_cli_post = pgi_inserted_by_number(386);
    pgi_assert_true($pgi_cli_post instanceof \WP_Post, 'el subcomando crea el episodio ausente');
    if ($pgi_cli_post instanceof \WP_Post) {
        pgi_assert_equals(9, (int) $pgi_cli_post->post_author, '--author fuerza el autor del episodio');
    }
    pgi_assert_true(
        in_array('Episodios ausentes: 1', pgi_cli_messages('log'), true),
        'el subcomando reporta el recuento exacto de ausentes (1)'
    );
    pgi_assert_true(
        in_array('Creados: 1', pgi_cli_messages('log'), true),
        'el subcomando reporta el recuento exacto de creados (1)'
    );
    pgi_assert_true(pgi_cli_has('success'), 'la ejecución real termina con éxito');

    // 4.2 Ensayo: rotula «Habría creado» y no escribe.
    harness_reset_state();
    pgi_set_origin(PGI_MISSING);
    pgi_seed_neighbours();

    $GLOBALS['mock_cli_output'] = array();
    call_user_func($pgi_callback, array(), array('dry-run' => true));

    pgi_assert_true(
        in_array('Habría creado: 1', pgi_cli_messages('log'), true),
        'el ensayo rotula «Habría creado: N» con el recuento exacto'
    );
    pgi_assert_equals(0, count($GLOBALS['mock_inserted_posts']), 'el ensayo del subcomando no inserta nada');
    pgi_assert_true(pgi_cli_has('success'), 'el ensayo termina con éxito');

    // 4.3 Origen inaccesible: error de WP-CLI, sin éxito ni escrituras.
    harness_reset_state();
    pgi_seed_wordpress(array());
    $GLOBALS['mock_remote_response'] = new WP_Error('http_request_failed', 'sin red');
    $GLOBALS['mock_transients']      = array();

    $GLOBALS['mock_cli_output'] = array();
    call_user_func($pgi_callback, array(), array());

    pgi_assert_true(pgi_cli_has('error'), 'un origen inaccesible se reporta con WP_CLI::error');
    pgi_assert_true(!pgi_cli_has('success'), 'un origen inaccesible no declara éxito');
    pgi_assert_equals(0, count($GLOBALS['mock_inserted_posts']), 'un origen inaccesible no escribe desde el subcomando');
}

// ---------------------------------------------------------------------------
// 5. Origen inaccesible o inválido: no se escribe nada.
// ---------------------------------------------------------------------------
echo "\n--- Origen inaccesible ---\n";

harness_reset_state();
pgi_seed_wordpress(array());
$GLOBALS['mock_remote_response'] = new WP_Error('http_request_failed', 'sin red');
$GLOBALS['mock_transients']      = array();

$pgi_fail = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
pgi_assert_equals(0, (int) $pgi_fail['created'], 'un origen inaccesible no crea nada');
pgi_assert_true(!empty($pgi_fail['origin_error']), 'un origen inaccesible se marca como error de origen');
pgi_assert_equals(0, (int) $pgi_fail['failed'], 'un origen inaccesible no se cuenta como fallo de creación');
pgi_assert_equals(0, count($GLOBALS['mock_inserted_posts']), 'un origen inaccesible no escribe');

harness_reset_state();
pgi_seed_wordpress(array());
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 200),
    'headers'  => array(),
    'body'     => '<rss><channel><item></rss',
);
$GLOBALS['mock_transients'] = array();

$pgi_bad = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
pgi_assert_equals(0, (int) $pgi_bad['created'], 'un XML inválido no crea nada');
pgi_assert_true(!empty($pgi_bad['origin_error']), 'un XML inválido se marca como error de origen');
pgi_assert_equals(0, count($GLOBALS['mock_inserted_posts']), 'un XML inválido no escribe');

// ---------------------------------------------------------------------------
// 6. Casos borde: metadato ausente se omite y item sin guid ni número se salta.
// ---------------------------------------------------------------------------
echo "\n--- Casos borde ---\n";

harness_reset_state();
pgi_set_origin(PGI_EDGE);
pgi_seed_wordpress(array());

$pgi_edge = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
pgi_assert_equals(1, (int) $pgi_edge['missing'], 'se detecta el episodio ausente con metadatos parciales');
pgi_assert_equals(1, (int) $pgi_edge['created'], 'se crea el episodio con metadatos parciales');
pgi_assert_equals(1, (int) $pgi_edge['skipped'], 'un item sin guid ni número se omite');

$pgi_388 = pgi_inserted_by_number(388);
pgi_assert_true($pgi_388 instanceof \WP_Post, 'el episodio creado es el 388');
if ($pgi_388 instanceof \WP_Post) {
    pgi_assert_equals(
        '',
        (string) get_post_meta($pgi_388->ID, \Atareao\PodcastFeed::SEASON_META, true),
        'un metadato ausente en el origen no se inventa'
    );
    pgi_assert_equals(
        'dddddddd-1111-5111-8111-dddddddddddd',
        get_post_meta($pgi_388->ID, \Atareao\PodcastFeed::GUID_META, true),
        'el guid del episodio parcial se persiste igualmente'
    );
    pgi_assert_equals(
        '',
        (string) $pgi_388->post_content,
        'sin descripción en el origen no se escribe contenido'
    );
}

// ---------------------------------------------------------------------------
// 7. La homología también casa solo por número, con guid distinto.
// ---------------------------------------------------------------------------
echo "\n--- Homología por número ---\n";

harness_reset_state();
pgi_set_origin(PGI_MISSING);
pgi_seed_wordpress(array(
    array('id' => 16215, 'number' => 385, 'guid' => 'aaaaaaaa-1111-5111-8111-aaaaaaaaaaaa', 'author' => 3, 'date' => '2022-05-12 05:00:00'),
    array('id' => 16216, 'number' => 386, 'guid' => 'ffffffff-2222-5222-8222-ffffffffffff', 'author' => 7, 'date' => '2022-05-19 05:27:16'),
    array('id' => 16217, 'number' => 387, 'guid' => 'cccccccc-1111-5111-8111-cccccccccccc', 'author' => 7, 'date' => '2022-05-26 05:00:00'),
));

$pgi_bynumber = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
pgi_assert_equals(0, (int) $pgi_bynumber['missing'], 'el mismo número no se considera ausente aunque el guid difiera');
pgi_assert_equals(0, (int) $pgi_bynumber['created'], 'no se crea nada cuando la homología casa por número');
pgi_assert_equals(0, count($GLOBALS['mock_inserted_posts']), 'no se inserta nada cuando la homología casa por número');

// ---------------------------------------------------------------------------
// 8. Origen válido pero sin items: no se inventa nada.
// ---------------------------------------------------------------------------
echo "\n--- Origen vacío ---\n";

harness_reset_state();
pgi_seed_wordpress(array());
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 200),
    'headers'  => array(),
    'body'     => '<?xml version="1.0"?><rss version="2.0"><channel><title>Vacío</title></channel></rss>',
);
$GLOBALS['mock_transients'] = array();

$pgi_empty = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
pgi_assert_equals(0, (int) $pgi_empty['missing'], 'un origen sin items no declara episodios ausentes');
pgi_assert_equals(0, (int) $pgi_empty['created'], 'un origen sin items no crea nada');
pgi_assert_equals(0, count($GLOBALS['mock_inserted_posts']), 'un origen sin items no escribe');
pgi_assert_true(!empty($pgi_empty['origin_error']), 'un origen sin items se trata como origen ilegible');

// ---------------------------------------------------------------------------
// 9. Fallo de creación: `failed` sin confundirse con el origen.
// ---------------------------------------------------------------------------
echo "\n--- Fallo de creación ---\n";

harness_reset_state();
pgi_set_origin(PGI_MISSING);
pgi_seed_neighbours();
$GLOBALS['mock_insert_post_failure'] = true;

$pgi_create_fail = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
pgi_assert_equals(1, (int) $pgi_create_fail['missing'], 'se detecta el episodio ausente antes de fallar');
pgi_assert_equals(0, (int) $pgi_create_fail['created'], 'un fallo de wp_insert_post no crea nada');
pgi_assert_equals(1, (int) $pgi_create_fail['failed'], 'el fallo de creación se cuenta en `failed`');
pgi_assert_true(empty($pgi_create_fail['origin_error']), 'el fallo de creación no se confunde con el origen');
pgi_assert_equals(0, count($GLOBALS['mock_inserted_posts']), 'un fallo de wp_insert_post no registra inserción');

// ---------------------------------------------------------------------------
// 10. El autor heredado salta los episodios sin autor.
// ---------------------------------------------------------------------------
echo "\n--- Autor heredado ---\n";

harness_reset_state();
pgi_set_origin(PGI_MISSING);
pgi_seed_wordpress(array(
    array('id' => 16215, 'number' => 385, 'guid' => 'aaaaaaaa-1111-5111-8111-aaaaaaaaaaaa', 'author' => 0, 'date' => '2022-06-02 05:00:00'),
    array('id' => 16217, 'number' => 387, 'guid' => 'cccccccc-1111-5111-8111-cccccccccccc', 'author' => 7, 'date' => '2022-05-26 05:00:00'),
));

$pgi_author      = \Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));
$pgi_author_post = pgi_inserted_by_number(386);
pgi_assert_true($pgi_author_post instanceof \WP_Post, 'se crea el episodio ausente con autor heredado');
if ($pgi_author_post instanceof \WP_Post) {
    pgi_assert_equals(
        7,
        (int) $pgi_author_post->post_author,
        'el autor heredado es el del episodio más reciente CON autor'
    );
}

echo "\nResumen: {$GLOBALS['pgi_passes']} PASS, " . count($GLOBALS['pgi_failures']) . " FAIL\n";

if (!empty($GLOBALS['pgi_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['pgi_failures'] as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

exit(0);
