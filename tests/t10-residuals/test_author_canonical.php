<?php
/**
 * Arnés del change `2026-11-20-t10-residual-fixes`: autor canónico.
 *
 * Ejercita el requisito «Archivo de autor canónico y redirección del nicename
 * heredado» del delta `seo`:
 *
 * - el paso de datos fija `user_nicename = atareao` para el autor 3, es
 *   idempotente y admite `--dry-run`;
 * - `/author/yyyxfsgj/` responde un único `301` hacia `get_author_posts_url()`
 *   del autor, sin bucle y sin tocar otros nicenames.
 *
 * La redirección se resuelve en `template_redirect` detectando el **path** de la
 * petición: la regla de rewrite del núcleo resuelve el nicename inexistente a
 * un 404, no a un archivo de autor. `SEO::ensureAuthorArchive()` no es la
 * solución y este arnés lo deja documentado con una aserción.
 *
 * Mismo estilo que `tests/podcast-guid-parity/test_guid_emission.php`. Se
 * ejecuta con `just test-t10` y en el job `lint` de CI.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';
require_once __DIR__ . '/assertions.php';

define('T10A_ROOT', dirname(__DIR__, 2));
define('T10A_SEO_FILE', T10A_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-seo.php');
define('T10A_CANONICAL_FILE', T10A_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-author-canonical.php');

if (!is_file(T10A_SEO_FILE)) {
    t10_assert_true(false, 'existe includes/class-seo.php');
    t10_finish();
}
require_once T10A_SEO_FILE;

if (!is_file(T10A_CANONICAL_FILE)) {
    t10_assert_true(false, 'existe includes/class-author-canonical.php');
    t10_finish();
}
require_once T10A_CANONICAL_FILE;

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}

/**
 * Provisiona el autor 3 (nicename heredado) y un segundo autor ajeno.
 *
 * @param string $nicename Nicename actual del autor 3.
 * @param bool   $conflict Si otro usuario ocupa ya `atareao`.
 * @return void
 */
function t10a_fixture($nicename = 'yyyxfsgj', $conflict = false)
{
    harness_reset_state();
    $GLOBALS['mock_users'] = array(
        (object) array(
            'ID' => 3,
            'user_login' => 'lorenzo',
            'user_nicename' => $nicename,
            'display_name' => 'atareao',
        ),
        (object) array(
            'ID' => 7,
            'user_login' => 'invitado',
            'user_nicename' => 'invitado',
            'display_name' => 'Invitado',
        ),
    );
    $GLOBALS['mock_authors'] = array(
        3 => array('nicename' => $nicename, 'display_name' => 'atareao'),
        7 => array('nicename' => 'invitado', 'display_name' => 'Invitado'),
    );

    if ($conflict) {
        $GLOBALS['mock_users'][] = (object) array(
            'ID' => 9,
            'user_login' => 'duplicado',
            'user_nicename' => 'atareao',
            'display_name' => 'Duplicado',
        );
        $GLOBALS['mock_authors'][9] = array('nicename' => 'atareao', 'display_name' => 'Duplicado');
    }
}

/**
 * Invoca el paso de datos, o devuelve `null` mientras no exista.
 *
 * @param bool $dry_run Si solo se simula.
 * @return array|null
 */
function t10a_canonicalize($dry_run = false)
{
    if (!class_exists('\Atareao\AuthorCanonical') || !method_exists('\Atareao\AuthorCanonical', 'canonicalize')) {
        return null;
    }

    return \Atareao\AuthorCanonical::canonicalize($dry_run);
}

/**
 * Extrae el cuerpo de una función con `token_get_all`, sin comentarios.
 *
 * Cargar el bootstrap real en el arnés ejecutaría el `init()` de todos los
 * módulos y exigiría dobles de medio WordPress; en su lugar se comprueba el
 * **código ejecutable** del bootstrap: que hace `require_once` del módulo y que
 * `atareao_functionality_init()` invoca `AuthorCanonical::init()`. El
 * tokenizador descarta comentarios, así que una llamada comentada no cuela.
 *
 * @param string $source Código fuente del bootstrap.
 * @param string $name   Nombre de la función.
 * @return string Cuerpo con llaves, o `''` si no existe.
 */
