<?php
/**
 * Arnés del change `episode-video-player`: reproductor del vídeo del episodio
 * incrustado (fachada con mejora progresiva + `youtube-nocookie`).
 *
 * Ejercita el delta `seo`: el retorno ampliado de `\Atareao\SEO::embeddedVideo()`
 * (`id` y `source`), la fachada `.episode-video` al final del contenido —solo
 * con `source === 'meta'`—, el no-duplicado cuando el cuerpo ya incrusta el
 * vídeo, el encolado condicional de los assets `episode-video.css`/`.js`, la
 * CSP de producción (`nginx/pro/wp.conf`) y la no-regresión de `VideoObject`.
 *
 * Los valores esperados se escriben literales: nunca se calculan con la función
 * bajo prueba. Se ejecuta con `just test-episode-video` (PHP puro sobre
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

define('EVP_ROOT', dirname(__DIR__, 2));
define('EVP_INCLUDES', EVP_ROOT . '/wp-content/plugins/atareao-functionality/includes');
define('EVP_SEO', EVP_INCLUDES . '/class-seo.php');
define('EVP_NGINX', EVP_ROOT . '/nginx/pro/wp.conf');
define('EVP_PLUGIN_URL', 'https://atareao.es/wp-content/plugins/atareao-functionality/');
define('EVP_PLUGIN_VERSION', '1.28.0');
define('EVP_STYLE_HANDLE', 'atareao-episode-video');
define('EVP_SCRIPT_HANDLE', 'atareao-episode-video');
define('EVP_ASSETS', EVP_ROOT . '/wp-content/plugins/atareao-functionality/assets');
define('EVP_JS', EVP_ASSETS . '/js/episode-video.js');
define('EVP_CSS', EVP_ASSETS . '/css/episode-video.css');
define('EVP_DOC_YT', EVP_ROOT . '/docs/youtube-video-fallback.md');
define('EVP_DOC_SEO', EVP_ROOT . '/docs/seo-visibility.md');

/*
 * El arnés no carga el bootstrap del plugin, que es quien define estas
 * constantes en producción. Se declaran aquí con los valores reales para poder
 * aseverar la ruta y el versionado de los assets registrados.
 */
if (!defined('ATAREAO_PLUGIN_URL')) {
    define('ATAREAO_PLUGIN_URL', EVP_PLUGIN_URL);
}
if (!defined('ATAREAO_PLUGIN_VERSION')) {
    define('ATAREAO_PLUGIN_VERSION', EVP_PLUGIN_VERSION);
}

if (is_file(EVP_SEO)) {
    require_once EVP_SEO;
}

