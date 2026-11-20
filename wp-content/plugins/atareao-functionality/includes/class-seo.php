<?php

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class SEO
{
    /**
     * Tipos de contenido que declaran la entidad `Article`.
     *
     * Son los tipos editoriales del change `article-structured-data`. `page`
     * queda fuera a propósito (una página no es un artículo) y `podcast` también
     * queda fuera porque tendrá su propio change. Es la única lista de tipos del
     * fichero: no la dupliques.
     */
    private const ARTICLE_SCHEMA_TYPES = array('post', 'tutorial', 'capitulo', 'aplicacion', 'software');

    /**
     * Tipos de contenido que exponen un episodio de podcast en su singular.
     */
    private const PODCAST_EPISODE_TYPES = array('podcast');

    /**
     * Superficies de listado que declaran título propio (sin sufijo de marca).
     *
     * `blog` es la portada del blog (`is_home()`); el resto son los archivos de
     * tipo de contenido. Todos caben en el límite de la SERP y ninguno termina
     * en el sufijo de marca del sitio.
     */
    private const LISTING_TITLES = array(
        'blog'       => 'Blog de Linux, Docker, Rust y self-hosting',
        'tutorial'   => 'Tutoriales de Linux, Docker y self-hosting',
        'podcast'    => 'Podcast de Linux, software libre y self-hosting',
        'aplicacion' => 'Aplicaciones para Linux y open source',
        'software'   => 'Software para Linux y open source',
    );

    /**
     * Descripciones propias de la portada y de las cuatro plantillas de listado.
     *
     * Todas caen en el rango 120–160 caracteres y describen la superficie en
     * lugar de repetir el título.
     */
    private const LISTING_DESCRIPTIONS = array(
        'front'      => 'atareao con Linux: tutoriales, podcast y software libre. Guías de Linux, Docker, Rust, Python y self-hosting para dominar la terminal y automatizar tu día.',
        'blog'       => 'Artículos y tutoriales sobre Linux, Docker, Rust, Python y self-hosting. Aprende a dominar la terminal, desplegar contenedores y automatizar con scripts.',
        'tutorial'   => 'Tutoriales paso a paso de Linux, Docker, Rust, Python y self-hosting. Guías prácticas para dominar la terminal, los contenedores y la automatización.',
        'podcast'    => 'El podcast de atareao con Linux: episodios sobre Linux, software libre, self-hosting y desarrollo. Escucha cada semana las novedades y trucos en audio.',
        'aplicacion' => 'Aplicaciones para Linux, open source y tecnología. Descubre herramientas, alternativas libres y apps para tu día a día explicadas en detalle.',
        'software'   => 'Software para Linux, open source y tecnología. Descubre programas, utilidades y alternativas libres con sus ventajas y cómo instalarlos.',
    );

    /**
     * Longitud máxima del `<title>` en la SERP.
     */
    private const TITLE_MAX_LENGTH = 60;

    /**
     * Rango de la `<meta name="description">`.
     */
    private const DESCRIPTION_MIN_LENGTH = 120;
    private const DESCRIPTION_MAX_LENGTH = 160;

    /**
     * Relleno neutral para completar una descripción corta hasta el mínimo.
     */
    private const DESCRIPTION_PADDING = ' en atareao con Linux, el blog de tutoriales, podcast y software libre sobre Linux, Docker, Rust, Python y self-hosting.';

    /**
     * Año de corte de la cola obsoleta del sitemap (2010–2016).
     */
    private const LEGACY_CUTOFF_YEAR = 2017;

    /**
     * Identificador canónico del canal de YouTube del proyecto.
     */
    public const YOUTUBE_CHANNEL_ID = 'UCP8dTCaLNJFxfklb_GNqe6A';

    /**
     * Baseline de CTR de `docs/medicion-baseline.md` §3, en porcentaje.
     */
    public const CTR_BASELINE = array(
        'global'   => 1.4,
        'episodes' => 1.3,
        'yt-dlp'   => 0.2,
    );

    private static $fetchpriority_added = false;

    public static function init()
    {
        // Cadena de wp_get_attachment_image_attributes: 10 (lazy) -> 15 (alt) -> 20 (fetchpriority).
        add_filter('wp_get_attachment_image_attributes', array(__CLASS__, 'lazyLoading'), 10, 3);
        add_filter('wp_get_attachment_image_attributes', array(__CLASS__, 'fixEmptyAlt'), 15, 3);
        add_filter('wp_get_attachment_image_attributes', array(__CLASS__, 'optimizeLoading'), 20, 3);
        add_filter('wp_calculate_image_sizes', array(__CLASS__, 'imageSizes'), 10, 5);
        add_filter('jpeg_quality', array(__CLASS__, 'jpegQuality'), 10, 2);
        add_filter('wp_editor_set_quality', array(__CLASS__, 'jpegQuality'), 10, 2);
        add_filter('big_image_size_threshold', array(__CLASS__, 'bigImageThreshold'), 10, 4);
        add_filter('wp_content_img_tag', array(__CLASS__, 'optimizeContentImage'), 10, 3);
        add_action('wp_head', array(__CLASS__, 'addPaginationRelLinks'), 0);
        add_action('wp_head', array(__CLASS__, 'addHreflang'), 1);
        add_filter('document_title_parts', array(__CLASS__, 'filterHomepageTitle'));
        /*
         * Integración con The SEO Framework por los filtros que el plugin
         * instalado (TSF 5.2.1) dispara de verdad. `the_seo_framework_title` y
         * `the_seo_framework_description` NO existen: se eliminaron en este
         * change y la guardia de `tests/t10-residuals/verified-hooks.php`
         * impide reintroducirlos.
         */
        add_filter('the_seo_framework_title_from_generation', array(__CLASS__, 'filterGeneratedTitle'), 10, 2);
        add_filter('the_seo_framework_generated_description', array(__CLASS__, 'filterGeneratedDescription'), 10, 3);
        add_filter('the_seo_framework_use_title_branding', array(__CLASS__, 'filterUseTitleBranding'), 10, 3);
        add_filter('the_seo_framework_sitemap_exclude_ids', array(__CLASS__, 'filterExcludeOldPages'));
        add_filter('the_seo_framework_schema_graph_data', array(__CLASS__, 'addArticleSchema'), 10, 2);
        add_filter('option_atareao_social_youtube', array(__CLASS__, 'filterYouTubeOption'));
        add_filter('the_content', array(__CLASS__, 'appendEpisodeVideoLink'), 20);
        add_action('init', array(__CLASS__, 'ensureAuthorArchive'), 5);
        add_action('template_redirect', array(__CLASS__, 'redirectSitemapIndex'), 1);
        add_action('template_redirect', array(__CLASS__, 'redirectLegacyAuthorNicename'), 2);
    }

    /**
     * Añade el atributo loading="lazy" a las imágenes.
     *
     * Primer eslabón de la cadena de wp_get_attachment_image_attributes (10).
     */
    public static function lazyLoading($attr, $attachment, $size)
    {
        $attr['loading'] = 'lazy';
        return $attr;
    }

    public static function fixEmptyAlt($attr, $attachment, $size)
    {
        if (!empty($attr['alt'])) {
            return $attr;
        }

        $post = get_post();
        if ($post && !empty($post->post_title)) {
            $attr['alt'] = $post->post_title;
        }
        return $attr;
    }

    public static function optimizeLoading($attr, $attachment, $size)
    {
        if (self::$fetchpriority_added) {
            return $attr;
        }
        if (!is_singular() && !is_front_page()) {
            return $attr;
        }

        $attr['fetchpriority'] = 'high';
        unset($attr['loading']);
        self::$fetchpriority_added = true;
        return $attr;
    }

    public static function optimizeContentImage($filtered_image, $context, $attachment_id)
    {
        if (self::$fetchpriority_added) {
            return $filtered_image;
        }
        if (!is_singular() && !is_front_page()) {
            return $filtered_image;
        }

        self::$fetchpriority_added = true;
        $replaced = preg_replace(
            '/<img /',
            '<img fetchpriority="high" ',
            $filtered_image,
            1
        );
        if ($replaced) {
            $replaced = preg_replace('/\bloading="lazy"\s*/', '', $replaced);
        }
        return $replaced ?: $filtered_image;
    }

    public static function addPaginationRelLinks()
    {
        if (is_singular() || is_404() || is_search()) {
            return;
        }

        global $wp_query, $paged;
        if ($wp_query->max_num_pages <= 1) {
            return;
        }

        $current = $paged ? absint($paged) : 1;

        if ($current > 1) {
            $prev_link = get_previous_posts_page_link();
            if ($prev_link) {
                echo '<link rel="prev" href="' . esc_url($prev_link) . '" />' . "\n";
            }
        }

        if ($current < $wp_query->max_num_pages) {
            $next_link = get_next_posts_page_link();
            if ($next_link) {
                echo '<link rel="next" href="' . esc_url($next_link) . '" />' . "\n";
            }
        }
    }

    public static function addHreflang()
    {
        $locale = get_locale();
        $hreflang = str_replace('_', '-', $locale);
        $url = esc_url(get_permalink());
        echo '<link rel="alternate" hreflang="' . esc_attr($hreflang) . '" href="' . $url . '" />' . "\n";
        echo '<link rel="alternate" hreflang="x-default" href="' . $url . '" />' . "\n";
    }

    public static function filterHomepageTitle($title_parts)
    {
        if (!is_front_page() && !is_home()) {
            return $title_parts;
        }

        if (isset($title_parts['tagline'])) {
            $title_parts['tagline'] = 'Tutoriales, Podcast y Software Libre';
        }

        return $title_parts;
    }

    /**
     * Filtra el título que genera The SEO Framework.
     *
     * TSF 5.2.1 dispara `the_seo_framework_title_from_generation` con la firma
     * `( $title, ?array $args )`. En la ruta auto-determinada —la habitual en la
     * portada— `$args` llega como **`null`**
     * (`inc/classes/meta/title.class.php:237-241`), así que el segundo argumento
     * se declara anulable y **nunca** se desreferencia: la superficie se decide
     * por la petición actual, no por `$args`.
     *
     * La portada y los listados cubiertos sirven su título curado (sin sufijo de
     * marca); cualquier otra superficie devuelve el valor recibido intacto.
     *
     * @param string     $title Título compuesto por TSF.
     * @param array|null $args  Argumentos de generación de TSF (anulables; no se usan).
     * @return string
     */
    public static function filterGeneratedTitle($title, $args = null)
    {
        $surface = self::coveredSurface();
        if ('' === $surface) {
            return $title;
        }

        if ('front' === $surface) {
            return self::limitTitle((string) get_bloginfo('name'));
        }

        return self::limitTitle(self::LISTING_TITLES[$surface]);
    }

    /**
     * Filtra la descripción que genera The SEO Framework.
     *
     * TSF 5.2.1 dispara `the_seo_framework_generated_description` con la firma
     * `( $desc, ?array $args, string $type )`, donde `$type` es `search`,
     * `opengraph` o `twitter` (`inc/classes/meta/description.class.php:176-181`).
     * Los tres tipos reciben la misma descripción curada en la portada y en los
     * listados cubiertos, de modo que `<meta name="description">`, `og:description`
     * y `twitter:description` coinciden. Fuera de esas superficies se devuelve
     * el valor recibido intacto.
     *
     * @param string     $description Descripción compuesta por TSF.
     * @param array|null $args        Argumentos de TSF (anulables; no se usan).
     * @param string     $type        `search`, `opengraph` o `twitter`.
     * @return string
     */
    public static function filterGeneratedDescription($description, $args = null, $type = 'search')
    {
        $surface = self::coveredSurface();
        if ('' === $surface) {
            return $description;
        }

        return self::surfaceDescription($surface);
    }

    /**
     * Retira el sufijo de marca solo en las superficies cubiertas.
     *
     * TSF decide el sufijo en `use_branding()`
     * (`inc/classes/meta/title/conditions.class.php:172-176`) y lo expone por
     * `the_seo_framework_use_title_branding` con la firma
     * `( bool $use, ?array $args, bool $social )`. Se devuelve `false` en la
     * portada y en los listados cubiertos, y el valor recibido intacto en el
     * resto: un post, un tutorial o un episodio conservan su marca. No se toca
     * el ajuste global `title_rem_additions` ni ningún otro ajuste de TSF.
     *
     * @param bool       $use    Decisión de TSF.
     * @param array|null $args   Argumentos de TSF (anulables; no se usan).
     * @param bool       $social Si la generación es para redes sociales.
     * @return bool
     */
    public static function filterUseTitleBranding($use, $args = null, $social = false)
    {
        return '' === self::coveredSurface() ? $use : false;
    }

    /**
     * Superficie curada de la **petición actual**, o `''` si no está cubierta.
     *
     * La decisión se toma con los condicionales de WordPress y nunca con los
     * argumentos de TSF: en la ruta auto-determinada de la portada TSF llama al
     * filtro con `$args = null`, así que `$args` no puede decidir la superficie.
     *
     * @return string `front`, `blog`, `tutorial`, `podcast`, `aplicacion`, `software` o `''`.
     */
    private static function coveredSurface()
    {
        if (self::isForeignContext()) {
            return '';
        }

        if (is_front_page()) {
            return 'front';
        }

        if (is_home()) {
            return 'blog';
        }

        return self::archiveSurface();
    }

    /**
     * Si la petición actual queda fuera del front-end editorial.
     *
     * En admin, AJAX, feeds, REST, sitemaps de TSF y `robots` los filtros deben
     * ser inertes: se comprueba **antes** de resolver la superficie para no
     * alterar títulos ni descripciones de contextos ajenos.
     *
     * @return bool
     */
    private static function isForeignContext()
    {
        if (function_exists('is_admin') && is_admin()) {
            return true;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return true;
        }

        if (function_exists('is_feed') && is_feed()) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return true;
        }

        if (function_exists('is_robots') && is_robots()) {
            return true;
        }

        /*
         * El sitemap de TSF **no** se detecta por una query var:
         * `the_seo_framework_sitemap` no la registra ni la lee TSF 5.2.1. La
         * petición se enruta por URI en `parse_request` y la respuesta queda
         * memoizada en `Query::is_sitemap()`
         * (`inc/classes/sitemap/registry.class.php:57-117`). Por eso se
         * pregunta a la propia API, guardada para seguir funcionando si TSF no
         * está instalado o cambia su forma.
         */
        if (function_exists('tsf')) {
            $tsf = tsf();
            if (is_object($tsf) && method_exists($tsf, 'query')) {
                $tsf_query = $tsf->query();
                if (is_object($tsf_query)
                    && method_exists($tsf_query, 'is_sitemap')
                    && $tsf_query->is_sitemap()
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Superficie de archivo de tipo de contenido de la petición actual.
     *
     * @return string `tutorial`, `podcast`, `aplicacion`, `software` o `''`.
     */
    private static function archiveSurface()
    {
        foreach (array('tutorial', 'podcast', 'aplicacion', 'software') as $type) {
            if (is_post_type_archive($type)) {
                return $type;
            }
        }

        return '';
    }

    /**
     * Recorta un texto a la longitud máxima, cortando por palabra.
     *
     * @param string $text Texto de entrada.
     * @param int    $max  Longitud máxima.
     * @return string
     */
    public static function limitTitle($text, $max = self::TITLE_MAX_LENGTH)
    {
        $text = trim((string) preg_replace('/\s+/', ' ', (string) $text));
        if ('' === $text || mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');
        if (false !== $space && $space > 0) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t\n\r\0\x0B,;:-");
    }

    /**
     * Normaliza una descripción al rango 120–160 caracteres.
     *
     * Elimina etiquetas, colapsa espacios y recorta por palabra cuando supera el
     * máximo. Cuando el texto es más corto que el mínimo se completa con un
     * relleno neutral (nunca repitiendo el título, que se recibe aparte solo
     * para garantizar que la descripción no sea la misma cadena).
     *
     * @param string $text  Texto de entrada.
     * @param string $title Título de la superficie (para no repetirlo).
     * @return string
     */
    public static function clampDescription($text, $title = '')
    {
        $text = wp_strip_all_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if ('' === $text) {
            return '';
        }

        if (mb_strlen($text) > self::DESCRIPTION_MAX_LENGTH) {
            $text = self::limitTitle($text, self::DESCRIPTION_MAX_LENGTH);
        }

        if (mb_strlen($text) < self::DESCRIPTION_MIN_LENGTH) {
            $text = rtrim($text, " \t\n\r\0\x0B.") . self::DESCRIPTION_PADDING;
            if (mb_strlen($text) > self::DESCRIPTION_MAX_LENGTH) {
                $text = self::limitTitle($text, self::DESCRIPTION_MAX_LENGTH);
            }
        }

        if ('' !== $title && strcasecmp($text, (string) $title) === 0) {
            $text .= self::DESCRIPTION_PADDING;
            if (mb_strlen($text) > self::DESCRIPTION_MAX_LENGTH) {
                $text = self::limitTitle($text, self::DESCRIPTION_MAX_LENGTH);
            }
        }

        return $text;
    }

    /**
     * Descripción propia de una superficie de plantilla.
     *
     * @param string $surface `front`, `blog`, `tutorial`, `podcast`, `aplicacion` o `software`.
     * @return string
     */
    public static function surfaceDescription($surface)
    {
        $key = isset(self::LISTING_DESCRIPTIONS[$surface]) ? $surface : 'blog';

        return self::LISTING_DESCRIPTIONS[$key];
    }

    /**
     * Descripción de un contenido singular derivada de su extracto.
     *
     * @param int|WP_Post|null $post Entrada o identificador.
     * @return string
     */
    public static function singularDescription($post)
    {
        $resolved = $post instanceof \WP_Post ? $post : get_post($post);
        if (!$resolved instanceof \WP_Post) {
            return '';
        }

        return self::clampDescription(self::getDescription($resolved), (string) get_the_title($resolved));
    }

    /**
     * Excluye páginas antiguas y obsoletas del sitemap de The SEO Framework.
     *
     * A la lista fija de páginas de 2014–2017 se le suman las entradas
     * publicadas antes de `LEGACY_CUTOFF_YEAR` (cola 2010–2016) resueltas por
     * `legacyPostIds()`, que solo consulta cuando el núcleo está cargado.
     *
     * @param array $exclude_ids Identificadores que el framework ya excluía.
     * @return array
     */
    public static function filterExcludeOldPages($exclude_ids)
    {
        $old_page_ids = array(344, 3543, 7671, 9387, 9395, 9602, 9798, 9810, 9876, 10185);
        $legacy_ids = self::legacyPostIds();

        return array_values(array_unique(array_merge((array) $exclude_ids, $old_page_ids, $legacy_ids)));
    }

    /**
     * Identificadores de las entradas de la cola obsoleta (2010–2016).
     *
     * Sin el núcleo cargado (`get_posts()` no disponible) devuelve una lista
     * vacía: en el arnés procedural el filtro solo añade la lista fija.
     *
     * @return int[]
     */
    public static function legacyPostIds()
    {
        if (!function_exists('get_posts')) {
            return array();
        }

        $ids = get_posts(array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'date_query'     => array(
                array(
                    'before'    => self::LEGACY_CUTOFF_YEAR . '-01-01 00:00:00',
                    'inclusive' => false,
                ),
            ),
        ));

        return array_map('intval', is_array($ids) ? $ids : array());
    }

    /**
     * Indica si un año pertenece a la cola obsoleta del sitemap.
     *
     * @param int|string $year Año a valorar.
     * @return bool
     */
    public static function isLegacyYear($year)
    {
        return (int) $year < self::LEGACY_CUTOFF_YEAR;
    }

    /**
     * Destino canónico de `/sitemap_index.xml`, o `''` si no aplica.
     *
     * Se publica un único inventario (`sitemap.xml`); el nombre de índice falso
     * redirige 301 al inventario real para no servir contenido idéntico bajo dos
     * nombres.
     *
     * @param string $request URI de la petición.
     * @return string
     */
    public static function sitemapRedirectTarget($request)
    {
        $path = (string) wp_parse_url((string) $request, PHP_URL_PATH);
        $path = '/' . trim($path, '/');

        return '/sitemap_index.xml' === $path ? home_url('/sitemap.xml') : '';
    }

    /**
     * Redirige `/sitemap_index.xml` al inventario real.
     *
     * @return void
     */
    public static function redirectSitemapIndex()
    {
        $request = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $target = self::sitemapRedirectTarget($request);
        if ('' === $target) {
            return;
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    /**
     * Redirige el nicename heredado del autor a su archivo canónico.
     *
     * `/author/yyyxfsgj/` no resuelve a un archivo de autor: la rewrite del
     * núcleo busca un usuario con ese `user_nicename` y no existe, así que la
     * petición cae en un 404. Se detecta por el **path** y se emite un único
     * `301` hacia `get_author_posts_url()` del autor.
     *
     * La detección cubre también las subrutas válidas del archivo
     * (`/page/2/`, `/feed/`, …) y **preserva el sufijo y la query string**:
     * `/author/yyyxfsgj/page/2/` redirige a `/author/atareao/page/2/` y
     * `?feed=rss2` se conserva. Antes se comparaba el path exacto y esas URLs,
     * que antes del cambio respondían, se quedaban sin redirección.
     *
     * `ensureAuthorArchive()` **no** es la solución: la regla de rewrite ya
     * existe en el núcleo y no cambia el `user_nicename`, que es la causa del
     * 404. El dato lo corrige el paso idempotente
     * `wp atareao-author canonicalize`.
     *
     * No hay bucle: el callback solo actúa sobre el prefijo heredado y, además,
     * no redirige cuando el destino coincide ya con la petición.
     *
     * @return void
     */
    public static function redirectLegacyAuthorNicename()
    {
        if (!class_exists('\Atareao\AuthorCanonical')) {
            return;
        }

        $request = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $request_parts = wp_parse_url($request);
        $request_parts = is_array($request_parts) ? $request_parts : array();
        $raw_path = (string) (isset($request_parts['path']) ? $request_parts['path'] : '');
        $request_path = '/' . trim($raw_path, '/');
        $request_query = isset($request_parts['query']) ? (string) $request_parts['query'] : '';
        $keeps_trailing_slash = '' !== $raw_path && '/' === substr($raw_path, -1);

        $legacy_base = '/author/' . AuthorCanonical::LEGACY_NICENAME;
        $is_legacy = $request_path === $legacy_base || 0 === strpos($request_path, $legacy_base . '/');
        if (!$is_legacy) {
            return;
        }

        $author = get_user_by('id', AuthorCanonical::AUTHOR_ID);
        if (!is_object($author) || empty($author->ID)) {
            return;
        }

        /* Si el dato aún no se corrigió, el destino sería la propia petición. */
        if (AuthorCanonical::LEGACY_NICENAME === (string) $author->user_nicename) {
            return;
        }

        $canonical = get_author_posts_url((int) $author->ID, (string) $author->user_nicename);
        if ('' === $canonical) {
            return;
        }

        /* Sufijo vacío o `/page/2/`, `/feed/`…; la query viaja aparte. */
        $suffix = (string) substr($request_path, strlen($legacy_base));
        $target = rtrim($canonical, '/') . '/' . ltrim($suffix, '/');
        if ($keeps_trailing_slash && '/' !== substr($target, -1)) {
            $target .= '/';
        }
        if ('' !== $request_query) {
            $target .= '?' . $request_query;
        }

        /* Sin bucle: si la petición ya es la canónica, no se redirige. */
        if (home_url($request) === $target) {
            return;
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    /**
     * Añade las entidades de podcast, vídeo, `HowTo` y organización/persona al
     * grafo JSON-LD de The SEO Framework.
     *
     * The SEO Framework entrega el grafo como un array SECUENCIAL de entidades
     * (sin `@context` ni `@graph`); aquí solo se AÑADEN entidades al final o se
     * enriquece la `Person` de primer nivel, nunca se emite un segundo bloque.
     * En cuanto no concurren las condiciones editoriales se devuelve el grafo
     * intacto para que el framework componga su único bloque.
     *
     * @param mixed      $graph Grafo de entidades compuesto por el framework.
     * @param array|null $args  Argumentos del framework (`null` si se autodetermina).
     * @return mixed El grafo ampliado, o el original.
     */
    public static function addArticleSchema($graph, $args = null)
    {
        if (!is_array($graph)) {
            return $graph;
        }

        $graph = self::enrichPersonProfiles($graph);
        $graph = self::appendSiteOrganization($graph);
        $graph = self::appendPodcastSeries($graph);

        if (!is_singular()) {
            return $graph;
        }

        $post = self::resolveSchemaPost($args);
        if (!$post instanceof \WP_Post) {
            return $graph;
        }

        $graph = self::appendArticleEntity($graph, $post);
        $graph = self::appendPodcastEpisode($graph, $post);
        $graph = self::appendVideoObject($graph, $post);
        $graph = self::appendHowTo($graph, $post);

        return $graph;
    }

    /**
     * Añade la entidad `Article` al grafo para un contenido editorial singular.
     *
     * @param array    $graph Grafo de entidades.
     * @param \WP_Post $post  Entrada a describir.
     * @return array
     */
    private static function appendArticleEntity($graph, $post)
    {
        if (!self::isArticleSchemaPost($post)) {
            return $graph;
        }

        $webpage = self::findSchemaEntity($graph, 'WebPage');
        if (!is_array($webpage) || empty($webpage['@id'])) {
            return $graph;
        }

        $graph[] = self::buildArticleEntity($graph, $webpage, $post);

        return $graph;
    }

    /**
     * Identificador canónico de la serie de podcast del sitio.
     *
     * @return string
     */
    public static function podcastSeriesId()
    {
        return home_url('/podcast/') . '#/schema/PodcastSeries';
    }

    /**
     * Añade la entidad `PodcastSeries` en el archivo `/podcast/`.
     *
     * @param array $graph Grafo de entidades.
     * @return array
     */
    private static function appendPodcastSeries($graph)
    {
        if (!is_post_type_archive('podcast') || null !== self::findSchemaEntity($graph, 'PodcastSeries')) {
            return $graph;
        }

        $series = array(
            '@type' => 'PodcastSeries',
            '@id'   => self::podcastSeriesId(),
            'name'  => (string) get_bloginfo('name'),
            'url'   => (string) get_post_type_archive_link('podcast'),
        );

        $image = esc_url_raw((string) get_option('atareao_podcast_cover'));
        if ('' !== $image) {
            $series['image'] = $image;
        }

        $graph[] = $series;

        return $graph;
    }

    /**
     * Añade la entidad `PodcastEpisode` en el singular de un episodio.
     *
     * @param array    $graph Grafo de entidades.
     * @param \WP_Post $post  Entrada a describir.
     * @return array
     */
    private static function appendPodcastEpisode($graph, $post)
    {
        if (!in_array((string) $post->post_type, self::PODCAST_EPISODE_TYPES, true)) {
            return $graph;
        }
        if ('publish' !== (string) $post->post_status || post_password_required($post)) {
            return $graph;
        }
        if (null !== self::findSchemaEntity($graph, 'PodcastEpisode')) {
            return $graph;
        }

        $permalink = (string) get_permalink($post);
        $episode = array(
            '@type'         => 'PodcastEpisode',
            '@id'           => $permalink . '#/schema/PodcastEpisode',
            'name'          => (string) get_the_title($post),
            'url'           => $permalink,
            'datePublished' => (string) get_the_date('c', $post),
            'partOfSeries'  => array('@id' => self::podcastSeriesId()),
        );

        $number = (string) get_post_meta($post->ID, 'number', true);
        if ('' !== $number) {
            $episode['episodeNumber'] = (int) $number;
        }

        $audio = trim((string) get_post_meta($post->ID, 'mp3-url', true));
        if ('' !== $audio) {
            $episode['associatedMedia'] = array(
                '@type'      => 'MediaObject',
                'contentUrl' => $audio,
            );
        }

        $graph[] = $episode;

        return $graph;
    }

    /**
     * Añade la entidad `VideoObject` cuando el contenido embebe un vídeo.
     *
     * @param array    $graph Grafo de entidades.
     * @param \WP_Post $post  Entrada a describir.
     * @return array
     */
    private static function appendVideoObject($graph, $post)
    {
        if (null !== self::findSchemaEntity($graph, 'VideoObject')) {
            return $graph;
        }

        $video = self::embeddedVideo($post);
        if (null === $video) {
            return $graph;
        }

        $video_object = array(
            '@type'      => 'VideoObject',
            'name'       => (string) get_the_title($post),
            'url'        => $video['url'],
            'uploadDate' => (string) get_the_date('c', $post),
        );

        $description = self::singularDescription($post);
        if ('' !== $description) {
            $video_object['description'] = $description;
        }
        if ('' !== $video['thumbnail']) {
            $video_object['thumbnailUrl'] = $video['thumbnail'];
        }

        $graph[] = $video_object;

        return $graph;
    }

    /**
     * Añade la entidad `HowTo` cuando el tutorial expone pasos ordenados.
     *
     * @param array    $graph Grafo de entidades.
     * @param \WP_Post $post  Entrada a describir.
     * @return array
     */
    private static function appendHowTo($graph, $post)
    {
        if ('tutorial' !== (string) $post->post_type) {
            return $graph;
        }
        if ('publish' !== (string) $post->post_status || post_password_required($post)) {
            return $graph;
        }
        if (null !== self::findSchemaEntity($graph, 'HowTo')) {
            return $graph;
        }

        $steps = self::extractSteps((string) $post->post_content);
        if (empty($steps)) {
            return $graph;
        }

        $graph[] = array(
            '@type' => 'HowTo',
            'name'  => (string) get_the_title($post),
            'step'  => $steps,
        );

        return $graph;
    }

    /**
     * Extrae los pasos ordenados de una lista `<ol>` del contenido.
     *
     * @param string $content Contenido HTML.
     * @return array Lista de `HowToStep`, vacía si no hay pasos.
     */
    public static function extractSteps($content)
    {
        if (preg_match('#<ol[^>]*>(.*?)</ol>#is', (string) $content, $list) !== 1) {
            return array();
        }
        if (preg_match_all('#<li[^>]*>(.*?)</li>#is', $list[1], $items) === false) {
            return array();
        }

        $steps = array();
        $position = 0;
        foreach ($items[1] as $item) {
            $position++;
            $text = trim((string) preg_replace('/\s+/', ' ', wp_strip_all_tags($item)));
            if ('' === $text) {
                continue;
            }
            $steps[] = array(
                '@type'    => 'HowToStep',
                'position' => $position,
                'name'     => self::limitTitle($text),
                'text'     => $text,
            );
        }

        return $steps;
    }

    /**
     * Añade `sameAs` con los perfiles externos a toda `Person` descrita del grafo.
     *
     * Recorre el grafo en profundidad y enriquece cualquier entidad `Person` que
     * declare `name` o `url`, esté en el primer nivel o anidada (por ejemplo como
     * `publisher` del `WebSite`, que es el caso de la página de autor y de las
     * fichas). Las referencias puras —nodos `Person` con solo `@id`— se dejan
     * intactas: por diseño no llevan propiedades propias y añadírselas las
     * convertiría en nodos en línea duplicados.
     *
     * @param array $graph Grafo de entidades.
     * @return array
     */
    private static function enrichPersonProfiles($graph)
    {
        $profiles = self::socialProfiles();
        if (empty($profiles)) {
            return $graph;
        }

        return self::enrichPersons($graph, $profiles);
    }

    /**
     * Enriquecimiento recursivo de las `Person` descritas de un nodo del grafo.
     *
     * Desciende por listas y mapas; los escalares y las listas de cadenas se
     * devuelven sin cambios. La fusión es aditiva y deduplicada (ver
     * `mergeProfiles()`).
     *
     * @param mixed    $node     Nodo del grafo (entidad, lista o escalar).
     * @param string[] $profiles Perfiles externos del sitio.
     * @return mixed Nodo con las `Person` descritas enriquecidas.
     */
    private static function enrichPersons($node, $profiles)
    {
        if (!is_array($node)) {
            return $node;
        }

        if (self::isDescribedPerson($node)) {
            $node['sameAs'] = self::mergeProfiles(
                isset($node['sameAs']) ? $node['sameAs'] : array(),
                $profiles
            );
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = self::enrichPersons($value, $profiles);
            }
        }

        return $node;
    }

    /**
     * Determina si un nodo es una `Person` descrita.
     *
     * Una `Person` está descrita cuando declara `name` o `url`; una referencia
     * pura (solo `@id`, o `@id` y `@type`) no lo está.
     *
     * @param mixed $node Nodo del grafo.
     * @return bool
     */
    private static function isDescribedPerson($node)
    {
        if (!is_array($node) || !isset($node['@type'])) {
            return false;
        }

        $types = is_array($node['@type']) ? $node['@type'] : array($node['@type']);
        if (!in_array('Person', $types, true)) {
            return false;
        }

        return isset($node['name']) || isset($node['url']);
    }

    /**
     * Fusiona perfiles externos evitando duplicados.
     *
     * @param mixed    $existing Valor `sameAs` existente (cadena o lista).
     * @param string[] $profiles Perfiles a añadir.
     * @return string[]
     */
    private static function mergeProfiles($existing, $profiles)
    {
        $current = array();
        if (is_array($existing)) {
            foreach ($existing as $value) {
                if (is_string($value) && '' !== $value) {
                    $current[] = $value;
                }
            }
        } elseif (is_string($existing) && '' !== $existing) {
            $current[] = $existing;
        }

        foreach ($profiles as $profile) {
            if (!in_array($profile, $current, true)) {
                $current[] = $profile;
            }
        }

        return $current;
    }

    /**
     * Añade la entidad `Organization` del sitio en la portada.
     *
     * @param array $graph Grafo de entidades.
     * @return array
     */
    private static function appendSiteOrganization($graph)
    {
        if (!is_front_page() || null !== self::findSchemaEntity($graph, 'Organization')) {
            return $graph;
        }

        $organization = array(
            '@type' => 'Organization',
            '@id'   => home_url('/#/schema/Organization'),
            'name'  => (string) get_bloginfo('name'),
            'url'   => home_url('/'),
        );

        $logo = self::siteLogoUrl();
        if ('' !== $logo) {
            $organization['logo'] = $logo;
        }

        $profiles = self::socialProfiles();
        if (!empty($profiles)) {
            $organization['sameAs'] = array_values($profiles);
        }

        $graph[] = $organization;

        return $graph;
    }

    /**
     * Perfiles externos del sitio leídos de las opciones, normalizados.
     *
     * @return string[]
     */
    public static function socialProfiles()
    {
        $keys = array('github', 'youtube', 'ivoox', 'spotify', 'apple', 'telegram', 'x', 'mastodon', 'linkedin');
        $profiles = array();
        foreach ($keys as $key) {
            $url = esc_url_raw((string) get_option('atareao_social_' . $key));
            if ('youtube' === $key) {
                $url = self::normalizeYouTubeChannel($url);
            }
            if ('' !== $url) {
                $profiles[] = $url;
            }
        }

        return $profiles;
    }

    /**
     * Normaliza el enlace de YouTube al canal real del proyecto.
     *
     * Corrige el enlace heredado `youtube.com/c/atareao` al identificador de
     * canal canónico.
     *
     * @param string $url URL configurada.
     * @return string
     */
    public static function normalizeYouTubeChannel($url)
    {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }
        if (preg_match('#youtube\.com/c/atareao#i', $url) === 1) {
            return 'https://www.youtube.com/channel/' . self::YOUTUBE_CHANNEL_ID;
        }

        return $url;
    }

    /**
     * Filtro `option_atareao_social_youtube`: normaliza el canal al leerlo.
     *
     * @param mixed $url Valor almacenado.
     * @return string
     */
    public static function filterYouTubeOption($url)
    {
        return self::normalizeYouTubeChannel((string) $url);
    }

    /**
     * URL del logotipo del sitio.
     *
     * @return string
     */
    public static function siteLogoUrl()
    {
        $option = esc_url_raw((string) get_option('atareao_site_logo'));
        if ('' !== $option) {
            return $option;
        }

        if (function_exists('get_theme_mod')) {
            $id = (int) get_theme_mod('custom_logo');
            if ($id > 0 && function_exists('wp_get_attachment_image_url')) {
                $url = wp_get_attachment_image_url($id, 'full');
                if (is_string($url) && '' !== $url) {
                    return $url;
                }
            }
        }

        return '';
    }

    /**
     * URL canónica del vídeo embebido en un contenido, o `''` si no hay.
     *
     * @param string $content Contenido HTML.
     * @return string
     */
    public static function embeddedVideoUrl($content)
    {
        $id = self::youtubeVideoId($content);
        if ('' !== $id) {
            return 'https://www.youtube.com/watch?v=' . $id;
        }
        if (preg_match('#(?:player\.)?vimeo\.com/(?:video/)?(\d+)#i', (string) $content, $match) === 1) {
            return 'https://vimeo.com/' . $match[1];
        }

        return '';
    }

    /**
     * Identificador del vídeo de YouTube embebido en un contenido.
     *
     * @param string $content Contenido HTML.
     * @return string
     */
    private static function youtubeVideoId($content)
    {
        if (preg_match(
            '#(?:youtube\.com/(?:embed/|watch\?v=)|youtu\.be/)([A-Za-z0-9_-]{6,})#i',
            (string) $content,
            $match
        ) === 1) {
            return $match[1];
        }

        return '';
    }

    /**
     * Vídeo embebido de una entrada: URL canónica y miniatura.
     *
     * @param int|\WP_Post|null $post Entrada o identificador.
     * @return array{url:string,thumbnail:string}|null
     */
    public static function embeddedVideo($post)
    {
        $resolved = $post instanceof \WP_Post ? $post : get_post($post);
        if (!$resolved instanceof \WP_Post) {
            return null;
        }

        $url = self::embeddedVideoUrl((string) $resolved->post_content);
        if ('' === $url) {
            return null;
        }

        $thumbnail = '';
        $id = self::youtubeVideoId((string) $resolved->post_content);
        if ('' !== $id) {
            $thumbnail = 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
        }

        return array('url' => $url, 'thumbnail' => $thumbnail);
    }

    /**
     * Enlaza el vídeo propio del episodio al final del contenido.
     *
     * @param string $content Contenido filtrado.
     * @return string
     */
    public static function appendEpisodeVideoLink($content)
    {
        if (!is_singular()) {
            return $content;
        }

        $post = get_post();
        if (!$post instanceof \WP_Post
            || !in_array((string) $post->post_type, self::PODCAST_EPISODE_TYPES, true)) {
            return $content;
        }

        $video = self::embeddedVideo($post);
        if (null === $video) {
            return $content;
        }

        return $content
            . '<p class="episode-video-link"><a href="' . esc_url($video['url'])
            . '" rel="noopener">Ver el vídeo de este episodio</a></p>';
    }

    /**
     * Garantiza que el archivo de autor esté registrado y sea resoluble.
     *
     * `/author/{nicename}/` devolvía 404; se registra una regla de reescritura
     * explícita que resuelve el archivo por `author_name`. Requiere un
     * `flush_rewrite_rules()` tras el despliegue (documentado en
     * `docs/seo-visibility.md`).
     *
     * @return void
     */
    public static function ensureAuthorArchive()
    {
        if (function_exists('add_rewrite_rule')) {
            add_rewrite_rule('^author/([^/]+)/?$', 'index.php?author_name=$matches[1]', 'top');
        }
    }

    /**
     * Baseline de CTR de `docs/medicion-baseline.md` §3.
     *
     * @return array<string,float>
     */
    public static function ctrBaseline()
    {
        return self::CTR_BASELINE;
    }

    /**
     * Resuelve la entrada a la que corresponde el grafo.
     *
     * En producción el framework llama con `$args` a `null` y la entrada es la
     * consultada; si algún día llega un identificador explícito, se respeta.
     *
     * @param array|null $args Argumentos del framework.
     * @return \WP_Post|null Entrada resuelta, o `null` si no hay.
     */
    private static function resolveSchemaPost($args)
    {
        $post = is_array($args) && !empty($args['id']) ? get_post((int) $args['id']) : get_post();

        if (!$post instanceof \WP_Post) {
            $post = get_queried_object();
        }

        return $post instanceof \WP_Post ? $post : null;
    }

    /**
     * Determina si una entrada debe declarar la entidad de artículo.
     *
     * Solo los cinco tipos editoriales, publicados y sin contraseña. `page` y
     * `podcast` quedan fuera (el podcast tendrá su propio change).
     *
     * @param \WP_Post $post Entrada a valorar.
     * @return bool
     */
    private static function isArticleSchemaPost($post)
    {
        if (!in_array((string) $post->post_type, self::ARTICLE_SCHEMA_TYPES, true)) {
            return false;
        }
        if ('publish' !== (string) $post->post_status) {
            return false;
        }

        return !post_password_required($post);
    }

    /**
     * Localiza la primera entidad del grafo que declare un `@type` dado.
     *
     * El `@type` de una entidad puede ser una cadena o un array de cadenas, así
     * que ambos casos se contemplan. Se centraliza aquí el recorrido para no
     * repetirlo al buscar la `WebPage` y la `WebSite`.
     *
     * @param mixed  $graph Grafo de entidades.
     * @param string $type  Tipo schema.org buscado.
     * @return array|null La entidad encontrada, o `null`.
     */
    private static function findSchemaEntity($graph, $type)
    {
        if (!is_array($graph)) {
            return null;
        }

        foreach ($graph as $entity) {
            if (!is_array($entity) || !isset($entity['@type'])) {
                continue;
            }
            $types = is_array($entity['@type']) ? $entity['@type'] : array($entity['@type']);
            if (in_array($type, $types, true)) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * Construye la entidad `Article` a partir del grafo y de la entrada.
     *
     * Toda propiedad sin valor se omite: el `@type` es uniforme para los cinco
     * tipos editoriales (sin ramas por tipo) y las referencias al autor y a la
     * editorial se resuelven por `@id` siempre que el grafo ya las declare.
     *
     * @param array    $graph   Grafo completo (para localizar la editorial).
     * @param array    $webpage Entidad `WebPage` a la que se enlaza el artículo.
     * @param \WP_Post $post    Entrada a describir.
     * @return array Entidad `Article` lista para añadir al grafo.
     */
    private static function buildArticleEntity($graph, $webpage, $post)
    {
        $published = (string) get_the_date('c', $post);

        $article = array(
            '@type' => 'Article',
            'headline' => (string) get_the_title($post),
            'datePublished' => $published,
            'mainEntityOfPage' => array('@id' => $webpage['@id']),
        );

        $modified = (string) get_the_modified_date('c', $post);
        if ('' !== $modified && $modified !== $published) {
            $article['dateModified'] = $modified;
        }

        $author = self::resolveSchemaAuthor($webpage, $post);
        if (!empty($author)) {
            $article['author'] = $author;
        }

        $publisher = self::resolveSchemaPublisher($graph);
        if (!empty($publisher)) {
            $article['publisher'] = $publisher;
        }

        $image = self::resolveSchemaImage($post);
        if (!empty($image)) {
            $article['image'] = $image;
        }

        return $article;
    }

    /**
     * Resuelve el autor del artículo.
     *
     * Si la `WebPage` ya declara un autor con `@id` se referencia con exactamente
     * ese `@id` (sin duplicar `name`, `sameAs` ni `@type`). Si no, se construye
     * un `Person` de respaldo con el nombre del autor de la entrada y la URL de
     * su perfil; sin nombre disponible, se omite el autor.
     *
     * @param array    $webpage Entidad `WebPage` del grafo.
     * @param \WP_Post $post    Entrada a describir.
     * @return array Referencia o `Person` de respaldo, o `array()` si no hay.
     */
    private static function resolveSchemaAuthor($webpage, $post)
    {
        if (!empty($webpage['author']['@id'])) {
            return array('@id' => $webpage['author']['@id']);
        }

        $name = (string) get_the_author_meta('display_name', $post->post_author);
        if ('' === $name) {
            return array();
        }

        $author = array(
            '@type' => 'Person',
            'name' => $name,
        );

        $url = (string) get_author_posts_url($post->post_author);
        if ('' !== $url) {
            $author['url'] = $url;
        }

        return $author;
    }

    /**
     * Resuelve la entidad editorial del artículo.
     *
     * Si la `WebSite` del grafo declara `publisher` con `@id`, se referencia con
     * exactamente ese `@id` (sin duplicar `name`, `logo` ni `@type`). En caso
     * contrario se omite la propiedad.
     *
     * @param array $graph Grafo de entidades.
     * @return array Referencia `@id`, o `array()` si la editorial no se declara.
     */
    private static function resolveSchemaPublisher($graph)
    {
        $website = self::findSchemaEntity($graph, 'WebSite');
        if (is_array($website) && !empty($website['publisher']['@id'])) {
            return array('@id' => $website['publisher']['@id']);
        }

        return array();
    }

    /**
     * Describe la imagen destacada de la entrada como `ImageObject`.
     *
     * La URL es la absoluta del adjunto y las dimensiones solo se emiten cuando
     * se conocen. Sin imagen destacada o sin URL válida, la propiedad se omite.
     *
     * @param \WP_Post $post Entrada a describir.
     * @return array `ImageObject`, o `array()` si no hay imagen.
     */
    private static function resolveSchemaImage($post)
    {
        $thumbnail_id = get_post_thumbnail_id($post);
        if (!$thumbnail_id) {
            return array();
        }

        $src = wp_get_attachment_image_src($thumbnail_id, 'full');
        if (!is_array($src) || empty($src[0])) {
            return array();
        }

        $image = array(
            '@type' => 'ImageObject',
            'url' => (string) $src[0],
        );

        if (!empty($src[1])) {
            $image['width'] = (int) $src[1];
        }
        if (!empty($src[2])) {
            $image['height'] = (int) $src[2];
        }

        return $image;
    }

    /**
     * Resuelve la descripción SEO de un post desde cualquier plugin SEO,
     * con caché estática por ID dentro de la misma petición.
     *
     * @param int|WP_Post $post Post ID or object.
     * @return string
     */
    public static function getDescription($post)
    {
        $post_id = is_object($post) ? $post->ID : intval($post);
        static $cache = array();

        if (isset($cache[$post_id])) {
            return $cache[$post_id];
        }

        $meta = get_metadata('post', $post_id, '', true);

        $keys = array('_genesis_description', '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description');
        foreach ($keys as $key) {
            if (!empty($meta[$key][0])) {
                $cache[$post_id] = $meta[$key][0];
                return $cache[$post_id];
            }
        }

        $post_obj = get_post($post_id);
        $cache[$post_id] = $post_obj ? $post_obj->post_excerpt : '';
        return $cache[$post_id];
    }

    /**
     * Política de `sizes` para las imágenes responsive.
     *
     * Reproduce el comportamiento efectivo medido en producción, no una política
     * nueva: el tema registraba sus filtros al cargar functions.php y el plugin en
     * `init`, así que a igual prioridad el plugin ejecutaba después. En singulares
     * ganaba el callback del plugin y en listados el del tema; aquí se consolida
     * ese híbrido en un único callback.
     */
    public static function imageSizes($sizes, $size, $image_src, $image_meta, $attachment_id)
    {
        if (is_singular()) {
            return '(max-width: 768px) 100vw, 1200px';
        }
        return '(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw';
    }

    /**
     * Calidad de compresión de las imágenes (JPEG y reescrituras del editor).
     */
    public static function jpegQuality($quality, $context)
    {
        return 85;
    }

    /**
     * Umbral de reescalado de las imágenes grandes.
     */
    public static function bigImageThreshold($threshold, $imagesize, $file, $attachment_id)
    {
        return 2560;
    }

    public static function paginationRange($paged, $max)
    {
        $range = 3;
        $show_dots = false;
        $pages = array();

        if ($max <= ($range * 2) + 1) {
            for ($i = 1; $i <= $max; $i++) {
                $pages[] = $i;
            }
            return $pages;
        }

        $pages[] = 1;

        $start = max(2, $paged - $range);
        $end = min($max - 1, $paged + $range);

        if ($start > 2) {
            $pages[] = '...';
        }

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        if ($end < $max - 1) {
            $pages[] = '...';
        }

        $pages[] = $max;

        return $pages;
    }
}
