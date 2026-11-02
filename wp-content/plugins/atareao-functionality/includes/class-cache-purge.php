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
     * User-Agent de las peticiones de calentado.
     *
     * El front de producción bloquea (`444`) los UAs que casan
     * `curl|wget|python-requests|Go-http|ruby|scrapy`, así que el calentado usa
     * un identificador propio que no dispara ese filtro y permite auditar su
     * tráfico en los logs de Nginx.
     */
    private const WARMER_USER_AGENT = 'Atareao-Feed-Warmer/1.0';

    /**
     * Presupuesto total del calentado, en segundos.
     *
     * El calentado corre en el `shutdown` de una petición FPM tras entregar la
     * respuesta, pero el worker sigue vivo: sin una cota total, dos feeds de
     * hasta 20 s cada uno podrían superar el `max_execution_time` (30 s típico)
     * y matar el worker a medias, dejando el segundo feed sin calentar. Con este
     * presupuesto el peor caso queda acotado (spec: *bounded in time*).
     */
    private const WARM_BUDGET_SECONDS = 20;

    /**
     * Tiempo máximo de una única petición de calentado en FPM, en segundos.
     */
    private const WARM_TIMEOUT_SECONDS = 15;

    /**
     * URLs de feed acumuladas para calentar al final de la petición.
     *
     * Se vacía una vez el callback de `shutdown` las consume, de modo que un
     * guardado por lotes que dispare varios `post_updated` en la misma petición
     * calienta cada URL una sola vez.
     *
     * @var array<string, bool>
     */
    private static array $warmUrls = [];

    /**
     * Si el callback de `shutdown` ya se registró en esta petición.
     *
     * Evita acumular registros duplicados cuando varios eventos (p. ej. un
     * guardado por lotes) programan el calentado en la misma petición.
     */
    private static bool $warmScheduled = false;

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

        if (!self::firePurgeRequests($urls)) {
            return;
        }

        self::scheduleWarm($post);
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

        if (!self::firePurgeRequests($urls)) {
            return;
        }

        self::scheduleWarm($post_after);
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
            // 4b. Feed del archivo del tipo (ej: /podcast/feed/), que el pie
            //     del tema anuncia y que sirve el módulo del podcast.
            $urls[] = get_post_type_archive_feed_link($post->post_type);
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
     * La purga es *fail-closed*: sin secreto configurado no se emite ninguna
     * petición y se devuelve `false`, de modo que el llamante sabe que NO hubo
     * purga (y, por tanto, no debe calentar la caché de feeds).
     *
     * @param string[] $urls
     * @return bool `true` cuando las peticiones de purga se emitieron.
     */
    private static function firePurgeRequests(array $urls): bool
    {
        $secret = self::getSecret();
        if ($secret === '') {
            error_log('CachePurge: purga omitida, el secreto no está configurado.');
            return false;
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

        return true;
    }

    /**
     * URLs de FEED a calentar tras la purga, para el post dado.
     *
     * Solo feeds: el feed principal y el feed del archivo del tipo de contenido
     * cuando el tipo tiene archive. Nunca el set completo de purga, para no
     * provocar la estampida de re-cacheo ya observada al vaciar la caché entera.
     *
     * @param  \WP_Post $post Post publicado o actualizado.
     * @return string[] URLs de feed, filtradas y sin duplicados.
     */
    public static function getFeedUrlsToWarm(\WP_Post $post): array
    {
        $urls = [];

        // Feed principal.
        $urls[] = get_feed_link();

        // Feed del archivo del tipo de contenido (p. ej. /podcast/feed/), que el
        // pie del tema anuncia y que sirve el módulo del podcast.
        $post_type = get_post_type_object($post->post_type);
        if ($post_type && $post_type->has_archive) {
            $urls[] = get_post_type_archive_feed_link($post->post_type);
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Acumular las URLs de feed del post y programar el calentado una sola vez.
     *
     * Se registra un único callback en el hook `shutdown`, que WordPress dispara
     * cuando la respuesta ya se ha construido pero antes de cerrar la conexión.
     * El callback consume y vacía el acumulador, así que varias llamadas en la
     * misma petición (p. ej. un guardado por lotes) producen un solo calentado.
     *
     * @param \WP_Post $post Post publicado o actualizado.
     */
    private static function scheduleWarm(\WP_Post $post): void
    {
        foreach (self::getFeedUrlsToWarm($post) as $url) {
            self::$warmUrls[$url] = true;
        }

        // El callback se registra una sola vez por petición: si ya está armado,
        // basta con haber acumulado las URLs, que el callback ya existente
        // drenará.
        if (empty(self::$warmUrls) || self::$warmScheduled) {
            return;
        }

        self::$warmScheduled = true;
        add_action('shutdown', [self::class, 'warmScheduledFeeds'], 10, 0);
    }

    /**
     * Callback de `shutdown`: calienta las URLs de feed acumuladas.
     *
     * Vacía el acumulador antes de calentar y rearma la bandera, de modo que un
     * segundo callback registrado en la misma petición no repita el trabajo y
     * una petición nueva pueda volver a programar el calentado.
     */
    public static function warmScheduledFeeds(): void
    {
        self::$warmScheduled = false;

        if (empty(self::$warmUrls)) {
            return;
        }

        $urls = array_keys(self::$warmUrls);
        self::$warmUrls = [];

        self::warmFeeds($urls);
    }

    /**
     * Calentar la caché de front para las URLs de feed indicadas.
     *
     * Cada petición es un `GET` simple: sin cabecera `X-Cache-Purge` y sin
     * cookies, de modo que Nginx **compone y guarda** el feed en lugar de
     * saltarse la caché, y el siguiente lector obtiene un acierto en vez de
     * pagar la composición en frío. El calentado es best-effort: cualquier
     * fallo o `WP_Error` se ignora y nunca afecta al resultado de la publicación.
     *
     * En PHP-FPM, `fastcgi_finish_request()` se invoca **antes** de calentar, de
     * modo que el editor ya tiene su respuesta y no espera. Donde esa función no
     * existe (p. ej. WP-CLI) se cae a un `GET` no bloqueante y acotado a 0,1 s.
     *
     * @param string[] $urls URLs de feed a calentar.
     */
    public static function warmFeeds(array $urls): void
    {
        if (empty($urls)) {
            return;
        }

        $fpm = function_exists('fastcgi_finish_request');
        if ($fpm) {
            // La respuesta del editor ya está entregada; ahora protegemos al
            // worker para que el calentado no lo mate a medias.
            fastcgi_finish_request();
            @set_time_limit(0);
            ignore_user_abort(true);
        }

        // Presupuesto total: el bucle se corta al agotarse, así que el peor caso
        // queda acotado aunque haya varias URLs.
        $deadline = microtime(true) + self::WARM_BUDGET_SECONDS;
        foreach ($urls as $url) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                break;
            }

            // Best-effort: el resultado (respuesta o WP_Error) se descarta.
            wp_remote_get($url, self::warmRequestArgs($fpm, $remaining));
        }
    }

    /**
     * Argumentos de una petición de calentado, según runtime y presupuesto.
     *
     * Punto único de la política: en FPM la petición es bloqueante (para que
     * Nginx componga y **guarde** el feed) y su `timeout` nunca excede el tiempo
     * restante del presupuesto total, ni el tope por petición. Fuera de FPM
     * (p. ej. WP-CLI) se usa un `GET` no bloqueante y acotado a 0,1 s. Es una
     * función pura, de modo que ambas ramas son comprobables sin depender del
     * runtime.
     *
     * @param bool  $fpm       Si `fastcgi_finish_request()` está disponible.
     * @param float $remaining Segundos restantes del presupuesto total.
     * @return array{timeout: int|float, blocking: bool, user-agent: string}
     */
    public static function warmRequestArgs(bool $fpm, float $remaining): array
    {
        if (!$fpm) {
            return [
                'timeout'    => 0.1,
                'blocking'   => false,
                'user-agent' => self::WARMER_USER_AGENT,
            ];
        }

        return [
            'timeout'    => min(self::WARM_TIMEOUT_SECONDS, $remaining),
            'blocking'   => true,
            'user-agent' => self::WARMER_USER_AGENT,
        ];
    }
}