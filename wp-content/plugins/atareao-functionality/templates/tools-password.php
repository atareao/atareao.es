<?php
/**
 * Tools - Password Generator
 *
 * Route: /tools/password
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/password/');
$tool_title = 'Password Generator seguro online | ' . get_bloginfo('name');
$tool_description = 'Generador de contrasenas seguras con longitud configurable, lotes, opciones de caracteres y estimacion de entropia para administradores y desarrolladores.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'Password Generator',
            'url' => $tool_url,
            'applicationCategory' => 'SecurityApplication',
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
                    'name' => 'Que hace este generador de contrasenas?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Genera contrasenas aleatorias robustas con configuracion de longitud y tipos de caracteres.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Como se calcula la fortaleza?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Se estima con la entropia en bits segun el tamano del alfabeto y la longitud de la contrasena.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Se envian las contrasenas al servidor?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'No. La generacion se realiza de forma local en el navegador.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir la configuracion?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. Copiar enlace comparte solo la configuracion y no las contrasenas generadas.',
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
    <article id="post-tools-password" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">Password Generator</h1>
            <?php atareao_tools_render_breadcrumb('password'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Genera contrasenas seguras para cuentas, servicios y entornos de administracion con controles de longitud y complejidad.
                </p>
            </div>

            <div id="pw_error" class="atareao-feedback-error" hidden></div>

            <form id="pw_form" class="atareao-contact-form" method="post" action="" novalidate>
                <div class="pw-grid">
                    <div>
                        <label for="pw_length">Longitud</label>
                        <input id="pw_length" type="number" min="8" max="128" value="20">
                    </div>
                    <div>
                        <label for="pw_count">Cantidad</label>
                        <input id="pw_count" type="number" min="1" max="100" value="10">
                    </div>
                </div>

                <div>
                    <label>Conjunto de caracteres</label>
                    <p class="pw-options">
                        <label><input id="pw_lower" type="checkbox" checked> minusculas (a-z)</label>
                        <label><input id="pw_upper" type="checkbox" checked> mayusculas (A-Z)</label>
                        <label><input id="pw_digits" type="checkbox" checked> numeros (0-9)</label>
                        <label><input id="pw_symbols" type="checkbox"> simbolos (!@#...)</label>
                        <label><input id="pw_avoid_ambiguous" type="checkbox"> evitar ambiguos (O/0, l/1...)</label>
                    </p>
                </div>

                <div>
                    <label>Ejemplos rapidos</label>
                    <p>
                        <button type="button" class="pw-example" data-length="16" data-count="5" data-symbols="0">16 chars</button>
                        <button type="button" class="pw-example" data-length="24" data-count="10" data-symbols="1">24 chars + simbolos</button>
                        <button type="button" class="pw-example" data-length="32" data-count="3" data-symbols="1">32 chars high entropy</button>
                    </p>
                </div>

                <div style="text-align:center;">
                    <button type="submit" id="pw_generate" class="pw-action">Generar</button>
                    <button type="button" id="pw_copy_output" class="pw-action">Copiar salida</button>
                    <button type="button" id="pw_copy_link" class="pw-action">Copiar enlace</button>
                    <p class="pw-security-note">Por seguridad, el enlace compartido no incluye contrasenas generadas.</p>
                </div>

                <section>
                    <label for="pw_summary">Resumen</label>
                    <p id="pw_summary" class="atareao-pw-summary">Listo para generar contrasenas.</p>
                </section>

                <section>
                    <label for="pw_output">Contrasenas generadas</label>
                    <pre id="pw_output" class="pw-output" aria-live="polite"></pre>
                </section>
            </form>

            <section class="atareao-tool-seo-content" aria-label="Guia rapida de contrasenas">
                <h2>Guia rapida de uso</h2>
                <h3>1. Ajusta longitud y lote</h3>
                <p>Selecciona una longitud adecuada y el numero de contrasenas necesarias para tu escenario.</p>

                <h3>2. Define alfabeto seguro</h3>
                <p>Activa mayusculas, numeros y simbolos para aumentar entropia, y evita ambiguos si necesitas lectura manual.</p>

                <h3>3. Evalua entropia estimada</h3>
                <p>Usa el indicador de bits para comprobar que la fuerza se ajusta a politicas de seguridad de tu organizacion.</p>
            </section>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de password">
                <h2>Preguntas frecuentes</h2>
                <h3>Que longitud se recomienda</h3>
                <p>Para uso general, 16 o mas caracteres suele ofrecer un buen margen de seguridad.</p>

                <h3>Cuando activar simbolos</h3>
                <p>Activalos en servicios que lo permitan para aumentar el alfabeto y la entropia total.</p>

                <h3>Evitar caracteres ambiguos</h3>
                <p>Es util cuando se deben transcribir contrasenas manualmente para reducir errores de lectura.</p>

                <h3>Privacidad y uso seguro</h3>
                <p>La generacion ocurre en navegador y el enlace compartible no incluye contrasenas resultantes.</p>
            </section>
        </div>
    </article>
</main>

<style>
.atareao-pw-summary {
    font-style: italic;
    color: inherit;
}

.pw-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.8rem;
}

@media (max-width: 780px) {
    .pw-grid {
        grid-template-columns: 1fr;
    }
}

.pw-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.45rem 1rem;
}

.pw-options input[type="checkbox"] {
    width: auto;
    margin: 0 0.35rem 0 0;
}

@media (max-width: 780px) {
    .pw-options {
        grid-template-columns: 1fr;
    }
}

.atareao-contact-form .pw-action,
.atareao-contact-form .pw-example {
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

.atareao-contact-form .pw-action:hover,
.atareao-contact-form .pw-example:hover {
    filter: brightness(0.94);
}

.pw-security-note {
    margin-top: 0.45rem;
    font-size: 0.9rem;
    opacity: 0.85;
}

.pw-output {
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

[data-theme="dark"] .atareao-pw-summary {
    color: #e5e7eb;
}

[data-theme="dark"] .pw-output {
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
    html:not([data-theme="light"]) .atareao-pw-summary {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .pw-output {
        border-color: #44506a;
        background: #1d2538;
        color: #e5e7eb;
    }
}
</style>

<script>
(function () {
    'use strict';

    var form = document.getElementById('pw_form');
    var lengthInput = document.getElementById('pw_length');
    var countInput = document.getElementById('pw_count');
    var lowerInput = document.getElementById('pw_lower');
    var upperInput = document.getElementById('pw_upper');
    var digitsInput = document.getElementById('pw_digits');
    var symbolsInput = document.getElementById('pw_symbols');
    var ambiguousInput = document.getElementById('pw_avoid_ambiguous');
    var output = document.getElementById('pw_output');
    var summary = document.getElementById('pw_summary');
    var errorBox = document.getElementById('pw_error');
    var copyOutputBtn = document.getElementById('pw_copy_output');
    var copyLinkBtn = document.getElementById('pw_copy_link');
    var exampleButtons = document.querySelectorAll('.pw-example');

    var CHARS = {
        lower: 'abcdefghijklmnopqrstuvwxyz',
        upper: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        digits: '0123456789',
        symbols: '!@#$%^&*()_+-=[]{}|;:,.<>?~'
    };

    var AMBIGUOUS = 'O0oIl1|';

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function randomInt(max) {
        if (window.crypto && window.crypto.getRandomValues) {
            var arr = new Uint32Array(1);
            window.crypto.getRandomValues(arr);
            return arr[0] % max;
        }
        return Math.floor(Math.random() * max);
    }

    function getAlphabet() {
        var alphabet = '';
        if (lowerInput.checked) {
            alphabet += CHARS.lower;
        }
        if (upperInput.checked) {
            alphabet += CHARS.upper;
        }
        if (digitsInput.checked) {
            alphabet += CHARS.digits;
        }
        if (symbolsInput.checked) {
            alphabet += CHARS.symbols;
        }

        if (!alphabet) {
            throw new Error('Debes seleccionar al menos un tipo de caracter.');
        }

        if (ambiguousInput.checked) {
            alphabet = alphabet.split('').filter(function (ch) {
                return AMBIGUOUS.indexOf(ch) === -1;
            }).join('');

            if (!alphabet) {
                throw new Error('No quedan caracteres disponibles tras filtrar ambiguos.');
            }
        }

        return alphabet;
    }

    function generatePassword(length, alphabet) {
        var chars = [];
        for (var i = 0; i < length; i++) {
            chars.push(alphabet.charAt(randomInt(alphabet.length)));
        }
        return chars.join('');
    }

    function estimateEntropy(length, alphabetSize) {
        return length * Math.log2(alphabetSize);
    }

    function updateShareUrl() {
        var url = new URL(window.location.href);
        url.searchParams.set('length', String(lengthInput.value || '20'));
        url.searchParams.set('count', String(countInput.value || '10'));
        url.searchParams.set('lower', lowerInput.checked ? '1' : '0');
        url.searchParams.set('upper', upperInput.checked ? '1' : '0');
        url.searchParams.set('digits', digitsInput.checked ? '1' : '0');
        url.searchParams.set('symbols', symbolsInput.checked ? '1' : '0');
        url.searchParams.set('avoidAmbiguous', ambiguousInput.checked ? '1' : '0');
        url.searchParams.delete('data');
        window.history.replaceState({}, '', url.toString());
        return url.toString();
    }

    function fillFromUrl() {
        var url = new URL(window.location.href);
        var length = Number(url.searchParams.get('length'));
        var count = Number(url.searchParams.get('count'));

        if (Number.isInteger(length) && length >= 8 && length <= 128) {
            lengthInput.value = String(length);
        }

        if (Number.isInteger(count) && count >= 1 && count <= 100) {
            countInput.value = String(count);
        }

        lowerInput.checked = url.searchParams.get('lower') !== '0';
        upperInput.checked = url.searchParams.get('upper') !== '0';
        digitsInput.checked = url.searchParams.get('digits') !== '0';
        symbolsInput.checked = url.searchParams.get('symbols') === '1';
        ambiguousInput.checked = url.searchParams.get('avoidAmbiguous') === '1';
    }

    function generate() {
        clearError();

        var length = Number(lengthInput.value);
        var count = Number(countInput.value);

        if (!Number.isInteger(length) || length < 8 || length > 128) {
            showError('La longitud debe estar entre 8 y 128.');
            return;
        }

        if (!Number.isInteger(count) || count < 1 || count > 100) {
            showError('La cantidad debe estar entre 1 y 100.');
            return;
        }

        var alphabet;
        try {
            alphabet = getAlphabet();
        } catch (err) {
            showError(err.message || 'Configuracion invalida.');
            return;
        }

        var lines = [];
        for (var i = 0; i < count; i++) {
            lines.push(generatePassword(length, alphabet));
        }

        output.textContent = lines.join('\n');

        var entropy = estimateEntropy(length, alphabet.length);
        summary.textContent = 'Generadas ' + count + ' contrasenas. Entropia estimada por contrasena: ' + entropy.toFixed(1) + ' bits.';

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
            lengthInput.value = this.getAttribute('data-length') || '20';
            countInput.value = this.getAttribute('data-count') || '10';
            symbolsInput.checked = this.getAttribute('data-symbols') === '1';
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