$GLOBALS['evp_passes']   = 0;
$GLOBALS['evp_failures'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function evp_pass($description)
{
    $GLOBALS['evp_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function evp_fail($description)
{
    $GLOBALS['evp_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function evp_assert_true($condition, $description)
{
    if ($condition) {
        evp_pass($description);

        return;
    }

    evp_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function evp_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        evp_pass($description);

        return;
    }

    evp_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

/**
 * Aserción de contención de una subcadena.
 *
 * @param string $needle      Subcadena buscada.
 * @param string $haystack    Cadena donde buscar.
 * @param string $description Descripción.
 * @return void
 */
function evp_assert_contains($needle, $haystack, $description)
{
    evp_assert_true(false !== strpos((string) $haystack, $needle), $description);
}

/**
 * Aserción de ausencia de una subcadena.
 *
 * @param string $needle      Subcadena prohibida.
 * @param string $haystack    Cadena donde buscar.
 * @param string $description Descripción.
 * @return void
 */
function evp_assert_not_contains($needle, $haystack, $description)
{
    evp_assert_true(false === strpos((string) $haystack, $needle), $description);
}

/**
 * Crea un episodio en el archivo en memoria con su contenido y metadatos.
 *
 * @param int    $id      Identificador.
 * @param string $content Contenido.
 * @param array  $meta    Metadatos.
 * @return \WP_Post
 */
function evp_episode($id, $content, $meta)
{
    $post = new \WP_Post(array(
        'ID'            => $id,
        'post_type'     => 'podcast',
        'post_status'   => 'publish',
        'post_title'    => 'Episodio ' . $id,
        'post_content'  => $content,
        'post_date'     => '2026-11-02 10:00:00',
        'post_modified' => '2026-11-02 10:00:00',
    ));
    $GLOBALS['mock_posts'][]        = $post;
    $GLOBALS['mock_post_meta'][$id] = is_array($meta) ? $meta : array();

    return $post;
}

/**
 * Reinicia el estado del arnés y vuelve a registrar los hooks del plugin.
 *
 * @return void
 */
function evp_boot()
{
    harness_reset_state();
    \Atareao\SEO::init();
}

/**
 * Ejecuta el filtro de la fachada sin provocar un error fatal cuando el método
 * aún no existe (fase RED): devuelve el contenido intacto en ese caso.
 *
 * @param string $content Contenido de entrada.
 * @return string Contenido tras el filtro.
 */
function evp_player_content($content)
{
    if (!method_exists('\Atareao\SEO', 'appendEpisodeVideoPlayer')) {
        return $content;
    }

    return \Atareao\SEO::appendEpisodeVideoPlayer($content);
}

/**
 * Localiza la entidad `VideoObject` del grafo.
 *
 * @param mixed $graph Grafo de entidades.
 * @return array|null
 */
function evp_find_video($graph)
{
    foreach ((array) $graph as $entity) {
        if (is_array($entity) && isset($entity['@type']) && 'VideoObject' === $entity['@type']) {
            return $entity;
        }
    }

    return null;
}

/**
 * Extrae una directiva de la CSP declarada en `nginx/pro/wp.conf`.
 *
 * @param string $csp       Valor completo de `Content-Security-Policy`.
 * @param string $directive Nombre de la directiva (por ejemplo `img-src`).
 * @return string Valor de la directiva, o `''` si no está.
 */
function evp_csp_directive($csp, $directive)
{
    if (preg_match('#(?:^|;\s*)' . preg_quote($directive, '#') . '\s+([^;]+)#', $csp, $match) === 1) {
        return trim($match[1]);
    }

    return '';
}

if (!is_file(EVP_SEO)) {
    echo "  ✗ existe includes/class-seo.php\n";
    echo "\nResumen: 0 PASS, 1 FAIL\n";
    exit(1);
}

echo "\n=== episode-video-player — fachada del vídeo del episodio ===\n";

// ---------------------------------------------------------------------------
echo "\n--- Resolución: retorno ampliado con id y source (TDD 1.1–1.3) ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
$evp_post = evp_episode(
    7201,
    '<p>Mira:</p><iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe>',
    array('youtube_url' => 'https://www.youtube.com/watch?v=zzzzzzzzzzz')
);
$evp_video = \Atareao\SEO::embeddedVideo($evp_post);
evp_assert_equals('abcdefghijk', $evp_video['id'] ?? null, 'el embed del contenido expone su id de YouTube');
evp_assert_equals('content', $evp_video['source'] ?? null, 'el embed del contenido declara source=content');
evp_assert_equals(
    'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg',
    $evp_video['thumbnail'] ?? null,
    'la miniatura sigue derivándose del id del contenido'
);

harness_reset_state();
$evp_post = evp_episode(7202, 'Contenido sin vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=gxC35wcbW_s',
));
$evp_video = \Atareao\SEO::embeddedVideo($evp_post);
evp_assert_equals('gxC35wcbW_s', $evp_video['id'] ?? null, 'el respaldo en metadato expone el id del metadato');
evp_assert_equals('meta', $evp_video['source'] ?? null, 'el respaldo en metadato declara source=meta');

harness_reset_state();
$evp_post = evp_episode(7203, '<iframe src="https://player.vimeo.com/video/123456789"></iframe>', array());
$evp_video = \Atareao\SEO::embeddedVideo($evp_post);
evp_assert_equals('', $evp_video['id'] ?? null, 'un vídeo de Vimeo devuelve id vacío (no hay id de YouTube)');
evp_assert_equals('content', $evp_video['source'] ?? null, 'un vídeo de Vimeo viene del contenido');

// ---------------------------------------------------------------------------
echo "\n--- Fachada al final del contenido (TDD 2.1) ---\n";
// ---------------------------------------------------------------------------
evp_assert_true(
    method_exists('\Atareao\SEO', 'appendEpisodeVideoPlayer'),
    'SEO expone appendEpisodeVideoPlayer()'
);

harness_reset_state();
$evp_post = evp_episode(7210, 'Contenido sin vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
));
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = true;
$evp_content = evp_player_content('ORIGEN');

evp_assert_true(0 === strpos($evp_content, 'ORIGEN'), 'la fachada se añade al final del contenido');
evp_assert_contains('class="episode-video"', $evp_content, 'la fachada usa la clase .episode-video');
evp_assert_contains('data-video-id="abcdefghijk"', $evp_content, 'la fachada lleva el identificador del metadato');
evp_assert_contains(
    'src="https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg"',
    $evp_content,
    'la fachada usa la miniatura del identificador'
);
evp_assert_contains(
    'href="https://www.youtube.com/watch?v=abcdefghijk"',
    $evp_content,
    'la fachada enlaza al vídeo canónico de YouTube'
);
evp_assert_contains('class="episode-video__link"', $evp_content, 'la fachada contiene el enlace .episode-video__link');
evp_assert_contains('class="episode-video__thumb"', $evp_content, 'la fachada contiene la miniatura .episode-video__thumb');
evp_assert_contains('class="episode-video__play"', $evp_content, 'la fachada contiene el botón de reproducción');
evp_assert_contains('screen-reader-text', $evp_content, 'la fachada lleva el texto accesible');
evp_assert_contains('Ver el vídeo de este episodio', $evp_content, 'el texto accesible describe la acción');
evp_assert_contains('loading="lazy"', $evp_content, 'la miniatura se carga en diferido');
evp_assert_contains('decoding="async"', $evp_content, 'la miniatura se decodifica de forma asíncrona');
evp_assert_contains('width="480"', $evp_content, 'la miniatura declara su ancho (sin CLS)');
evp_assert_contains('height="360"', $evp_content, 'la miniatura declara su alto (sin CLS)');

// Guarda defensiva: sin identificador la fachada no debe emitirse. El contrato
// actual nunca llega a `source === 'meta'` con `id === ''`, así que se comprueba
// de forma estática sobre el cuerpo del propio método en producción.
$evp_seo_src     = is_readable(EVP_SEO) ? (string) file_get_contents(EVP_SEO) : '';
$evp_facade_at   = strpos($evp_seo_src, 'function appendEpisodeVideoPlayer');
$evp_facade_body = '';
if (false !== $evp_facade_at) {
    $evp_facade_end  = strpos($evp_seo_src, "\n    }", $evp_facade_at);
    $evp_facade_body = false !== $evp_facade_end
        ? substr($evp_seo_src, $evp_facade_at, $evp_facade_end - $evp_facade_at)
        : substr($evp_seo_src, $evp_facade_at);
}
evp_assert_contains(
    "if ('' === \$id) {",
    $evp_facade_body,
    'appendEpisodeVideoPlayer() guarda el caso de identificador vacío antes de emitir'
);

// ---------------------------------------------------------------------------
echo "\n--- Sin JavaScript la fachada sigue siendo un enlace (TDD 2.6, ADDED) ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
$evp_post = evp_episode(7211, 'Contenido sin vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=1Q5rGnwRBTY',
));
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = true;
$evp_content = evp_player_content('');
evp_assert_contains(
    '<a class="episode-video__link" href="https://www.youtube.com/watch?v=1Q5rGnwRBTY" rel="noopener">',
    $evp_content,
    'sin JS el respaldo es un <a> a la URL canónica con rel=noopener'
);
evp_assert_contains(
    'src="https://i.ytimg.com/vi/1Q5rGnwRBTY/hqdefault.jpg"',
    $evp_content,
    'el <a> de respaldo contiene la miniatura'
);

// ---------------------------------------------------------------------------
echo "\n--- El vídeo incrustado en el cuerpo no se duplica (TDD 2.2) ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
$evp_post = evp_episode(
    7212,
    '<p>Mira:</p><iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe>',
    array('youtube_url' => 'https://www.youtube.com/watch?v=zzzzzzzzzzz')
);
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = true;
$evp_content = evp_player_content('<p>Mira:</p>');
evp_assert_equals('<p>Mira:</p>', $evp_content, 'con el vídeo en el cuerpo NO se añade la fachada (no se duplica)');
evp_assert_not_contains('episode-video', $evp_content, 'con source=content no aparece ninguna fachada .episode-video');

// ---------------------------------------------------------------------------
echo "\n--- Sin vídeo ni singularidad no se añade nada (TDD 2.3–2.4) ---\n";
// ---------------------------------------------------------------------------
harness_reset_state();
$evp_post = evp_episode(7213, 'Contenido sin vídeo.', array());
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = true;
evp_assert_equals('ORIGEN', evp_player_content('ORIGEN'), 'sin vídeo no se añade fachada');

harness_reset_state();
$evp_post = evp_episode(7214, 'Contenido sin vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
));
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = false;
evp_assert_equals('ORIGEN', evp_player_content('ORIGEN'), 'en un listado (no singular) no se añade fachada');

// ---------------------------------------------------------------------------
echo "\n--- Assets registrados con ATAREAO_PLUGIN_VERSION (TDD 3.1–3.4) ---\n";
// ---------------------------------------------------------------------------
evp_boot();

$evp_style = $GLOBALS['mock_registered_styles'][EVP_STYLE_HANDLE] ?? null;
evp_assert_true(is_array($evp_style), 'SEO::init() registra el estilo de la fachada');
if (is_array($evp_style)) {
    evp_assert_equals(
        EVP_PLUGIN_URL . 'assets/css/episode-video.css',
        $evp_style['src'] ?? null,
        'el CSS de la fachada apunta a assets/css/episode-video.css'
    );
    evp_assert_equals(EVP_PLUGIN_VERSION, $evp_style['ver'] ?? null, 'el CSS se versiona con ATAREAO_PLUGIN_VERSION');
}

$evp_script = $GLOBALS['mock_registered_scripts'][EVP_SCRIPT_HANDLE] ?? null;
evp_assert_true(is_array($evp_script), 'SEO::init() registra el script de la fachada');
if (is_array($evp_script)) {
    evp_assert_equals(
        EVP_PLUGIN_URL . 'assets/js/episode-video.js',
        $evp_script['src'] ?? null,
        'el JS de la fachada apunta a assets/js/episode-video.js'
    );
    evp_assert_equals(EVP_PLUGIN_VERSION, $evp_script['ver'] ?? null, 'el JS se versiona con ATAREAO_PLUGIN_VERSION');
    evp_assert_true(!empty($evp_script['in_footer']), 'el JS se imprime en el pie de página');
}

// ---------------------------------------------------------------------------
echo "\n--- Assets encolados solo en el singular con vídeo de metadato (TDD 3.3) ---\n";
// ---------------------------------------------------------------------------
evp_boot();
$evp_post = evp_episode(7220, 'Contenido sin vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
));
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = true;
do_action('wp_enqueue_scripts');
evp_assert_true(!empty($GLOBALS['mock_enqueued_styles'][EVP_STYLE_HANDLE]), 'en singular con vídeo de metadato se encola el CSS');
evp_assert_true(!empty($GLOBALS['mock_enqueued_scripts'][EVP_SCRIPT_HANDLE]), 'en singular con vídeo de metadato se encola el JS');

