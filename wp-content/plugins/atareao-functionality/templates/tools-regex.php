<?php
/**
 * Tools - Regex Tester
 *
 * Route: /tools/regex
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/regex/');
$tool_title = 'Regex Tester: prueba expresiones regulares online | ' . get_bloginfo('name');
$tool_description = 'Prueba expresiones regulares con flags, visualiza coincidencias y grupos capturados, y comparte resultados con URL.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'Regex Tester',
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
                    'name' => 'Que hace esta herramienta de regex?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Permite probar patrones regex con flags y ver coincidencias y grupos capturados en tiempo real.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Que flags soporta?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Soporta g para busqueda global, i para ignorar mayusculas y minusculas, m para modo multilinea en inicio y fin de linea, s para que el punto coincida tambien con saltos de linea y u para modo Unicode.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir una prueba concreta?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. Con Copiar enlace se genera una URL con patron, flags y texto de ejemplo.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Por que a veces no aparece ningun resultado?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Puede ocurrir por un patron demasiado restrictivo, por flags no adecuadas o por no usar la flag g cuando buscas multiples coincidencias.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Que diferencia hay entre coincidencia y grupo?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'La coincidencia completa es el texto que cumple el patron. Los grupos son subpartes capturadas entre parentesis.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Se guardan mis datos al usar la herramienta?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'No. El analisis se hace en el navegador y solo se incluye estado en la URL si pulsas Copiar enlace.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Como probar regex multilinea correctamente?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Activa m para que ^ y $ funcionen por linea y combina con s si necesitas que el punto tambien coincida con saltos de linea.',
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
    <article id="post-tools-regex" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">Regex Tester</h1>
            <?php atareao_tools_render_breadcrumb('regex'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Prueba patrones regex de JavaScript, valida flags y visualiza coincidencias y grupos capturados.
                </p>
            </div>

            <div id="regex_error" class="atareao-feedback-error" hidden></div>

            <form id="regex_form" class="atareao-contact-form" method="post" action="" novalidate>
                <div>
                    <label for="regex_pattern">Patron regex</label>
                    <input id="regex_pattern" type="text" value="\b[A-Z]{2,}\b" placeholder="\b[A-Z]{2,}\b" autocomplete="off" spellcheck="false">
                </div>

                <div>
                    <label>Flags</label>
                    <p class="regex-flag-list">
                        <label><input type="checkbox" class="regex-flag" value="g" checked> g</label>
                        <label><input type="checkbox" class="regex-flag" value="i"> i</label>
                        <label><input type="checkbox" class="regex-flag" value="m"> m</label>
                        <label><input type="checkbox" class="regex-flag" value="s"> s</label>
                        <label><input type="checkbox" class="regex-flag" value="u"> u</label>
                    </p>
                </div>

                <div>
                    <label for="regex_subject">Texto de prueba</label>
                    <textarea id="regex_subject" rows="7" spellcheck="false">ERROR: fallo de LOGIN en API
INFO: backup correcto
WARN: token caducado</textarea>
                </div>

                <div>
                    <label>Ejemplos rapidos</label>
                    <p>
                        <button type="button" class="regex-example" data-pattern="\b[A-Z]{2,}\b" data-flags="g" data-subject="ERROR: fallo de LOGIN en API\nINFO: backup correcto\nWARN: token caducado">Mayusculas</button>
                        <button type="button" class="regex-example" data-pattern="\b\d{1,3}(?:\.\d{1,3}){3}\b" data-flags="g" data-subject="Server A: 192.168.1.12\nServer B: 10.0.0.20">IPv4</button>
                        <button type="button" class="regex-example" data-pattern="(?:[a-z0-9._%+-]+)@(?:[a-z0-9.-]+)\.[a-z]{2,}" data-flags="gi" data-subject="Contactos: admin@example.com, soporte@atareao.es">Email</button>
                        <button type="button" class="regex-example" data-pattern="(ERROR|WARN):\s(.+)" data-flags="gm" data-subject="ERROR: disco lleno\nINFO: ok\nWARN: memoria alta">Grupos</button>
                    </p>
                </div>

                <div style="text-align:center;">
                    <button type="submit" id="regex_analyze">Probar regex</button>
                    <button type="button" id="regex_copy_link" class="regex-example">Copiar enlace</button>
                </div>

                <section>
                    <label for="regex_summary">Resumen</label>
                    <p id="regex_summary" class="atareao-regex-summary">Sin resultados aun.</p>
                </section>

                <section>
                    <label for="regex_matches">Coincidencias resaltadas</label>
                    <pre id="regex_matches" class="regex-highlight" aria-live="polite"></pre>
                </section>

                <section>
                    <label>Grupos capturados</label>
                    <div class="regex-results-table-wrap">
                        <table class="regex-results-table" id="regex_groups_table">
                            <thead>
                                <tr>
                                    <th scope="col">Match</th>
                                    <th scope="col">Grupo</th>
                                    <th scope="col">Valor</th>
                                </tr>
                            </thead>
                            <tbody id="regex_groups"></tbody>
                        </table>
                    </div>
                </section>
            </form>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de regex">
                <h2>Preguntas frecuentes</h2>
                <h3>Que motor regex se utiliza</h3>
                <p>Se usa el motor de expresiones regulares de JavaScript del navegador.</p>

                <h3>Que hace cada flag regex</h3>
                <p><strong>g</strong>: busqueda global de todas las coincidencias.</p>
                <p><strong>i</strong>: ignora mayusculas y minusculas.</p>
                <p><strong>m</strong>: modo multilinea para que <code>^</code> y <code>$</code> funcionen por linea.</p>
                <p><strong>s</strong>: permite que <code>.</code> coincida tambien con saltos de linea.</p>
                <p><strong>u</strong>: activa modo Unicode para un tratamiento correcto de caracteres extendidos.</p>

                <h3>Por que no veo coincidencias</h3>
                <p>Revisa si el patron es demasiado estricto o si necesitas activar la flag <code>g</code> para encontrar mas de una coincidencia.</p>

                <h3>Coincidencia completa vs grupos capturados</h3>
                <p>La coincidencia completa es todo el texto que cumple la regex, mientras que los grupos son partes internas definidas con parentesis.</p>

                <h3>Como depurar una regex paso a paso</h3>
                <p>Empieza con un patron simple, verifica una coincidencia y luego anade grupos o cuantificadores de forma progresiva.</p>

                <h3>Privacidad del contenido probado</h3>
                <p>El calculo se ejecuta en el navegador. Solo se genera una URL compartible cuando pulsas Copiar enlace.</p>

                <h3>Uso de multilinea y saltos de linea</h3>
                <p>Usa <code>m</code> cuando quieras que <code>^</code> y <code>$</code> evalen cada linea, y <code>s</code> cuando el punto deba atravesar saltos de linea.</p>

                <h3>Como compartir una prueba</h3>
                <p>Con Copiar enlace guardas patron, flags y texto en la URL para abrir exactamente el mismo estado.</p>
            </section>
        </div>
    </article>
</main>

<style>
.atareao-regex-summary {
    font-style: italic;
    color: inherit;
}

.regex-flag-list {
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.regex-flag-list label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.regex-flag-list input[type="checkbox"] {
    width: auto;
    margin: 0;
}

.atareao-contact-form .regex-example {
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

.atareao-contact-form .regex-example:hover {
    filter: brightness(0.94);
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

[data-theme="dark"] .atareao-tools-breadcrumb-link {
    color: #8bc3e6;
}

[data-theme="dark"] .atareao-tools-breadcrumb-select {
    border-color: #2a2a2a;
    background: #151617;
    color: #e6e6e6;
}

.regex-highlight {
    border: 1px solid #d7dbe2;
    border-radius: 8px;
    background: #f7fafd;
    color: #222;
    padding: 0.85rem;
    padding-top: calc(0.85rem + 40px);
    white-space: pre-wrap;
    word-break: break-word;
    min-height: 6rem;
}

.regex-highlight mark {
    background: #ffe082;
    color: #111;
    padding: 0;
}

.regex-results-table-wrap {
    overflow-x: auto;
}

.regex-results-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}

.regex-results-table th,
.regex-results-table td {
    padding: 0.5rem 0.55rem;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
    color: #222222;
}

.regex-results-table th {
    font-weight: 700;
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

[data-theme="dark"] .atareao-regex-summary {
    color: #e5e7eb;
}

[data-theme="dark"] .regex-highlight {
    border-color: #44506a;
    background: #1d2538;
    color: #e5e7eb;
}

[data-theme="dark"] .regex-highlight mark {
    background: #ffd54f;
    color: #111;
}

[data-theme="dark"] .regex-results-table th,
[data-theme="dark"] .regex-results-table td {
    border-bottom-color: #334155;
    color: #e5e7eb;
}

@media (prefers-color-scheme: dark) {
    html:not([data-theme="light"]) .atareao-regex-summary {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .regex-highlight {
        border-color: #44506a;
        background: #1d2538;
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .regex-highlight mark {
        background: #ffd54f;
        color: #111;
    }

    html:not([data-theme="light"]) .regex-results-table th,
    html:not([data-theme="light"]) .regex-results-table td {
        color: #e5e7eb;
    }
}
</style>

<script>
(function () {
    'use strict';

    var patternInput = document.getElementById('regex_pattern');
    var subjectInput = document.getElementById('regex_subject');
    var form = document.getElementById('regex_form');
    var summary = document.getElementById('regex_summary');
    var highlighted = document.getElementById('regex_matches');
    var groupsBox = document.getElementById('regex_groups');
    var errorBox = document.getElementById('regex_error');
    var copyLinkBtn = document.getElementById('regex_copy_link');
    var exampleButtons = document.querySelectorAll('.regex-example[data-pattern]');
    var flagInputs = document.querySelectorAll('.regex-flag');

    function getFlags() {
        var flags = '';
        for (var i = 0; i < flagInputs.length; i++) {
            if (flagInputs[i].checked) {
                flags += flagInputs[i].value;
            }
        }
        return flags;
    }

    function setFlags(flags) {
        var normalized = flags || '';
        for (var i = 0; i < flagInputs.length; i++) {
            flagInputs[i].checked = normalized.indexOf(flagInputs[i].value) !== -1;
        }
    }

    function decodeEscapedText(text) {
        return String(text || '')
            .replace(/\\r\\n/g, '\n')
            .replace(/\\n/g, '\n');
    }

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function highlightMatches(subject, matchIndexes) {
        if (!matchIndexes.length) {
            return escapeHtml(subject);
        }

        var out = '';
        var cursor = 0;

        for (var i = 0; i < matchIndexes.length; i++) {
            var start = matchIndexes[i].start;
            var end = matchIndexes[i].end;
            out += escapeHtml(subject.slice(cursor, start));
            out += '<mark>' + escapeHtml(subject.slice(start, end)) + '</mark>';
            cursor = end;
        }

        out += escapeHtml(subject.slice(cursor));
        return out;
    }

    function renderGroups(matches) {
        groupsBox.innerHTML = '';

        var hasGroups = false;
        for (var i = 0; i < matches.length; i++) {
            for (var g = 1; g < matches[i].groups.length; g++) {
                hasGroups = true;
                var tr = document.createElement('tr');

                var matchTd = document.createElement('td');
                matchTd.textContent = '#' + (i + 1);

                var groupTd = document.createElement('td');
                groupTd.textContent = '$' + g;

                var valueTd = document.createElement('td');
                valueTd.textContent = matches[i].groups[g] === undefined ? '(sin valor)' : matches[i].groups[g];

                tr.appendChild(matchTd);
                tr.appendChild(groupTd);
                tr.appendChild(valueTd);
                groupsBox.appendChild(tr);
            }
        }

        if (!hasGroups) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 3;
            emptyCell.textContent = 'No hay grupos capturados en los resultados actuales.';
            emptyRow.appendChild(emptyCell);
            groupsBox.appendChild(emptyRow);
        }
    }

    function updateShareUrl(pattern, flags, subject) {
        var url = new URL(window.location.href);
        url.searchParams.set('pattern', pattern);
        url.searchParams.set('flags', flags);
        url.searchParams.set('subject', subject.slice(0, 600));
        window.history.replaceState({}, '', url.toString());
        return url.toString();
    }

    function fillFromUrl() {
        var url = new URL(window.location.href);
        var pattern = url.searchParams.get('pattern');
        var flags = url.searchParams.get('flags');
        var subject = url.searchParams.get('subject');

        if (pattern !== null) {
            patternInput.value = pattern;
        }

        if (flags !== null) {
            setFlags(flags);
        }

        if (subject !== null) {
            subjectInput.value = subject;
        }
    }

    function execute() {
        clearError();

        var pattern = patternInput.value;
        var flags = getFlags();
        var subject = subjectInput.value;

        if (!pattern.trim()) {
            showError('El patron regex no puede estar vacio.');
            return;
        }

        var expression;
        try {
            expression = new RegExp(pattern, flags);
        } catch (err) {
            showError(err.message || 'Regex invalida.');
            return;
        }

        var matches = [];
        var matchRanges = [];

        if (flags.indexOf('g') !== -1) {
            var globalMatch;
            while ((globalMatch = expression.exec(subject)) !== null) {
                matches.push({
                    index: globalMatch.index,
                    text: globalMatch[0],
                    groups: Array.prototype.slice.call(globalMatch)
                });
                matchRanges.push({
                    start: globalMatch.index,
                    end: globalMatch.index + globalMatch[0].length
                });

                if (globalMatch[0] === '') {
                    expression.lastIndex++;
                }
            }
        } else {
            var singleMatch = expression.exec(subject);
            if (singleMatch) {
                matches.push({
                    index: singleMatch.index,
                    text: singleMatch[0],
                    groups: Array.prototype.slice.call(singleMatch)
                });
                matchRanges.push({
                    start: singleMatch.index,
                    end: singleMatch.index + singleMatch[0].length
                });
            }
        }

        highlighted.innerHTML = highlightMatches(subject, matchRanges);

        if (!matches.length) {
            summary.textContent = 'No hay coincidencias para /' + pattern + '/' + flags + '.';
            renderGroups([]);
            updateShareUrl(pattern, flags, subject);
            return;
        }

        summary.textContent = 'Encontradas ' + matches.length + ' coincidencias para /' + pattern + '/' + flags + '.';
        renderGroups(matches);
        updateShareUrl(pattern, flags, subject);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        execute();
    });

    patternInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            execute();
        }
    });

    for (var i = 0; i < flagInputs.length; i++) {
        flagInputs[i].addEventListener('change', execute);
    }

    copyLinkBtn.addEventListener('click', function () {
        var url = updateShareUrl(patternInput.value, getFlags(), subjectInput.value);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                copyLinkBtn.textContent = 'Enlace copiado';
                setTimeout(function () {
                    copyLinkBtn.textContent = 'Copiar enlace';
                }, 1500);
            }).catch(function () {
                showError('No se pudo copiar automaticamente. Enlace: ' + url);
            });
        } else {
            showError('Tu navegador no permite copiar automaticamente. Enlace: ' + url);
        }
    });

    for (var j = 0; j < exampleButtons.length; j++) {
        exampleButtons[j].addEventListener('click', function () {
            patternInput.value = this.getAttribute('data-pattern') || '';
            subjectInput.value = decodeEscapedText(this.getAttribute('data-subject') || '');
            setFlags(this.getAttribute('data-flags') || 'g');
            execute();
        });
    }

    fillFromUrl();
    execute();
})();
</script>

<?php
get_footer();
