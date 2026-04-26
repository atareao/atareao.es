<?php
/**
 * Tools - YAML JSON Converter
 *
 * Route: /tools/yaml-json
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/yaml-json/');
$tool_title = 'YAML JSON Converter online | ' . get_bloginfo('name');
$tool_description = 'Convierte YAML a JSON y JSON a YAML, valida sintaxis y formatea salida para flujos DevOps y administracion de sistemas.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'YAML JSON Converter',
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
                    'name' => 'Que hace este conversor YAML JSON?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Convierte YAML a JSON y JSON a YAML con validacion de sintaxis y salida legible.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Es util para Kubernetes y Ansible?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si, es muy util para revisar manifiestos, variables y configuraciones antes de desplegar.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Se envian datos al servidor?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'No. La conversion se realiza en el navegador.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir una conversion?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. El boton Copiar enlace guarda modo y contenido resumido para reproducir el caso.',
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
    <article id="post-tools-yaml-json" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">YAML JSON Converter</h1>
            <?php atareao_tools_render_breadcrumb('yaml-json'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Convierte YAML a JSON y JSON a YAML para depurar configuraciones en Kubernetes, Ansible y pipelines CI/CD.
                </p>
            </div>

            <div id="yj_error" class="atareao-feedback-error" hidden></div>

            <form id="yj_form" class="atareao-contact-form" method="post" action="" novalidate>
                <div class="yj-grid">
                    <div>
                        <label for="yj_mode">Modo de conversion</label>
                        <select id="yj_mode">
                            <option value="yaml-to-json" selected>YAML → JSON</option>
                            <option value="json-to-yaml">JSON → YAML</option>
                        </select>
                    </div>
                    <div>
                        <label>&nbsp;</label>
                        <p>
                            <button type="button" id="yj_convert" class="yj-action">Convertir</button>
                            <button type="button" id="yj_copy_output" class="yj-action">Copiar salida</button>
                            <button type="button" id="yj_copy_link" class="yj-action">Copiar enlace</button>
                        </p>
                    </div>
                </div>

                <div>
                    <label for="yj_input">Entrada</label>
                    <textarea id="yj_input" rows="10" spellcheck="false">app:
  name: atareao
  tools:
    - crontab
    - subnetting
enabled: true</textarea>
                </div>

                <div>
                    <label>Ejemplos rapidos</label>
                    <p>
                        <button type="button" class="yj-example" data-mode="yaml-to-json" data-content="apiVersion: v1\nkind: ConfigMap\nmetadata:\n  name: demo\ndata:\n  key: value">Kubernetes YAML</button>
                        <button type="button" class="yj-example" data-mode="json-to-yaml" data-content='{"service":"nginx","ports":[80,443],"enabled":true}'>JSON API</button>
                    </p>
                </div>

                <section>
                    <label for="yj_summary">Resumen</label>
                    <p id="yj_summary" class="atareao-yj-summary">Listo para convertir.</p>
                </section>

                <section>
                    <label for="yj_output">Salida</label>
                    <pre id="yj_output" class="yj-output" aria-live="polite"></pre>
                </section>
            </form>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de YAML JSON">
                <h2>Preguntas frecuentes</h2>
                <h3>Cuando conviene YAML frente a JSON</h3>
                <p>YAML suele ser mas legible para configuracion humana; JSON es ideal para intercambio de datos y APIs.</p>

                <h3>Errores comunes de conversion</h3>
                <p>En YAML: indentacion inconsistente. En JSON: comillas o comas incorrectas.</p>

                <h3>Uso recomendado en DevOps</h3>
                <p>Valida y convierte antes de desplegar manifiestos para evitar errores en entornos de produccion.</p>

                <h3>Privacidad de la informacion</h3>
                <p>El procesamiento se ejecuta en navegador, sin enviar contenido al servidor.</p>
            </section>
        </div>
    </article>
</main>

<style>
.atareao-yj-summary {
    font-style: italic;
    color: inherit;
}

.yj-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.8rem;
}

@media (max-width: 780px) {
    .yj-grid {
        grid-template-columns: 1fr;
    }
}

.atareao-contact-form .yj-action,
.atareao-contact-form .yj-example {
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

.atareao-contact-form .yj-action:hover,
.atareao-contact-form .yj-example:hover {
    filter: brightness(0.94);
}

.yj-output {
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

[data-theme="dark"] .atareao-yj-summary {
    color: #e5e7eb;
}

[data-theme="dark"] .yj-output {
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
    html:not([data-theme="light"]) .atareao-yj-summary {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .yj-output {
        border-color: #44506a;
        background: #1d2538;
        color: #e5e7eb;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/js-yaml@4.1.0/dist/js-yaml.min.js"></script>
<script>
(function () {
    'use strict';

    var form = document.getElementById('yj_form');
    var modeSelect = document.getElementById('yj_mode');
    var input = document.getElementById('yj_input');
    var output = document.getElementById('yj_output');
    var summary = document.getElementById('yj_summary');
    var errorBox = document.getElementById('yj_error');
    var convertBtn = document.getElementById('yj_convert');
    var copyOutputBtn = document.getElementById('yj_copy_output');
    var copyLinkBtn = document.getElementById('yj_copy_link');
    var exampleButtons = document.querySelectorAll('.yj-example');

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function decodeEscaped(text) {
        return String(text || '').replace(/\\n/g, '\n');
    }

    function updateShareUrl(mode, content) {
        var url = new URL(window.location.href);
        url.searchParams.set('mode', mode);
        url.searchParams.set('data', String(content || '').slice(0, 1200));
        window.history.replaceState({}, '', url.toString());
        return url.toString();
    }

    function fillFromUrl() {
        var url = new URL(window.location.href);
        var mode = url.searchParams.get('mode');
        var data = url.searchParams.get('data');

        if (mode === 'yaml-to-json' || mode === 'json-to-yaml') {
            modeSelect.value = mode;
        }

        if (data !== null) {
            input.value = data;
        }
    }

    function convert() {
        clearError();

        if (!window.jsyaml) {
            showError('No se pudo cargar el parser YAML. Revisa tu conexion e intenta de nuevo.');
            return;
        }

        var mode = modeSelect.value;
        var source = input.value;

        if (!source.trim()) {
            showError('Introduce contenido para convertir.');
            return;
        }

        try {
            var result;

            if (mode === 'yaml-to-json') {
                var yamlObj = window.jsyaml.load(source);
                result = JSON.stringify(yamlObj, null, 2);
                summary.textContent = 'Conversion YAML → JSON completada.';
            } else {
                var jsonObj = JSON.parse(source);
                result = window.jsyaml.dump(jsonObj, {
                    noRefs: true,
                    lineWidth: 120,
                    indent: 2
                });
                summary.textContent = 'Conversion JSON → YAML completada.';
            }

            output.textContent = result;
            updateShareUrl(mode, source);
        } catch (err) {
            showError(err.message || 'No se pudo convertir el contenido.');
            summary.textContent = 'Error de conversion.';
        }
    }

    convertBtn.addEventListener('click', convert);

    copyOutputBtn.addEventListener('click', function () {
        var text = output.textContent || '';
        if (!text) {
            showError('No hay salida para copiar.');
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                copyOutputBtn.textContent = 'Copiado';
                setTimeout(function () {
                    copyOutputBtn.textContent = 'Copiar salida';
                }, 1500);
            }).catch(function () {
                showError('No se pudo copiar automaticamente.');
            });
            return;
        }

        showError('Tu navegador no permite copiar automaticamente.');
    });

    copyLinkBtn.addEventListener('click', function () {
        var url = updateShareUrl(modeSelect.value, input.value);

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

    for (var i = 0; i < exampleButtons.length; i++) {
        exampleButtons[i].addEventListener('click', function () {
            modeSelect.value = this.getAttribute('data-mode') || 'yaml-to-json';
            input.value = decodeEscaped(this.getAttribute('data-content') || '');
            convert();
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        convert();
    });

    fillFromUrl();
    convert();
})();
</script>

<?php
get_footer();
