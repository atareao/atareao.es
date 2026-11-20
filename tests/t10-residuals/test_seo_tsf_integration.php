<?php
/**
 * Arnés del change `2026-11-20-t10-residual-fixes`: integración del plugin con
 * los filtros que The SEO Framework (TSF) 5.2.1 dispara de verdad.
 *
 * Ejercita el delta `seo` (requisito «Integración con The SEO Framework por sus
 * filtros reales»): el título se filtra por `the_seo_framework_title_from_generation`
 * con firma `( $title, ?array $args )`, la descripción por
 * `the_seo_framework_generated_description` con `( $desc, ?array $args, $type )`
 * y el sufijo de marca por `the_seo_framework_use_title_branding` con
 * `( $use, ?array $args, $social )`.
 *
 * La superficie se decide por la **petición actual** (condicionales de
 * WordPress), nunca por `$args`: la ruta auto-determinada de la portada llega
 * con `$args = null`. Fuera de la portada y de los listados cubiertos el
 * callback devuelve el valor recibido sin cambios.
 *
 * También ejecuta la guardia anti-falso-verde: los hooks `the_seo_framework_*`
 * que registra el plugin deben pertenecer todos a la lista verificada de
 * `verified-hooks.php`, que se cumple sin TSF instalado (como en CI).
 *
 * Mismo estilo que `tests/podcast-guid-parity/test_guid_emission.php`. Se
 * ejecuta con `just test-t10` y en el job `lint` de CI.
 *
 * El escenario REST necesita la constante `REST_REQUEST` definida antes de
 * cargar el plugin y no se puede definir y retirar en el mismo proceso, así que
 * el arnés se relanza como proceso hijo (`--child=rest`).
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';
require_once __DIR__ . '/assertions.php';
require_once __DIR__ . '/verified-hooks.php';

define('T10_ROOT', dirname(__DIR__, 2));
define('T10_SEO_FILE', T10_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-seo.php');

$t10_child = null;
foreach ((array) (isset($argv) ? $argv : array()) as $t10_argument) {
    if (0 === strpos($t10_argument, '--child=')) {
        $t10_child = substr($t10_argument, strlen('--child='));
    }
}

if ('rest' === $t10_child) {
    define('REST_REQUEST', true);
}

/**
 * Deja la petición simulada en un contexto neutro (ninguna superficie activa).
 *
 * @return void
 */
function t10_seo_reset_request()
{
    $GLOBALS['mock_front_page'] = false;
    $GLOBALS['mock_home'] = false;
    $GLOBALS['mock_singular'] = false;
    $GLOBALS['mock_post_type_archive'] = false;
    $GLOBALS['mock_is_feed'] = false;
    $GLOBALS['mock_is_admin'] = false;
    $GLOBALS['mock_doing_ajax'] = false;
    $GLOBALS['mock_is_json_request'] = false;
    $GLOBALS['mock_is_robots'] = false;
    $GLOBALS['mock_query_vars'] = array();
    $GLOBALS['mock_tsf_sitemap'] = false;
    $GLOBALS['mock_bloginfo'] = array('name' => 'atareao con Linux');
}

/**
 * Doble de la API pública de TSF (`tsf()`).
 *
 * El plugin detecta el sitemap preguntando a `tsf()->query()->is_sitemap()`,
 * no por una query var: TSF 5.2.1 no registra `the_seo_framework_sitemap`.
 * Este doble reproduce esa API real para que el escenario pueda activarla.
 *
 * @return object
 */
function tsf()
{
    return new T10TsfStub();
}

/**
 * Objeto raíz de TSF (doble).
 */
class T10TsfStub
{
    /**
     * Devuelve el objeto de consulta de TSF.
     *
     * @return T10TsfQueryStub
     */
    public function query()
    {
        return new T10TsfQueryStub();
    }
}

/**
 * Objeto de consulta de TSF (doble).
 */
class T10TsfQueryStub
{
    /**
     * Si la petición actual es un sitemap de TSF.
     *
     * @return bool
     */
    public function is_sitemap()
    {
        return !empty($GLOBALS['mock_tsf_sitemap']);
    }
}

/**
 * Invoca el callback de título, o `null` mientras no exista.
 *
 * @param string     $title Valor recibido de TSF.
 * @param array|null $args  Argumentos de TSF (anulables).
 * @return string|null
 */
