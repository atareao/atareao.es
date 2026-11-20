<?php
/**
 * Arnés procedural de la fase RED del change `podcast-feed-soberania`.
 *
 * Ejercita el contrato del delta `podcast-feed` (feed completo, metadatos de
 * episodio, guid compatible con Anchor, capítulos, ausencia de redirección) y
 * del delta `podcast-transcript-page` (texto de la transcripción indexable en
 * la página del episodio) sobre el módulo `Atareao\PodcastFeed`.
 *
 * Mismo estilo que `tests/podcast-srt-upload/test_srt_upload.php`: contador
 * propio de pases/fallos, `exit(0)` si todo pasa y `exit(1)` si algo falla.
 * Se ejecuta con `just test-feed-soberania`.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('PFS_ROOT', dirname(__DIR__, 2));
define('PFS_FEED', PFS_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php');
define('PFS_TEMPLATE', PFS_ROOT . '/wp-content/themes/atareao-theme/single-podcast.php');

foreach (array(PFS_FEED) as $pfs_bootstrap_file) {
    if (is_file($pfs_bootstrap_file)) {
        require_once $pfs_bootstrap_file;
    }
}

$GLOBALS['pfs_passes']   = 0;
$GLOBALS['pfs_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pfs_pass($description)
{
    $GLOBALS['pfs_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function pfs_fail($description)
{
    $GLOBALS['pfs_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function pfs_assert_true($condition, $description)
{
    if ($condition) {
        pfs_pass($description);

        return;
    }

    pfs_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function pfs_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        pfs_pass($description);

        return;
    }

    pfs_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

/**
 * Carga un documento XML con los prefijos podcast e itunes declarados.
 *
 * @param string $body Cuerpo XML.
 * @return \DOMDocument|null
 */
function pfs_xml($body)
{
    if (!is_string($body) || trim($body) === '') {
        return null;
    }

    libxml_use_internal_errors(true);
    $document = new \DOMDocument();
    $ok = $document->loadXML(
        '<root xmlns:podcast="https://podcastindex.org/namespace/1.0"'
        . ' xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">'
        . $body . '</root>'
    );
    libxml_clear_errors();

    return $ok ? $document : null;
}

/**
 * Primer elemento con prefijo y nombre local dentro de un documento.
 *
 * @param \DOMNode|null $document Documento o elemento.
 * @param string        $prefix   Prefijo (`podcast` o `itunes`).
 * @param string        $local    Nombre local.
 * @return \DOMElement|null
 */
function pfs_first($document, $prefix, $local)
{
    if (!$document instanceof \DOMNode) {
        return null;
    }

    $ns = $prefix === 'podcast'
        ? 'https://podcastindex.org/namespace/1.0'
        : 'http://www.itunes.com/dtds/podcast-1.0.dtd';
    $list = $document->getElementsByTagNameNS($ns, $local);

    return $list->length > 0 ? $list->item(0) : null;
}

/**
 * Texto de un elemento, o '' cuando no existe.
 *
 * @param \DOMElement|null $element Elemento.
 * @return string
 */
function pfs_text($element)
{
    return $element instanceof \DOMElement ? trim((string) $element->textContent) : '';
}

echo "podcast-feed-soberania — contrato del feed y de la transcripción en página\n";

// ---------------------------------------------------------------------------
// Fixture.
// ---------------------------------------------------------------------------
harness_reset_state();

$GLOBALS['mock_is_feed'] = true;
$GLOBALS['mock_post_type_archive'] = 'podcast';

$pfs_srt_tmp = tempnam(sys_get_temp_dir(), 'pfs-srt-');
file_put_contents(
    $pfs_srt_tmp,
    "1\n00:00:00,000 --> 00:00:02,000\nHola y bienvenidos al episodio.\n\n"
    . "2\n00:00:02,000 --> 00:00:04,000\nHoy hablamos del feed soberano.\n"
);
$pfs_chapters_tmp = tempnam(sys_get_temp_dir(), 'pfs-chapters-');
file_put_contents($pfs_chapters_tmp, '{"version":"1.2.0","chapters":[]}');
$pfs_missing_tmp = sys_get_temp_dir() . '/pfs-no-existe-' . uniqid() . '.json';

