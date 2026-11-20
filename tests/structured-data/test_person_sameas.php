<?php
/**
 * Arnés del change `2026-11-20-author-sameas`: los perfiles externos del sitio
 * (`sameAs`) se publican en **toda** entidad `Person` que el grafo describa
 * —de primer nivel o anidada, por ejemplo como `publisher` del `WebSite`—, sin
 * tocar las referencias puras ni alterar `@type`/`@id`.
 *
 * Ejercita el contrato del delta `structured-data` (requisito «Perfiles externos
 * en la entidad `Person` del grafo»). Los valores esperados se escriben
 * literales; nunca se calculan con la función bajo prueba. Se ejecuta con
 * `just test-t10` y en el job `lint` de CI (PHP puro sobre `tests/wp-stubs.php`,
 * sin WordPress ni contenedor).
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('AS_ROOT', dirname(__DIR__, 2));
define('AS_SEO_FILE', AS_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-seo.php');

$GLOBALS['as_passes']   = 0;
$GLOBALS['as_failures'] = array();

/**
 * Registra el resultado de una aserción.
 *
 * @param bool   $condition   Resultado de la comprobación.
 * @param string $description Descripción legible.
 * @return void
 */
function as_assert($condition, $description)
{
    if ($condition) {
        $GLOBALS['as_passes']++;
        return;
    }

    $GLOBALS['as_failures'][] = $description;
}

/**
 * Aplica el filtro real del grafo de datos estructurados al grafo dado.
 *
 * @param array $graph Grafo de entidades.
 * @return mixed
 */
function as_apply($graph)
{
    return apply_filters('the_seo_framework_schema_graph_data', $graph, null);
}

/**
 * Devuelve el `sameAs` de una entidad, siempre como lista.
 *
 * @param mixed $entity Entidad del grafo.
 * @return array
 */
function as_same_as($entity)
{
    if (!is_array($entity) || !isset($entity['sameAs'])) {
        return array();
    }

    $value = $entity['sameAs'];

    return is_array($value) ? $value : array($value);
}

/**
 * Cuenta las entidades del grafo.
 *
 * @param mixed $graph Grafo.
 * @return int
 */
function as_count($graph)
{
    return is_array($graph) ? count($graph) : -1;
}

if (!is_file(AS_SEO_FILE)) {
    echo "  ✗ existe includes/class-seo.php\n";
    echo "\nResumen: 0 PASS, 1 FAIL\n";
    exit(1);
}

require_once AS_SEO_FILE;
\Atareao\SEO::init();

// Contexto neutro: ni portada, ni home, ni singular, ni archivo de podcast.
$GLOBALS['mock_front_page']         = false;
$GLOBALS['mock_home']               = false;
$GLOBALS['mock_singular']           = false;
$GLOBALS['mock_post_type_archive']  = false;

$as_github  = 'https://github.com/atareao';
$as_youtube = 'https://www.youtube.com/channel/UCP8dTCaLNJFxfklb_GNqe6A';

$as_profiles = array(
    'atareao_social_github'  => 'https://github.com/atareao',
    'atareao_social_youtube' => 'https://www.youtube.com/c/atareao',
);

echo "\n=== author-sameas: perfiles externos en la entidad Person ===\n";

// ---------------------------------------------------------------------------
// Escenario: la página de autor declara los perfiles del autor
// ---------------------------------------------------------------------------
$GLOBALS['mock_options'] = $as_profiles;
$as_author_graph = as_apply(array(
    array(
        '@type'     => 'WebSite',
        '@id'       => 'https://atareao.es/#/schema/WebSite',
        'publisher' => array(
            '@type' => 'Person',
            '@id'   => 'https://atareao.es/#/schema/Person',
            'name'  => 'Lorenzo Carbonell',
            'url'   => 'https://atareao.es/author/atareao/',
        ),
    ),
    array('@type' => 'CollectionPage', '@id' => 'https://atareao.es/author/atareao/'),
));
$as_author_person  = isset($as_author_graph[0]['publisher']) ? $as_author_graph[0]['publisher'] : null;
$as_author_same_as = as_same_as($as_author_person);
as_assert(
    in_array($as_github, $as_author_same_as, true) && in_array($as_youtube, $as_author_same_as, true),
    'la Person anidada en WebSite.publisher declara sameAs con GitHub y el canal de YouTube normalizado'
);

