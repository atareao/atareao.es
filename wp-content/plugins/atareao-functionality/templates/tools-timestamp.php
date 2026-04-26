<?php
/**
 * Tools - Timestamp Converter
 *
 * Route: /tools/timestamp
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/timestamp/');
$tool_title = 'Timestamp Converter: Unix epoch a fecha online | ' . get_bloginfo('name');
$tool_description = 'Convierte Unix timestamp a fecha UTC/local, ISO 8601 y formato legible. Tambien transforma fecha y hora a epoch segundos y milisegundos.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'Timestamp Converter',
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
                    'name' => 'Que convierte esta herramienta de timestamp?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Convierte epoch Unix en segundos o milisegundos a fecha legible y tambien permite obtener epoch a partir de fecha y hora.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Como saber si un timestamp esta en segundos o milisegundos?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Normalmente los timestamps de 10 digitos son segundos y los de 13 digitos son milisegundos.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir una conversion concreta?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. El boton Copiar enlace guarda timestamp, unidad y zona horaria en la URL.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Para que se usa en desarrollo y DevOps?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Es muy util para depurar logs, trazas de APIs, expiraciones de tokens y eventos de monitorizacion.',
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
    <article id="post-tools-timestamp" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">Timestamp Converter</h1>
            <?php atareao_tools_render_breadcrumb('timestamp'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Convierte Unix timestamp a fecha legible y transforma fecha/hora a epoch para depurar logs, APIs y eventos.
                </p>
            </div>

            <div id="ts_error" class="atareao-feedback-error" hidden></div>

            <form id="ts_form" class="atareao-contact-form" method="post" action="" novalidate>
                <div>
                    <label for="ts_input">Unix timestamp</label>
                    <input id="ts_input" type="text" value="1714132800" placeholder="1714132800" inputmode="numeric" autocomplete="off" spellcheck="false">
                </div>

                <div class="ts-grid">
                    <div>
                        <label for="ts_unit">Unidad</label>
                        <select id="ts_unit">
                            <option value="s" selected>Segundos (10 digitos)</option>
                            <option value="ms">Milisegundos (13 digitos)</option>
                        </select>
                    </div>
                    <div>
                        <label for="ts_timezone">Zona horaria de salida</label>
                        <select id="ts_timezone">
                            <option value="local" selected>Local del navegador</option>
                            <option value="UTC">UTC</option>
                            <option value="Europe/Madrid">Europe/Madrid</option>
                            <option value="America/Bogota">America/Bogota</option>
                            <option value="America/Mexico_City">America/Mexico_City</option>
                        </select>
                    </div>
                </div>

                <div class="ts-grid">
                    <div>
                        <label for="ts_date_input">Fecha y hora (local)</label>
                        <input id="ts_date_input" type="datetime-local">
                    </div>
                    <div>
                        <label>Ejemplos rapidos</label>
                        <p>
                            <button type="button" class="ts-example" data-ts="0" data-unit="s">epoch 0</button>
                            <button type="button" class="ts-example" data-ts="946684800" data-unit="s">Y2K</button>
                            <button type="button" class="ts-example" data-ts="1704067200" data-unit="s">2024-01-01</button>
                            <button type="button" class="ts-example" data-ts="1714132800000" data-unit="ms">ms example</button>
                        </p>
                    </div>
                </div>

                <div style="text-align:center;">
                    <button type="button" id="ts_from_unix" class="ts-action">Desde Unix</button>
                    <button type="button" id="ts_from_date" class="ts-action">Desde fecha</button>
                    <button type="button" id="ts_now" class="ts-action">Ahora</button>
                    <button type="button" id="ts_copy_link" class="ts-action">Copiar enlace</button>
                </div>

                <section>
                    <label for="ts_summary">Resumen</label>
                    <p id="ts_summary" class="atareao-ts-summary">Listo para convertir.</p>
                </section>

                <section>
                    <label>Resultados</label>
                    <div class="ts-results-table-wrap">
                        <table class="ts-results-table">
                            <thead>
                                <tr>
                                    <th scope="col">Campo</th>
                                    <th scope="col">Valor</th>
                                </tr>
                            </thead>
                            <tbody id="ts_results"></tbody>
                        </table>
                    </div>
                </section>
            </form>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de timestamp">
                <h2>Preguntas frecuentes</h2>
                <h3>Segundos o milisegundos</h3>
                <p>Como referencia rapida, 10 digitos suele indicar segundos y 13 digitos suele indicar milisegundos.</p>

                <h3>Usos habituales</h3>
                <p>Se usa para interpretar logs de backend, revisar expiraciones de JWT y validar eventos temporales en bases de datos y colas.</p>

                <h3>UTC frente a hora local</h3>
                <p>En sistemas distribuidos conviene trabajar en UTC y convertir a local solo para visualizacion y soporte.</p>

                <h3>Compartir conversiones</h3>
                <p>Con Copiar enlace puedes enviar el mismo caso a otro miembro del equipo para revisar resultados de forma consistente.</p>
            </section>
        </div>
    </article>
</main>

<style>
.atareao-ts-summary {
    font-style: italic;
    color: inherit;
}

.ts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.8rem;
}

@media (max-width: 780px) {
    .ts-grid {
        grid-template-columns: 1fr;
    }
}

.atareao-contact-form .ts-action,
.atareao-contact-form .ts-example {
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

.atareao-contact-form .ts-action:hover,
.atareao-contact-form .ts-example:hover {
    filter: brightness(0.94);
}

.ts-results-table-wrap {
    overflow-x: auto;
}

.ts-results-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}

.ts-results-table th,
.ts-results-table td {
    padding: 0.5rem 0.55rem;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
    color: #222222;
}

.ts-results-table th {
    font-weight: 700;
}

#ts_results code {
    display: inline-block;
    padding: 0.15rem 0.45rem;
    border-radius: 0.4rem;
    font-size: 0.92em;
    border: 1px solid #d9d9d9;
    background: #f5f5f5;
    color: #1f2937;
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

[data-theme="dark"] .atareao-ts-summary {
    color: #e5e7eb;
}

[data-theme="dark"] .ts-results-table th,
[data-theme="dark"] .ts-results-table td {
    border-bottom-color: #334155;
    color: #e5e7eb;
}

[data-theme="dark"] #ts_results code {
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
    html:not([data-theme="light"]) .atareao-ts-summary {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .ts-results-table th,
    html:not([data-theme="light"]) .ts-results-table td {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) #ts_results code {
        border-color: #44506a;
        background: #1d2538;
        color: #e5e7eb;
    }
}
</style>

<script>
(function () {
    'use strict';

    var tsInput = document.getElementById('ts_input');
    var tsUnit = document.getElementById('ts_unit');
    var tzSelect = document.getElementById('ts_timezone');
    var dateInput = document.getElementById('ts_date_input');
    var resultsBody = document.getElementById('ts_results');
    var summary = document.getElementById('ts_summary');
    var errorBox = document.getElementById('ts_error');
    var fromUnixBtn = document.getElementById('ts_from_unix');
    var fromDateBtn = document.getElementById('ts_from_date');
    var nowBtn = document.getElementById('ts_now');
    var copyLinkBtn = document.getElementById('ts_copy_link');
    var exampleButtons = document.querySelectorAll('.ts-example');

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function formatForTimezone(date, timezone) {
        if (timezone === 'local') {
            return date.toLocaleString('es-ES', { hour12: false });
        }

        return new Intl.DateTimeFormat('es-ES', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        }).format(date);
    }

    function updateShareUrl(ts, unit, timezone) {
        var url = new URL(window.location.href);
        url.searchParams.set('ts', String(ts));
        url.searchParams.set('unit', unit);
        url.searchParams.set('tz', timezone);
        window.history.replaceState({}, '', url.toString());
        return url.toString();
    }

    function parseUnixInput() {
        var raw = tsInput.value.trim();
        if (!/^[-]?\d+$/.test(raw)) {
            throw new Error('El timestamp debe ser numerico.');
        }

        var parsed = Number(raw);
        if (!Number.isFinite(parsed)) {
            throw new Error('Timestamp invalido.');
        }

        return parsed;
    }

    function fillFromUrl() {
        var url = new URL(window.location.href);
        var ts = url.searchParams.get('ts');
        var unit = url.searchParams.get('unit');
        var tz = url.searchParams.get('tz');

        if (ts !== null && ts !== '') {
            tsInput.value = ts;
        }

        if (unit === 's' || unit === 'ms') {
            tsUnit.value = unit;
        }

        if (tz && tzSelect.querySelector('option[value="' + tz + '"]')) {
            tzSelect.value = tz;
        }
    }

    function renderRows(rows) {
        resultsBody.innerHTML = '';

        for (var i = 0; i < rows.length; i++) {
            var tr = document.createElement('tr');
            var fieldTd = document.createElement('td');
            var valueTd = document.createElement('td');
            var code = document.createElement('code');

            fieldTd.textContent = rows[i].label;
            code.textContent = rows[i].value;
            valueTd.appendChild(code);

            tr.appendChild(fieldTd);
            tr.appendChild(valueTd);
            resultsBody.appendChild(tr);
        }
    }

    function syncDatetimeLocal(date) {
        var pad = function (n) {
            return String(n).padStart(2, '0');
        };

        var local = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
        var value = local.getFullYear() + '-' +
            pad(local.getMonth() + 1) + '-' +
            pad(local.getDate()) + 'T' +
            pad(local.getHours()) + ':' +
            pad(local.getMinutes());

        dateInput.value = value;
    }

    function convertFromUnix() {
        clearError();

        var unix = parseUnixInput();
        var millis = tsUnit.value === 'ms' ? unix : unix * 1000;
        var date = new Date(millis);

        if (isNaN(date.getTime())) {
            showError('No se pudo convertir el timestamp.');
            return;
        }

        var timezone = tzSelect.value || 'local';
        var unixSeconds = Math.floor(millis / 1000);

        syncDatetimeLocal(date);
        summary.textContent = 'Timestamp convertido correctamente.';

        renderRows([
            { label: 'Unix (segundos)', value: String(unixSeconds) },
            { label: 'Unix (milisegundos)', value: String(millis) },
            { label: 'ISO 8601 (UTC)', value: date.toISOString() },
            { label: 'Fecha UTC', value: date.toUTCString() },
            { label: 'Fecha local navegador', value: date.toString() },
            { label: 'Fecha en zona seleccionada', value: formatForTimezone(date, timezone) + ' (' + timezone + ')' }
        ]);

        updateShareUrl(tsInput.value.trim(), tsUnit.value, timezone);
    }

    function convertFromDate() {
        clearError();

        var value = dateInput.value;
        if (!value) {
            showError('Selecciona una fecha y hora para convertir.');
            return;
        }

        var date = new Date(value);
        if (isNaN(date.getTime())) {
            showError('Fecha invalida.');
            return;
        }

        var millis = date.getTime();
        var seconds = Math.floor(millis / 1000);

        tsInput.value = tsUnit.value === 'ms' ? String(millis) : String(seconds);
        summary.textContent = 'Fecha convertida a Unix correctamente.';

        convertFromUnix();
    }

    function setNow() {
        var now = new Date();
        var nowMs = now.getTime();
        var nowSeconds = Math.floor(nowMs / 1000);

        tsInput.value = tsUnit.value === 'ms' ? String(nowMs) : String(nowSeconds);
        syncDatetimeLocal(now);
        convertFromUnix();
    }

    fromUnixBtn.addEventListener('click', convertFromUnix);
    fromDateBtn.addEventListener('click', convertFromDate);
    nowBtn.addEventListener('click', setNow);

    tzSelect.addEventListener('change', convertFromUnix);
    tsUnit.addEventListener('change', convertFromUnix);

    copyLinkBtn.addEventListener('click', function () {
        var url = updateShareUrl(tsInput.value.trim(), tsUnit.value, tzSelect.value || 'local');
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
            tsInput.value = this.getAttribute('data-ts') || '0';
            tsUnit.value = this.getAttribute('data-unit') || 's';
            convertFromUnix();
        });
    }

    fillFromUrl();
    setNow();
})();
</script>

<?php
get_footer();