function t10_generated_title($title, $args = null)
{
    if (!class_exists('\Atareao\SEO') || !method_exists('\Atareao\SEO', 'filterGeneratedTitle')) {
        return null;
    }

    return \Atareao\SEO::filterGeneratedTitle($title, $args);
}

/**
 * Invoca el callback de descripción, o `null` mientras no exista.
 *
 * @param string     $description Valor recibido de TSF.
 * @param array|null $args        Argumentos de TSF (anulables).
 * @param string     $type        `search`, `opengraph` o `twitter`.
 * @return string|null
 */
function t10_generated_description($description, $args = null, $type = 'search')
{
    if (!class_exists('\Atareao\SEO') || !method_exists('\Atareao\SEO', 'filterGeneratedDescription')) {
        return null;
    }

    return \Atareao\SEO::filterGeneratedDescription($description, $args, $type);
}

/**
 * Invoca el callback del sufijo de marca, o `null` mientras no exista.
 *
 * @param bool       $use    Valor recibido de TSF.
 * @param array|null $args   Argumentos de TSF (anulables).
 * @param bool       $social Si la generación es para redes sociales.
 * @return bool|null
 */
function t10_use_title_branding($use, $args = null, $social = false)
{
    if (!class_exists('\Atareao\SEO') || !method_exists('\Atareao\SEO', 'filterUseTitleBranding')) {
        return null;
    }

    return \Atareao\SEO::filterUseTitleBranding($use, $args, $social);
}

if (!is_file(T10_SEO_FILE)) {
    t10_assert_true(false, 'existe includes/class-seo.php');
    t10_finish();
}

require_once T10_SEO_FILE;

/*
 * Modo hijo: solo el escenario REST, con la constante ya definida.
 */
if ('rest' === $t10_child) {
    t10_seo_reset_request();
    $GLOBALS['mock_front_page'] = true;
    t10_assert_same(
        'Título del framework',
        t10_generated_title('Título del framework', null),
        'REST: el título de la portada no se altera'
    );
    t10_assert_same(
        'Descripción del framework',
        t10_generated_description('Descripción del framework', null, 'search'),
        'REST: la descripción no se altera'
    );
    t10_finish();
}

echo "=== Integración con los filtros reales de TSF (t10) ===\n";

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el plugin registra solo los hooks verificados ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
\Atareao\SEO::init();

$t10_expected_registrations = array(
    'the_seo_framework_title_from_generation' => 2,
    'the_seo_framework_generated_description' => 3,
    'the_seo_framework_use_title_branding'    => 3,
);
foreach ($t10_expected_registrations as $t10_hook => $t10_accepted_args) {
    $t10_callbacks = isset($GLOBALS['mock_filters'][$t10_hook])
        ? $GLOBALS['mock_filters'][$t10_hook]
        : array();
    t10_assert_true(!empty($t10_callbacks), "el plugin registra {$t10_hook}");
    if (!empty($t10_callbacks)) {
        t10_assert_same(
            $t10_accepted_args,
            (int) $t10_callbacks[0]['args'],
            "{$t10_hook} acepta {$t10_accepted_args} argumentos"
        );
        t10_assert_same(
            10,
            (int) $t10_callbacks[0]['priority'],
            "{$t10_hook} se registra a prioridad 10"
        );
    }
}

