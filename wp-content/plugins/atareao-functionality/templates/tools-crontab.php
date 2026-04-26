<?php
/**
 * Tools - Crontab Helper
 *
 * Route: /tools/crontab
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/crontab/');
$tool_title = 'Crontab Helper: expresion cron y proximas ejecuciones | ' . get_bloginfo('name');
$tool_description = 'Analiza expresiones cron de 5 campos, interpreta alias como @daily y calcula las proximas ejecuciones con zona horaria.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'Crontab Helper',
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
                    'name' => 'Cuantos campos tiene una expresion cron estandar?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Este analizador usa expresiones de 5 campos: minuto, hora, dia del mes, mes y dia de la semana.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Que alias cron reconoce?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Soporta alias comunes como @hourly, @daily, @weekly y @monthly.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir una expresion cron concreta?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. Al pulsar Copiar enlace se genera una URL con la expresion y la zona horaria en parametros.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Que errores cron son mas comunes?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Los errores habituales son usar 6 campos en lugar de 5, invertir dia del mes y dia de la semana, o no ajustar correctamente la zona horaria.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Sirve para validar tareas de backup y mantenimiento?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. Es util para revisar horarios de backup, limpieza, rotacion de logs y reinicios programados antes de aplicarlos en produccion.',
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
    <article id="post-tools-crontab" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">Crontab Helper</h1>
            <?php atareao_tools_render_breadcrumb('crontab'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Escribe una expresion cron de 5 campos y obtendras una explicacion rapida
                    y las proximas ejecuciones previstas.
                </p>
            </div>

            <div id="cron_error" class="atareao-feedback-error" hidden></div>

            <form id="cron_form" class="atareao-contact-form" method="post" action="" novalidate>
                <div>
                    <label for="cron_expression">Expresion cron</label>
                    <input
                        id="cron_expression"
                        type="text"
                        value="*/15 * * * *"
                        placeholder="*/15 * * * *"
                        autocomplete="off"
                        spellcheck="false"
                    >
                </div>

                <div>
                    <label for="cron_timezone">Zona horaria</label>
                    <select id="cron_timezone">
                        <option value="local">Local del navegador</option>
                        <option value="UTC">UTC</option>
                        <option value="Europe/Madrid">Europe/Madrid</option>
                        <option value="America/Mexico_City">America/Mexico_City</option>
                        <option value="America/Bogota">America/Bogota</option>
                    </select>
                </div>

                <div>
                    <label>Ejemplos rapidos</label>
                    <p>
                        <button type="button" class="cron-example" data-example="@hourly">@hourly</button>
                        <button type="button" class="cron-example" data-example="@daily">@daily</button>
                        <button type="button" class="cron-example" data-example="@weekly">@weekly</button>
                        <button type="button" class="cron-example" data-example="@monthly">@monthly</button>
                        <button type="button" class="cron-example" data-example="* * * * *">cada minuto</button>
                        <button type="button" class="cron-example" data-example="*/5 * * * *">cada 5 min</button>
                        <button type="button" class="cron-example" data-example="*/15 * * * *">cada 15 min</button>
                        <button type="button" class="cron-example" data-example="0 * * * *">cada hora</button>
                        <button type="button" class="cron-example" data-example="0 */6 * * *">cada 6 horas</button>
                        <button type="button" class="cron-example" data-example="0 0 * * *">cada dia a medianoche</button>
                        <button type="button" class="cron-example" data-example="0 9 * * MON-FRI">lunes a viernes 09:00</button>
                        <button type="button" class="cron-example" data-example="30 7 * * 1-5">dias laborables 07:30</button>
                        <button type="button" class="cron-example" data-example="0 0 1 * *">primer dia de mes</button>
                    </p>
                </div>

                <div style="text-align:center;">
                    <button type="submit" id="cron_analyze">Analizar</button>
                    <button type="button" id="cron_copy_link" class="cron-example">Copiar enlace</button>
                </div>

                <section>
                    <label for="cron_description">Descripcion</label>
                    <p id="cron_description" class="atareao-cron-description">Cada 15 minutos.</p>
                </section>

                <section>
                    <label for="cron_next_runs">Proximas 5 ejecuciones</label>
                    <ol id="cron_next_runs"></ol>
                </section>
            </form>

            <section class="atareao-tool-seo-content" aria-label="Guia rapida de cron">
                <h2>Guia rapida de uso</h2>
                <h3>1. Escribe o selecciona una expresion</h3>
                <p>Introduce una expresion cron de 5 campos o pulsa un ejemplo para partir de una base valida.</p>

                <h3>2. Ajusta zona horaria</h3>
                <p>Elige UTC o zona local para simular ejecuciones reales y evitar discrepancias en servidores distribuidos.</p>

                <h3>3. Verifica proximas ejecuciones</h3>
                <p>Confirma que las siguientes ventanas de ejecucion coinciden con mantenimiento, backup o procesos programados.</p>
            </section>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de cron">
                <h2>Preguntas frecuentes</h2>
                <h3>Que formato cron usa esta herramienta</h3>
                <p>Usa cron estandar de 5 campos: minuto, hora, dia del mes, mes y dia de la semana.</p>

                <h3>Para que sirve la zona horaria</h3>
                <p>Permite simular las proximas ejecuciones en hora local o en una zona concreta como UTC o Europe/Madrid.</p>

                <h3>Como compartir el resultado</h3>
                <p>Con el boton Copiar enlace generas una URL con la expresion y el timezone para abrir el mismo analisis.</p>

                <h3>Errores frecuentes al escribir cron</h3>
                <p>Los fallos mas comunes son usar campos de mas, confundir dia del mes con dia de la semana y olvidar la zona horaria final de ejecucion.</p>

                <h3>Casos de uso habituales</h3>
                <p>Esta herramienta es util para planificar tareas de backup, renovaciones de certificados, limpieza de temporales y envios de reportes.</p>

                <h3>Buenas practicas en produccion</h3>
                <p>Antes de activar una tarea, valida primero la expresion, confirma timezone y comprueba las proximas ejecuciones para evitar ventanas conflictivas.</p>
            </section>
        </div>
    </article>
