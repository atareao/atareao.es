<?php
/**
 * Tools - JSON Formatter
 *
 * Route: /tools/json
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/json/');
$tool_title = 'JSON Formatter y Validator online | ' . get_bloginfo('name');
$tool_description = 'Valida, formatea y minifica JSON. Visualiza errores de parseo con mensajes claros y comparte el estado por URL.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'JSON Formatter y Validator',
            'url' => $tool_url,
            'applicationCategory' => 'DeveloperApplication',
            'operatingSystem' => 'Any',
            'description' => $tool_description,
            'offers' => array(
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'EUR',
            ),
        ),
        array(
            '@type' => 'FAQPage',
            'mainEntity' => array(
                array(
                    '@type' => 'Question',
                    'name' => 'Que hace esta herramienta JSON?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Valida sintaxis JSON, formatea con indentacion configurable y permite minificar en una sola linea.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Guarda mis datos en servidor?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'No. Todo el procesamiento se realiza en el navegador.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir el resultado?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Puedes compartir la URL de la herramienta, pero por seguridad no se incluye el contenido JSON en el enlace.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Que errores JSON son mas habituales?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Los errores frecuentes son comas finales, comillas simples, claves sin comillas dobles y llaves o corchetes sin cerrar.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Cuando conviene minificar JSON?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'La minificacion es util para reducir tamano en transporte o almacenamiento; el formateo se recomienda para depuracion y revision humana.',
                    ),
                ),
            ),
        ),
    ),
);

$seo_plugin_active = class_exists('WPSEO_Frontend') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION');

if (!$seo_plugin_active) {
    add_filter(
        'pre_get_document_title',
        static function () use ($tool_title) {
            return $tool_title;
        },
        20
    );
}

add_action(
    'wp_head',
    static function () use ($tool_url, $tool_title, $tool_description, $tool_schema, $seo_plugin_active) {
        if (!$seo_plugin_active) {
            echo '<meta name="description" content="' . esc_attr($tool_description) . '">' . "\n";
            echo '<link rel="canonical" href="' . esc_url($tool_url) . '">' . "\n";
            echo '<meta name="robots" content="index,follow,max-image-preview:large">' . "\n";
            echo '<meta property="og:type" content="website">' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($tool_title) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($tool_description) . '">' . "\n";
            echo '<meta property="og:url" content="' . esc_url($tool_url) . '">' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
            echo '<meta name="twitter:card" content="summary">' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($tool_title) . '">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr($tool_description) . '">' . "\n";
        }

        echo '<script type="application/ld+json">' . wp_json_encode($tool_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    },
    5
);

get_header();
?>

<main id="primary" class="site-main">
    <article id="post-tools-json" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">JSON Formatter y Validator</h1>
            <?php atareao_tools_render_breadcrumb('json'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Valida, formatea y minifica JSON para revisar payloads de APIs, configuraciones y respuestas de servicios.
                </p>
            </div>

            <div id="json_error" class="atareao-feedback-error" hidden></div>

            <form id="json_form" class="atareao-contact-form" method="post" action="" novalidate>
                <div>
                    <label for="json_input">JSON de entrada</label>
                    <textarea id="json_input" rows="10" spellcheck="false">{"app":"atareao","tools":["crontab","subnetting","regex"],"active":true}</textarea>
                </div>

                <div class="json-options-row">
                    <div>
                        <label for="json_indent">Indentacion</label>
                        <select id="json_indent">
                            <option value="2" selected>2 espacios</option>
                            <option value="4">4 espacios</option>
                            <option value="tab">Tabulador</option>
                        </select>
                    </div>

                    <div>
                        <label>&nbsp;</label>
                        <p>
                            <button type="button" id="json_validate" class="json-action">Validar</button>
                            <button type="button" id="json_format" class="json-action">Formatear</button>
                            <button type="button" id="json_minify" class="json-action">Minificar</button>
                            <button type="button" id="json_copy" class="json-action">Copiar salida</button>
                            <button type="button" id="json_copy_link" class="json-action">Copiar enlace</button>
                        </p>
                        <p class="json-security-note">Por seguridad, el enlace compartido no incluye tu JSON.</p>
                    </div>
                </div>

                <section>
                    <label for="json_summary">Resumen</label>
                    <p id="json_summary" class="atareao-json-summary">Listo para validar.</p>
                </section>

                <section>
                    <label for="json_output">Salida</label>
                    <pre id="json_output" class="json-output" aria-live="polite"></pre>
                </section>
            </form>

            <section class="atareao-tool-seo-content" aria-label="Guia rapida de JSON">
                <h2>Guia rapida de uso</h2>
                <h3>1. Pega tu JSON de entrada</h3>
                <p>Inserta respuestas de API o configuraciones para validar estructura y detectar errores sintacticos.</p>

                <h3>2. Elige accion</h3>
                <p>Usa Validar para comprobar parseo, Formatear para lectura humana o Minificar para reducir tamano.</p>

                <h3>3. Reutiliza resultado</h3>
                <p>Copia la salida limpia y aplicala en tests, pipelines CI/CD o configuracion de servicios.</p>
            </section>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de JSON">
                <h2>Preguntas frecuentes</h2>
                <h3>Que diferencia hay entre formatear y minificar</h3>
                <p>Formatear mejora legibilidad con saltos e indentacion. Minificar elimina espacios para reducir tamano.</p>

                <h3>Como detectar errores de sintaxis rapido</h3>
                <p>Usa Validar para comprobar el parseo e identificar exactamente si el JSON es valido o no.</p>

                <h3>Se procesa el JSON en servidor</h3>
                <p>No. Todo se procesa en navegador para preservar privacidad del contenido.</p>

                <h3>Como compartir un caso</h3>
                <p>Copiar enlace comparte la URL de la herramienta, pero no adjunta el contenido JSON para evitar fugas de datos.</p>

                <h3>Errores JSON mas comunes</h3>
                <p>Si el parseo falla, revisa primero comillas dobles, comas sobrantes, claves sin cerrar y estructura correcta de llaves o corchetes.</p>

                <h3>Cuando usar formatear o minificar</h3>
                <p>Formatear facilita lectura y revision por equipos; minificar reduce tamano para envio por red o almacenamiento eficiente.</p>

                <h3>Casos de uso tipicos</h3>
                <p>Resulta util para validar respuestas de APIs REST, configurar servicios y depurar payloads antes de enviar peticiones.</p>
            </section>
        </div>
    </article>
</main>

<style>
.atareao-json-summary {
    font-style: italic;
    color: inherit;
}

.json-options-row {
    display: grid;
    grid-template-columns: 12rem 1fr;
    gap: 0.75rem;
}

@media (max-width: 780px) {
    .json-options-row {
        grid-template-columns: 1fr;
    }
}

.atareao-contact-form .json-action {
    display: inline-block;
    border: 0;
    border-radius: 6px;
    background: var(--atareao-accent, #0073aa);
    color: #fff;
    cursor: pointer;
    font-size: 0.9rem;
    line-height: 1.2;
    padding: 0.35rem 0.6rem;
    margin: 0 0.25rem 0.25rem 0;
}

.atareao-contact-form .json-action:hover {
    filter: brightness(0.94);
}

.json-security-note {
    margin-top: 0.45rem;
    font-size: 0.9rem;
    opacity: 0.85;
}

.json-output {
    border: 1px solid #d7dbe2;
    border-radius: 8px;
    background: #f7fafd;
    color: #222;
    padding: 0.85rem;
    padding-top: calc(0.85rem + 40px);
    white-space: pre-wrap;
    word-break: break-word;
    min-height: 8rem;
}

.atareao-tools-breadcrumb {
    width: 80%;
    margin: 0.45rem auto 0;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.atareao-tools-breadcrumb-link {
    color: #0073aa;
    text-decoration: none;
    font-weight: 600;
}

.atareao-tools-breadcrumb-link:hover {
    text-decoration: underline;
}

.atareao-tools-breadcrumb-sep {
    opacity: 0.7;
}

.atareao-tools-breadcrumb-select {
    width: auto;
    min-width: 13rem;
    margin: 0;
    padding: 0.45rem 0.55rem;
    border: 1.5px solid #c3cfe2;
    border-radius: 8px;
    background: #f7fafd;
    color: #222;
}

.atareao-tool-seo-content {
    width: 80%;
    margin: 1.5rem auto 0;
}

.atareao-tool-seo-content h2 {
    margin: 0 0 0.75rem;
}

.atareao-tool-seo-content h3 {
    margin: 0.95rem 0 0.4rem;
}

[data-theme="dark"] .atareao-json-summary {
    color: #e5e7eb;
}

[data-theme="dark"] .json-output {
    border-color: #44506a;
    background: #1d2538;
    color: #e5e7eb;
}

[data-theme="dark"] .atareao-tools-breadcrumb-link {
    color: #8bc3e6;
}

[data-theme="dark"] .atareao-tools-breadcrumb-select {
    border-color: #2a2a2a;
    background: #151617;
    color: #e6e6e6;
}

@media (prefers-color-scheme: dark) {
    html:not([data-theme="light"]) .atareao-json-summary {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .json-output {
        border-color: #44506a;
        background: #1d2538;
        color: #e5e7eb;
    }
}
</style>

<script>
(function () {
    'use strict';

    var form = document.getElementById('json_form');
    var input = document.getElementById('json_input');
    var output = document.getElementById('json_output');
    var summary = document.getElementById('json_summary');
    var errorBox = document.getElementById('json_error');
    var indentSelect = document.getElementById('json_indent');
    var validateBtn = document.getElementById('json_validate');
    var formatBtn = document.getElementById('json_format');
    var minifyBtn = document.getElementById('json_minify');
    var copyBtn = document.getElementById('json_copy');
    var copyLinkBtn = document.getElementById('json_copy_link');

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function getIndent() {
        if (indentSelect.value === 'tab') {
            return '\t';
        }
        return Number(indentSelect.value) || 2;
    }

    function parseJson(value) {
        return JSON.parse(value);
    }

    function updateShareUrl() {
        var url = new URL(window.location.href);
        url.searchParams.delete('data');
        window.history.replaceState({}, '', url.toString());
        return url.toString();
    }

    function fillFromUrl() {
        updateShareUrl();
    }

    function setOutput(text) {
        output.textContent = text;
    }

    function setSummary(text) {
        summary.textContent = text;
    }

    function doValidate() {
        clearError();
        var value = input.value.trim();

        if (!value) {
            showError('Introduce JSON para validar.');
            return;
        }

        try {
            parseJson(value);
            setSummary('JSON valido.');
            setOutput(value);
            updateShareUrl();
        } catch (err) {
            showError(err.message || 'JSON invalido.');
            setSummary('JSON invalido.');
        }
    }

    function doFormat() {
        clearError();
        var value = input.value.trim();

        if (!value) {
            showError('Introduce JSON para formatear.');
            return;
        }

        try {
            var parsed = parseJson(value);
            var formatted = JSON.stringify(parsed, null, getIndent());
            input.value = formatted;
            setOutput(formatted);
            setSummary('JSON formateado correctamente.');
            updateShareUrl();
        } catch (err) {
            showError(err.message || 'No se pudo formatear el JSON.');
            setSummary('Error de parseo.');
        }
    }

    function doMinify() {
        clearError();
        var value = input.value.trim();

        if (!value) {
            showError('Introduce JSON para minificar.');
            return;
        }

        try {
            var parsed = parseJson(value);
            var minified = JSON.stringify(parsed);
            input.value = minified;
            setOutput(minified);
            setSummary('JSON minificado correctamente.');
            updateShareUrl();
        } catch (err) {
            showError(err.message || 'No se pudo minificar el JSON.');
            setSummary('Error de parseo.');
        }
    }

    validateBtn.addEventListener('click', doValidate);
    formatBtn.addEventListener('click', doFormat);
    minifyBtn.addEventListener('click', doMinify);

    copyBtn.addEventListener('click', function () {
        var text = output.textContent || '';
        if (!text) {
            showError('No hay salida para copiar.');
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                copyBtn.textContent = 'Copiado';
                setTimeout(function () {
                    copyBtn.textContent = 'Copiar salida';
                }, 1500);
            }).catch(function () {
                showError('No se pudo copiar automaticamente.');
            });
            return;
        }

        showError('Tu navegador no permite copiar automaticamente.');
    });

    copyLinkBtn.addEventListener('click', function () {
        var url = updateShareUrl();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                copyLinkBtn.textContent = 'Enlace copiado';
                setTimeout(function () {
                    copyLinkBtn.textContent = 'Copiar enlace';
                }, 1500);
            }).catch(function () {
                showError('No se pudo copiar automaticamente. Enlace: ' + url);
            });
            return;
        }

        showError('Tu navegador no permite copiar automaticamente. Enlace: ' + url);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        doFormat();
    });

    fillFromUrl();
    doValidate();
})();
</script>

<?php
get_footer();
