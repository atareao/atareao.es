<?php
/**
 * MCP Server Implementation — Tools for AI interaction
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class MCP
{
    /**
     * Número máximo de elementos devueltos por página.
     */
    private const MAX_PER_PAGE = 50;

    /**
     * Longitud máxima admitida para el argumento `query`.
     */
    private const MAX_QUERY_LENGTH = 200;

    /**
     * Longitud máxima del contenido de un post en la respuesta.
     *
     * Evita volcar entradas enormes en una sola petición; el resto de campos
     * (título, enlace, extracto) se conservan íntegros.
     */
    private const MAX_CONTENT_LENGTH = 65536;

    /**
     * Número de entradas devueltas por `get_latest_posts` si no se indica otro.
     */
    private const DEFAULT_LATEST = 5;

    /**
     * Inicializar
     */
    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'registerRoutes'));
        add_action('wp_head', array(__CLASS__, 'addDiscoveryMeta'));
    }

    /**
     * Add MCP discovery meta tag to head
     */
    public static function addDiscoveryMeta()
    {
        echo '<link rel="mcp-server" type="application/json" href="' . esc_url(rest_url('atareao/v1/mcp')) . '">' . "\n";
    }

    /**
     * Register REST API routes
     */
    public static function registerRoutes()
    {
        register_rest_route(
            'atareao/v1',
            '/mcp',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'handleRequest'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Handle MCP Request (JSON-RPC 2.0)
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response
     */
    public static function handleRequest($request)
    {
        $body = $request->get_json_params();

        if (empty($body) || !isset($body['jsonrpc']) || $body['jsonrpc'] !== '2.0') {
            return self::errorResponse(-32700, 'Parse error or invalid JSON-RPC');
        }

        $method = isset($body['method']) ? $body['method'] : '';
        $params = isset($body['params']) ? $body['params'] : array();
        $id     = isset($body['id']) ? $body['id'] : null;

        try {
            switch ($method) {
                case 'tools/list':
                    return self::successResponse(self::listTools(), $id);

                case 'tools/call':
                    return self::callTool($params, $id);

                default:
                    return self::errorResponse(-32601, 'Method not found', $id);
            }
        } catch (\Throwable $e) {
            // El detalle técnico queda solo en el registro del servidor; al
            // cliente se le devuelve un error genérico sin información interna.
            \Atareao\error_log('MCP internal error: ' . $e->getMessage());
            return self::errorResponse(-32603, 'Internal error', $id);
        }
    }

    /**
     * List available tools
     *
     * @return array
     */
    private static function listTools()
    {
        return array(
            'tools' => array(
                array(
                    'name'        => 'get_latest_posts',
                    'description' => 'Retrieves the 5 most recent posts from any category and post type.',
                    'inputSchema' => array(
                        'type'       => 'object',
                        'properties' => (object) array(),
                    ),
                ),
                array(
                    'name'        => 'get_post',
                    'description' => 'Retrieves a single post by its ID, including full content.',
                    'inputSchema' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id' => array(
                                'type'        => 'integer',
                                'description' => 'The unique ID of the post to retrieve.',
                            ),
                        ),
                        'required'   => array('id'),
                    ),
                ),
                array(
                    'name'        => 'search_posts',
                    'description' => 'Searches for posts across all public post types by a text query.',
                    'inputSchema' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'query' => array(
                                'type'        => 'string',
                                'description' => 'The search term or query string.',
                            ),
                        ),
                        'required'   => array('query'),
                    ),
                ),
            ),
        );
    }

    /**
     * Call a specific tool.
     *
     * Valida los argumentos de cada herramienta antes de ejecutar ninguna
     * consulta; un argumento inválido responde -32602 sin tocar la base.
     *
     * @param array $params Request parameters.
     * @param mixed $id     Request ID.
     * @return \WP_REST_Response
     */
    private static function callTool($params, $id)
    {
        $tool_name = isset($params['name']) ? $params['name'] : '';
        $arguments = isset($params['arguments']) && is_array($params['arguments'])
            ? $params['arguments']
            : array();

        switch ($tool_name) {
            case 'get_latest_posts':
                $limit = self::intArgument($arguments, 'limit', self::DEFAULT_LATEST, 1, self::MAX_PER_PAGE);
                if (is_wp_error($limit)) {
                    return self::errorResponse(-32602, $limit->get_error_message(), $id);
                }
                return self::successResponse(self::getLatestPosts($limit), $id);

            case 'get_post':
                $post_id = self::intArgument($arguments, 'id');
                if (is_wp_error($post_id)) {
                    return self::errorResponse(-32602, $post_id->get_error_message(), $id);
                }
                $result = self::getPost($post_id);
                if (is_wp_error($result)) {
                    return self::errorResponse(-32602, $result->get_error_message(), $id);
                }
                return self::successResponse($result, $id);

            case 'search_posts':
                if (!isset($arguments['query']) || !is_string($arguments['query'])) {
                    return self::errorResponse(-32602, 'Invalid params: query', $id);
                }
                $query_text = trim($arguments['query']);
                if ($query_text === '' || strlen($query_text) > self::MAX_QUERY_LENGTH) {
                    return self::errorResponse(-32602, 'Invalid params: query', $id);
                }
                $per_page = self::intArgument($arguments, 'per_page', 10, 1, self::MAX_PER_PAGE, true);
                $page     = self::intArgument($arguments, 'page', 1, 1, 1000, true);
                if (is_wp_error($per_page)) {
                    return self::errorResponse(-32602, $per_page->get_error_message(), $id);
                }
                if (is_wp_error($page)) {
                    return self::errorResponse(-32602, $page->get_error_message(), $id);
                }
                return self::successResponse(self::searchPosts($query_text, $per_page, $page), $id);

            default:
                return self::errorResponse(-32601, 'Tool not found', $id);
        }
    }

    /**
     * Valida y normaliza un argumento entero.
     *
     * @param array      $arguments Argumentos de la herramienta.
     * @param string     $key       Clave a leer.
     * @param int|null   $default   Valor por defecto si no está presente.
     * @param int        $min       Valor mínimo permitido.
     * @param int        $max       Valor máximo permitido.
     * @param bool       $clamp     Acota fuera de rango en vez de rechazar.
     * @return int|\WP_Error        Entero validado o error si es inválido.
     */
    private static function intArgument(
        $arguments,
        $key,
        $default = null,
        $min = 1,
        $max = PHP_INT_MAX,
        $clamp = false
    ) {
        if (!array_key_exists($key, $arguments)) {
            if ($default === null) {
                return new \WP_Error('invalid_params', 'Invalid params: ' . $key);
            }
            return (int) $default;
        }

        $value = $arguments[$key];
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return new \WP_Error('invalid_params', 'Invalid params: ' . $key);
        }

        $value = (int) $value;
        if ($value < $min || $value > $max) {
            if (!$clamp) {
                return new \WP_Error('invalid_params', 'Invalid params: ' . $key);
            }
            $value = max($min, min($max, $value));
        }

        return $value;
    }

    /**
     * Tipos de contenido públicos admitidos en los listados.
     *
     * @return string[]
     */
    private static function publicPostTypes()
    {
        $types = get_post_types(array('public' => true));
        if (!is_array($types) || empty($types)) {
            return array('post');
        }
        return array_values($types);
    }

    /**
     * Implement get_latest_posts.
     *
     * @param int $limit Número máximo de entradas a devolver.
     * @return array
     */
    private static function getLatestPosts($limit)
    {
        $args = array(
            'post_type'      => self::publicPostTypes(),
            'posts_per_page' => $limit,
            'post_status'    => 'publish',
            'post_password'  => '',
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $query = new \WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $posts[] = self::formatPost($post);
            }
        }

        return array('content' => array(array('type' => 'text', 'text' => wp_json_encode($posts, JSON_PRETTY_PRINT))));
    }

    /**
     * Implement get_post.
     *
     * Un post inexistente, no publicado o protegido por contraseña devuelve el
     * mismo error genérico, sin exponer su contenido ni su existencia.
     *
     * @param int $post_id Identificador de la entrada.
     * @return array|\WP_Error
     */
    private static function getPost($post_id)
    {
        $post = get_post($post_id);

        $is_public = $post
            && 'publish' === $post->post_status
            && empty($post->post_password)
            && !post_password_required($post);

        if (!$is_public) {
            return new \WP_Error('not_found', 'Post not found');
        }

        $formatted = self::formatPost($post, true);
        return array('content' => array(array('type' => 'text', 'text' => wp_json_encode($formatted, JSON_PRETTY_PRINT))));
    }

    /**
     * Implement search_posts.
     *
     * @param string $query_text Consulta saneada.
     * @param int    $per_page   Tamaño de página acotado.
     * @param int    $page       Página solicitada.
     * @return array
     */
    private static function searchPosts($query_text, $per_page, $page)
    {
        $args = array(
            'post_type'      => self::publicPostTypes(),
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
            'post_password'  => '',
            's'              => $query_text,
        );

        $query = new \WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $posts[] = self::formatPost($post);
            }
        }

        return array('content' => array(array('type' => 'text', 'text' => wp_json_encode($posts, JSON_PRETTY_PRINT))));
    }

    /**
     * Format a post object for MCP response.
     *
     * El contenido se obtiene con `get_post_field()` (nunca del crudo sin
     * comprobar contraseña) y se recorta al tope para no volcar entradas
     * enormes. No se exponen datos de usuario.
     *
     * @param object $post            Entrada de WordPress.
     * @param bool   $include_content Incluir el contenido completo.
     * @return array
     */
    private static function formatPost($post, $include_content = false)
    {
        $data = array(
            'id'      => $post->ID,
            'title'   => get_the_title($post),
            'date'    => $post->post_date,
            'type'    => $post->post_type,
            'slug'    => $post->post_name,
            'link'    => get_permalink($post),
            'excerpt' => get_the_excerpt($post),
        );

        if ($include_content) {
            $content = get_post_field('post_content', $post);
            $content = apply_filters('the_content', $content);
            $data['content'] = self::clampText((string) $content, self::MAX_CONTENT_LENGTH);

            $featured_img = get_the_post_thumbnail_url($post, 'full');
            if ($featured_img) {
                $data['featured_image_url'] = $featured_img;
            }
        }

        return $data;
    }

    /**
     * Recorta un texto al número máximo de bytes permitido.
     *
     * @param string $text Texto original.
     * @param int    $max  Longitud máxima.
     * @return string
     */
    private static function clampText($text, $max)
    {
        if (strlen($text) <= $max) {
            return $text;
        }
        return substr($text, 0, $max);
    }

    /**
     * Helper for JSON-RPC success response
     */
    private static function successResponse($result, $id)
    {
        return new \WP_REST_Response(
            array(
                'jsonrpc' => '2.0',
                'result'  => $result,
                'id'      => $id,
            ),
            200
        );
    }

    /**
     * Helper for JSON-RPC error response
     */
    private static function errorResponse($code, $message, $id = null)
    {
        return new \WP_REST_Response(
            array(
                'jsonrpc' => '2.0',
                'error'   => array(
                    'code'    => $code,
                    'message' => $message,
                ),
                'id'      => $id,
            ),
            200 // JSON-RPC errors typically return 200 at HTTP level if transport succeeded
        );
    }
}
