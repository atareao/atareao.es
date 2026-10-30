<?php
/**
 * MCP registry artifacts — domain ownership proof and protected resource metadata.
 *
 * Serves the two documents the official MCP registry looks up at the site's
 * well-known paths, outside the REST render cycle, so their specific media
 * types can be fixed and their routes resolve even when rewrite rules have not
 * been flushed.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class MCPRegistry
{
    /**
     * Path of the domain ownership proof document.
     */
    public const PROOF_PATH = '/.well-known/mcp-registry-auth';

    /**
     * Path of the OAuth 2.0 protected resource metadata document.
     */
    public const PROTECTED_RESOURCE_PATH = '/.well-known/oauth-protected-resource';

    /**
     * Media type of the domain ownership proof document.
     */
    public const PROOF_MEDIA_TYPE = 'text/plain; charset=utf-8';

    /**
     * Media type of the protected resource metadata document.
     */
    public const PROTECTED_RESOURCE_MEDIA_TYPE = 'application/json';

    /**
     * Constant that holds the proof line, with precedence over the option.
     */
    public const PROOF_CONSTANT = 'ATAREAO_MCP_REGISTRY_PROOF';

    /**
     * WordPress option that holds the proof line when no constant is defined.
     */
    public const PROOF_OPTION = 'atareao_mcp_registry_proof';

    /**
     * Wire the registry routes.
     *
     * The artifacts are declarative: they never touch an authentication filter,
     * so the public query tools keep answering without credentials.
     *
     * @return void
     */
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'serve'), 0);
    }

    /**
     * Whether a request path is one of the registry artifacts.
     *
     * @param string $path Request path.
     * @return bool
     */
    public static function isRegistryPath($path): bool
    {
        $path = (string) $path;

        return $path === self::PROOF_PATH
            || $path === self::PROTECTED_RESOURCE_PATH;
    }

    /**
     * Media type for a registry path, or null when it is not one.
     *
     * @param string $path Request path.
     * @return string|null
     */
    public static function mediaTypeForPath($path): ?string
    {
        if ((string) $path === self::PROOF_PATH) {
            return self::PROOF_MEDIA_TYPE;
        }
        if ((string) $path === self::PROTECTED_RESOURCE_PATH) {
            return self::PROTECTED_RESOURCE_MEDIA_TYPE;
        }

        return null;
    }

    /**
     * Proof line of domain ownership.
     *
     * Single source of truth: a constant takes precedence over the WordPress
     * option, so production can pin the line in `wp-config.php` while the
     * settings page keeps a database fallback. The private key is never part
     * of this value.
     *
     * @return string Proof line, or an empty string when none is configured.
     */
    public static function proofLine(): string
    {
        if (defined(self::PROOF_CONSTANT)) {
            $constant = trim((string) constant(self::PROOF_CONSTANT));
            if ($constant !== '') {
                return $constant;
            }
        }

        return trim((string) get_option(self::PROOF_OPTION, ''));
    }

    /**
     * Proof document body, or null when no proof is configured.
     *
     * @return string|null
     */
    public static function proofDocument(): ?string
    {
        $line = self::proofLine();
        if ($line === '') {
            return null;
        }

        return $line . "\n";
    }

    /**
     * OAuth 2.0 Protected Resource Metadata document (RFC 9728).
     *
     * The document is declarative: it announces where authorization would be
     * obtained (the configured identity provider) without requiring a token.
     *
     * @return array|null Document, or null when no identity provider is configured.
     */
    public static function protectedResourceDocument(): ?array
    {
        $provider = self::identityProvider();
        if ($provider === '') {
            return null;
        }

        return array(
            'resource'                 => self::canonicalEndpointUrl(),
            'authorization_servers'    => array($provider),
            'bearer_methods_supported' => array('header'),
            'resource_documentation'   => home_url('/'),
        );
    }

    /**
     * Configured identity provider issuer.
     *
     * Same source of truth as the identity provider configuration: the
     * `PocketIDLogin` option. The class constant is read with a `defined()`
     * guard so this module does not depend on the load order.
     *
     * @return string Issuer, or an empty string when none is configured.
     */
    private static function identityProvider(): string
    {
        $option = defined('\Atareao\PocketIDLogin::OPTION_URL')
            ? constant('\Atareao\PocketIDLogin::OPTION_URL')
            : 'atareao_pocketid_url';

        return trim((string) get_option($option, ''));
    }

    /**
     * Canonical URI of the clean MCP endpoint.
     *
     * Derived from the single source of truth (`MCPDiscovery::endpointUrl()`)
     * and normalized: lower-case scheme and host, no trailing slash, port kept.
     *
     * @return string
     */
    private static function canonicalEndpointUrl(): string
    {
        $endpoint = \Atareao\MCPDiscovery::endpointUrl();
        $parts    = wp_parse_url($endpoint);

        if (!is_array($parts) || !isset($parts['scheme']) || !isset($parts['host'])) {
            return rtrim(strtolower((string) $endpoint), '/');
        }

        $url = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']);
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        if (isset($parts['path'])) {
            $url .= rtrim((string) $parts['path'], '/');
        }

        return $url;
    }

    /**
     * Pure response plan for a registry request.
     *
     * @param string $method      HTTP method.
     * @param string $path        Request path.
     * @param string $ifNoneMatch If-None-Match header value.
     * @return array|null Response plan, or null when the path is not a registry artifact.
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

        if ((string) $path === self::PROOF_PATH) {
            $body = self::proofDocument();
        } else {
            $document = self::protectedResourceDocument();
            if ($document === null) {
                $body = null;
            } else {
                $body = wp_json_encode($document);
                if (!is_string($body)) {
                    $body = '';
                }
            }
        }

        if ($body === null) {
            return array(
                'status'  => 404,
                'headers' => array(),
                'body'    => '',
            );
        }

        $etag = \Atareao\MCPDiscovery::etagFor($body);

        if (trim((string) $ifNoneMatch) !== '' && trim((string) $ifNoneMatch) === $etag) {
            return array(
                'status'  => 304,
                'headers' => \Atareao\MCPDiscovery::headersFor($mediaType, $etag),
                'body'    => '',
            );
        }

        return array(
            'status'  => 200,
            'headers' => \Atareao\MCPDiscovery::headersFor($mediaType, $etag),
            'body'    => $body,
        );
    }

    /**
     * Serve a registry artifact when the current request targets one.
     *
     * @return void
     */
    public static function serve()
    {
        $path = self::currentPath();
        if (!self::isRegistryPath($path)) {
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
     * Path of the current request, without the query string.
     *
     * @return string
     */
    private static function currentPath(): string
    {
        $uri  = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }
}
