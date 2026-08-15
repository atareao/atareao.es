/**
 * Bloque de Timestamp Helper
 *
 * Editor Gutenberg con:
 * - Conversion bidireccional Unix timestamp <-> fecha legible
 * - Auto-deteccion de segundos (10 digitos) vs milisegundos (13 digitos)
 * - Resultados en ISO 8601, RFC 2822, UTC, local y zona seleccionada
 * - Tiempo relativo ("hace 2 horas", "en 3 dias")
 * - Historial en localStorage
 * - URLs compartibles via query params
 * - Actualizacion en tiempo real
 * - Botones de ejemplo rapido
 */
(function (wp) {
    'use strict';

    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var __ = wp.i18n.__;

    /* ====================================================================
     * TIMEZONE LIST
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

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function dateToDatetimeLocal(date) {
        var local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        return local.getFullYear() + '-' +
            pad(local.getMonth() + 1) + '-' +
            pad(local.getDate()) + 'T' +
            pad(local.getHours()) + ':' +
            pad(local.getMinutes());
    }

    function getTimezoneOffsetName() {
        var offset = -new Date().getTimezoneOffset();
        var sign = offset >= 0 ? '+' : '-';
        var h = pad(Math.floor(Math.abs(offset) / 60));
        var m = pad(Math.abs(offset) % 60);
        return 'UTC' + sign + h + ':' + m;
    }

    function loadHistory() {
        try {
            var raw = localStorage.getItem(HISTORY_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveToHistory(timestamp, unit, results) {
        var history = loadHistory();
        var entry = {
            id: Date.now(),
            timestamp: timestamp,
            unit: unit,
            iso: results.iso,
            timezone: results.timezone,
        };
        // Remove duplicate if exists
        history = history.filter(function (h) {
            return !(h.timestamp === entry.timestamp && h.unit === entry.unit);
        });
        history.unshift(entry);
        if (history.length > MAX_HISTORY) {
            history = history.slice(0, MAX_HISTORY);
        }
        try {
            localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
        } catch (e) {
            // localStorage full or unavailable
        }
        return history;
    }

    function clearHistory() {
        try {
            localStorage.removeItem(HISTORY_KEY);
        } catch (e) {
            // ignore
        }
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
        var tzFormatted = formatForTimezone(date, timezone);

        return {
            error: null,
            unixSeconds: String(unixSeconds),
            unixMillis: String(millis),
            iso8601: date.toISOString(),
            rfc2822: date.toUTCString(),
            utc: date.toUTCString(),
            local: date.toString(),
            timezoneFormatted: tzFormatted + ' (' + timezone + ')',
            timezone: timezone,
            relative: formatRelativeTime(date),
            date: date,
            iso: date.toISOString(),
        };
    }

    function convertFromDate(dateValue, unit, timezone) {
        if (!dateValue) {
            return { error: 'Selecciona una fecha y hora para convertir.' };
        }
        var date = new Date(dateValue);
        if (isNaN(date.getTime())) {
            return { error: 'Fecha invalida.' };
        }
        var millis = date.getTime();
        var ts = unit === 'ms' ? String(millis) : String(Math.floor(millis / 1000));
        return { timestamp: ts, results: convert(ts, unit, timezone) };
    }

    /* ====================================================================
     * RESULT ROWS
     * ==================================================================== */

    var RESULT_FIELDS = [
        { key: 'unixSeconds', label: 'Unix (segundos)' },
        { key: 'unixMillis', label: 'Unix (milisegundos)' },
        { key: 'iso8601', label: 'ISO 8601' },
        { key: 'rfc2822', label: 'RFC 2822' },
        { key: 'utc', label: 'UTC' },
        { key: 'local', label: 'Local navegador' },
        { key: 'timezoneFormatted', label: 'Zona seleccionada' },
    ];

    /* ====================================================================
     * EDIT COMPONENT
     * ==================================================================== */

    function TimestampEditor(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;

        var timestamp = attributes.timestamp || '';
        var unit = attributes.unit || 's';
        var timezone = attributes.timezone || 'local';
        var showHistory = attributes.showHistory !== false;

        var _useState = useState(timestamp);
        var inputValue = _useState[0];
        var setInputValue = _useState[1];

        var _useState2 = useState('');
        var dateInputValue = _useState2[0];
        var setDateInputValue = _useState2[1];

        var _useState3 = useState(null);
        var results = _useState3[0];
        var setResults = _useState3[1];

        var _useState4 = useState('');
        var summary = _useState4[0];
        var setSummary = _useState4[1];

        var _useState5 = useState('');
        var errorMessage = _useState5[0];
        var setErrorMessage = _useState5[1];

        var _useState6 = useState([]);
        var history = _useState6[0];
        var setHistory = _useState6[1];

        var _useState7 = useState('');
        var copyButtonText = _useState7[0];
        var setCopyButtonText = _useState7[1];

        var debounceRef = useRef(null);
        var hasInitialized = useRef(false);
        var copyTimerRef = useRef(null);

        // Load history on mount
        useEffect(function () {
            setHistory(loadHistory());
        }, []);

        // Cleanup timers on unmount
        useEffect(function () {
            return function () {
                if (copyTimerRef.current) {
                    clearTimeout(copyTimerRef.current);
                }
            };
        }, []);

        // Initial conversion on mount (from URL params or default)
        useEffect(function () {
            if (hasInitialized.current) return;
            hasInitialized.current = true;

            var urlParams = new URLSearchParams(window.location.search);
            var tsParam = urlParams.get('ts');
            var unitParam = urlParams.get('unit');
            var tzParam = urlParams.get('tz');

            var initialTs = tsParam || timestamp || '1714132800';
            var initialUnit = (unitParam === 's' || unitParam === 'ms') ? unitParam : unit;
            var validTz = TIMEZONES.some(function (t) { return t.value === tzParam; });
            var initialTz = validTz ? tzParam : timezone;

            if (tsParam || timestamp) {
                setInputValue(initialTs);
                setAttributes({ timestamp: initialTs });
            }
            if (unitParam) {
                setAttributes({ unit: initialUnit });
            }
            if (tzParam && validTz) {
                setAttributes({ timezone: initialTz });
            }

            var conv = convert(initialTs, initialUnit, initialTz);
            if (conv && !conv.error) {
                setResults(conv);
                setSummary('Timestamp convertido correctamente.');
                setDateInputValue(dateToDatetimeLocal(conv.date));
            } else if (conv) {
                setErrorMessage(conv.error);
            }
        }, []);

        // Auto-detect unit when input changes
        useEffect(function () {
            if (!inputValue) return;
            if (!isValidNumeric(inputValue)) return;
            var detected = autoDetectUnit(inputValue);
            if (detected !== unit) {
                setAttributes({ unit: detected });
            }
        }, [inputValue]);

        // Debounced real-time conversion
        useEffect(function () {
            if (!inputValue) return;
            if (!isValidNumeric(inputValue)) return;

            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }

            debounceRef.current = setTimeout(function () {
                var detectedUnit = autoDetectUnit(inputValue);
                var conv = convert(inputValue, detectedUnit, timezone);
                if (conv && !conv.error) {
                    setResults(conv);
                    setErrorMessage('');
                    setSummary('Conversion en tiempo real.');
                    setDateInputValue(dateToDatetimeLocal(conv.date));
                }
            }, 300);

            return function () {
                if (debounceRef.current) {
                    clearTimeout(debounceRef.current);
                }
            };
        }, [inputValue, timezone]);

        function handleConvertFromUnix() {
            setErrorMessage('');
            if (!inputValue || !isValidNumeric(inputValue)) {
                setErrorMessage('Introduce un timestamp numerico.');
                return;
            }
            var detectedUnit = autoDetectUnit(inputValue);
            var conv = convert(inputValue, detectedUnit, timezone);
            if (conv && !conv.error) {
                setResults(conv);
                setSummary('Timestamp convertido correctamente.');
                setDateInputValue(dateToDatetimeLocal(conv.date));
                setAttributes({ timestamp: inputValue, unit: detectedUnit });
                var updated = saveToHistory(inputValue, detectedUnit, conv);
                setHistory(updated);
                updateShareUrl(inputValue, detectedUnit, timezone);
            } else if (conv) {
                setErrorMessage(conv.error);
            }
        }

        function handleConvertFromDate() {
            setErrorMessage('');
            var result = convertFromDate(dateInputValue, unit, timezone);
            if (result.error) {
                setErrorMessage(result.error);
                return;
            }
            setInputValue(result.timestamp);
            setAttributes({ timestamp: result.timestamp });
            setResults(result.results);
            setSummary('Fecha convertida a Unix correctamente.');
            if (result.results && !result.results.error) {
                var updated = saveToHistory(result.timestamp, unit, result.results);
                setHistory(updated);
                updateShareUrl(result.timestamp, unit, timezone);
            }
        }

        function handleNow() {
            var now = new Date();
            var nowMs = now.getTime();
            var nowTs = unit === 'ms' ? String(nowMs) : String(Math.floor(nowMs / 1000));
            setInputValue(nowTs);
            setAttributes({ timestamp: nowTs });
            setDateInputValue(dateToDatetimeLocal(now));
            var conv = convert(nowTs, unit, timezone);
            if (conv && !conv.error) {
                setResults(conv);
                setSummary('Timestamp actual.');
                var updated = saveToHistory(nowTs, unit, conv);
                setHistory(updated);
                updateShareUrl(nowTs, unit, timezone);
            }
        }

        function handleExample(ts, exUnit) {
            setInputValue(ts);
            setAttributes({ timestamp: ts, unit: exUnit });
            setDateInputValue('');
            var conv = convert(ts, exUnit, timezone);
            if (conv && !conv.error) {
                setResults(conv);
                setErrorMessage('');
                setSummary('Ejemplo cargado.');
                setDateInputValue(dateToDatetimeLocal(conv.date));
                var updated = saveToHistory(ts, exUnit, conv);
                setHistory(updated);
                updateShareUrl(ts, exUnit, timezone);
            }
        }

        function handleCopyLink() {
            var url = updateShareUrl(inputValue, unit, timezone);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    setCopyButtonText('Enlace copiado');
                    if (copyTimerRef.current) {
                        clearTimeout(copyTimerRef.current);
                    }
                    copyTimerRef.current = setTimeout(function () {
                        setCopyButtonText('');
                        copyTimerRef.current = null;
                    }, 1500);
                }).catch(function () {
                    setErrorMessage('No se pudo copiar: ' + url);
                });
            } else {
                setErrorMessage('Copia manual: ' + url);
            }
        }

        function handleHistoryClick(entry) {
            setInputValue(entry.timestamp);
            setAttributes({ timestamp: entry.timestamp, unit: entry.unit });
            var conv = convert(entry.timestamp, entry.unit, timezone);
            if (conv && !conv.error) {
                setResults(conv);
                setErrorMessage('');
                setSummary('Restaurado del historial.');
                setDateInputValue(dateToDatetimeLocal(conv.date));
                updateShareUrl(entry.timestamp, entry.unit, timezone);
            }
        }

        function handleClearHistory() {
            clearHistory();
            setHistory([]);
        }

        function updateShareUrl(ts, u, tz) {
            var url = new URL(window.location.href);
            url.searchParams.set('ts', String(ts));
            url.searchParams.set('unit', u);
            url.searchParams.set('tz', tz);
            window.history.replaceState({}, '', url.toString());
            return url.toString();
        }

        function getRelativeBadge(relative) {
            return el('span', { className: 'ts-relative-badge' }, relative);
        }

        /* ====== BUILD UI ====== */

        var blockProps = useBlockProps();

        // Editor placeholder when no conversion done yet
        if (!results) {
            return el('div', Object.assign({}, blockProps, { className: blockProps.className + ' atareao-timestamp-helper-editor' }),
                el('div', { className: 'ts-editor-icon' },
                    el('span', { className: 'dashicons dashicons-clock' })
                ),
                el('h3', {}, __('Timestamp Helper', 'atareao-functionality')),
                el('p', {}, __('Convierte Unix timestamp a fecha legible y viceversa.', 'atareao-functionality')),
                el('div', { className: 'ts-editor-preview' },
                    inputValue ? inputValue + ' (' + unit + ')' : __('Introduce un timestamp en el panel lateral', 'atareao-functionality')
                )
            );
        }

        // Inspector controls (sidebar)
        var inspectorControls = el(InspectorControls, {},
            el(PanelBody, { title: __('Configuracion', 'atareao-functionality'), initialOpen: true },
                el(TextControl, {
                    label: __('Timestamp', 'atareao-functionality'),
                    value: inputValue,
                    className: 'ts-inspector-input',
                    onChange: function (val) {
                        setInputValue(val);
                        setAttributes({ timestamp: val });
                    },
                }),
                el(SelectControl, {
                    label: __('Unidad', 'atareao-functionality'),
                    value: unit,
                    options: UNITS,
                    onChange: function (val) {
                        setAttributes({ unit: val });
                        if (inputValue && isValidNumeric(inputValue)) {
                            var conv = convert(inputValue, val, timezone);
                            if (conv && !conv.error) {
                                setResults(conv);
                            }
                        }
                    },
                }),
                el('div', { className: 'ts-inspector-examples' },
                    QUICK_EXAMPLES.map(function (ex) {
                        return el('button', {
                            key: ex.ts + ex.unit,
                            type: 'button',
                            onClick: function () { handleExample(ex.ts, ex.unit); },
                        }, ex.label);
                    })
                ),
                results && !results.error
                    ? el('div', { className: 'ts-inspector-preview' }, results.iso8601)
                    : null,
            ),
            el(PanelBody, { title: __('Zona horaria', 'atareao-functionality'), initialOpen: false },
                el(SelectControl, {
                    label: __('Zona de salida', 'atareao-functionality'),
                    value: timezone,
                    options: TIMEZONES,
                    onChange: function (val) {
                        setAttributes({ timezone: val });
                        if (inputValue && isValidNumeric(inputValue)) {
                            var conv = convert(inputValue, unit, val);
                            if (conv && !conv.error) {
                                setResults(conv);
                            }
                        }
                    },
                }),
            ),
            el(PanelBody, { title: __('Historial', 'atareao-functionality'), initialOpen: false },
                el(ToggleControl, {
                    label: __('Mostrar historial', 'atareao-functionality'),
                    checked: showHistory,
                    onChange: function (val) {
                        setAttributes({ showHistory: val });
                    },
                }),
            ),
        );

        // Main UI
        var mainContent = el('div', { className: 'atareao-timestamp-helper' },
            // Error message
            errorMessage ? el('div', { className: 'ts-error visible' }, errorMessage) : null,

            // Summary
            summary ? el('div', { className: 'ts-summary' }, summary) : null,

            // Input grid
            el('div', { className: 'ts-grid' },
                el('div', {},
                    el('label', { htmlFor: 'ts_input_' + props.clientId }, __('Unix timestamp', 'atareao-functionality')),
                    el('input', {
                        id: 'ts_input_' + props.clientId,
                        type: 'text',
                        value: inputValue,
                        placeholder: '1714132800',
                        inputMode: 'numeric',
                        autoComplete: 'off',
                        spellCheck: false,
                        className: 'atareao-contact-input',
                        onChange: function (e) {
                            setInputValue(e.target.value);
                            setAttributes({ timestamp: e.target.value });
                        },
                    })
                ),
                el('div', {},
                    el('label', { htmlFor: 'ts_date_' + props.clientId }, __('Fecha y hora (local)', 'atareao-functionality')),
                    el('input', {
                        id: 'ts_date_' + props.clientId,
                        type: 'datetime-local',
                        value: dateInputValue,
                        className: 'atareao-contact-input',
                        onChange: function (e) { setDateInputValue(e.target.value); },
                    })
                ),
            ),

            // Second grid row: unit + timezone
            el('div', { className: 'ts-grid' },
                el('div', {},
                    el('label', { htmlFor: 'ts_unit_' + props.clientId }, __('Unidad', 'atareao-functionality')),
                    el('select', {
                        id: 'ts_unit_' + props.clientId,
                        value: unit,
                        onChange: function (e) {
                            setAttributes({ unit: e.target.value });
                            if (inputValue && isValidNumeric(inputValue)) {
                                var conv = convert(inputValue, e.target.value, timezone);
                                if (conv && !conv.error) {
                                    setResults(conv);
                                }
                            }
                        },
                    },
                        UNITS.map(function (u) {
                            return el('option', { key: u.value, value: u.value }, u.label);
                        })
                    ),
                ),
                el('div', {},
                    el('label', { htmlFor: 'ts_tz_' + props.clientId }, __('Zona horaria de salida', 'atareao-functionality')),
                    el('select', {
                        id: 'ts_tz_' + props.clientId,
                        value: timezone,
                        onChange: function (e) {
                            setAttributes({ timezone: e.target.value });
                            if (inputValue && isValidNumeric(inputValue)) {
                                var conv = convert(inputValue, unit, e.target.value);
                                if (conv && !conv.error) {
                                    setResults(conv);
                                }
                            }
                        },
                    },
                        TIMEZONES.map(function (tz) {
                            return el('option', { key: tz.value, value: tz.value }, tz.label);
                        })
                    ),
                ),
            ),

            // Quick examples
            el('div', {},
                el('label', {}, __('Ejemplos rapidos', 'atareao-functionality')),
                el('div', {},
                    QUICK_EXAMPLES.map(function (ex) {
                        return el('button', {
                            key: ex.ts + ex.unit,
                            type: 'button',
                            className: 'ts-example',
                            onClick: function () { handleExample(ex.ts, ex.unit); },
                        }, ex.label);
                    })
                ),
            ),

            // Action buttons
            el('div', { className: 'ts-actions-bar', style: { marginTop: '0.75rem' } },
                el('button', { type: 'button', className: 'ts-action', onClick: handleConvertFromUnix },
                    __('Desde Unix', 'atareao-functionality')
                ),
                el('button', { type: 'button', className: 'ts-action', onClick: handleConvertFromDate },
                    __('Desde fecha', 'atareao-functionality')
                ),
                el('button', { type: 'button', className: 'ts-action', onClick: handleNow },
                    __('Ahora', 'atareao-functionality')
                ),
                el('button', { type: 'button', className: 'ts-action', onClick: handleCopyLink },
                    copyButtonText || __('Copiar enlace', 'atareao-functionality')
                ),
            ),

            // Results table
            results && !results.error ? el('div', { className: 'ts-results-table-wrap' },
                el('table', { className: 'ts-results-table' },
                    el('thead', {},
                        el('tr', {},
                            el('th', { scope: 'col' }, __('Campo', 'atareao-functionality')),
                            el('th', { scope: 'col' }, __('Valor', 'atareao-functionality')),
                        ),
                    ),
                    el('tbody', {},
                        RESULT_FIELDS.map(function (field) {
                            return el('tr', { key: field.key },
                                el('td', {}, field.label),
                                el('td', {},
                                    el('code', { className: 'ts-results-value' }, results[field.key])
                                ),
                            );
                        }),
                        // Relative time row
                        el('tr', { key: 'relative' },
                            el('td', {}, __('Tiempo relativo', 'atareao-functionality')),
                            el('td', {}, getRelativeBadge(results.relative)),
                        ),
                    ),
                ),
            ) : null,

            // History section
            showHistory && history.length > 0 ? el('div', { className: 'ts-history-section' },
                el('h4', {},
                    __('Historial', 'atareao-functionality'),
                    ' ',
                    el('button', {
                        className: 'ts-history-clear',
                        type: 'button',
                        onClick: handleClearHistory,
                    }, __('Limpiar', 'atareao-functionality')),
                ),
                el('ul', { className: 'ts-history-list' },
                    history.map(function (entry) {
                        return el('li', {
                            key: entry.id,
                            className: 'ts-history-item',
                            onClick: function () { handleHistoryClick(entry); },
                        },
                            el('span', { className: 'ts-history-ts' }, entry.timestamp),
                            el('span', { className: 'ts-history-date' },
                                new Date(entry.iso).toLocaleDateString('es-ES')
                            ),
                        );
                    }),
                ),
            ) : null,
        );

        return el('div', Object.assign({}, blockProps, { key: props.clientId }),
            inspectorControls,
            mainContent
        );
    }

    /* ====================================================================
     * SAVE FUNCTION (dynamic block — renders via PHP)
     * ==================================================================== */

    function save() {
        // Dynamic block: render_callback handles frontend HTML
        return null;
    }

    /* ====================================================================
     * REGISTER BLOCK
     * ==================================================================== */

    registerBlockType('atareao/timestamp-helper', {
        edit: TimestampEditor,
        save: save,
    });
})(window.wp);
