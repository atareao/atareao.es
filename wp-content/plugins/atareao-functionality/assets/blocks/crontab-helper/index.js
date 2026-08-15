/**
 * Bloque de Crontab Helper
 *
 * Editor Gutenberg con:
 * - Parseo de expresiones cron (5 y 6 campos, @alias, todos los operadores)
 * - Descripcion en lenguaje natural
 * - Calculo de proximas ejecuciones
 * - Constructor visual por campos
 * - Lenguaje natural a cron
 * - Calendario termico anual
 * - Export multi-formato (systemd, K8s, AWS, iCal)
 * - Historial, riesgo, URLs compartibles
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
    var Button = wp.components.Button;
    var Notice = wp.components.Notice;
    var TabPanel = wp.components.TabPanel;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useCallback = wp.element.useCallback;
    var __ = wp.i18n.__;

    /* ====================================================================
     * CRON PARSER ENGINE
     * ==================================================================== */

    var ALIASES = {
        '@hourly':  '0 * * * *',
        '@daily':   '0 0 * * *',
        '@weekly':  '0 0 * * 0',
        '@monthly': '0 0 1 * *',
        '@yearly':  '0 0 1 1 *',
        '@annually':'0 0 1 1 *',
        '@reboot':  '@reboot'
    };

    var DAY_NAMES = { sun: 0, mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6 };
    var MONTH_NAMES = { jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6, jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12 };

    var FIELD_RANGES = {
        second:     { min: 0, max: 59 },
        minute:     { min: 0, max: 59 },
        hour:       { min: 0, max: 23 },
        dayOfMonth: { min: 1, max: 31 },
        month:      { min: 1, max: 12 },
        dayOfWeek:  { min: 0, max: 7 }
    };

    var FIELD_NAMES = ['minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek'];
    var FIELD_NAMES_6 = ['second', 'minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek'];
    var FIELD_LABELS = { second: 'Segundos', minute: 'Minuto', hour: 'Hora', dayOfMonth: 'Dia del Mes', month: 'Mes', dayOfWeek: 'Dia de la Semana' };

    /**
     * Resolver alias cron.
     */
    function resolveAlias(expr) {
        expr = expr.trim().toLowerCase();
        if (ALIASES[expr]) {
            return { original: expr, expanded: ALIASES[expr] };
        }
        return { original: expr, expanded: expr };
    }

    /**
     * Normalizar nombres de dias y meses en un campo cron.
     */
    function normalizeNames(field) {
        var upper = field.toUpperCase();
        if (DAY_NAMES[upper] !== undefined) return String(DAY_NAMES[upper]);
        if (MONTH_NAMES[upper] !== undefined) return String(MONTH_NAMES[upper]);
        return field;
    }

    /**
     * Dividir un campo cron en sus partes (maneja comas, rangos, steps).
     */
    function parseCronField(field, range) {
        var min = range.min, max = range.max;

        // Nombres de dias/meses
        field = field.split(',').map(function (part) {
            return part.split('-').map(normalizeNames).join('-');
        }).join(',');

        // Si es exactamente '*', devolver todos los valores
        if (field === '*') {
            var all = [];
            for (var i = min; i <= max; i++) all.push(i);
            return all;
        }

        // Manejar L (last): dia de semana o dia del mes
        if (/^\d*L$/i.test(field)) {
            return ['L'];
        }

        // Manejar W (weekday)
        if (/^\d+W$/i.test(field)) {
            return ['W' + parseInt(field, 10)];
        }

        // Manejar # (nth weekday): 6#3 = tercer viernes
        if (/^\d+#\d+$/.test(field)) {
            return [field];
        }

        var values = [];
        var parts = field.split(',');

        for (var p = 0; p < parts.length; p++) {
            var part = parts[p].trim();
            var step = 1;
            var rangePart = part;

            // Detectar step: */5, 1-10/2, etc.
            var slashIdx = part.indexOf('/');
            if (slashIdx !== -1) {
                step = parseInt(part.slice(slashIdx + 1), 10);
                if (isNaN(step) || step < 1) step = 1;
                rangePart = part.slice(0, slashIdx);
            }

            // Si es '*', significa desde min hasta max
            if (rangePart === '*') {
                for (var i = min; i <= max; i += step) {
                    values.push(i);
                }
                continue;
            }

            // Rango: n-m
            var dashIdx = rangePart.indexOf('-');
            if (dashIdx !== -1) {
                var start = parseInt(rangePart.slice(0, dashIdx), 10);
                var end = parseInt(rangePart.slice(dashIdx + 1), 10);
                if (isNaN(start)) start = min;
                if (isNaN(end)) end = max;
                for (var i = start; i <= end; i += step) {
                    values.push(i);
                }
                continue;
            }

            // Valor unico
            var val = parseInt(rangePart, 10);
            if (!isNaN(val)) {
                values.push(val);
            }
        }

        // Filtrar duplicados y ordenar
        values = values.filter(function (v, i) {
            return values.indexOf(v) === i;
        }).sort(function (a, b) { return a - b; });

        return values;
    }

    /**
     * Parsear una expresion cron completa.
     * Retorna { valid, error, fields, rawFields, description, nextExecutions }.
     */
    function parseCronExpression(expr) {
        if (!expr || !expr.trim()) {
            return { valid: false, error: 'Introduce una expresion cron' };
        }

        var resolved = resolveAlias(expr);
        var cronStr = resolved.expanded;

        // @reboot es especial
        if (cronStr === '@reboot') {
            return {
                valid: true,
                isReboot: true,
                fields: null,
                rawFields: ['@reboot'],
                description: 'Se ejecuta al arrancar el sistema',
                nextExecutions: []
            };
        }

        var tokens = cronStr.trim().split(/\s+/);
        var isSixField = tokens.length === 6;
        var fieldNames = isSixField ? FIELD_NAMES_6 : FIELD_NAMES;

        if (tokens.length < 5 || tokens.length > 6) {
            return {
                valid: false,
                error: 'La expresion debe tener 5 campos (o 6 con segundos). Tiene ' + tokens.length + '.'
            };
        }

        var fields = {};
        var rawFields = {};
        var error = null;

        for (var i = 0; i < fieldNames.length; i++) {
            var name = fieldNames[i];
            var token = tokens[i];
            var range = FIELD_RANGES[name];

            rawFields[name] = token;

            try {
                fields[name] = parseCronField(token, range);
            } catch (e) {
                error = 'Error en campo "' + FIELD_LABELS[name] + '": ' + token;
                fields[name] = [];
            }
        }

        if (error) {
            return { valid: false, error: error, fields: fields, rawFields: rawFields };
        }

        // Validar que no esten vacios
        for (var i = 0; i < fieldNames.length; i++) {
            if (fields[fieldNames[i]].length === 0) {
                return {
                    valid: false,
                    error: 'Campo "' + FIELD_LABELS[fieldNames[i]] + '" no tiene valores validos',
                    fields: fields,
                    rawFields: rawFields
                };
            }
        }

        var description = describeCron(fields, isSixField);
        var nextExecutions = getNextExecutions(fields, isSixField, 10);

        return {
            valid: true,
            fields: fields,
            rawFields: rawFields,
            isSixField: isSixField,
            description: description,
            nextExecutions: nextExecutions
        };
    }

    /**
     * Generar descripcion en lenguaje natural.
     */
    function describeCron(fields, isSixField) {
        if (!fields) return '';

        var min = fields.minute || [];
        var hour = fields.hour || [];
        var dom = fields.dayOfMonth || [];
        var mon = fields.month || [];
        var dow = fields.dayOfWeek || [];
        var sec = fields.second || [];

        var parts = [];

        // Segundos
        if (isSixField && sec.length > 0) {
            if (sec.length === 60) {
                parts.push('cada segundo');
            } else if (sec.length === 1) {
                parts.push('en el segundo ' + sec[0]);
            } else {
                var secDesc = describeList(sec);
                parts.push('en los segundos ' + secDesc);
            }
        }

        // Minutos
        if (min.length === 60) {
            parts.push('cada minuto');
        } else if (min.length === 1) {
            parts.push('en el minuto ' + min[0]);
        } else {
            var minDesc = describeList(min);
            parts.push('cada ' + minDesc + ' minutos');
        }

        // Horas
        if (hour.length === 24) {
            // No decir nada si es "cada hora" porque ya lo cubre cada minuto
            if (min.length !== 60 || parts.length > 1) {
                // ok
            }
        } else if (hour.length === 1) {
            parts.push('a las ' + hour[0].toString().padStart(2, '0') + ':' + (min.length === 1 ? min[0].toString().padStart(2, '0') : '00'));
        } else {
            var hourDesc = describeList(hour);
            parts.push('a las horas ' + hourDesc);
        }

        // Dia del mes
        if (dom.length === 31) {
            // todos los dias - no decir nada
        } else if (dom.length === 1 && dom[0] === 'L') {
            parts.push('el ultimo dia del mes');
        } else if (dom.length === 1 && typeof dom[0] === 'string' && dom[0].charAt(0) === 'W') {
            parts.push('el dia laborable mas cercano al ' + parseInt(dom[0], 10));
        } else {
            var domDesc = describeList(dom);
            parts.push('el dia ' + domDesc + ' del mes');
        }

        // Mes
        if (mon.length === 12) {
            // todos los meses
        } else {
            var monNames = mon.map(function (m) {
                var months = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                return months[m] || m;
            });
            parts.push('durante ' + describeList(monNames));
        }

        // Dia de la semana
        if (dow.length === 8 || (dow.length === 7 && dow.indexOf(0) !== -1 && dow.indexOf(7) !== -1)) {
            // todos los dias
        } else {
            var dayNames = dow.map(function (d) {
                var days = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
                return days[d] || d;
            });

            // Detectar rangos de lunes a viernes
            if (dow.length === 5 && dow.indexOf(1) !== -1 && dow.indexOf(2) !== -1 && dow.indexOf(3) !== -1 && dow.indexOf(4) !== -1 && dow.indexOf(5) !== -1) {
                parts.push('de lunes a viernes');
            } else if (dow.length === 2 && dow.indexOf(0) !== -1 && dow.indexOf(6) !== -1) {
                parts.push('los fines de semana');
            } else {
                parts.push('los ' + describeList(dayNames));
            }
        }

        if (parts.length === 0) {
            return 'Cada minuto, todos los dias';
        }

        // Capitalizar primera letra
        var result = parts.join(', ');
        return result.charAt(0).toUpperCase() + result.slice(1);
    }

    /**
     * Describir una lista de valores en lenguaje natural.
     */
    function describeList(values) {
        if (values.length === 1) return String(values[0]);

        // Detectar si es un step regular
        if (values.length > 2) {
            var step = values[1] - values[0];
            var isRegular = true;
            for (var i = 2; i < values.length; i++) {
                if (values[i] - values[i - 1] !== step) {
                    isRegular = false;
                    break;
                }
            }
            if (isRegular && step > 0) {
                return values[0] + '-' + values[values.length - 1] + ' (cada ' + step + ')';
            }
        }

        // Detectar rango
        if (values[values.length - 1] - values[0] === values.length - 1) {
            return values[0] + '-' + values[values.length - 1];
        }

        // Lista normal
        return values.slice(0, -1).join(', ') + ' y ' + values[values.length - 1];
    }

    /**
     * Comprobar si un valor encaja en un campo cron.
     */
    function fieldMatches(value, fieldValues) {
        if (!fieldValues || fieldValues.length === 0) return false;
        if (fieldValues.length === 1 && fieldValues[0] === 'L') return false; // L necesita calculo especial
        return fieldValues.indexOf(value) !== -1;
    }

    /**
     * Calcular el ultimo dia del mes para una fecha dada.
     */
    function getLastDayOfMonth(year, month) {
        return new Date(year, month, 0).getDate();
    }

    /**
     * Comprobar si un dia coincide con los campos de dia del mes y dia de la semana.
     */
    function dayMatches(date, dom, dow) {
        var dayOfMonth = date.getDate();
        var dayOfWeek = date.getDay(); // 0=domingo
        var year = date.getFullYear();
        var month = date.getMonth() + 1;

        // Dia de la semana: 7 = domingo en cron
        var cronDOW = dayOfWeek === 0 ? 0 : dayOfWeek;

        var domMatch = false;
        var dowMatch = false;

        // Comprobar dia del mes
        if (dom) {
            if (dom.indexOf(dayOfMonth) !== -1) {
                domMatch = true;
            }
            // Comprobar L
            for (var i = 0; i < dom.length; i++) {
                if (dom[i] === 'L' && dayOfMonth === getLastDayOfMonth(year, month)) {
                    domMatch = true;
                }
                if (typeof dom[i] === 'string' && dom[i].charAt(0) === 'W') {
                    var targetDay = parseInt(dom[i], 10);
                    var nearestWeekday = getNearestWeekday(year, month, targetDay);
                    if (dayOfMonth === nearestWeekday) {
                        domMatch = true;
                    }
                }
            }
        }

        // Comprobar dia de la semana
        if (dow) {
            if (dow.indexOf(cronDOW) !== -1 || (dow.indexOf(7) !== -1 && cronDOW === 0)) {
                dowMatch = true;
            }
            // Comprobar # (nth weekday)
            for (var i = 0; i < dow.length; i++) {
                if (typeof dow[i] === 'string' && dow[i].indexOf('#') !== -1) {
                    var parts = dow[i].split('#');
                    var targetDOW = parseInt(parts[0], 10);
                    var nth = parseInt(parts[1], 10);
                    var count = 0;
                    for (var d = 1; d <= dayOfMonth; d++) {
                        var testDate = new Date(year, month - 1, d);
                        if (testDate.getDay() === targetDOW) {
                            count++;
                            if (d === dayOfMonth && count === nth) {
                                dowMatch = true;
                            }
                        }
                    }
                }
            }
        }

        // Si ambos son * (todos los valores), coincide
        if (!dom || dom.length === 0 || dom.length === 31) domMatch = true;
        if (!dow || dow.length === 0 || dow.length === 8 || (dow.length === 7 && dow.indexOf(0) !== -1 && dow.indexOf(7) !== -1)) dowMatch = true;

        // Si ambos estan especificados, cualquiera que coincida es valido (OR)
        var bothSpecified = dom.length > 0 && dom.length < 31 && dow.length > 0 && dow.length < 8;
        if (bothSpecified) {
            return domMatch || dowMatch;
        }
        return domMatch && dowMatch;
    }

    /**
     * Obtener el dia laborable mas cercano a una fecha.
     */
    function getNearestWeekday(year, month, day) {
        var date = new Date(year, month - 1, day);
        var dow = date.getDay();
        if (dow === 0) return day + 1; // domingo -> lunes
        if (dow === 6) return day - 1; // sabado -> viernes
        return day;
    }

    /**
     * Calcular proximas N ejecuciones.
     */
    function getNextExecutions(fields, isSixField, count) {
        if (!fields) return [];

        var min = fields.minute || [];
        var hour = fields.hour || [];
        var dom = fields.dayOfMonth || [];
        var mon = fields.month || [];
        var dow = fields.dayOfWeek || [];
        var sec = fields.second || [];

        var now = new Date();
        var results = [];
        var maxIterations = 525600; // 1 año en minutos
        var iterations = 0;
        var cursor = new Date(now);

        // Empezar desde el siguiente minuto
        cursor.setSeconds(0);
        cursor.setMilliseconds(0);
        cursor.setMinutes(cursor.getMinutes() + 1);

        while (results.length < count && iterations < maxIterations) {
            var year = cursor.getFullYear();
            var month = cursor.getMonth() + 1;

            // Comprobar mes
            if (mon.length > 0 && mon.length < 12) {
                if (mon.indexOf(month) === -1) {
                    // Saltar al siguiente mes
                    cursor.setMonth(cursor.getMonth() + 1);
                    cursor.setDate(1);
                    cursor.setHours(0);
                    cursor.setMinutes(0);
                    iterations++;
                    continue;
                }
            }

            // Comprobar dia
            if (!dayMatches(cursor, dom, dow)) {
                cursor.setDate(cursor.getDate() + 1);
                cursor.setHours(0);
                cursor.setMinutes(0);
                iterations++;
                continue;
            }

            // Comprobar hora
            if (hour.length > 0 && hour.length < 24) {
                if (hour.indexOf(cursor.getHours()) === -1) {
                    cursor.setHours(cursor.getHours() + 1);
                    cursor.setMinutes(0);
                    iterations++;
                    continue;
                }
            }

            // Comprobar minuto
            if (min.length > 0 && min.length < 60) {
                if (min.indexOf(cursor.getMinutes()) === -1) {
                    cursor.setMinutes(cursor.getMinutes() + 1);
                    iterations++;
                    continue;
                }
            }

            // Si llegamos aqui, todos los campos coinciden
            results.push(new Date(cursor));
            cursor.setMinutes(cursor.getMinutes() + 1);
            iterations++;
        }

        return results;
    }

    /* ====================================================================
     * NATURAL LANGUAGE → CRON
     * ==================================================================== */

    function naturalToCron(text) {
        if (!text || !text.trim()) return null;

        var lower = text.toLowerCase().trim();

        // @alias shortcuts
        if (lower === '@hourly' || lower === 'cada hora') return '0 * * * *';
        if (lower === '@daily' || lower === 'cada dia' || lower === 'todos los dias') return '0 0 * * *';
        if (lower === '@weekly' || lower === 'cada semana') return '0 0 * * 0';
        if (lower === '@monthly' || lower === 'cada mes') return '0 0 1 * *';
        if (lower === '@yearly' || lower === 'cada año' || lower === 'cada ano') return '0 0 1 1 *';

        var minute = '*', hour = '*', dom = '*', month = '*', dow = '*';

        // cada N minutos/horas
        var match = lower.match(/cada (\d+) minutos?/);
        if (match) { minute = '*/' + match[1]; }

        match = lower.match(/cada (\d+) horas?/);
        if (match) { hour = '*/' + match[1]; }

        // cada minuto / cada hora
        if (lower.indexOf('cada minuto') !== -1 && !lower.match(/cada \d+ minutos?/)) { minute = '*'; }
        if (lower.indexOf('cada hora') !== -1 && !lower.match(/cada \d+ horas?/)) { hour = '*'; }

        // a las HH:MM
        match = lower.match(/a las (\d{1,2}):?(\d{2})?/);
        if (match) {
            hour = parseInt(match[1], 10).toString();
            if (match[2]) minute = parseInt(match[2], 10).toString();
        }

        // de lunes a viernes
        if (lower.indexOf('lunes') !== -1 && lower.indexOf('viernes') !== -1) {
            dow = '1-5';
        }

        // fines de semana
        if (lower.indexOf('fin de semana') !== -1 || lower.indexOf('finde') !== -1) {
            dow = '0,6';
        }

        // los sabados, los domingos, etc.
        var dayMap = { lunes: 1, martes: 2, miercoles: 3, jueves: 4, viernes: 5, sabado: 6, domingo: 0 };
        for (var day in dayMap) {
            if (lower.indexOf(day) !== -1 && dow === '*') {
                dow = String(dayMap[day]);
            }
        }

        // cada mes el dia N
        match = lower.match(/el (dia )?(\d{1,2})( del mes)?/);
        if (match) { dom = match[2]; }

        // todos los dias
        if (lower.indexOf('todos los dias') !== -1 || lower.indexOf('cada dia') !== -1) {
            dom = '*';
            dow = '*';
        }

        // cada: patron generico "cada X minutos desde las HH hasta las HH"
        // (simplificado: solo capturamos lo basico)

        // @reboot
        if (lower.indexOf('arranque') !== -1 || lower.indexOf('reinicio') !== -1 || lower === '@reboot') {
            return '@reboot';
        }

        return minute + ' ' + hour + ' ' + dom + ' ' + month + ' ' + dow;
    }

    /* ====================================================================
     * EXPORT FUNCTIONS
     * ==================================================================== */

    function toSystemdTimer(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression);
        var cronStr = resolved.expanded;

        if (cronStr === '@reboot') return '[Unit]\nDescription=My cron task\n\n[Service]\nType=oneshot\nExecStart=/usr/bin/true\n\n[Install]\nWantedBy=multi-user.target';

        var tokens = cronStr.trim().split(/\s+/);
        if (tokens.length < 5) return '';

        // systemd usa: OnCalendar=daily|hourly|*-*-* HH:MM:00
        if (tokens.join(' ') === '0 0 * * *') return '[Timer]\nOnCalendar=daily\nPersistent=true';
        if (tokens.join(' ') === '0 * * * *') return '[Timer]\nOnCalendar=hourly\nPersistent=true';

        // OnCalendar=*-*-* HH:MM:00 (para 5 campos)
        var onCalendar = '*-*-* ' + tokens[1] + ':' + tokens[0] + ':00';

        // Si dia de mes no es *
        if (tokens[2] !== '*') {
            onCalendar = '*-*-' + tokens[2] + ' ' + tokens[1] + ':' + tokens[0] + ':00';
        }

        // Si mes no es *
        if (tokens[3] !== '*') {
            onCalendar = '*-' + tokens[3] + '-' + tokens[2] + ' ' + tokens[1] + ':' + tokens[0] + ':00';
        }

        // Si dia de semana no es * (systemd usa dias..)
        if (tokens[4] !== '*') {
            onCalendar = tokens[1] + ':' + tokens[0] + ':00';
        }

        return '[Timer]\nOnCalendar=' + onCalendar + '\nPersistent=true';
    }

    function toKubernetesYaml(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression);
        var cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '';

        return 'apiVersion: batch/v1\nkind: CronJob\nmetadata:\n  name: my-cronjob\nspec:\n  schedule: "' + cronStr + '"\n  jobTemplate:\n    spec:\n      template:\n        spec:\n          containers:\n          - name: my-container\n            image: busybox\n            command:\n            - /bin/sh\n            - -c\n            - "echo Hello"\n          restartPolicy: OnFailure';
    }

    function toEventBridgeRule(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression);
        var cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '';

        var tokens = cronStr.trim().split(/\s+/);
        if (tokens.length < 5) return '';

        // AWS EventBridge usa 6 campos: minutos horas dia-mes mes dia-semana año
        var minute = tokens[0];
        var hour = tokens[1];
        var dayOfMonth = tokens[2] === '*' ? '?' : tokens[2];
        var month = tokens[3];
        var dayOfWeek = tokens[4] === '*' ? '?' : tokens[4];

        return 'cron(' + minute + ' ' + hour + ' ' + dayOfMonth + ' ' + month + ' ' + dayOfWeek + ' *)';
    }

    function toIcal(expression, parsed, count) {
        count = count || 10;
        if (!parsed || !parsed.valid || parsed.isReboot) return '';

        var nextExecs = getNextExecutions(parsed.fields, parsed.isSixField, count);
        if (nextExecs.length === 0) return '';

        var lines = [];
        lines.push('BEGIN:VCALENDAR');
        lines.push('VERSION:2.0');
        lines.push('PRODID:-//atareao//Crontab Helper//ES');

        for (var i = 0; i < nextExecs.length; i++) {
            var d = nextExecs[i];
            var dtstart = d.getUTCFullYear() +
                pad2(d.getUTCMonth() + 1) +
                pad2(d.getUTCDate()) + 'T' +
                pad2(d.getUTCHours()) +
                pad2(d.getUTCMinutes()) + '00';
            lines.push('BEGIN:VEVENT');
            lines.push('DTSTART:' + dtstart);
            lines.push('DURATION:PT1M');
            lines.push('SUMMARY:Cron ejecucion: ' + expression);
            lines.push('END:VEVENT');
        }

        lines.push('END:VCALENDAR');
        return lines.join('\r\n');
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function toPlainText(expression, parsed) {
        if (!parsed) return 'Expresion: ' + expression + '\n(Pulsa "Analizar" para ver los detalles)';

        var lines = [];
        lines.push('Expresion: ' + expression);
        if (parsed.valid) {
            lines.push('Descripcion: ' + parsed.description);
            if (parsed.isReboot) {
                lines.push('Se ejecuta al arrancar el sistema.');
            } else if (parsed.nextExecutions && parsed.nextExecutions.length > 0) {
                lines.push('');
                lines.push('Proximas ejecuciones:');
                for (var i = 0; i < Math.min(parsed.nextExecutions.length, 5); i++) {
                    var d = parsed.nextExecutions[i];
                    lines.push('  ' + (i + 1) + '. ' + d.toISOString().replace('T', ' ').slice(0, 19) + ' UTC');
                }
            }
        } else {
            lines.push('Error: ' + parsed.error);
        }
        return lines.join('\n');
    }

    function toCrontabFile(expression) {
        return '# ┌───────── minuto (0-59)\n# │ ┌───────── hora (0-23)\n# │ │ ┌───────── dia del mes (1-31)\n# │ │ │ ┌───────── mes (1-12)\n# │ │ │ │ ┌───────── dia de la semana (0-7, 0=domingo)\n# │ │ │ │ │\n' + expression + '  /ruta/al/comando';
    }

    function toGithubAction(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression);
        var cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '';
        return 'name: Cron job\nrun-name: Scheduled task\non:\n  schedule:\n    - cron: \'' + cronStr + '\'\n  workflow_dispatch:\njobs:\n  run:\n    runs-on: ubuntu-latest\n    steps:\n      - name: Run task\n        run: echo "Execute task"';
    }

    function toAnsibleCron(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression);
        var cronStr = resolved.expanded;
        if (cronStr === '@reboot') {
            return 'cron:\n  name: "Reboot task"\n  special_time: reboot\n  job: "/usr/bin/true"';
        }
        var tokens = cronStr.trim().split(/\s+/);
        if (tokens.length < 5) return '';
        return 'cron:\n  name: "Scheduled task"\n  minute: "' + tokens[0] + '"\n  hour: "' + tokens[1] + '"\n  day: "' + tokens[2] + '"\n  month: "' + tokens[3] + '"\n  weekday: "' + tokens[4] + '"\n  job: "/usr/bin/true"';
    }

    function toCloudWatchExpression(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression);
        var cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '';
        var tokens = cronStr.trim().split(/\s+/);
        if (tokens.length < 5) return '';
        var minute = tokens[0];
        var hour = tokens[1];
        var dayOfMonth = tokens[2] === '*' ? '?' : tokens[2];
        var month = tokens[3];
        var dayOfWeek = tokens[4] === '*' ? '?' : tokens[4];
        return 'cron(' + minute + ' ' + hour + ' ' + dayOfMonth + ' ' + month + ' ' + dayOfWeek + ' *)';
    }

    function toSqlQuery(expression, parsed) {
        if (!expression) return '';
        if (!parsed || !parsed.valid || parsed.isReboot) return '-- @reboot no se puede expresar como consulta SQL';
        var fields = parsed.fields;
        var parts = [];
        if (fields.minute && fields.minute.length < 60) {
            parts.push('EXTRACT(MINUTE FROM scheduled_at) IN (' + fields.minute.join(', ') + ')');
        }
        if (fields.hour && fields.hour.length < 24) {
            parts.push('EXTRACT(HOUR FROM scheduled_at) IN (' + fields.hour.join(', ') + ')');
        }
        if (fields.dayOfMonth && fields.dayOfMonth.length < 31) {
            var domValues = fields.dayOfMonth.filter(function (v) { return typeof v === 'number'; });
            if (domValues.length > 0) {
                parts.push('EXTRACT(DAY FROM scheduled_at) IN (' + domValues.join(', ') + ')');
            }
        }
        if (fields.month && fields.month.length < 12) {
            parts.push('EXTRACT(MONTH FROM scheduled_at) IN (' + fields.month.join(', ') + ')');
        }
        if (fields.dayOfWeek && fields.dayOfWeek.length < 8) {
            var dowValues = fields.dayOfWeek.filter(function (v) { return typeof v === 'number'; });
            if (dowValues.length > 0) {
                parts.push('EXTRACT(DOW FROM scheduled_at) IN (' + dowValues.join(', ') + ')');
            }
        }
        if (parts.length === 0) {
            return 'SELECT * FROM tasks\nWHERE EXTRACT(MINUTE FROM scheduled_at) = 0;';
        }
        return 'SELECT * FROM tasks\nWHERE ' + parts.join('\n  AND ');
    }

    function toNaturalSummary(expression, parsed) {
        if (!expression) return '';
        if (!parsed || !parsed.valid) return 'Expresion no valida';
        if (parsed.isReboot) return 'Se ejecuta al arrancar del sistema. No hay un horario fijo.';
        var fields = parsed.fields;
        var ejecucionesPorDia = (fields.minute ? fields.minute.length : 60) * (fields.hour ? fields.hour.length : 24);
        if (ejecucionesPorDia === 0) ejecucionesPorDia = 60 * 24;
        var ejecucionesPorSemana = ejecucionesPorDia * 7;
        var ejecucionesPorMes = ejecucionesPorDia * 30;
        var ejecucionesPorAno = ejecucionesPorDia * 365;
        var lines = [];
        lines.push('Resumen de la expresion cron:');
        lines.push('');
        lines.push('  Descripcion: ' + parsed.description);
        lines.push('  Ejecuciones por dia: ' + ejecucionesPorDia);
        lines.push('  Ejecuciones por semana: ' + ejecucionesPorSemana);
        lines.push('  Ejecuciones por mes: ' + ejecucionesPorMes);
        lines.push('  Ejecuciones por ano: ' + ejecucionesPorAno);
        if (parsed.nextExecutions && parsed.nextExecutions.length > 0) {
            lines.push('  Proxima ejecucion: ' + parsed.nextExecutions[0].toISOString().replace('T', ' ').slice(0, 19));
        }
        return lines.join('\n');
    }


    /* ====================================================================
     * RISK ANALYSIS
     * ==================================================================== */

    function analyzeRisks(parsed) {
        var risks = [];
        if (!parsed || !parsed.valid) return risks;

        if (parsed.isReboot) {
            risks.push({ type: 'info', text: 'Se ejecuta al arrancar. Asegurate de que el sistema no arranque multiples veces en un periodo corto.' });
            return risks;
        }

        var fields = parsed.fields;
        if (!fields) return risks;

        var min = fields.minute || [];
        var hour = fields.hour || [];
        var dom = fields.dayOfMonth || [];
        var dow = fields.dayOfWeek || [];
        var raw = parsed.rawFields || {};

        // Cada minuto
        if (min.length === 60 && hour.length === 24) {
            risks.push({ type: 'error', text: 'Se ejecuta CADA MINUTO. Revisa si es realmente necesario para evitar carga excesiva en el servidor.' });
        }

        // */1 es equivalente a *
        if (raw.minute === '*/1') {
            risks.push({ type: 'warning', text: '*/1 es equivalente a *. Usa "*" directamente.' });
        }

        // Cron golf: lista de minutos secuenciales simplificable
        if (min.length > 2 && min[min.length - 1] - min[0] === min.length - 1) {
            var suggested = '*/' + (min[1] - min[0]);
            risks.push({ type: 'info', text: 'Los minutos ' + min[0] + '-' + min[min.length - 1] + ' se pueden simplificar a ' + suggested + '.' });
        }

        // Thundering herd: ejecucion exacta en punto
        if (min.length === 1 && min[0] === 0 && hour.length === 24) {
            risks.push({ type: 'warning', text: 'Se ejecuta justo en punto (:00). Si multiples servidores usan esta expresion, considera anadir un segundo aleatorio (ej: sleep $((RANDOM % 60))) para evitar thundering herd.' });
        }

        // Thundering herd: ejecucion a medianoche
        if (hour.length === 1 && hour[0] === 0 && min.length === 1 && min[0] === 0) {
            risks.push({ type: 'warning', text: 'Se ejecuta a las 00:00. Si es una tarea compartida, considera distribuirla en una hora aleatoria para evitar picos de carga.' });
        }

        // DST (cambio de hora)
        if (hour.length === 1 && hour[0] >= 2 && hour[0] <= 3) {
            risks.push({ type: 'warning', text: 'Posible conflicto con cambio de hora (DST). Esta ejecucion podria saltarse o duplicarse durante el cambio al horario de verano/invierno.' });
        }

        // Dia 31
        if (dom.indexOf(31) !== -1) {
            risks.push({ type: 'warning', text: 'No todos los meses tienen 31 dias. Febrero, abril, junio, septiembre y noviembre no ejecutaran esta tarea.' });
        }

        // 29 de febrero
        if (dom.indexOf(29) !== -1 && fields.month && fields.month.indexOf(2) !== -1) {
            risks.push({ type: 'warning', text: 'El 29 de febrero solo existe en anos bisiestos. Esta tarea se ejecutara aproximadamente cada 4 anos.' });
        }

        // Ambos dom y dow especificados (comportamiento OR)
        var domSpecified = dom.length > 0 && dom.length < 31;
        var dowSpecified = dow.length > 0 && dow.length < 8;
        if (domSpecified && dowSpecified) {
            risks.push({ type: 'info', text: 'Tanto dia del mes como dia de la semana estan especificados. La tarea se ejecutara cuando UNO DE LOS DOS coincida (comportamiento OR).' });
        }

        // Alta frecuencia (> 144 veces al dia)
        var executionsPerDay = min.length * hour.length;
        if (executionsPerDay > 144) {
            risks.push({ type: 'warning', text: 'Alta frecuencia: ' + executionsPerDay + ' ejecuciones/dia. Revisa si es necesario o puedes reducir la frecuencia.' });
        }

        // Solo fin de semana
        if (dowSpecified && dow.length === 2 && dow.indexOf(0) !== -1 && dow.indexOf(6) !== -1) {
            risks.push({ type: 'info', text: 'Solo se ejecuta los fines de semana. Asegurate de que es intencional.' });
        }

        // Solo dias laborables
        if (dowSpecified && dow.length === 5 && dow.indexOf(1) !== -1 && dow.indexOf(5) !== -1) {
            risks.push({ type: 'info', text: 'Solo se ejecuta en dias laborables (L-V).' });
        }

        return risks;
    }

    /* ====================================================================
     * HISTORY (localStorage)
     * ==================================================================== */

    function getHistory() {
        try {
            var data = localStorage.getItem('atareao_crontab_history');
            return data ? JSON.parse(data) : [];
        } catch (e) {
            return [];
        }
    }

    function addToHistory(expression, description) {
        try {
            var history = getHistory();
            // Eliminar duplicado si existe
            history = history.filter(function (item) { return item.expression !== expression; });
            // Añadir al inicio
            history.unshift({ expression: expression, description: description, timestamp: Date.now() });
            // Limitar a 20
            if (history.length > 20) history = history.slice(0, 20);
            localStorage.setItem('atareao_crontab_history', JSON.stringify(history));
            return history;
        } catch (e) {
            return [];
        }
    }

    /* ====================================================================
     * URL PARAMETERS
     * ==================================================================== */

    function getUrlParams() {
        var params = {};
        var query = window.location.search.substring(1);
        if (!query) return params;
        var pairs = query.split('&');
        for (var i = 0; i < pairs.length; i++) {
            var pair = pairs[i].split('=');
            params[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
        }
        return params;
    }

    function updateUrlParams(expression, timezone) {
        try {
            var url = new URL(window.location.href);
            if (expression) {
                url.searchParams.set('cron', expression);
            } else {
                url.searchParams.delete('cron');
            }
            if (timezone && timezone !== 'UTC') {
                url.searchParams.set('tz', timezone);
            } else {
                url.searchParams.delete('tz');
            }
            window.history.replaceState({}, '', url.toString());
        } catch (e) {
            // Fallback silencioso
        }
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        // Fallback
        return new Promise(function (resolve) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            resolve();
        });
    }

    /* ====================================================================
     * HEATMAP GENERATOR
     * ==================================================================== */

    function generateHeatmapData(fields, isSixField, year) {
        year = year || new Date().getFullYear();
        var data = {};

        var min = fields.minute || [];
        var hour = fields.hour || [];
        var dom = fields.dayOfMonth || [];
        var mon = fields.month || [];
        var dow = fields.dayOfWeek || [];

        for (var m = 1; m <= 12; m++) {
            var daysInMonth = new Date(year, m, 0).getDate();
            for (var d = 1; d <= daysInMonth; d++) {
                var date = new Date(year, m - 1, d);
                var key = year + '-' + pad2(m) + '-' + pad2(d);
                var count = 0;

                // Calcular cuantas veces se ejecuta este dia
                if (dayMatches(date, dom, dow)) {
                    // Contar ejecuciones en este dia
                    for (var h = 0; h < 24; h++) {
                        if (hour.length < 24 && hour.indexOf(h) === -1) continue;
                        for (var minVal = 0; minVal < 60; minVal++) {
                            if (min.length < 60 && min.indexOf(minVal) === -1) continue;
                            count++;
                        }
                    }
                }

                data[key] = count;
            }
        }

        return data;
    }

    function getHeatLevel(count, maxCount) {
        if (count === 0) return 0;
        if (maxCount === 0) return 0;
        var ratio = count / maxCount;
        if (ratio <= 0.05) return 1;
        if (ratio <= 0.15) return 2;
        if (ratio <= 0.30) return 3;
        if (ratio <= 0.50) return 4;
        if (ratio <= 0.70) return 5;
        if (ratio <= 0.85) return 6;
        return 7;
    }

    /* ====================================================================
     * REACT COMPONENTS
     * ==================================================================== */

    /**
     * Formatea una fecha para mostrar, incluyendo segundos si es necesario.
     */
    function formatTime(date, showSeconds) {
        var opts = { hour: '2-digit', minute: '2-digit' };
        if (showSeconds) opts.second = '2-digit';
        return date.toLocaleTimeString('es-ES', opts);
    }

    /**
     * Componente de preview de la expresion cron.
     */
    function CrontabPreview(props) {
        var expression = props.expression;
        var parsed = props.parsed;
        var onCopyLink = props.onCopyLink;
        var onCopyExpression = props.onCopyExpression;
        var copied = props.copied;
        var showSeconds = props.showSeconds;

        var statusClass = 'crontab-status crontab-status-valid';
        var statusText = 'Valida';
        if (!parsed || !parsed.valid) {
            statusClass = 'crontab-status crontab-status-invalid';
            statusText = 'Invalida';
        }

        var descClass = 'crontab-description';
        if (!parsed || !parsed.valid) {
            descClass += ' crontab-error';
        } else if (parsed.valid && parsed.fields && parsed.fields.minute && parsed.fields.minute.length === 60) {
            descClass += ' crontab-warning';
        }

        var children = [];

        // Expression display
        var exprChildren = [];
        exprChildren.push(el('code', { key: 'expr-code' }, expression || '? ? ? ? ?'));
        exprChildren.push(el('span', { key: 'expr-status', className: statusClass }, statusText));
        exprChildren.push(el('button', {
            key: 'expr-copy',
            className: 'crontab-btn crontab-btn-sm',
            onClick: onCopyExpression
        }, copied === 'expr' ? 'Copiado!' : 'Copiar'));

        children.push(el('div', { key: 'expr-display', className: 'crontab-expression-display' }, exprChildren));

        // Description
        var descText = '';
        if (!parsed) {
            descText = 'Introduce una expresion cron para analizarla';
        } else if (parsed.valid) {
            descText = parsed.description;
        } else {
            descText = parsed.error || 'Expresion invalida';
        }
        children.push(el('div', { key: 'desc', className: descClass }, descText));

        // Next executions
        if (parsed && parsed.valid && !parsed.isReboot && parsed.nextExecutions && parsed.nextExecutions.length > 0) {
            var tableRows = [];
            for (var i = 0; i < Math.min(parsed.nextExecutions.length, 5); i++) {
                var d = parsed.nextExecutions[i];
                var dayNames = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
                var row = el('tr', { key: 'row-' + i },
                    el('td', {}, String(i + 1)),
                    el('td', {}, d.toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' })),
                    el('td', {}, formatTime(d, showSeconds)),
                    el('td', {}, dayNames[d.getDay()])
                );
                tableRows.push(row);
            }

            var table = el('table', {},
                el('thead', {},
                    el('tr', {},
                        el('th', {}, '#'),
                        el('th', {}, 'Fecha'),
                        el('th', {}, 'Hora'),
                        el('th', {}, 'Dia')
                    )
                ),
                el('tbody', {}, tableRows)
            );

            children.push(el('div', { key: 'exec', className: 'crontab-executions' },
                el('h4', {}, 'Proximas ' + Math.min(parsed.nextExecutions.length, 5) + ' ejecuciones'),
                table
            ));
        }

        // Reboot message
        if (parsed && parsed.valid && parsed.isReboot) {
            children.push(el('div', { key: 'reboot', className: 'crontab-executions' },
                el('p', { style: { fontStyle: 'italic', color: '#888' } }, 'Esta tarea se ejecuta cada vez que arranca el sistema. No hay un horario fijo.')
            ));
        }

        // Copy link button
        children.push(el('div', { key: 'actions', style: { display: 'flex', gap: '0.5rem', marginTop: '0.5rem' } },
            el('button', {
                className: 'crontab-btn crontab-btn-secondary',
                onClick: onCopyLink
            }, copied === 'link' ? 'Enlace copiado!' : 'Copiar enlace')
        ));

        return el('div', {}, children);
    }

    /**
     * Componente de modo "Expert" - input de texto libre.
     */
    function ExpertMode(props) {
        var expression = props.expression;
        var setExpression = props.setExpression;
        var onAnalyze = props.onAnalyze;
        var useSeconds = props.useSeconds;

        var placeholder = useSeconds ? '*/15 * * * * *' : '*/15 * * * *';

        var fieldInfo = useSeconds ? '6 campos (segundos incluidos)' : '5 campos';

        var buttons = [
            { label: '@hourly', value: '@hourly' },
            { label: '@daily', value: '@daily' },
            { label: '@weekly', value: '@weekly' },
            { label: '@monthly', value: '@monthly' },
            { label: 'Cada min', value: '* * * * *' },
            { label: 'Cada 5 min', value: '*/5 * * * *' },
            { label: 'Cada 15 min', value: '*/15 * * * *' },
            { label: 'Cada hora', value: '0 * * * *' },
            { label: 'Cada 6 h', value: '0 */6 * * *' },
            { label: 'Medianoche', value: '0 0 * * *' },
            { label: '09:00 L-V', value: '0 9 * * 1-5' },
            { label: '07:30 L-V', value: '30 7 * * 1-5' },
            { label: 'Dia 1 del mes', value: '0 0 1 * *' },
            { label: 'Cada Lunes', value: '0 0 * * 1' },
            { label: 'Finde 10:00', value: '0 10 * * 0,6' }
        ];

        var buttonEls = buttons.map(function (b) {
            return el('button', {
                key: b.value,
                className: 'crontab-btn crontab-btn-sm',
                onClick: function () { setExpression(b.value); onAnalyze(b.value); }
            }, b.label);
        });

        return el('div', {},
            el('div', { className: 'crontab-input-group' },
                el('input', {
                    type: 'text',
                    value: expression,
                    placeholder: placeholder,
                    onChange: function (e) { setExpression(e.target.value); },
                    onKeyUp: function (e) {
                        if (e.key === 'Enter') onAnalyze(expression);
                    }
                }),
                el('button', {
                    className: 'crontab-btn',
                    onClick: function () { onAnalyze(expression); }
                }, 'Analizar')
            ),
            el('div', { className: 'crontab-btn-group' }, buttonEls),
            el('div', { style: { fontSize: '0.75rem', color: '#888', marginTop: '0.25rem', textAlign: 'right' } }, fieldInfo)
        );
    }

    /**
     * Componente de modo "Asistente" - selectores por campo.
     */
    function AssistantMode(props) {
        var expression = props.expression;
        var setExpression = props.setExpression;
        var onAnalyze = props.onAnalyze;
        var useSeconds = props.useSeconds;

        var fieldCount = useSeconds ? 6 : 5;
        var fields = [];
        if (expression && expression.trim()) {
            var tokens = expression.trim().split(/\s+/);
            if (tokens.length >= fieldCount) {
                fields = tokens.slice(0, fieldCount);
            }
        }
        while (fields.length < fieldCount) fields.push('*');

        var fieldOptions = {
            second: ['*', '*/5', '*/10', '*/15', '*/30', '0', '15', '30', '45'],
            minute: ['*', '*/5', '*/10', '*/15', '*/30', '0', '15', '30', '45'],
            hour: ['*', '*/2', '*/6', '*/12', '0', '6', '8', '9', '12', '18', '22', '23'],
            dayOfMonth: ['*', '*/2', '1', '15', '31', 'L'],
            month: ['*', '*/3', '1', '3', '6', '9', '12'],
            dayOfWeek: ['*', '0', '1', '2', '3', '4', '5', '6', '1-5', '0,6']
        };

        var fieldLabels = useSeconds
            ? ['Segundos', 'Minuto', 'Hora', 'Dia del Mes', 'Mes', 'Dia de la Semana']
            : ['Minuto', 'Hora', 'Dia del Mes', 'Mes', 'Dia de la Semana'];
        var fieldKeys = useSeconds
            ? ['second', 'minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek']
            : ['minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek'];

        var selectors = fieldKeys.map(function (key, idx) {
            var options = fieldOptions[key].map(function (opt) {
                return el('option', { key: opt, value: opt }, opt);
            });

            return el('div', { key: key, className: 'crontab-field-selector' },
                el('label', {}, fieldLabels[idx]),
                el('select', {
                    value: fields[idx] || '*',
                    onChange: function (e) {
                        var newFields = fields.slice();
                        newFields[idx] = e.target.value;
                        var expr = newFields.join(' ');
                        setExpression(expr);
                        onAnalyze(expr);
                    }
                }, options)
            );
        });

        return el('div', {},
            el('p', { style: { fontSize: '0.85rem', color: '#888', marginBottom: '0.5rem' } },
                'Selecciona los valores para cada campo:'
            ),
            el('div', { className: 'crontab-field-selectors' }, selectors)
        );
    }

    /**
     * Componente de modo "Natural" - lenguaje natural.
     */
    function NaturalMode(props) {
        var naturalText = props.naturalText;
        var setNaturalText = props.setNaturalText;
        var onNaturalToCron = props.onNaturalToCron;

        var examples = [
            'cada 15 minutos',
            'cada hora',
            'a las 9:00 de lunes a viernes',
            'todos los dias a las 23:00',
            'cada mes el dia 1',
            'cada 5 minutos de lunes a viernes'
        ];

        var exampleEls = examples.map(function (ex) {
            return el('button', {
                key: ex,
                className: 'crontab-btn crontab-btn-sm',
                onClick: function () { setNaturalText(ex); onNaturalToCron(ex); }
            }, ex);
        });

        return el('div', { className: 'crontab-natural-input' },
            el('textarea', {
                value: naturalText,
                placeholder: 'Escribe en espanol, por ejemplo:\n"cada 15 minutos de lunes a viernes"',
                onChange: function (e) { setNaturalText(e.target.value); },
                rows: 3
            }),
            el('button', {
                className: 'crontab-btn',
                style: { marginTop: '0.5rem' },
                onClick: function () { onNaturalToCron(naturalText); }
            }, 'Convertir a cron'),
            el('p', { className: 'crontab-hint' }, 'Ejemplos:'),
            el('div', { className: 'crontab-btn-group' }, exampleEls)
        );
    }

    /**
     * Componente de calendario termico.
     */
    function CalendarHeatmap(props) {
        var parsed = props.parsed;
        var showCalendar = props.showCalendar;

        if (!showCalendar || !parsed || !parsed.valid || parsed.isReboot) {
            return null;
        }

        var heatmapData = generateHeatmapData(parsed.fields, parsed.isSixField, new Date().getFullYear());
        var maxCount = 0;
        for (var key in heatmapData) {
            if (heatmapData[key] > maxCount) maxCount = heatmapData[key];
        }

        var monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        var dayHeaders = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];

        var months = [];
        for (var m = 1; m <= 12; m++) {
            var daysInMonth = new Date(new Date().getFullYear(), m, 0).getDate();
            var firstDay = new Date(new Date().getFullYear(), m - 1, 1).getDay();
            // Ajustar: lunes=0, domingo=6
            firstDay = firstDay === 0 ? 6 : firstDay - 1;

            var dayCells = [];
            // Espacios vacios antes del primer dia
            for (var e = 0; e < firstDay; e++) {
                dayCells.push(el('div', { key: 'empty-' + m + '-' + e, className: 'crontab-calendar-day cal-empty' }, ''));
            }

            for (var d = 1; d <= daysInMonth; d++) {
                var key = new Date().getFullYear() + '-' + (m < 10 ? '0' + m : m) + '-' + (d < 10 ? '0' + d : d);
                var count = heatmapData[key] || 0;
                var level = getHeatLevel(count, maxCount);
                var dayEl = el('div', {
                    key: 'day-' + m + '-' + d,
                    className: 'crontab-calendar-day cal-heat-' + level,
                    title: d + ' ' + monthNames[m - 1] + ': ' + count + ' ejecuciones'
                }, String(d));
                dayCells.push(dayEl);
            }

            months.push(el('div', { key: 'month-' + m, className: 'crontab-calendar-month' },
                el('div', { className: 'crontab-calendar-month-header' }, monthNames[m - 1]),
                el('div', { className: 'crontab-calendar-days' }, dayCells)
            ));
        }

        var legendColors = [
            { level: 0, color: '#ebedf0' },
            { level: 2, color: '#c6e48b' },
            { level: 4, color: '#239a3b' },
            { level: 6, color: '#b81414' },
            { level: 7, color: '#4a0000' }
        ];

        var legendSwatches = legendColors.map(function (lc) {
            return el('span', {
                key: 'legend-' + lc.level,
                className: 'legend-swatch',
                style: { backgroundColor: lc.color }
            });
        });

        return el('div', { className: 'crontab-calendar' },
            el('h4', {}, 'Calendario termico anual (' + new Date().getFullYear() + ')'),
            el('div', { className: 'crontab-calendar-grid' }, months),
            el('div', { className: 'crontab-calendar-legend' },
                el('span', { className: 'legend-label' }, 'Menos'),
                legendSwatches,
                el('span', { className: 'legend-label' }, 'Mas')
            )
        );
    }

    function GanttChart(props) {
        var parsed = props.parsed;
        var nextExecutions = props.nextExecutions;
        var showSeconds = props.showSeconds;
        var _useStateGantt = useState('24h');
        var zoom = _useStateGantt[0];
        var setZoom = _useStateGantt[1];

        if (!parsed || !parsed.valid || parsed.isReboot || !nextExecutions || nextExecutions.length === 0) {
            return null;
        }

        var execs = nextExecutions.slice(0, nextExecutions.length);
        var startTime = execs[0].getTime();
        var endTime = execs[execs.length - 1].getTime();
        var totalDuration = endTime - startTime;

        useEffect(function () {
            var canvas = document.getElementById('crontab-gantt-canvas-el');
            if (!canvas) return;
            var ctx = canvas.getContext('2d');
            var width = canvas.width;
            var height = canvas.height;
            var padding = { top: 20, right: 20, bottom: 40, left: 60 };
            var chartWidth = width - padding.left - padding.right;
            var chartHeight = height - padding.top - padding.bottom;
            var barHeight = Math.min(20, chartHeight / execs.length);

            ctx.clearRect(0, 0, width, height);

            ctx.fillStyle = '#f8f9fa';
            ctx.fillRect(0, 0, width, height);

            ctx.fillStyle = '#333';
            ctx.font = 'bold 14px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Proximas ' + execs.length + ' ejecuciones (' + zoom + ')', width / 2, 14);

            for (var i = 0; i < execs.length; i++) {
                var t = execs[i].getTime();
                var x = padding.left + ((t - startTime) / totalDuration) * chartWidth;
                var y = padding.top + i * (barHeight + 2);
                var w = Math.max(4, (chartWidth / execs.length) * 0.8);

                var intensity = 0.3 + (i / execs.length) * 0.7;
                ctx.fillStyle = 'rgba(33, 150, 243, ' + intensity + ')';
                ctx.fillRect(x, y, Math.min(w, chartWidth - x + padding.left), barHeight);

                ctx.fillStyle = '#555';
                ctx.font = '10px sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(formatTime(execs[i], showSeconds), padding.left - 5, y + barHeight / 2 + 3);
            }

            ctx.fillStyle = '#888';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'center';
            var labelCount = Math.min(10, execs.length);
            for (var i = 0; i <= labelCount; i++) {
                var t = startTime + (totalDuration / labelCount) * i;
                var x = padding.left + (i / labelCount) * chartWidth;
                var d = new Date(t);
                ctx.fillText(formatTime(d, showSeconds), x, height - 10);
            }
        });

        var zoomButtons = ['1h', '4h', '24h', '7d'].map(function (z) {
            return el('button', {
                key: z,
                className: 'crontab-btn crontab-btn-sm' + (zoom === z ? ' is-active' : ''),
                onClick: function () { setZoom(z); }
            }, z);
        });

        return el('div', { className: 'crontab-gantt' },
            el('h4', {}, 'Diagrama de Gantt'),
            el('div', { className: 'crontab-gantt-zoom' }, zoomButtons),
            el('canvas', {
                id: 'crontab-gantt-canvas-el',
                className: 'crontab-gantt-canvas',
                width: 600,
                height: Math.max(100, execs.length * 22 + 60),
                style: { width: '100%', height: 'auto', maxHeight: '400px', border: '1px solid #ddd', borderRadius: '4px' }
            })
        );
    }


    /**
     * Componente de export.
     */
    function ExportPanel(props) {
        var expression = props.expression;
        var parsed = props.parsed;
        var copied = props.copied;
        var onCopy = props.onCopy;

        if (!expression || !parsed || !parsed.valid) {
            return el('p', { style: { color: '#888', fontStyle: 'italic' } }, 'Introduce una expresion cron valida para ver las opciones de exportacion.');
        }

        var tabs = [
            { name: 'github', title: 'GitHub', content: toGithubAction(expression) },
            { name: 'ansible', title: 'Ansible', content: toAnsibleCron(expression) },
            { name: 'cloudwatch', title: 'CloudWatch', content: toCloudWatchExpression(expression) },
            { name: 'sql', title: 'SQL', content: toSqlQuery(expression, parsed) },
            { name: 'resumen', title: 'Resumen', content: toNaturalSummary(expression, parsed) },
            { name: 'systemd', title: 'systemd', content: toSystemdTimer(expression) },
            { name: 'k8s', title: 'Kubernetes', content: toKubernetesYaml(expression) },
            { name: 'aws', title: 'AWS', content: toEventBridgeRule(expression) },
            { name: 'ical', title: 'iCal', content: toIcal(expression, parsed, 50) },
            { name: 'text', title: 'Texto', content: toPlainText(expression, parsed) }
        ];

        var tabPanel = el(TabPanel, {
            className: 'crontab-export-tabs',
            activeClass: 'is-active',
            tabs: tabs.map(function (t) {
                return { name: t.name, title: t.title, className: 'crontab-export-tab' };
            }),
            onSelect: function () {}
        }, function (tab) {
            var content = '';
            for (var i = 0; i < tabs.length; i++) {
                if (tabs[i].name === tab.name) {
                    content = tabs[i].content;
                    break;
                }
            }
            return el('div', { className: 'crontab-export-content' },
                el('pre', {},
                    el('code', {}, content)
                ),
                el('button', {
                    className: 'crontab-btn crontab-btn-sm',
                    onClick: function () { onCopy(tab.name, content); }
                }, copied === tab.name ? 'Copiado!' : 'Copiar')
            );
        });

        return el('div', { className: 'crontab-export' },
            el('h4', {}, 'Exportar'),
            tabPanel
        );
    }

    function ShareImage(props) {
        var expression = props.expression;
        var parsed = props.parsed;
        var showSeconds = props.showSeconds;
        var _useStateShare = useState(false);
        var showShare = _useStateShare[0];
        var setShowShare = _useStateShare[1];
        var _useStateShare2 = useState(null);
        var imageData = _useStateShare2[0];
        var setImageData = _useStateShare2[1];

        if (!expression || !parsed || !parsed.valid) {
            return null;
        }

        function generateImage() {
            var canvas = document.createElement('canvas');
            canvas.width = 800;
            canvas.height = 400;
            var ctx = canvas.getContext('2d');

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, 800, 400);

            ctx.fillStyle = '#1a1a2e';
            ctx.fillRect(0, 0, 800, 100);

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 28px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Crontab Helper', 400, 45);

            ctx.font = 'bold 20px monospace';
            ctx.fillStyle = '#4fc3f7';
            ctx.fillText(expression, 400, 80);

            if (parsed.description) {
                ctx.fillStyle = '#333333';
                ctx.font = '16px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(parsed.description, 400, 140);
            }

            if (parsed.nextExecutions && parsed.nextExecutions.length > 0) {
                ctx.fillStyle = '#1a1a2e';
                ctx.font = 'bold 14px sans-serif';
                ctx.textAlign = 'left';
                ctx.fillText('Proximas ejecuciones:', 50, 190);

                ctx.font = '13px sans-serif';
                for (var i = 0; i < Math.min(parsed.nextExecutions.length, 3); i++) {
                    var d = parsed.nextExecutions[i];
                    var y = 215 + i * 25;
                    var dayNames = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
                    ctx.fillStyle = '#333333';
                    ctx.textAlign = 'left';
                    ctx.fillText(String(i + 1) + '. ' + d.toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' }) + ' ' + formatTime(d, showSeconds) + ' (' + dayNames[d.getDay()] + ')', 50, y);
                }
            }

            ctx.fillStyle = '#1a1a2e';
            ctx.fillRect(0, 360, 800, 40);
            ctx.fillStyle = '#ffffff';
            ctx.font = '12px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('atareao.es - Crontab Helper', 400, 385);

            setImageData(canvas.toDataURL('image/png'));
            setShowShare(true);
        }

        function downloadImage() {
            if (!imageData) return;
            var link = document.createElement('a');
            link.download = 'crontab-' + expression.replace(/[\/\s*]/g, '_') + '.png';
            link.href = imageData;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        var children = [];

        if (!showShare) {
            children.push(el('button', {
                key: 'share-btn',
                className: 'crontab-btn',
                onClick: generateImage
            }, 'Compartir como imagen'));
        } else {
            children.push(el('div', { key: 'share-preview', style: { marginTop: '0.5rem' } },
                el('img', {
                    src: imageData,
                    style: { width: '100%', maxWidth: '400px', border: '1px solid #ddd', borderRadius: '4px', display: 'block', marginBottom: '0.5rem' }
                }),
                el('button', {
                    className: 'crontab-btn',
                    onClick: downloadImage
                }, 'Descargar PNG')
            ));
        }

        return el('div', { className: 'crontab-share' }, children);
    }


    /**
     * Componente de riesgos.
     */
    function RiskAnalysis(props) {
        var parsed = props.parsed;
        if (!parsed || !parsed.valid) return null;

        var risks = analyzeRisks(parsed);
        if (risks.length === 0) return null;

        var riskItems = risks.map(function (risk, idx) {
            var icon = risk.type === 'error' ? 'X' : risk.type === 'warning' ? '!' : 'i';
            return el('div', {
                key: 'risk-' + idx,
                className: 'crontab-risk-item crontab-risk-' + risk.type
            },
                el('strong', { style: { marginRight: '0.35rem' } }, icon),
                el('span', {}, risk.text)
            );
        });

        return el('div', { className: 'crontab-risks' },
            el('h4', {}, 'Analisis de riesgos'),
            riskItems
        );
    }

    function WhatIfSimulator(props) {
        var expression = props.expression;
        var parsed = props.parsed;
        var onApply = props.onApply;
        var _useStateWhatIf = useState(expression);
        var modifiedExpr = _useStateWhatIf[0];
        var setModifiedExpr = _useStateWhatIf[1];
        var _useStateWhatIf2 = useState(null);
        var modifiedParsed = _useStateWhatIf2[0];
        var setModifiedParsed = _useStateWhatIf2[1];

        if (!parsed || !parsed.valid || parsed.isReboot) {
            return null;
        }

        function analyzeModified() {
            var result = parseCronExpression(modifiedExpr);
            setModifiedParsed(result);
        }

        function handleSliderChange(e) {
            var tokens = modifiedExpr.trim().split(/\s+/);
            if (tokens.length >= 5) {
                tokens[0] = '*/' + e.target.value;
                var newExpr = tokens.join(' ');
                setModifiedExpr(newExpr);
                var result = parseCronExpression(newExpr);
                setModifiedParsed(result);
            }
        }

        var originalStats = null;
        var modifiedStats = null;

        if (parsed && parsed.valid && parsed.fields) {
            var origPerDay = (parsed.fields.minute ? parsed.fields.minute.length : 60) * (parsed.fields.hour ? parsed.fields.hour.length : 24);
            if (origPerDay === 0) origPerDay = 60 * 24;
            originalStats = {
                perDay: origPerDay,
                perWeek: origPerDay * 7,
                perMonth: origPerDay * 30
            };
        }

        if (modifiedParsed && modifiedParsed.valid && modifiedParsed.fields) {
            var modPerDay = (modifiedParsed.fields.minute ? modifiedParsed.fields.minute.length : 60) * (modifiedParsed.fields.hour ? modifiedParsed.fields.hour.length : 24);
            if (modPerDay === 0) modPerDay = 60 * 24;
            modifiedStats = {
                perDay: modPerDay,
                perWeek: modPerDay * 7,
                perMonth: modPerDay * 30
            };
        }

        var statNames = ['perDay', 'perWeek', 'perMonth'];
        var statLabels = { perDay: '/dia', perWeek: '/semana', perMonth: '/mes' };

        var comparison = statNames.map(function (key) {
            var orig = originalStats ? originalStats[key] : 0;
            var mod = modifiedStats ? modifiedStats[key] : 0;
            var diff = mod - orig;
            var diffClass = diff > 0 ? 'crontab-risk-error' : (diff < 0 ? 'crontab-risk-success' : '');
            return el('div', { key: key, className: 'crontab-whatif-stat' },
                el('div', { className: 'crontab-whatif-label' }, statLabels[key]),
                el('div', { className: 'crontab-whatif-before' },
                    el('span', { className: 'crontab-whatif-value' }, String(orig)),
                    el('span', { className: 'crontab-whatif-desc' }, 'Antes')
                ),
                el('div', { className: 'crontab-whatif-after ' + diffClass },
                    el('span', { className: 'crontab-whatif-value' }, String(mod)),
                    el('span', { className: 'crontab-whatif-desc' }, 'Despues')
                ),
                el('div', { className: 'crontab-whatif-arrow' }, diff > 0 ? '+' + diff : String(diff))
            );
        });

        var sliderOptions = [1, 2, 5, 10, 15, 30, 45, 60].map(function (val) {
            return el('option', { key: val, value: val }, 'Cada ' + val + ' min');
        });

        return el('div', { className: 'crontab-whatif' },
            el('h4', {}, 'Simulador What-If'),
            el('div', { className: 'crontab-input-group', style: { marginBottom: '0.5rem' } },
                el('input', {
                    type: 'text',
                    value: modifiedExpr,
                    placeholder: '*/15 * * * *',
                    onChange: function (e) { setModifiedExpr(e.target.value); }
                }),
                el('button', {
                    className: 'crontab-btn',
                    onClick: analyzeModified
                }, 'Simular')
            ),
            el('div', { style: { marginBottom: '0.5rem' } },
                el('label', { style: { fontSize: '0.85rem', display: 'block', marginBottom: '0.25rem' } }, 'Cambiar frecuencia:'),
                el('select', {
                    value: '15',
                    onChange: handleSliderChange,
                    style: { width: '100%' }
                }, sliderOptions)
            ),
            el('div', { className: 'crontab-whatif-comparison' }, comparison),
            el('button', {
                className: 'crontab-btn',
                style: { marginTop: '0.5rem', width: '100%' },
                onClick: function () { if (onApply) onApply(modifiedExpr); }
            }, 'Aplicar cambio')
        );
    }


    /**
     * Componente de historial.
     */
    function HistoryPanel(props) {
        var onSelect = props.onSelect;
        var history = getHistory();

        if (history.length === 0) {
            return el('p', { style: { color: '#888', fontStyle: 'italic', fontSize: '0.85rem' } },
                'Aun no hay historial. Las expresiones que analices se guardaran aqui.'
            );
        }

        var items = history.slice(0, 10).map(function (item, idx) {
            return el('li', {
                key: 'hist-' + idx,
                className: 'crontab-history-item',
                onClick: function () { onSelect(item.expression); }
            },
                el('code', {}, item.expression),
                el('span', { className: 'crontab-history-desc' }, item.description || '')
            );
        });

        return el('div', { className: 'crontab-history' },
            el('h4', {}, 'Historial'),
            el('ul', { className: 'crontab-history-list' }, items)
        );
    }

    /* ====================================================================
     * BLOCK REGISTRATION
     * ==================================================================== */

    registerBlockType('atareao/crontab-helper', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var _useState = useState(attributes.expression || '');
            var expression = _useState[0];
            var setExpressionLocal = _useState[1];

            var _useState2 = useState('');
            var naturalText = _useState2[0];
            var setNaturalText = _useState2[1];

            var _useState3 = useState(null);
            var parsed = _useState3[0];
            var setParsed = _useState3[1];

            var _useState4 = useState('');
            var copied = _useState4[0];
            var setCopied = _useState4[1];

            // Leer parametros de URL al cargar
            useEffect(function () {
                var params = getUrlParams();
                if (params.cron && !attributes.expression) {
                    setExpressionLocal(params.cron);
                    setAttributes({ expression: params.cron });
                    if (params.tz) {
                        setAttributes({ timezone: params.tz });
                    }
                    analyzeExpression(params.cron);
                }
            }, []);

            function analyzeExpression(expr) {
                if (!expr || !expr.trim()) {
                    setParsed(null);
                    return;
                }
                var result = parseCronExpression(expr);
                setParsed(result);
                setAttributes({ expression: expr });

                if (result.valid) {
                    addToHistory(expr, result.description);
                    updateUrlParams(expr, attributes.timezone);
                }
            }

            function handleExpressionChange(value) {
                setExpressionLocal(value);
                updateUrlParams(value, attributes.timezone);
            }

            function handleNaturalToCron(text) {
                if (!text || !text.trim()) return;
                var cronExpr = naturalToCron(text);
                if (cronExpr) {
                    setExpressionLocal(cronExpr);
                    setAttributes({ expression: cronExpr });
                    analyzeExpression(cronExpr);
                }
            }

            function handleCopyLink() {
                var url = window.location.href.split('?')[0];
                var params = new URLSearchParams();
                if (expression) params.set('cron', expression);
                if (attributes.timezone && attributes.timezone !== 'UTC') params.set('tz', attributes.timezone);
                var fullUrl = url + '?' + params.toString();
                copyToClipboard(fullUrl).then(function () {
                    setCopied('link');
                    setTimeout(function () { setCopied(''); }, 2000);
                });
            }

            function handleCopyExpression() {
                copyToClipboard(expression).then(function () {
                    setCopied('expr');
                    setTimeout(function () { setCopied(''); }, 2000);
                });
            }

            function handleCopyExport(format, content) {
                copyToClipboard(content).then(function () {
                    setCopied(format);
                    setTimeout(function () { setCopied(''); }, 2000);
                });
            }

            function handleHistorySelect(expr) {
                setExpressionLocal(expr);
                setAttributes({ expression: expr });
                analyzeExpression(expr);
            }

            // Determinar que modo mostrar
            var mode = attributes.mode || 'expert';

            var modeTabs = [
                { name: 'expert', title: 'Experto' },
                { name: 'assistant', title: 'Asistente' },
                { name: 'natural', title: 'Natural' }
            ];

            var modePanel;
            if (mode === 'assistant') {
                modePanel = el(AssistantMode, {
                    expression: expression,
                    setExpression: handleExpressionChange,
                    onAnalyze: analyzeExpression,
                    useSeconds: attributes.useSeconds
                });
            } else if (mode === 'natural') {
                modePanel = el(NaturalMode, {
                    naturalText: naturalText,
                    setNaturalText: setNaturalText,
                    onNaturalToCron: handleNaturalToCron
                });
            } else {
                modePanel = el(ExpertMode, {
                    expression: expression,
                    setExpression: handleExpressionChange,
                    onAnalyze: analyzeExpression,
                    useSeconds: attributes.useSeconds
                });
            }

            var preview = el(CrontabPreview, {
                expression: expression,
                parsed: parsed,
                onCopyLink: handleCopyLink,
                onCopyExpression: handleCopyExpression,
                copied: copied,
                showSeconds: attributes.useSeconds
            });

            var calendar = el(CalendarHeatmap, {
                parsed: parsed,
                showCalendar: attributes.showCalendar
            });

            var exportPanel = el(ExportPanel, {
                expression: expression,
                parsed: parsed,
                copied: copied,
                onCopy: handleCopyExport
            });

            var risks = el(RiskAnalysis, {
                parsed: parsed
            });

            var ganttChart = null;
            if (parsed && parsed.valid && !parsed.isReboot) {
                ganttChart = el(GanttChart, {
                    parsed: parsed,
                    nextExecutions: parsed.nextExecutions,
                    showSeconds: attributes.useSeconds
                });
            }

            var shareImage = el(ShareImage, {
                expression: expression,
                parsed: parsed,
                showSeconds: attributes.useSeconds
            });

            return el('div', blockProps,
                // Inspector controls
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Expresion Cron', initialOpen: true },
                        el(TextControl, {
                            label: 'Expresion cron',
                            value: expression,
                            placeholder: '*/15 * * * *',
                            onChange: function (value) {
                                setExpressionLocal(value);
                                setAttributes({ expression: value });
                            },
                            onBlur: function () { analyzeExpression(expression); },
                            className: 'crontab-inspector-expression'
                        }),
                        el(SelectControl, {
                            label: 'Modo de entrada',
                            value: mode,
                            options: [
                                { label: 'Experto (texto libre)', value: 'expert' },
                                { label: 'Asistente (selectores)', value: 'assistant' },
                                { label: 'Lenguaje natural', value: 'natural' }
                            ],
                            onChange: function (value) { setAttributes({ mode: value }); }
                        }),
                        el(SelectControl, {
                            label: 'Zona horaria',
                            value: attributes.timezone,
                            options: [
                                { label: 'UTC', value: 'UTC' },
                                { label: 'Europe/Madrid', value: 'Europe/Madrid' },
                                { label: 'America/Mexico_City', value: 'America/Mexico_City' },
                                { label: 'America/Bogota', value: 'America/Bogota' },
                                { label: 'America/Argentina/Buenos_Aires', value: 'America/Argentina/Buenos_Aires' },
                                { label: 'America/Santiago', value: 'America/Santiago' },
                                { label: 'America/Lima', value: 'America/Lima' }
                            ],
                            onChange: function (value) { setAttributes({ timezone: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'Mostrar calendario termico',
                            checked: attributes.showCalendar,
                            onChange: function (value) { setAttributes({ showCalendar: value }); }
                        }),
                        el(ToggleControl, {
                            label: 'Incluir segundos (6 campos)',
                            help: 'Activa para expresiones de 6 campos como Java Quartz Scheduler',
                            checked: attributes.useSeconds,
                            onChange: function (value) { setAttributes({ useSeconds: value }); }
                        }),
                        el(TextControl, {
                            label: 'Numero de proximas ejecuciones',
                            type: 'number',
                            value: String(attributes.nextExecutions || 5),
                            min: 1,
                            max: 50,
                            onChange: function (value) {
                                var n = parseInt(value, 10);
                                if (!isNaN(n) && n > 0) setAttributes({ nextExecutions: n });
                            }
                        })
                    ),
                    el(PanelBody, { title: 'Historial', initialOpen: false },
                        el(HistoryPanel, { onSelect: handleHistorySelect })
                    ),
                    el(PanelBody, { title: 'Exportar', initialOpen: false },
                        exportPanel
                    ),
                    el(PanelBody, { title: 'Simulador What-If', initialOpen: false },
                        el(WhatIfSimulator, {
                            expression: expression,
                            parsed: parsed,
                            onApply: function (expr) {
                                setExpressionLocal(expr);
                                setAttributes({ expression: expr });
                                analyzeExpression(expr);
                            }
                        })
                    )
                ),
                // Editor preview
                el('div', { className: 'atareao-crontab-helper-editor' },
                    // Mode tabs
                    el('div', { className: 'crontab-mode-tabs' },
                        modeTabs.map(function (tab) {
                            return el('button', {
                                key: tab.name,
                                className: 'crontab-mode-tab' + (mode === tab.name ? ' is-active' : ''),
                                onClick: function () { setAttributes({ mode: tab.name }); }
                            }, tab.title);
                        })
                    ),
                    modePanel,
                    preview,
                    risks,
                    ganttChart,
                    calendar,
                    shareImage
                )
            );
        },

        save: function () {
            return null;
        }
    });
})(window.wp);