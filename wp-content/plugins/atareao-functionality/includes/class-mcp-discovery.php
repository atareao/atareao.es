<?php
/**
 * MCP discovery artifacts — AI catalog and server card.
 *
 * Serves the two discovery documents at their well-known/clean paths, outside
 * the REST render cycle, so their specific media types can be fixed and their
 * routes resolve even when rewrite rules have not been flushed.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class MCPDiscovery
{
    /**
     * Path of the AI catalog document.
     */
    public const CATALOG_PATH = '/.well-known/ai-catalog.json';

    /**
     * Path of the server card document.
     */
    public const CARD_PATH = '/mcp/server-card';

    /**
     * Path of the canonical ARD manifest.
     */
    public const ARD_PATH = '/.well-known/ard.json';

    /**
     * Media type of the AI catalog document.
     */
    public const CATALOG_MEDIA_TYPE = 'application/ai-catalog+json';

    /**
     * Media type of the server card document.
     */
    public const CARD_MEDIA_TYPE = 'application/mcp-server-card+json';

    /**
     * Media type of the ARD manifest (the standard prescribes none, so JSON).
     */
    public const ARD_MEDIA_TYPE = 'application/json';

    /**
     * Literal ARD context URI declared by the entry.
     */
    public const ARD_CONTEXT = 'https://agenticresourcediscovery.org/context/v1';

    /**
     * Cache-Control directive shared by both artifacts.
     */
    public const CACHE_CONTROL = 'public, max-age=3600';

    /**
     * AI Catalog spec version.
     */
    public const SPEC_VERSION = '1.0';

    /**
     * Stable identifier of the MCP server entry.
     */
    public const IDENTIFIER = 'urn:air:atareao.es:mcp:blog';

    /**
     * Reverse-DNS server name.
     */
    public const SERVER_NAME = 'es.atareao/blog';

    /**
     * Human readable title.
     */
    public const SERVER_TITLE = 'atareao.es — consulta del blog';

    /**
     * Human readable description.
     */
    public const SERVER_DESCRIPTION = 'Consulta pública y de solo lectura del contenido publicado en atareao.es.';

    /**
     * Literal schema URL required by SEP-2127.
     */
    public const SERVER_CARD_SCHEMA = 'https://static.modelcontextprotocol.io/schemas/v1/server-card.schema.json';

    /**
     * Wire the discovery routes.
     *
     * @return void
     */
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'serve'), 0);
        add_filter('robots_txt', array(__CLASS__, 'filterRobotsTxt'), 10, 2);
    }

    /**
     * Protocol versions the server actually honours (single source of truth).
     *
     * @return string[]
     */
    public static function supportedProtocolVersions(): array
    {
        return array('2026-07-28', '2025-06-18');
    }

    /**
     * Absolute URL of the clean MCP endpoint.
     *
     * @return string
     */
    public static function endpointUrl(): string
    {
        return home_url('/mcp');
    }

    /**
     * Absolute URL of the server card.
     *
     * @return string
     */
    public static function cardUrl(): string
    {
        return home_url(self::CARD_PATH);
    }

    /**
     * Absolute URL of the canonical ARD manifest.
     *
     * Single source of the absolute URL announced by the `Agentmap:` robots
     * directive and the `<link rel="ard">` head tag.
     *
     * @return string
     */
    public static function ardUrl(): string
    {
        return home_url(self::ARD_PATH);
    }

    /**
     * Entries shared by both manifest routes.
     *
     * The AI catalog and the ARD manifest derive from this one composition, so
     * the two routes cannot diverge. Each entry keeps the AI Catalog shape
     * (`identifier`, `type`, `url`) and adds the ARD context and the natural
     * language queries.
     *
     * @return array
     */
    public static function discoveryEntries(): array
    {
        return array(
            array(
                'identifier'            => self::IDENTIFIER,
                'type'                  => self::CARD_MEDIA_TYPE,
                'url'                   => self::cardUrl(),
                '@context'              => self::ARD_CONTEXT,
                'representativeQueries' => array(
                    'qué artículos hay sobre Podman',
                    'qué tutoriales se han publicado sobre Rust',
                    'qué aplicaciones recomienda el blog',
                    'qué podcasts hay disponibles',
                ),
            ),
        );
    }

    /**
     * AI catalog document.
     *
     * @return array
     */
    public static function catalogDocument(): array
    {
        return array(
            'specVersion' => self::SPEC_VERSION,
            'entries'     => self::discoveryEntries(),
        );
    }

    /**
     * Canonical ARD manifest document.
     *
     * @return array
     */
    public static function ardDocument(): array
    {
        return array(
            'entries' => self::discoveryEntries(),
        );
    }

    /**
     * Server card document.
     *
     * @return array
     */
    public static function serverCardDocument(): array
    {
        $version = defined('ATAREAO_PLUGIN_VERSION') ? ATAREAO_PLUGIN_VERSION : '1.0.0';

        return array(
            '$schema'     => self::SERVER_CARD_SCHEMA,
            'name'        => self::SERVER_NAME,
            'title'       => self::SERVER_TITLE,
            'description' => self::SERVER_DESCRIPTION,
            'version'     => $version,
            'websiteUrl'  => home_url('/'),
            'remotes'     => array(
                array(
                    'type'                      => 'streamable-http',
                    'url'                       => self::endpointUrl(),
                    'supportedProtocolVersions' => self::supportedProtocolVersions(),
                ),
            ),
        );
    }

    /**
     * Whether a request path is one of the discovery artifacts.
     *
     * @param string $path Request path.
     * @return bool
     */
    public static function isDiscoveryPath($path): bool
    {
        $path = (string) $path;

        return $path === self::ARD_PATH
            || $path === self::CATALOG_PATH
            || $path === self::CARD_PATH;
    }

    /**
     * Media type for a discovery path, or null when it is not one.
     *
     * @param string $path Request path.
     * @return string|null
     */
    public static function mediaTypeForPath($path): ?string
    {
        if ((string) $path === self::ARD_PATH) {
            return self::ARD_MEDIA_TYPE;
        }
        if ((string) $path === self::CATALOG_PATH) {
            return self::CATALOG_MEDIA_TYPE;
        }
        if ((string) $path === self::CARD_PATH) {
            return self::CARD_MEDIA_TYPE;
        }

        return null;
    }

    /**
     * Document served for a discovery path.
     *
     * @param string $path Request path.
     * @return array
     */
    private static function documentForPath($path): array
    {
        if ((string) $path === self::ARD_PATH) {
            return self::ardDocument();
        }
        if ((string) $path === self::CARD_PATH) {
            return self::serverCardDocument();
        }

        return self::catalogDocument();
    }

    /**
     * HTTP headers for a discovery artifact.
     *
     * @param string $mediaType Specific media type.
     * @param string $etag      Entity tag.
     * @return array
     */
    public static function headersFor($mediaType, $etag): array
    {
        return array(
            'Content-Type'                  => (string) $mediaType,
            'Cache-Control'                 => self::CACHE_CONTROL,
            'ETag'                          => (string) $etag,
            'Access-Control-Allow-Origin'   => '*',
            'Access-Control-Allow-Methods'  => 'GET',
            'Access-Control-Allow-Headers'  => 'Content-Type, If-None-Match',
            'Access-Control-Expose-Headers' => 'ETag',
        );
    }

    /**
     * Entity tag for a body.
     *
     * @param string $body Serialized document.
     * @return string
     */
    public static function etagFor($body): string
    {
        return '"' . hash('sha256', (string) $body) . '"';
    }

    /**
     * Pure response plan for a discovery request.
     *
     * @param string $method      HTTP method.
     * @param string $path        Request path.
     * @param string $ifNoneMatch If-None-Match header value.
     * @return array|null Response plan, or null when the path is not a discovery artifact.
     */
    public static function responseFor($method, $path, $ifNoneMatch): ?array
    {
        if (strtoupper((string) $method) !== 'GET') {
            return null;
        }

        $mediaType = self::mediaTypeForPath($path);
        if ($mediaType === null) {
            return null;
        }

        $document = self::documentForPath($path);

        $body = wp_json_encode($document);
        if (!is_string($body)) {
            $body = '';
        }

        $etag = self::etagFor($body);

        if (trim((string) $ifNoneMatch) !== '' && trim((string) $ifNoneMatch) === $etag) {
            return array(
                'status'  => 304,
                'headers' => self::headersFor($mediaType, $etag),
                'body'    => '',
            );
        }

        return array(
            'status'  => 200,
            'headers' => self::headersFor($mediaType, $etag),
            'body'    => $body,
        );
    }

    /**
     * Serve a discovery artifact when the current request targets one.
     *
     * @return void
     */
    public static function serve()
    {
        $path = self::currentPath();
        if (!self::isDiscoveryPath($path)) {
            return;
        }

        $method      = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
        $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? (string) $_SERVER['HTTP_IF_NONE_MATCH'] : '';
        $plan        = self::responseFor($method, $path, $ifNoneMatch);

        if ($plan === null) {
            return;
        }

        status_header($plan['status']);
        foreach ($plan['headers'] as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($plan['status'] !== 304 && $plan['body'] !== '') {
            echo $plan['body'];
        }

        exit;
    }

    /**
     * Append the ARD `Agentmap:` directive to the virtual robots.txt.
     *
     * The site has no robots.txt file on disk: WordPress (via The SEO
     * Framework) generates one and passes it through the `robots_txt` filter,
     * so the announcement cannot live in a static, unversioned file.
     *
     * @param string $output The generated robots.txt contents.
     * @param bool   $public Whether the site is public.
     * @return string
     */
    public static function filterRobotsTxt($output, $public = true): string
    {
        $output = (string) $output;
        $line   = 'Agentmap: ' . self::ardUrl();

        if (strpos($output, $line) !== false) {
            return $output;
        }

        return rtrim($output, "\n") . "\n" . $line . "\n";
    }

    /**
     * Path of the current request, without the query string.
     *
     * @return string
     */
    private static function currentPath(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }
}
