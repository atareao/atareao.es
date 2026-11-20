<?php
/**
 * Arnés de la fase RED del change `2026-11-20-podcast-duration-backfill`:
 * backfill idempotente de la duración desde el RSS de Anchor.
 *
 * Ejercita el contrato del delta `podcast-feed` (requisito «Duración del
 * episodio en el feed», apartado «Backfill desde Anchor»): fuente resuelta,
 * emparejamiento por `anchor-guid` y por número único, validación con
 * `PodcastFeed::normalizeDuration()`, escritura solo si difiere a través del
 * punto único (`PodcastDuration::writeDuration()`), informe, `--dry-run`,
 * `--limit`, `--source`, idempotencia y origen ilegible.
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

define('PBD_ROOT', dirname(__DIR__, 2));
define('PBD_INCLUDES', PBD_ROOT . '/wp-content/plugins/atareao-functionality/includes');
define('PBD_DURATION', PBD_INCLUDES . '/class-podcast-duration.php');
define('PBD_AUDIO', PBD_INCLUDES . '/class-podcast-audio.php');
define('PBD_FEED', PBD_INCLUDES . '/class-podcast-feed.php');
define('PBD_GUID', PBD_INCLUDES . '/class-podcast-guid.php');
define('PBD_FIXTURE', __DIR__ . '/fixtures/anchor-rss-duration.xml');
define('PBD_MISSING', dirname(__DIR__) . '/podcast-guid-parity/fixtures/anchor-rss-missing.xml');

foreach (array(PBD_AUDIO, PBD_FEED, PBD_GUID, PBD_DURATION) as $pbd_bootstrap_file) {
    if (is_file($pbd_bootstrap_file)) {
        require_once $pbd_bootstrap_file;
    }
}

$GLOBALS['pbd_passes']   = 0;
$GLOBALS['pbd_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pbd_pass($description)
{
    $GLOBALS['pbd_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pbd_fail($description)
{
    $GLOBALS['pbd_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function pbd_assert_true($condition, $description)
{
    if ($condition) {
        pbd_pass($description);

        return;
    }

    pbd_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function pbd_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        pbd_pass($description);

        return;
    }

    pbd_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

/**
 * Sirve el fixture como respuesta del RSS de Anchor.
 *
 * @param string $path Ruta del fixture.
 * @return void
 */
function pbd_serve($path)
{
    $GLOBALS['mock_remote_response'] = array(
        'response' => array('code' => 200),
        'headers'  => array(),
        'body'     => (string) file_get_contents($path),
    );
    $GLOBALS['mock_transients'] = array();
}

/**
 * Una entrada del archivo de WordPress con sus metadatos.
 *
 * @param int        $id    Identificador.
 * @param array|null $meta  Metadatos, o null para un episodio sin metas.
 * @return array{0:\\WP_Post,1:array}
 */
function pbd_episode($id, $meta)
{
    return array(
        new \WP_Post(array(
            'ID'          => $id,
            'post_type'   => 'podcast',
            'post_status' => 'publish',
            'post_title'  => 'Episodio ' . $id,
        )),
        $meta === null ? array() : $meta,
    );
}

/**
 * Siembra el archivo de WordPress con el escenario completo del backfill.
 *
 * @return void
 */