evp_boot();
$evp_post = evp_episode(7221, 'Contenido sin vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
));
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = false;
do_action('wp_enqueue_scripts');
evp_assert_true(empty($GLOBALS['mock_enqueued_styles'][EVP_STYLE_HANDLE]), 'en un listado NO se encola el CSS');
evp_assert_true(empty($GLOBALS['mock_enqueued_scripts'][EVP_SCRIPT_HANDLE]), 'en un listado NO se encola el JS');

evp_boot();
$evp_post = evp_episode(7222, 'Contenido sin vídeo.', array());
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = true;
do_action('wp_enqueue_scripts');
evp_assert_true(empty($GLOBALS['mock_enqueued_styles'][EVP_STYLE_HANDLE]), 'en un episodio sin vídeo NO se encola el CSS');

evp_boot();
$evp_post = evp_episode(7223, '<iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe>', array());
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = true;
do_action('wp_enqueue_scripts');
evp_assert_true(
    empty($GLOBALS['mock_enqueued_styles'][EVP_STYLE_HANDLE]),
    'con el vídeo en el cuerpo (source=content) NO se encola el CSS'
);

evp_boot();
$evp_post = evp_episode(7224, 'Contenido sin vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
));
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = false;
$GLOBALS['mock_home']           = true;
do_action('wp_enqueue_scripts');
evp_assert_true(empty($GLOBALS['mock_enqueued_styles'][EVP_STYLE_HANDLE]), 'en la portada NO se encola el CSS');
evp_assert_true(empty($GLOBALS['mock_enqueued_scripts'][EVP_SCRIPT_HANDLE]), 'en la portada NO se encola el JS');

