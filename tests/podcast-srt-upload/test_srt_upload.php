<?php
/**
 * Arnés procedural de la fase RED del módulo `podcast-srt-upload`.
 *
 * Ejercita el contrato del delta `podcast-srt-upload` sobre el módulo de
 * producción `Atareao\SrtUpload` y sobre el selector del metabox, que la fase
 * GREEN implementará. Mientras no existan, cada escenario se imprime como
 * FALLO con motivo «no implementado» y el proceso termina con código 1, sin
 * morir con un fatal.
 *
 * Mismo estilo que `tests/audio-sync/test_podcast_audio.php`: contador propio
 * de pases/fallos, `exit(0)` si todo pasa y `exit(1)` si algo falla.
 * Se ejecuta con `just test-srt-upload`.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('SSU_ROOT', dirname(__DIR__, 2));
define('SSU_MODULE', SSU_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-srt-upload.php');
define('SSU_FEED', SSU_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php');
define('SSU_METABOXES', SSU_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-metaboxes.php');

/*
 * Carga los módulos de producción cuando existan. En RED no existen y cada
 * escenario se reporta como fallo, sin fatal.
 */
foreach (array(SSU_FEED, SSU_MODULE, SSU_METABOXES) as $ssu_bootstrap_file) {
    if (is_file($ssu_bootstrap_file)) {
        require_once $ssu_bootstrap_file;
    }
}

