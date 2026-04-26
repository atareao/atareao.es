<?php
/**
 * Tools - UUID Generator
 *
 * Route: /tools/uuid
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/uuid/');
$tool_title = 'UUID Generator online v4 | ' . get_bloginfo('name');
$tool_description = 'Generador UUID v4 para desarrollo y administracion de sistemas. Crea UUID en lote, en mayusculas o minusculas, y copia resultados al portapapeles.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'UUID Generator',
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
                    'name' => 'Que version de UUID genera esta herramienta?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Genera UUID v4 aleatorios usando APIs criptograficamente seguras del navegador cuando estan disponibles.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo generar varios UUID a la vez?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. Puedes generar lotes y copiarlos en formato lista para usar en scripts, pruebas o importaciones.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Se guardan los UUID en servidor?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'No. Todo el proceso ocurre en el navegador y no se envia el contenido generado al servidor.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir la configuracion?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. Copiar enlace comparte solo la configuracion de generacion, no los UUID resultantes.',
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
    <article id="post-tools-uuid" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">UUID Generator</h1>
            <?php atareao_tools_render_breadcrumb('uuid'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Genera UUID v4 para bases de datos, APIs, colas y pruebas de integracion sin salir del navegador.
                </p>
            </div>

            <div id="uuid_error" class="atareao-feedback-error" hidden></div>

            <form id="uuid_form" class="atareao-contact-form" method="post" action="" novalidate>
                <div class="uuid-grid">
                    <div>
                        <label for="uuid_count">Cantidad</label>
                        <input id="uuid_count" type="number" min="1" max="200" value="10">
                    </div>
                    <div>
                        <label for="uuid_case">Formato</label>
                        <select id="uuid_case">
                            <option value="lower" selected>Minusculas</option>
                            <option value="upper">Mayusculas</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label>Ejemplos rapidos</label>
                    <p>
                        <button type="button" class="uuid-example" data-count="1" data-case="lower">1 UUID</button>
                        <button type="button" class="uuid-example" data-count="10" data-case="lower">10 UUID</button>
                        <button type="button" class="uuid-example" data-count="50" data-case="lower">50 UUID</button>
                        <button type="button" class="uuid-example" data-count="10" data-case="upper">10 en MAYUSCULAS</button>
                    </p>
                </div>

                <div style="text-align:center;">
                    <button type="submit" id="uuid_generate" class="uuid-action">Generar</button>
                    <button type="button" id="uuid_copy_output" class="uuid-action">Copiar salida</button>
                    <button type="button" id="uuid_copy_link" class="uuid-action">Copiar enlace</button>
                    <p class="uuid-security-note">Por seguridad, el enlace compartido solo incluye configuracion, no UUID generados.</p>
                </div>

                <section>
                    <label for="uuid_summary">Resumen</label>
                    <p id="uuid_summary" class="atareao-uuid-summary">Listo para generar UUID.</p>
                </section>

                <section>
                    <label for="uuid_output">UUID generados</label>
                    <pre id="uuid_output" class="uuid-output" aria-live="polite"></pre>
                </section>
            </form>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de UUID">
                <h2>Preguntas frecuentes</h2>
                <h3>Para que sirve un UUID v4</h3>
                <p>Es util para identificadores unicos en sistemas distribuidos sin coordinacion central.</p>

                <h3>Cuantos UUID puedo generar de una vez</h3>
                <p>Puedes generar lotes para pruebas, importaciones y sembrado de datos sin repetir valores.</p>

                <h3>Mayusculas o minusculas</h3>
                <p>Ambos formatos representan el mismo UUID. Elige segun convencion de tu proyecto o base de datos.</p>

                <h3>Privacidad y seguridad</h3>
                <p>La generacion es local en navegador y el enlace compartible no incluye los UUID resultantes.</p>
            </section>
        </div>
    </article>
</main>

<style>
.atareao-uuid-summary {
    font-style: italic;
    color: inherit;
}

.uuid-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.8rem;
}

@media (max-width: 780px) {
    .uuid-grid {
        grid-template-columns: 1fr;
    }
}

.atareao-contact-form .uuid-action,
.atareao-contact-form .uuid-example {
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

.atareao-contact-form .uuid-action:hover,
.atareao-contact-form .uuid-example:hover {
    filter: brightness(0.94);
}

.uuid-security-note {
    margin-top: 0.45rem;
    font-size: 0.9rem;
    opacity: 0.85;
}

.uuid-output {
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

[data-theme="dark"] .atareao-uuid-summary {
    color: #e5e7eb;
}

[data-theme="dark"] .uuid-output {
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
    html:not([data-theme="light"]) .atareao-uuid-summary {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .uuid-output {
        border-color: #44506a;
        background: #1d2538;
        color: #e5e7eb;
    }
}
</style>

<script>
(function () {
    'use strict';

    var form = document.getElementById('uuid_form');
    var countInput = document.getElementById('uuid_count');
    var caseSelect = document.getElementById('uuid_case');
    var output = document.getElementById('uuid_output');
    var summary = document.getElementById('uuid_summary');
    var errorBox = document.getElementById('uuid_error');
    var copyOutputBtn = document.getElementById('uuid_copy_output');
    var copyLinkBtn = document.getElementById('uuid_copy_link');
    var exampleButtons = document.querySelectorAll('.uuid-example');

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function updateShareUrl() {
        var url = new URL(window.location.href);
        var count = String(countInput.value || '10');
        var format = caseSelect.value || 'lower';

        url.searchParams.set('count', count);
        url.searchParams.set('format', format);
        url.searchParams.delete('data');

        window.history.replaceState({}, '', url.toString());
        return url.toString();
    }

    function fillFromUrl() {
        var url = new URL(window.location.href);
        var count = Number(url.searchParams.get('count'));
        var format = url.searchParams.get('format');

        if (Number.isInteger(count) && count >= 1 && count <= 200) {
            countInput.value = String(count);
        }

        if (format === 'lower' || format === 'upper') {
            caseSelect.value = format;
        }
    }

    function fallbackUuidV4() {
        var bytes = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
        } else {
            for (var i = 0; i < 16; i++) {
                bytes[i] = Math.floor(Math.random() * 256);
            }
        }

        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;

        var hex = [];
        for (var j = 0; j < bytes.length; j++) {
            hex.push(bytes[j].toString(16).padStart(2, '0'));
        }

        return [
            hex.slice(0, 4).join(''),
            hex.slice(4, 6).join(''),
            hex.slice(6, 8).join(''),
            hex.slice(8, 10).join(''),
            hex.slice(10, 16).join('')
        ].join('-');
    }

    function createUuid() {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }
        return fallbackUuidV4();
    }

    function generate() {
        clearError();

        var count = Number(countInput.value);
        if (!Number.isInteger(count) || count < 1 || count > 200) {
            showError('La cantidad debe estar entre 1 y 200.');
            return;
        }

        var format = caseSelect.value;
        var lines = [];
        for (var i = 0; i < count; i++) {
            var id = createUuid();
            lines.push(format === 'upper' ? id.toUpperCase() : id.toLowerCase());
        }

        output.textContent = lines.join('\n');
        summary.textContent = 'Generados ' + count + ' UUID v4 en formato ' + (format === 'upper' ? 'MAYUSCULAS' : 'minusculas') + '.';

        updateShareUrl();
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
            countInput.value = this.getAttribute('data-count') || '10';
            caseSelect.value = this.getAttribute('data-case') || 'lower';
            generate();
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        generate();
    });

    fillFromUrl();
    generate();
})();
</script>

<?php
get_footer();