evp_boot();
$evp_post = new \WP_Post(array(
    'ID'           => 7225,
    'post_type'    => 'post',
    'post_status'  => 'publish',
    'post_title'   => 'Entrada normal',
    'post_content' => 'Contenido sin vídeo.',
    'post_date'    => '2026-11-02 10:00:00',
));
$GLOBALS['mock_posts'][]        = $evp_post;
$GLOBALS['mock_post_meta'][7225] = array('youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk');
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = true;
do_action('wp_enqueue_scripts');
evp_assert_true(
    empty($GLOBALS['mock_enqueued_styles'][EVP_STYLE_HANDLE]),
    'en el singular de un tipo que no es episodio NO se encola el CSS'
);
evp_assert_true(
    empty($GLOBALS['mock_enqueued_scripts'][EVP_SCRIPT_HANDLE]),
    'en el singular de un tipo que no es episodio NO se encola el JS'
);

// ---------------------------------------------------------------------------
echo "\n--- CSP de producción: miniatura y frame-src (TDD 4.2) ---\n";
// ---------------------------------------------------------------------------
$evp_wp_conf = is_readable(EVP_NGINX) ? (string) file_get_contents(EVP_NGINX) : '';
evp_assert_true('' !== $evp_wp_conf, 'nginx/pro/wp.conf es legible');
$evp_csp_line = '';
if (preg_match('#add_header Content-Security-Policy "([^"]+)"#', $evp_wp_conf, $evp_match) === 1) {
    $evp_csp_line = $evp_match[1];
}
evp_assert_true('' !== $evp_csp_line, 'nginx/pro/wp.conf declara la cabecera Content-Security-Policy');