</main>

<style>
.atareao-cron-description {
    font-style: italic;
    color: inherit;
}

.atareao-contact-form .cron-example {
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

.atareao-contact-form select {
    width: 100%;
    border-radius: 8px;
    border: 1px solid #d7dbe2;
    padding: 0.55rem 0.65rem;
    background: #fff;
}

[data-theme="dark"] .atareao-contact-form select {
    background: #131826;
    border-color: #44506a;
    color: #e5e7eb;
}

.atareao-contact-form .cron-example:hover {
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

[data-theme="dark"] .atareao-cron-description {
    color: #e5e7eb;
}

[data-theme="light"] .atareao-cron-description {
    color: #222222;
}

#cron_next_runs code {
    display: inline-block;
    padding: 0.15rem 0.45rem;
    border-radius: 0.4rem;
    font-size: 0.92em;
    border: 1px solid #d9d9d9;
    background: #f5f5f5;
    color: #1f2937;
}

[data-theme="light"] #cron_next_runs code {
    border-color: #d7dbe2;
    background: #f7f8fb;
    color: #1f2937;
}

[data-theme="dark"] #cron_next_runs code {
    border-color: #44506a;
    background: #1d2538;
    color: #e5e7eb;
}

@media (prefers-color-scheme: dark) {
    html:not([data-theme="light"]) #cron_next_runs code {
        border-color: #44506a;
        background: #1d2538;
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .atareao-cron-description {
        color: #e5e7eb;
    }
}
</style>