function t10a_function_body($source, $name)
{
    $tokens = token_get_all($source);
    $total = count($tokens);
    for ($i = 0; $i < $total; $i++) {
        if (!is_array($tokens[$i]) || T_FUNCTION !== $tokens[$i][0]) {
            continue;
        }

        $j = $i + 1;
        while ($j < $total && is_array($tokens[$j]) && T_WHITESPACE === $tokens[$j][0]) {
            $j++;
        }
        if ($j >= $total || !is_array($tokens[$j]) || T_STRING !== $tokens[$j][0] || $name !== $tokens[$j][1]) {
            continue;
        }

        while ($j < $total && '{' !== $tokens[$j]) {
            $j++;
        }

        $depth = 0;
        $body = '';
        for (; $j < $total; $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            if (is_array($tokens[$j]) && (T_COMMENT === $tokens[$j][0] || T_DOC_COMMENT === $tokens[$j][0])) {
                continue;
            }
            $body .= $text;
            if ('{' === $text) {
                $depth++;
            } elseif ('}' === $text) {
                $depth--;
                if (0 === $depth) {
                    return $body;
                }
            }
        }
    }

    return '';
}

/**
 * Indica si el bootstrap cablea `AuthorCanonical::init()`.
 *
 * @return bool
 */
function t10a_bootstrap_wires_author_canonical()
{
    $file = T10A_ROOT . '/wp-content/plugins/atareao-functionality/atareao-functionality.php';
    $source = is_readable($file) ? (string) file_get_contents($file) : '';
    if ('' === $source) {
        return false;
    }

    $body = t10a_function_body($source, 'atareao_functionality_init');

    return 1 === preg_match('/AuthorCanonical\s*::\s*init\s*\(\s*\)/', $body);
}

/**
 * Indica si el fuente hace `require_once`/`include_once` del fichero indicado.
 *
 * Recorre los tokens en busca de `T_REQUIRE_ONCE`/`T_INCLUDE_ONCE` y examina
 * solo la sentencia que abren: un `require_once` comentado es un comentario y
 * una ruta dentro de una cadena ajena a la sentencia no cuenta, así que el
 * análisis no se deja engañar por el texto crudo del fichero.
 *
 * @param string $source Código fuente del bootstrap.
 * @param string $needle Ruta relativa del módulo.
 * @return bool
 */
