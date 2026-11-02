<?php
/**
 * Arnés procedural de la fase RED del módulo `podcast-audio-sync`.
 *
 * Ejercita el contrato del delta `podcast-audio-sync` sobre el módulo de
 * producción `Atareao\PodcastAudio` (que la fase GREEN implementará en
 * `includes/class-podcast-audio.php`). Mientras el módulo no exista, cada
 * escenario se imprime como FALLO con motivo «módulo no implementado» y el
 * proceso termina con código 1, sin morir con un fatal.
 *
 * Mismo estilo que `tests/podcast-transcripts/test_attach.php`: contador
 * propio de pases/fallos, `exit(0)` si todo pasa y `exit(1)` si algo falla.
 * Se ejecuta con `just test-audio`.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('PA_ROOT', dirname(__DIR__, 2));
define('PA_MODULE', PA_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-audio.php');
define('PA_FEED', PA_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php');
define('PA_FIXTURE', __DIR__ . '/fixtures/origin-rss-sample.xml');

// Enclosures del fixture. Deben coincidir literalmente con el XML.
define('PA_ENCLOSURE_844', 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2024/1/ata844.mp3');
define('PA_ENCLOSURE_839', 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2024/1/ata839.mp3');
define('PA_ENCLOSURE_705', 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2024/1/705.mp3');

/*
 * El mismo fichero de audio en dos formas: envuelto por la redirección de
 * reproducción del origen (URL real escapada) y enlace directo al fichero.
 */
