<?php
/**
 * Arnés procedural de la fase RED del change `podcast-episode-admin-fields`.
 *
 * Fija el contrato de los campos de multimedia del episodio —imagen del
 * episodio y documento de capítulos— sobre `Atareao\Metaboxes`, que la fase
 * GREEN implementará. Mientras no existan los métodos ni las constantes, cada
 * escenario se imprime como FALLO con motivo «no implementado» y el proceso
 * termina con código 1, sin morir con un fatal.
 *
 * Mismo estilo que `tests/podcast-srt-upload/test_srt_upload.php`: contador
 * propio de pases/fallos, `exit(0)` si todo pasa y `exit(1)` si algo falla.
 * Se ejecuta con `just test-episode-admin-fields`.
 *
 * Escenarios cubiertos (ids del spec `podcast-feed`):
 *  - Campos vacíos / Valores existentes (render del metabox).
 *  - Guardado válido, Nonce inválido, Usuario sin permiso, Autosave y post
 *    type distinto (guardado seguro).
 *  - Limpiar un campo (borrado del metadato y vuelta de la portada del feed).
 *  - Imagen válida / Adjunto inexistente o que no es imagen (validación).
 *  - Capítulos válidos / Capítulos no válidos (validación).
 *  - Metadatos no expuestos por REST (registro).
 *  - Regresión de imagen / Regresión de capítulos (feed).
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

require_once dirname(__DIR__) . '/wp-stubs.php';

define('PAF_ROOT', dirname(__DIR__, 2));
define('PAF_METABOXES', PAF_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-metaboxes.php');
define('PAF_FEED', PAF_ROOT . '/wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php');

/*
 * Carga los módulos de producción cuando existan. En RED los métodos del
 * contrato aún no existen y cada escenario se reporta como fallo, sin fatal.
 */
foreach (array(PAF_FEED, PAF_METABOXES) as $paf_bootstrap_file) {
    if (is_file($paf_bootstrap_file)) {
        require_once $paf_bootstrap_file;
    }
}

$GLOBALS['paf_passes'] = 0;
$GLOBALS['paf_failures'] = array();
$GLOBALS['paf_tmp_files'] = array();

/**
 * Registra un pase.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function paf_pass($description)
{
    $GLOBALS['paf_passes']++;
    echo "  PASS: {$description}\n";
}

/**
 * Registra un fallo.
 *
 * @param string $description Descripción legible.
 * @return void
 */
function paf_fail($description)
{
    $GLOBALS['paf_failures'][] = $description;
    echo "  FAIL: {$description}\n";
}

/**
 * Aserción booleana.
 *
 * @param bool   $condition   Condición.
 * @param string $description Descripción.
 * @return void
 */
function paf_assert_true($condition, $description)
{
    if ($condition) {
        paf_pass($description);

        return;
    }

    paf_fail($description);
}

/**
 * Aserción de igualdad estricta.
 *
 * @param mixed  $expected    Valor esperado.
 * @param mixed  $actual      Valor real.
 * @param string $description Descripción.
 * @return void
 */
function paf_assert_equals($expected, $actual, $description)
{
    if ($expected === $actual) {
        paf_pass($description);

        return;
    }

    paf_fail(
        $description . ' (esperado ' . var_export($expected, true)
        . ', obtenido ' . var_export($actual, true) . ')'
    );
}

/**
 * Aserción de subcadena.
 *
 * @param string $needle      Subcadena buscada.
 * @param string $haystack    Texto donde buscar.
 * @param string $description Descripción.
 * @return void
 */
function paf_assert_contains($needle, $haystack, $description)
{
    paf_assert_true(
        is_string($haystack) && strpos($haystack, (string) $needle) !== false,
        $description
    );
}

/**
 * Aserción de ausencia de subcadena.
 *
 * @param string $needle      Subcadena prohibida.
 * @param string $haystack    Texto donde buscar.
 * @param string $description Descripción.
 * @return void
 */
function paf_assert_not_contains($needle, $haystack, $description)
{
    paf_assert_true(
        !is_string($haystack) || strpos($haystack, (string) $needle) === false,
        $description
    );
}

/**
 * Reporta un método o constante que la fase GREEN aún no ha implementado.
 *
 * @param string $what Contrato ausente.
 * @return void
 */
function paf_not_implemented($what)
{
    paf_fail($what . ' — no implementado');
}

/**
 * Comprueba si un método público de `Atareao\Metaboxes` existe.
 *
 * @param string $method Nombre del método.
 * @return bool
 */
function paf_has_method($method)
{
    return class_exists('\Atareao\Metaboxes') && method_exists('\Atareao\Metaboxes', $method);
}

/**
 * Valor de una constante pública de `Atareao\Metaboxes`, o `null` si no existe.
 *
 * @param string $name Nombre de la constante.
 * @return mixed
 */
function paf_constant($name)
{
    $reference = 'Atareao\\Metaboxes::' . $name;

    return defined($reference) ? constant($reference) : null;
}

