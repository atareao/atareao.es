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
     * Ventana, en segundos, del rate limiting por IP.
     */
    public const RATE_LIMIT_WINDOW = 60;

    /**
     * Número máximo de peticiones por IP dentro de la ventana.
     */
    public const RATE_LIMIT_MAX_REQUESTS = 60;

    /**
     * Ruta limpia y pública del transporte MCP.
     */
    public const CLEAN_ENDPOINT_PATH = '/mcp';

    /**
     * Ruta REST a la que se normaliza la ruta limpia.
     */
    public const CLEAN_ENDPOINT_ROUTE = '/atareao/v1/mcp';

    /**
     * Clave de `_meta` con la versión de protocolo por petición.
     */
    public const META_PROTOCOL_VERSION = 'io.modelcontextprotocol/protocolVersion';

    /**
     * Clave de `_meta` con las capacidades del cliente.
     */
    public const META_CLIENT_CAPABILITIES = 'io.modelcontextprotocol/clientCapabilities';

    /**
     * Clave de `_meta` con la identidad del servidor.
     */
    public const META_SERVER_INFO = 'io.modelcontextprotocol/serverInfo';

    /**
     * Tiempo de vida (ms) de la caché de los listados modernos.
     */
    public const TOOLS_LIST_TTL_MS = 3600000;

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
     * Metas públicas expuestas por tipo de contenido.
     *
     * Solo se exponen las claves listadas aquí (más `post_views_count` en todos
     * los tipos). Las claves internas (prefijo `_`) nunca se incluyen.
     *
     * @var array<string, string[]>
     */
    private const PUBLIC_METAS = array(
        'podcast'  => array('mp3-url', 'number', 'season'),
        'capitulo' => array('numero-capitulo', 'tutorial-id'),
    );

    /**
     * Tipos de contenido del dominio solicitables en el argumento `post_type`.
     *
     * Lista permitida fija, no dependiente del runtime (`get_post_types()`).
     * Coincide con el `enum` del cliente WebMCP. Los tipos públicos que no
     * pertenecen al dominio (por ejemplo `page`) no pueden filtrarse aunque
     * existan en el runtime.
     *
     * @var string[]
     */
    private const ALLOWED_POST_TYPES = array(
        'post',
        'tutorial',
        'capitulo',
        'aplicacion',
        'podcast',
        'software',
    );

    /**
     * Inicializar
     */
    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'registerRoutes'));
        add_action('wp_head', array(__CLASS__, 'addDiscoveryMeta'));
        add_action('parse_request', array(__CLASS__, 'routeCleanEndpoint'), 5);

        if (function_exists('remove_filter')) {
            // La REST API de WordPress emite CORS con credenciales y métodos con
            // efectos; para un servicio público de solo lectura los sustituimos.
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        }
        add_filter('rest_pre_serve_request', array(__CLASS__, 'sendCorsHeaders'), 10, 3);
        add_filter('rest_post_dispatch', array(__CLASS__, 'sendAllowHeader'), 20, 3);
    }

    /**
     * Emite las cabeceras CORS de un servicio público de solo lectura.
     *
     * Permite la consulta anónima desde cualquier origen para POST y resuelve
     * el preflight OPTIONS, sin habilitar credenciales ni métodos con efectos.
     *
     * @param mixed            $served Respuesta ya servida (false por defecto).
     * @param \WP_REST_Response $result Respuesta REST.
     * @param \WP_REST_Request  $request Petición REST.
     * @return mixed
     */
    public static function sendCorsHeaders($served, $result, $request)
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
        foreach (self::corsHeaders($origin) as $name => $value) {
            header($name . ': ' . $value);
        }

        return $served;
    }

    /**
     * Cabeceras CORS de solo lectura, como mapa nombre => valor.
     *
     * Admite la consulta anónima desde cualquier origen para POST/OPTIONS, sin
     * habilitar credenciales ni métodos con efectos, y admite las cabeceras del
     * protocolo MCP (además de `Content-Type`, `Accept` y `Authorization`).
     * `Vary: Origin` evita que las cachés mezclen orígenes distintos.
     *
     * @param string $origin Origen de la petición (vacío ⇒ comodín).
     * @return array
     */
    public static function corsHeaders($origin = ''): array
    {
        $allow = $origin !== '' ? $origin : '*';

        return array(
            'Access-Control-Allow-Origin'  => $allow,
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, MCP-Protocol-Version, '
                . 'Mcp-Method, Mcp-Name, Authorization',
            'Vary'                         => 'Origin',
        );
    }

    /**
     * Completa la cabecera `Allow` de la respuesta al preflight del endpoint.
     *
     * WordPress core añade `Allow` en `rest_post_dispatch`
     * (`rest_send_allow_header`, prioridad 10) a partir de los métodos
     * declarados en la ruta, que solo registra `CREATABLE` (POST). El preflight
     * `OPTIONS` lo cortocircuita `rest_handle_options_request` en
     * `rest_pre_dispatch`, así que la respuesta acababa anunciando `Allow: POST`
     * mientras el CORS anunciaba `POST, OPTIONS`. Este filtro posterior
     * (prioridad 20) lo completa. El tercer argumento `$replace = true` es
     * imprescindible: `WP_REST_Response::header()` concatena el valor existente
     * con `', '` cuando no se pide reemplazo (y su valor por defecto es `true`),
     * de modo que sin ese tercer argumento la respuesta habría quedado como
     * `Allow: POST, POST, OPTIONS` en vez de `POST, OPTIONS`.
     *
     * @param mixed             $response Respuesta REST.
     * @param \WP_REST_Server   $server   Servidor REST.
     * @param \WP_REST_Request  $request  Petición REST.
     * @return mixed
     */
    public static function sendAllowHeader($response, $server, $request)
    {
        if (!is_object($request) || !method_exists($request, 'get_route')) {
            return $response;
        }

        $method = method_exists($request, 'get_method') ? strtoupper((string) $request->get_method()) : '';
        if ($method !== 'OPTIONS') {
            return $response;
        }

        $route = '/' . trim((string) $request->get_route(), '/');
        if ($route !== self::CLEAN_ENDPOINT_ROUTE) {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'header')) {
            $response->header('Allow', 'POST, OPTIONS', true);
        }

        return $response;
    }

    /**
     * Add MCP discovery meta tag to head
     */
    public static function addDiscoveryMeta()
    {
        echo '<link rel="mcp-server" type="application/json" href="' . esc_url(home_url(self::CLEAN_ENDPOINT_PATH)) . '">' . "\n";
        echo '<link rel="ard" type="application/json" href="' . esc_url(MCPDiscovery::ardUrl()) . '">' . "\n";
    }

    /**
     * Whether a request path is the clean MCP endpoint.
     *
     * @param string $path Request path.
     * @return bool
     */
    public static function isCleanEndpointPath($path): bool
    {
        $normalized = '/' . trim((string) $path, '/');

        return $normalized === self::CLEAN_ENDPOINT_PATH;
    }

    /**
     * How the clean endpoint answers a given HTTP method.
     *
     * @param string $method HTTP method.
     * @return string Either 'transport' or 'method_not_allowed'.
     */
    public static function cleanEndpointMethodPlan($method): string
    {
        $method = strtoupper((string) $method);
        if ($method === 'GET' || $method === 'DELETE') {
            return 'method_not_allowed';
        }

        return 'transport';
    }

    /**
     * Normalize a clean-endpoint request onto the existing REST route.
     *
     * Feeds both the WP query var (what `rest_api_loaded()` dispatches on) and
     * the standard `rest_route` request channel (what the anonymous gate reads),
     * so the alias reaches the one existing transport without touching it.
     *
     * @param object|null $wp WP environment object.
     * @return void
     */
    public static function normalizeCleanEndpoint($wp = null): void
    {
        $_GET['rest_route'] = self::CLEAN_ENDPOINT_ROUTE;
        if (is_object($wp) && isset($wp->query_vars) && is_array($wp->query_vars)) {
            $wp->query_vars['rest_route'] = self::CLEAN_ENDPOINT_ROUTE;
        }
    }

    /**
     * Normalize the clean endpoint onto the REST transport during parse_request.
     *
     * Runs before `rest_api_loaded()` (priority 5 < 10). A request to the clean
     * endpoint with a method reserved for streaming (`GET`/`DELETE`) is answered
     * with `405`; any other method is normalized onto the existing route.
     *
     * @param object|null $wp WP environment object.
     * @return void
     */
    public static function routeCleanEndpoint($wp = null)
    {
        $path = self::currentRequestPath();
        if (!self::isCleanEndpointPath($path)) {
            return;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
        if (self::cleanEndpointMethodPlan($method) === 'method_not_allowed') {
            status_header(405);
            header('Allow: POST, OPTIONS');
            exit;
        }

        self::normalizeCleanEndpoint($wp);
    }

    /**
     * Path of the current request, without the query string.
     *
     * @return string
     */
    private static function currentRequestPath(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) ? $path : '';
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
                'args'                => array(
                    'jsonrpc' => array(
                        'validate_callback' => array(__CLASS__, 'validateJsonrpcArg'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'method'  => array(
                        'validate_callback' => array(__CLASS__, 'validateMethodArg'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'params'  => array(
                        'validate_callback' => array(__CLASS__, 'validateParamsArg'),
                        'sanitize_callback' => array(__CLASS__, 'sanitizeEnvelopeIdentity'),
                    ),
                    'id'      => array(
                        'validate_callback' => array(__CLASS__, 'validateIdArg'),
                        'sanitize_callback' => array(__CLASS__, 'sanitizeEnvelopeIdentity'),
                    ),
                ),
            )
        );
    }

    /**
     * Valida el argumento de sobre `jsonrpc`.
     *
     * @param mixed $value Valor recibido.
     * @return bool
     */
    public static function validateJsonrpcArg($value)
    {
        return is_string($value);
    }

    /**
     * Valida el argumento de sobre `method`.
     *
     * @param mixed $value Valor recibido.
     * @return bool
     */
    public static function validateMethodArg($value)
    {
        return is_string($value);
    }

    /**
     * Valida el argumento de sobre `params`.
     *
     * @param mixed $value Valor recibido.
     * @return bool
     */
    public static function validateParamsArg($value)
    {
        return is_array($value) || is_object($value);
    }

    /**
     * Valida el argumento de sobre `id`.
     *
     * @param mixed $value Valor recibido.
     * @return bool
     */
    public static function validateIdArg($value)
    {
        return is_int($value) || is_string($value) || $value === null;
    }

    /**
     * Sanitiza un argumento de sobre que no requiere transformación.
     *
     * @param mixed $value Valor recibido.
     * @return mixed
     */
    public static function sanitizeEnvelopeIdentity($value)
    {
        return $value;
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

        $rate_error = self::checkRateLimit();
        if (is_wp_error($rate_error)) {
            return self::rateLimitedResponse($id);
        }

        try {
            if (self::isModernRequest($method, $body, $request)) {
                return self::handleModernRequest($request, $body, $method, $params, $id);
            }

            return self::handleLegacyRequest($method, $params, $id);
        } catch (\Throwable $e) {
            // El detalle técnico queda solo en el registro del servidor; al
            // cliente se le devuelve un error genérico sin información interna.
            error_log('MCP internal error: ' . $e->getMessage());
            return self::errorResponse(-32603, 'Internal error', $id);
        }
    }

    /**
     * Comprueba el rate limiting por IP de la petición actual.
     *
     * Usa una ventana fija de `RATE_LIMIT_WINDOW` segundos sobre un transient
     * cuya clave es un hash de la IP; nunca confía en cabeceras de proxy.
     *
     * @return true|\WP_Error `true` si la petición está permitida.
     */
    private static function checkRateLimit()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        $key = 'atareao_mcp_rl_' . hash('sha256', $ip);

        $count = (int) get_transient($key);
        if ($count >= self::RATE_LIMIT_MAX_REQUESTS) {
            return new \WP_Error('rate_limited', 'Rate limit exceeded');
        }

        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
        return true;
    }

    /**
     * Respuesta HTTP 429 con `Retry-After` para las peticiones limitadas.
     *
     * @param mixed $id Identificador JSON-RPC.
     * @return \WP_REST_Response
     */
    private static function rateLimitedResponse($id)
    {
        $response = new \WP_REST_Response(
            array(
                'jsonrpc' => '2.0',
                'error'   => array(
                    'code'    => -32000,
                    'message' => 'Rate limit exceeded',
                ),
                'id'      => $id,
            ),
            429
        );
        $response->header('Retry-After', (string) self::RATE_LIMIT_WINDOW);

        return $response;
    }

    /**
     * Whether the request selects the modern stateless era.
     *
     * La mera presencia de `_meta` NO basta para decidir la era: `_meta` es un
     * campo general del protocolo y los clientes legacy lo envían con claves
     * como `progressToken`, sin ningún metadato moderno. Solo se considera
     * moderna la petición que declara la versión de protocolo en `_meta` o que
     * llega con la cabecera `Mcp-Method`.
     *
     * @param string                 $method  JSON-RPC method.
     * @param array                  $body    Decoded request body.
     * @param \WP_REST_Request|null  $request REST request.
     * @return bool
     */
    private static function isModernRequest($method, $body, $request = null): bool
    {
        if ($method === 'initialize') {
            return false;
        }

        $meta = isset($body['params']['_meta']) && is_array($body['params']['_meta'])
            ? $body['params']['_meta']
            : array();

        if (isset($meta[self::META_PROTOCOL_VERSION])) {
            return true;
        }

        return self::requestHeader($request, 'Mcp-Method') !== null;
    }

    /**
     * Serve a legacy-era request (keeps the pre-change behaviour).
     *
     * @param string $method JSON-RPC method.
     * @param array  $params Request params.
     * @param mixed  $id     JSON-RPC id.
     * @return \WP_REST_Response
     */
    private static function handleLegacyRequest($method, $params, $id)
    {
        switch ($method) {
            case 'initialize':
                $offered = isset($params['protocolVersion']) ? $params['protocolVersion'] : null;
                return self::successResponse(self::initializeResult($offered), $id);

            case 'tools/list':
                return self::successResponse(self::listTools(), $id);

            case 'tools/call':
                return self::callTool($params, $id);

            default:
                return self::errorResponse(-32601, 'Method not found', $id);
        }
    }

    /**
     * Serve a modern-era (stateless) request.
     *
     * @param \WP_REST_Request $request REST request.
     * @param array            $body    Decoded body.
     * @param string           $method  JSON-RPC method.
     * @param array            $params  Request params.
     * @param mixed            $id      JSON-RPC id.
     * @return \WP_REST_Response
     */
    private static function handleModernRequest($request, $body, $method, $params, $id)
    {
        $meta = isset($params['_meta']) && is_array($params['_meta']) ? $params['_meta'] : array();

        if (!isset($meta[self::META_PROTOCOL_VERSION]) || !isset($meta[self::META_CLIENT_CAPABILITIES])) {
            return self::modernErrorResponse(-32602, 'Invalid params', 400, $id);
        }

        $requested = $meta[self::META_PROTOCOL_VERSION];
        if (!in_array($requested, self::supportedProtocolVersions(), true)) {
            return self::modernErrorResponse(
                -32022,
                'UnsupportedProtocolVersionError',
                400,
                $id,
                array(
                    'supported' => self::supportedProtocolVersions(),
                    'requested' => $requested,
                )
            );
        }

        $header_error = self::validateProtocolHeaders($request, $method, $params, $requested, $id);
        if ($header_error !== null) {
            return $header_error;
        }

        if (!array_key_exists('id', $body)) {
            return new \WP_REST_Response(null, 202);
        }

        switch ($method) {
            case 'server/discover':
                return self::modernSuccessResponse(self::discoverResult(), $id);

            case 'tools/list':
                $result = self::listTools();
                $result['ttlMs'] = self::TOOLS_LIST_TTL_MS;
                $result['cacheScope'] = 'public';
                return self::modernSuccessResponse($result, $id);

            case 'tools/call':
                return self::decorateModernResponse(self::callTool($params, $id));

            default:
                return self::modernErrorResponse(-32601, 'Method not found', 404, $id);
        }
    }

    /**
     * Discovery result for `server/discover`.
     *
     * Declares the supported versions in the `supportedVersions` field required
     * by the modern (2026-07-28) `server/discover` schema — distinct from the
     * card's `remotes[].supportedProtocolVersions`. `resultType` and the server
     * identity in `_meta` are added by `modernSuccessResponse()`.
     *
     * @return array
     */
    public static function discoverResult()
    {
        return array(
            'supportedVersions' => self::supportedProtocolVersions(),
            'capabilities'      => array('tools' => (object) array()),
            'ttlMs'             => self::TOOLS_LIST_TTL_MS,
            'cacheScope'        => 'public',
        );
    }

    /**
     * Build a modern JSON-RPC success response.
     *
     * @param array $result Result payload.
     * @param mixed $id     JSON-RPC id.
     * @return \WP_REST_Response
     */
    private static function modernSuccessResponse($result, $id)
    {
        $result['resultType'] = 'complete';
        $result['_meta'] = array(self::META_SERVER_INFO => self::serverInfo());

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
     * Build a modern JSON-RPC error response with an HTTP status.
     *
     * @param int    $code    JSON-RPC error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status.
     * @param mixed  $id      JSON-RPC id.
     * @param array  $data    Optional error data.
     * @return \WP_REST_Response
     */
    private static function modernErrorResponse($code, $message, $status, $id, $data = null)
    {
        $error = array(
            'code'    => $code,
            'message' => $message,
        );
        if (is_array($data)) {
            $error['data'] = $data;
        }

        return new \WP_REST_Response(
            array(
                'jsonrpc' => '2.0',
                'error'   => $error,
                'id'      => $id,
            ),
            $status
        );
    }

    /**
     * Decorate a legacy-shaped response as a modern result, when applicable.
     *
     * @param \WP_REST_Response $response Response produced by the shared logic.
     * @return \WP_REST_Response
     */
    private static function decorateModernResponse($response)
    {
        $data = $response->get_data();
        if (is_array($data) && isset($data['result']) && is_array($data['result'])) {
            $data['result']['resultType'] = 'complete';
            $data['result']['_meta'] = array(self::META_SERVER_INFO => self::serverInfo());

            return new \WP_REST_Response($data, $response->get_status());
        }

        return $response;
    }

    /**
     * Validate the mandatory modern-era headers.
     *
     * Un `params.name` no textual es un parámetro inválido (`-32602`), no un
     * desajuste de cabecera: se rechaza antes de compararlo con `Mcp-Name`,
     * sin convertirlo a cadena.
     *
     * @param \WP_REST_Request $request   REST request.
     * @param string           $method    JSON-RPC method.
     * @param array            $params    Request params.
     * @param string           $requested Version declared in `_meta`.
     * @param mixed            $id        JSON-RPC id.
     * @return \WP_REST_Response|null Null when every header matches.
     */
    private static function validateProtocolHeaders($request, $method, $params, $requested, $id)
    {
        $protocol = self::requestHeader($request, 'MCP-Protocol-Version');
        if ($protocol === null || $protocol !== $requested) {
            return self::modernErrorResponse(-32020, 'HeaderMismatch', 400, $id);
        }

        $mcp_method = self::requestHeader($request, 'Mcp-Method');
        if ($mcp_method === null || $mcp_method !== $method) {
            return self::modernErrorResponse(-32020, 'HeaderMismatch', 400, $id);
        }

        if ($method === 'tools/call') {
            if (isset($params['name']) && !is_string($params['name'])) {
                return self::modernErrorResponse(-32602, 'Invalid params', 400, $id);
            }

            $mcp_name = self::requestHeader($request, 'Mcp-Name');
            $expected = isset($params['name']) ? $params['name'] : '';
            if ($mcp_name === null || self::decodeMcpName($mcp_name) !== $expected) {
                return self::modernErrorResponse(-32020, 'HeaderMismatch', 400, $id);
            }
        }

        return null;
    }

    /**
     * Read a request header, or null when absent.
     *
     * @param \WP_REST_Request $request REST request.
     * @param string           $name    Header name.
     * @return string|null
     */
    private static function requestHeader($request, $name)
    {
        if (!is_object($request) || !method_exists($request, 'get_header')) {
            return null;
        }

        $value = $request->get_header($name);

        return $value === null ? null : (string) $value;
    }

    /**
     * Decode an `Mcp-Name` value that uses the Base64 sentinel.
     *
     * @param string $value Header value.
     * @return string
     */
    private static function decodeMcpName($value)
    {
        $value = (string) $value;
        if (strlen($value) > 11
            && substr($value, 0, 9) === '=?base64?'
            && substr($value, -2) === '?='
        ) {
            $decoded = base64_decode(substr($value, 9, -2), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Versiones de protocolo soportadas (fuente única de verdad).
     *
     * @return string[]
     */
    public static function supportedProtocolVersions(): array
    {
        return MCPDiscovery::supportedProtocolVersions();
    }

    /**
     * Resultado de `initialize` para clientes MCP genéricos.
     *
     * @return array
     */
    public static function initializeResult($offered = null)
    {
        return array(
            'protocolVersion' => self::negotiateProtocolVersion($offered),
            'serverInfo'      => self::serverInfo(),
            'capabilities'    => array(
                'tools' => (object) array(),
            ),
        );
    }

    /**
     * Negocia la versión de protocolo de la era legacy.
     *
     * Devuelve la mejor versión soportada que no supera la ofrecida por el
     * cliente; si el cliente no la indica, la más baja soportada.
     *
     * @param mixed $offered Versión ofrecida por el cliente.
     * @return string
     */
    private static function negotiateProtocolVersion($offered): string
    {
        $supported = self::supportedProtocolVersions();

        if (!is_string($offered) || $offered === '') {
            return (string) end($supported);
        }

        foreach ($supported as $version) {
            if (version_compare($version, $offered, '<=')) {
                return (string) $version;
            }
        }

        return (string) end($supported);
    }

    /**
     * Identidad del servidor, compartida por resultados y descubrimiento.
     *
     * @return array
     */
    private static function serverInfo(): array
    {
        $version = defined('ATAREAO_PLUGIN_VERSION') ? ATAREAO_PLUGIN_VERSION : '1.0.0';

        return array(
            'name'    => 'atareao-mcp',
            'version' => $version,
        );
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
                    'description' => 'Devuelve las últimas entradas publicadas y públicas del blog (solo lectura).',
                    'inputSchema' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'limit'     => array(
                                'type'        => 'integer',
                                'description' => 'Número máximo de entradas a devolver (1-50).',
                                'minimum'     => 1,
                                'maximum'     => self::MAX_PER_PAGE,
                            ),
                            'post_type' => array(
                                'type'        => 'string',
                                'description' => 'Restringe los resultados a un tipo de contenido público.',
                                'enum'        => self::ALLOWED_POST_TYPES,
                            ),
                        ),
                    ),
                    'annotations' => array(
                        'readOnlyHint'          => true,
                        'untrustedContentHint' => true,
                        'destructiveHint'       => false,
                    ),
                ),
                array(
                    'name'        => 'get_post',
                    'description' => 'Devuelve una entrada publicada y pública por su ID (solo lectura).',
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
                    'annotations' => array(
                        'readOnlyHint'          => true,
                        'untrustedContentHint' => true,
                        'destructiveHint'       => false,
                    ),
                ),
                array(
                    'name'        => 'search_posts',
                    'description' => 'Busca entradas publicadas y públicas por texto (solo lectura).',
                    'inputSchema' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'query'     => array(
                                'type'        => 'string',
                                'description' => 'The search term or query string.',
                            ),
                            'post_type' => array(
                                'type'        => 'string',
                                'description' => 'Restringe los resultados a un tipo de contenido público.',
                                'enum'        => self::ALLOWED_POST_TYPES,
                            ),
                            'per_page'  => array(
                                'type'        => 'integer',
                                'description' => 'Resultados por página (1-50).',
                                'minimum'     => 1,
                                'maximum'     => self::MAX_PER_PAGE,
                            ),
                            'page'      => array(
                                'type'        => 'integer',
                                'description' => 'Página solicitada (>=1).',
                                'minimum'     => 1,
                            ),
                        ),
                        'required'   => array('query'),
                    ),
                    'annotations' => array(
                        'readOnlyHint'          => true,
                        'untrustedContentHint' => true,
                        'destructiveHint'       => false,
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
                $post_type = self::postTypeArgument($arguments);
                if (is_wp_error($post_type)) {
                    return self::errorResponse(-32602, $post_type->get_error_message(), $id);
                }
                return self::successResponse(self::getLatestPosts($limit, $post_type), $id);

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
                $post_type = self::postTypeArgument($arguments);
                if (is_wp_error($post_type)) {
                    return self::errorResponse(-32602, $post_type->get_error_message(), $id);
                }
                return self::successResponse(self::searchPosts($query_text, $per_page, $page, $post_type), $id);

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
     * Valida y normaliza el argumento opcional `post_type`.
     *
     * Un valor ausente o `null` significa «todos los tipos públicos»: el
     * conjunto por defecto sigue siendo `publicPostTypes()`, de modo que la
     * consulta abarca todos los tipos públicos del runtime (incluido `page`),
     * como antes del cambio.
     *
     * Un valor **explícito** debe pertenecer a la lista permitida fija
     * `ALLOWED_POST_TYPES` (los seis tipos del dominio). Cualquier otro valor
     * —incluido un tipo público fuera del dominio, como `page`— devuelve un
     * error para responder `-32602` sin ejecutar la consulta.
     *
     * @param array $arguments Argumentos de la herramienta.
     * @return string|null|\WP_Error Tipo validado, `null` si se omite, o error.
     */
    private static function postTypeArgument($arguments)
    {
        if (!array_key_exists('post_type', $arguments) || $arguments['post_type'] === null) {
            return null;
        }

        $value = $arguments['post_type'];
        if (!is_string($value)) {
            return new \WP_Error('invalid_params', 'Invalid params: post_type');
        }

        if (!in_array($value, self::ALLOWED_POST_TYPES, true)) {
            return new \WP_Error('invalid_params', 'Invalid params: post_type');
        }

        return $value;
    }

    /**
     * Implement get_latest_posts.
     *
     * @param int         $limit     Número máximo de entradas a devolver.
     * @param string|null $post_type Tipo de contenido a filtrar (null = todos).
     * @return array
     */
    private static function getLatestPosts($limit, $post_type = null)
    {
        $args = array(
            'post_type'      => $post_type !== null ? array($post_type) : self::publicPostTypes(),
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

        return array('content' => array(array('type' => 'text', 'text' => wp_json_encode($posts, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE))));
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
            && in_array($post->post_type, self::publicPostTypes(), true)
            && !post_password_required($post);

        if (!$is_public) {
            return new \WP_Error('not_found', 'Post not found');
        }

        $formatted = self::formatPost($post, true);
        return array('content' => array(array('type' => 'text', 'text' => wp_json_encode($formatted, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE))));
    }

    /**
     * Implement search_posts.
     *
     * @param string      $query_text Consulta saneada.
     * @param int         $per_page   Tamaño de página acotado.
     * @param int         $page       Página solicitada.
     * @param string|null $post_type  Tipo de contenido a filtrar (null = todos).
     * @return array
     */
    private static function searchPosts($query_text, $per_page, $page, $post_type = null)
    {
        $args = array(
            'post_type'      => $post_type !== null ? array($post_type) : self::publicPostTypes(),
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

        return array('content' => array(array('type' => 'text', 'text' => wp_json_encode($posts, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE))));
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

        $meta = self::publicMetas($post);
        if (!empty($meta)) {
            $data['meta'] = $meta;
        }

        $taxonomies = self::publicTaxonomies($post);
        if (!empty($taxonomies)) {
            $data['taxonomies'] = $taxonomies;
        }

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
     * Metas públicas de una entrada según su tipo de contenido.
     *
     * Devuelve únicamente las claves de la lista blanca `PUBLIC_METAS` (más
     * `post_views_count`) que existan y no estén vacías. Nunca expone claves
     * internas con prefijo `_`.
     *
     * @param object $post Entrada de WordPress.
     * @return array<string, mixed> Mapa meta_key => valor.
     */
    private static function publicMetas($post)
    {
        $keys = array('post_views_count');
        if (isset(self::PUBLIC_METAS[$post->post_type])) {
            $keys = array_merge(self::PUBLIC_METAS[$post->post_type], $keys);
        }

        $meta = array();
        foreach ($keys as $key) {
            $value = get_post_meta($post->ID, $key, true);
            if ($value !== '' && $value !== null && $value !== false) {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }

    /**
     * Taxonomías públicas de una entrada con sus términos legibles.
     *
     * Solo se incluyen taxonomías marcadas como públicas; las privadas y los
     * datos de usuario quedan fuera.
     *
     * @param object $post Entrada de WordPress.
     * @return array<string, string[]> Mapa taxonomía => nombres de término.
     */
    private static function publicTaxonomies($post)
    {
        $taxonomies = get_object_taxonomies($post->post_type, 'objects');
        if (!is_array($taxonomies)) {
            return array();
        }

        $result = array();
        foreach ($taxonomies as $taxonomy) {
            if (empty($taxonomy->public)) {
                continue;
            }

            $terms = get_the_terms($post, $taxonomy->name);
            if (!is_array($terms)) {
                continue;
            }

            $names = array();
            foreach ($terms as $term) {
                if (isset($term->name) && $term->name !== '') {
                    $names[] = $term->name;
                }
            }

            if (!empty($names)) {
                $result[$taxonomy->name] = $names;
            }
        }

        return $result;
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

        if (function_exists('mb_strcut')) {
            return mb_strcut($text, 0, $max, 'UTF-8');
        }

        // Red de seguridad sin mbstring: recorta bytes hasta que el fragmento
        // sea UTF-8 válido, sin partir una secuencia multibyte.
        $cut = substr($text, 0, $max);
        while ($cut !== '' && @preg_match('//u', $cut) === false) {
            $cut = substr($cut, 0, -1);
        }
        return $cut;
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