$GLOBALS['mock_attachments'] = array(
    8801 => array(
        'url'  => 'https://atareao.es/wp-content/uploads/2026/10/episodio.srt',
        'file' => $pfs_srt_tmp,
        'mime' => 'application/x-subrip',
    ),
    8802 => array(
        'url'  => 'https://atareao.es/wp-content/uploads/2026/10/capitulos.json',
        'file' => $pfs_chapters_tmp,
        'mime' => 'application/json',
    ),
    8803 => array(
        'url'  => 'https://atareao.es/wp-content/uploads/2026/10/portada-episodio.jpg',
        'file' => $pfs_chapters_tmp,
        'mime' => 'image/jpeg',
    ),
    8804 => array(
        'url'  => 'https://atareao.es/wp-content/uploads/2026/10/capitulos-inexistentes.json',
        'file' => $pfs_missing_tmp,
    ),
);

$pfs_episode = function ($overrides = array()) {
    $fields = array(
        'ID' => 0,
        'post_type' => 'podcast',
        'post_status' => 'publish',
        'post_password' => '',
        'post_title' => 'Episodio de pruebas',
        'post_date' => '2026-10-20 08:00:00',
        'post_modified' => '2026-10-20 08:00:00',
        'post_author' => 7,
        'post_content' => '',
        'post_excerpt' => '',
    );

    return new \WP_Post(array_merge($fields, $overrides));
};

$GLOBALS['mock_posts'] = array(
    $pfs_episode(array('ID' => 6001, 'post_title' => 'Episodio 849')),
    $pfs_episode(array('ID' => 6002, 'post_title' => 'Episodio sin portada propia')),
    $pfs_episode(array('ID' => 6003, 'post_title' => 'Episodio con capítulos')),
    $pfs_episode(array('ID' => 6004, 'post_title' => 'Episodio con capítulos inexistentes')),
);

$pfs_cover = 'https://atareao.es/wp-content/uploads/2026/10/portada-programa.jpg';

$GLOBALS['mock_post_meta'] = array(
    6001 => array(
        'mp3-url'       => 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2026/1/ata849.mp3',
        'number'        => '849',
        'season'        => '7',
        'duration'      => '00:42:10',
        'transcript-srt' => 8801,
        'anchor-guid'   => '11111111-1111-5111-8111-111111111111',
        'episode-image' => 8803,
        'podcast-chapters' => 8802,
    ),
    6002 => array(
        'mp3-url'  => 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2026/1/ata848.mp3',
        'number'   => '848',
        '_duration' => '00:33:00',
    ),
    6003 => array(
        'mp3-url'       => 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2026/1/ata847.mp3',
        'duration'      => '00:20:00',
        'podcast-chapters' => 8802,
    ),
    6004 => array(
        'mp3-url'       => 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2026/1/ata846.mp3',
        'duration'      => '00:20:00',
        'podcast-chapters' => 8804,
    ),
);

$GLOBALS['mock_options']['atareao_podcast_feed'] = 'https://atareao.es/podcast/feed/';
$GLOBALS['mock_options']['atareao_podcast_cover'] = $pfs_cover;
$GLOBALS['mock_options']['atareao_podcast_author'] = 'atareao';
$GLOBALS['mock_options']['atareao_podcast_owner_name'] = 'Lorenzo Carbonell';
$GLOBALS['mock_options']['atareao_podcast_owner_email'] = 'lorenzo@atareao.es';

// El módulo existe y expone el contrato.
pfs_assert_true(is_file(PFS_FEED), 'existe includes/class-podcast-feed.php');
pfs_assert_true(class_exists('\Atareao\PodcastFeed'), 'la clase \Atareao\PodcastFeed está disponible');

if (class_exists('\Atareao\PodcastFeed')) {
    \Atareao\PodcastFeed::init();
}

// ---------------------------------------------------------------------------
// 1. R1 — el techo de 100 se levanta SOLO en el feed del podcast.
// ---------------------------------------------------------------------------
echo "\n--- R1: feed completo sin tocar el resto de feeds ---\n";