/**
 * Clave de metadato de la imagen del episodio (constante del feed si existe).
 *
 * @return string
 */
function paf_episode_image_key()
{
    return class_exists('\Atareao\PodcastFeed')
        ? (string) \Atareao\PodcastFeed::EPISODE_IMAGE_META
        : 'episode-image';
}

/**
 * Clave de metadato de los capítulos (constante del feed si existe).
 *
 * @return string
 */
function paf_chapters_key()
{
    return class_exists('\Atareao\PodcastFeed')
        ? (string) \Atareao\PodcastFeed::CHAPTERS_META
        : 'podcast-chapters';
}

/**
 * Deja el simulador en un estado limpio con un usuario editor.
 *
 * @return void
 */
function paf_reset()
{
    harness_reset_state();
    $GLOBALS['mock_current_user_id'] = 7;
    $GLOBALS['mock_user_caps'] = array(
        'edit_post' => true,
        'edit_posts' => true,
    );
}

/**
 * Siembra una entrada en la fixture y la devuelve.
 *
 * @param int    $id   Identificador.
 * @param string $type Tipo de contenido.
 * @return \WP_Post
 */
function paf_seed_post($id, $type = 'podcast')
{
    $post = new \WP_Post(
        array(
            'ID' => (int) $id,
            'post_type' => (string) $type,
            'post_status' => 'publish',
            'post_password' => '',
            'post_title' => 'Episodio ' . $id,
            'post_date' => '2026-11-01 08:00:00',
            'post_modified' => '2026-11-01 08:00:00',
            'post_author' => 7,
            'post_content' => '',
            'post_excerpt' => '',
        )
    );
    $GLOBALS['mock_posts'][] = $post;

    return $post;
}

/**
 * Crea un fichero temporal con extensión y lo registra para el cierre.
 *
 * @param string $extension Extensión sin punto.
 * @param string $contents  Contenido.
 * @return string Ruta absoluta.
 */
function paf_temp_file($extension, $contents = '')
{
    $path = sys_get_temp_dir() . '/paf-' . uniqid('', true) . '.' . $extension;
    file_put_contents($path, $contents);
    $GLOBALS['paf_tmp_files'][] = $path;

    return $path;
}

/**
 * Siembra un adjunto en la fixture.
 *
 * @param int         $id   Identificador.
 * @param string      $mime MIME type.
 * @param string      $url  URL absoluta.
 * @param string|null $file Ruta del fichero (null: sin fichero).
 * @param string      $alt  Texto alternativo.
 * @return void
 */
function paf_seed_attachment($id, $mime, $url, $file = null, $alt = '')
{
    $attachment = array(
        'mime' => (string) $mime,
        'url' => (string) $url,
        'alt' => (string) $alt,
    );
    if (null !== $file) {
        $attachment['file'] = (string) $file;
    }

    $GLOBALS['mock_attachments'][(int) $id] = $attachment;
}

/**
 * Ejecuta un callable capturando su salida y cualquier error, sin fatal.
 *
 * @param callable $callable Callable a ejecutar.
 * @return array{0: string|null, 1: \Throwable|null} Salida y excepción.
 */
function paf_capture($callable)
{
    ob_start();
    try {
        $callable();
    } catch (\Throwable $error) {
        ob_end_clean();

        return array(null, $error);
    }

    return array(ob_get_clean(), null);
}

/**
 * Renderiza el metabox y devuelve su HTML, o `null` si lanzó un error.
 *
 * @param \WP_Post $post Entrada.
 * @param string   $scenario Id del escenario para el mensaje de fallo.
 * @return string|null
 */
function paf_render_metabox($post, $scenario)
{
    list($html, $error) = paf_capture(
        function () use ($post) {
            \Atareao\Metaboxes::renderEpisodeMediaMetabox($post);
        }
    );

    if (null !== $error) {
        paf_fail(
            $scenario . ': renderEpisodeMediaMetabox() lanzó '
            . get_class($error) . ': ' . $error->getMessage()
        );

        return null;
    }

    return $html;
}

/**
 * Detecta un control para quitar el adjunto en el HTML del metabox.
 *
 * El contrato no fija el marcado exacto; se acepta el atributo
 * `data-atareao-remove`, una clase `atareao-media-remove` o un control cuyo
 * texto sea «Quitar».
 *
 * @param string|null $html HTML del metabox.
 * @return bool
 */
function paf_has_remove_control($html)
{
    if (!is_string($html)) {
        return false;
    }

    if (preg_match('/data-atareao-remove|atareao-media-remove/u', $html) === 1) {
        return true;
    }

    return preg_match('/>\s*Quitar/u', $html) === 1;
}

/**
 * Invoca `saveEpisodeMedia` capturando errores, sin fatal.
 *
 * @param int    $postId Identificador.
 * @param array  $input  Entrada del formulario.
 * @param string $nonce  Nonce.
 * @param string $scenario Id del escenario.
 * @return array|null Resultado, o `null` si lanzó un error.
 */