$evp_img_src = evp_csp_directive($evp_csp_line, 'img-src');
evp_assert_contains(
    'https://i.ytimg.com',
    $evp_img_src,
    'la CSP de producción permite la miniatura (img-src incluye https://i.ytimg.com)'
);

$evp_frame_src = evp_csp_directive($evp_csp_line, 'frame-src');
evp_assert_contains('https:', $evp_frame_src, 'la CSP de producción permite el origen https: del <iframe>');

// ---------------------------------------------------------------------------
echo "\n--- Assets estáticos: JS y CSS de la fachada (MEDIA 2) ---\n";
// ---------------------------------------------------------------------------
$evp_js = is_readable(EVP_JS) ? (string) file_get_contents(EVP_JS) : '';
evp_assert_true('' !== $evp_js, 'assets/js/episode-video.js existe y es legible');
evp_assert_contains(
    'youtube-nocookie.com/embed/',
    $evp_js,
    'el JS incrusta el reproductor con youtube-nocookie.com/embed/ (privacidad)'
);
evp_assert_contains(
    '?autoplay=1&rel=0',
    $evp_js,
    'el JS encola la reproducción automática sin vídeos relacionados'
);
evp_assert_contains('data-video-id', $evp_js, 'el JS lee el identificador de data-video-id');
evp_assert_true(
    false !== strpos($evp_js, 'replaceChild') || false !== strpos($evp_js, 'createElement('),
    'el JS sustituye el enlace por el <iframe> (replaceChild/createElement)'
);
evp_assert_not_contains('innerHTML', $evp_js, 'el JS NO usa innerHTML (defensa anti-XSS)');
evp_assert_contains('preventDefault', $evp_js, 'el JS intercepta el clic del enlace');
evp_assert_contains('DOMContentLoaded', $evp_js, 'el JS engancha los enlaces al DOMContentLoaded');

