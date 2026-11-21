<?php
/**
 * Arnés del change `youtube-video-fallback`: resolución del vídeo propio de un
 * episodio con **respaldo en el metadato** `youtube_url`.
 *
 * Ejercita el delta `seo` (requisito «Resolución del vídeo del episodio con
 * respaldo en metadato»): registro del metadato, precedencia contenido →
 * metadato en `\Atareao\SEO::embeddedVideo()`, sin regresión del embed en el
 * contenido (YouTube y Vimeo), fachada `.episode-video` al final del
 * episodio (solo con vídeo de metadato) y entidad `VideoObject` del grafo
 * alimentados por la MISMA resolución, `null` sin vídeo y post inexistente sin
 * error fatal.
 *
 * Los valores esperados se escriben literales: nunca se calculan con la función
 * bajo prueba. Se ejecuta con `just test-youtube-video` (PHP puro sobre
 * `tests/wp-stubs.php`, sin WordPress ni contenedor) y en el job `lint` de CI.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('YTV_ROOT', dirname(__DIR__, 2));
define('YTV_INCLUDES', YTV_ROOT . '/wp-content/plugins/atareao-functionality/includes');
define('YTV_SEO', YTV_INCLUDES . '/class-seo.php');
define('YTV_FEED', YTV_INCLUDES . '/class-podcast-feed.php');
define('YTV_METABOXES', YTV_INCLUDES . '/class-metaboxes.php');

foreach (array(YTV_FEED, YTV_SEO, YTV_METABOXES) as $ytv_bootstrap_file) {
    if (is_file($ytv_bootstrap_file)) {
        require_once $ytv_bootstrap_file;
    }
}

$GLOBALS['ytv_passes']   = 0;
$GLOBALS['ytv_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function ytv_pass($description)
{
    $GLOBALS['ytv_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function ytv_fail($description)
{
    $GLOBALS['ytv_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function ytv_assert_true($condition, $description)
{
    if ($condition) {
        ytv_pass($description);

        return;
    }

    ytv_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function ytv_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        ytv_pass($description);

        return;
    }

    ytv_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

/**
 * Aserción de nulidad.
 *
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function ytv_assert_null($actual, $description)
{
    ytv_assert_true(null === $actual, $description);
}

/**
 * Crea un episodio en el archivo en memoria con su contenido y metadatos.
 *
 * @param int    $id      Identificador.
 * @param string $content Contenido.
 * @param array  $meta    Metadatos.
 * @param string $excerpt Extracto.
 * @return \WP_Post
 */
function ytv_episode($id, $content, $meta, $excerpt = '')
{
    $post = new \WP_Post(array(
        'ID'            => $id,
        'post_type'     => 'podcast',
        'post_status'   => 'publish',
        'post_title'    => 'Episodio ' . $id,
        'post_content'  => $content,
        'post_excerpt'  => $excerpt,
        'post_date'     => '2026-11-02 10:00:00',
        'post_modified' => '2026-11-02 10:00:00',
    ));
    $GLOBALS['mock_posts'][]         = $post;
    $GLOBALS['mock_post_meta'][$id]  = is_array($meta) ? $meta : array();

    return $post;
}

/**
 * Reinicia el estado del arnés y vuelve a registrar los hooks del plugin.
 *
 * `harness_reset_state()` vacía `$GLOBALS['mock_filters']` (salvo los de REST),
 * así que tras cada reinicio hay que reconstruir los filtros de `SEO::init()`
 * antes de disparar `the_seo_framework_schema_graph_data`.
 *
 * @return void
 */
function ytv_boot()
{
    harness_reset_state();
    \Atareao\SEO::init();
}

/**
 * Localiza la entidad `VideoObject` del grafo.
 *
 * @param mixed $graph Grafo de entidades.
 * @return array|null
 */
function ytv_find_video($graph)
{
    foreach ((array) $graph as $entity) {
        if (is_array($entity) && isset($entity['@type']) && 'VideoObject' === $entity['@type']) {
            return $entity;
        }
    }

    return null;
}

/**
 * Ejecuta el filtro de la fachada sin provocar un error fatal cuando el método
 * aún no existe (fase RED): devuelve el contenido intacto en ese caso.
 *
 * @param string $content Contenido de entrada.
 * @return string Contenido tras el filtro.
 */
function ytv_player_content($content)
{
    if (!method_exists('\Atareao\SEO', 'appendEpisodeVideoPlayer')) {
        return $content;
    }

    return \Atareao\SEO::appendEpisodeVideoPlayer($content);
}

if (!is_file(YTV_SEO)) {
    echo "  ✗ existe includes/class-seo.php\n";
    echo "\nResumen: 0 PASS, 1 FAIL\n";
    exit(1);
}