function paf_save($postId, array $input, $nonce, $scenario)
{
    try {
        return \Atareao\Metaboxes::saveEpisodeMedia((int) $postId, $input, (string) $nonce);
    } catch (\Throwable $error) {
        paf_fail(
            $scenario . ': saveEpisodeMedia() lanzó '
            . get_class($error) . ': ' . $error->getMessage()
        );

        return null;
    }
}

/**
 * Extrae una lista de un resultado de guardado, tolerando ausencias.
 *
 * @param mixed  $result Resultado.
 * @param string $key    `saved` o `rejected`.
 * @return array
 */
function paf_result_list($result, $key)
{
    return is_array($result) && isset($result[$key]) && is_array($result[$key])
        ? $result[$key]
        : array();
}

/**
 * Invoca un validador del contrato capturando errores, sin fatal.
 *
 * @param string $method   Método.
 * @param int    $id       Adjunto.
 * @param string $scenario Id del escenario.
 * @return bool|null
 */
function paf_validate($method, $id, $scenario)
{
    try {
        return (bool) \Atareao\Metaboxes::$method((int) $id);
    } catch (\Throwable $error) {
        paf_fail(
            $scenario . ': ' . $method . '() lanzó '
            . get_class($error) . ': ' . $error->getMessage()
        );

        return null;
    }
}

/**
 * Carga un fragmento de item del feed con los prefijos declarados.
 *
 * @param string $body XML del item.
 * @return \DOMDocument|null
 */
function paf_xml($body)
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
 * @param \DOMNode|null $document Documento.
 * @param string        $prefix   `podcast` o `itunes`.
 * @param string        $local    Nombre local.
 * @return \DOMElement|null
 */
function paf_first($document, $prefix, $local)
{
    if (!$document instanceof \DOMNode) {
        return null;
    }

    $namespace = 'podcast' === $prefix
        ? 'https://podcastindex.org/namespace/1.0'
        : 'http://www.itunes.com/dtds/podcast-1.0.dtd';
    $list = $document->getElementsByTagNameNS($namespace, $local);

    return $list->length > 0 ? $list->item(0) : null;
}

/**
 * Compone el item de un episodio del feed capturando el `echo` del módulo.
 *
 * @param int $postId Episodio.
 * @return string XML emitido.
 */
function paf_item_xml($postId)
{
    ob_start();
    \Atareao\PodcastFeed::itemElements((int) $postId);

    return (string) ob_get_clean();
}

/**
 * Prepara el contexto de feed del podcast con la portada del programa.
 *
 * @param string $cover URL de portada.
 * @return void
 */
function paf_feed_context($cover)
{
    $GLOBALS['mock_is_feed'] = true;
    $GLOBALS['mock_post_type_archive'] = 'podcast';
    $GLOBALS['mock_options']['atareao_podcast_cover'] = (string) $cover;
}

echo "podcast-episode-admin-fields — contrato de los campos multimedia del episodio\n";

// ---------------------------------------------------------------------------
// 0. Contrato público: constantes de campo y de nonce.
// ---------------------------------------------------------------------------
echo "\n--- Contrato público de Metaboxes ---\n";

$paf_constants = array(
    'EPISODE_IMAGE_FIELD' => 'atareao_episode_image',
    'CHAPTERS_FIELD' => 'atareao_podcast_chapters',
    'MEDIA_NONCE_FIELD' => 'atareao_podcast_media_nonce',
    'MEDIA_NONCE_ACTION' => 'atareao_podcast_media',
);
foreach ($paf_constants as $paf_name => $paf_value) {
    if (null === paf_constant($paf_name)) {
        paf_not_implemented('Metaboxes::' . $paf_name);
        continue;
    }

    paf_assert_equals(
        $paf_value,
        paf_constant($paf_name),
        'Metaboxes::' . $paf_name . ' vale ' . $paf_value
    );
}

// ---------------------------------------------------------------------------
// 1. Render: «Campos vacíos» y «Valores existentes».
// ---------------------------------------------------------------------------
echo "\n--- S1: render del metabox («Campos vacíos» / «Valores existentes») ---\n";

paf_reset();
$paf_post = paf_seed_post(7001);

