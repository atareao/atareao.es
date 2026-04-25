<?php
/**
 * Tools - Crontab Helper
 *
 * Route: /tools/crontab
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="primary" class="site-main">
    <section class="crontab-tool">
        <div class="crontab-tool__bg-shape" aria-hidden="true"></div>

        <header class="crontab-tool__header">
            <p class="crontab-tool__eyebrow">Tools</p>
            <h1>Crontab Helper</h1>
            <p class="crontab-tool__lead">
                Escribe una expresion cron de 5 campos y obtendras una explicacion rapida
                y las proximas ejecuciones previstas.
            </p>
        </header>

        <div class="crontab-tool__card">
            <label for="cron_expression" class="crontab-tool__label">Expresion cron</label>
            <div class="crontab-tool__input-row">
                <input
                    id="cron_expression"
                    type="text"
                    value="*/15 * * * *"
                    placeholder="*/15 * * * *"
                    autocomplete="off"
                    spellcheck="false"
                >
                <button type="button" id="cron_analyze">Analizar</button>
            </div>

            <div class="crontab-tool__examples">
                <span>Ejemplos:</span>
                <button type="button" class="cron-example" data-example="*/5 * * * *">cada 5 min</button>
                <button type="button" class="cron-example" data-example="0 * * * *">cada hora</button>
                <button type="button" class="cron-example" data-example="30 7 * * 1-5">dias laborables 07:30</button>
                <button type="button" class="cron-example" data-example="0 0 1 * *">primer dia de mes</button>
            </div>

            <div id="cron_error" class="crontab-tool__error" hidden></div>

            <section class="crontab-tool__result">
                <h2>Descripcion</h2>
                <p id="cron_description">Cada 15 minutos.</p>
            </section>

            <section class="crontab-tool__result">
                <h2>Proximas 10 ejecuciones</h2>
                <ol id="cron_next_runs" class="crontab-tool__runs"></ol>
            </section>
        </div>
    </section>
</main>

<style>
.crontab-tool {
    --tool-bg-1: #f5f8ff;
    --tool-bg-2: #fef7ef;
    --tool-surface: #ffffff;
    --tool-border: #d9e0ef;
    --tool-text: #1e293b;
    --tool-muted: #64748b;
    --tool-accent: #0f766e;
    --tool-accent-dark: #115e59;
    --tool-danger: #b42318;

    position: relative;
    max-width: 920px;
    margin: 2rem auto 3rem;
    padding: 1.5rem;
    color: var(--tool-text);
}

.crontab-tool__bg-shape {
    position: absolute;
    inset: 0;
    z-index: 0;
    border-radius: 20px;
    background:
        radial-gradient(circle at 10% 10%, rgba(15, 118, 110, 0.12), transparent 40%),
        radial-gradient(circle at 90% 90%, rgba(245, 158, 11, 0.15), transparent 42%),
        linear-gradient(135deg, var(--tool-bg-1), var(--tool-bg-2));
}

.crontab-tool__header,
.crontab-tool__card {
    position: relative;
    z-index: 1;
}

.crontab-tool__header {
    padding: 1rem 0.5rem 1.5rem;
}

.crontab-tool__eyebrow {
    margin: 0 0 0.2rem;
    color: var(--tool-accent-dark);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 700;
    font-size: 0.75rem;
}

.crontab-tool h1 {
    margin: 0 0 0.6rem;
    font-size: clamp(1.8rem, 4vw, 2.5rem);
    line-height: 1.1;
}

.crontab-tool__lead {
    margin: 0;
    color: var(--tool-muted);
    max-width: 72ch;
}

.crontab-tool__card {
    background: var(--tool-surface);
    border: 1px solid var(--tool-border);
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    padding: 1rem;
}

.crontab-tool__label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.crontab-tool__input-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.75rem;
}

#cron_expression {
    width: 100%;
    border: 1px solid var(--tool-border);
    border-radius: 10px;
    padding: 0.75rem 0.85rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, monospace;
    font-size: 1rem;
}

#cron_analyze,
.cron-example {
    border: 0;
    border-radius: 10px;
    background: var(--tool-accent);
    color: #fff;
    cursor: pointer;
    font-weight: 600;
}

#cron_analyze {
    padding: 0.75rem 1rem;
}

#cron_analyze:hover,
.cron-example:hover {
    background: var(--tool-accent-dark);
}

.crontab-tool__examples {
    margin-top: 0.8rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
    color: var(--tool-muted);
    font-size: 0.92rem;
}