function pbd_seed()
{
    $guid = '11111111-1662-5111-8111-111111111111';
    $episodes = array(
        // 60× corta y con espejo: se corrige.
        8001 => array(
            'mp3-url'   => 'https://example.test/16628.mp3',
            'anchor-guid' => $guid,
            'number'    => '16628',
            'duration'  => '00:22',
            '_duration' => '00:22',
        ),
        // Entero de minutos sueltos, sin espejo: se corrige y se crea el espejo.
        8002 => array(
            'mp3-url'   => 'https://example.test/17764.mp3',
            'anchor-guid' => '22222222-1776-5111-8111-222222222222',
            'number'    => '17764',
            'duration'  => '20',
        ),
        // Sin duración: se rellena.
        8003 => array(
            'mp3-url'   => 'https://example.test/850.mp3',
            'anchor-guid' => '33333333-0850-5111-8111-333333333333',
            'number'    => '850',
        ),
        // Ya correcto: sin cambios.
        8004 => array(
            'mp3-url'   => 'https://example.test/900.mp3',
            'anchor-guid' => '44444444-0900-5111-8111-444444444444',
            'number'    => '900',
            'duration'  => '00:45:00',
            '_duration' => '00:45:00',
        ),
        // Anchor ilegible: no se escribe.
        8005 => array(
            'mp3-url'   => 'https://example.test/901.mp3',
            'anchor-guid' => '55555555-0901-5111-8111-555555555555',
            'number'    => '901',
            'duration'  => '00:01:00',
            '_duration' => '00:01:00',
        ),
        // Sin homólogo: no se toca.
        8006 => array(
            'mp3-url'   => 'https://example.test/999.mp3',
            'anchor-guid' => '00000000-0000-5000-8000-000000000000',
            'number'    => '999',
            'duration'  => '00:11:11',
            '_duration' => '00:11:11',
        ),
        // Número ambiguo (dos items ATA 950): sin correspondencia.
        8007 => array(
            'mp3-url'   => 'https://example.test/950.mp3',
            'number'    => '950',
            'duration'  => '00:02:02',
            '_duration' => '00:02:02',
        ),
        // Sin guid y número único: respaldo por número.
        8008 => array(
            'mp3-url'   => 'https://example.test/903.mp3',
            'number'    => '903',
            'duration'  => '00:03:03',
            '_duration' => '00:03:03',
        ),
        // Guid con espacios exteriores: el emparejamiento compara tras trim.
        8009 => array(
            'mp3-url'   => 'https://example.test/906.mp3',
            'anchor-guid' => '  bbbbbbbb-0906-5111-8111-bbbbbbbbbbbb  ',
            'number'    => '906',
        ),
        // Quinto cambio, para el límite.
        8010 => array(
            'mp3-url'   => 'https://example.test/905.mp3',
            'anchor-guid' => 'aaaaaaaa-0905-5111-8111-aaaaaaaaaaaa',
            'number'    => '905',
            'duration'  => '00:05:05',
            '_duration' => '00:05:05',
        ),
    );

    $GLOBALS['mock_posts']     = array();
    $GLOBALS['mock_post_meta'] = array();
    foreach ($episodes as $id => $meta) {
        $episode = pbd_episode($id, $meta);
        $GLOBALS['mock_posts'][]             = $episode[0];
        $GLOBALS['mock_post_meta'][$id]      = $episode[1];
    }
}

/**
 * Instantánea de los metadatos de los episodios sembrados.
 *
 * @return array
 */
function pbd_snapshot()
{
    return isset($GLOBALS['mock_post_meta']) ? $GLOBALS['mock_post_meta'] : array();
}

/**
 * Mensajes de WP-CLI de un tipo.
 *
 * @param string $type Tipo (`log`, `success`, `error`).
 * @return string[]
 */
function pbd_cli_messages($type)
{
    $messages = array();
    foreach (isset($GLOBALS['mock_cli_output']) ? $GLOBALS['mock_cli_output'] : array() as $entry) {
        if (isset($entry['type'], $entry['message']) && $entry['type'] === $type) {
            $messages[] = (string) $entry['message'];
        }
    }

    return $messages;
}

/**
 * Si el informe declara un origen concreto.
 *
 * @param array  $report Informe.
 * @param string $origin Origen esperado.
 * @return bool
 */
function pbd_origin_is($report, $origin)
{
    return isset($report['origin']) && (string) $report['origin'] === $origin;
}

echo "podcast-duration — backfill desde Anchor\n";

pbd_assert_true(is_file(PBD_DURATION), 'existe includes/class-podcast-duration.php');
pbd_assert_true(
    class_exists('\Atareao\PodcastDuration'),
    'la clase \Atareao\PodcastDuration está disponible'
);
pbd_assert_true(
    class_exists('\Atareao\PodcastDuration') && method_exists('\Atareao\PodcastDuration', 'writeDuration'),
    'existe PodcastDuration::writeDuration()'
);
pbd_assert_true(
    class_exists('\Atareao\PodcastDuration') && method_exists('\Atareao\PodcastDuration', 'backfill'),
    'existe PodcastDuration::backfill()'
);

