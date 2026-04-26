<?php
/**
 * Tools - Base64 Encode Decode
 *
 * Route: /tools/base64
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/base64/');
$tool_title = 'Base64 Encode Decode online | ' . get_bloginfo('name');
$tool_description = 'Codifica y decodifica texto en Base64 con opcion URL-safe, util para APIs, tokens y depuracion de payloads.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'Base64 Encode Decode',
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
                    'name' => 'Que hace esta herramienta Base64?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Permite codificar texto a Base64 y decodificar Base64 a texto, incluyendo modo URL-safe.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Que es Base64 URL-safe?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Es una variante de Base64 que reemplaza caracteres para ser segura en URLs y tokens web.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Se guarda el contenido en servidor?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'No. El proceso se realiza localmente en el navegador.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir la configuracion?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. El enlace compartible conserva configuracion basica sin incluir el contenido de entrada.',
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
    <article id="post-tools-base64" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">Base64 Encode Decode</h1>
            <?php atareao_tools_render_breadcrumb('base64'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Codifica y decodifica texto en Base64 para pruebas con APIs, tokens y transporte de datos.
                </p>
            </div>

            <div id="b64_error" class="atareao-feedback-error" hidden></div>

            <form id="b64_form" class="atareao-contact-form" method="post" action="" novalidate>
                <div class="b64-grid">
                    <div>
                        <label for="b64_mode">Operacion</label>
                        <select id="b64_mode">
                            <option value="encode" selected>Texto → Base64</option>
                            <option value="decode">Base64 → Texto</option>
                        </select>
                    </div>
                    <div>
                        <label><input id="b64_urlsafe" type="checkbox"> Modo URL-safe</label>
                    </div>
                </div>

                <div>
                    <label for="b64_input">Entrada</label>
                    <textarea id="b64_input" rows="8" spellcheck="false">atareao-tools: base64 demo</textarea>
                </div>

                <div>
                    <label>Ejemplos rapidos</label>
                    <p>
                        <button type="button" class="b64-example" data-mode="encode" data-urlsafe="0" data-content="hola mundo">Texto simple</button>
                        <button type="button" class="b64-example" data-mode="decode" data-urlsafe="0" data-content="aG9sYSBtdW5kbw==">Decode clasico</button>
                        <button type="button" class="b64-example" data-mode="decode" data-urlsafe="1" data-content="aG9sYS13b3JsZA">Decode URL-safe</button>
                    </p>
                </div>

                <div style="text-align:center;">
                    <button type="submit" id="b64_run" class="b64-action">Procesar</button>
                    <button type="button" id="b64_copy_output" class="b64-action">Copiar salida</button>
                    <button type="button" id="b64_copy_link" class="b64-action">Copiar enlace</button>
                    <p class="b64-security-note">Por seguridad, el enlace compartido no incluye el contenido de entrada.</p>
                </div>

                <section>
                    <label for="b64_summary">Resumen</label>
                    <p id="b64_summary" class="atareao-b64-summary">Listo para procesar.</p>
                </section>

                <section>
                    <label for="b64_output">Salida</label>
                    <pre id="b64_output" class="b64-output" aria-live="polite"></pre>
                </section>
            </form>

            <section class="atareao-tool-seo-content" aria-label="Guia rapida de Base64">
                <h2>Guia rapida de uso</h2>
                <h3>1. Elige operacion</h3>
                <p>Selecciona encode para convertir texto a Base64 o decode para recuperar texto original.</p>

                <h3>2. Activa URL-safe si corresponde</h3>
                <p>Usa URL-safe para tokens o parametros web que no admiten caracteres Base64 clasicos.</p>

                <h3>3. Copia y reutiliza</h3>
                <p>Exporta la salida para scripts, pruebas API o validaciones de integracion.</p>
            </section>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de Base64">
                <h2>Preguntas frecuentes</h2>
                <h3>Base64 cifra datos</h3>
                <p>No. Base64 solo codifica; no aporta cifrado ni confidencialidad.</p>

                <h3>Cuando usar URL-safe</h3>
                <p>Es recomendable en tokens web, enlaces y parametros donde +, / o = pueden dar problemas.</p>

                <h3>Por que falla al decodificar</h3>
                <p>Normalmente por caracteres invalidos o por padding incompleto en la cadena Base64.</p>

                <h3>Privacidad de la herramienta</h3>
                <p>El procesamiento es local y el enlace compartido no adjunta contenido sensible.</p>
            </section>
        </div>
    </article>
</main>

<style>
.atareao-b64-summary {
    font-style: italic;
    color: inherit;
}

.b64-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.8rem;
}

@media (max-width: 780px) {
    .b64-grid {
        grid-template-columns: 1fr;
    }
}

.atareao-contact-form .b64-action,
.atareao-contact-form .b64-example {
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

.atareao-contact-form .b64-action:hover,
.atareao-contact-form .b64-example:hover {
    filter: brightness(0.94);
}

.b64-security-note {
    margin-top: 0.45rem;
    font-size: 0.9rem;
    opacity: 0.85;
}

.b64-output {
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

[data-theme="dark"] .atareao-b64-summary {
    color: #e5e7eb;
}

[data-theme="dark"] .b64-output {
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
    html:not([data-theme="light"]) .atareao-b64-summary {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .b64-output {
        border-color: #44506a;
        background: #1d2538;
        color: #e5e7eb;
    }
}
</style>

<script>
(function () {
    'use strict';

    var form = document.getElementById('b64_form');
    var modeSelect = document.getElementById('b64_mode');
    var urlsafeInput = document.getElementById('b64_urlsafe');
    var input = document.getElementById('b64_input');
    var output = document.getElementById('b64_output');
    var summary = document.getElementById('b64_summary');
    var errorBox = document.getElementById('b64_error');
    var copyOutputBtn = document.getElementById('b64_copy_output');
    var copyLinkBtn = document.getElementById('b64_copy_link');
    var exampleButtons = document.querySelectorAll('.b64-example');

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function textToBase64(text) {
        var bytes = new TextEncoder().encode(text);
        var binary = '';
        for (var i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    function base64ToText(base64) {
        var binary = atob(base64);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return new TextDecoder('utf-8', { fatal: false }).decode(bytes);
    }

    function toUrlSafe(base64) {
        return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    }

    function fromUrlSafe(base64url) {
        var normalized = base64url.replace(/-/g, '+').replace(/_/g, '/');
        var mod = normalized.length % 4;
        if (mod === 2) {
            normalized += '==';
        } else if (mod === 3) {
            normalized += '=';
        } else if (mod !== 0) {
            throw new Error('Cadena Base64 URL-safe invalida.');
        }
        return normalized;
    }

    function updateShareUrl() {
        var url = new URL(window.location.href);
        url.searchParams.set('mode', modeSelect.value || 'encode');
        url.searchParams.set('urlsafe', urlsafeInput.checked ? '1' : '0');
        url.searchParams.delete('data');
        window.history.replaceState({}, '', url.toString());
        return url.toString();
    }

    function fillFromUrl() {
        var url = new URL(window.location.href);
        var mode = url.searchParams.get('mode');
        var urlsafe = url.searchParams.get('urlsafe');

        if (mode === 'encode' || mode === 'decode') {
            modeSelect.value = mode;
        }

        urlsafeInput.checked = (urlsafe === '1');
    }

    function run() {
        clearError();

        var source = input.value;
        if (!source) {
            showError('Introduce contenido para procesar.');
            return;
        }

        try {
            var result;
            if (modeSelect.value === 'encode') {
                result = textToBase64(source);
                if (urlsafeInput.checked) {
                    result = toUrlSafe(result);
                }
                summary.textContent = 'Texto codificado a Base64 correctamente.';
            } else {
                var incoming = source.trim();
                if (urlsafeInput.checked) {
                    incoming = fromUrlSafe(incoming);
                }
                result = base64ToText(incoming);
                summary.textContent = 'Base64 decodificado a texto correctamente.';
            }

            output.textContent = result;
            updateShareUrl();
        } catch (err) {
            showError(err.message || 'No se pudo procesar el contenido.');
            summary.textContent = 'Error en la conversion.';
        }
    }

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

    for (var i = 0; i < exampleButtons.length; i++) {
        exampleButtons[i].addEventListener('click', function () {
            modeSelect.value = this.getAttribute('data-mode') || 'encode';
            urlsafeInput.checked = this.getAttribute('data-urlsafe') === '1';
            input.value = this.getAttribute('data-content') || '';
            run();
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        run();
    });

    fillFromUrl();
    run();
})();
</script>

<?php
get_footer();