$pfs_query = function () {
    return new class {
        /**
         * Query vars set by the code under test.
         *
         * @var array
         */
        private $data = array();

        /**
         * Set a query var.
         *
         * @param string $key   Var name.
         * @param mixed  $value Var value.
         * @return self
         */
        public function set($key, $value)
        {
            $this->data[$key] = $value;

            return $this;
        }

        /**
         * Read a query var.
         *
         * @param string $key Var name.
         * @return mixed
         */
        public function get($key)
        {
            return array_key_exists($key, $this->data) ? $this->data[$key] : null;
        }
    };
};

if (class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'feedLimit')) {
    $GLOBALS['mock_is_feed'] = true;
    $GLOBALS['mock_post_type_archive'] = 'podcast';
    pfs_assert_true(
        \Atareao\PodcastFeed::feedLimit(100, 'posts_per_rss') > 100,
        'el feed del podcast levanta el techo de 100'
    );

    $GLOBALS['mock_post_type_archive'] = false;
    pfs_assert_equals(
        100,
        \Atareao\PodcastFeed::feedLimit(100, 'posts_per_rss'),
        'el feed principal conserva su límite de 100'
    );

    $GLOBALS['mock_post_type_archive'] = 'podcast';
    $GLOBALS['mock_is_feed'] = false;
    pfs_assert_equals(
        100,
        \Atareao\PodcastFeed::feedLimit(100, 'posts_per_rss'),
        'fuera del feed no se toca el límite'
    );

    $GLOBALS['mock_is_feed'] = true;
    $GLOBALS['mock_post_type_archive'] = 'podcast';
} else {
    pfs_fail('feedLimit() está disponible');
}

if (class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'excludeNonPublic')) {
    $GLOBALS['mock_is_feed'] = true;
    $GLOBALS['mock_post_type_archive'] = 'podcast';
    $pfs_podcast_query = $pfs_query();
    $pfs_podcast_query->set('post_type', 'podcast');
    \Atareao\PodcastFeed::excludeNonPublic($pfs_podcast_query);
    pfs_assert_equals(
        false,
        $pfs_podcast_query->get('has_password'),
        'el feed del podcast excluye los episodios protegidos'
    );
    pfs_assert_equals(
        'publish',
        $pfs_podcast_query->get('post_status'),
        'el feed del podcast solo incluye episodios publicados'
    );

    $pfs_main_query = $pfs_query();
    $pfs_main_query->set('post_type', 'post');
    \Atareao\PodcastFeed::excludeNonPublic($pfs_main_query);
    pfs_assert_equals(
        null,
        $pfs_main_query->get('has_password'),
        'el feed principal no se toca'
    );
} else {
    pfs_fail('excludeNonPublic() está disponible');
}

// ---------------------------------------------------------------------------
// 2. R5 — canal: copyright, tipo episodic, propietario y sin redirección.
// ---------------------------------------------------------------------------
echo "\n--- R5: metadatos del canal ---\n";

$pfs_channel_xml = class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'channelElements')
    ? \Atareao\PodcastFeed::channelElements()
    : '';
$pfs_channel = pfs_xml('<channel>' . $pfs_channel_xml . '</channel>');
$pfs_channel_el = $pfs_channel instanceof \DOMDocument
    ? $pfs_channel->getElementsByTagName('channel')->item(0)
    : null;

$pfs_copyright = $pfs_channel_el instanceof \DOMElement
    ? $pfs_channel_el->getElementsByTagName('copyright')->item(0)
    : null;
pfs_assert_true(
    $pfs_copyright instanceof \DOMElement,
    'el canal declara <copyright>'
);
pfs_assert_true(
    $pfs_copyright instanceof \DOMElement
        && strpos((string) $pfs_copyright->textContent, 'Lorenzo Carbonell') !== false,
    'el copyright lleva el titular del programa'
);
pfs_assert_true(
    $pfs_copyright instanceof \DOMElement
        && strpos((string) $pfs_copyright->textContent, date('Y')) !== false,
    'el copyright lleva el año en curso'
);