define(
    'PA_WRAPPED_700',
    'https://anchor.fm/s/5a5b39c/podcast/play/12345/'
        . 'https%3A%2F%2Fd3ctxlq1ktw2nl.cloudfront.net%2Fstaging%2F2024%2F1%2Fata700.mp3'
);
define('PA_DIRECT_700', 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2024/1/ata700.mp3');

// Una URL válida cuyo fichero no publica el origen para ningún episodio.
define('PA_UNKNOWN_MP3', 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2024/1/ata999-otro.mp3');

// Una URL absoluta válida pero sin extensión `.mp3`: no tiene identidad de audio.
define('PA_NOEXT_AUDIO', 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2024/1/especial-sin-extension');

/*
 * Valores que terminan en `.mp3` pero NO son URLs absolutas http(s). La spec
 * los trata como audio ausente, así que deben repararse aunque del nombre del
 * fichero se pueda extraer una identidad.
 */
define('PA_RELATIVE_MP3', 'ata844.mp3');
define('PA_ABSPATH_MP3', '/staging/2024/1/ata844.mp3');
define('PA_PROTO_MP3', '//d3ctxlq1ktw2nl.cloudfront.net/staging/2024/1/ata844.mp3');

/**
 * Nombres canónicos de los escenarios del delta (los 27 del spec).
 *
 * @return string[]
 */
function pa_scenario_names()
{
    return array(
        'El audio procede del origen',
        'Una ejecución descarga el origen una sola vez',
        'Un origen inalcanzable no escribe nada',
        'La misma identidad con dos formas de URL se considera la misma',
        'Una identidad distinta es una divergencia, no un error',
        'La identidad gana al número',
        'Los formatos de título del origen se normalizan al mismo número',
        'Un número con dos candidatos no se resuelve',
        'Un número sin item no se resuelve',
        'Un episodio sin enlace de audio recibe el del origen',
        'Un valor que no es una URL se repara',
        'Tras reparar, el feed publica un enlace de audio válido',
        'Una ejecución no cambia el audio ya correcto',
        'Una divergencia de identidad solo se informa',
        'Un emparejamiento fallido no borra el valor existente',
        'Un episodio nuevo queda resuelto al publicarse',
        'Un origen que aún no tiene el episodio no rompe la publicación',
        'Un fallo de red no rompe el guardado',
        'Volver a guardar no repite el trabajo',
        'Los borradores y otros tipos no se tocan',
        'El ensayo no escribe nada',
        'La segunda ejecución no escribe nada',
        'El límite acota la ejecución',
        'Solo los episodios con el audio ausente o inválido',
        'El comando informa de cada fallo sin abortar',
        'Solo se guardan URLs absolutas http(s)',
        'Un valor no saneable no se escribe',
    );
}

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pa_pass($description)
{
    $GLOBALS['pa_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pa_fail($description)
{
    $GLOBALS['pa_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function pa_assert_true($condition, $description)
{
    if ($condition) {
        pa_pass($description);

        return;
    }

    pa_fail($description);
}

/**
 * Aserción de identidad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function pa_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        pa_pass($description);

        return;
    }

    pa_fail(
        $description . ' (Expected: ' . var_export($expected, true)
            . ', got: ' . var_export($actual, true) . ')'
    );
}

/**
 * Imprime el encabezado de un escenario.
 *
 * @param string $name Nombre del escenario.
 * @return void
 */
function pa_section($name)
{
    echo "\n--- Scenario: {$name} ---\n";
}

/**
 * Reinicia todo el estado simulado antes de cada caso.
 *
 * `harness_reset_state()` no vacía la cola FIFO de respuestas remotas, así que
 * se limpia aquí de forma explícita.
 *
 * @return void
 */
function pa_reset()
{
    harness_reset_state();
    $GLOBALS['mock_remote_queue'] = array();
}

/**
 * Registra los ganchos del módulo tras limpiar el registro simulado.
 *
 * Debe llamarse después de `pa_reset()` porque `harness_reset_state()` vacía
 * `$GLOBALS['mock_actions']`. Devuelve `true` cuando el gancho de publicación
 * posterior a la persistencia de metas quedó registrado.
 *
 * @return bool
 */
function pa_register_audio_hooks(): bool
{
    \Atareao\PodcastAudio::init();

    return isset($GLOBALS['mock_actions']['wp_after_insert_post'])
        && count($GLOBALS['mock_actions']['wp_after_insert_post']) > 0;
}

/**
 * Ejecuta una función capturando los `PHP Warning`/`Notice` emitidos.
 *
 * Se usa para demostrar que parsear un XML sin `<channel>` no emite el aviso
 * `foreach() argument must be of type array|object, null given`.
 *
 * @param callable $callable Función a ejecutar.
 * @return array{result: mixed, warnings: string[]}
 */
function pa_capture(callable $callable): array
{
    $warnings = array();
    set_error_handler(
        function ($errno, $errstr) use (&$warnings) {
            $warnings[] = (string) $errstr;

            return true;
        }
    );

    $result = null;
    try {
        $result = $callable();
    } finally {
        restore_error_handler();
    }

    return array('result' => $result, 'warnings' => $warnings);
}

/**
 * Configura el origen como alcanzable y devuelve el XML del fixture.
 *
 * @return string Contenido del fixture.
 */
function pa_origin_ok()
{
    $xml = (string) file_get_contents(PA_FIXTURE);
    $GLOBALS['mock_remote_response'] = array(
        'response' => array('code' => 200),
        'headers'  => array(),
        'body'     => $xml,
    );

    return $xml;
}

/**
 * Construye un `WP_Post` de fixture.
 *
 * @param int    $id     Identificador.
 * @param string $title  Título.
 * @param string $type   Tipo de contenido.
 * @param string $status Estado.
 * @return WP_Post
 */
function pa_post($id, $title, $type = 'podcast', $status = 'publish')
{
    return new WP_Post(
        array(
            'ID'          => (int) $id,
            'post_title'  => (string) $title,
            'post_type'   => (string) $type,
            'post_status' => (string) $status,
        )
    );
}

/**
 * Añade un episodio a la fixture de `WP_Query` y, opcionalmente, sus metas.
 *
 * @param int         $id     Identificador.
 * @param string      $title  Título.
 * @param string|null $audio  Valor de `mp3-url` (null = ausente).
 * @param int|null    $number Valor de `number` (null = ausente).
 * @param string      $type   Tipo de contenido.
 * @param string      $status Estado.
 * @return void
 */
function pa_add_post($id, $title, $audio = null, $number = null, $type = 'podcast', $status = 'publish')
{
    $GLOBALS['mock_posts'][] = pa_post($id, $title, $type, $status);
    if (null !== $audio) {
        $GLOBALS['mock_post_meta'][(int) $id]['mp3-url'] = $audio;
    }
    if (null !== $number) {
        $GLOBALS['mock_post_meta'][(int) $id]['number'] = $number;
    }
}

/**
 * Lee una meta del almacén en memoria.
 *
 * @param int    $id      Identificador.
 * @param string $key     Clave.
 * @param mixed  $default Valor por omisión.
 * @return mixed
 */
function pa_meta($id, $key, $default = null)
{
    return isset($GLOBALS['mock_post_meta'][(int) $id][$key])
        ? $GLOBALS['mock_post_meta'][(int) $id][$key]
        : $default;
}

/**
 * Busca en una lista de items parseados el primero cuyo título empieza por un prefijo.
 *
 * @param array  $items  Items de `parseOriginFeed()`.
 * @param string $prefix Prefijo del título.
 * @return array|null
 */
function pa_find_item($items, $prefix)
{
    foreach ((array) $items as $item) {
        if (isset($item['title']) && strpos((string) $item['title'], (string) $prefix) === 0) {
            return $item;
        }
    }

    return null;
}

/**
 * Imprime el resumen y termina con el código que corresponda.
 *
 * @return void
 */
function pa_finish()
{
    echo "\n============================================\n";
    echo 'Total passed: ' . (int) $GLOBALS['pa_passes']
        . ', Total failed: ' . count($GLOBALS['pa_failures']) . "\n";
    if (count($GLOBALS['pa_failures']) > 0) {
        echo "FAILED TESTS:\n";
        foreach ($GLOBALS['pa_failures'] as $failure) {
            echo '  - ' . $failure . "\n";
        }
        exit(1);
    }

    echo "All tests passed successfully!\n";
    exit(0);
}

$GLOBALS['pa_passes']        = 0;
$GLOBALS['pa_failures']      = array();
$GLOBALS['pa_scenarios']     = pa_scenario_names();
$GLOBALS['pa_module_loaded'] = false;

if (is_file(PA_MODULE)) {
    require_once PA_MODULE;
    $GLOBALS['pa_module_loaded'] = class_exists('\Atareao\PodcastAudio');
}

// El feed de producción ya existe: se carga para poder componer su `<enclosure>`.
if (is_file(PA_FEED)) {
    require_once PA_FEED;
}

echo "=== Running podcast-audio-sync (RED) Test Suite ===\n";

if (!$GLOBALS['pa_module_loaded']) {
    foreach ($GLOBALS['pa_scenarios'] as $scenario) {
        pa_fail($scenario . ' — módulo no implementado');
    }

    pa_finish();
}

// ---------------------------------------------------------------------------
// Escenario 1 — El audio procede del origen
// ---------------------------------------------------------------------------
pa_section('El audio procede del origen');
pa_reset();
$pa_xml   = pa_origin_ok();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed($pa_xml);
pa_assert_equals(11, count($pa_items), 'parseOriginFeed extrae los 11 items del recorte');
$pa_item = pa_find_item($pa_items, 'ATA 844');
pa_assert_true(null !== $pa_item, 'parseOriginFeed devuelve el item ATA 844');
if (null !== $pa_item) {
    pa_assert_true(strpos((string) $pa_item['title'], 'ATA 844') === 0, 'el título del item ATA 844 procede del origen');
    pa_assert_equals(PA_ENCLOSURE_844, $pa_item['enclosure'], 'el enclosure de ATA 844 procede del origen');
    pa_assert_equals('ata844.mp3', $pa_item['identity'], 'la identidad de ATA 844 es el basename del mp3');
    pa_assert_equals(844, $pa_item['number'], 'el número deducido del título ATA 844 es 844');
}

pa_reset();
pa_origin_ok();
pa_add_post(1844, 'ATA 844 - Podcasting en Linux', null, 844);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(PA_ENCLOSURE_844, pa_meta(1844, 'mp3-url'), 'syncEpisodes escribe exactamente el enclosure del origen');

// ---------------------------------------------------------------------------
// Escenario 2 — Una ejecución descarga el origen una sola vez
// ---------------------------------------------------------------------------
pa_section('Una ejecución descarga el origen una sola vez');
pa_reset();
pa_origin_ok();
pa_add_post(201, 'ATA 844 - a', null, 844);
pa_add_post(202, 'ATA839 - b', null, 839);
pa_add_post(203, '705 - c', null, 705);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(1, count($GLOBALS['mock_remote_requests']), 'tres episodios provocan una única descarga del origen');
pa_assert_equals(3, $pa_report['repaired'], 'los tres episodios se reparan con el índice compartido');

// ---------------------------------------------------------------------------
// Escenario 3 — Un origen inalcanzable no escribe nada
// ---------------------------------------------------------------------------
pa_section('Un origen inalcanzable no escribe nada');
pa_reset();
pa_add_post(301, 'ATA 844 - a', null, 844);
$GLOBALS['mock_remote_response'] = new WP_Error('http_request_failed', 'origen caído');
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(null, pa_meta(301, 'mp3-url'), 'con un WP_Error del origen no se escribe mp3-url');
pa_assert_true(isset($pa_report['failed']) && $pa_report['failed'] > 0, 'el resumen reporta el fallo del origen (failed > 0)');

pa_reset();
pa_add_post(302, 'ATA 844 - a', null, 844);
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 500),
    'headers'  => array(),
    'body'     => 'boom',
);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(null, pa_meta(302, 'mp3-url'), 'con una respuesta 500 del origen no se escribe mp3-url');

// ---------------------------------------------------------------------------
// Escenario — La descarga del origen es segura y no promete un ETag muerto
// ---------------------------------------------------------------------------
pa_section('La descarga del origen es segura');
pa_reset();
pa_origin_ok();
pa_add_post(3051, 'ATA 844 - a', null, 844);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
$pa_args = isset($GLOBALS['mock_remote_requests'][0]['args']) ? $GLOBALS['mock_remote_requests'][0]['args'] : array();
pa_assert_equals(1, count($GLOBALS['mock_remote_requests']), 'la sincronización descarga el origen una vez');
pa_assert_true(!empty($pa_args['reject_unsafe_urls']), 'la descarga del origen rechaza URLs inseguras (anti-SSRF)');
pa_assert_equals(0, isset($pa_args['redirection']) ? $pa_args['redirection'] : null, 'la descarga del origen no sigue redirecciones');
pa_assert_true(
    !isset($pa_args['headers']['If-None-Match']),
    'la descarga del origen no envía un If-None-Match muerto'
);

pa_reset();
pa_add_post(3052, 'ATA 844 - a', null, 844);
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 304),
    'headers'  => array('etag' => '"abc"'),
    'body'     => '',
);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(null, pa_meta(3052, 'mp3-url'), 'una respuesta 304 sin caché no escribe nada');
pa_assert_equals(1, $pa_report['failed'], 'una respuesta 304 sin caché se cuenta como fallo del origen');

// ---------------------------------------------------------------------------
// Escenario — Una respuesta que no es RSS es un fallo, no un índice vacío
// ---------------------------------------------------------------------------
pa_section('Una respuesta que no es RSS es un fallo');
pa_reset();
pa_add_post(3061, 'ATA 844 - a', null, 844);
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 200),
    'headers'  => array(),
    'body'     => '<html><body>Error 503 del CDN</body></html>',
);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(1, $pa_report['failed'], 'una respuesta HTML se cuenta como fallo del origen');
pa_assert_equals(null, pa_meta(3061, 'mp3-url'), 'una respuesta HTML no escribe ninguna meta');

pa_reset();
pa_add_post(3062, 'ATA 844 - a', null, 844);
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 200),
    'headers'  => array(),
    'body'     => '<?xml version="1.0"?><rss version="2.0"></rss>',
);
$pa_capture = pa_capture(function () {
    return \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
});
pa_assert_equals(array(), $pa_capture['warnings'], 'un RSS sin <channel> no emite ningún PHP Warning');
pa_assert_equals(1, $pa_capture['result']['failed'], 'un RSS sin <channel> se cuenta como fallo del origen');
pa_assert_equals(null, pa_meta(3062, 'mp3-url'), 'un RSS sin <channel> no escribe ninguna meta');