function t10a_requires_file($source, $needle)
{
    $tokens = token_get_all($source);
    $total = count($tokens);

    for ($i = 0; $i < $total; $i++) {
        if (!is_array($tokens[$i]) || (T_REQUIRE_ONCE !== $tokens[$i][0] && T_INCLUDE_ONCE !== $tokens[$i][0])) {
            continue;
        }

        for ($j = $i + 1; $j < $total; $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            if (';' === $text || '?>' === $text) {
                break;
            }
            if (!is_array($tokens[$j])) {
                continue;
            }
            if (T_COMMENT === $tokens[$j][0] || T_DOC_COMMENT === $tokens[$j][0]) {
                continue;
            }
            if ((T_CONSTANT_ENCAPSED_STRING === $tokens[$j][0] || T_ENCAPSED_AND_WHITESPACE === $tokens[$j][0])
                && false !== strpos($tokens[$j][1], $needle)
            ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Indica si el bootstrap carga el fichero del módulo del autor.
 *
 * @return bool
 */
function t10a_bootstrap_requires_author_canonical()
{
    $file = T10A_ROOT . '/wp-content/plugins/atareao-functionality/atareao-functionality.php';
    $source = is_readable($file) ? (string) file_get_contents($file) : '';
    if ('' === $source) {
        return false;
    }

    return t10a_requires_file($source, 'includes/class-author-canonical.php');
}

/**
 * Resuelve la redirección de un path en un proceso hijo.
 *
 * `redirectLegacyAuthorNicename()` termina con `exit` —como el resto de
 * redirecciones del plugin—, así que el escenario se ejecuta aparte. El hijo
 * imprime `__T10A_CONTINUED__` **después** de la llamada: si el método siguió
 * ejecutando, la marca aparece; si salió por `exit`, no. Además el `shutdown`
 * deja el destino y el estado en `__T10A_REDIRECT__`.
 *
 * @param string $path Path de la petición.
 * @return array{location:string|null,status:int,exit:int,continued:bool}
 */
function t10a_redirect($path)
{
    $output = array();
    $exit = 1;
    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' '
        . escapeshellarg('--redirect=' . $path),
        $output,
        $exit
    );

    $marker = null;
    $continued = false;
    foreach ($output as $line) {
        if (0 === strpos($line, '__T10A_REDIRECT__ ')) {
            $marker = substr($line, strlen('__T10A_REDIRECT__ '));
        }
        if ('__T10A_CONTINUED__' === trim($line)) {
            $continued = true;
        }
    }

    $decoded = is_string($marker) ? json_decode($marker, true) : null;
    if (!is_array($decoded)) {
        return array('location' => null, 'status' => 0, 'exit' => $exit, 'continued' => $continued);
    }

    return array(
        'location' => $decoded['location'] ?? null,
        'status'   => (int) ($decoded['status'] ?? 0),
        'exit'     => $exit,
        'continued' => $continued,
    );
}

/*
 * Modo hijo: resuelve una redirección y deja el resultado en el marcador. La
 * fixture se canoniza para que el destino sea la URL canónica.
 */
$t10a_redirect_path = null;
foreach ((array) (isset($argv) ? $argv : array()) as $t10a_argument) {
    if (0 === strpos($t10a_argument, '--redirect=')) {
        $t10a_redirect_path = substr($t10a_argument, strlen('--redirect='));
    }
}

if (null !== $t10a_redirect_path) {
    t10a_fixture();
    t10a_canonicalize();
    $_SERVER['REQUEST_URI'] = $t10a_redirect_path;

    register_shutdown_function(function () {
        echo "\n__T10A_REDIRECT__ " . json_encode(array(
            'location' => isset($GLOBALS['last_redirect']) ? $GLOBALS['last_redirect'] : null,
            'status'   => isset($GLOBALS['last_redirect_status']) ? (int) $GLOBALS['last_redirect_status'] : 0,
        ));
    });

    if (method_exists('\Atareao\SEO', 'redirectLegacyAuthorNicename')) {
        \Atareao\SEO::redirectLegacyAuthorNicename();
    }

    /*
     * Si la redirección no salió por `exit`, el flujo sigue hasta aquí. El
     * padre convierte la presencia de esta marca en un fallo del contrato.
     */
    echo "__T10A_CONTINUED__\n";
    exit(0);
}

echo "=== Autor canónico y nicename heredado (t10) ===\n";

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el paso de datos canónico es idempotente ---\n";
// ---------------------------------------------------------------------------
t10a_fixture();
$t10a_dry = t10a_canonicalize(true);
t10_assert_true(is_array($t10a_dry), 'el paso de datos existe y devuelve un informe');
t10_assert_same(true, $t10a_dry['needed'] ?? null, 'el informe anuncia que el nicename debe cambiar');
t10_assert_same(false, $t10a_dry['changed'] ?? null, '`--dry-run` no escribe');
t10_assert_same(0, $GLOBALS['mock_user_updates'], '`--dry-run` no modifica ningún dato');

t10a_fixture();
$t10a_first = t10a_canonicalize();
t10_assert_same(true, $t10a_first['changed'] ?? null, 'la primera ejecución cambia el nicename');
t10_assert_same('atareao', $GLOBALS['mock_authors'][3]['nicename'], 'el autor 3 queda con nicename atareao');
t10_assert_same(1, $GLOBALS['mock_user_updates'], 'la primera ejecución escribe una sola vez');
t10_assert_same(
    'https://atareao.es/author/atareao/',
    get_author_posts_url(3),
    'la URL canónica del autor 3 es /author/atareao/'
);

$t10a_second = t10a_canonicalize();
t10_assert_same(false, $t10a_second['changed'] ?? null, 'la segunda ejecución no cambia nada');
t10_assert_same(1, $GLOBALS['mock_user_updates'], 'la segunda ejecución no escribe');
t10_assert_same('atareao', $GLOBALS['mock_authors'][3]['nicename'], 'el nicename sigue siendo atareao');

t10a_fixture('yyyxfsgj', true);
$t10a_conflict = t10a_canonicalize();
t10_assert_same(false, $t10a_conflict['ok'] ?? null, 'el paso de datos falla si otro usuario ocupa atareao');
t10_assert_same('yyyxfsgj', $GLOBALS['mock_authors'][3]['nicename'], 'un conflicto no modifica el nicename');
t10_assert_same(0, $GLOBALS['mock_user_updates'], 'un conflicto no escribe');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el subcomando WP-CLI ejecuta el paso de datos ---\n";
// ---------------------------------------------------------------------------
t10a_fixture();
$GLOBALS['mock_cli_commands'] = array();
\Atareao\AuthorCanonical::init();
t10_assert_true(
    isset($GLOBALS['mock_cli_commands']['atareao-author canonicalize']),
    'se registra el subcomando `wp atareao-author canonicalize`'
);

if (isset($GLOBALS['mock_cli_commands']['atareao-author canonicalize'])) {
    $t10a_cli = $GLOBALS['mock_cli_commands']['atareao-author canonicalize'];
    $t10a_cli(array(), array('dry-run' => true));
    t10_assert_same(0, $GLOBALS['mock_user_updates'], 'el subcomando respeta --dry-run');
    $t10a_cli(array(), array());
    t10_assert_same(1, $GLOBALS['mock_user_updates'], 'el subcomando escribe el nicename');
    t10_assert_true(!empty($GLOBALS['mock_cli_output']), 'el subcomando reporta el resultado');
}

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el bootstrap cablea el módulo del autor ---\n";
// ---------------------------------------------------------------------------
t10_assert_true(
    t10a_requires_file(
        "<?php\nrequire_once ATAREAO_PLUGIN_DIR . 'includes/class-author-canonical.php';\n",
        'includes/class-author-canonical.php'
    ),
    'el chequeo del require_once reconoce la sentencia real'
);
t10_assert_same(
    false,
    t10a_requires_file(
        "<?php\n// require_once ATAREAO_PLUGIN_DIR . 'includes/class-author-canonical.php';\n",
        'includes/class-author-canonical.php'
    ),
    'el chequeo del require_once ignora una línea comentada'
);
t10_assert_true(
    t10a_bootstrap_requires_author_canonical(),
    'el bootstrap hace require_once de includes/class-author-canonical.php'
);
t10_assert_true(
    t10a_bootstrap_wires_author_canonical(),
    'atareao_functionality_init() invoca AuthorCanonical::init()'
);

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el nicename heredado redirige con un único 301 ---\n";
// ---------------------------------------------------------------------------
/*
 * La redirección solo existe si `SEO::init()` la engancha: comprobar el método
 * a mano dejaría pasar un módulo que nunca se registra (el falso verde que
 * originó este change).
 */
$GLOBALS['mock_actions'] = array();
\Atareao\SEO::init();
$t10a_hooked = false;
foreach ((array) (isset($GLOBALS['mock_actions']['template_redirect']) ? $GLOBALS['mock_actions']['template_redirect'] : array()) as $t10a_action) {
    $t10a_callback = isset($t10a_action['callback']) ? $t10a_action['callback'] : null;
    if (is_array($t10a_callback)
        && 'Atareao\\SEO' === ($t10a_callback[0] ?? null)
        && 'redirectLegacyAuthorNicename' === ($t10a_callback[1] ?? null)
    ) {
        $t10a_hooked = true;
    }
}
t10_assert_true(
    $t10a_hooked,
    'SEO::init() registra redirectLegacyAuthorNicename en template_redirect'
);

t10a_fixture();
t10a_canonicalize();
$t10a_legacy = t10a_redirect('/author/yyyxfsgj/');
t10_assert_same(0, $t10a_legacy['exit'], 'la petición heredada termina tras redirigir');
t10_assert_same(false, $t10a_legacy['continued'], 'la petición heredada sale por exit y no sigue ejecutando');
t10_assert_same(
    'https://atareao.es/author/atareao/',
    $t10a_legacy['location'],
    'el nicename heredado redirige a la URL canónica'
);
t10_assert_same(301, $t10a_legacy['status'], 'la redirección es un 301');

$t10a_legacy_query = t10a_redirect('/author/yyyxfsgj/?utm_source=feed');
t10_assert_same(
    'https://atareao.es/author/atareao/?utm_source=feed',
    $t10a_legacy_query['location'],
    'la query string se conserva al redirigir'
);
t10_assert_same(301, $t10a_legacy_query['status'], 'con query string sigue siendo un 301 único');
t10_assert_same(false, $t10a_legacy_query['continued'], 'con query string también sale por exit');

/*
 * Las subrutas del archivo (`/page/2/`, `/feed/`) eran URLs válidas antes del
 * cambio y el 301 solo casaba el path exacto: se quedaban sin redirección.
 */
$t10a_legacy_page = t10a_redirect('/author/yyyxfsgj/page/2/');
t10_assert_same(
    'https://atareao.es/author/atareao/page/2/',
    $t10a_legacy_page['location'],
    'la paginación del nicename heredado redirige preservando `/page/2/`'
);
t10_assert_same(301, $t10a_legacy_page['status'], 'la paginación heredada es un único 301');
t10_assert_same(false, $t10a_legacy_page['continued'], 'la paginación heredada sale por exit');

$t10a_legacy_feed = t10a_redirect('/author/yyyxfsgj/feed/');
t10_assert_same(
    'https://atareao.es/author/atareao/feed/',
    $t10a_legacy_feed['location'],
    'el feed del nicename heredado redirige preservando `/feed/`'
);
t10_assert_same(301, $t10a_legacy_feed['status'], 'el feed heredado es un único 301');
t10_assert_same(false, $t10a_legacy_feed['continued'], 'el feed heredado sale por exit');

$t10a_legacy_feed_query = t10a_redirect('/author/yyyxfsgj/feed/?feed=rss2');
t10_assert_same(
    'https://atareao.es/author/atareao/feed/?feed=rss2',
    $t10a_legacy_feed_query['location'],
    'la subruta y la query string se conservan juntas'
);
t10_assert_same(301, $t10a_legacy_feed_query['status'], 'subruta + query sigue siendo un 301 único');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: la redirección no entra en bucle ---\n";
// ---------------------------------------------------------------------------
$t10a_canonical = t10a_redirect('/author/atareao/');
t10_assert_same(null, $t10a_canonical['location'], 'la URL canónica no se redirige a sí misma');
t10_assert_same(0, $t10a_canonical['status'], 'la URL canónica no emite redirección');
t10_assert_same(0, $t10a_canonical['exit'], 'la URL canónica se sirve sin salir por redirección');
t10_assert_same(true, $t10a_canonical['continued'], 'la URL canónica sigue ejecutando y no sale por exit');

$t10a_canonical_page = t10a_redirect('/author/atareao/page/2/');
t10_assert_same(null, $t10a_canonical_page['location'], 'la subruta canónica no se redirige');
t10_assert_same(true, $t10a_canonical_page['continued'], 'la subruta canónica sigue ejecutando');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: la redirección no afecta a otros nicenames ---\n";
// ---------------------------------------------------------------------------
$t10a_other = t10a_redirect('/author/invitado/');
t10_assert_same(null, $t10a_other['location'], 'otro nicename no se redirige');
t10_assert_same(true, $t10a_other['continued'], 'otro nicename sigue ejecutando');

$t10a_normal = t10a_redirect('/una-entrada/');
t10_assert_same(null, $t10a_normal['location'], 'una ruta normal no se redirige');
t10_assert_same(true, $t10a_normal['continued'], 'una ruta normal sigue ejecutando');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: la rewrite de autor no es la solución ---\n";
// ---------------------------------------------------------------------------
t10a_fixture();
if (method_exists('\Atareao\SEO', 'ensureAuthorArchive')) {
    \Atareao\SEO::ensureAuthorArchive();
}
t10_assert_same(
    'https://atareao.es/author/yyyxfsgj/',
    get_author_posts_url(3),
    'registrar la rewrite no cambia el nicename: el archivo canónico sigue siendo el heredado'
);

t10_finish();
