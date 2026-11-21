<?php
/**
 * Arnés procedural del change `episode-transcript-collapse`.
 *
 * Ejercita el contrato del delta `podcast-transcript-page`: la transcripción se
 * presenta **plegada** por defecto en un `<details>` nativo con su `<summary>`,
 * conservando las clases `episode-transcript` / `episode-transcript__title`, sin
 * el atributo `open`, con el texto presente en el HTML (indexable) y sin sección
 * cuando no hay transcripción accesible.
 *
 * Mismo estilo que `tests/podcast-feed-soberania/test_feed_complete.php`:
 * contador propio de pases/fallos, `exit(0)` si todo pasa y `exit(1)` si algo
 * falla. Se ejecuta con `just test-episode-transcript-collapse`.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('ETC_ROOT', dirname(__DIR__, 2));
define('ETC_FEED', ETC_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php');

if (is_file(ETC_FEED)) {
    require_once ETC_FEED;
}

$GLOBALS['etc_passes']   = 0;
$GLOBALS['etc_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function etc_pass($description)
{
    $GLOBALS['etc_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function etc_fail($description)
{
    $GLOBALS['etc_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function etc_assert_true($condition, $description)
{
    if ($condition) {
        etc_pass($description);

        return;
    }

    etc_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function etc_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        etc_pass($description);

        return;
    }

    etc_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

echo "episode-transcript-collapse — transcripción plegada en un <details> nativo\n";

harness_reset_state();

$etc_srt_tmp = tempnam(sys_get_temp_dir(), 'etc-srt-');
file_put_contents(
    $etc_srt_tmp,
    "1\n00:00:00,000 --> 00:00:02,000\nHola y bienvenidos al episodio.\n\n"
    . "2\n00:00:02,000 --> 00:00:04,000\nHoy hablamos de la transcripción plegada.\n"
);
$etc_missing_tmp = sys_get_temp_dir() . '/etc-no-existe-' . uniqid() . '.srt';

$GLOBALS['mock_attachments'] = array(
    9001 => array(
        'url'  => 'https://atareao.es/wp-content/uploads/2026/11/episodio.srt',
        'file' => $etc_srt_tmp,
        'mime' => 'application/x-subrip',
    ),
    9004 => array(
        'url'  => 'https://atareao.es/wp-content/uploads/2026/11/inexistente.srt',
        'file' => $etc_missing_tmp,
    ),
);

etc_assert_true(is_file(ETC_FEED), 'existe includes/class-podcast-feed.php');
etc_assert_true(class_exists('\Atareao\PodcastFeed'), 'la clase \Atareao\PodcastFeed está disponible');
etc_assert_true(
    method_exists('\Atareao\PodcastFeed', 'transcriptHtml'),
    'transcriptHtml() está disponible'
);

$etc_html = \Atareao\PodcastFeed::transcriptHtml(9001);

// ---------------------------------------------------------------------------
// 1.1 — con transcripción: `<details>` + clase + `<summary>` «Transcripción» + texto.
// ---------------------------------------------------------------------------
echo "\n--- 1.1 con transcripción: desplegable, summary y texto ---\n";

etc_assert_true(strpos($etc_html, '<details') !== false, 'transcriptHtml emite un <details>');
etc_assert_true(
    strpos($etc_html, 'class="episode-transcript"') !== false,
    'el <details> conserva la clase episode-transcript'
);
etc_assert_true(strpos($etc_html, '<summary') !== false, 'transcriptHtml emite un <summary>');
etc_assert_true(
    strpos($etc_html, 'episode-transcript__title') !== false,
    'el <summary> conserva la clase episode-transcript__title'
);
etc_assert_true(
    strpos($etc_html, 'Transcripción') !== false,
    'el <summary> muestra el texto «Transcripción»'
);
etc_assert_true(
    strpos($etc_html, 'Hola y bienvenidos al episodio.') !== false,
    'el texto de la transcripción sigue presente en el HTML (indexable)'
);
etc_assert_true(
    strpos($etc_html, 'Hoy hablamos de la transcripción plegada.') !== false,
    'todo el texto hablado permanece en el HTML servido'
);

// ---------------------------------------------------------------------------
// 1.2 — plegado por defecto: el `<details>` NO lleva el atributo `open`.
// ---------------------------------------------------------------------------
echo "\n--- 1.2 plegado por defecto (sin open) ---\n";

$etc_details_open = preg_match('/<details\b[^>]*>/i', $etc_html, $etc_details_match) === 1
    ? $etc_details_match[0]
    : '';
etc_assert_true($etc_details_open !== '', 'se localiza la etiqueta <details> de apertura');
etc_assert_true(
    preg_match('/\bopen\b/i', $etc_details_open) !== 1,
    'el <details> NO lleva el atributo open (plegado por defecto)'
);

// ---------------------------------------------------------------------------
// 1.3 — sin transcripción accesible → cadena vacía (sin sección).
// ---------------------------------------------------------------------------
echo "\n--- 1.3 sin transcripción accesible ---\n";

etc_assert_equals('', \Atareao\PodcastFeed::transcriptHtml(9004), 'un SRT inaccesible no produce HTML');
etc_assert_equals('', \Atareao\PodcastFeed::transcriptHtml(0), 'un id no positivo no produce HTML');

// ---------------------------------------------------------------------------
// 2.2 — accesibilidad: el <summary> es nativo y tiene texto visible.
// ---------------------------------------------------------------------------
echo "\n--- 2.2 accesibilidad del <summary> ---\n";

$etc_summary_text = preg_match('/<summary\b[^>]*>(.*?)<\/summary>/is', $etc_html, $etc_summary_match) === 1
    ? trim(strip_tags($etc_summary_match[1]))
    : '';
etc_assert_true(
    $etc_summary_text !== '',
    'el <summary> tiene texto visible (operable por teclado de forma nativa)'
);
etc_assert_true(
    $etc_summary_text === 'Transcripción',
    'el texto visible del <summary> es «Transcripción»'
);

echo "\nResumen: {$GLOBALS['etc_passes']} PASS, " . count($GLOBALS['etc_failures']) . " FAIL\n";

if (!empty($GLOBALS['etc_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['etc_failures'] as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

exit(0);
