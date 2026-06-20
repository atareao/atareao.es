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

        switch ($method) {
            case 'tools/list':
                return self::successResponse(self::listTools(), $id);

            case 'tools/call':
                return self::callTool($params, $id);

            default:
                return self::errorResponse(-32601, 'Method not found', $id);
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
     * Call a specific tool
     *
     * @param array $params Request parameters.
     * @param mixed $id     Request ID.
     * @return \WP_REST_Response
     */
    private static function callTool($params, $id)
    {
        $tool_name = isset($params['name']) ? $params['name'] : '';
        $arguments = isset($params['arguments']) ? $params['arguments'] : array();

        switch ($tool_name) {
            case 'get_latest_posts':
                return self::successResponse(self::getLatestPosts(), $id);

            case 'get_post':
                if (!isset($arguments['id'])) {
                    return self::errorResponse(-32602, 'Missing required argument: id', $id);
                }
                $result = self::getPost(intval($arguments['id']));
                if (is_wp_error($result)) {
                    return self::errorResponse(-32000, $result->get_error_message(), $id);
                }
                return self::successResponse($result, $id);

            case 'search_posts':
                if (!isset($arguments['query'])) {
                    return self::errorResponse(-32602, 'Missing required argument: query', $id);
                }
                return self::successResponse(self::searchPosts($arguments['query']), $id);

            default:
                return self::errorResponse(-32601, 'Tool not found', $id);
        }
    }

    /**
     * Implement get_latest_posts
     */
    private static function getLatestPosts()
    {
        $args = array(
            'post_type'      => 'any',
            'posts_per_page' => 5,
            'post_status'    => 'publish',
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
     * Implement get_post
     */
    private static function getPost($post_id)
    {
        $post = get_post($post_id);

        if (!$post || 'publish' !== $post->post_status) {
            return new \WP_Error('not_found', 'Post not found');
        }

        $formatted = self::formatPost($post, true);
        return array('content' => array(array('type' => 'text', 'text' => wp_json_encode($formatted, JSON_PRETTY_PRINT))));
    }

    /**
     * Implement search_posts
     */
    private static function searchPosts($query_text)
    {
        $args = array(
            'post_type'      => 'any',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
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
     * Format a post object for MCP response
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
            $data['content'] = apply_filters('the_content', $post->post_content);
            $data['author']  = get_the_author_meta('display_name', $post->post_author);
            $featured_img    = get_the_post_thumbnail_url($post, 'full');
            if ($featured_img) {
                $data['featured_image_url'] = $featured_img;
            }
        }

        return $data;
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
