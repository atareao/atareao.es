/**
 * atareao.es — capa WebMCP para el front-end público.
 *
 * Mejora progresiva: expone herramientas (tools) de solo lectura al agente IA
 * en-página cuando el navegador soporta WebMCP. Si la API no existe, este
 * script es un no-op silencioso: no lanza errores ni altera la página.
 *
 * Ubicación de la API: la especificación WebMCP (W3C CG Draft) trasladó la
 * API de `navigator.modelContext` a `document.modelContext` (jul-2026). Por eso
 * se resuelve primero en `document` y, como ruta de transición, se mantiene el
 * fallback a `navigator.modelContext` para navegadores antiguos.
 *
 * Solo se usan `registerTool()` / `unregisterTool()`, la superficie vigente de
 * la especificación.
 *
 * Las tools no implementan lógica de consulta en el navegador: reenvían
 * `tools/call` al MCP público ya existente en `POST /wp-json/atareao/v1/mcp`.
 * No se incrustan nonces, tokens ni secretos.
 */
(function () {
    'use strict';

    var PUBLIC_TYPES = ['post', 'tutorial', 'capitulo', 'aplicacion', 'podcast', 'software'];

    /** Contador incremental de peticiones JSON-RPC para evitar ids duplicados. */
    var seq = 0;

    /**
     * Resuelve el endpoint MCP: configuración explícita del tema/plugin si está
     * disponible, o la ruta relativa por defecto (mismo origen).
     * Se lee desde `globalThis` para funcionar también bajo `node:vm`.
     */
    function resolveEndpoint() {
        var config = globalThis.AtareaoWebMCP;
        if (config && typeof config.endpoint === 'string' && config.endpoint) {
            return config.endpoint;
        }
        return '/wp-json/atareao/v1/mcp';
    }

    /**
     * Ejecuta una llamada JSON-RPC `tools/call` contra el endpoint MCP.
     * Nunca lanza: los fallos de red, las respuestas HTTP no correctas y los
     * errores JSON-RPC se devuelven como objeto estructurado `{ isError, error }`.
     */
    function callMcp(name, args) {
        var fetchFn = globalThis.fetch;
        if (typeof fetchFn !== 'function') {
            return Promise.resolve({ isError: true, error: 'fetch unavailable' });
        }

        var body = {
            jsonrpc: '2.0',
            id: ++seq,
            method: 'tools/call',
            params: { name: name, arguments: args || {} },
        };

        var request;
        try {
            request = fetchFn(resolveEndpoint(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
        } catch (error) {
            var syncMessage = error && error.message ? error.message : String(error);
            return Promise.resolve({ isError: true, error: syncMessage });
        }

        return Promise.resolve(request).then(function (response) {
            if (!response || !response.ok) {
                var status = response && response.status ? response.status : 'unknown';
                return { isError: true, error: 'HTTP error ' + status };
            }
            return response.json();
        }).then(function (data) {
            if (data && data.error) {
                return { isError: true, error: data.error };
            }
            return data && data.result !== undefined ? data.result : data;
        }).catch(function (error) {
            var message = error && error.message ? error.message : String(error);
            return { isError: true, error: message };
        });
    }

    /** Definiciones de las tools (datos puros) expuestas al agente. */
    var tools = [
        {
            name: 'get_latest_posts',
            description: 'Devuelve las últimas entradas publicadas y públicas de atareao.es (posts, tutoriales, capítulos de podcast, aplicaciones y software).',
            inputSchema: {
                type: 'object',
                properties: {
                    limit: {
                        type: 'integer',
                        description: 'Número máximo de entradas a devolver.',
                        minimum: 1,
                        maximum: 50,
                    },
                    post_type: {
                        type: 'string',
                        description: 'Tipo de contenido público a listar.',
                        enum: PUBLIC_TYPES,
                    },
                },
                required: [],
            },
            annotations: { readOnlyHint: true, untrustedContentHint: true },
            execute: function (args) {
                return callMcp('get_latest_posts', args);
            },
        },
        {
            name: 'get_post',
            description: 'Devuelve una entrada publicada y pública de atareao.es por su identificador.',
            inputSchema: {
                type: 'object',
                properties: {
                    id: {
                        type: 'integer',
                        description: 'Identificador (ID) de la entrada.',
                    },
                },
                required: ['id'],
            },
            annotations: { readOnlyHint: true, untrustedContentHint: true },
            execute: function (args) {
                return callMcp('get_post', args);
            },
        },
        {
            name: 'search_posts',
            description: 'Busca entradas publicadas y públicas de atareao.es por texto, con filtro opcional por tipo de contenido.',
            inputSchema: {
                type: 'object',
                properties: {
                    query: {
                        type: 'string',
                        description: 'Término de búsqueda.',
                    },
                    post_type: {
                        type: 'string',
                        description: 'Tipo de contenido público donde buscar.',
                        enum: PUBLIC_TYPES,
                    },
                    per_page: {
                        type: 'integer',
                        description: 'Resultados por página.',
                        minimum: 1,
                        maximum: 50,
                    },
                    page: {
                        type: 'integer',
                        description: 'Número de página de resultados.',
                        minimum: 1,
                    },
                },
                required: ['query'],
            },
            annotations: { readOnlyHint: true, untrustedContentHint: true },
            execute: function (args) {
                return callMcp('search_posts', args);
            },
        },
    ];

    /** Devuelve la API WebMCP disponible, o `null` si no hay ninguna. */
    function resolveModelContext() {
        if (typeof document !== 'undefined' && document && document.modelContext) {
            return document.modelContext;
        }
        if (typeof navigator !== 'undefined' && navigator && navigator.modelContext) {
            return navigator.modelContext;
        }
        return null;
    }

    var modelContext = resolveModelContext();
    if (!modelContext || typeof modelContext.registerTool !== 'function') {
        return;
    }

    tools.forEach(function (tool) {
        modelContext.registerTool(tool);
    });
})();