\Atareao\SEO::init();

echo "\n=== youtube-video-fallback — resolución, metadato, enlace y VideoObject ===\n";

// ---------------------------------------------------------------------------
echo "\n--- Metadato youtube_url (registro) ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
\Atareao\Metaboxes::registerMetaFields();

$ytv_meta = $GLOBALS['mock_registered_meta']['podcast']['youtube_url'] ?? null;
ytv_assert_true(is_array($ytv_meta), 'youtube_url está registrado sobre el CPT podcast');

if (is_array($ytv_meta)) {
    ytv_assert_true(!empty($ytv_meta['single']), 'youtube_url es un valor único (single=true)');
    ytv_assert_equals('string', $ytv_meta['type'] ?? null, 'youtube_url es de tipo string');
    ytv_assert_true(
        !isset($ytv_meta['show_in_rest']) || false === $ytv_meta['show_in_rest'],
        'youtube_url NO se expone en REST (show_in_rest desactivado)'
    );

    ytv_assert_true(
        isset($ytv_meta['sanitize_callback']) && is_callable($ytv_meta['sanitize_callback']),
        'youtube_url declara un sanitize_callback'
    );

    if (isset($ytv_meta['sanitize_callback']) && is_callable($ytv_meta['sanitize_callback'])) {
        ytv_assert_equals(
            'https://www.youtube.com/watch?v=1Q5rGnwRBTY',
            call_user_func($ytv_meta['sanitize_callback'], 'https://www.youtube.com/watch?v=1Q5rGnwRBTY'),
            'el saneador conserva una URL http(s) de YouTube'
        );
        ytv_assert_equals(
            '',
            call_user_func($ytv_meta['sanitize_callback'], 'javascript:alert(1)'),
            'el saneador rechaza un esquema peligroso'
        );
        ytv_assert_equals(
            '',
            call_user_func($ytv_meta['sanitize_callback'], 'ftp://example.com/video'),
            'el saneador rechaza un esquema que no es http(s)'
        );
    }
}

ytv_assert_true(
    method_exists('\Atareao\SEO', 'sanitizeYoutubeUrl'),
    'SEO expone el saneado compartido sanitizeYoutubeUrl()'
);
if (method_exists('\Atareao\SEO', 'sanitizeYoutubeUrl')) {
    ytv_assert_equals(
        'https://www.youtube.com/watch?v=1Q5rGnwRBTY',
        \Atareao\SEO::sanitizeYoutubeUrl('https://www.youtube.com/watch?v=1Q5rGnwRBTY'),
        'sanitizeYoutubeUrl() conserva una URL http(s)'
    );
    ytv_assert_equals(
        '',
        \Atareao\SEO::sanitizeYoutubeUrl('ftp://example.com/video'),
        'sanitizeYoutubeUrl() rechaza un esquema que no es http(s)'
    );
    ytv_assert_equals(
        '',
        \Atareao\SEO::sanitizeYoutubeUrl('javascript:alert(1)'),
        'sanitizeYoutubeUrl() rechaza un esquema peligroso'
    );
}

// ---------------------------------------------------------------------------
echo "\n--- Resolución: el contenido gana al metadato (sin regresión) ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
$ytv_post = ytv_episode(
    7101,
    '<p>Mira:</p><iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe>',
    array('youtube_url' => 'https://www.youtube.com/watch?v=zzzzzzzzzzz')
);
$ytv_video = \Atareao\SEO::embeddedVideo($ytv_post);
ytv_assert_true(is_array($ytv_video), 'un episodio con embed devuelve vídeo');
ytv_assert_equals(
    'https://www.youtube.com/watch?v=abcdefghijk',
    $ytv_video['url'] ?? null,
    'la URL canónica sale del id embebido en el contenido'
);
ytv_assert_equals(
    'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg',
    $ytv_video['thumbnail'] ?? null,
    'la miniatura se deriva del id embebido'
);

harness_reset_state();
$ytv_post = ytv_episode(7102, 'Mira https://www.youtube.com/watch?v=gxC35wcbW_s aquí.', array());
$ytv_video = \Atareao\SEO::embeddedVideo($ytv_post);
ytv_assert_equals(
    'https://www.youtube.com/watch?v=gxC35wcbW_s',
    $ytv_video['url'] ?? null,
    'reconoce una URL youtube.com/watch?v= en el contenido'
);

harness_reset_state();
$ytv_post = ytv_episode(7103, 'Mira https://youtu.be/1Q5rGnwRBTY', array());
$ytv_video = \Atareao\SEO::embeddedVideo($ytv_post);
ytv_assert_equals(
    'https://www.youtube.com/watch?v=1Q5rGnwRBTY',
    $ytv_video['url'] ?? null,
    'reconoce una URL youtu.be/ en el contenido'
);

