<?php
/**
 * Arnés del change `youtube-video-fallback`: comando WP-CLI de backfill del
 * metadato `youtube_url` desde un TSV de mapeo episodio↔vídeo.
 *
 * Ejercita el contrato de la propuesta/design (Decisión 4): el comando
 * `wp atareao youtube-video backfill --file=<tsv>` lee el fichero, resuelve
 * cada fila por `post_id`, escribe `youtube_url` con la URL canónica del
 * `videoId`, es idempotente, `--dry-run` informa sin escribir, `--force`
 * reescribe un valor previo y una fila con `post_id` inexistente se reporta
 * sin abortar. Mismo estilo que `tests/podcast-duration/test_backfill.php`.
 *
 * Los valores esperados se escriben literales: nunca se calculan con la función
 * bajo prueba. Se ejecuta con `just php tests/youtube-video-fallback/test_backfill.php`.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('YVB_ROOT', dirname(__DIR__, 2));
define('YVB_INCLUDES', YVB_ROOT . '/wp-content/plugins/atareao-functionality/includes');
define('YVB_YT', YVB_INCLUDES . '/class-youtube-video.php');
define('YVB_FIXTURE', __DIR__ . '/fixtures/mapping.tsv');

$GLOBALS['yvb_passes']   = 0;
$GLOBALS['yvb_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function yvb_pass($description)
{
    $GLOBALS['yvb_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function yvb_fail($description)
{
    $GLOBALS['yvb_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function yvb_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        yvb_pass($description);

        return;
    }

    yvb_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function yvb_assert_true($condition, $description)
{
    yvb_assert_equals(true, (bool) $condition, $description);
}

/**
 * Mensajes de WP-CLI de un tipo.
 *
 * @param string $type Tipo (`log`, `success`, `warning`, `error`).
 * @return string[]
 */
function yvb_cli_messages($type)
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
 * Siembra el archivo con los episodios del fixture y el metadato de partida.
 *
 * @param string $meta_7002 Valor previo de `youtube_url` en el post 7002.
 * @return void
 */
function yvb_seed($meta_7002 = 'https://www.youtube.com/watch?v=gxC35wcbW_s')
{
    $GLOBALS['mock_posts']     = array();
    $GLOBALS['mock_post_meta'] = array();

    foreach (array(7001, 7002, 7003) as $yvb_id) {
        $GLOBALS['mock_posts'][] = new \WP_Post(array(
            'ID'          => $yvb_id,
            'post_type'   => 'podcast',
            'post_status' => 'publish',
            'post_title'  => 'Episodio ' . $yvb_id,
        ));
        $GLOBALS['mock_post_meta'][$yvb_id] = array();
    }

    if ('' !== $meta_7002) {
        $GLOBALS['mock_post_meta'][7002]['youtube_url'] = $meta_7002;
    }
}

/**
 * Snapshot de los metadatos de los episodios.
 *
 * @return array
 */
function yvb_snapshot()
{
    return isset($GLOBALS['mock_post_meta']) ? $GLOBALS['mock_post_meta'] : array();
}

/**
 * Escribe un TSV temporal con la cabecera real del mapeo.
 *
 * @param string[] $rows Filas ya tabuladas.
 * @return string Ruta del fichero temporal.
 */
function yvb_tsv($rows)
{
    $yvb_path = tempnam(sys_get_temp_dir(), 'yvb-');
    $yvb_header = "post_id\tslug\tfecha_ep\tvideoId\tnum_video\tfecha_video\ttitulo\n";
    file_put_contents($yvb_path, $yvb_header . implode("\n", $rows) . "\n");

    return $yvb_path;
}

echo "youtube-video — backfill desde TSV\n";

if (!is_file(YVB_YT)) {
    echo "  FAIL: existe includes/class-youtube-video.php\n";
    echo "\nResumen: 0 PASS, 1 FAIL\n";
    exit(1);
}

require_once YVB_INCLUDES . '/class-seo.php';
require_once YVB_YT;

yvb_assert_true(class_exists('\Atareao\YoutubeVideo'), 'la clase \Atareao\YoutubeVideo está disponible');
yvb_assert_true(
    class_exists('\Atareao\YoutubeVideo') && method_exists('\Atareao\YoutubeVideo', 'backfill'),
    'existe YoutubeVideo::backfill()'
);
yvb_assert_true(
    class_exists('\Atareao\YoutubeVideo') && method_exists('\Atareao\YoutubeVideo', 'cliBackfill'),
    'existe YoutubeVideo::cliBackfill()'
);
yvb_assert_equals(
    'youtube_url',
    defined('\Atareao\YoutubeVideo::META_KEY') ? \Atareao\YoutubeVideo::META_KEY : null,
    'la clave del metadato es youtube_url'
);