if (!paf_has_method('renderEpisodeMediaMetabox')) {
    paf_not_implemented('Escenario «Campos vacíos»: Metaboxes::renderEpisodeMediaMetabox()');
    paf_not_implemented('Escenario «Valores existentes»: Metaboxes::renderEpisodeMediaMetabox()');
} else {
    $paf_html = paf_render_metabox($paf_post, 'Campos vacíos');

    paf_assert_contains(
        'atareao_podcast_media_nonce',
        $paf_html,
        'Campos vacíos: el metabox imprime el campo de nonce'
    );
    paf_assert_contains(
        'name="atareao_episode_image"',
        $paf_html,
        'Campos vacíos: el campo de imagen está presente'
    );
    paf_assert_contains(
        'name="atareao_podcast_chapters"',
        $paf_html,
        'Campos vacíos: el campo de capítulos está presente'
    );
    paf_assert_true(
        is_string($paf_html) && substr_count($paf_html, 'type="hidden"') >= 2,
        'Campos vacíos: los dos campos son ocultos (type="hidden")'
    );
    paf_assert_true(
        !paf_has_remove_control($paf_html),
        'Campos vacíos: sin valor no se muestra botón de quitar'
    );

    $paf_image_url = 'https://atareao.es/wp-content/uploads/2026/11/episodio-7001.png';
    paf_seed_attachment(1234, 'image/png', $paf_image_url, null, 'Miniatura 1234');
    $GLOBALS['mock_post_meta'][7001][paf_episode_image_key()] = 1234;
    $GLOBALS['mock_post_meta'][7001][paf_chapters_key()] = 5678;

    $paf_html = paf_render_metabox($paf_post, 'Valores existentes');

    paf_assert_contains(
        $paf_image_url,
        $paf_html,
        'Valores existentes: se muestra la miniatura del adjunto 1234'
    );
    paf_assert_contains(
        'value="1234"',
        $paf_html,
        'Valores existentes: el campo de imagen indica 1234'
    );
    paf_assert_contains(
        'value="5678"',
        $paf_html,
        'Valores existentes: el campo de capítulos indica 5678'
    );
    paf_assert_true(
        paf_has_remove_control($paf_html),
        'Valores existentes: ambos campos muestran botón de quitar'
    );

    // El valor se normaliza con absint y se escapa: nada de atributos inyectados.
    $GLOBALS['mock_post_meta'][7001][paf_chapters_key()] = '5678" onfocus="alert(1)';
    $paf_html = paf_render_metabox($paf_post, 'Valores existentes (escape)');
    paf_assert_not_contains(
        'onfocus',
        $paf_html,
        'Valores existentes: el valor del metadato se escapa con esc_attr'
    );
    paf_assert_contains(
        'value="5678"',
        $paf_html,
        'Valores existentes: el valor del metadato se normaliza con absint'
    );
}

// ---------------------------------------------------------------------------
// 2. Guardado válido.
// ---------------------------------------------------------------------------
echo "\n--- S2: «Guardado válido» ---\n";

paf_reset();
paf_seed_post(7002);

if (!paf_has_method('saveEpisodeMedia')) {
    paf_not_implemented('Escenario «Guardado válido»: Metaboxes::saveEpisodeMedia()');
} else {
    $paf_result = paf_save(
        7002,
        array(
            'atareao_episode_image' => '1234',
            'atareao_podcast_chapters' => '5678',
        ),
        'valid_nonce',
        'Guardado válido'
    );

    paf_assert_true(is_array($paf_result), 'Guardado válido: saveEpisodeMedia() devuelve un array');
    paf_assert_true(
        is_array($paf_result) && array_key_exists('saved', $paf_result) && array_key_exists('rejected', $paf_result),
        'Guardado válido: el resultado declara saved y rejected'
    );
    paf_assert_true(
        in_array(paf_episode_image_key(), paf_result_list($paf_result, 'saved'), true),
        'Guardado válido: saved incluye episode-image'
    );
    paf_assert_true(
        in_array(paf_chapters_key(), paf_result_list($paf_result, 'saved'), true),
        'Guardado válido: saved incluye podcast-chapters'
    );
    paf_assert_equals(
        array(),
        paf_result_list($paf_result, 'rejected'),
        'Guardado válido: rejected queda vacío'
    );
    paf_assert_equals(
        1234,
        get_post_meta(7002, paf_episode_image_key(), true),
        'Guardado válido: episode-image se normaliza a entero 1234'
    );
    paf_assert_equals(
        5678,
        get_post_meta(7002, paf_chapters_key(), true),
        'Guardado válido: podcast-chapters se normaliza a entero 5678'
    );

    $paf_actions = isset($GLOBALS['mock_verify_nonce_actions']) ? $GLOBALS['mock_verify_nonce_actions'] : array();
    $paf_action = null;
    if (null === paf_constant('MEDIA_NONCE_ACTION')) {
        paf_not_implemented('Metaboxes::MEDIA_NONCE_ACTION');
    } else {
        $paf_action = paf_constant('MEDIA_NONCE_ACTION');
    }
    paf_assert_equals(
        array($paf_action),
        $paf_actions,
        'Guardado válido: el nonce se verifica contra MEDIA_NONCE_ACTION'
    );
}

// ---------------------------------------------------------------------------
// 3. Nonce inválido.
// ---------------------------------------------------------------------------
echo "\n--- S3: «Nonce inválido» ---\n";

paf_reset();
paf_seed_post(7003);
$GLOBALS['mock_post_meta'][7003][paf_episode_image_key()] = 1234;
$GLOBALS['mock_post_meta'][7003][paf_chapters_key()] = 5678;
$GLOBALS['mock_verify_nonce_result'] = false;