.cron-example {
    padding: 0.38rem 0.55rem;
    font-size: 0.82rem;
}

.crontab-tool__error {
    margin-top: 0.9rem;
    color: var(--tool-danger);
    font-weight: 600;
}

.crontab-tool__result {
    margin-top: 1.15rem;
    border-top: 1px solid var(--tool-border);
    padding-top: 0.95rem;
}

.crontab-tool__result h2 {
    margin: 0 0 0.35rem;
    font-size: 1.05rem;
}

.crontab-tool__result p {
    margin: 0;
}

.crontab-tool__runs {
    margin: 0;
    padding-left: 1.25rem;
}

.crontab-tool__runs li {
    margin: 0.2rem 0;
}

@media (max-width: 680px) {
    .crontab-tool {
        padding: 1rem;
        margin-top: 1rem;
    }

    .crontab-tool__card {
        padding: 0.85rem;
    }

    .crontab-tool__input-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
(function () {
    'use strict';

    var exprInput = document.getElementById('cron_expression');
    var analyzeBtn = document.getElementById('cron_analyze');
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

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
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
        var fields = expression.trim().replace(/\s+/g, ' ').split(' ');
        if (fields.length !== 5) {
            throw new Error('La expresion debe tener exactamente 5 campos.');
        }

        return fields.map(function (part, index) {
            return parsePart(part, FIELD_RANGES[index]);
        });
    }

    function matches(date, sets) {
        var minute = date.getMinutes();
        var hour = date.getHours();
        var dom = date.getDate();
        var month = date.getMonth() + 1;
        var dow = date.getDay();

        return sets[0].has(minute) &&
            sets[1].has(hour) &&
            sets[2].has(dom) &&
            sets[3].has(month) &&
            sets[4].has(dow);
    }

    function nextRuns(sets, count) {
        var list = [];
        var cursor = new Date();
        cursor.setSeconds(0, 0);
        cursor.setMinutes(cursor.getMinutes() + 1);

        var safety = 0;
        while (list.length < count && safety < 800000) {
            if (matches(cursor, sets)) {
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

    function formatDate(date) {
        var y = date.getFullYear();
        var m = pad(date.getMonth() + 1);
        var d = pad(date.getDate());
        var h = pad(date.getHours());
        var min = pad(date.getMinutes());
        var days = ['dom', 'lun', 'mar', 'mie', 'jue', 'vie', 'sab'];

        return days[date.getDay()] + ' ' + d + '/' + m + '/' + y + ' ' + h + ':' + min;
    }

    function describe(expression) {
        var p = expression.trim().replace(/\s+/g, ' ').split(' ');
        var minute = p[0];
        var hour = p[1];
        var dom = p[2];
        var month = p[3];
        var dow = p[4];

        if (expression.trim() === '* * * * *') {
            return 'Cada minuto.';
        }

        if (/^\*\/[0-9]+$/.test(minute) && hour === '*' && dom === '*' && month === '*' && dow === '*') {
            return 'Cada ' + minute.split('/')[1] + ' minutos.';
        }

        if (/^[0-9]+$/.test(minute) && hour === '*' && dom === '*' && month === '*' && dow === '*') {
            return 'Cada hora, en el minuto ' + minute + '.';
        }

        if (/^[0-9]+$/.test(minute) && /^[0-9]+$/.test(hour) && dom === '*' && month === '*' && dow === '*') {
            return 'Todos los dias a las ' + pad(Number(hour)) + ':' + pad(Number(minute)) + '.';
        }

        return 'Cron personalizado: minuto=' + minute + ', hora=' + hour + ', dia-mes=' + dom + ', mes=' + month + ', dia-semana=' + dow + '.';
    }

    function render(expression) {
        clearError();

        var sets;
        try {
            sets = parseExpression(expression);
        } catch (err) {
            showError(err.message || 'Expresion invalida.');
            return;
        }

        descBox.textContent = describe(expression);

        var runs = nextRuns(sets, 10);
        runsBox.innerHTML = '';

        if (!runs.length) {
            showError('No se encontraron ejecuciones futuras con esa expresion.');
            return;
        }

        for (var i = 0; i < runs.length; i++) {
            var li = document.createElement('li');
            li.textContent = formatDate(runs[i]);
            runsBox.appendChild(li);
        }
    }

    analyzeBtn.addEventListener('click', function () {
        render(exprInput.value);
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

    render(exprInput.value);
})();
</script>

<?php
get_footer();