if (!class_exists('\Atareao\YoutubeVideo') || !method_exists('\Atareao\YoutubeVideo', 'backfill')) {
    echo "\nResumen: {$GLOBALS['yvb_passes']} PASS, " . count($GLOBALS['yvb_failures']) . " FAIL\n";
    exit(1);
}

// ---------------------------------------------------------------------------
echo "\n--- --dry-run: informa sin escribir ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
yvb_seed();
$yvb_before = yvb_snapshot();

$yvb_dry = \Atareao\YoutubeVideo::backfill(array('file' => YVB_FIXTURE, 'dry_run' => true));

yvb_assert_equals(4, (int) $yvb_dry['total'], 'el ensayo cuenta las cuatro filas del mapeo');
yvb_assert_equals(1, (int) $yvb_dry['actualizados'], 'el ensayo cuenta una fila que escribiría');
yvb_assert_equals(1, (int) $yvb_dry['sin_cambios'], 'el ensayo cuenta una fila ya correcta');
yvb_assert_equals(1, (int) $yvb_dry['sin_video'], 'el ensayo cuenta una fila sin vídeo');
yvb_assert_equals(1, (int) $yvb_dry['inexistentes'], 'el ensayo cuenta una fila con post inexistente');
yvb_assert_true($yvb_before === yvb_snapshot(), 'el ensayo no escribe ningún metadato');
yvb_assert_equals(1, count($yvb_dry['changes'] ?? array()), 'el ensayo devuelve la lista de cambios');
yvb_assert_equals(
    array('post_id' => 7001, 'from' => '', 'to' => 'https://www.youtube.com/watch?v=a1b2c3d4e5f'),
    $yvb_dry['changes'][0] ?? null,
    'cada cambio lleva post_id, valor previo y valor nuevo'
);

// ---------------------------------------------------------------------------
echo "\n--- Pasada real: escribe por post_id e idempotente ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
yvb_seed();

$yvb_report = \Atareao\YoutubeVideo::backfill(array('file' => YVB_FIXTURE));

yvb_assert_equals(1, (int) $yvb_report['actualizados'], 'la pasada real actualiza una fila');
yvb_assert_equals(1, (int) $yvb_report['sin_cambios'], 'la fila ya correcta queda en sin_cambios');
yvb_assert_equals(1, (int) $yvb_report['sin_video'], 'la fila sin vídeo se salta');
yvb_assert_equals(1, (int) $yvb_report['inexistentes'], 'la fila con post inexistente se reporta');
yvb_assert_equals(
    'https://www.youtube.com/watch?v=a1b2c3d4e5f',
    $GLOBALS['mock_post_meta'][7001]['youtube_url'] ?? null,
    'escribe la URL canónica del videoId de la fila 7001'
);
yvb_assert_equals(
    'https://www.youtube.com/watch?v=gxC35wcbW_s',
    $GLOBALS['mock_post_meta'][7002]['youtube_url'] ?? null,
    'no toca una fila cuyo valor ya coincide'
);
yvb_assert_true(!isset($GLOBALS['mock_post_meta'][7003]['youtube_url']), 'una fila sin vídeo no escribe metadato');

$yvb_before_second = yvb_snapshot();
$yvb_second = \Atareao\YoutubeVideo::backfill(array('file' => YVB_FIXTURE));
$yvb_after_second = yvb_snapshot();

yvb_assert_equals(0, (int) $yvb_second['actualizados'], 'la segunda pasada no actualiza nada');
yvb_assert_equals(2, (int) $yvb_second['sin_cambios'], 'en la segunda pasada las dos filas válidas están sin cambios');
yvb_assert_true($yvb_before_second === $yvb_after_second, 'la segunda pasada no cambia ningún metadato');

// ---------------------------------------------------------------------------
echo "\n--- Valor previo distinto: sin --force se respeta ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
yvb_seed('https://www.youtube.com/watch?v=WRONGWRONG1');

$yvb_no_force = \Atareao\YoutubeVideo::backfill(array('file' => YVB_FIXTURE));

yvb_assert_equals(1, (int) $yvb_no_force['protegidos'], 'sin --force un valor previo distinto queda protegido');
yvb_assert_equals(
    'https://www.youtube.com/watch?v=WRONGWRONG1',
    $GLOBALS['mock_post_meta'][7002]['youtube_url'] ?? null,
    'sin --force no se pisa el valor previo'
);

// ---------------------------------------------------------------------------
echo "\n--- --force: reescribe un valor previo ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
yvb_seed('https://www.youtube.com/watch?v=WRONGWRONG1');

