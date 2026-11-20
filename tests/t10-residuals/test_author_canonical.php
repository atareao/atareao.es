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
 * Resuelve la redirección de un path en un proceso hijo.
 *
 * `redirectLegacyAuthorNicename()` termina con `exit` —como el resto de
 * redirecciones del plugin—, así que el escenario se ejecuta aparte: el hijo
 * imprime un marcador en su `shutdown` y el padre lo interpreta.
 *
 * @param string $path Path de la petición.
 * @return array{location:string|null,status:int,exit:int}
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
    foreach ($output as $line) {
        if (0 === strpos($line, '__T10A_REDIRECT__ ')) {
            $marker = substr($line, strlen('__T10A_REDIRECT__ '));
        }
    }

    $decoded = is_string($marker) ? json_decode($marker, true) : null;
    if (!is_array($decoded)) {
        return array('location' => null, 'status' => 0, 'exit' => $exit);
    }

    return array(
        'location' => $decoded['location'] ?? null,
        'status'   => (int) ($decoded['status'] ?? 0),
        'exit'     => $exit,
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
t10_assert_same(
    'https://atareao.es/author/atareao/',
    $t10a_legacy['location'],
    'el nicename heredado redirige a la URL canónica'
);
t10_assert_same(301, $t10a_legacy['status'], 'la redirección es un 301');

$t10a_legacy_query = t10a_redirect('/author/yyyxfsgj/?utm_source=feed');
t10_assert_same(
    'https://atareao.es/author/atareao/',
    $t10a_legacy_query['location'],
    'la redirección también se aplica con query string'
);
t10_assert_same(301, $t10a_legacy_query['status'], 'con query string sigue siendo un 301 único');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: la redirección no entra en bucle ---\n";
// ---------------------------------------------------------------------------
$t10a_canonical = t10a_redirect('/author/atareao/');
t10_assert_same(null, $t10a_canonical['location'], 'la URL canónica no se redirige a sí misma');
t10_assert_same(0, $t10a_canonical['status'], 'la URL canónica no emite redirección');
t10_assert_same(0, $t10a_canonical['exit'], 'la URL canónica se sirve sin salir por redirección');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: la redirección no afecta a otros nicenames ---\n";
// ---------------------------------------------------------------------------
$t10a_other = t10a_redirect('/author/invitado/');
t10_assert_same(null, $t10a_other['location'], 'otro nicename no se redirige');

$t10a_normal = t10a_redirect('/una-entrada/');
t10_assert_same(null, $t10a_normal['location'], 'una ruta normal no se redirige');

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