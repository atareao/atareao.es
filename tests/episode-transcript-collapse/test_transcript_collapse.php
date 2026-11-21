<?php
/**
 * Arnés procedural del change `episode-transcript-collapse`.
 *
 * Ejercita el contrato del delta `podcast-transcript-page`: la transcripción se
 * presenta **plegada** por defecto en un `<details>` nativo con su `<summary>`
 * como primer hijo, conservando las clases `episode-transcript` /
 * `episode-transcript__title` y el `aria-label`, sin el atributo `open`, con el
 * texto presente en el HTML (indexable) y escapado, sin sección cuando no hay
 * transcripción accesible, y con CSS que no oculta el texto ni recorta el foco.
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

/**
 * Extrae las reglas `selector { cuerpo }` de una hoja CSS, sin comentarios.
 *
 * Suficiente para este arnés: las reglas de `.episode-transcript` viven al
 * primer nivel (sin anidamiento dentro de `@media`), así que el par
 * selector/cuerpo se aísla con una expresión regular.
 *
 * @param string $css Contenido CSS.
 * @return array<int, array{selector: string, body: string}>
 */
function etc_css_rules($css)
{
    $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) $css);
    $rules = array();
    if (preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as $match) {
            $rules[] = array(
                'selector' => trim($match[1]),
                'body'     => trim($match[2]),
            );
        }
    }

    return $rules;
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

$etc_special_tmp = tempnam(sys_get_temp_dir(), 'etc-special-');
file_put_contents(
    $etc_special_tmp,
    "1\n00:00:00,000 --> 00:00:02,000\nTom & Jerry <3 \"cita\" 'apóstrofo'.\n"
);

$GLOBALS['mock_attachments'] = array(
    9001 => array(
        'url'  => 'https://atareao.es/wp-content/uploads/2026/11/episodio.srt',
        'file' => $etc_srt_tmp,
        'mime' => 'application/x-subrip',
    ),
    9002 => array(
        'url'  => 'https://atareao.es/wp-content/uploads/2026/11/especiales.srt',
        'file' => $etc_special_tmp,
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

$etc_details_open = preg_match('/<details\b[^>]*>/i', $etc_html, $etc_details_match) === 1
    ? $etc_details_match[0]
    : '';

// ---------------------------------------------------------------------------
// 1.1 — con transcripción: `<details>` + clase + `<summary>` «Transcripción» + texto.
// ---------------------------------------------------------------------------
echo "\n--- 1.1 con transcripción: desplegable, summary y texto ---\n";

etc_assert_true(strpos($etc_html, '<details') !== false, 'transcriptHtml emite un <details>');
etc_assert_true(
    strpos($etc_html, 'class="episode-transcript"') !== false,
    'el <details> conserva la clase episode-transcript'
);
etc_assert_true(
    strpos($etc_details_open, 'aria-label="Transcripción del episodio"') !== false,
    'el <details> conserva el aria-label «Transcripción del episodio»'
);
etc_assert_true(strpos($etc_html, '<summary') !== false, 'transcriptHtml emite un <summary>');
etc_assert_true(
    strpos($etc_html, 'episode-transcript__title') !== false,
    'el <summary> conserva la clase episode-transcript__title'
);
etc_assert_true(
    preg_match('/<details\b[^>]*>\s*<summary\b/i', $etc_html) === 1,
    'el <summary> es el primer hijo directo del <details> (inmediatamente tras su apertura)'
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

// ---------------------------------------------------------------------------
// 5 — escapado del texto de los párrafos (fixture con &, < y ").
// ---------------------------------------------------------------------------
echo "\n--- 5 escapado del texto de los párrafos ---\n";

$etc_special_html = \Atareao\PodcastFeed::transcriptHtml(9002);
etc_assert_true(
    strpos($etc_special_html, 'Tom &amp; Jerry &lt;3 &quot;cita&quot;') !== false,
    'el texto de los párrafos se escapa como entidades HTML (&, <, ")'
);
etc_assert_true(
    strpos($etc_special_html, '<3') === false,
    'no se sirve un "<" crudo del texto de la transcripción'
);

// ---------------------------------------------------------------------------
// 4/7 — CSS: no se oculta el texto, existe el estado [open] y el foco no se recorta.
// ---------------------------------------------------------------------------
echo "\n--- 4/7 CSS: no se oculta el texto y hay estado [open] ---\n";

$etc_css_path = ETC_ROOT . '/wp-content/themes/atareao-theme/css/custom-post-types.css';
etc_assert_true(is_file($etc_css_path), 'existe css/custom-post-types.css');

$etc_css_rules = is_file($etc_css_path)
    ? etc_css_rules((string) file_get_contents($etc_css_path))
    : array();

$etc_transcript_rules = array();
$etc_open_rule = false;
foreach ($etc_css_rules as $etc_rule) {
    if (preg_match('/\.episode-transcript(__title)?\b/', $etc_rule['selector']) !== 1) {
        continue;
    }
    if (strpos($etc_rule['selector'], '::') === false) {
        $etc_transcript_rules[] = $etc_rule;
    }
    if (preg_match('/\.episode-transcript\[open\]/', $etc_rule['selector']) === 1) {
        $etc_open_rule = true;
    }
}

etc_assert_true(count($etc_transcript_rules) > 0, 'hay reglas CSS para .episode-transcript');

$etc_hidden = array();
foreach ($etc_transcript_rules as $etc_rule) {
    if (preg_match('/display\s*:\s*none/i', $etc_rule['body']) === 1
        || preg_match('/visibility\s*:\s*hidden/i', $etc_rule['body']) === 1
    ) {
        $etc_hidden[] = $etc_rule['selector'];
    }
}
etc_assert_true(
    empty($etc_hidden),
    'ninguna regla CSS oculta la transcripción (.episode-transcript / p / __title) con display:none o visibility:hidden'
);

etc_assert_true($etc_open_rule, 'existe el bloque .episode-transcript[open]');

$etc_base_body = '';
foreach ($etc_transcript_rules as $etc_rule) {
    if (preg_match('/^\.episode-transcript\s*$/', $etc_rule['selector']) === 1) {
        $etc_base_body = $etc_rule['body'];
    }
}
etc_assert_true(
    preg_match('/overflow\s*:\s*hidden/i', $etc_base_body) !== 1,
    'la regla base .episode-transcript no usa overflow: hidden (no recorta el outline de foco)'
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