if (!class_exists('\Atareao\PodcastDuration')
    || !method_exists('\Atareao\PodcastDuration', 'writeDuration')
    || !method_exists('\Atareao\PodcastDuration', 'backfill')
) {
    echo "\nResumen: {$GLOBALS['pbd_passes']} PASS, " . count($GLOBALS['pbd_failures']) . " FAIL\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// 1. Punto único de escritura: canónico y espejo, solo si cambia.
// ---------------------------------------------------------------------------
echo "\n--- writeDuration(): canónico y espejo ---\n";

harness_reset_state();
$GLOBALS['mock_post_meta'][8101] = array('duration' => '00:22', '_duration' => '00:22');

pbd_assert_true(
    \Atareao\PodcastDuration::writeDuration(8101, '00:22:11'),
    'writeDuration() escribe cuando el valor difiere'
);
pbd_assert_equals(
    '00:22:11',
    $GLOBALS['mock_post_meta'][8101]['duration'],
    'writeDuration() escribe el canónico'
);
pbd_assert_equals(
    '00:22:11',
    $GLOBALS['mock_post_meta'][8101]['_duration'],
    'writeDuration() escribe el espejo con el mismo valor'
);
pbd_assert_true(
    !\Atareao\PodcastDuration::writeDuration(8101, '00:22:11'),
    'writeDuration() no reescribe cuando ambos metas ya coinciden'
);

$GLOBALS['mock_post_meta'][8102] = array('duration' => '00:30:00');
pbd_assert_true(
    \Atareao\PodcastDuration::writeDuration(8102, '00:30:00'),
    'writeDuration() rellena el espejo cuando falta'
);
pbd_assert_equals(
    '00:30:00',
    $GLOBALS['mock_post_meta'][8102]['_duration'],
    'el espejo rellenado lleva el valor del canónico'
);

$GLOBALS['mock_post_meta'][8103] = array('duration' => '00:40:00', '_duration' => '00:40:00');
pbd_assert_true(
    !\Atareao\PodcastDuration::writeDuration(8103, 'invalida'),
    'writeDuration() rechaza un valor no normalizable'
);
pbd_assert_equals('00:40:00', $GLOBALS['mock_post_meta'][8103]['duration'], 'un valor inválido no toca el canónico');
pbd_assert_equals('00:40:00', $GLOBALS['mock_post_meta'][8103]['_duration'], 'un valor inválido no toca el espejo');

// ---------------------------------------------------------------------------
// 2. Pasada real: rellena, corrige, respeta y reporta.
// ---------------------------------------------------------------------------
echo "\n--- Pasada real ---\n";

harness_reset_state();
pbd_seed();
pbd_serve(PBD_FIXTURE);

$pbd_report = \Atareao\PodcastDuration::backfill();

pbd_assert_equals(6, (int) $pbd_report['actualizados'], 'la pasada actualiza seis episodios');
pbd_assert_equals(1, (int) $pbd_report['sin_cambios'], 'un episodio ya correcto queda en sin_cambios');
pbd_assert_equals(2, (int) $pbd_report['sin_correspondencia'], 'dos episodios sin homólogo van a sin_correspondencia');
pbd_assert_equals(1, (int) $pbd_report['ilegibles'], 'un valor ilegible de Anchor va a ilegibles');
pbd_assert_equals(0, (int) $pbd_report['pendientes'], 'sin límite no hay pendientes');

pbd_assert_equals('00:22:11', $GLOBALS['mock_post_meta'][8001]['duration'], 'corrige la duración 60× corta 00:22');
pbd_assert_equals('00:22:11', $GLOBALS['mock_post_meta'][8001]['_duration'], 'el espejo de 8001 queda coherente');
pbd_assert_equals('00:20:37', $GLOBALS['mock_post_meta'][8002]['duration'], 'corrige el entero de minutos 20 a 00:20:37');
pbd_assert_equals('00:20:37', $GLOBALS['mock_post_meta'][8002]['_duration'], 'crea el espejo que faltaba en 8002');
pbd_assert_equals('00:22:04', $GLOBALS['mock_post_meta'][8003]['duration'], 'rellena un episodio sin duración');
pbd_assert_equals('00:22:04', $GLOBALS['mock_post_meta'][8003]['_duration'], 'el relleno escribe también el espejo');
pbd_assert_equals('00:33:33', $GLOBALS['mock_post_meta'][8008]['duration'], 'el respaldo por número único aplica la duración');
pbd_assert_equals('00:09:09', $GLOBALS['mock_post_meta'][8009]['duration'], 'el guid se compara tras trim y aplica la duración');
pbd_assert_equals('00:55:55', $GLOBALS['mock_post_meta'][8010]['duration'], 'un quinto episodio también se actualiza');

pbd_assert_equals('00:45:00', $GLOBALS['mock_post_meta'][8004]['duration'], 'un episodio ya correcto no se reescribe');
pbd_assert_equals('00:11:11', $GLOBALS['mock_post_meta'][8006]['duration'], 'sin correspondencia conserva su duración');
pbd_assert_equals('00:11:11', $GLOBALS['mock_post_meta'][8006]['_duration'], 'sin correspondencia conserva su espejo');
pbd_assert_equals('00:02:02', $GLOBALS['mock_post_meta'][8007]['duration'], 'un número ambiguo no se modifica');
pbd_assert_equals('00:01:00', $GLOBALS['mock_post_meta'][8005]['duration'], 'un valor ilegible de Anchor no se escribe');
pbd_assert_equals('00:01:00', $GLOBALS['mock_post_meta'][8005]['_duration'], 'un valor ilegible no toca el espejo');

pbd_assert_equals(
    'https://example.test/16628.mp3',
    $GLOBALS['mock_post_meta'][8001]['mp3-url'],
    'el backfill no toca mp3-url'
);
pbd_assert_equals('16628', $GLOBALS['mock_post_meta'][8001]['number'], 'el backfill no toca number');
pbd_assert_equals(
    '11111111-1662-5111-8111-111111111111',
    $GLOBALS['mock_post_meta'][8001]['anchor-guid'],
    'el backfill no toca anchor-guid'
);

foreach (array(8001, 8002, 8003, 8008, 8009, 8010) as $pbd_updated) {
    pbd_assert_equals(
        $GLOBALS['mock_post_meta'][$pbd_updated]['duration'],
        $GLOBALS['mock_post_meta'][$pbd_updated]['_duration'],
        'el espejo de ' . $pbd_updated . ' coincide con el canónico'
    );
}

// ---------------------------------------------------------------------------
// 3. Idempotencia: una segunda pasada real no cambia nada.
// ---------------------------------------------------------------------------
echo "\n--- Idempotencia ---\n";

$pbd_before = pbd_snapshot();
$pbd_second = \Atareao\PodcastDuration::backfill();
$pbd_after  = pbd_snapshot();

pbd_assert_equals(0, (int) $pbd_second['actualizados'], 'la segunda pasada reporta 0 actualizados');
pbd_assert_equals(7, (int) $pbd_second['sin_cambios'], 'los siete episodios emparejados y válidos quedan en sin_cambios');
pbd_assert_equals(1, (int) $pbd_second['ilegibles'], 'el ilegible sigue siendo ilegible');
pbd_assert_equals(2, (int) $pbd_second['sin_correspondencia'], 'los dos sin homólogo siguen fuera');
pbd_assert_true($pbd_before === $pbd_after, 'ningún metadato cambia en la segunda pasada');

// ---------------------------------------------------------------------------
// 4. --dry-run: informa los cambios y no escribe.
// ---------------------------------------------------------------------------
echo "\n--- --dry-run ---\n";

harness_reset_state();
pbd_seed();
pbd_serve(PBD_FIXTURE);
$pbd_dry_before = pbd_snapshot();

$pbd_dry = \Atareao\PodcastDuration::backfill(array('dry_run' => true));

pbd_assert_equals(6, (int) $pbd_dry['actualizados'], 'el ensayo cuenta los seis cambios que haría');
pbd_assert_equals(0, (int) $pbd_dry['pendientes'], 'el ensayo no deja pendientes sin límite');
pbd_assert_true($pbd_dry_before === pbd_snapshot(), 'el ensayo no escribe ningún metadato');

// ---------------------------------------------------------------------------
// 5. --limit: acota las actualizaciones y reporta pendientes.
// ---------------------------------------------------------------------------
echo "\n--- --limit ---\n";

harness_reset_state();
pbd_seed();
pbd_serve(PBD_FIXTURE);

$pbd_limited = \Atareao\PodcastDuration::backfill(array('limit' => 2));
pbd_assert_equals(2, (int) $pbd_limited['actualizados'], '--limit=2 actualiza como máximo dos');
pbd_assert_equals(4, (int) $pbd_limited['pendientes'], '--limit=2 deja cuatro pendientes');

$pbd_rest = \Atareao\PodcastDuration::backfill();
pbd_assert_equals(4, (int) $pbd_rest['actualizados'], 'una pasada posterior completa los cuatro pendientes');
pbd_assert_equals(0, (int) $pbd_rest['pendientes'], 'tras la pasada posterior no quedan pendientes');

// ---------------------------------------------------------------------------
// 6. --source: lee el fichero y no hace red.
// ---------------------------------------------------------------------------
echo "\n--- --source ---\n";

harness_reset_state();
pbd_seed();
$GLOBALS['mock_remote_response'] = new WP_Error('http_request_failed', 'sin red a propósito');
$GLOBALS['mock_remote_requests'] = array();

$pbd_source = \Atareao\PodcastDuration::backfill(array('source' => PBD_FIXTURE));

pbd_assert_equals(6, (int) $pbd_source['actualizados'], '--source aplica las duraciones del fichero');
pbd_assert_equals(0, count($GLOBALS['mock_remote_requests']), '--source no realiza peticiones de red');
pbd_assert_true(pbd_origin_is($pbd_source, PBD_FIXTURE), 'el informe indica la ruta del fichero como origen');

// ---------------------------------------------------------------------------
// 7. Origen ilegible: aborta sin escrituras.
// ---------------------------------------------------------------------------
echo "\n--- Origen ilegible ---\n";

harness_reset_state();
pbd_seed();
$pbd_before_invalid = pbd_snapshot();
$pbd_missing = \Atareao\PodcastDuration::backfill(array('source' => '/tmp/pbd-no-existe-' . uniqid() . '.xml'));

pbd_assert_true(!empty($pbd_missing['origin_error']), 'un --source inexistente se reporta como origin_error');
pbd_assert_equals(0, (int) $pbd_missing['actualizados'], 'un --source inexistente no actualiza nada');
pbd_assert_true($pbd_before_invalid === pbd_snapshot(), 'un --source inexistente no escribe ningún metadato');

harness_reset_state();
pbd_seed();
pbd_serve(PBD_FIXTURE);
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 200),
    'headers'  => array(),
    'body'     => 'esto no es xml',
);
$pbd_before_broken = pbd_snapshot();
$pbd_broken = \Atareao\PodcastDuration::backfill();