pfs_assert_equals(
    'episodic',
    pfs_text(pfs_first($pfs_channel, 'itunes', 'type')),
    'itunes:type vale episodic por defecto'
);
pfs_assert_equals(
    'Lorenzo Carbonell',
    pfs_text(pfs_first(pfs_first($pfs_channel, 'itunes', 'owner'), 'itunes', 'name')),
    'itunes:owner declara el nombre'
);
pfs_assert_equals(
    'lorenzo@atareao.es',
    pfs_text(pfs_first(pfs_first($pfs_channel, 'itunes', 'owner'), 'itunes', 'email')),
    'itunes:owner declara el correo'
);
pfs_assert_equals(
    0,
    $pfs_channel instanceof \DOMDocument
        ? $pfs_channel->getElementsByTagName('itunes:new-feed-url')->length
        : 0,
    'el canal no declara <itunes:new-feed-url>'
);
pfs_assert_true(
    strpos($pfs_channel_xml, 'new-feed-url') === false,
    'el canal no declara ningún new-feed-url'
);

// ---------------------------------------------------------------------------
// 3. R2/R3/R4 — metadatos del episodio (duración, imagen, descripción).
// ---------------------------------------------------------------------------
echo "\n--- R2/R3: metadatos del episodio ---\n";

$pfs_item_6001 = class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'itemElements')
    ? pfs_xml(\Atareao\PodcastFeed::itemElements(6001))
    : null;
pfs_assert_equals(
    '00:42:10',
    pfs_text(pfs_first($pfs_item_6001, 'itunes', 'duration')),
    'el item declara itunes:duration desde el metadato'
);
pfs_assert_equals(
    'https://atareao.es/wp-content/uploads/2026/10/portada-episodio.jpg',
    pfs_first($pfs_item_6001, 'itunes', 'image') instanceof \DOMElement
        ? pfs_first($pfs_item_6001, 'itunes', 'image')->getAttribute('href')
        : '',
    'el item declara itunes:image con la portada propia del episodio'
);

$pfs_item_6002 = class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'itemElements')
    ? pfs_xml(\Atareao\PodcastFeed::itemElements(6002))
    : null;
pfs_assert_equals(
    '00:33:00',
    pfs_text(pfs_first($pfs_item_6002, 'itunes', 'duration')),
    'la duración también se lee del metadato heredado _duration'
);
pfs_assert_equals(
    $pfs_cover,
    pfs_first($pfs_item_6002, 'itunes', 'image') instanceof \DOMElement
        ? pfs_first($pfs_item_6002, 'itunes', 'image')->getAttribute('href')
        : '',
    'sin portada propia, el item usa la portada del programa'
);
pfs_assert_true(
    pfs_first($pfs_item_6002, 'itunes', 'image') instanceof \DOMElement
        && strpos(pfs_first($pfs_item_6002, 'itunes', 'image')->getAttribute('href'), 'https://') === 0,
    'la portada del episodio es absoluta y https'
);

// ---------------------------------------------------------------------------
// 4. R6 — guid compatible con Anchor, no permalink, estable y sin duplicados.
// ---------------------------------------------------------------------------
echo "\n--- R6: guid compatible con el origen ---\n";

if (class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'itemGuid')) {
    pfs_assert_equals(
        '11111111-1111-5111-8111-111111111111',
        \Atareao\PodcastFeed::itemGuid(6001),
        'el guid del episodio mapeado es el UUID de Anchor'
    );
    $pfs_unmapped = \Atareao\PodcastFeed::itemGuid(6002);
    pfs_assert_true(
        $pfs_unmapped !== '' && $pfs_unmapped !== 'https://atareao.es/post/6002',
        'un episodio sin par conserva un guid estable propio, no el permalink'
    );
    pfs_assert_equals(
        $pfs_unmapped,
        \Atareao\PodcastFeed::itemGuid(6002),
        'el guid propio es estable entre composiciones'
    );
    pfs_assert_true(
        \Atareao\PodcastFeed::itemGuid(6001) !== \Atareao\PodcastFeed::itemGuid(6003),
        'dos episodios no comparten guid'
    );
    pfs_assert_equals(
        '11111111-1111-5111-8111-111111111111',
        apply_filters('get_the_guid', 'https://atareao.es/?post_type=podcast&p=6001', 6001),
        'el filtro get_the_guid devuelve el UUID de Anchor en el feed del podcast'
    );
} else {
    pfs_fail('itemGuid() está disponible');
}

// ---------------------------------------------------------------------------
// 5. Capítulos: contrato sí, sin productor.
// ---------------------------------------------------------------------------
echo "\n--- Capítulos del episodio ---\n";

$pfs_item_6003 = class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'itemElements')
    ? pfs_xml(\Atareao\PodcastFeed::itemElements(6003))
    : null;
