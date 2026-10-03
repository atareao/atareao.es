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
     * Variable de entorno con el secreto de purga provisionado por podman secret.
     */
    private const SECRET_ENV = 'ATAREAO_PURGE_SECRET';

    /**
     * Variable de entorno con la ruta al fichero del secreto provisionado.
     */
    private const SECRET_FILE_ENV = 'ATAREAO_PURGE_SECRET_FILE';

    /**
     * Inicializar hooks.
     */
    public static function init(): void
    {
        add_action('transition_post_status', [self::class, 'onPublish'], 10, 3);
        add_action('post_updated', [self::class, 'onUpdate'], 10, 3);
    }

    /**
     * Leer el secreto de purga desde el entorno o desde el fichero provisionado.
     *
     * El valor nunca vive en el repositorio: lo provee `podman secret` mediante
     * las variables de entorno ATAREAO_PURGE_SECRET o ATAREAO_PURGE_SECRET_FILE.
     *
     * El secreto se normaliza con `trim()` de forma **incondicional**, con un
     * único punto de salida: un salto de línea u otro espacio en blanco final
     * no impide que el header coincida con la clave del `map` de Nginx. Sin
     * secreto (vacío tras `trim()`) se devuelve la cadena vacía, de modo que la
     * purga queda *fail-closed*.
     *
     * @return string Secreto normalizado, o cadena vacía si no está configurado.
     */
    public static function getSecret(): string
    {
        $secret = '';

        $env = getenv(self::SECRET_ENV);
        if (is_string($env) && trim($env) !== '') {
            $secret = $env;
        } else {
            $file = getenv(self::SECRET_FILE_ENV);
            if (is_string($file) && $file !== '' && is_readable($file)) {
                $value = file_get_contents($file);
                if (is_string($value)) {
                    $secret = $value;
                }
            }
        }

        return trim($secret);
    }

    /**
     * Comparar en tiempo constante el secreto recibido con el esperado.
     *
     * Comparación exigida por el spec (`hash_equals`, sin igualdad ordinaria).
     * Si no hay secreto configurado la purga NO se autentica (comportamiento
     * seguro) y se devuelve false sin revelar ningún valor.
     *
     * @param string $provided Secreto recibido en la petición.
     * @return bool
     */
    public static function verifySecret(string $provided): bool
    {
        $secret = self::getSecret();
        if ($secret === '') {
            return false;
        }

        return hash_equals($secret, $provided);
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
        // Solo en primera publicación (transición a 'publish')
        if ($new_status !== 'publish') {
            return;
        }
        if ($old_status === 'publish') {
            // Si ya estaba publicado, lo maneja onUpdate via post_updated
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
     * Disparar purga cuando se actualiza un post ya publicado.
     *
     * @param int      $post_id    ID del post.
     * @param \WP_Post $post_after  Post después de la actualización.
     * @param \WP_Post $post_before Post antes de la actualización.
     */
    public static function onUpdate(int $post_id, \WP_Post $post_after, \WP_Post $post_before): void
    {
        // Solo cuando se actualiza un post que ya estaba publicado (publish → publish)
        // Si el estado cambió, transition_post_status ya lo manejó en onPublish
        if ($post_before->post_status !== 'publish' || $post_after->post_status !== 'publish') {
            return;
        }
        // Ignorar revisiones
        if (wp_is_post_revision($post_id)) {
            return;
        }
        // Ignorar autoguardados
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $urls = self::getUrlsToPurge($post_after);
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
        $secret = self::getSecret();
        if ($secret === '') {
            error_log('CachePurge: purga omitida, el secreto no está configurado.');
            return;
        }

        $args = [
            'timeout'  => 0.1,
            'blocking' => false,
            'headers'  => [
                'X-Cache-Purge' => $secret,
            ],
        ];

        foreach ($urls as $url) {
            wp_remote_get($url, $args);
        }
    }
}