pbd_assert_true(!empty($pbd_broken['origin_error']), 'un XML inválido se reporta como origin_error');
pbd_assert_true($pbd_before_broken === pbd_snapshot(), 'un XML inválido no escribe ningún metadato');

// ---------------------------------------------------------------------------
// 8. El ajuste vacío resuelve el origen por defecto (caso real de producción).
// ---------------------------------------------------------------------------
echo "\n--- Ajuste de origen vacío ---\n";

harness_reset_state();
pbd_seed();
$GLOBALS['mock_options'] = array();
pbd_serve(PBD_FIXTURE);

$pbd_default = \Atareao\PodcastDuration::backfill();

pbd_assert_equals(6, (int) $pbd_default['actualizados'], 'sin ajuste configurado el backfill aplica duraciones');
pbd_assert_equals(
    \Atareao\PodcastAudio::ORIGIN_RSS_DEFAULT,
    $GLOBALS['mock_remote_requests'][0]['url'] ?? '',
    'sin ajuste se descarga la URL de origen por defecto'
);
pbd_assert_true(
    pbd_origin_is($pbd_default, \Atareao\PodcastAudio::ORIGIN_RSS_DEFAULT),
    'el informe indica la URL resuelta por defecto'
);

// ---------------------------------------------------------------------------
// 9. Informe con URL configurada.
// ---------------------------------------------------------------------------
echo "\n--- Origen configurado ---\n";