// ---------------------------------------------------------------------------
// Escenario — La caché gana al marcador de fallo y el éxito lo borra
// ---------------------------------------------------------------------------
pa_section('La caché gana al marcador de fallo');
pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed((string) file_get_contents(PA_FIXTURE));
$GLOBALS['mock_transients']['atareao_podcast_audio_index'] = array(
    'index' => \Atareao\PodcastAudio::buildIndex($pa_items),
);
$GLOBALS['mock_transients']['atareao_podcast_audio_index_failure'] = time();
pa_add_post(3071, 'ATA 844 - a', null, 844);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(0, count($GLOBALS['mock_remote_requests']), 'una caché válida evita descargar aunque haya marcador de fallo');
pa_assert_equals(PA_ENCLOSURE_844, pa_meta(3071, 'mp3-url'), 'la caché se usa para reparar el episodio');
pa_assert_true(
    false === get_transient('atareao_podcast_audio_index_failure'),
    'un índice resuelto borra el marcador de fallo'
);

pa_reset();
$GLOBALS['mock_transients']['atareao_podcast_audio_index_failure'] = time();
pa_add_post(3072, 'ATA 844 - a', null, 844);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(0, count($GLOBALS['mock_remote_requests']), 'un marcador de fallo vigente evita descargar de nuevo');
pa_assert_equals(1, $pa_report['failed'], 'con el marcador de fallo vigente el episodio se cuenta como fallido');

// ---------------------------------------------------------------------------
// Escenario — El tope del cuerpo cubre el feed real y detecta el truncamiento
// ---------------------------------------------------------------------------
pa_section('El tope del cuerpo cubre el feed real');
// El RSS real del origen mide 5 376 885 B sin comprimir (915 028 B con gzip).
pa_assert_true(
    \Atareao\PodcastAudio::MAX_BODY_BYTES >= 8388608,
    'el tope del cuerpo supera con holgura el feed real (5 376 885 B)'
);

// La petición pide un byte más que el tope: así un cuerpo truncado vuelve con
// longitud tope + 1 y el guard de `originIndex()` lo detecta siempre (antes
// quedaba justo en el tope y `>` era falso).
pa_reset();
pa_origin_ok();
pa_add_post(3081, 'ATA 844 - a', null, 844);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
$pa_size_args = isset($GLOBALS['mock_remote_requests'][0]['args']) ? $GLOBALS['mock_remote_requests'][0]['args'] : array();
pa_assert_equals(
    \Atareao\PodcastAudio::MAX_BODY_BYTES + 1,
    isset($pa_size_args['limit_response_size']) ? $pa_size_args['limit_response_size'] : null,
    'la petición al origen pide como máximo el tope + 1 byte'
);

// Un cuerpo que alcanza el tope es un truncamiento: fallo y sin caché de éxito.
pa_reset();
pa_add_post(3082, 'ATA 844 - a', null, 844);
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 200),
    'headers'  => array(),
    'body'     => str_repeat('x', \Atareao\PodcastAudio::MAX_BODY_BYTES + 1),
);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(1, $pa_report['failed'], 'un cuerpo que alcanza el tope se cuenta como fallo del origen');
pa_assert_equals(null, pa_meta(3082, 'mp3-url'), 'un cuerpo truncado no escribe ninguna meta');
pa_assert_true(
    false === get_transient('atareao_podcast_audio_index'),
    'un cuerpo truncado no se cachea como índice de éxito'
);
pa_assert_true(
    false !== get_transient('atareao_podcast_audio_index_failure'),
    'un cuerpo truncado marca el fallo del origen'
);