harness_reset_state();
$ytv_post = ytv_episode(7104, '<iframe src="https://player.vimeo.com/video/123456789"></iframe>', array());
$ytv_video = \Atareao\SEO::embeddedVideo($ytv_post);
ytv_assert_equals('https://vimeo.com/123456789', $ytv_video['url'] ?? null, 'un embed de Vimeo se conserva');
ytv_assert_equals('', $ytv_video['thumbnail'] ?? null, 'un vídeo de Vimeo sin miniatura conocida devuelve miniatura vacía');

harness_reset_state();
$ytv_post = ytv_episode(7105, '<iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe>', array('youtube_url' => 'https://vimeo.com/123'));
$ytv_video = \Atareao\SEO::embeddedVideo($ytv_post);
ytv_assert_equals(
    'https://www.youtube.com/watch?v=abcdefghijk',
    $ytv_video['url'] ?? null,
    'con embed en el contenido, un metadato que no es YouTube no altera el resultado'
);

ytv_assert_equals(
    'https://www.youtube.com/watch?v=abcdefghijk',
    \Atareao\SEO::embeddedVideoUrl('<iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe>'),
    'embeddedVideoUrl sigue resolviendo solo el contenido'
);

// ---------------------------------------------------------------------------
echo "\n--- Resolución: respaldo en el metadato ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
$ytv_post = ytv_episode(7110, 'Contenido sin ningún vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=gxC35wcbW_s',
));
$ytv_video = \Atareao\SEO::embeddedVideo($ytv_post);
ytv_assert_equals('https://www.youtube.com/watch?v=gxC35wcbW_s', $ytv_video['url'] ?? null, 'sin embed, la URL sale del metadato');
ytv_assert_equals(
    'https://i.ytimg.com/vi/gxC35wcbW_s/hqdefault.jpg',
    $ytv_video['thumbnail'] ?? null,
    'la miniatura se deriva del id del metadato'
);

harness_reset_state();
$ytv_post = ytv_episode(7111, 'Contenido sin vídeo.', array('youtube_url' => 'https://youtu.be/1Q5rGnwRBTY'));
$ytv_video = \Atareao\SEO::embeddedVideo($ytv_post);
ytv_assert_equals(
    'https://www.youtube.com/watch?v=1Q5rGnwRBTY',
    $ytv_video['url'] ?? null,
    'un youtu.be suelto en el metadato se acepta y se canonicaliza'
);

harness_reset_state();
$ytv_post = ytv_episode(7112, 'Contenido sin vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=BEIzwyPianA&t=30s',
));
$ytv_video = \Atareao\SEO::embeddedVideo($ytv_post);
ytv_assert_equals(
    'https://www.youtube.com/watch?v=BEIzwyPianA',
    $ytv_video['url'] ?? null,
    'un watch?v= con parámetros extra se reduce al identificador'
);

// ---------------------------------------------------------------------------
echo "\n--- Resolución: sin vídeo ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
$ytv_post = ytv_episode(7120, 'Contenido sin vídeo.', array());
ytv_assert_null(\Atareao\SEO::embeddedVideo($ytv_post), 'sin embed ni metadato la resolución es null');

harness_reset_state();
$ytv_post = ytv_episode(7121, 'Contenido sin vídeo.', array('youtube_url' => ''));
ytv_assert_null(\Atareao\SEO::embeddedVideo($ytv_post), 'un metadato vacío no aporta vídeo');

harness_reset_state();
$ytv_post = ytv_episode(7122, 'Contenido sin vídeo.', array('youtube_url' => 'https://vimeo.com/123456789'));
ytv_assert_null(\Atareao\SEO::embeddedVideo($ytv_post), 'un metadato que no es de YouTube se ignora');

harness_reset_state();
$ytv_post = ytv_episode(7123, 'Contenido sin vídeo.', array('youtube_url' => 'https://example.com/video'));
ytv_assert_null(\Atareao\SEO::embeddedVideo($ytv_post), 'una URL no-YouTube se ignora y no hay respaldo');

harness_reset_state();
$GLOBALS['mock_queried_object'] = null;
ytv_assert_null(\Atareao\SEO::embeddedVideo(424242), 'un post inexistente devuelve null sin error fatal');

// ---------------------------------------------------------------------------
echo "\n--- Fachada de final de episodio (.episode-video) ---\n";
// ---------------------------------------------------------------------------
ytv_assert_true(
    method_exists('\Atareao\SEO', 'appendEpisodeVideoPlayer'),
    'SEO expone appendEpisodeVideoPlayer()'
);