if (!paf_has_method('saveEpisodeMedia')) {
    paf_not_implemented('Escenario «Nonce inválido»: Metaboxes::saveEpisodeMedia()');
} else {
    $paf_result = paf_save(
        7003,
        array(
            'atareao_episode_image' => '2222',
            'atareao_podcast_chapters' => '3333',
        ),
        'nonce-invalido',
        'Nonce inválido'
    );

    paf_assert_equals(
        1234,
        get_post_meta(7003, paf_episode_image_key(), true),
        'Nonce inválido: episode-image queda sin cambios'
    );
    paf_assert_equals(
        5678,
        get_post_meta(7003, paf_chapters_key(), true),
        'Nonce inválido: podcast-chapters queda sin cambios'
    );
    paf_assert_equals(
        array(),
        paf_result_list($paf_result, 'saved'),
        'Nonce inválido: no se declara ninguna clave guardada'
    );
    paf_assert_true(
        in_array(paf_episode_image_key(), paf_result_list($paf_result, 'rejected'), true),
        'Nonce inválido: rejected incluye episode-image (tenía valor)'
    );
    paf_assert_true(
        in_array(paf_chapters_key(), paf_result_list($paf_result, 'rejected'), true),
        'Nonce inválido: rejected incluye podcast-chapters (tenía valor)'
    );
}

// ---------------------------------------------------------------------------
// 4. Usuario sin permiso.
// ---------------------------------------------------------------------------
echo "\n--- S4: «Usuario sin permiso» ---\n";

paf_reset();
paf_seed_post(7004);
$GLOBALS['mock_post_meta'][7004][paf_episode_image_key()] = 1234;
$GLOBALS['mock_user_caps'] = array('edit_post' => false, 'edit_posts' => false);

if (!paf_has_method('saveEpisodeMedia')) {
    paf_not_implemented('Escenario «Usuario sin permiso»: Metaboxes::saveEpisodeMedia()');
} else {
    $paf_result = paf_save(
        7004,
        array('atareao_episode_image' => '2222'),
        'valid_nonce',
        'Usuario sin permiso'
    );

    paf_assert_equals(
        1234,
        get_post_meta(7004, paf_episode_image_key(), true),
        'Usuario sin permiso: episode-image queda sin cambios'
    );
    paf_assert_equals(
        array(),
        paf_result_list($paf_result, 'saved'),
        'Usuario sin permiso: no se declara ninguna clave guardada'
    );
}

// ---------------------------------------------------------------------------
// 5. Autosave y revisión.
// ---------------------------------------------------------------------------
echo "\n--- S5: «Autosave» ---\n";

paf_reset();
paf_seed_post(7005);
$GLOBALS['mock_post_meta'][7005][paf_episode_image_key()] = 1234;
$GLOBALS['mock_is_post_autosave'] = true;

if (!paf_has_method('saveEpisodeMedia')) {
    paf_not_implemented('Escenario «Autosave»: Metaboxes::saveEpisodeMedia()');
} else {
    $paf_result = paf_save(
        7005,
        array('atareao_episode_image' => '2222'),
        'valid_nonce',
        'Autosave'
    );

    paf_assert_equals(
        1234,
        get_post_meta(7005, paf_episode_image_key(), true),
        'Autosave: episode-image queda sin cambios'
    );
    paf_assert_equals(
        array(),
        paf_result_list($paf_result, 'saved'),
        'Autosave: no se declara ninguna clave guardada'
    );

    $GLOBALS['mock_is_post_autosave'] = false;
    $GLOBALS['mock_is_post_revision'] = true;
    $paf_result = paf_save(
        7005,
        array('atareao_episode_image' => '3333'),
        'valid_nonce',
        'Revisión'
    );

    paf_assert_equals(
        1234,
        get_post_meta(7005, paf_episode_image_key(), true),
        'Revisión: episode-image queda sin cambios'
    );
    paf_assert_equals(
        array(),
        paf_result_list($paf_result, 'saved'),
        'Revisión: no se declara ninguna clave guardada'
    );
}

// ---------------------------------------------------------------------------
// 6. Post type distinto de podcast.
// ---------------------------------------------------------------------------
echo "\n--- S6: post type distinto de podcast ---\n";

paf_reset();
paf_seed_post(7006, 'post');
$GLOBALS['mock_post_meta'][7006][paf_episode_image_key()] = 1234;

if (!paf_has_method('saveEpisodeMedia')) {
    paf_not_implemented('Escenario «Post type distinto»: Metaboxes::saveEpisodeMedia()');
} else {
    $paf_result = paf_save(
        7006,
        array('atareao_episode_image' => '2222'),
        'valid_nonce',
        'Post type distinto'
    );

    paf_assert_equals(
        1234,
        get_post_meta(7006, paf_episode_image_key(), true),
        'Post type distinto: episode-image queda sin cambios'
    );
    paf_assert_equals(
        array(),
        paf_result_list($paf_result, 'saved'),
        'Post type distinto: no se declara ninguna clave guardada'
    );
}