// ---------------------------------------------------------------------------
// Escenario: una Person anidada se enriquece sin perder sus datos
// ---------------------------------------------------------------------------
as_assert(
    'Lorenzo Carbonell' === (isset($as_author_person['name']) ? $as_author_person['name'] : null),
    'la Person anidada conserva su name al enriquecerse'
);
as_assert(
    'https://atareao.es/#/schema/Person' === (isset($as_author_person['@id']) ? $as_author_person['@id'] : null),
    'la Person anidada conserva su @id al enriquecerse'
);

// ---------------------------------------------------------------------------
// Escenario: una Person de primer nivel sigue recibiéndolo
// ---------------------------------------------------------------------------
$as_top_graph = as_apply(array(
    array(
        '@type'  => 'Person',
        '@id'    => 'https://atareao.es/#/schema/Person',
        'name'   => 'Lorenzo Carbonell',
        'sameAs' => 'https://x.com/atareao',
    ),
));
$as_top_same_as = as_same_as(isset($as_top_graph[0]) ? $as_top_graph[0] : null);
as_assert(
    in_array('https://x.com/atareao', $as_top_same_as, true)
        && in_array($as_github, $as_top_same_as, true)
        && in_array($as_youtube, $as_top_same_as, true),
    'la Person de primer nivel conserva sus perfiles y recibe los del sitio'
);

// ---------------------------------------------------------------------------
// Escenario: una referencia pura no se modifica
// ---------------------------------------------------------------------------
$as_ref_graph = as_apply(array(
    array(
        '@type'     => 'WebSite',
        'publisher' => array('@id' => 'https://atareao.es/#/schema/Person'),
    ),
    array(
        '@type'  => 'WebPage',
        'author' => array('@id' => 'https://atareao.es/#/schema/Person'),
    ),
));
as_assert(
    array('@id' => 'https://atareao.es/#/schema/Person') === $as_ref_graph[0]['publisher'],
    'la referencia pura del publisher permanece intacta (sin sameAs)'
);
as_assert(
    array('@id' => 'https://atareao.es/#/schema/Person') === $as_ref_graph[1]['author'],
    'la referencia pura del author permanece intacta (sin sameAs)'
);

// ---------------------------------------------------------------------------
// Escenario: no se altera el tipo ni el identificador ni el número de entidades
// ---------------------------------------------------------------------------
as_assert(2 === as_count($as_ref_graph), 'el número de entidades no cambia');
as_assert(
    'WebSite' === (isset($as_ref_graph[0]['@type']) ? $as_ref_graph[0]['@type'] : null)
        && 'CollectionPage' === (isset($as_author_graph[1]['@type']) ? $as_author_graph[1]['@type'] : null),
    'los @type de las entidades no cambian'
);

// ---------------------------------------------------------------------------
// Escenario: los perfiles existentes no se duplican
// ---------------------------------------------------------------------------
$as_dup_graph = as_apply(array(
    array(
        '@type'  => 'Person',
        '@id'    => 'https://atareao.es/#/schema/Person',
        'name'   => 'Lorenzo Carbonell',
        'sameAs' => $as_github,
    ),
));
$as_dup_same_as = as_same_as(isset($as_dup_graph[0]) ? $as_dup_graph[0] : null);
as_assert(
    1 === count(array_keys($as_dup_same_as, $as_github, true)),
    'un perfil ya presente no se duplica'
);

// ---------------------------------------------------------------------------
// Escenario: sin perfiles configurados no se añade nada
// ---------------------------------------------------------------------------
$GLOBALS['mock_options'] = array();
$as_none_graph = as_apply(array(
    array(
        '@type'     => 'WebSite',
        'publisher' => array(
            '@type' => 'Person',
            '@id'   => 'https://atareao.es/#/schema/Person',
            'name'  => 'Lorenzo Carbonell',
            'url'   => 'https://atareao.es/author/atareao/',
        ),
    ),
));
as_assert(
    !isset($as_none_graph[0]['publisher']['sameAs']),
    'sin opciones atareao_social_* no se añade sameAs'
);

// ---------------------------------------------------------------------------
echo "\nResumen: {$GLOBALS['as_passes']} PASS, " . count($GLOBALS['as_failures']) . " FAIL\n";

if (!empty($GLOBALS['as_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['as_failures'] as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

exit(0);