harness_reset_state();
$ytv_post = ytv_episode(7130, 'Contenido sin vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
));
$GLOBALS['mock_queried_object'] = $ytv_post;
$GLOBALS['mock_singular']       = true;
$ytv_content = ytv_player_content('ORIGEN');
ytv_assert_true(0 === strpos($ytv_content, 'ORIGEN'), 'la fachada se añade al final del contenido');
ytv_assert_true(
    false !== strpos($ytv_content, 'episode-video'),
    'con respaldo en metadato el contenido incluye la fachada .episode-video'
);
ytv_assert_true(
    false !== strpos($ytv_content, 'data-video-id="abcdefghijk"'),
    'la fachada lleva el identificador del metadato'
);
ytv_assert_true(
    false !== strpos($ytv_content, 'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg'),
    'la fachada usa la miniatura del metadato'
);
ytv_assert_true(
    false !== strpos($ytv_content, 'href="https://www.youtube.com/watch?v=abcdefghijk"'),
    'la fachada enlaza al vídeo del metadato'
);

harness_reset_state();
$ytv_post = ytv_episode(7131, 'Contenido sin vídeo.', array());
$GLOBALS['mock_queried_object'] = $ytv_post;
$GLOBALS['mock_singular']       = true;
ytv_assert_equals('ORIGEN', ytv_player_content('ORIGEN'), 'sin vídeo no se añade fachada');

harness_reset_state();
$ytv_post = ytv_episode(7132, '<iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe>', array());
$GLOBALS['mock_queried_object'] = $ytv_post;
$GLOBALS['mock_singular']       = true;
ytv_assert_equals(
    'ORIGEN',
    ytv_player_content('ORIGEN'),
    'con el vídeo en el cuerpo (source=content) NO se añade fachada'
);

// ---------------------------------------------------------------------------
echo "\n--- VideoObject del grafo ---\n";
// ---------------------------------------------------------------------------
ytv_boot();
$ytv_post = ytv_episode(
    7140,
    'Contenido sin vídeo.',
    array('youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk'),
    'Analizamos el peligro de las apuestas en este episodio del podcast.'
);
$GLOBALS['mock_queried_object'] = $ytv_post;
$GLOBALS['mock_singular']       = true;
$ytv_graph  = apply_filters('the_seo_framework_schema_graph_data', array(), null);
$ytv_object = ytv_find_video($ytv_graph);

ytv_assert_true(is_array($ytv_object), 'con respaldo en metadato el grafo declara VideoObject');
if (is_array($ytv_object)) {
    ytv_assert_equals('Episodio 7140', $ytv_object['name'] ?? null, 'el VideoObject lleva el nombre del episodio');
    ytv_assert_equals(
        'https://www.youtube.com/watch?v=abcdefghijk',
        $ytv_object['url'] ?? null,
        'el VideoObject lleva la URL del metadato'
    );
    ytv_assert_equals(
        'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg',
        $ytv_object['thumbnailUrl'] ?? null,
        'el VideoObject lleva la miniatura del metadato'
    );
    ytv_assert_equals(
        (string) get_the_date('c', $ytv_post),
        $ytv_object['uploadDate'] ?? null,
        'el VideoObject lleva la fecha de subida'
    );
    ytv_assert_true(
        isset($ytv_object['description'])
            && false !== strpos((string) $ytv_object['description'], 'peligro de las apuestas'),
        'el VideoObject lleva la descripción del episodio'
    );
}

ytv_boot();
$ytv_post = ytv_episode(7141, 'Contenido sin vídeo.', array());
$GLOBALS['mock_queried_object'] = $ytv_post;
$GLOBALS['mock_singular']       = true;
$ytv_graph = apply_filters('the_seo_framework_schema_graph_data', array(), null);
ytv_assert_null(ytv_find_video($ytv_graph), 'sin vídeo el grafo no declara VideoObject');

ytv_boot();
$ytv_post = ytv_episode(7142, '<iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe>', array());
$GLOBALS['mock_queried_object'] = $ytv_post;
$GLOBALS['mock_singular']       = true;
$ytv_graph  = apply_filters('the_seo_framework_schema_graph_data', array(), null);
$ytv_object = ytv_find_video($ytv_graph);
ytv_assert_equals(
    'https://www.youtube.com/watch?v=abcdefghijk',
    $ytv_object['url'] ?? null,
    'un embed en el contenido sigue declarando VideoObject (sin regresión)'
);

// ---------------------------------------------------------------------------
echo "\nResumen: {$GLOBALS['ytv_passes']} PASS, " . count($GLOBALS['ytv_failures']) . " FAIL\n";

if (!empty($GLOBALS['ytv_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['ytv_failures'] as $ytv_failure) {
        echo "  - {$ytv_failure}\n";
    }

    exit(1);
}

exit(0);