harness_reset_state();
pbd_seed();
$GLOBALS['mock_options']['atareao_podcast_origin_rss'] = 'https://example.test/origen-configurado.xml';
pbd_serve(PBD_FIXTURE);

$pbd_configured = \Atareao\PodcastDuration::backfill();

pbd_assert_equals(
    'https://example.test/origen-configurado.xml',
    $GLOBALS['mock_remote_requests'][0]['url'] ?? '',
    'con ajuste se descarga la URL configurada'
);
pbd_assert_true(
    pbd_origin_is($pbd_configured, 'https://example.test/origen-configurado.xml'),
    'el informe indica la URL configurada'
);

// ---------------------------------------------------------------------------
// 10. Subcomando WP-CLI: registro, recuentos, ensayo y error.
// ---------------------------------------------------------------------------
echo "\n--- Subcomando WP-CLI ---\n";

harness_reset_state();
pbd_seed();
pbd_serve(PBD_FIXTURE);

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}
\Atareao\PodcastDuration::init();

$pbd_command = 'atareao podcast-duration-backfill';
pbd_assert_true(
    isset($GLOBALS['mock_cli_commands'][$pbd_command]),
    'el subcomando atareao podcast-duration-backfill queda registrado'
);

if (isset($GLOBALS['mock_cli_commands'][$pbd_command])) {
    $pbd_callback = $GLOBALS['mock_cli_commands'][$pbd_command];

    $GLOBALS['mock_cli_output'] = array();
    call_user_func($pbd_callback, array(), array());

    pbd_assert_true(
        in_array('Actualizados: 6', pbd_cli_messages('log'), true),
        'el subcomando reporta seis actualizados'
    );
    pbd_assert_true(
        in_array('Origen: ' . \Atareao\PodcastAudio::ORIGIN_RSS_DEFAULT, pbd_cli_messages('log'), true),
        'el subcomando reporta la URL de origen usada'
    );
    pbd_assert_true(!empty(pbd_cli_messages('success')), 'la ejecución real termina con éxito');

    harness_reset_state();
    pbd_seed();
    pbd_serve(PBD_FIXTURE);
    $pbd_cli_before = pbd_snapshot();

    $GLOBALS['mock_cli_output'] = array();
    call_user_func($pbd_callback, array(), array('dry-run' => true));

    pbd_assert_true(
        in_array('Habría actualizado: 6', pbd_cli_messages('log'), true),
        'el ensayo rotula «Habría actualizado: N»'
    );
    pbd_assert_true($pbd_cli_before === pbd_snapshot(), 'el ensayo del subcomando no escribe');

    harness_reset_state();
    pbd_seed();
    $GLOBALS['mock_remote_response'] = new WP_Error('http_request_failed', 'sin red');
    $pbd_cli_error_before = pbd_snapshot();

    $GLOBALS['mock_cli_output'] = array();
    call_user_func($pbd_callback, array(), array());

    pbd_assert_true(!empty(pbd_cli_messages('error')), 'un origen ilegible se reporta con WP_CLI::error');
    pbd_assert_true(empty(pbd_cli_messages('success')), 'un origen ilegible no declara éxito');
    pbd_assert_true($pbd_cli_error_before === pbd_snapshot(), 'un origen ilegible no escribe desde el subcomando');
}