// Un feed válido del tamaño real (5 376 885 B) sí construye el índice.
pa_reset();
pa_add_post(3083, 'ATA 844 - a', null, 844);
$pa_real = (string) file_get_contents(PA_FIXTURE);
$pa_real = str_replace('<channel>', '<channel>' . str_repeat(' ', 5376885 - strlen($pa_real)), $pa_real);
$GLOBALS['mock_remote_response'] = array(
    'response' => array('code' => 200),
    'headers'  => array(),
    'body'     => $pa_real,
);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(5376885, strlen($pa_real), 'el cuerpo de prueba mide el feed real sin comprimir');
pa_assert_equals(1, $pa_report['repaired'], 'un feed válido del tamaño real se parsea y repara');
pa_assert_equals(PA_ENCLOSURE_844, pa_meta(3083, 'mp3-url'), 'el feed del tamaño real escribe el enclosure');

// ---------------------------------------------------------------------------
// Escenario 4 — La misma identidad con dos formas de URL se considera la misma
// ---------------------------------------------------------------------------
pa_section('La misma identidad con dos formas de URL se considera la misma');
pa_reset();
pa_origin_ok();
pa_assert_equals(
    'ata700.mp3',
    \Atareao\PodcastAudio::audioIdentity(PA_WRAPPED_700),
    'la URL envuelta por el origen tiene identidad ata700.mp3'
);
pa_assert_equals(
    'ata700.mp3',
    \Atareao\PodcastAudio::audioIdentity(PA_DIRECT_700),
    'la URL directa al mismo fichero tiene la misma identidad'
);
pa_assert_equals(
    \Atareao\PodcastAudio::audioIdentity('ata844.mp3'),
    \Atareao\PodcastAudio::audioIdentity('ata844.MP3'),
    'la identidad del audio es insensible a mayúsculas en la extensión'
);
$pa_items = \Atareao\PodcastAudio::parseOriginFeed((string) file_get_contents(PA_FIXTURE));
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
$pa_plan  = \Atareao\PodcastAudio::planSync(
    array('id' => 401, 'title' => 'ATA 700 - Forma envuelta', 'mp3_url' => PA_DIRECT_700, 'number' => 700),
    $pa_index
);
pa_assert_equals('ok', $pa_plan['class'], 'la misma identidad en dos formas se empareja como ok');
pa_assert_equals(null, $pa_plan['value'], 'un episodio emparejado no propone valor de reparación');

pa_reset();
pa_origin_ok();
pa_add_post(401, 'ATA 700 - Forma envuelta', PA_DIRECT_700, 700);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(PA_DIRECT_700, pa_meta(401, 'mp3-url'), 'el mp3-url del episodio emparejado no se modifica');

// ---------------------------------------------------------------------------
// Escenario 5 — Una identidad distinta es una divergencia, no un error
// ---------------------------------------------------------------------------
pa_section('Una identidad distinta es una divergencia, no un error');
pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed(pa_origin_ok());
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
$pa_plan  = \Atareao\PodcastAudio::planSync(
    array('id' => 501, 'title' => 'ATA 844 - a', 'mp3_url' => PA_UNKNOWN_MP3, 'number' => 844),
    $pa_index
);
pa_assert_equals('divergent', $pa_plan['class'], 'una identidad de audio desconocida se reporta como divergente');
pa_assert_equals(null, $pa_plan['value'], 'una divergencia no propone valor de reparación');

pa_reset();
pa_origin_ok();
pa_add_post(501, 'ATA 844 - a', PA_UNKNOWN_MP3, 844);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(PA_UNKNOWN_MP3, pa_meta(501, 'mp3-url'), 'la divergencia no modifica el mp3-url existente');

// ---------------------------------------------------------------------------
// Escenario 6 — La identidad gana al número
// ---------------------------------------------------------------------------
pa_section('La identidad gana al número');
pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed(pa_origin_ok());
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
$pa_plan  = \Atareao\PodcastAudio::planSync(
    array('id' => 601, 'title' => 'ATA 999 - número distinto', 'mp3_url' => PA_DIRECT_700, 'number' => 999),
    $pa_index
);
pa_assert_equals('ok', $pa_plan['class'], 'con la identidad presente, un número distinto no cambia el emparejamiento');
pa_assert_equals(null, $pa_plan['value'], 'el emparejamiento por identidad no propone escritura');

// ---------------------------------------------------------------------------
// Escenario 7 — Los formatos de título del origen se normalizan al mismo número
// ---------------------------------------------------------------------------
pa_section('Los formatos de título del origen se normalizan al mismo número');
pa_reset();
pa_assert_equals(839, \Atareao\PodcastAudio::episodeNumberFromTitle('ATA 839 - Podcasting'), 'ATA 839 se normaliza a 839');
pa_assert_equals(839, \Atareao\PodcastAudio::episodeNumberFromTitle('ATA839 - Sin espacio'), 'ATA839 se normaliza a 839');
pa_assert_equals(
    50,
    \Atareao\PodcastAudio::episodeNumberFromTitle('ATA T01E50 - Retroinformática'),
    'ATA T01E50 se normaliza a 50'
);
pa_assert_equals(705, \Atareao\PodcastAudio::episodeNumberFromTitle('705 - Especial'), '705 - ... se normaliza a 705');
pa_assert_equals(36, \Atareao\PodcastAudio::episodeNumberFromTitle('T01E36 - Sólo audio'), 'T01E36 se normaliza a 36');
pa_assert_equals(844, \Atareao\PodcastAudio::episodeNumberFromTitle('ATA 844 - Normal'), 'ATA 844 se normaliza a 844');
pa_assert_equals(
    839,
    \Atareao\PodcastAudio::episodeNumberFromTitle('ATA 839 - T02E05 - Doble patrón'),
    'el número ATA explícito gana al patrón TxxExx'
);

// ---------------------------------------------------------------------------
// Escenario 8 — Un número con dos candidatos no se resuelve
// ---------------------------------------------------------------------------
pa_section('Un número con dos candidatos no se resuelve');
pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed(pa_origin_ok());
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
$pa_plan  = \Atareao\PodcastAudio::planSync(
    array('id' => 801, 'title' => 'ATA 667 - x', 'mp3_url' => '', 'number' => 667),
    $pa_index
);
pa_assert_equals('ambiguous', $pa_plan['class'], 'un número con dos items del origen se reporta como ambiguo');
pa_assert_equals(null, $pa_plan['value'], 'un número ambiguo no propone valor');

pa_reset();
pa_origin_ok();
pa_add_post(801, 'ATA 667 - x', null, 667);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(null, pa_meta(801, 'mp3-url'), 'un número ambiguo no escribe ninguna meta');
pa_assert_equals(1, $pa_report['ambiguous'], 'el resumen cuenta el episodio ambiguo');