t10_assert_true(
    !isset($GLOBALS['mock_filters']['the_seo_framework_title']),
    'el plugin no registra el hook muerto the_seo_framework_title'
);
t10_assert_true(
    !isset($GLOBALS['mock_filters']['the_seo_framework_description']),
    'el plugin no registra el hook muerto the_seo_framework_description'
);

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el título de la portada lo reescribe el filtro real ---\n";
// ---------------------------------------------------------------------------
t10_seo_reset_request();
$GLOBALS['mock_front_page'] = true;
$t10_front_title = t10_generated_title('Título del framework', null);
t10_assert_same('atareao con Linux', $t10_front_title, 'la portada sirve el nombre del sitio como título curado');
t10_assert_true(
    is_string($t10_front_title) && mb_strlen($t10_front_title) <= 60,
    'el título de la portada no supera 60 caracteres'
);
t10_assert_true(
    is_string($t10_front_title) && 0 !== mb_substr_count($t10_front_title, 'atareao con Linux'),
    'el título de la portada contiene el nombre del sitio'
);
t10_assert_same(
    1,
    is_string($t10_front_title) ? mb_substr_count($t10_front_title, 'atareao con Linux') : 0,
    'el nombre del sitio aparece exactamente una vez en el título'
);

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el título de un listado lo reescribe el filtro real ---\n";
// ---------------------------------------------------------------------------
$t10_surfaces = array(
    'blog'       => array('title' => 'Blog de Linux, Docker, Rust y self-hosting', 'mode' => array('home' => true)),
    'tutorial'   => array('title' => 'Tutoriales de Linux, Docker y self-hosting', 'mode' => array('archive' => 'tutorial')),
    'podcast'    => array('title' => 'Podcast de Linux, software libre y self-hosting', 'mode' => array('archive' => 'podcast')),
    'aplicacion' => array('title' => 'Aplicaciones para Linux y open source', 'mode' => array('archive' => 'aplicacion')),
    'software'   => array('title' => 'Software para Linux y open source', 'mode' => array('archive' => 'software')),
);
foreach ($t10_surfaces as $t10_surface => $t10_definition) {
    t10_seo_reset_request();
    $GLOBALS['mock_home'] = !empty($t10_definition['mode']['home']);
    $GLOBALS['mock_post_type_archive'] = isset($t10_definition['mode']['archive'])
        ? $t10_definition['mode']['archive']
        : false;

    $t10_title = t10_generated_title('Framework', null);
    t10_assert_same($t10_definition['title'], $t10_title, "el listado {$t10_surface} sirve su título curado");
    t10_assert_true(
        is_string($t10_title) && mb_strlen($t10_title) <= 60,
        "el título del listado {$t10_surface} no supera 60 caracteres"
    );
    t10_assert_true(
        is_string($t10_title) && false === mb_strpos($t10_title, 'atareao con Linux'),
        "el título del listado {$t10_surface} no arrastra el sufijo de marca"
    );
}

// ---------------------------------------------------------------------------
echo "\n--- Scenario: un contenido singular no se altera ---\n";
// ---------------------------------------------------------------------------
$t10_singulars = array(
    'post'    => 'Una entrada - atareao con Linux',
    'tutorial' => 'Un tutorial - atareao con Linux',
    'episodio' => 'Un episodio - atareao con Linux',
);
foreach ($t10_singulars as $t10_name => $t10_value) {
    t10_seo_reset_request();
    $GLOBALS['mock_singular'] = true;
    t10_assert_same($t10_value, t10_generated_title($t10_value, null), "el título del {$t10_name} no se altera");
    t10_assert_same(
        $t10_value,
        t10_generated_description($t10_value, null, 'search'),
        "la descripción del {$t10_name} no se altera"
    );
}

// ---------------------------------------------------------------------------
echo "\n--- Scenario: un contexto ajeno al front-end no se altera ---\n";
// ---------------------------------------------------------------------------
$t10_foreign_contexts = array(
    'admin'      => array('mock_is_admin' => true),
    'AJAX'       => array('mock_doing_ajax' => true),
    'feed'       => array('mock_is_feed' => true),
    'REST (JSON)' => array('mock_is_json_request' => true),
    'robots'     => array('mock_is_robots' => true),
    'sitemap'    => array('mock_tsf_sitemap' => true),
);
foreach ($t10_foreign_contexts as $t10_context => $t10_flags) {
    t10_seo_reset_request();
    /* La portada es la superficie cubierta más probable: si el bail-out falla, aquí se ve. */
    $GLOBALS['mock_front_page'] = true;
    foreach ($t10_flags as $t10_global => $t10_value) {
        $GLOBALS[$t10_global] = $t10_value;
    }

    t10_assert_same(
        'Título del framework',
        t10_generated_title('Título del framework', null),
        "en {$t10_context} el título se devuelve intacto"
    );
    t10_assert_same(
        'Descripción del framework',
        t10_generated_description('Descripción del framework', null, 'search'),
        "en {$t10_context} la descripción se devuelve intacta"
    );
}

/*
 * Control negativo de la guardia: la query var `the_seo_framework_sitemap` no
 * la usa TSF 5.2.1, así que su presencia no puede desactivar las superficies.
 * Si alguien reintroduce la consulta a la query var, esta aserción cae.
 */
