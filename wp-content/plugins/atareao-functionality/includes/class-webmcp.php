<?php
/**
 * WebMCP integration — tools de navegador sobre el MCP existente.
 *
 * Registra en el front-end público el cliente `assets/js/webmcp.js`, que
 * declara herramientas tipadas de solo lectura ante la API WebMCP del
 * navegador (`document.modelContext`, con fallback a `navigator.modelContext`).
 * La URL del endpoint MCP se localiza para reutilizar
 * `POST /wp-json/atareao/v1/mcp` sin introducir rutas nuevas.
 *
 * No se incrusta ningún nonce ni secreto: el HTML puede estar cacheado durante
 * horas y la consulta es pública de solo lectura.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class WebMCP
{
    /**
     * Nombre del handle del script del cliente WebMCP.
     */
    private const SCRIPT_HANDLE = 'atareao-webmcp';

    /**
     * Nombre del objeto global localizado para el cliente.
     */
    private const LOCALIZE_NAME = 'AtareaoWebMCP';

    /**
     * Inicializar.
     *
     * Engancha la carga de assets solo en el front-end público. El panel de
     * administración no encola ni registra ninguna tool.
     *
     * @return void
     */
    public static function init()
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueueAssets'));
    }

    /**
     * Encola el cliente WebMCP y localiza el endpoint MCP.
     *
     * Solo actúa en el front-end público. La localización expone únicamente la
     * URL del endpoint (sin nonce ni credenciales).
     *
     * @return void
     */
    public static function enqueueAssets()
    {
        if (is_admin()) {
            return;
        }

        $version = defined('ATAREAO_PLUGIN_VERSION') ? ATAREAO_PLUGIN_VERSION : '1.0.0';
        $src     = ATAREAO_PLUGIN_URL . 'assets/js/webmcp.js';

        wp_enqueue_script(self::SCRIPT_HANDLE, $src, array(), $version, true);

        wp_localize_script(
            self::SCRIPT_HANDLE,
            self::LOCALIZE_NAME,
            array(
                'endpoint' => esc_url_raw(rest_url('atareao/v1/mcp')),
            )
        );
    }
}