// ---------------------------------------------------------------------------
// 7. Limpiar un campo: metadato eliminado y feed con la portada del programa.
// ---------------------------------------------------------------------------
echo "\n--- S7: «Limpiar un campo» ---\n";

paf_reset();
$paf_post = paf_seed_post(7007);
$paf_cover = 'https://atareao.es/wp-content/uploads/2026/11/portada-programa.jpg';
paf_feed_context($paf_cover);
$GLOBALS['mock_post_meta'][7007]['mp3-url'] = 'https://cdn.example.com/ata7007.mp3';
$GLOBALS['mock_post_meta'][7007][paf_episode_image_key()] = 1234;

if (!paf_has_method('saveEpisodeMedia')) {
    paf_not_implemented('Escenario «Limpiar un campo»: Metaboxes::saveEpisodeMedia()');
} else {
    $paf_result = paf_save(
        7007,
        array('atareao_episode_image' => ''),
        'valid_nonce',
        'Limpiar un campo'
    );

    paf_assert_equals(
        '',
        get_post_meta(7007, paf_episode_image_key(), true),
        'Limpiar un campo: episode-image queda ausente'
    );
    paf_assert_true(
        !isset($GLOBALS['mock_post_meta'][7007][paf_episode_image_key()]),
        'Limpiar un campo: el metadato se borra del almacén, no se deja a 0'
    );
    paf_assert_true(
        in_array(
            array('post_id' => 7007, 'meta_key' => paf_episode_image_key()),
            isset($GLOBALS['mock_deleted_post_meta']) ? $GLOBALS['mock_deleted_post_meta'] : array(),
            true
        ),
        'Limpiar un campo: el guardado llama a delete_post_meta()'
    );

    if (class_exists('\Atareao\PodcastFeed')) {
        $paf_item = paf_xml(paf_item_xml(7007));
        $paf_image = paf_first($paf_item, 'itunes', 'image');
        paf_assert_equals(
            $paf_cover,
            $paf_image instanceof \DOMElement ? $paf_image->getAttribute('href') : '',
            'Limpiar un campo: el feed vuelve a la portada del programa'
        );
    } else {
        paf_not_implemented('PodcastFeed::itemElements() para el fallback de portada');
    }
}

// ---------------------------------------------------------------------------
// 8. Validación de la imagen: «Imagen válida» y «Adjunto inexistente o que no
//    es imagen».
// ---------------------------------------------------------------------------
echo "\n--- S8: validación de la imagen ---\n";

paf_reset();
$paf_png = paf_temp_file('png', "\x89PNG\r\n\x1a\n");
$paf_pdf = paf_temp_file('pdf', '%PDF-1.4');
paf_seed_attachment(1234, 'image/png', 'https://atareao.es/wp-content/uploads/2026/11/1234.png', $paf_png, 'PNG');
paf_seed_attachment(1235, 'application/pdf', 'https://atareao.es/wp-content/uploads/2026/11/1235.pdf', $paf_pdf, 'PDF');

if (!paf_has_method('isValidEpisodeImage')) {
    paf_not_implemented('Escenario «Imagen válida»: Metaboxes::isValidEpisodeImage()');
    paf_not_implemented('Escenario «Adjunto inexistente o que no es imagen»: Metaboxes::isValidEpisodeImage()');
} else {
    paf_assert_true(
        true === paf_validate('isValidEpisodeImage', 1234, 'Imagen válida'),
        'Imagen válida: un PNG existente es aceptado'
    );
    paf_assert_true(
        false === paf_validate('isValidEpisodeImage', 9999, 'Adjunto inexistente'),
        'Adjunto inexistente: un ID sin adjunto es rechazado'
    );
    paf_assert_true(
        false === paf_validate('isValidEpisodeImage', 1235, 'MIME no imagen'),
        'MIME no imagen: un PDF es rechazado'
    );
}

// ---------------------------------------------------------------------------
// 9. Validación de los capítulos: «Capítulos válidos» y «Capítulos no válidos».
// ---------------------------------------------------------------------------
echo "\n--- S9: validación de los capítulos ---\n";

paf_reset();
$paf_chapters_json = paf_temp_file('json', '{"version":"1.2.0","chapters":[{"startTime":0,"title":"Intro"}]}');
$paf_chapters_plain = paf_temp_file('txt', '{"chapters":[{"startTime":0,"title":"Intro"}]}');
$paf_no_chapters = paf_temp_file('json', '{"version":"1.2.0"}');
$paf_empty_chapters = paf_temp_file('json', '{"chapters":[]}');
$paf_broken_json = paf_temp_file('json', '{"chapters":[{"startTime":0,');
$paf_mp3 = paf_temp_file('mp3', 'ID3');