// ---------------------------------------------------------------------------
// Escenario 9 — Un número sin item no se resuelve
// ---------------------------------------------------------------------------
pa_section('Un número sin item no se resuelve');
pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed(pa_origin_ok());
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
$pa_plan  = \Atareao\PodcastAudio::planSync(
    array('id' => 901, 'title' => 'ATA 4242 - x', 'mp3_url' => '', 'number' => 4242),
    $pa_index
);
pa_assert_equals('unmatched', $pa_plan['class'], 'un número sin item se reporta como sin par');
pa_assert_equals(null, $pa_plan['value'], 'un número sin item no propone valor');

pa_reset();
pa_origin_ok();
pa_add_post(901, 'ATA 4242 - x', null, 4242);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(null, pa_meta(901, 'mp3-url'), 'un número sin item no escribe ninguna meta');
pa_assert_equals(1, $pa_report['unmatched'], 'el resumen cuenta el episodio sin par');

// ---------------------------------------------------------------------------
// Escenario 10 — Un episodio sin enlace de audio recibe el del origen
// ---------------------------------------------------------------------------
pa_section('Un episodio sin enlace de audio recibe el del origen');
pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed(pa_origin_ok());
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
$pa_plan  = \Atareao\PodcastAudio::planSync(
    array('id' => 1001, 'title' => 'ATA 844 - a', 'mp3_url' => '', 'number' => 844),
    $pa_index
);
pa_assert_equals('repair', $pa_plan['class'], 'un episodio sin audio y número único se marca repair');
pa_assert_equals(PA_ENCLOSURE_844, $pa_plan['value'], 'la reparación propone el enclosure del origen');

pa_reset();
pa_origin_ok();
pa_add_post(1001, 'ATA 844 - a', null, 844);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(PA_ENCLOSURE_844, pa_meta(1001, 'mp3-url'), 'el mp3-url queda escrito con el enclosure del origen');
pa_assert_equals(
    PA_ENCLOSURE_844,
    get_post_meta(1001, 'mp3-url', true),
    'el mp3-url se vuelve a leer con ese valor'
);

// ---------------------------------------------------------------------------
// Escenario 11 — Un valor que no es una URL se repara
// ---------------------------------------------------------------------------
pa_section('Un valor que no es una URL se repara');
pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed(pa_origin_ok());
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
$pa_plan  = \Atareao\PodcastAudio::planSync(
    array('id' => 1101, 'title' => 'ATA 844 - a', 'mp3_url' => '15', 'number' => 844),
    $pa_index
);
pa_assert_equals('repair', $pa_plan['class'], 'un valor "15" no es URL absoluta y se repara');
pa_assert_equals(PA_ENCLOSURE_844, $pa_plan['value'], 'la reparación propone el enclosure del origen');

pa_reset();
pa_origin_ok();
pa_add_post(1101, 'ATA 844 - a', '15', 844);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(PA_ENCLOSURE_844, pa_meta(1101, 'mp3-url'), 'el valor "15" se sustituye por el enclosure del origen');
pa_assert_true(
    (bool) preg_match('|^https?://|', (string) pa_meta(1101, 'mp3-url')),
    'el valor reparado es una URL absoluta http(s)'
);

// Un valor que termina en `.mp3` pero no es una URL absoluta http(s) es audio
// ausente: la identidad se ignora y se repara por número.
pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed(pa_origin_ok());
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
foreach (array(PA_RELATIVE_MP3, PA_ABSPATH_MP3, PA_PROTO_MP3) as $pa_bad_value) {
    $pa_plan = \Atareao\PodcastAudio::planSync(
        array('id' => 1102, 'title' => 'ATA 844 - a', 'mp3_url' => $pa_bad_value, 'number' => 844),
        $pa_index
    );
    pa_assert_equals(
        'repair',
        $pa_plan['class'],
        'un valor no-URL terminado en .mp3 se trata como ausente y se repara: ' . $pa_bad_value
    );
    pa_assert_equals(
        PA_ENCLOSURE_844,
        $pa_plan['value'],
        'la reparación propone el enclosure del origen: ' . $pa_bad_value
    );
}

pa_reset();
pa_origin_ok();
pa_add_post(1102, 'ATA 844 - a', PA_RELATIVE_MP3, 844);
pa_add_post(1103, 'ATA839 - b', PA_ABSPATH_MP3, 839);
pa_add_post(1104, '705 - c', PA_PROTO_MP3, 705);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(PA_ENCLOSURE_844, pa_meta(1102, 'mp3-url'), 'el nombre relativo terminado en .mp3 se repara');
pa_assert_equals(PA_ENCLOSURE_839, pa_meta(1103, 'mp3-url'), 'la ruta absoluta terminada en .mp3 se repara');
pa_assert_equals(PA_ENCLOSURE_705, pa_meta(1104, 'mp3-url'), 'la URL protocol-relative terminada en .mp3 se repara');

// ---------------------------------------------------------------------------
// Escenario 12 — Tras reparar, el feed publica un enlace de audio válido
// ---------------------------------------------------------------------------
pa_section('Tras reparar, el feed publica un enlace de audio válido');
pa_reset();
pa_origin_ok();
pa_add_post(1201, 'ATA 844 - a', null, 844);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(PA_ENCLOSURE_844, pa_meta(1201, 'mp3-url'), 'el episodio queda reparado antes de componer el feed');
$GLOBALS['mock_is_feed']          = true;
$GLOBALS['mock_post_type_archive'] = 'podcast';
ob_start();
$pa_html = \Atareao\PodcastFeed::itemElements(1201);
ob_end_clean();
pa_assert_true(
    strpos((string) $pa_html, '<enclosure url="https://d3ctxlq1ktw2nl.cloudfront.net/') !== false,
    'el enclosure del feed apunta a la URL absoluta del origen'
);
pa_assert_true(strpos((string) $pa_html, 'url="15"') === false, 'el feed no publica el enclosure inválido');