t10_seo_reset_request();
$GLOBALS['mock_front_page'] = true;
$GLOBALS['mock_query_vars'] = array('the_seo_framework_sitemap' => '1');
t10_assert_same(
    'atareao con Linux',
    t10_generated_title('Título del framework', null),
    'la query var the_seo_framework_sitemap no desactiva la portada (TSF no la usa)'
);

/*
 * REST: `REST_REQUEST` se define antes de cargar el plugin, así que se prueba
 * en un proceso hijo que vuelve a ejecutar este mismo arnés.
 */
t10_seo_reset_request();
$t10_rest_output = array();
$t10_rest_status = 1;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child=rest', $t10_rest_output, $t10_rest_status);
t10_assert_same(0, $t10_rest_status, 'en REST (REST_REQUEST) el plugin devuelve los valores intactos');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: la ruta auto-determinada no desactiva las superficies cubiertas ---\n";
// ---------------------------------------------------------------------------
t10_seo_reset_request();
$GLOBALS['mock_front_page'] = true;
t10_assert_same(
    'atareao con Linux',
    t10_generated_title('Título del framework', null),
    'con $args = null la portada sigue sirviendo el título curado'
);
t10_assert_same(
    'atareao con Linux: tutoriales, podcast y software libre. Guías de Linux, Docker, Rust, Python y self-hosting para dominar la terminal y automatizar tu día.',
    t10_generated_description('Descripción del framework', null, 'search'),
    'con $args = null la portada sigue sirviendo la descripción curada'
);

t10_seo_reset_request();
$GLOBALS['mock_front_page'] = true;
$t10_warnings = array();
set_error_handler(function ($errno, $errstr) use (&$t10_warnings) {
    $t10_warnings[] = $errstr;

    return true;
});
$t10_malformed_title = t10_generated_title('Framework', array('id' => 5));
$t10_malformed_description = t10_generated_description('Framework', array('id' => 5), 'search');
restore_error_handler();
t10_assert_same('atareao con Linux', $t10_malformed_title, 'sin la clave uid no hay error y se sirve el título curado');
t10_assert_true(
    is_string($t10_malformed_description) && '' !== $t10_malformed_description,
    'sin la clave uid no hay error y se sirve la descripción curada'
);
t10_assert_same(array(), $t10_warnings, 'tratar $args como anulable no emite avisos');

t10_seo_reset_request();
$GLOBALS['mock_singular'] = true;
t10_assert_same(
    'Valor del singular',
    t10_generated_title('Valor del singular', null),
    'con $args = null fuera de las superficies cubiertas devuelve el valor recibido'
);

// ---------------------------------------------------------------------------
echo "\n--- Scenario: la descripción curada se aplica a los tres tipos ---\n";
// ---------------------------------------------------------------------------
/*
 * Fuente de verdad independiente del plugin: las seis descripciones están
 * **hardcodeadas** aquí, como los títulos de `$t10_surfaces`. Derivar el valor
 * esperado de `SEO::surfaceDescription()` haría la aserción tautológica: una
 * mutación que devolviera siempre la descripción de portada seguiría en verde.
 */
$t10_descriptions = array(
    'front'      => 'atareao con Linux: tutoriales, podcast y software libre. Guías de Linux, Docker, Rust, Python y self-hosting para dominar la terminal y automatizar tu día.',
    'blog'       => 'Artículos y tutoriales sobre Linux, Docker, Rust, Python y self-hosting. Aprende a dominar la terminal, desplegar contenedores y automatizar con scripts.',
    'tutorial'   => 'Tutoriales paso a paso de Linux, Docker, Rust, Python y self-hosting. Guías prácticas para dominar la terminal, los contenedores y la automatización.',
    'podcast'    => 'El podcast de atareao con Linux: episodios sobre Linux, software libre, self-hosting y desarrollo. Escucha cada semana las novedades y trucos en audio.',
    'aplicacion' => 'Aplicaciones para Linux, open source y tecnología. Descubre herramientas, alternativas libres y apps para tu día a día explicadas en detalle.',
    'software'   => 'Software para Linux, open source y tecnología. Descubre programas, utilidades y alternativas libres con sus ventajas y cómo instalarlos.',
);

