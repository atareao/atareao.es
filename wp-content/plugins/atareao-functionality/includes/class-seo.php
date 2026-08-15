<?php

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class SEO
{
    private static $fetchpriority_added = false;

    public static function init()
    {
        add_filter('wp_get_attachment_image_attributes', array(__CLASS__, 'fixEmptyAlt'), 15, 3);
        add_filter('wp_get_attachment_image_attributes', array(__CLASS__, 'optimizeLoading'), 20, 3);
        add_filter('wp_content_img_tag', array(__CLASS__, 'optimizeContentImage'), 10, 3);
        add_action('wp_head', array(__CLASS__, 'addPaginationRelLinks'), 0);
        add_action('wp_head', array(__CLASS__, 'addHreflang'), 1);
        add_filter('document_title_parts', array(__CLASS__, 'filterHomepageTitle'));
        add_filter('the_seo_framework_title', array(__CLASS__, 'filterSeoFrameworkHomepageTitle'), 10, 2);
        add_filter('the_seo_framework_description', array(__CLASS__, 'filterSeoFrameworkBlogDescription'), 10, 2);
        add_filter('the_seo_framework_sitemap_exclude_ids', array(__CLASS__, 'filterExcludeOldPages'));
        add_filter('wp_calculate_image_sizes', array(__CLASS__, 'heroImageSizes'), 10, 5);
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

    public static function heroImageSizes($sizes, $size, $image_src, $image_meta, $attachment_id)
    {
        if (is_singular()) {
            return '(max-width: 768px) 100vw, 1200px';
        }
        return $sizes;
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