$yvb_force = \Atareao\YoutubeVideo::backfill(array('file' => YVB_FIXTURE, 'force' => true));

yvb_assert_equals(2, (int) $yvb_force['actualizados'], '--force actualiza las dos filas resolubles');
yvb_assert_equals(0, (int) $yvb_force['protegidos'], '--force no deja filas protegidas');
yvb_assert_equals(
    'https://www.youtube.com/watch?v=gxC35wcbW_s',
    $GLOBALS['mock_post_meta'][7002]['youtube_url'] ?? null,
    '--force corrige el valor previo con el del mapeo'
);

// ---------------------------------------------------------------------------
echo "\n--- Fila con URL: se usa tal cual ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
yvb_seed();
$yvb_url_row = yvb_tsv(array(
    "7001\tepisodio-uno\t2026-01-01\thttps://www.youtube.com/watch?v=a1b2c3d4e5f\t1\t2026-01-02\tEpisodio uno",
));
$yvb_url_report = \Atareao\YoutubeVideo::backfill(array('file' => $yvb_url_row));
unlink($yvb_url_row);

yvb_assert_equals(1, (int) $yvb_url_report['actualizados'], 'una fila con URL en videoId se escribe');
yvb_assert_equals(
    'https://www.youtube.com/watch?v=a1b2c3d4e5f',
    $GLOBALS['mock_post_meta'][7001]['youtube_url'] ?? null,
    'la URL del mapeo se usa tal cual'
);

// ---------------------------------------------------------------------------
echo "\n--- Origen ilegible ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
yvb_seed();
$yvb_before_invalid = yvb_snapshot();
$yvb_missing = \Atareao\YoutubeVideo::backfill(array('file' => '/tmp/yvb-no-existe-' . uniqid() . '.tsv'));

yvb_assert_true('' !== (string) ($yvb_missing['origin_error'] ?? ''), 'un --file inexistente se reporta como origin_error');
yvb_assert_equals(0, (int) $yvb_missing['actualizados'], 'un --file inexistente no actualiza nada');
yvb_assert_true($yvb_before_invalid === yvb_snapshot(), 'un --file inexistente no escribe ningún metadato');

// ---------------------------------------------------------------------------
echo "\n--- Subcomando WP-CLI ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
yvb_seed();

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}
\Atareao\YoutubeVideo::init();

$yvb_command = 'atareao youtube-video backfill';
yvb_assert_true(
    isset($GLOBALS['mock_cli_commands'][$yvb_command]),
    'el subcomando atareao youtube-video backfill queda registrado'
);

if (isset($GLOBALS['mock_cli_commands'][$yvb_command])) {
    $yvb_callback = $GLOBALS['mock_cli_commands'][$yvb_command];

    $GLOBALS['mock_cli_output'] = array();
    call_user_func($yvb_callback, array(), array('file' => YVB_FIXTURE));

    yvb_assert_true(
        in_array('Actualizados: 1', yvb_cli_messages('log'), true),
        'el subcomando reporta el total de actualizados'
    );
    yvb_assert_true(!empty(yvb_cli_messages('success')), 'la ejecución real termina con éxito');
    yvb_assert_true(!empty(yvb_cli_messages('warning')), 'la fila inexistente se avisa con warning');

    harness_reset_state();
    yvb_seed();
    $yvb_cli_before = yvb_snapshot();

    $GLOBALS['mock_cli_output'] = array();
    call_user_func($yvb_callback, array(), array('file' => YVB_FIXTURE, 'dry-run' => true));

    yvb_assert_true(
        in_array('Habría actualizado: 1', yvb_cli_messages('log'), true),
        'el ensayo del subcomando rotula «Habría actualizado: N»'
    );
    yvb_assert_true($yvb_cli_before === yvb_snapshot(), 'el ensayo del subcomando no escribe');

    harness_reset_state();
    yvb_seed();

    $GLOBALS['mock_cli_output'] = array();
    call_user_func($yvb_callback, array(), array('file' => '/tmp/yvb-no-existe-' . uniqid() . '.tsv'));

    yvb_assert_true(!empty(yvb_cli_messages('error')), 'un origen ilegible se reporta con WP_CLI::error');
    yvb_assert_true(empty(yvb_cli_messages('success')), 'un origen ilegible no declara éxito');
}

echo "\nResumen: {$GLOBALS['yvb_passes']} PASS, " . count($GLOBALS['yvb_failures']) . " FAIL\n";

if (!empty($GLOBALS['yvb_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['yvb_failures'] as $yvb_failure) {
        echo "  - {$yvb_failure}\n";
    }

    exit(1);
}

exit(0);