t10_assert_same(6, count($t10_descriptions), 'hay seis descripciones curadas (portada y cinco listados)');
t10_assert_same(
    6,
    count(array_unique($t10_descriptions)),
    'las seis descripciones son distintas entre sí: ninguna superficie repite el texto de otra'
);
foreach ($t10_descriptions as $t10_surface => $t10_expected_description) {
    $t10_description_length = mb_strlen($t10_expected_description);
    t10_assert_true(
        $t10_description_length >= 120 && $t10_description_length <= 160,
        "la descripción esperada de {$t10_surface} cae en 120–160 caracteres ({$t10_description_length})"
    );
}

foreach (array('search', 'opengraph', 'twitter') as $t10_type) {
    t10_seo_reset_request();
    $GLOBALS['mock_front_page'] = true;
    $t10_front_description = t10_generated_description('Descripción del framework', null, $t10_type);
    t10_assert_same(
        $t10_descriptions['front'],
        $t10_front_description,
        "la portada sirve su descripción curada en el tipo {$t10_type}"
    );
    t10_assert_true(
        is_string($t10_front_description)
            && mb_strlen($t10_front_description) >= 120
            && mb_strlen($t10_front_description) <= 160,
        "la descripción de la portada cae en 120–160 caracteres ({$t10_type})"
    );
    foreach ($t10_surfaces as $t10_surface => $t10_definition) {
        t10_assert_true(
            $t10_front_description !== $t10_descriptions[$t10_surface],
            "la portada no sirve la descripción de {$t10_surface} ({$t10_type})"
        );
    }
}
foreach ($t10_surfaces as $t10_surface => $t10_definition) {
    foreach (array('search', 'opengraph', 'twitter') as $t10_type) {
        t10_seo_reset_request();
        $GLOBALS['mock_home'] = !empty($t10_definition['mode']['home']);
        $GLOBALS['mock_post_type_archive'] = isset($t10_definition['mode']['archive'])
            ? $t10_definition['mode']['archive']
            : false;
        $t10_description = t10_generated_description('Framework', null, $t10_type);
        t10_assert_same(
            $t10_descriptions[$t10_surface],
            $t10_description,
            "el listado {$t10_surface} sirve su descripción curada en el tipo {$t10_type}"
        );
        t10_assert_true(
            is_string($t10_description)
                && mb_strlen($t10_description) >= 120
                && mb_strlen($t10_description) <= 160,
            "la descripción del listado {$t10_surface} cae en 120–160 ({$t10_type})"
        );
        /*
         * Control negativo explícito: si el plugin devolviera la descripción de
         * otra superficie (o la de portada), esta aserción cae aunque la
         * longitud siga siendo válida.
         */
        foreach ($t10_descriptions as $t10_other_surface => $t10_other_description) {
            if ($t10_other_surface === $t10_surface) {
                continue;
            }
            t10_assert_true(
                $t10_description !== $t10_other_description,
                "el listado {$t10_surface} no sirve la descripción de {$t10_other_surface} ({$t10_type})"
            );
        }
    }
}
t10_seo_reset_request();
$GLOBALS['mock_home'] = true;
$t10_blog_search = t10_generated_description('Framework', null, 'search');
$t10_blog_og = t10_generated_description('Framework', null, 'opengraph');
$t10_blog_twitter = t10_generated_description('Framework', null, 'twitter');
t10_assert_true(
    is_string($t10_blog_search)
        && $t10_blog_search === $t10_blog_og
        && $t10_blog_og === $t10_blog_twitter,
    'meta, Open Graph y Twitter comparten la misma descripción curada'
);

// ---------------------------------------------------------------------------
echo "\n--- Scenario: el sufijo de marca solo se retira en las superficies cubiertas ---\n";
// ---------------------------------------------------------------------------
t10_seo_reset_request();
$GLOBALS['mock_front_page'] = true;
t10_assert_same(false, t10_use_title_branding(true, null, false), 'la portada no lleva sufijo de marca');
t10_assert_same(false, t10_use_title_branding(true, null, true), 'la portada no lleva sufijo de marca en social');

foreach ($t10_surfaces as $t10_surface => $t10_definition) {
    t10_seo_reset_request();
    $GLOBALS['mock_home'] = !empty($t10_definition['mode']['home']);
    $GLOBALS['mock_post_type_archive'] = isset($t10_definition['mode']['archive'])
        ? $t10_definition['mode']['archive']
        : false;
    t10_assert_same(false, t10_use_title_branding(true, null, false), "el listado {$t10_surface} no lleva sufijo de marca");
}