// ---------------------------------------------------------------------------
// Escenario 13 — Una ejecución no cambia el audio ya correcto
// ---------------------------------------------------------------------------
pa_section('Una ejecución no cambia el audio ya correcto');
pa_reset();
pa_origin_ok();
pa_add_post(1301, 'ATA 844 - a', PA_ENCLOSURE_844, 844);
// Se siembra la caché de tamaño para que ambas composiciones del feed sean idénticas.
$GLOBALS['mock_post_meta'][1301][\Atareao\PodcastFeed::AUDIO_LENGTH_META] = array(
    'bytes'   => 12345,
    'expires' => time() + 3600,
);
$GLOBALS['mock_is_feed']          = true;
$GLOBALS['mock_post_type_archive'] = 'podcast';
ob_start();
$pa_before = \Atareao\PodcastFeed::itemElements(1301);
ob_end_clean();
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
$GLOBALS['mock_is_feed']          = true;
$GLOBALS['mock_post_type_archive'] = 'podcast';
ob_start();
$pa_after = \Atareao\PodcastFeed::itemElements(1301);
ob_end_clean();
pa_assert_equals(PA_ENCLOSURE_844, pa_meta(1301, 'mp3-url'), 'el mp3-url válido sigue siendo el mismo valor');
pa_assert_equals($pa_before, $pa_after, 'el enclosure del feed es idéntico antes y después de la ejecución');

// ---------------------------------------------------------------------------
// Escenario 14 — Una divergencia de identidad solo se informa
// ---------------------------------------------------------------------------
pa_section('Una divergencia de identidad solo se informa');
pa_reset();
pa_origin_ok();
pa_add_post(1401, 'ATA 844 - a', PA_UNKNOWN_MP3, 844);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(PA_UNKNOWN_MP3, pa_meta(1401, 'mp3-url'), 'la divergencia no cambia el valor existente');
pa_assert_equals(1, $pa_report['divergent'], 'el resumen cuenta la divergencia');

// ---------------------------------------------------------------------------
// Escenario 15 — Un emparejamiento fallido no borra el valor existente
// ---------------------------------------------------------------------------
pa_section('Un emparejamiento fallido no borra el valor existente');
pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed(pa_origin_ok());
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
$pa_plan  = \Atareao\PodcastAudio::planSync(
    array('id' => 1501, 'title' => 'ATA 667 - x', 'mp3_url' => PA_NOEXT_AUDIO, 'number' => 667),
    $pa_index
);
pa_assert_equals('ambiguous', $pa_plan['class'], 'un audio válido sin identidad .mp3 cae al número y resulta ambiguo');
pa_assert_equals(null, $pa_plan['value'], 'un emparejamiento ambiguo no propone valor');

pa_reset();
pa_origin_ok();
pa_add_post(1501, 'ATA 667 - x', PA_NOEXT_AUDIO, 667);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(
    PA_NOEXT_AUDIO,
    pa_meta(1501, 'mp3-url'),
    'el valor válido sigue igual pese al emparejamiento ambiguo'
);

pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed(pa_origin_ok());
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
$pa_plan  = \Atareao\PodcastAudio::planSync(
    array('id' => 1502, 'title' => 'ATA 4242 - x', 'mp3_url' => PA_NOEXT_AUDIO, 'number' => 4242),
    $pa_index
);
pa_assert_equals('unmatched', $pa_plan['class'], 'un audio válido sin identidad .mp3 y sin número casa resulta sin par');

// ---------------------------------------------------------------------------
// Escenario 16 — Un episodio nuevo queda resuelto al publicarse
// ---------------------------------------------------------------------------
pa_section('Un episodio nuevo queda resuelto al publicarse');
pa_reset();
pa_origin_ok();
$pa_post = pa_post(1601, 'ATA 844 - a');
$GLOBALS['mock_posts'][] = $pa_post;
$GLOBALS['mock_post_meta'][1601]['number'] = 844;
pa_assert_true(pa_register_audio_hooks(), 'init() registra el gancho wp_after_insert_post');
do_action('wp_after_insert_post', 1601, $pa_post, true, null);
pa_assert_equals(
    PA_ENCLOSURE_844,
    pa_meta(1601, 'mp3-url'),
    'al publicarse, el mp3-url se escribe con el enclosure del origen'
);
pa_assert_equals(PA_ENCLOSURE_844, get_post_meta(1601, 'mp3-url', true), 'se vuelve a leer con ese valor');
pa_assert_true(
    isset($GLOBALS['mock_post_meta'][1601]['_atareao_podcast_audio_attempt']),
    'la publicación resuelta marca el intento'
);

// ---------------------------------------------------------------------------
// Escenario 17 — Un origen que aún no tiene el episodio no rompe la publicación
// ---------------------------------------------------------------------------
pa_section('Un origen que aún no tiene el episodio no rompe la publicación');
pa_reset();
pa_origin_ok();
$pa_post = pa_post(1701, 'ATA 4242 - x');
$GLOBALS['mock_posts'][] = $pa_post;
$GLOBALS['mock_post_meta'][1701]['number'] = 4242;
pa_register_audio_hooks();
do_action('wp_after_insert_post', 1701, $pa_post, true, null);
pa_assert_equals(null, pa_meta(1701, 'mp3-url'), 'un episodio que el origen aún no lista queda publicado sin meta');
pa_assert_true(
    !isset($GLOBALS['mock_post_meta'][1701]['_atareao_podcast_audio_attempt']),
    'un episodio sin par no marca el intento y podrá resolverse más tarde'
);

// ---------------------------------------------------------------------------
// Escenario 18 — Un fallo de red no rompe el guardado
// ---------------------------------------------------------------------------
pa_section('Un fallo de red no rompe el guardado');
pa_reset();
$pa_post = pa_post(1801, 'ATA 844 - a');
$GLOBALS['mock_posts'][] = $pa_post;
$GLOBALS['mock_post_meta'][1801]['number'] = 844;
$GLOBALS['mock_remote_response'] = new WP_Error('http_request_failed', 'sin red');
pa_register_audio_hooks();
do_action('wp_after_insert_post', 1801, $pa_post, true, null);
pa_assert_equals(null, pa_meta(1801, 'mp3-url'), 'con el origen caído no se escribe meta');
pa_assert_true(
    !isset($GLOBALS['mock_post_meta'][1801]['_atareao_podcast_audio_attempt']),
    'un fallo de red no marca el intento y el guardado puede reintentarse'
);

// ---------------------------------------------------------------------------
// Escenario 19 — Volver a guardar no repite el trabajo
// ---------------------------------------------------------------------------
pa_section('Volver a guardar no repite el trabajo');
pa_reset();
pa_origin_ok();
$pa_post = pa_post(1901, 'ATA 844 - a');
$GLOBALS['mock_posts'][] = $pa_post;
$GLOBALS['mock_post_meta'][1901]['number'] = 844;
pa_register_audio_hooks();
do_action('wp_after_insert_post', 1901, $pa_post, true, null);
$pa_first = pa_meta(1901, 'mp3-url');
pa_assert_equals(PA_ENCLOSURE_844, $pa_first, 'la primera transición resuelve el audio');
pa_assert_true(
    isset($GLOBALS['mock_post_meta'][1901]['_atareao_podcast_audio_attempt']),
    'se marca la meta interna de intento'
);
$GLOBALS['mock_remote_requests'] = array();
do_action('wp_after_insert_post', 1901, $pa_post, true, null);
pa_assert_equals(0, count($GLOBALS['mock_remote_requests']), 'el segundo guardado no repite la descarga del origen');
pa_assert_equals($pa_first, pa_meta(1901, 'mp3-url'), 'el segundo guardado no altera la meta');