$GLOBALS['ssu_passes']   = 0;
$GLOBALS['ssu_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function ssu_pass($description)
{
    $GLOBALS['ssu_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function ssu_fail($description)
{
    $GLOBALS['ssu_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function ssu_assert_true($condition, $description)
{
    if ($condition) {
        ssu_pass($description);

        return;
    }

    ssu_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function ssu_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        ssu_pass($description);

        return;
    }

    ssu_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

/**
 * Mapa de tipos de core relevante, copiado de `wp_get_mime_types()`.
 *
 * La clave agrupada `txt|asc|c|cc|h|srt` es literal de core: es la que gana
 * cuando el tipo de `srt` se añade al final del mapa.
 *
 * @return array<string, string>
 */
function ssu_core_mimes()
{
    return array(
        'txt|asc|c|cc|h|srt' => 'text/plain',
        'csv'                => 'text/csv',
        'vtt'                => 'text/vtt',
        'mp3|m4a|m4b'        => 'audio/mpeg',
    );
}

/**
 * Resuelve el tipo de un fichero como `wp_check_filetype()`: el PRIMER patrón
 * que casa gana, y el orden de las claves del mapa es el que decide.
 *
 * @param string                $filename Nombre de fichero.
 * @param array<string, string> $mimes    Mapa extensión => tipo.
 * @return array{ext: string|false, type: string|false}
 */
function ssu_check_filetype($filename, array $mimes)
{
    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    foreach ($mimes as $pattern => $mime) {
        foreach (explode('|', (string) $pattern) as $candidate) {
            if ($extension === strtolower($candidate)) {
                return array('ext' => $extension, 'type' => $mime);
            }
        }
    }

    return array('ext' => false, 'type' => false);
}

echo "podcast-srt-upload — contrato del tipo de subida y del selector\n";

/*
 * 1. El módulo existe y expone el filtro.
 */
ssu_assert_true(is_file(SSU_MODULE), 'existe includes/class-srt-upload.php');
ssu_assert_true(
    class_exists('\Atareao\SrtUpload'),
    'la clase \Atareao\SrtUpload está disponible'
);
ssu_assert_true(
    method_exists('\Atareao\SrtUpload', 'allowSrtMime'),
    'el módulo expone allowSrtMime()'
);

/*
 * 2. El tipo registrado gana al de core (escenario «El tipo registrado gana al
 * de core»): la clave `srt` tiene que ir PRIMERO.
 */
if (class_exists('\Atareao\SrtUpload') && method_exists('\Atareao\SrtUpload', 'allowSrtMime')) {
    $filtered = \Atareao\SrtUpload::allowSrtMime(ssu_core_mimes());

    ssu_assert_true(
        array_key_first($filtered) === 'srt',
        'la clave srt es la primera del mapa filtrado'
    );

    $resolved = ssu_check_filetype('prueba.srt', $filtered);
    ssu_assert_equals('srt', $resolved['ext'], 'un .srt resuelve su extensión');
    ssu_assert_equals(
        'application/x-subrip',
        $resolved['type'],
        'un .srt resuelve application/x-subrip y no text/plain'
    );
    ssu_assert_equals(
        'application/x-subrip',
        ssu_check_filetype('episodio-12.srt', $filtered)['type'],
        'la resolución no depende del nombre del fichero'
    );
}

/*
 * 3. El tipo sale de la constante del feed, no de un literal duplicado.
 */
if (class_exists('\Atareao\PodcastFeed')) {
    ssu_assert_equals(
        'application/x-subrip',
        \Atareao\PodcastFeed::TRANSCRIPT_MEDIA_TYPE,
        'PodcastFeed::TRANSCRIPT_MEDIA_TYPE sigue siendo application/x-subrip'
    );
}

/*
 * 4. Sin regresión en los tipos de core.
 */
if (class_exists('\Atareao\SrtUpload') && method_exists('\Atareao\SrtUpload', 'allowSrtMime')) {
    $filtered = \Atareao\SrtUpload::allowSrtMime(ssu_core_mimes());

    ssu_assert_equals(
        'text/plain',
        ssu_check_filetype('notas.txt', $filtered)['type'],
        'un .txt sigue resolviendo text/plain'
    );
    ssu_assert_equals(
        'text/vtt',
        ssu_check_filetype('subs.vtt', $filtered)['type'],
        'un .vtt conserva su tipo'
    );
    ssu_assert_equals(
        'text/plain',
        $filtered['txt|asc|c|cc|h|srt'],
        'el patrón agrupado de core queda intacto'
    );
}

/*
 * 5. El filtro se registra en `upload_mimes` al inicializar el módulo.
 */
if (class_exists('\Atareao\SrtUpload') && method_exists('\Atareao\SrtUpload', 'init')) {
    $GLOBALS['mock_filters'] = array();
    \Atareao\SrtUpload::init();

    ssu_assert_true(
        has_filter('upload_mimes', array(\Atareao\SrtUpload::class, 'allowSrtMime')),
        'init() engancha allowSrtMime en upload_mimes'
    );

    $applied = apply_filters('upload_mimes', ssu_core_mimes());
    ssu_assert_equals(
        'application/x-subrip',
        ssu_check_filetype('prueba.srt', $applied)['type'],
        'aplicar upload_mimes ya resuelve application/x-subrip'
    );
}

/*
 * 6. El selector del metabox acepta los dos tipos posibles (escenarios «Un SRT
 * con el tipo del pipeline es seleccionable» y «Un SRT con tipo text/plain es
 * seleccionable»).
 */
if (class_exists('\Atareao\SrtUpload') && class_exists('\Atareao\Metaboxes')) {
    ssu_assert_true(
        method_exists('\Atareao\Metaboxes', 'transcriptLibraryTypes'),
        'el metabox expone transcriptLibraryTypes()'
    );

    if (method_exists('\Atareao\Metaboxes', 'transcriptLibraryTypes')) {
        ssu_assert_equals(
            array('application/x-subrip', 'text/plain'),
            \Atareao\Metaboxes::transcriptLibraryTypes(),
            'el selector pide application/x-subrip y text/plain'
        );
    }
}

echo "\nResumen: {$GLOBALS['ssu_passes']} PASS, " . count($GLOBALS['ssu_failures']) . " FAIL\n";

if (!empty($GLOBALS['ssu_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['ssu_failures'] as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

exit(0);