t10_seo_reset_request();
$GLOBALS['mock_singular'] = true;
t10_assert_same(true, t10_use_title_branding(true, null, false), 'un post conserva su sufijo de marca');
t10_assert_same(false, t10_use_title_branding(false, null, false), 'un post conserva el valor recibido cuando TSF no pone marca');
$GLOBALS['mock_post_type_archive'] = false;
t10_assert_same(true, t10_use_title_branding(true, null, true), 'un episodio conserva su sufijo de marca');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: la guardia de hooks no deja pasar un hook inexistente ---\n";
// ---------------------------------------------------------------------------
$t10_registered = array_keys((array) $GLOBALS['mock_filters']);
$t10_registered_tsf = array_values(array_filter($t10_registered, function ($t10_hook) {
    return 0 === strpos((string) $t10_hook, 'the_seo_framework_');
}));
t10_assert_same(array(), tsf_hook_violations($t10_registered_tsf), 'todos los hooks de TSF registrados están en la lista');

$t10_fake_hooks = $t10_registered_tsf;
$t10_fake_hooks[] = 'the_seo_framework_bogus_hook';
t10_assert_same(
    array('the_seo_framework_bogus_hook'),
    tsf_hook_violations($t10_fake_hooks),
    'la guardia identifica el hook fuera de la lista'
);

$t10_prohibited_hooks = $t10_registered_tsf;
$t10_prohibited_hooks[] = 'the_seo_framework_title';
t10_assert_same(
    array('the_seo_framework_title'),
    tsf_hook_violations($t10_prohibited_hooks),
    'la guardia identifica el hook prohibido'
);

$t10_policy = tsf_hook_policy();
t10_assert_same(5, count($t10_policy['verified']), 'la lista contiene exactamente los cinco hooks verificados');
foreach ($t10_policy['verified'] as $t10_hook => $t10_reference) {
    t10_assert_true(
        1 === preg_match('/\.php:\d+(-\d+)?$/', $t10_reference),
        "la lista documenta fichero:línea para {$t10_hook}"
    );
}
t10_assert_same(
    array('the_seo_framework_title', 'the_seo_framework_description'),
    $t10_policy['prohibited'],
    'la lista marca los dos hooks prohibidos'
);
t10_assert_true(class_exists('\Atareao\SEO'), 'el plugin carga sin TSF instalado y sin error fatal');

// ---------------------------------------------------------------------------
echo "\n--- Scenario: la verificación de la lista contra el TSF instalado ---\n";
// ---------------------------------------------------------------------------
$t10_fixture = sys_get_temp_dir() . '/t10-tsf-' . getmypid();
if (!is_dir($t10_fixture)) {
    mkdir($t10_fixture, 0700, true);
}
$t10_complete = "<?php\n"
    . "apply_filters( 'the_seo_framework_title_from_generation', \$title, \$args );\n"
    . "apply_filters('the_seo_framework_generated_description', \$desc, \$args, \$type);\n"
    . "apply_filters( \"the_seo_framework_use_title_branding\", \$use, \$args, \$social );\n"
    . "apply_filters('the_seo_framework_schema_graph_data', \$graph);\n"
    . "apply_filters('the_seo_framework_sitemap_exclude_ids', \$ids);\n";
file_put_contents($t10_fixture . '/hooks.php', $t10_complete);
$t10_verification = tsf_verify_installed_hooks($t10_fixture);
t10_assert_same(5, $t10_verification['checked'], 'la verificación comprueba los cinco hooks');
t10_assert_same(array(), $t10_verification['missing'], 'la verificación acepta un TSF que dispara los cinco hooks');

file_put_contents(
    $t10_fixture . '/hooks.php',
    str_replace('the_seo_framework_use_title_branding', 'the_seo_framework_branding_removed', $t10_complete)
);
$t10_verification = tsf_verify_installed_hooks($t10_fixture);
t10_assert_same(
    array('the_seo_framework_use_title_branding'),
    $t10_verification['missing'],
    'la verificación reporta el hook que el TSF instalado no dispara'
);
$t10_verification = tsf_verify_installed_hooks($t10_fixture . '/no-existe');
t10_assert_true('' !== $t10_verification['error'], 'la verificación falla si no existe el directorio del plugin');

unlink($t10_fixture . '/hooks.php');
rmdir($t10_fixture);

t10_finish();