// ---------------------------------------------------------------------------
// Escenario 20 — Los borradores y otros tipos no se tocan
// ---------------------------------------------------------------------------
pa_section('Los borradores y otros tipos no se tocan');
pa_reset();
pa_origin_ok();
pa_register_audio_hooks();
$pa_draft = pa_post(2001, 'ATA 844 - borrador', 'podcast', 'draft');
$GLOBALS['mock_posts'][] = $pa_draft;
$GLOBALS['mock_post_meta'][2001]['number'] = 844;
do_action('wp_after_insert_post', 2001, $pa_draft, true, null);
pa_assert_equals(null, pa_meta(2001, 'mp3-url'), 'un borrador de episodio no recibe meta');
$pa_article = pa_post(2002, 'Entrada normal', 'post', 'publish');
$GLOBALS['mock_posts'][] = $pa_article;
do_action('wp_after_insert_post', 2002, $pa_article, true, null);
pa_assert_equals(null, pa_meta(2002, 'mp3-url'), 'una entrada que no es podcast no recibe meta');
pa_assert_equals(
    0,
    count($GLOBALS['mock_remote_requests']),
    'no se descarga el origen para borradores ni otros tipos'
);

// ---------------------------------------------------------------------------
// Escenario — El intento solo se marca cuando el episodio queda resuelto
// ---------------------------------------------------------------------------
pa_section('El intento solo se marca cuando se resuelve');
pa_reset();
pa_origin_ok();
pa_register_audio_hooks();

// Ambiguo: dos items del origen comparten el número.
$pa_amb = pa_post(2051, 'ATA 667 - x');
$GLOBALS['mock_posts'][] = $pa_amb;
$GLOBALS['mock_post_meta'][2051]['number'] = 667;
do_action('wp_after_insert_post', 2051, $pa_amb, true, null);
pa_assert_true(
    !isset($GLOBALS['mock_post_meta'][2051]['_atareao_podcast_audio_attempt']),
    'un emparejamiento ambiguo no marca el intento'
);

// Divergente: audio válido cuyo fichero no publica el origen.
$pa_div = pa_post(2052, 'ATA 844 - a');
$GLOBALS['mock_posts'][] = $pa_div;
$GLOBALS['mock_post_meta'][2052]['number'] = 844;
$GLOBALS['mock_post_meta'][2052]['mp3-url'] = PA_UNKNOWN_MP3;
do_action('wp_after_insert_post', 2052, $pa_div, true, null);
pa_assert_equals(PA_UNKNOWN_MP3, pa_meta(2052, 'mp3-url'), 'una divergencia no se sobrescribe');
pa_assert_true(
    !isset($GLOBALS['mock_post_meta'][2052]['_atareao_podcast_audio_attempt']),
    'una divergencia no marca el intento'
);

// Ya correcto: marca el intento para no repetir la descarga.
$pa_okp = pa_post(2053, 'ATA 844 - a');
$GLOBALS['mock_posts'][] = $pa_okp;
$GLOBALS['mock_post_meta'][2053]['number'] = 844;
$GLOBALS['mock_post_meta'][2053]['mp3-url'] = PA_ENCLOSURE_844;
do_action('wp_after_insert_post', 2053, $pa_okp, true, null);
pa_assert_true(
    isset($GLOBALS['mock_post_meta'][2053]['_atareao_podcast_audio_attempt']),
    'un episodio ya correcto marca el intento'
);

// ---------------------------------------------------------------------------
// Escenario 21 — El ensayo no escribe nada
// ---------------------------------------------------------------------------
pa_section('El ensayo no escribe nada');
pa_reset();
pa_origin_ok();
pa_add_post(2101, 'ATA 844 - a', null, 844);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => true, 'limit' => 0, 'missing' => false));
pa_assert_equals(null, pa_meta(2101, 'mp3-url'), 'el ensayo no escribe ninguna meta');
pa_assert_equals(1, $pa_report['repaired'], 'el resumen del ensayo describe lo que se habría escrito');

// ---------------------------------------------------------------------------
// Escenario 22 — La segunda ejecución no escribe nada
// ---------------------------------------------------------------------------
pa_section('La segunda ejecución no escribe nada');
pa_reset();
pa_origin_ok();
pa_add_post(2201, 'ATA 844 - a', null, 844);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
$pa_after_first = pa_meta(2201, 'mp3-url');
$pa_report      = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(PA_ENCLOSURE_844, $pa_after_first, 'la primera ejecución repara el audio');
pa_assert_equals(0, $pa_report['repaired'], 'la segunda ejecución no repara nada');
pa_assert_equals($pa_after_first, pa_meta(2201, 'mp3-url'), 'la segunda ejecución no escribe ninguna meta');
pa_assert_equals(1, $pa_report['ok'], 'el resumen informa del episodio ya correcto');

// ---------------------------------------------------------------------------
// Escenario 23 — El límite acota la ejecución
// ---------------------------------------------------------------------------
pa_section('El límite acota la ejecución');
pa_reset();
pa_origin_ok();
pa_add_post(2301, 'ATA 844 - a', null, 844);
pa_add_post(2302, 'ATA839 - b', null, 839);
pa_add_post(2303, '705 - c', null, 705);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 2, 'missing' => false));
$pa_written = 0;
foreach (array(2301, 2302, 2303) as $pa_id) {
    if (null !== pa_meta($pa_id, 'mp3-url')) {
        $pa_written++;
    }
}
pa_assert_equals(2, $pa_written, 'el límite revisa como máximo N episodios');
pa_assert_equals(2, $pa_report['repaired'], 'el resumen cuenta dos reparaciones');
pa_assert_equals(1, $pa_report['remaining'], 'el resumen indica cuántos quedan pendientes');

// ---------------------------------------------------------------------------
// Escenario 24 — Solo los episodios con el audio ausente o inválido
// ---------------------------------------------------------------------------
pa_section('Solo los episodios con el audio ausente o inválido');
pa_reset();
pa_origin_ok();
pa_add_post(2401, 'ATA 844 - a', PA_ENCLOSURE_844, 844);
pa_add_post(2402, 'ATA839 - b', null, 839);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => true));
pa_assert_equals(PA_ENCLOSURE_844, pa_meta(2401, 'mp3-url'), 'con --missing no se modifica el mp3-url válido');
pa_assert_equals(PA_ENCLOSURE_839, pa_meta(2402, 'mp3-url'), 'con --missing se repara el audio ausente');
pa_assert_equals(1, $pa_report['repaired'], 'solo se repara el episodio con audio ausente');