paf_seed_attachment(5678, 'application/json', 'https://atareao.es/wp-content/uploads/2026/11/5678.json', $paf_chapters_json);
paf_seed_attachment(5679, 'audio/mpeg', 'https://atareao.es/wp-content/uploads/2026/11/5679.mp3', $paf_mp3);
paf_seed_attachment(5680, 'application/json', 'https://atareao.es/wp-content/uploads/2026/11/5680.json', $paf_no_chapters);
paf_seed_attachment(5681, 'text/plain', 'https://atareao.es/wp-content/uploads/2026/11/5681.txt', $paf_broken_json);
paf_seed_attachment(5682, 'text/plain', 'https://atareao.es/wp-content/uploads/2026/11/5682.txt', $paf_chapters_plain);
paf_seed_attachment(5683, 'application/json', 'https://atareao.es/wp-content/uploads/2026/11/5683.json', $paf_empty_chapters);

if (!paf_has_method('isValidChaptersDocument')) {
    paf_not_implemented('Escenario «Capítulos válidos»: Metaboxes::isValidChaptersDocument()');
    paf_not_implemented('Escenario «Capítulos no válidos»: Metaboxes::isValidChaptersDocument()');
} else {
    paf_assert_true(
        true === paf_validate('isValidChaptersDocument', 5678, 'Capítulos válidos'),
        'Capítulos válidos: un JSON con chapters no vacío es aceptado'
    );
    paf_assert_true(
        true === paf_validate('isValidChaptersDocument', 5682, 'Capítulos válidos (text/plain)'),
        'Capítulos válidos: un text/plain con chapters no vacío es aceptado'
    );
    paf_assert_true(
        false === paf_validate('isValidChaptersDocument', 5679, 'Capítulos no válidos (MP3)'),
        'Capítulos no válidos: un MP3 es rechazado'
    );
    paf_assert_true(
        false === paf_validate('isValidChaptersDocument', 5680, 'Capítulos no válidos (sin chapters)'),
        'Capítulos no válidos: un JSON sin array chapters es rechazado'
    );
    paf_assert_true(
        false === paf_validate('isValidChaptersDocument', 5681, 'Capítulos no válidos (JSON roto)'),
        'Capítulos no válidos: un JSON inválido es rechazado'
    );
    paf_assert_true(
        false === paf_validate('isValidChaptersDocument', 5683, 'Capítulos no válidos (chapters vacío)'),
        'Capítulos no válidos: un array chapters vacío es rechazado'
    );
}

// ---------------------------------------------------------------------------
// 10. «Metadatos no expuestos por REST»: registro con register_post_meta.
// ---------------------------------------------------------------------------
echo "\n--- S10: registro de metadatos ---\n";

paf_reset();

if (!paf_has_method('registerEpisodeMediaMeta')) {
    paf_not_implemented('Escenario «Metadatos no expuestos por REST»: Metaboxes::registerEpisodeMediaMeta()');
} else {
    \Atareao\Metaboxes::registerEpisodeMediaMeta();
    $paf_registered = isset($GLOBALS['mock_registered_meta']['podcast'])
        ? $GLOBALS['mock_registered_meta']['podcast']
        : array();

    foreach (array(paf_episode_image_key(), paf_chapters_key()) as $paf_key) {
        paf_assert_true(
            isset($paf_registered[$paf_key]),
            'Metadatos no expuestos por REST: ' . $paf_key . ' está registrado para podcast'
        );

        if (!isset($paf_registered[$paf_key])) {
            continue;
        }

        $paf_args = $paf_registered[$paf_key];
        paf_assert_equals(
            'integer',
            isset($paf_args['type']) ? $paf_args['type'] : null,
            'Metadatos no expuestos por REST: ' . $paf_key . ' es de tipo integer'
        );
        paf_assert_true(
            isset($paf_args['single']) && true === $paf_args['single'],
            'Metadatos no expuestos por REST: ' . $paf_key . ' es single'
        );
        paf_assert_true(
            isset($paf_args['show_in_rest']) && false === $paf_args['show_in_rest'],
            'Metadatos no expuestos por REST: ' . $paf_key . ' no se expone en REST'
        );
        paf_assert_true(
            isset($paf_args['auth_callback']) && is_callable($paf_args['auth_callback']),
            'Metadatos no expuestos por REST: ' . $paf_key . ' declara auth_callback'
        );

        if (isset($paf_args['auth_callback']) && is_callable($paf_args['auth_callback'])) {
            $GLOBALS['mock_user_caps'] = array('edit_posts' => true);
            paf_assert_true(
                true === (bool) call_user_func($paf_args['auth_callback']),
                'Metadatos no expuestos por REST: ' . $paf_key . ' autoriza con edit_posts'
            );

            $GLOBALS['mock_user_caps'] = array('edit_posts' => false);
            paf_assert_true(
                false === (bool) call_user_func($paf_args['auth_callback']),
                'Metadatos no expuestos por REST: ' . $paf_key . ' deniega sin edit_posts'
            );
        }
    }
}