$pfs_chapters = pfs_first($pfs_item_6003, 'podcast', 'chapters');
pfs_assert_true($pfs_chapters instanceof \DOMElement, 'con capítulos accesibles se declara podcast:chapters');
pfs_assert_equals(
    'https://atareao.es/wp-content/uploads/2026/10/capitulos.json',
    $pfs_chapters instanceof \DOMElement ? $pfs_chapters->getAttribute('url') : '',
    'podcast:chapters declara la URL absoluta del documento'
);
pfs_assert_equals(
    'application/json+chapters',
    $pfs_chapters instanceof \DOMElement ? $pfs_chapters->getAttribute('type') : '',
    'podcast:chapters declara el media type de capítulos'
);

$pfs_item_6002_chapters = pfs_first($pfs_item_6002, 'podcast', 'chapters');
pfs_assert_true(
    !$pfs_item_6002_chapters instanceof \DOMElement,
    'sin capítulos no se declara podcast:chapters'
);

$pfs_item_6004 = class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'itemElements')
    ? pfs_xml(\Atareao\PodcastFeed::itemElements(6004))
    : null;
pfs_assert_true(
    !pfs_first($pfs_item_6004, 'podcast', 'chapters') instanceof \DOMElement,
    'un documento de capítulos inexistente no se declara'
);

// ---------------------------------------------------------------------------
// 6. R4 — descripción con las notas completas.
// ---------------------------------------------------------------------------
echo "\n--- R4: descripción con las notas completas ---\n";

$GLOBALS['mock_posts'][0]->post_content = 'Notas completas del episodio con todo el detalle.';
$GLOBALS['mock_queried_object'] = $GLOBALS['mock_posts'][0];
$pfs_excerpt_in = 'Extracto corto';
$pfs_excerpt_out = class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'excerptFilter')
    ? apply_filters('the_excerpt_rss', $pfs_excerpt_in)
    : $pfs_excerpt_in;
pfs_assert_true(
    strpos((string) $pfs_excerpt_out, 'Notas completas del episodio') !== false,
    'la descripción del feed lleva las notas completas, no el extracto'
);
$GLOBALS['mock_queried_object'] = null;

// ---------------------------------------------------------------------------
// 7. podcast-transcript-page — texto indexable en la página del episodio.
// ---------------------------------------------------------------------------
echo "\n--- podcast-transcript-page: texto indexable ---\n";

if (class_exists('\Atareao\PodcastFeed') && method_exists('\Atareao\PodcastFeed', 'transcriptText')) {
    $pfs_text = \Atareao\PodcastFeed::transcriptText(8801);
    pfs_assert_true(
        strpos($pfs_text, 'Hola y bienvenidos al episodio.') !== false,
        'transcriptText devuelve el texto hablado del SRT'
    );
    pfs_assert_true(
        strpos($pfs_text, '-->') === false && strpos($pfs_text, '00:00:00,000') === false,
        'transcriptText descarta las marcas de tiempo'
    );
    pfs_assert_equals('', \Atareao\PodcastFeed::transcriptText(8804), 'un SRT inaccesible no produce texto');

    $pfs_html = \Atareao\PodcastFeed::transcriptHtml(8801);
    pfs_assert_true(
        strpos($pfs_html, 'Hola y bienvenidos al episodio.') !== false,
        'transcriptHtml sirve el texto dentro del HTML'
    );
    pfs_assert_true(
        strpos($pfs_html, '<section') !== false,
        'transcriptHtml envuelve el texto en una sección'
    );
    pfs_assert_equals('', \Atareao\PodcastFeed::transcriptHtml(8804), 'sin transcripción accesible no hay sección');
} else {
    pfs_fail('transcriptText() está disponible');
}

pfs_assert_true(
    is_file(PFS_TEMPLATE) && strpos((string) file_get_contents(PFS_TEMPLATE), 'transcriptHtml') !== false,
    'single-podcast.php muestra la transcripción con transcriptHtml()'
);

echo "\nResumen: {$GLOBALS['pfs_passes']} PASS, " . count($GLOBALS['pfs_failures']) . " FAIL\n";

if (!empty($GLOBALS['pfs_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['pfs_failures'] as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

exit(0);