// ---------------------------------------------------------------------------
// 11. El importador de episodios escribe el espejo al crear.
// ---------------------------------------------------------------------------
echo "\n--- Importador de episodios ---\n";

harness_reset_state();
pbd_serve(PBD_MISSING);
\Atareao\PodcastGuid::importMissingEpisodes(array('dry_run' => false));

$pbd_created = null;
foreach (isset($GLOBALS['mock_inserted_posts']) ? $GLOBALS['mock_inserted_posts'] : array() as $pbd_post) {
    if ($pbd_post instanceof \WP_Post
        && (string) get_post_meta((int) $pbd_post->ID, 'number', true) === '386'
    ) {
        $pbd_created = $pbd_post;
    }
}

pbd_assert_true($pbd_created instanceof \WP_Post, 'el importador crea el episodio 386');
if ($pbd_created instanceof \WP_Post) {
    pbd_assert_equals('00:22:04', get_post_meta((int) $pbd_created->ID, 'duration', true), 'el alta escribe el canónico');
    pbd_assert_equals('00:22:04', get_post_meta((int) $pbd_created->ID, '_duration', true), 'el alta escribe el espejo');
}

echo "\nResumen: {$GLOBALS['pbd_passes']} PASS, " . count($GLOBALS['pbd_failures']) . " FAIL\n";

if (!empty($GLOBALS['pbd_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['pbd_failures'] as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

exit(0);
