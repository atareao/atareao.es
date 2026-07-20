<?php
/**
 * Cache Purge — Purga programática de la caché de Nginx al publicar contenido.
 *
 * Cuando se publica un post, envía peticiones con el header X-Cache-Purge
 * a todas las URLs que podrían mostrar ese contenido (portada, blog,
 * archivos de categoría, etc.), forzando a Nginx a regenerar la caché.
 *
 * @package Atareao_Functionality
 * @since   1.5.0
 */

namespace Atareao;

defined('ABSPATH') || exit;

class CachePurge
{
    /**
     * Secreto compartido con Nginx para autenticar la purga.
     * Debe coincidir con el valor en el mapa $purge_active de nginx/default.conf.
     */
    private const PURGE_SECRET = 'atareao_purge_2026';

    /**
     * Inicializar hooks.
     */
    public static function init(): void
    {
        add_action('transition_post_status', [self::class, 'onPublish'], 10, 3);
    }

    /**
     * Disparar purga cuando un post se publica por primera vez.
     *
     * @param string   $new_status Nuevo estado.
     * @param string   $old_status Estado anterior.
     * @param \WP_Post $post       Objeto del post.
     */
    public static function onPublish(string $new_status, string $old_status, \WP_Post $post): void
    {
        // Solo en primera publicación, no en actualizaciones
        if ($new_status !== 'publish') {
            return;
        }
        if ($old_status === 'publish') {
            return;
        }
        // Ignorar revisiones
        if (wp_is_post_revision($post->ID)) {
            return;
        }
        // Ignorar autoguardados
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $urls = self::getUrlsToPurge($post);
        if (empty($urls)) {
            return;
        }

        self::firePurgeRequests($urls);
    }

    /**
     * Recopilar todas las URLs que pueden mostrar este post.
     *
     * @param  \WP_Post $post
     * @return string[]
     */
    private static function getUrlsToPurge(\WP_Post $post): array
    {
        $urls = [];

        // 1. Portada (front-page.php)
        $urls[] = home_url('/');

        // 2. Blog page (si no es la misma que la portada)
        $blog_page_id = get_option('page_for_posts');
        if ($blog_page_id) {
            $urls[] = get_permalink($blog_page_id);
        }

        // 3. Feed RSS
        $urls[] = get_feed_link();

        // 4. Archivo del tipo de post (ej: /tutoriales/, /podcast/)
        $post_type = get_post_type_object($post->post_type);
        if ($post_type && $post_type->has_archive) {
            $urls[] = get_post_type_archive_link($post->post_type);
        }

        // 5. Categorías del post
        $categories = get_the_category($post->ID);
        foreach ($categories as $cat) {
            $urls[] = get_category_link($cat->term_id);
        }

        // 6. Tags del post
        $tags = get_the_tags($post->ID);
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $urls[] = get_tag_link($tag->term_id);
            }
        }

        // 7. Taxonomías personalizadas (ej: serie de podcast)
        $taxonomies = get_object_taxonomies($post->post_type, 'objects');
        foreach ($taxonomies as $tax) {
            if ($tax->public && !in_array($tax->name, ['category', 'post_tag'], true)) {
                $terms = wp_get_post_terms($post->ID, $tax->name);
                foreach ($terms as $term) {
                    if (!is_wp_error($term)) {
                        $urls[] = get_term_link($term);
                    }
                }
            }
        }

        // Eliminar duplicados y URLs vacías
        $urls = array_unique(array_filter($urls));

        return $urls;
    }

    /**
     * Disparar peticiones de purga a las URLs en segundo plano.
     *
     * @param string[] $urls
     */
    private static function firePurgeRequests(array $urls): void
    {
        $args = [
            'timeout'  => 0.1,
            'blocking' => false,
            'headers'  => [
                'X-Cache-Purge' => self::PURGE_SECRET,
            ],
        ];

        foreach ($urls as $url) {
            wp_remote_get($url, $args);
        }
    }
}