$evp_css = is_readable(EVP_CSS) ? (string) file_get_contents(EVP_CSS) : '';
evp_assert_true('' !== $evp_css, 'assets/css/episode-video.css existe y es legible');
evp_assert_contains('aspect-ratio', $evp_css, 'el CSS declara la relación de aspecto del contenedor');
evp_assert_contains('object-fit: cover', $evp_css, 'el CSS ajusta la miniatura con object-fit: cover');
evp_assert_contains('.episode-video__play', $evp_css, 'el CSS estiliza el botón de reproducción');
evp_assert_contains('screen-reader-text', $evp_css, 'el CSS oculta visualmente el texto accesible');

// ---------------------------------------------------------------------------
echo "\n--- Documentación viva sin referencias huérfanas (MEDIA 1) ---\n";
// ---------------------------------------------------------------------------
$evp_doc_yt  = is_readable(EVP_DOC_YT) ? (string) file_get_contents(EVP_DOC_YT) : '';
$evp_doc_seo = is_readable(EVP_DOC_SEO) ? (string) file_get_contents(EVP_DOC_SEO) : '';
evp_assert_true('' !== $evp_doc_yt, 'docs/youtube-video-fallback.md es legible');
evp_assert_true('' !== $evp_doc_seo, 'docs/seo-visibility.md es legible');
evp_assert_not_contains(
    'appendEpisodeVideoLink',
    $evp_doc_yt,
    'docs/youtube-video-fallback.md no cita el método renombrado appendEpisodeVideoLink'
);
evp_assert_contains(
    'appendEpisodeVideoPlayer',
    $evp_doc_yt,
    'docs/youtube-video-fallback.md describe el método vigente appendEpisodeVideoPlayer'
);
evp_assert_contains(
    '.episode-video',
    $evp_doc_yt,
    'docs/youtube-video-fallback.md describe la fachada vigente .episode-video'
);
evp_assert_contains(
    '#240',
    $evp_doc_yt,
    'docs/youtube-video-fallback.md nota que en #240 se sustituyó el enlace por la fachada'
);
evp_assert_not_contains(
    'appendEpisodeVideoLink',
    $evp_doc_seo,
    'docs/seo-visibility.md no cita el método renombrado appendEpisodeVideoLink'
);
evp_assert_contains(
    'appendEpisodeVideoPlayer',
    $evp_doc_seo,
    'docs/seo-visibility.md describe el método vigente appendEpisodeVideoPlayer'
);

// ---------------------------------------------------------------------------
echo "\n--- VideoObject: sin regresión (TDD 1.4) ---\n";
// ---------------------------------------------------------------------------
evp_boot();
$evp_post = evp_episode(7230, 'Contenido sin vídeo.', array(
    'youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
));
$GLOBALS['mock_queried_object'] = $evp_post;
$GLOBALS['mock_singular']       = true;
$evp_object = evp_find_video(apply_filters('the_seo_framework_schema_graph_data', array(), null));
evp_assert_true(is_array($evp_object), 'con vídeo de metadato el grafo sigue declarando VideoObject');
if (is_array($evp_object)) {
    evp_assert_equals(
        'https://www.youtube.com/watch?v=abcdefghijk',
        $evp_object['url'] ?? null,
        'el VideoObject sigue consumiendo la URL de la resolución'
    );
    evp_assert_equals(
        'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg',
        $evp_object['thumbnailUrl'] ?? null,
        'el VideoObject sigue consumiendo la miniatura de la resolución'
    );
}

// ---------------------------------------------------------------------------
echo "\nResumen: {$GLOBALS['evp_passes']} PASS, " . count($GLOBALS['evp_failures']) . " FAIL\n";

if (!empty($GLOBALS['evp_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['evp_failures'] as $evp_failure) {
        echo "  - {$evp_failure}\n";
    }

    exit(1);
}

exit(0);
