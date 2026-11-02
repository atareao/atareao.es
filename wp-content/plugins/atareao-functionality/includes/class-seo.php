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
        add_filter('the_seo_framework_title', array(__CLASS__, 'filterSeoFrameworkHomepageTitle'), 10, 2);
        add_filter('the_seo_framework_description', array(__CLASS__, 'filterSeoFrameworkBlogDescription'), 10, 2);
        add_filter('the_seo_framework_sitemap_exclude_ids', array(__CLASS__, 'filterExcludeOldPages'));
        add_filter('the_seo_framework_schema_graph_data', array(__CLASS__, 'addArticleSchema'), 10, 2);
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
     * Filtra el title de la homepage para The SEO Framework.
     * The SEO Framework sobreescribe document_title_parts, por lo que
     * necesitamos un hook específico para su generación de títulos.
     */
    public static function filterSeoFrameworkHomepageTitle($title, $post_id)
    {
        if (is_front_page()) {
            return 'atareao con Linux — Tutoriales, Podcast y Software Libre';
        } elseif (is_home()) {
            return 'Blog — atareao con Linux | Tutoriales, Podcast y Software Libre';
        }

        return $title;
    }

    /**
     * Filtra la meta description de la página del blog para The SEO Framework.
     * Por defecto genera "Últimas entradas: atareao con Linux…" que es muy genérica.
     */
    public static function filterSeoFrameworkBlogDescription($description, $post_id)
    {
        if (!is_home()) {
            return $description;
        }

        return 'Tutoriales de Linux, Docker, Rust, Python y self-hosting. Aprende a dominar la terminal, desplegar contenedores y automatizar con scripts. Más de 3000 artículos.';
    }

    /**
     * Excluye páginas antiguas y obsoletas del sitemap de The SEO Framework.
     * Estas páginas tienen contenido desactualizado de 2014-2017 que ya no
     * aporta valor SEO y lastra la calidad general del sitemap.
     */
    public static function filterExcludeOldPages($exclude_ids)
    {
        $old_page_ids = array(344, 3543, 7671, 9387, 9395, 9602, 9798, 9810, 9876, 10185);
        return array_merge($exclude_ids, $old_page_ids);
    }

    /**
     * Añade la entidad `Article` al grafo JSON-LD de The SEO Framework.
     *
     * The SEO Framework entrega el grafo como un array SECUENCIAL de entidades
     * (sin `@context` ni `@graph`); aquí solo se AÑADE una entidad al final,
     * nunca se reescriben las existentes. En cuanto no concurren las condiciones
     * editoriales (singular, tipo de contenido, estado público, sin contraseña)
     * o el grafo no declara la `WebPage` a la que enlazar, se devuelve el grafo
     * intacto para que el framework componga su único bloque.
     *
     * @param mixed      $graph Grafo de entidades compuesto por el framework.
     * @param array|null $args  Argumentos del framework (`null` si se autodetermina).
     * @return mixed El grafo con la entidad de artículo añadida, o el original.
     */
    public static function addArticleSchema($graph, $args = null)
    {
        if (!is_array($graph) || !is_singular()) {
            return $graph;
        }

        $post = self::resolveSchemaPost($args);
        if (!$post instanceof \WP_Post || !self::isArticleSchemaPost($post)) {
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