// ---------------------------------------------------------------------------
// 11. «Regresión de imagen» y «Regresión de capítulos» del feed.
// ---------------------------------------------------------------------------
echo "\n--- S11: regresión del feed ---\n";

paf_reset();
$paf_cover = 'https://atareao.es/wp-content/uploads/2026/11/portada-programa.jpg';
paf_feed_context($paf_cover);

$paf_episode_image = 'https://atareao.es/wp-content/uploads/2026/11/episodio-7101.jpg';
$paf_chapters_file = paf_temp_file('json', '{"chapters":[{"startTime":0,"title":"Intro"}]}');
$paf_missing_chapters = sys_get_temp_dir() . '/paf-borrado-' . uniqid('', true) . '.json';

paf_seed_attachment(8801, 'image/jpeg', $paf_episode_image, $paf_chapters_file, 'Portada del episodio');
paf_seed_attachment(8802, 'application/json', 'https://atareao.es/wp-content/uploads/2026/11/capitulos.json', $paf_chapters_file);
paf_seed_attachment(8803, 'application/json', 'https://atareao.es/wp-content/uploads/2026/11/capitulos-borrados.json', $paf_missing_chapters);

foreach (array(7101 => 'con imagen', 7102 => 'sin imagen propia', 7103 => 'con capítulos', 7104 => 'con capítulos borrados') as $paf_id => $paf_title) {
    paf_seed_post($paf_id);
    $GLOBALS['mock_posts'][count($GLOBALS['mock_posts']) - 1]->post_title = 'Episodio ' . $paf_title;
    $GLOBALS['mock_post_meta'][$paf_id]['mp3-url'] = 'https://cdn.example.com/ata' . $paf_id . '.mp3';
}
$GLOBALS['mock_post_meta'][7101][paf_episode_image_key()] = 8801;
$GLOBALS['mock_post_meta'][7103][paf_chapters_key()] = 8802;
$GLOBALS['mock_post_meta'][7104][paf_chapters_key()] = 8803;

if (!class_exists('\Atareao\PodcastFeed') || !method_exists('\Atareao\PodcastFeed', 'itemElements')) {
    paf_not_implemented('Escenario «Regresión de imagen»: PodcastFeed::itemElements()');
    paf_not_implemented('Escenario «Regresión de capítulos»: PodcastFeed::itemElements()');
} else {
    $paf_item = paf_xml(paf_item_xml(7101));
    $paf_image = paf_first($paf_item, 'itunes', 'image');
    paf_assert_equals(
        $paf_episode_image,
        $paf_image instanceof \DOMElement ? $paf_image->getAttribute('href') : '',
        'Regresión de imagen: con episode-image se usa su URL absoluta'
    );

    $paf_item = paf_xml(paf_item_xml(7102));
    $paf_image = paf_first($paf_item, 'itunes', 'image');
    paf_assert_equals(
        $paf_cover,
        $paf_image instanceof \DOMElement ? $paf_image->getAttribute('href') : '',
        'Regresión de imagen: sin episode-image se usa la portada del programa'
    );

    $paf_item = paf_xml(paf_item_xml(7103));
    $paf_chapters = paf_first($paf_item, 'podcast', 'chapters');
    paf_assert_true(
        $paf_chapters instanceof \DOMElement,
        'Regresión de capítulos: con documento accesible se emite podcast:chapters'
    );
    paf_assert_equals(
        'https://atareao.es/wp-content/uploads/2026/11/capitulos.json',
        $paf_chapters instanceof \DOMElement ? $paf_chapters->getAttribute('url') : '',
        'Regresión de capítulos: podcast:chapters declara la URL absoluta'
    );
    paf_assert_equals(
        'application/json+chapters',
        $paf_chapters instanceof \DOMElement ? $paf_chapters->getAttribute('type') : '',
        'Regresión de capítulos: podcast:chapters declara el media type'
    );

    $paf_item = paf_xml(paf_item_xml(7104));
    paf_assert_true(
        !paf_first($paf_item, 'podcast', 'chapters') instanceof \DOMElement,
        'Regresión de capítulos: un adjunto borrado no emite la etiqueta'
    );
}

// ---------------------------------------------------------------------------
// Cierre.
// ---------------------------------------------------------------------------
foreach ($GLOBALS['paf_tmp_files'] as $paf_tmp) {
    if (is_string($paf_tmp) && is_file($paf_tmp)) {
        @unlink($paf_tmp);
    }
}

echo "\nResumen: {$GLOBALS['paf_passes']} PASS, " . count($GLOBALS['paf_failures']) . " FAIL\n";

if (!empty($GLOBALS['paf_failures'])) {
    echo "\nFallos:\n";
    foreach ($GLOBALS['paf_failures'] as $paf_failure) {
        echo "  - {$paf_failure}\n";
    }

    exit(1);
}

exit(0);