<script>
(function () {
    'use strict';

    var exprInput = document.getElementById('cron_expression');
    var tzSelect = document.getElementById('cron_timezone');
    var cronForm = document.getElementById('cron_form');
    var analyzeBtn = document.getElementById('cron_analyze');
    var copyLinkBtn = document.getElementById('cron_copy_link');
    var descBox = document.getElementById('cron_description');
    var runsBox = document.getElementById('cron_next_runs');
    var errorBox = document.getElementById('cron_error');
    var exampleButtons = document.querySelectorAll('.cron-example');

    var FIELD_RANGES = [
        { min: 0, max: 59, name: 'minuto' },
        { min: 0, max: 23, name: 'hora' },
        { min: 1, max: 31, name: 'dia del mes' },
        { min: 1, max: 12, name: 'mes' },
        { min: 0, max: 6, name: 'dia de la semana' }
    ];

    var MONTH_ALIASES = {
        JAN: 1, FEB: 2, MAR: 3, APR: 4, MAY: 5, JUN: 6,
        JUL: 7, AUG: 8, SEP: 9, OCT: 10, NOV: 11, DEC: 12
    };

    var WEEKDAY_ALIASES = {
        SUN: 0, MON: 1, TUE: 2, WED: 3, THU: 4, FRI: 5, SAT: 6
    };

    var MACROS = {
        '@yearly': '0 0 1 1 *',
        '@annually': '0 0 1 1 *',
        '@monthly': '0 0 1 * *',
        '@weekly': '0 0 * * 0',
        '@daily': '0 0 * * *',
        '@midnight': '0 0 * * *',
        '@hourly': '0 * * * *'
    };

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function expandMacro(expression) {
        var trimmed = expression.trim();
        var key = trimmed.toLowerCase();
        if (MACROS[key]) {
            return MACROS[key];
        }
        if (key === '@reboot') {
            throw new Error('La macro @reboot no depende del tiempo y no se puede simular.');
        }
        return expression;
    }

    function normalizeAliases(part, fieldIndex) {
        var result = part.toUpperCase();
        var aliases = null;

        if (fieldIndex === 3) {
            aliases = MONTH_ALIASES;
        } else if (fieldIndex === 4) {
            aliases = WEEKDAY_ALIASES;
            result = result.replace(/\b7\b/g, '0');
        }

        if (!aliases) {
            return part;
        }

        Object.keys(aliases).forEach(function (alias) {
            var pattern = new RegExp('\\b' + alias + '\\b', 'g');
            result = result.replace(pattern, String(aliases[alias]));
        });

        return result;
    }

    function parsePart(part, cfg) {
        var values = new Set();
        var chunks = part.split(',');

        function addRange(start, end, step) {
            if (step <= 0) {
                throw new Error('Paso invalido en ' + cfg.name + '.');
            }
            if (start > end) {
                throw new Error('Rango invalido en ' + cfg.name + '.');
            }
            if (start < cfg.min || end > cfg.max) {
                throw new Error('Valor fuera de rango en ' + cfg.name + '.');
            }
            for (var i = start; i <= end; i += step) {
                values.add(i);
            }
        }

        for (var c = 0; c < chunks.length; c++) {
            var token = chunks[c].trim();
            if (!token) {
                throw new Error('Token vacio en ' + cfg.name + '.');
            }

            if (token === '*') {
                addRange(cfg.min, cfg.max, 1);
                continue;
            }

            if (token.indexOf('/') !== -1) {
                var stepParts = token.split('/');
                if (stepParts.length !== 2) {
                    throw new Error('Formato de paso invalido en ' + cfg.name + '.');
                }

                var base = stepParts[0];
                var step = Number(stepParts[1]);
                if (!Number.isInteger(step)) {
                    throw new Error('Paso invalido en ' + cfg.name + '.');
                }

                if (base === '*') {
                    addRange(cfg.min, cfg.max, step);
                    continue;
                }

                if (base.indexOf('-') !== -1) {
                    var rangeParts = base.split('-');
                    var rStart = Number(rangeParts[0]);
                    var rEnd = Number(rangeParts[1]);
                    if (!Number.isInteger(rStart) || !Number.isInteger(rEnd)) {
                        throw new Error('Rango invalido en ' + cfg.name + '.');
                    }
                    addRange(rStart, rEnd, step);
                    continue;
                }

                var fixed = Number(base);
                if (!Number.isInteger(fixed)) {
                    throw new Error('Valor invalido en ' + cfg.name + '.');
                }
                addRange(fixed, cfg.max, step);
                continue;
            }

            if (token.indexOf('-') !== -1) {
                var parts = token.split('-');
                if (parts.length !== 2) {
                    throw new Error('Rango invalido en ' + cfg.name + '.');
                }
                var start = Number(parts[0]);
                var end = Number(parts[1]);
                if (!Number.isInteger(start) || !Number.isInteger(end)) {
                    throw new Error('Rango invalido en ' + cfg.name + '.');
                }
                addRange(start, end, 1);
                continue;
            }

            var num = Number(token);
            if (!Number.isInteger(num)) {
                throw new Error('Valor invalido en ' + cfg.name + '.');
            }
            addRange(num, num, 1);
        }

        return values;
    }

    function parseExpression(expression) {
        var expanded = expandMacro(expression);
        var fields = expanded.trim().replace(/\s+/g, ' ').split(' ');

        if (fields.length !== 5) {
            throw new Error('La expresion debe tener exactamente 5 campos.');
        }

        return fields.map(function (part, index) {
            return parsePart(normalizeAliases(part, index), FIELD_RANGES[index]);
        });
    }

    function getDatePartsForTimezone(date, timezone) {
        if (timezone === 'local') {
            return {
                minute: date.getMinutes(),
                hour: date.getHours(),
                day: date.getDate(),
                month: date.getMonth() + 1,
                dow: date.getDay()
            };
        }

        var formatter = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            minute: '2-digit',
            hour: '2-digit',
            day: '2-digit',
            month: '2-digit',
            weekday: 'short',
            hour12: false
        });

        var parts = formatter.formatToParts(date);
        var map = {};
        for (var i = 0; i < parts.length; i++) {
            map[parts[i].type] = parts[i].value;
        }

        var dowMap = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

        return {
            minute: Number(map.minute),
            hour: Number(map.hour),
            day: Number(map.day),
            month: Number(map.month),
            dow: dowMap[map.weekday]
        };
    }

    function matches(date, sets, timezone) {
        var p = getDatePartsForTimezone(date, timezone);

        return sets[0].has(p.minute) &&
            sets[1].has(p.hour) &&
            sets[2].has(p.day) &&
            sets[3].has(p.month) &&
            sets[4].has(p.dow);
    }

    function nextRuns(sets, count, timezone) {
        var list = [];
        var cursor = new Date();
        cursor.setSeconds(0, 0);
        cursor.setMinutes(cursor.getMinutes() + 1);

        var safety = 0;
        while (list.length < count && safety < 3000000) {
            if (matches(cursor, sets, timezone)) {
                list.push(new Date(cursor));
            }

            cursor.setMinutes(cursor.getMinutes() + 1);
            safety++;
        }

        return list;
    }

    function pad(v) {
        return String(v).padStart(2, '0');
    }

    function formatDate(date, timezone) {
        if (timezone !== 'local') {
            return new Intl.DateTimeFormat('es-ES', {
                timeZone: timezone,
                weekday: 'short',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23'
            }).format(date) + ' (' + timezone + ')';
        }

        var y = date.getFullYear();
        var m = pad(date.getMonth() + 1);
        var d = pad(date.getDate());
        var h = pad(date.getHours());
        var min = pad(date.getMinutes());
        var days = ['dom', 'lun', 'mar', 'mie', 'jue', 'vie', 'sab'];

        return days[date.getDay()] + ' ' + d + '/' + m + '/' + y + ' ' + h + ':' + min;
    }

    function describe(expression, timezone) {
        var expanded = expandMacro(expression);
        var p = expanded.trim().replace(/\s+/g, ' ').split(' ');
        var minute = p[0];
        var hour = p[1];
        var dom = p[2];
        var month = p[3];
        var dow = p[4];
        var zoneLabel = timezone === 'local' ? 'hora local' : timezone;

        if (expanded.trim() === '* * * * *') {
            return 'Cada minuto (' + zoneLabel + ').';
        }

        if (/^\*\/[0-9]+$/.test(minute) && hour === '*' && dom === '*' && month === '*' && dow === '*') {
            return 'Cada ' + minute.split('/')[1] + ' minutos (' + zoneLabel + ').';
        }

        if (/^[0-9]+$/.test(minute) && hour === '*' && dom === '*' && month === '*' && dow === '*') {
            return 'Cada hora, en el minuto ' + minute + ' (' + zoneLabel + ').';
        }

        if (/^[0-9]+$/.test(minute) && /^[0-9]+$/.test(hour) && dom === '*' && month === '*' && dow === '*') {
            return 'Todos los dias a las ' + pad(Number(hour)) + ':' + pad(Number(minute)) + ' (' + zoneLabel + ').';
        }

        return 'Cron personalizado: minuto=' + minute + ', hora=' + hour + ', dia-mes=' + dom + ', mes=' + month + ', dia-semana=' + dow + ' (' + zoneLabel + ').';
    }

    function updateShareUrl(expression, timezone) {
        var url = new URL(window.location.href);
        url.searchParams.set('expr', expression);
        url.searchParams.set('tz', timezone);
        url.searchParams.delete('adv');
        url.searchParams.delete('sec');
        window.history.replaceState({}, '', url.toString());
        return url.toString();
    }

    function loadInitialState() {
        var url = new URL(window.location.href);
        var exprParam = url.searchParams.get('expr');
        var tzParam = url.searchParams.get('tz');

        if (exprParam) {
            exprInput.value = exprParam;
        }
        if (tzParam && tzSelect.querySelector('option[value="' + tzParam + '"]')) {
            tzSelect.value = tzParam;
        }
    }

    function render(expression) {
        clearError();

        var timezone = tzSelect.value || 'local';
        var count = 5;

        var sets;
        try {
            sets = parseExpression(expression);
        } catch (err) {
            showError(err.message || 'Expresion invalida.');
            return;
        }

        descBox.textContent = describe(expression, timezone);

        var runs = nextRuns(sets, count, timezone);
        runsBox.innerHTML = '';

        if (!runs.length) {
            showError('No se encontraron ejecuciones futuras con esa expresion.');
            return;
        }

        for (var i = 0; i < runs.length; i++) {
            var li = document.createElement('li');
            var code = document.createElement('code');
            code.textContent = formatDate(runs[i], timezone);
            li.appendChild(code);
            runsBox.appendChild(li);
        }

        updateShareUrl(expression.trim(), timezone);
    }

    cronForm.addEventListener('submit', function (event) {
        event.preventDefault();
        render(exprInput.value);
    });

    analyzeBtn.addEventListener('click', function () {
        render(exprInput.value);
    });

    tzSelect.addEventListener('change', function () {
        render(exprInput.value);
    });

    copyLinkBtn.addEventListener('click', function () {
        var url = updateShareUrl(exprInput.value.trim(), tzSelect.value || 'local');
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

    exprInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            render(exprInput.value);
        }
    });

    for (var i = 0; i < exampleButtons.length; i++) {
        exampleButtons[i].addEventListener('click', function () {
            exprInput.value = this.getAttribute('data-example') || '* * * * *';
            render(exprInput.value);
        });
    }

    loadInitialState();
    render(exprInput.value);
})();
</script>

<?php
get_footer();
