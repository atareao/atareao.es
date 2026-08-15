/**
 * Timestamp Helper - Frontend
 *
 * Hidrata el bloque Timestamp Helper en el frontend publico.
 * Proporciona conversion bidireccional Unix timestamp <-> fecha legible,
 * seleccion de zona horaria, historial local y URLs compartibles.
 */
(function () {
    'use strict';

    /* ====================================================================
     * CONSTANTS
     * ==================================================================== */

    var TIMEZONES = [
        { label: 'Local del navegador', value: 'local' },
        { label: 'UTC', value: 'UTC' },
        { label: 'Europe/Madrid', value: 'Europe/Madrid' },
        { label: 'America/Bogota', value: 'America/Bogota' },
        { label: 'America/Mexico_City', value: 'America/Mexico_City' },
        { label: 'America/Argentina/Buenos_Aires', value: 'America/Argentina/Buenos_Aires' },
        { label: 'America/Santiago', value: 'America/Santiago' },
        { label: 'America/Lima', value: 'America/Lima' },
        { label: 'America/Sao_Paulo', value: 'America/Sao_Paulo' },
        { label: 'Europe/London', value: 'Europe/London' },
        { label: 'Europe/Berlin', value: 'Europe/Berlin' },
        { label: 'Europe/Paris', value: 'Europe/Paris' },
        { label: 'Asia/Tokyo', value: 'Asia/Tokyo' },
        { label: 'Asia/Shanghai', value: 'Asia/Shanghai' },
        { label: 'Asia/Kolkata', value: 'Asia/Kolkata' },
        { label: 'Australia/Sydney', value: 'Australia/Sydney' },
        { label: 'Pacific/Auckland', value: 'Pacific/Auckland' },
    ];

    var UNITS = [
        { label: 'Segundos (10 digitos)', value: 's' },
        { label: 'Milisegundos (13 digitos)', value: 'ms' },
    ];

    var QUICK_EXAMPLES = [
        { ts: '0', unit: 's', label: 'epoch 0' },
        { ts: '946684800', unit: 's', label: 'Y2K' },
        { ts: '1704067200', unit: 's', label: '2024-01-01' },
        { ts: '1714132800000', unit: 'ms', label: 'ms example' },
    ];

    var RESULT_FIELDS = [
        { key: 'unixSeconds', label: 'Unix (segundos)' },
        { key: 'unixMillis', label: 'Unix (milisegundos)' },
        { key: 'iso8601', label: 'ISO 8601' },
        { key: 'rfc2822', label: 'RFC 2822' },
        { key: 'utc', label: 'UTC' },
        { key: 'local', label: 'Local navegador' },
        { key: 'timezoneFormatted', label: 'Zona seleccionada' },
    ];

    var HISTORY_KEY = 'atareao_timestamp_history';
    var MAX_HISTORY = 20;

    /* ====================================================================
     * UTILITY FUNCTIONS
     * ==================================================================== */

    function getMilliseconds(value, unit) {
        return unit === 'ms' ? value : value * 1000;
    }

    function autoDetectUnit(value) {
        var str = String(value).replace(/^-/, '');
        return str.length >= 12 ? 'ms' : 's';
    }

    function isValidNumeric(value) {
        return /^[-]?\d+$/.test(String(value).trim());
    }

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function formatForTimezone(date, timezone) {
        try {
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
                hour12: false,
            }).format(date);
        } catch (e) {
            return date.toLocaleString('es-ES', { hour12: false });
        }
    }

    function formatRelativeTime(date) {
        var now = new Date();
        var diffMs = date.getTime() - now.getTime();
        var absMs = Math.abs(diffMs);
        var seconds = Math.floor(absMs / 1000);
        var minutes = Math.floor(seconds / 60);
        var hours = Math.floor(minutes / 60);
        var days = Math.floor(hours / 24);
        var weeks = Math.floor(days / 7);
        var months = Math.floor(days / 30);
        var years = Math.floor(days / 365);

        var prefix = diffMs < 0 ? 'hace ' : 'en ';

        if (seconds < 60) return prefix + 'unos segundos';
        if (minutes < 60) return prefix + minutes + ' minuto' + (minutes !== 1 ? 's' : '');
        if (hours < 24) return prefix + hours + ' hora' + (hours !== 1 ? 's' : '');
        if (days < 7) return prefix + days + ' dia' + (days !== 1 ? 's' : '');
        if (weeks < 5) return prefix + weeks + ' semana' + (weeks !== 1 ? 's' : '');
        if (months < 12) return prefix + months + ' mes' + (months !== 1 ? 'es' : '');
        return prefix + years + ' año' + (years !== 1 ? 's' : '');
    }

    function dateToDatetimeLocal(date) {
        var local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        return local.getFullYear() + '-' +
            pad(local.getMonth() + 1) + '-' +
            pad(local.getDate()) + 'T' +
            pad(local.getHours()) + ':' +
            pad(local.getMinutes());
    }

    function loadHistory() {
        try {
            var raw = localStorage.getItem(HISTORY_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveToHistory(timestamp, unit, iso, timezone) {
        var history = loadHistory();
        var entry = {
            id: Date.now(),
            timestamp: timestamp,
            unit: unit,
            iso: iso,
            timezone: timezone,
        };
        history = history.filter(function (h) {
            return !(h.timestamp === entry.timestamp && h.unit === entry.unit);
        });
        history.unshift(entry);
        if (history.length > MAX_HISTORY) {
            history = history.slice(0, MAX_HISTORY);
        }
        try {
            localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
        } catch (e) {}
        return history;
    }

    function clearHistory() {
        try {
            localStorage.removeItem(HISTORY_KEY);
        } catch (e) {}
    }

    /* ====================================================================
     * CONVERSION ENGINE
     * ==================================================================== */

    function convert(timestamp, unit, timezone) {
        var raw = String(timestamp).trim();
        if (!isValidNumeric(raw)) {
            return { error: 'El timestamp debe ser numerico.' };
        }

        var parsed = Number(raw);
        if (!isFinite(parsed)) {
            return { error: 'Timestamp invalido.' };
        }

        var millis = getMilliseconds(parsed, unit);
        var date = new Date(millis);

        if (isNaN(date.getTime())) {
            return { error: 'No se pudo convertir el timestamp.' };
        }

        var unixSeconds = Math.floor(millis / 1000);

        return {
            error: null,
            unixSeconds: String(unixSeconds),
            unixMillis: String(millis),
            iso8601: date.toISOString(),
            rfc2822: date.toUTCString(),
            utc: date.toUTCString(),
            local: date.toString(),
            timezoneFormatted: formatForTimezone(date, timezone) + ' (' + timezone + ')',
            timezone: timezone,
            relative: formatRelativeTime(date),
            date: date,
            iso: date.toISOString(),
        };
    }

    /* ====================================================================
     * DOM HELPERS
     * ==================================================================== */

    function createElement(tag, attrs, children) {
        var el = document.createElement(tag);
        if (attrs) {
            for (var key in attrs) {
                if (attrs.hasOwnProperty(key)) {
                    if (key === 'className') {
                        el.className = attrs[key];
                    } else if (key === 'innerHTML') {
                        el.innerHTML = attrs[key];
                    } else if (key.startsWith('on')) {
                        el.addEventListener(key.slice(2).toLowerCase(), attrs[key]);
                    } else if (key === 'style' && typeof attrs[key] === 'object') {
                        for (var s in attrs[key]) {
                            if (attrs[key].hasOwnProperty(s)) {
                                el.style[s] = attrs[key][s];
                            }
                        }
                    } else {
                        el.setAttribute(key, attrs[key]);
                    }
                }
            }
        }
        if (children) {
            for (var i = 0; i < children.length; i++) {
                if (typeof children[i] === 'string') {
                    el.appendChild(document.createTextNode(children[i]));
                } else if (children[i]) {
                    el.appendChild(children[i]);
                }
            }
        }
        return el;
    }

    /* ====================================================================
     * BLOCK HYDRATOR
     * ==================================================================== */

    function hydrateBlock(container) {
        // Read data attributes
        var initialTs = container.getAttribute('data-timestamp') || '1714132800';
        var initialUnit = container.getAttribute('data-unit') || 's';
        var initialTz = container.getAttribute('data-timezone') || 'local';
        var showHistory = container.getAttribute('data-show-history') !== '0';

        // Current state
        var currentTs = initialTs;
        var currentUnit = initialUnit;
        var currentTz = initialTz;
        var currentResults = null;

        // Unique ID prefix derived from the container id so multiple blocks
        // on the same page do not collide.
        var idPrefix = (container.id || 'ts') + '_';

        // Refs
        var tsInput, tsUnit, tzSelect, dateInput, resultsBody, summaryEl, errorEl, historyContainer, copyBtn;

        /* ====== Build UI ====== */

        function buildUI() {
            container.innerHTML = '';

            // Error box
            errorEl = createElement('div', { className: 'ts-error' });
            container.appendChild(errorEl);

            // Summary
            summaryEl = createElement('div', { className: 'ts-summary' });
            container.appendChild(summaryEl);

            // Grid row 1: timestamp input + date input
            var grid1 = createElement('div', { className: 'ts-grid' });
            var tsField = createElement('div', {});
            tsField.appendChild(createElement('label', { for: idPrefix + 'input' }, ['Unix timestamp']));
            tsInput = createElement('input', {
                id: idPrefix + 'input',
                type: 'text',
                value: currentTs,
                placeholder: '1714132800',
                inputmode: 'numeric',
                autocomplete: 'off',
                spellcheck: 'false',
            });
            tsField.appendChild(tsInput);
            grid1.appendChild(tsField);

            var dateField = createElement('div', {});
            dateField.appendChild(createElement('label', { for: idPrefix + 'date_input' }, ['Fecha y hora (local)']));
            dateInput = createElement('input', {
                id: idPrefix + 'date_input',
                type: 'datetime-local',
            });
            dateField.appendChild(dateInput);
            grid1.appendChild(dateField);
            container.appendChild(grid1);

            // Grid row 2: unit + timezone
            var grid2 = createElement('div', { className: 'ts-grid' });
            var unitField = createElement('div', {});
            unitField.appendChild(createElement('label', { for: idPrefix + 'unit' }, ['Unidad']));
            tsUnit = createElement('select', { id: idPrefix + 'unit' });
            UNITS.forEach(function (u) {
                var opt = createElement('option', { value: u.value }, [u.label]);
                if (u.value === currentUnit) opt.selected = true;
                tsUnit.appendChild(opt);
            });
            unitField.appendChild(tsUnit);
            grid2.appendChild(unitField);

            var tzField = createElement('div', {});
            tzField.appendChild(createElement('label', { for: idPrefix + 'timezone' }, ['Zona horaria de salida']));
            tzSelect = createElement('select', { id: idPrefix + 'timezone' });
            TIMEZONES.forEach(function (tz) {
                var opt = createElement('option', { value: tz.value }, [tz.label]);
                if (tz.value === currentTz) opt.selected = true;
                tzSelect.appendChild(opt);
            });
            tzField.appendChild(tzSelect);
            grid2.appendChild(tzField);
            container.appendChild(grid2);

            // Quick examples
            var exampleLabel = createElement('label', {}, ['Ejemplos rapidos']);
            container.appendChild(exampleLabel);
            var exampleRow = createElement('div', {});
            QUICK_EXAMPLES.forEach(function (ex) {
                exampleRow.appendChild(createElement('button', {
                    type: 'button',
                    className: 'ts-example',
                    onClick: function () {
                        currentTs = ex.ts;
                        currentUnit = ex.unit;
                        tsInput.value = ex.ts;
                        tsUnit.value = ex.unit;
                        doConvertAction();
                    },
                }, [ex.label]));
            });
            container.appendChild(exampleRow);

            // Action buttons
            var actionsBar = createElement('div', { className: 'ts-actions-bar' });
            actionsBar.appendChild(createElement('button', {
                type: 'button',
                className: 'ts-action',
                onClick: doConvertFromUnix,
            }, ['Desde Unix']));
            actionsBar.appendChild(createElement('button', {
                type: 'button',
                className: 'ts-action',
                onClick: doConvertFromDate,
            }, ['Desde fecha']));
            actionsBar.appendChild(createElement('button', {
                type: 'button',
                className: 'ts-action',
                onClick: doNow,
            }, ['Ahora']));
            actionsBar.appendChild(copyBtn = createElement('button', {
                type: 'button',
                className: 'ts-action',
            }, ['Copiar enlace']));
            container.appendChild(actionsBar);

            // Results table
            resultsBody = createElement('tbody', { id: idPrefix + 'results_body' });
            var table = createElement('table', { className: 'ts-results-table' },
                [
                    createElement('thead', {},
                        [createElement('tr', {},
                            [
                                createElement('th', { scope: 'col' }, ['Campo']),
                                createElement('th', { scope: 'col' }, ['Valor']),
                            ]
                        )]
                    ),
                    resultsBody,
                ]
            );
            var tableWrap = createElement('div', { className: 'ts-results-table-wrap' }, [table]);
            container.appendChild(tableWrap);

            // History section
            historyContainer = createElement('div', { className: 'ts-history-section', id: idPrefix + 'history_section' });
            container.appendChild(historyContainer);

            // Events
            tsInput.addEventListener('input', function () {
                currentTs = tsInput.value;
                var detected = autoDetectUnit(currentTs);
                if (detected !== currentUnit && isValidNumeric(currentTs)) {
                    currentUnit = detected;
                    tsUnit.value = detected;
                }
                debouncedConvert();
            });

            tsUnit.addEventListener('change', function () {
                currentUnit = tsUnit.value;
                if (isValidNumeric(currentTs)) doConvert();
            });

            tzSelect.addEventListener('change', function () {
                currentTz = tzSelect.value;
                if (isValidNumeric(currentTs)) doConvert();
            });

            dateInput.addEventListener('change', function () {
                // No auto-convert, user must click "Desde fecha"
            });

            copyBtn.addEventListener('click', doCopyLink);

            // Load from URL params
            fillFromUrl();

            // Initial conversion
            doConvert();
        }

        /* ====== Debounce ====== */

        var debounceTimer = null;

        function debouncedConvert() {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                if (isValidNumeric(currentTs)) {
                    doConvert();
                }
            }, 300);
        }

        /* ====== Conversion actions ====== */

        function showError(msg) {
            errorEl.textContent = msg;
            errorEl.classList.add('visible');
        }

        function clearError() {
            errorEl.textContent = '';
            errorEl.classList.remove('visible');
        }

        function renderResults(conv) {
            resultsBody.innerHTML = '';
            RESULT_FIELDS.forEach(function (field) {
                var value = conv[field.key] || '';
                var tr = createElement('tr', {},
                    [
                        createElement('td', {}, [field.label]),
                        createElement('td', {},
                            [createElement('code', { className: 'ts-results-value' }, [value])]
                        ),
                    ]
                );
                resultsBody.appendChild(tr);
            });
            // Relative time row
            var relTr = createElement('tr', {},
                [
                    createElement('td', {}, ['Tiempo relativo']),
                    createElement('td', {},
                        [createElement('span', { className: 'ts-relative-badge' }, [conv.relative])]
                    ),
                ]
            );
            resultsBody.appendChild(relTr);
        }

        function renderHistory() {
            if (!showHistory) {
                historyContainer.innerHTML = '';
                return;
            }
            var history = loadHistory();
            if (history.length === 0) {
                historyContainer.innerHTML = '';
                return;
            }
            historyContainer.innerHTML = '';
            var titleRow = createElement('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                [
                    createElement('h4', { style: { margin: '0 0 0.5rem' } }, ['Historial']),
                    createElement('button', { className: 'ts-history-clear', onClick: doClearHistory }, ['Limpiar']),
                ]
            );
            historyContainer.appendChild(titleRow);

            var list = createElement('ul', { className: 'ts-history-list' });
            history.forEach(function (entry) {
                var dateStr = new Date(entry.iso).toLocaleDateString('es-ES');
                var li = createElement('li', {
                    className: 'ts-history-item',
                    onClick: function () {
                        currentTs = entry.timestamp;
                        currentUnit = entry.unit;
                        tsInput.value = entry.timestamp;
                        tsUnit.value = entry.unit;
                        doConvertAction();
                    },
                },
                    [
                        createElement('span', { className: 'ts-history-ts' }, [entry.timestamp]),
                        createElement('span', { className: 'ts-history-date' }, [dateStr]),
                    ]
                );
                list.appendChild(li);
            });
            historyContainer.appendChild(list);
        }

        function doConvert() {
            clearError();
            if (!currentTs || !isValidNumeric(currentTs)) {
                showError('Introduce un timestamp numerico.');
                return;
            }
            var detected = autoDetectUnit(currentTs);
            if (detected !== currentUnit) {
                currentUnit = detected;
                tsUnit.value = detected;
            }
            var conv = convert(currentTs, currentUnit, currentTz);
            if (conv.error) {
                showError(conv.error);
                return;
            }
            currentResults = conv;
            summaryEl.textContent = 'Timestamp convertido correctamente.';
            dateInput.value = dateToDatetimeLocal(conv.date);
            renderResults(conv);
            renderHistory();
        }

        /**
         * Conversion desencadenada por una accion explicita del usuario
         * (botones "Desde Unix", "Desde fecha", "Ahora", ejemplos o historial).
         * Solo aqui se persiste en el historial y se actualiza la URL
         * compartible, evitando entradas parciales al escribir.
         */
        function doConvertAction() {
            doConvert();
            if (currentResults && !currentResults.error) {
                updateShareUrl(currentTs, currentUnit, currentTz);
                saveToHistory(currentTs, currentUnit, currentResults.iso, currentTz);
                renderHistory();
            }
        }

        function doConvertFromUnix() {
            doConvertAction();
        }

        function doConvertFromDate() {
            clearError();
            var val = dateInput.value;
            if (!val) {
                showError('Selecciona una fecha y hora para convertir.');
                return;
            }
            var date = new Date(val);
            if (isNaN(date.getTime())) {
                showError('Fecha invalida.');
                return;
            }
            var millis = date.getTime();
            currentTs = currentUnit === 'ms' ? String(millis) : String(Math.floor(millis / 1000));
            tsInput.value = currentTs;
            summaryEl.textContent = 'Fecha convertida a Unix correctamente.';
            doConvertAction();
        }

        function doNow() {
            var now = new Date();
            var nowMs = now.getTime();
            currentTs = currentUnit === 'ms' ? String(nowMs) : String(Math.floor(nowMs / 1000));
            tsInput.value = currentTs;
            dateInput.value = dateToDatetimeLocal(now);
            summaryEl.textContent = 'Timestamp actual.';
            doConvertAction();
        }

        function doCopyLink() {
            var url = updateShareUrl(currentTs, currentUnit, currentTz);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    copyBtn.textContent = 'Enlace copiado';
                    setTimeout(function () { copyBtn.textContent = 'Copiar enlace'; }, 1500);
                }).catch(function () {
                    showError('No se pudo copiar. Enlace: ' + url);
                });
            } else {
                showError('Copia manual: ' + url);
            }
        }

        function doClearHistory() {
            clearHistory();
            renderHistory();
        }

        function updateShareUrl(ts, unit, tz) {
            var url = new URL(window.location.href);
            url.searchParams.set('ts', String(ts));
            url.searchParams.set('unit', unit);
            url.searchParams.set('tz', tz);
            window.history.replaceState({}, '', url.toString());
            return url.toString();
        }

        function fillFromUrl() {
            var url = new URL(window.location.href);
            var ts = url.searchParams.get('ts');
            var unit = url.searchParams.get('unit');
            var tz = url.searchParams.get('tz');

            if (ts && ts !== '') {
                currentTs = ts;
                tsInput.value = ts;
            }
            if (unit === 's' || unit === 'ms') {
                currentUnit = unit;
                tsUnit.value = unit;
            }
            if (tz) {
                var valid = false;
                for (var i = 0; i < TIMEZONES.length; i++) {
                    if (TIMEZONES[i].value === tz) {
                        valid = true;
                        break;
                    }
                }
                if (valid) {
                    currentTz = tz;
                    tzSelect.value = tz;
                }
            }
        }

        /* ====== Initialize ====== */
        buildUI();
    }

    /* ====================================================================
     * HYDRATE ALL BLOCKS ON PAGE
     * ==================================================================== */

    function hydrateAll() {
        var containers = document.querySelectorAll('.atareao-timestamp-helper[data-timestamp]');
        for (var i = 0; i < containers.length; i++) {
            hydrateBlock(containers[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hydrateAll);
    } else {
        hydrateAll();
    }
})();