// ---------------------------------------------------------------------------
// Escenario 25 — El comando informa de cada fallo sin abortar
// ---------------------------------------------------------------------------
pa_section('El comando informa de cada fallo sin abortar');
pa_reset();
pa_origin_ok();
pa_add_post(2501, 'ATA 844 - a', null, 844);
pa_add_post(2502, 'ATA 552 - roto', null, 552);
pa_add_post(2503, 'ATA839 - b', null, 839);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(PA_ENCLOSURE_844, pa_meta(2501, 'mp3-url'), 'el primer episodio se repara');
pa_assert_equals(PA_ENCLOSURE_839, pa_meta(2503, 'mp3-url'), 'el episodio posterior al fallo también se repara');
pa_assert_equals(null, pa_meta(2502, 'mp3-url'), 'el episodio con enclosure inválido no recibe meta');
pa_assert_equals(1, $pa_report['failed'], 'el resumen cuenta el episodio fallido');
pa_assert_equals(2, $pa_report['repaired'], 'el resumen cuenta los dos reparados');

// ---------------------------------------------------------------------------
// Escenario 26 — Solo se guardan URLs absolutas http(s)
// ---------------------------------------------------------------------------
pa_section('Solo se guardan URLs absolutas http(s)');
pa_reset();
pa_assert_true(
    \Atareao\PodcastAudio::isUsableAudioUrl('https://d3ctxlq1ktw2nl.cloudfront.net/x.mp3'),
    'una URL https absoluta es usable'
);
pa_assert_true(
    \Atareao\PodcastAudio::isUsableAudioUrl('http://example.com/x.mp3'),
    'una URL http absoluta es usable'
);
pa_assert_true(!\Atareao\PodcastAudio::isUsableAudioUrl('15'), 'el valor "15" no es usable');
pa_assert_true(!\Atareao\PodcastAudio::isUsableAudioUrl(''), 'la cadena vacía no es usable');
pa_assert_true(
    !\Atareao\PodcastAudio::isUsableAudioUrl('javascript:alert(1)'),
    'un esquema no permitido no es usable'
);
pa_assert_true(
    !\Atareao\PodcastAudio::isUsableAudioUrl('ftp://example.com/x.mp3'),
    'un esquema que no es http(s) no es usable'
);

// ---------------------------------------------------------------------------
// Escenario 27 — Un valor no saneable no se escribe
// ---------------------------------------------------------------------------
pa_section('Un valor no saneable no se escribe');
pa_reset();
$pa_items = \Atareao\PodcastAudio::parseOriginFeed(pa_origin_ok());
$pa_index = \Atareao\PodcastAudio::buildIndex($pa_items);
$pa_plan  = \Atareao\PodcastAudio::planSync(
    array('id' => 2701, 'title' => 'ATA 552 - x', 'mp3_url' => '', 'number' => 552),
    $pa_index
);
pa_assert_equals('failed', $pa_plan['class'], 'el enclosure "15" del origen no es saneable y se cuenta como fallido');
pa_assert_equals(null, $pa_plan['value'], 'un enclosure no saneable no propone valor');

$pa_bad_index = \Atareao\PodcastAudio::buildIndex(
    array(
        array(
            'title'     => 'ATA 9999 - x',
            'enclosure' => 'javascript:alert(1)',
            'identity'  => '',
            'number'    => 9999,
        ),
    )
);
$pa_plan2 = \Atareao\PodcastAudio::planSync(
    array('id' => 2702, 'title' => 'ATA 9999 - x', 'mp3_url' => '', 'number' => 9999),
    $pa_bad_index
);
pa_assert_equals('failed', $pa_plan2['class'], 'un enclosure con esquema no permitido se cuenta como fallido');
pa_assert_equals(null, $pa_plan2['value'], 'un esquema no permitido no propone valor');

pa_reset();
pa_origin_ok();
pa_add_post(2701, 'ATA 552 - x', null, 552);
\Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 0, 'missing' => false));
pa_assert_equals(null, pa_meta(2701, 'mp3-url'), 'el episodio con enclosure no saneable no recibe meta');

// ---------------------------------------------------------------------------
// Escenario — Las opciones del comando se validan
// ---------------------------------------------------------------------------
pa_section('Las opciones del comando se validan');
pa_reset();
pa_add_post(2301, 'ATA 844 - a', null, 844);
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => -5, 'missing' => false));
pa_assert_true(isset($pa_report['failed']), 'un límite negativo no rompe la sincronización');

pa_assert_true(
    method_exists('\Atareao\PodcastAudio', 'normalizeLimit'),
    'el módulo expone la validación de --limit'
);
if (method_exists('\Atareao\PodcastAudio', 'normalizeLimit')) {
    pa_assert_equals(0, \Atareao\PodcastAudio::normalizeLimit(-5), 'un límite negativo se normaliza a 0');
    pa_assert_equals(3, \Atareao\PodcastAudio::normalizeLimit('3'), 'un límite numérico se conserva');
}

pa_assert_true(
    method_exists('\Atareao\PodcastAudio', 'normalizeFormat'),
    'el módulo expone la validación de --format'
);
if (method_exists('\Atareao\PodcastAudio', 'normalizeFormat')) {
    pa_assert_equals('table', \Atareao\PodcastAudio::normalizeFormat(''), 'el formato por omisión es table');
    pa_assert_equals('json', \Atareao\PodcastAudio::normalizeFormat('json'), 'el formato json se acepta');
    pa_assert_equals('table', \Atareao\PodcastAudio::normalizeFormat('table'), 'el formato table se acepta');
    pa_assert_equals(null, \Atareao\PodcastAudio::normalizeFormat('xml'), 'un formato desconocido no se acepta');
}

pa_reset();
pa_add_post(2401, 'ATA 844 - a', null, 844);
pa_add_post(2402, 'ATA839 - b', null, 839);
pa_add_post(2403, '705 - c', null, 705);
$GLOBALS['mock_remote_response'] = new WP_Error('http_request_failed', 'origen caído');
$pa_report = \Atareao\PodcastAudio::syncEpisodes(array('dry_run' => false, 'limit' => 2, 'missing' => false));
pa_assert_equals(2, $pa_report['failed'], 'con el origen caído y límite 2 se cuentan 2 fallos');
pa_assert_equals(1, $pa_report['remaining'], 'con el origen caído y límite 2 queda 1 pendiente');

pa_finish();
