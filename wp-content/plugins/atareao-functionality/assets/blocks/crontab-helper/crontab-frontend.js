/**
 * Crontab Helper - Frontend
 *
 * Hidrata el bloque Crontab Helper en el frontend publico.
 * Proporciona la misma funcionalidad interactiva que el editor:
 * parseo, constructor visual, calendario termico, export, etc.
 */
(function () {
    'use strict';

    /* ====================================================================
     * CRON PARSER ENGINE (same as editor)
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
    var FIELD_LABELS = { second: 'segundos', minute: 'minuto', hour: 'hora', dayOfMonth: 'dia del mes', month: 'mes', dayOfWeek: 'dia de la semana' };

    function resolveAlias(expr) {
        expr = expr.trim().toLowerCase();
        if (ALIASES[expr]) return { original: expr, expanded: ALIASES[expr] };
        return { original: expr, expanded: expr };
    }

    function normalizeNames(field) {
        var upper = field.toUpperCase();
        if (DAY_NAMES[upper] !== undefined) return String(DAY_NAMES[upper]);
        if (MONTH_NAMES[upper] !== undefined) return String(MONTH_NAMES[upper]);
        return field;
    }

    function parseCronField(field, range) {
        var min = range.min, max = range.max;
        field = field.split(',').map(function (part) {
            return part.split('-').map(normalizeNames).join('-');
        }).join(',');
        if (field === '*') {
            var all = [];
            for (var i = min; i <= max; i++) all.push(i);
            return all;
        }
        if (/^\d*L$/i.test(field)) return ['L'];
        if (/^\d+W$/i.test(field)) return ['W' + parseInt(field, 10)];
        if (/^\d+#\d+$/.test(field)) return [field];

        var values = [];
        var parts = field.split(',');
        for (var p = 0; p < parts.length; p++) {
            var part = parts[p].trim();
            var step = 1;
            var rangePart = part;
            var slashIdx = part.indexOf('/');
            if (slashIdx !== -1) {
                step = parseInt(part.slice(slashIdx + 1), 10);
                if (isNaN(step) || step < 1) step = 1;
                rangePart = part.slice(0, slashIdx);
            }
            if (rangePart === '*') {
                for (var i = min; i <= max; i += step) values.push(i);
                continue;
            }
            var dashIdx = rangePart.indexOf('-');
            if (dashIdx !== -1) {
                var start = parseInt(rangePart.slice(0, dashIdx), 10);
                var end = parseInt(rangePart.slice(dashIdx + 1), 10);
                if (isNaN(start)) start = min;
                if (isNaN(end)) end = max;
                for (var i = start; i <= end; i += step) values.push(i);
                continue;
            }
            var val = parseInt(rangePart, 10);
            if (!isNaN(val)) values.push(val);
        }
        values = values.filter(function (v, i) { return values.indexOf(v) === i; }).sort(function (a, b) { return a - b; });
        return values;
    }

    function parseCronExpression(expr) {
        if (!expr || !expr.trim()) return { valid: false, error: 'Introduce una expresion cron' };
        var resolved = resolveAlias(expr);
        var cronStr = resolved.expanded;
        if (cronStr === '@reboot') return { valid: true, isReboot: true, fields: null, rawFields: ['@reboot'], description: 'Se ejecuta al arrancar el sistema', nextExecutions: [] };

        var tokens = cronStr.trim().split(/\s+/);
        var isSixField = tokens.length === 6;
        var fieldNames = isSixField ? FIELD_NAMES_6 : FIELD_NAMES;
        if (tokens.length < 5 || tokens.length > 6) return { valid: false, error: 'La expresion debe tener 5 campos (o 6 con segundos). Tiene ' + tokens.length + '.' };

        var fields = {}, rawFields = {}, error = null;
        for (var i = 0; i < fieldNames.length; i++) {
            var name = fieldNames[i];
            var token = tokens[i];
            var range = FIELD_RANGES[name];
            rawFields[name] = token;
            try { fields[name] = parseCronField(token, range); }
            catch (e) { error = 'Error en campo "' + FIELD_LABELS[name] + '": ' + token; fields[name] = []; }
        }
        if (error) return { valid: false, error: error, fields: fields, rawFields: rawFields };
        for (var i = 0; i < fieldNames.length; i++) {
            if (fields[fieldNames[i]].length === 0) return { valid: false, error: 'Campo "' + FIELD_LABELS[fieldNames[i]] + '" no tiene valores validos', fields: fields, rawFields: rawFields };
        }
        var description = describeCron(fields, isSixField);
        var nextExecutions = getNextExecutions(fields, isSixField, 10);
        return { valid: true, fields: fields, rawFields: rawFields, isSixField: isSixField, description: description, nextExecutions: nextExecutions };
    }

    function describeCron(fields, isSixField) {
        if (!fields) return '';
        var min = fields.minute || [], hour = fields.hour || [], dom = fields.dayOfMonth || [], mon = fields.month || [], dow = fields.dayOfWeek || [], sec = fields.second || [];
        var parts = [];
        if (isSixField && sec.length > 0) {
            if (sec.length === 60) parts.push('cada segundo');
            else if (sec.length === 1) parts.push('en el segundo ' + sec[0]);
            else parts.push('en los segundos ' + describeList(sec));
        }
        if (min.length === 60) parts.push('cada minuto');
        else if (min.length === 1) parts.push('en el minuto ' + min[0]);
        else parts.push('cada ' + describeList(min) + ' minutos');
        if (hour.length === 24) {}
        else if (hour.length === 1) {}
        else parts.push('a las horas ' + describeList(hour));
        if (dom.length === 31) {}
        else if (dom.length === 1 && dom[0] === 'L') parts.push('el ultimo dia del mes');
        else if (dom.length === 1 && typeof dom[0] === 'string' && dom[0].charAt(0) === 'W') parts.push('el dia laborable mas cercano al ' + parseInt(dom[0], 10));
        else parts.push('el dia ' + describeList(dom) + ' del mes');
        if (mon.length !== 12) {
            var monNames = mon.map(function (m) { var months = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']; return months[m] || m; });
            parts.push('durante ' + describeList(monNames));
        }
        if (dow.length === 8 || (dow.length === 7 && dow.indexOf(0) !== -1 && dow.indexOf(7) !== -1)) {}
        else {
            var dayNames = dow.map(function (d) { var days = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo']; return days[d] || d; });
            if (dow.length === 5 && dow.indexOf(1) !== -1 && dow.indexOf(2) !== -1 && dow.indexOf(3) !== -1 && dow.indexOf(4) !== -1 && dow.indexOf(5) !== -1) parts.push('de lunes a viernes');
            else if (dow.length === 2 && dow.indexOf(0) !== -1 && dow.indexOf(6) !== -1) parts.push('los fines de semana');
            else parts.push('los ' + describeList(dayNames));
        }
        if (parts.length === 0) return 'Cada minuto, todos los dias';
        var result = parts.join(', ');
        return result.charAt(0).toUpperCase() + result.slice(1);
    }

    function describeList(values) {
        if (values.length === 1) return String(values[0]);
        if (values.length > 2) {
            var step = values[1] - values[0], isRegular = true;
            for (var i = 2; i < values.length; i++) { if (values[i] - values[i - 1] !== step) { isRegular = false; break; } }
            if (isRegular && step > 0) return values[0] + '-' + values[values.length - 1] + ' (cada ' + step + ')';
        }
        if (values[values.length - 1] - values[0] === values.length - 1) return values[0] + '-' + values[values.length - 1];
        return values.slice(0, -1).join(', ') + ' y ' + values[values.length - 1];
    }

    function getLastDayOfMonth(year, month) { return new Date(year, month, 0).getDate(); }

    function dayMatches(date, dom, dow) {
        var dayOfMonth = date.getDate(), dayOfWeek = date.getDay(), year = date.getFullYear(), month = date.getMonth() + 1;
        var cronDOW = dayOfWeek === 0 ? 0 : dayOfWeek;
        var domMatch = false, dowMatch = false;
        if (dom) {
            if (dom.indexOf(dayOfMonth) !== -1) domMatch = true;
            for (var i = 0; i < dom.length; i++) {
                if (dom[i] === 'L' && dayOfMonth === getLastDayOfMonth(year, month)) domMatch = true;
                if (typeof dom[i] === 'string' && dom[i].charAt(0) === 'W') { var nearestWeekday = getNearestWeekday(year, month, parseInt(dom[i], 10)); if (dayOfMonth === nearestWeekday) domMatch = true; }
            }
        }
        if (dow) {
            if (dow.indexOf(cronDOW) !== -1 || (dow.indexOf(7) !== -1 && cronDOW === 0)) dowMatch = true;
            for (var i = 0; i < dow.length; i++) {
                if (typeof dow[i] === 'string' && dow[i].indexOf('#') !== -1) {
                    var parts = dow[i].split('#'), targetDOW = parseInt(parts[0], 10), nth = parseInt(parts[1], 10), count = 0;
                    for (var d = 1; d <= dayOfMonth; d++) { var testDate = new Date(year, month - 1, d); if (testDate.getDay() === targetDOW) { count++; if (d === dayOfMonth && count === nth) dowMatch = true; } }
                }
            }
        }
        if (!dom || dom.length === 0 || dom.length === 31) domMatch = true;
        if (!dow || dow.length === 0 || dow.length === 8 || (dow.length === 7 && dow.indexOf(0) !== -1 && dow.indexOf(7) !== -1)) dowMatch = true;
        var bothSpecified = dom.length > 0 && dom.length < 31 && dow.length > 0 && dow.length < 8;
        return bothSpecified ? (domMatch || dowMatch) : domMatch && dowMatch;
    }

    function getNearestWeekday(year, month, day) {
        var date = new Date(year, month - 1, day), dow = date.getDay();
        if (dow === 0) return day + 1;
        if (dow === 6) return day - 1;
        return day;
    }

    function getNextExecutions(fields, isSixField, count) {
        if (!fields) return [];
        var min = fields.minute || [], hour = fields.hour || [], dom = fields.dayOfMonth || [], mon = fields.month || [], dow = fields.dayOfWeek || [];
        var now = new Date(), results = [], maxIter = 525600, iter = 0;
        var cursor = new Date(now);
        cursor.setSeconds(0); cursor.setMilliseconds(0); cursor.setMinutes(cursor.getMinutes() + 1);
        while (results.length < count && iter < maxIter) {
            var year = cursor.getFullYear(), month = cursor.getMonth() + 1;
            if (mon.length > 0 && mon.length < 12) { if (mon.indexOf(month) === -1) { cursor.setMonth(cursor.getMonth() + 1); cursor.setDate(1); cursor.setHours(0); cursor.setMinutes(0); iter++; continue; } }
            if (!dayMatches(cursor, dom, dow)) { cursor.setDate(cursor.getDate() + 1); cursor.setHours(0); cursor.setMinutes(0); iter++; continue; }
            if (hour.length > 0 && hour.length < 24) { if (hour.indexOf(cursor.getHours()) === -1) { cursor.setHours(cursor.getHours() + 1); cursor.setMinutes(0); iter++; continue; } }
            if (min.length > 0 && min.length < 60) { if (min.indexOf(cursor.getMinutes()) === -1) { cursor.setMinutes(cursor.getMinutes() + 1); iter++; continue; } }
            results.push(new Date(cursor));
            cursor.setMinutes(cursor.getMinutes() + 1);
            iter++;
        }
        return results;
    }

    function naturalToCron(text) {
        if (!text || !text.trim()) return null;
        var lower = text.toLowerCase().trim();
        if (lower === '@hourly' || lower === 'cada hora') return '0 * * * *';
        if (lower === '@daily' || lower === 'cada dia' || lower === 'todos los dias') return '0 0 * * *';
        if (lower === '@weekly' || lower === 'cada semana') return '0 0 * * 0';
        if (lower === '@monthly' || lower === 'cada mes') return '0 0 1 * *';
        if (lower === '@yearly' || lower === 'cada año' || lower === 'cada ano') return '0 0 1 1 *';
        var minute = '*', hour = '*', dom = '*', month = '*', dow = '*';
        var match = lower.match(/cada (\d+) minutos?/);
        if (match) minute = '*/' + match[1];
        match = lower.match(/cada (\d+) horas?/);
        if (match) hour = '*/' + match[1];
        if (lower.indexOf('cada minuto') !== -1 && !lower.match(/cada \d+ minutos?/)) minute = '*';
        if (lower.indexOf('cada hora') !== -1 && !lower.match(/cada \d+ horas?/)) hour = '*';
        match = lower.match(/a las (\d{1,2}):?(\d{2})?/);
        if (match) { hour = parseInt(match[1], 10).toString(); if (match[2]) minute = parseInt(match[2], 10).toString(); }
        if (lower.indexOf('lunes') !== -1 && lower.indexOf('viernes') !== -1) dow = '1-5';
        if (lower.indexOf('fin de semana') !== -1 || lower.indexOf('finde') !== -1) dow = '0,6';
        var dayMap = { lunes: 1, martes: 2, miercoles: 3, jueves: 4, viernes: 5, sabado: 6, domingo: 0 };
        for (var day in dayMap) { if (lower.indexOf(day) !== -1 && dow === '*') dow = String(dayMap[day]); }
        match = lower.match(/el (dia )?(\d{1,2})( del mes)?/);
        if (match) dom = match[2];
        if (lower.indexOf('todos los dias') !== -1 || lower.indexOf('cada dia') !== -1) { dom = '*'; dow = '*'; }
        if (lower.indexOf('arranque') !== -1 || lower.indexOf('reinicio') !== -1 || lower === '@reboot') return '@reboot';
        return minute + ' ' + hour + ' ' + dom + ' ' + month + ' ' + dow;
    }

    function analyzeRisks(parsed) {
        var risks = [];
        if (!parsed || !parsed.valid) return risks;
        if (parsed.isReboot) { risks.push({ type: 'info', text: 'Se ejecuta al arrancar. Asegurate de que el sistema no arranque multiples veces en un periodo corto.' }); return risks; }
        var fields = parsed.fields;
        var raw = parsed.rawFields || {};
        if (!fields) return risks;
        var min = fields.minute || [], hour = fields.hour || [], dom = fields.dayOfMonth || [], dow = fields.dayOfWeek || [];
        if (min.length === 60 && hour.length === 24) risks.push({ type: 'error', text: 'Se ejecuta CADA MINUTO. Revisa si es realmente necesario para evitar carga excesiva en el servidor.' });
        if (raw.minute === '*/1') risks.push({ type: 'warning', text: '*/1 es equivalente a *. Usa "*" directamente.' });
        if (hour.length === 1 && hour[0] >= 2 && hour[0] <= 3) risks.push({ type: 'warning', text: 'Posible conflicto con cambio de hora (DST). Esta ejecucion podria saltarse o duplicarse en marzo/octubre.' });
        if (dom.indexOf(31) !== -1) risks.push({ type: 'warning', text: 'No todos los meses tienen 31 dias. Febrero, abril, junio, septiembre y noviembre no ejecutaran esta tarea.' });
        if (dom.indexOf(29) !== -1 && fields.month && fields.month.indexOf(2) !== -1) risks.push({ type: 'warning', text: 'El 29 de febrero solo existe en años bisiestos. Esta tarea se ejecutara aproximadamente cada 4 años.' });
        var domSpecified = dom.length > 0 && dom.length < 31, dowSpecified = dow.length > 0 && dow.length < 8;
        if (domSpecified && dowSpecified) risks.push({ type: 'info', text: 'Tanto dia del mes como dia de la semana estan especificados. La tarea se ejecutara cuando UNO DE LOS DOS coincida (comportamiento OR).' });
        if (hour.length === 1 && hour[0] === 0 && min.length === 1 && min[0] === 0) risks.push({ type: 'info', text: 'Se ejecuta a medianoche. Verifica que no coincida con tareas de backup o rotacion de logs.' });

        // Cron golf detection
        if (min.length > 1 && min.length < 60) {
            var step = min[1] - min[0];
            var isRegularStep = true;
            for (var i = 2; i < min.length; i++) {
                if (min[i] - min[i - 1] !== step) { isRegularStep = false; break; }
            }
            if (isRegularStep && step > 1) {
                risks.push({ type: 'info', text: 'Los minutos siguen un patron regular cada ' + step + '. Puedes simplificar con */' + step + ' en lugar de lista explicita.' });
            }
        }

        // Thundering herd: ejecucion en punto (:00)
        if (min.length === 1 && min[0] === 0 && hour.length < 24) {
            risks.push({ type: 'warning', text: 'Esta tarea se ejecuta en punto (:00). Considera anadir un retardo aleatorio de minutos para evitar el efecto "thundering herd" si multiples tareas comienzan a la vez.' });
        }

        // Alta frecuencia
        var executionsPerDay = 0;
        for (var h = 0; h < 24; h++) {
            if (hour.length < 24 && hour.indexOf(h) === -1) continue;
            for (var m = 0; m < 60; m++) {
                if (min.length < 60 && min.indexOf(m) === -1) continue;
                executionsPerDay++;
            }
        }
        if (executionsPerDay > 144) {
            risks.push({ type: 'warning', text: 'Frecuencia alta: ' + executionsPerDay + ' ejecuciones al dia. Revisa si es necesario o puedes reducir la frecuencia.' });
        }

        // Weekend-only y workday-only
        var isWorkday = false, isWeekend = false;
        if (dowSpecified) {
            var hasWeekend = false, hasWorkday = false;
            for (var i = 0; i < dow.length; i++) {
                var d = dow[i];
                if (d === 0 || d === 6 || d === 7) hasWeekend = true;
                else hasWorkday = true;
            }
            if (hasWeekend && !hasWorkday) {
                risks.push({ type: 'info', text: 'Solo se ejecuta en fin de semana. Verifica que sea el comportamiento deseado.' });
            }
            if (hasWorkday && !hasWeekend) {
                risks.push({ type: 'info', text: 'Solo se ejecuta en dias laborables. Verifica que sea el comportamiento deseado.' });
            }
        }

        return risks;
    }

    function getHistory() { try { return JSON.parse(localStorage.getItem('atareao_crontab_history') || '[]'); } catch (e) { return []; } }
    function addToHistory(expression, description) { try { var h = getHistory().filter(function (i) { return i.expression !== expression; }); h.unshift({ expression: expression, description: description, timestamp: Date.now() }); if (h.length > 20) h = h.slice(0, 20); localStorage.setItem('atareao_crontab_history', JSON.stringify(h)); return h; } catch (e) { return []; } }
    function getUrlParams() { var params = {}, query = window.location.search.substring(1); if (!query) return params; query.split('&').forEach(function (pair) { var p = pair.split('='); params[decodeURIComponent(p[0])] = decodeURIComponent(p[1] || ''); }); return params; }
    function updateUrlParams(expression, timezone) { try { var url = new URL(window.location.href); if (expression) url.searchParams.set('cron', expression); else url.searchParams.delete('cron'); if (timezone && timezone !== 'UTC') url.searchParams.set('tz', timezone); else url.searchParams.delete('tz'); window.history.replaceState({}, '', url.toString()); } catch (e) {} }
    function copyToClipboard(text) { if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text); return new Promise(function (resolve) { var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); resolve(); }); }

    function generateHeatmapData(fields, isSixField, year) {
        year = year || new Date().getFullYear();
        var data = {}, min = fields.minute || [], hour = fields.hour || [], dom = fields.dayOfMonth || [], dow = fields.dayOfWeek || [];
        for (var m = 1; m <= 12; m++) {
            var daysInMonth = new Date(year, m, 0).getDate();
            for (var d = 1; d <= daysInMonth; d++) {
                var date = new Date(year, m - 1, d), key = year + '-' + (m < 10 ? '0' + m : m) + '-' + (d < 10 ? '0' + d : d), count = 0;
                if (dayMatches(date, dom, dow)) {
                    for (var h = 0; h < 24; h++) { if (hour.length < 24 && hour.indexOf(h) === -1) continue; for (var minVal = 0; minVal < 60; minVal++) { if (min.length < 60 && min.indexOf(minVal) === -1) continue; count++; } }
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
        if (ratio <= 0.05) return 1; if (ratio <= 0.15) return 2; if (ratio <= 0.30) return 3;
        if (ratio <= 0.50) return 4; if (ratio <= 0.70) return 5; if (ratio <= 0.85) return 6;
        return 7;
    }

    function toSystemdTimer(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression), cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '[Unit]\nDescription=My cron task\n\n[Service]\nType=oneshot\nExecStart=/usr/bin/true\n\n[Install]\nWantedBy=multi-user.target';
        var tokens = cronStr.trim().split(/\s+/);
        if (tokens.length < 5) return '';
        if (tokens.join(' ') === '0 0 * * *') return '[Timer]\nOnCalendar=daily\nPersistent=true';
        if (tokens.join(' ') === '0 * * * *') return '[Timer]\nOnCalendar=hourly\nPersistent=true';
        return '[Timer]\nOnCalendar=*-*-* ' + tokens[1] + ':' + tokens[0] + ':00\nPersistent=true';
    }

    function toKubernetesYaml(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression), cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '';
        return 'apiVersion: batch/v1\nkind: CronJob\nmetadata:\n  name: my-cronjob\nspec:\n  schedule: "' + cronStr + '"\n  jobTemplate:\n    spec:\n      template:\n        spec:\n          containers:\n          - name: my-container\n            image: busybox\n            command:\n            - /bin/sh\n            - -c\n            - "echo Hello"\n          restartPolicy: OnFailure';
    }

    function toEventBridgeRule(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression), cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '';
        var tokens = cronStr.trim().split(/\s+/);
        if (tokens.length < 5) return '';
        return 'cron(' + tokens[0] + ' ' + tokens[1] + ' ' + (tokens[2] === '*' ? '?' : tokens[2]) + ' ' + tokens[3] + ' ' + (tokens[4] === '*' ? '?' : tokens[4]) + ' *)';
    }

    function toIcal(expression, parsed, count) {
        count = count || 10;
        if (!parsed || !parsed.valid || parsed.isReboot) return '';
        var nextExecs = getNextExecutions(parsed.fields, parsed.isSixField, count);
        if (nextExecs.length === 0) return '';
        var lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//atareao//Crontab Helper//ES'];
        for (var i = 0; i < nextExecs.length; i++) {
            var d = nextExecs[i];
            var dtstart = d.getUTCFullYear() + pad2(d.getUTCMonth() + 1) + pad2(d.getUTCDate()) + 'T' + pad2(d.getUTCHours()) + pad2(d.getUTCMinutes()) + '00';
            lines.push('BEGIN:VEVENT', 'DTSTART:' + dtstart, 'DURATION:PT1M', 'SUMMARY:Cron ejecucion: ' + expression, 'END:VEVENT');
        }
        lines.push('END:VCALENDAR');
        return lines.join('\r\n');
    }
    function pad2(n) { return n < 10 ? '0' + n : String(n); }

    /* ====================================================================
     * FEATURE 5: NEW EXPORT FORMATS
     * ==================================================================== */

    function toGithubAction(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression), cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '# Los Github Actions no soportan @reboot';
        return 'name: Cron job\non:\n  schedule:\n    - cron: "' + cronStr + '"\n\njobs:\n  cron:\n    runs-on: ubuntu-latest\n    steps:\n      - name: Checkout\n        uses: actions/checkout@v4\n      - name: Run task\n        run: echo "Tarea programada"';
    }

    function toAnsibleCron(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression), cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '- name: Ejecutar al arranque\n  ansible.builtin.cron:\n    name: "my task"\n    special_time: reboot\n    job: /usr/bin/true';
        var tokens = cronStr.trim().split(/\s+/);
        if (tokens.length < 5) return '';
        return '- name: Anadir tarea cron\n  ansible.builtin.cron:\n    name: "my task"\n    minute: "' + tokens[0] + '"\n    hour: "' + tokens[1] + '"\n    day: "' + tokens[2] + '"\n    month: "' + tokens[3] + '"\n    weekday: "' + tokens[4] + '"\n    job: /usr/bin/true';
    }

    function toCloudWatchExpression(expression) {
        if (!expression) return '';
        var resolved = resolveAlias(expression), cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '# CloudWatch no soporta @reboot';
        var tokens = cronStr.trim().split(/\s+/);
        if (tokens.length < 5) return '';
        var cronExpr = 'cron(' + tokens[0] + ' ' + tokens[1] + ' ' + (tokens[2] === '*' ? '?' : tokens[2]) + ' ' + tokens[3] + ' ' + (tokens[4] === '*' ? '?' : tokens[4]) + ' *)';
        return '# Expresion cron para AWS CloudWatch Events/EventBridge\n' + cronExpr + '\n\n# Como expresion rate (alternativa):\n# rate(1 hour)';
    }

    function toSqlQuery(expression, parsed) {
        if (!expression) return '';
        var resolved = resolveAlias(expression), cronStr = resolved.expanded;
        if (cronStr === '@reboot') return '-- @reboot no se puede expresar como SQL';
        var tokens = cronStr.trim().split(/\s+/);
        if (tokens.length < 5) return '';
        var desc = '';
        if (parsed && parsed.valid) {
            desc = parsed.description;
        }
        var lines = [];
        lines.push('-- Expresion cron: ' + expression);
        if (desc) lines.push('-- Descripcion: ' + desc);
        lines.push('-- Buscar tareas que coincidan con este patron:');
        lines.push('');
        lines.push('SELECT *');
        lines.push('FROM cron_schedule');
        lines.push('WHERE minute IN (' + expandFieldForSql(tokens[0], 0, 59) + ')');
        lines.push('  AND hour IN (' + expandFieldForSql(tokens[1], 0, 23) + ')');
        lines.push('  AND day_of_month IN (' + expandFieldForSql(tokens[2], 1, 31) + ')');
        lines.push('  AND month IN (' + expandFieldForSql(tokens[3], 1, 12) + ')');
        lines.push('  AND day_of_week IN (' + expandFieldForSql(tokens[4], 0, 7) + ');');
        return lines.join('\n');
    }

    function expandFieldForSql(field, min, max) {
        if (field === '*') {
            var parts = [];
            for (var i = min; i <= max; i++) parts.push(String(i));
            return parts.join(', ');
        }
        if (field.indexOf('/') !== -1) {
            var stepParts = field.split('/');
            var step = parseInt(stepParts[1], 10);
            var range = stepParts[0];
            var values = [];
            var start = min, end = max;
            if (range !== '*') {
                var dash = range.indexOf('-');
                if (dash !== -1) {
                    start = parseInt(range.slice(0, dash), 10);
                    end = parseInt(range.slice(dash + 1), 10);
                } else {
                    start = parseInt(range, 10);
                }
            }
            for (var i = start; i <= end; i += step) values.push(String(i));
            return values.join(', ');
        }
        if (field.indexOf('-') !== -1) {
            var dash = field.indexOf('-');
            var s = parseInt(field.slice(0, dash), 10);
            var e = parseInt(field.slice(dash + 1), 10);
            var vals = [];
            for (var i = s; i <= e; i++) vals.push(String(i));
            return vals.join(', ');
        }
        return field;
    }

    function toNaturalSummary(expression, parsed) {
        if (!expression) return '';
        var lines = [];
        lines.push('Resumen de la expresion cron');
        lines.push('===========================');
        lines.push('');
        lines.push('Expresion: ' + expression);
        if (!parsed || !parsed.valid) {
            lines.push('Estado: Expresion no valida');
            return lines.join('\n');
        }
        if (parsed.isReboot) {
            lines.push('Descripcion: Se ejecuta al arrancar el sistema');
            return lines.join('\n');
        }
        lines.push('Descripcion: ' + parsed.description);
        var fields = parsed.fields;
        if (!fields) return lines.join('\n');
        var min = fields.minute || [], hour = fields.hour || [], dom = fields.dayOfMonth || [], mon = fields.month || [], dow = fields.dayOfWeek || [];
        lines.push('');
        lines.push('Estadisticas:');
        var executionsPerDay = 0;
        for (var h = 0; h < 24; h++) {
            if (hour.length < 24 && hour.indexOf(h) === -1) continue;
            for (var m = 0; m < 60; m++) {
                if (min.length < 60 && min.indexOf(m) === -1) continue;
                executionsPerDay++;
            }
        }
        var executionsPerWeek = 0;
        if (dow.length > 0 && dow.length < 8) {
            executionsPerWeek = executionsPerDay * dow.length;
        } else {
            executionsPerWeek = executionsPerDay * 7;
        }
        var executionsPerMonth = executionsPerDay * 30;
        if (mon.length > 0 && mon.length < 12) {
            executionsPerMonth = executionsPerDay * 30 * (mon.length / 12);
        }
        lines.push('  - Ejecuciones por dia: ' + executionsPerDay);
        lines.push('  - Ejecuciones por semana: ~' + Math.round(executionsPerWeek));
        lines.push('  - Ejecuciones por mes: ~' + Math.round(executionsPerMonth));
        lines.push('  - Ejecuciones por año: ~' + Math.round(executionsPerMonth * 12));
        if (parsed.nextExecutions && parsed.nextExecutions.length > 0) {
            lines.push('');
            lines.push('Proximas 5 ejecuciones:');
            var dayNames = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
            for (var i = 0; i < Math.min(parsed.nextExecutions.length, 5); i++) {
                var d = parsed.nextExecutions[i];
                lines.push('  ' + (i + 1) + '. ' + d.toLocaleDateString('es-ES') + ' ' + formatTime(d) + ' (' + dayNames[d.getDay()] + ')');
            }
        }
        return lines.join('\n');
    }

    /* ====================================================================
     * DOM RENDERER
     * ==================================================================== */

    function crontabFrontend(container) {
        var expression = container.getAttribute('data-expression') || '';
        var timezone = container.getAttribute('data-timezone') || 'UTC';
        var mode = container.getAttribute('data-mode') || 'expert';
        var showCalendar = container.getAttribute('data-show-calendar') === '1';
        var nextCount = parseInt(container.getAttribute('data-next-executions'), 10) || 5;
        var useSeconds = container.getAttribute('data-use-seconds') === '1';

        function formatTime(date) {
            var opts = { hour: '2-digit', minute: '2-digit' };
            if (useSeconds) opts.second = '2-digit';
            return date.toLocaleTimeString('es-ES', opts);
        }

        var currentMode = mode;
        var currentExpression = expression;
        var currentParsed = null;
        var currentNatural = '';
        var copied = '';

        // Leer params de URL
        var params = getUrlParams();
        if (params.cron && !currentExpression) {
            currentExpression = params.cron;
            if (params.tz) timezone = params.tz;
        }

        // Analizar expresion inicial
        if (currentExpression) {
            currentParsed = parseCronExpression(currentExpression);
            if (currentParsed.valid) addToHistory(currentExpression, currentParsed.description);
        }

        function render() {
            container.innerHTML = '';
            container.className = 'atareao-crontab-helper';

            // Mode tabs
            var modeTabs = createEl('div', 'crontab-mode-tabs');
            var modes = [
                { name: 'expert', title: 'Experto' },
                { name: 'assistant', title: 'Asistente' },
                { name: 'natural', title: 'Natural' }
            ];
            modes.forEach(function (m) {
                var tab = createEl('button', 'crontab-mode-tab' + (currentMode === m.name ? ' is-active' : ''), m.title);
                tab.addEventListener('click', function () { currentMode = m.name; render(); });
                modeTabs.appendChild(tab);
            });
            container.appendChild(modeTabs);

            // Seconds toggle
            var settingsBar = createEl('div', 'crontab-settings-bar');
            var secondsLabel = createEl('label', 'crontab-seconds-toggle');
            var secondsCheckbox = document.createElement('input');
            secondsCheckbox.type = 'checkbox';
            secondsCheckbox.checked = useSeconds;
            secondsCheckbox.addEventListener('change', function () {
                useSeconds = this.checked;
                render();
            });
            var secondsText = document.createTextNode(' Incluir segundos (6 campos)');
            secondsLabel.appendChild(secondsCheckbox);
            secondsLabel.appendChild(secondsText);
            settingsBar.appendChild(secondsLabel);
            container.appendChild(settingsBar);

            // Mode panel
            var modePanel = createEl('div', 'crontab-mode-panel');
            if (currentMode === 'assistant') {
                modePanel.appendChild(renderAssistantMode());
            } else if (currentMode === 'natural') {
                modePanel.appendChild(renderNaturalMode());
            } else {
                modePanel.appendChild(renderExpertMode());
            }
            container.appendChild(modePanel);

            // Preview
            container.appendChild(renderPreview());

            // Risks
            if (currentParsed && currentParsed.valid) {
                container.appendChild(renderRisks());
            }

            // Calendar
            if (showCalendar && currentParsed && currentParsed.valid && !currentParsed.isReboot) {
                container.appendChild(renderCalendar());
            }

            // Gantt chart
            if (currentParsed && currentParsed.valid && !currentParsed.isReboot) {
                container.appendChild(renderGantt());
            }

            // Export
            if (currentParsed && currentParsed.valid) {
                container.appendChild(renderExport());
            }

            // What-If simulator
            if (currentParsed && currentParsed.valid && !currentParsed.isReboot) {
                container.appendChild(renderWhatIf());
            }

            // Share
            if (currentParsed && currentParsed.valid) {
                container.appendChild(renderShare());
            }

            // History
            container.appendChild(renderHistory());
        }

        function createEl(tag, className, text) {
            var el = document.createElement(tag);
            if (className) el.className = className;
            if (text !== undefined) el.textContent = text;
            return el;
        }

        function analyze(expr) {
            currentExpression = expr;
            currentParsed = parseCronExpression(expr);
            if (currentParsed.valid) addToHistory(expr, currentParsed.description);
            updateUrlParams(expr, timezone);
            render();
        }

        function renderExpertMode() {
            var wrapper = document.createDocumentFragment();

            var inputGroup = createEl('div', 'crontab-input-group');
            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'crontab-input';
            input.value = currentExpression;
            input.placeholder = '*/15 * * * *';
            input.addEventListener('keyup', function (e) { if (e.key === 'Enter') analyze(input.value); });
            inputGroup.appendChild(input);

            var btn = createEl('button', 'crontab-btn', 'Analizar');
            btn.addEventListener('click', function () { analyze(input.value); });
            inputGroup.appendChild(btn);
            wrapper.appendChild(inputGroup);

            // Quick buttons
            var quickBtns = [
                { label: '@hourly', value: '@hourly' }, { label: '@daily', value: '@daily' },
                { label: '@weekly', value: '@weekly' }, { label: '@monthly', value: '@monthly' },
                { label: 'Cada 5 min', value: '*/5 * * * *' }, { label: 'Cada 15 min', value: '*/15 * * * *' },
                { label: 'Cada hora', value: '0 * * * *' }, { label: '09:00 L-V', value: '0 9 * * 1-5' },
                { label: 'Medianoche', value: '0 0 * * *' }, { label: 'Cada Lunes', value: '0 0 * * 1' }
            ];
            var btnGroup = createEl('div', 'crontab-btn-group');
            quickBtns.forEach(function (b) {
                var el = createEl('button', 'crontab-btn crontab-btn-sm', b.label);
                el.addEventListener('click', function () { input.value = b.value; analyze(b.value); });
                btnGroup.appendChild(el);
            });
            wrapper.appendChild(btnGroup);

            // Field info indicator
            var fieldInfo = createEl('div', '', useSeconds ? '6 campos (segundos incluidos)' : '5 campos');
            fieldInfo.style.cssText = 'font-size:0.75rem;color:#888;margin-top:0.25rem;text-align:right';
            wrapper.appendChild(fieldInfo);

            return wrapper;
        }

        function renderAssistantMode() {
            var fieldCount = useSeconds ? 6 : 5;
            var fields = [];
            for (var i = 0; i < fieldCount; i++) fields.push('*');
            if (currentExpression && currentExpression.trim()) {
                var tokens = currentExpression.trim().split(/\s+/);
                if (tokens.length >= fieldCount) fields = tokens.slice(0, fieldCount);
            }

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

            var wrapper = createEl('div', '');
            var hint = createEl('p', '', 'Selecciona los valores para cada campo:');
            hint.style.cssText = 'font-size:0.85rem;color:#888;margin-bottom:0.5rem';
            wrapper.appendChild(hint);

            var selectors = createEl('div', 'crontab-field-selectors');
            fieldKeys.forEach(function (key, idx) {
                var selDiv = createEl('div', 'crontab-field-selector');
                var label = createEl('label', '', fieldLabels[idx]);
                selDiv.appendChild(label);
                var select = document.createElement('select');
                fieldOptions[key].forEach(function (opt) {
                    var option = document.createElement('option');
                    option.value = opt;
                    option.textContent = opt;
                    if (opt === fields[idx]) option.selected = true;
                    select.appendChild(option);
                });
                select.addEventListener('change', function () {
                    var newFields = fieldKeys.map(function (k, i) {
                        if (i === idx) return select.value;
                        var sel = selectors.querySelectorAll('select')[i];
                        return sel ? sel.value : fields[i];
                    });
                    analyze(newFields.join(' '));
                });
                selDiv.appendChild(select);
                selectors.appendChild(selDiv);
            });
            wrapper.appendChild(selectors);
            return wrapper;
        }

        function renderNaturalMode() {
            var wrapper = document.createDocumentFragment();

            var textarea = document.createElement('textarea');
            textarea.className = 'crontab-natural-textarea';
            textarea.value = currentNatural;
            textarea.placeholder = 'Escribe en espanol, por ejemplo:\n"cada 15 minutos de lunes a viernes"';
            textarea.rows = 3;
            textarea.style.cssText = 'width:100%;min-height:80px;padding:0.75rem;border:1.5px solid #d7dbe2;border-radius:8px;font-family:inherit;font-size:0.95rem;resize:vertical;box-sizing:border-box';
            wrapper.appendChild(textarea);

            var btn = createEl('button', 'crontab-btn', 'Convertir a cron');
            btn.style.marginTop = '0.5rem';
            btn.addEventListener('click', function () {
                var cronExpr = naturalToCron(textarea.value);
                if (cronExpr) { currentNatural = textarea.value; analyze(cronExpr); }
            });
            wrapper.appendChild(btn);

            var hint = createEl('p', 'crontab-hint', 'Ejemplos:');
            wrapper.appendChild(hint);

            var examples = ['cada 15 minutos', 'cada hora', 'a las 9:00 de lunes a viernes', 'todos los dias a las 23:00', 'cada mes el dia 1'];
            var btnGroup = createEl('div', 'crontab-btn-group');
            examples.forEach(function (ex) {
                var el = createEl('button', 'crontab-btn crontab-btn-sm', ex);
                el.addEventListener('click', function () { textarea.value = ex; currentNatural = ex; analyze(naturalToCron(ex)); });
                btnGroup.appendChild(el);
            });
            wrapper.appendChild(btnGroup);

            return wrapper;
        }

        function renderPreview() {
            var wrapper = createEl('div', '');

            // Expression display
            var exprDisplay = createEl('div', 'crontab-expression-display');
            var code = document.createElement('code');
            code.textContent = currentExpression || '? ? ? ? ?';
            exprDisplay.appendChild(code);

            var statusText = currentParsed && currentParsed.valid ? 'Valida' : 'Invalida';
            var statusClass = currentParsed && currentParsed.valid ? 'crontab-status-valid' : 'crontab-status-invalid';
            var status = createEl('span', 'crontab-status ' + statusClass, statusText);
            exprDisplay.appendChild(status);

            var copyBtn = createEl('button', 'crontab-btn crontab-btn-sm', copied === 'expr' ? 'Copiado!' : 'Copiar');
            copyBtn.addEventListener('click', function () {
                copyToClipboard(currentExpression).then(function () { copied = 'expr'; var b = copyBtn; b.textContent = 'Copiado!'; setTimeout(function () { b.textContent = 'Copiar'; if (copied === 'expr') copied = ''; }, 2000); });
            });
            exprDisplay.appendChild(copyBtn);
            wrapper.appendChild(exprDisplay);

            // Description
            var descClass = 'crontab-description';
            var descText = '';
            if (!currentParsed) descText = 'Introduce una expresion cron para analizarla';
            else if (currentParsed.valid) descText = currentParsed.description;
            else { descText = currentParsed.error || 'Expresion invalida'; descClass += ' crontab-error'; }
            var desc = createEl('div', descClass, descText);
            wrapper.appendChild(desc);

            // Next executions
            if (currentParsed && currentParsed.valid && !currentParsed.isReboot && currentParsed.nextExecutions.length > 0) {
                var execDiv = createEl('div', 'crontab-executions');
                var execTitle = createEl('h4', '', 'Proximas ' + Math.min(currentParsed.nextExecutions.length, nextCount) + ' ejecuciones');
                execDiv.appendChild(execTitle);

                var table = document.createElement('table');
                var thead = document.createElement('thead');
                var headRow = document.createElement('tr');
                ['#', 'Fecha', 'Hora', 'Dia'].forEach(function (h) {
                    var th = document.createElement('th');
                    th.textContent = h;
                    headRow.appendChild(th);
                });
                thead.appendChild(headRow);
                table.appendChild(thead);

                var tbody = document.createElement('tbody');
                var dayNames = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
                for (var i = 0; i < Math.min(currentParsed.nextExecutions.length, nextCount); i++) {
                    var d = currentParsed.nextExecutions[i];
                    var tr = document.createElement('tr');
                    [String(i + 1), d.toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' }), formatTime(d), dayNames[d.getDay()]].forEach(function (c) {
                        var td = document.createElement('td');
                        td.textContent = c;
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                }
                table.appendChild(tbody);
                execDiv.appendChild(table);
                wrapper.appendChild(execDiv);
            }

            // Reboot
            if (currentParsed && currentParsed.valid && currentParsed.isReboot) {
                var rebootDiv = createEl('div', 'crontab-executions');
                var rebootP = createEl('p', '', 'Esta tarea se ejecuta cada vez que arranca el sistema. No hay un horario fijo.');
                rebootP.style.cssText = 'font-style:italic;color:#888';
                rebootDiv.appendChild(rebootP);
                wrapper.appendChild(rebootDiv);
            }

            // Copy link
            var actions = createEl('div', '');
            actions.style.cssText = 'display:flex;gap:0.5rem;margin-top:0.5rem';
            var linkBtn = createEl('button', 'crontab-btn crontab-btn-secondary', copied === 'link' ? 'Enlace copiado!' : 'Copiar enlace');
            linkBtn.addEventListener('click', function () {
                var url = window.location.href.split('?')[0];
                var params = new URLSearchParams();
                if (currentExpression) params.set('cron', currentExpression);
                if (timezone && timezone !== 'UTC') params.set('tz', timezone);
                copyToClipboard(url + '?' + params.toString()).then(function () {
                    copied = 'link'; linkBtn.textContent = 'Enlace copiado!';
                    setTimeout(function () { linkBtn.textContent = 'Copiar enlace'; if (copied === 'link') copied = ''; }, 2000);
                });
            });
            actions.appendChild(linkBtn);
            wrapper.appendChild(actions);

            return wrapper;
        }

        function renderRisks() {
            var risks = analyzeRisks(currentParsed);
            if (risks.length === 0) return document.createDocumentFragment();

            var wrapper = createEl('div', 'crontab-risks');
            var title = createEl('h4', '', 'Analisis de riesgos');
            wrapper.appendChild(title);

            risks.forEach(function (risk) {
                var item = createEl('div', 'crontab-risk-item crontab-risk-' + risk.type);
                var strong = document.createElement('strong');
                strong.textContent = risk.type === 'error' ? 'X' : risk.type === 'warning' ? '!' : 'i';
                strong.style.marginRight = '0.35rem';
                item.appendChild(strong);
                var span = document.createElement('span');
                span.textContent = risk.text;
                item.appendChild(span);
                wrapper.appendChild(item);
            });

            return wrapper;
        }

        function renderCalendar() {
            if (!currentParsed || !currentParsed.valid || currentParsed.isReboot) return document.createDocumentFragment();

            var heatmapData = generateHeatmapData(currentParsed.fields, currentParsed.isSixField, new Date().getFullYear());
            var maxCount = 0;
            for (var key in heatmapData) { if (heatmapData[key] > maxCount) maxCount = heatmapData[key]; }

            var monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

            var wrapper = createEl('div', 'crontab-calendar');
            var title = createEl('h4', '', 'Calendario termico anual (' + new Date().getFullYear() + ')');
            wrapper.appendChild(title);

            var grid = createEl('div', 'crontab-calendar-grid');

            for (var m = 1; m <= 12; m++) {
                var daysInMonth = new Date(new Date().getFullYear(), m, 0).getDate();
                var firstDay = new Date(new Date().getFullYear(), m - 1, 1).getDay();
                firstDay = firstDay === 0 ? 6 : firstDay - 1;

                var monthDiv = createEl('div', 'crontab-calendar-month');
                var header = createEl('div', 'crontab-calendar-month-header', monthNames[m - 1]);
                monthDiv.appendChild(header);

                var daysDiv = createEl('div', 'crontab-calendar-days');
                for (var e = 0; e < firstDay; e++) {
                    daysDiv.appendChild(createEl('div', 'crontab-calendar-day cal-empty', ''));
                }
                for (var d = 1; d <= daysInMonth; d++) {
                    var key = new Date().getFullYear() + '-' + (m < 10 ? '0' + m : m) + '-' + (d < 10 ? '0' + d : d);
                    var count = heatmapData[key] || 0;
                    var level = getHeatLevel(count, maxCount);
                    var dayEl = createEl('div', 'crontab-calendar-day cal-heat-' + level, String(d));
                    dayEl.title = d + ' ' + monthNames[m - 1] + ': ' + count + ' ejecuciones';
                    daysDiv.appendChild(dayEl);
                }
                monthDiv.appendChild(daysDiv);
                grid.appendChild(monthDiv);
            }

            wrapper.appendChild(grid);

            // Legend
            var legend = createEl('div', 'crontab-calendar-legend');
            var legendColors = [
                { level: 0, color: '#ebedf0' }, { level: 2, color: '#c6e48b' },
                { level: 4, color: '#239a3b' }, { level: 6, color: '#b81414' }, { level: 7, color: '#4a0000' }
            ];
            var less = createEl('span', 'legend-label', 'Menos');
            legend.appendChild(less);
            legendColors.forEach(function (lc) {
                var swatch = createEl('span', 'legend-swatch', '');
                swatch.style.backgroundColor = lc.color;
                legend.appendChild(swatch);
            });
            var more = createEl('span', 'legend-label', 'Mas');
            legend.appendChild(more);
            wrapper.appendChild(legend);

            return wrapper;
        }

        /* ====================================================================
         * FEATURE 1: GANTT CHART
         * ==================================================================== */

        function renderGantt() {
            if (!currentParsed || !currentParsed.valid || currentParsed.isReboot) return document.createDocumentFragment();
            var wrapper = createEl('div', 'crontab-gantt');
            var title = createEl('h4', '', 'Diagrama de Gantt (proximas ' + nextCount + ' ejecuciones)');
            wrapper.appendChild(title);

            var execs = getNextExecutions(currentParsed.fields, currentParsed.isSixField, nextCount);
            if (execs.length === 0) {
                var empty = createEl('p', '', 'No hay suficientes ejecuciones para mostrar el diagrama.');
                empty.style.cssText = 'color:#888;font-style:italic;font-size:0.85rem';
                wrapper.appendChild(empty);
                return wrapper;
            }

            var zoomLevel = 86400000; // 1 dia por defecto
            var zoomBtns = createEl('div', 'crontab-gantt-zoom');
            var zooms = [
                { label: '1h', value: 3600000 },
                { label: '4h', value: 14400000 },
                { label: '24h', value: 86400000 },
                { label: '7d', value: 604800000 }
            ];
            zooms.forEach(function (z) {
                var btn = createEl('button', 'crontab-btn crontab-btn-sm' + (zoomLevel === z.value ? ' is-active' : ''), z.label);
                btn.addEventListener('click', function () {
                    zoomLevel = z.value;
                    drawGantt(canvas, execs, zoomLevel);
                    zoomBtns.querySelectorAll('.crontab-btn-sm').forEach(function (b) { b.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                });
                zoomBtns.appendChild(btn);
            });
            wrapper.appendChild(zoomBtns);

            var canvas = document.createElement('canvas');
            canvas.className = 'crontab-gantt-canvas';
            canvas.width = 800;
            canvas.height = Math.max(200, 30 + execs.length * 14);
            canvas.style.cssText = 'width:100%;max-width:800px;height:auto;border:1px solid #d7dbe2;border-radius:8px;background:#fff';
            wrapper.appendChild(canvas);

            drawGantt(canvas, execs, zoomLevel);

            return wrapper;
        }

        function drawGantt(canvas, execs, zoomLevel) {
            var ctx = canvas.getContext('2d');
            var w = canvas.width;
            var h = canvas.height;
            var padLeft = 60;
            var padRight = 20;
            var padTop = 30;
            var rowHeight = 12;
            var rowGap = 2;
            var chartW = w - padLeft - padRight;
            var chartH = execs.length * (rowHeight + rowGap);

            ctx.clearRect(0, 0, w, h);

            var now = new Date();
            var endTime = new Date(now.getTime() + zoomLevel);

            var timeRange = endTime.getTime() - now.getTime();
            var timeStart = now.getTime();

            // Draw time labels
            ctx.fillStyle = '#666';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'center';
            var numLabels = 6;
            for (var i = 0; i <= numLabels; i++) {
                var t = timeStart + (timeRange / numLabels) * i;
                var labelDate = new Date(t);
                var labelX = padLeft + (chartW / numLabels) * i;
                ctx.fillText(formatTime(labelDate), labelX, 12);
                ctx.strokeStyle = '#eee';
                ctx.beginPath();
                ctx.moveTo(labelX, padTop);
                ctx.lineTo(labelX, padTop + chartH);
                ctx.stroke();
            }

            // Draw bars
            var barColors = ['#4a90d9', '#50c878', '#e8a838', '#d94a4a', '#9b59b6', '#1abc9c', '#e67e22', '#3498db'];
            for (var i = 0; i < execs.length; i++) {
                var d = execs[i];
                var t = d.getTime();
                var x = padLeft + ((t - timeStart) / timeRange) * chartW;
                var y = padTop + i * (rowHeight + rowGap);
                var barW = Math.max(4, (chartW / timeRange) * 60000);

                ctx.fillStyle = barColors[i % barColors.length];
                ctx.fillRect(x, y, barW, rowHeight);

                // Label
                ctx.fillStyle = '#333';
                ctx.font = '9px sans-serif';
                ctx.textAlign = 'right';
                var label = formatTime(d);
                ctx.fillText(label, padLeft - 4, y + rowHeight - 1);
            }
        }

        function formatTime(d) {
            var hours = d.getHours();
            var mins = d.getMinutes();
            return (hours < 10 ? '0' + hours : hours) + ':' + (mins < 10 ? '0' + mins : mins);
        }

        /* ====================================================================
         * FEATURE 5: EXPORT (updated)
         * ==================================================================== */

        function renderExport() {
            if (!currentExpression || !currentParsed || !currentParsed.valid) return document.createDocumentFragment();

            var wrapper = createEl('div', 'crontab-export');
            var title = createEl('h4', '', 'Exportar');
            wrapper.appendChild(title);

            var tabs = [
                { name: 'github', title: 'GitHub', content: toGithubAction(currentExpression) },
                { name: 'ansible', title: 'Ansible', content: toAnsibleCron(currentExpression) },
                { name: 'cloudwatch', title: 'CloudWatch', content: toCloudWatchExpression(currentExpression) },
                { name: 'sql', title: 'SQL', content: toSqlQuery(currentExpression, currentParsed) },
                { name: 'resumen', title: 'Resumen', content: toNaturalSummary(currentExpression, currentParsed) },
                { name: 'systemd', title: 'systemd', content: toSystemdTimer(currentExpression) },
                { name: 'k8s', title: 'Kubernetes', content: toKubernetesYaml(currentExpression) },
                { name: 'aws', title: 'AWS', content: toEventBridgeRule(currentExpression) },
                { name: 'ical', title: 'iCal', content: toIcal(currentExpression, currentParsed, 50) },
                { name: 'text', title: 'Texto', content: toPlainText(currentExpression, currentParsed) }
            ];

            var activeTab = 'github';

            var tabBar = createEl('div', 'crontab-export-tabs');
            var tabContent = createEl('div', 'crontab-export-content');

            function renderTabContent() {
                tabContent.innerHTML = '';
                var content = '';
                tabs.forEach(function (t) { if (t.name === activeTab) content = t.content; });
                var pre = document.createElement('pre');
                var code = document.createElement('code');
                code.textContent = content;
                pre.appendChild(code);
                tabContent.appendChild(pre);

                var copyBtn = createEl('button', 'crontab-btn crontab-btn-sm', copied === activeTab ? 'Copiado!' : 'Copiar');
                copyBtn.style.cssText = 'position:absolute;top:0.5rem;right:0.5rem';
                copyBtn.addEventListener('click', function () {
                    copyToClipboard(content).then(function () {
                        copied = activeTab; copyBtn.textContent = 'Copiado!';
                        setTimeout(function () { copyBtn.textContent = 'Copiar'; if (copied === activeTab) copied = ''; }, 2000);
                    });
                });
                tabContent.appendChild(copyBtn);

                // Download iCal button
                if (activeTab === 'ical') {
                    var dlBtn = createEl('button', 'crontab-btn crontab-btn-sm', 'Descargar .ics');
                    dlBtn.style.cssText = 'position:absolute;top:0.5rem;right:4.5rem';
                    dlBtn.addEventListener('click', function () {
                        var blob = new Blob([content], { type: 'text/calendar;charset=utf-8' });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url; a.download = 'crontab-events.ics'; a.click();
                        URL.revokeObjectURL(url);
                    });
                    tabContent.appendChild(dlBtn);
                }
            }

            tabs.forEach(function (tab) {
                var tabEl = createEl('button', 'crontab-export-tab' + (tab.name === activeTab ? ' is-active' : ''), tab.title);
                tabEl.addEventListener('click', function () {
                    activeTab = tab.name;
                    tabBar.querySelectorAll('.crontab-export-tab').forEach(function (t) { t.classList.remove('is-active'); });
                    tabEl.classList.add('is-active');
                    renderTabContent();
                });
                tabBar.appendChild(tabEl);
            });

            wrapper.appendChild(tabBar);
            wrapper.appendChild(tabContent);
            renderTabContent();

            return wrapper;
        }

        /* ====================================================================
         * FEATURE 4: WHAT-IF SIMULATOR
         * ==================================================================== */

        function renderWhatIf() {
            if (!currentParsed || !currentParsed.valid || currentParsed.isReboot) return document.createDocumentFragment();
            var wrapper = createEl('div', 'crontab-whatif');
            var title = createEl('h4', '', 'Simulador "Que pasaria si..."');
            wrapper.appendChild(title);

            var inputGroup = createEl('div', 'crontab-input-group');
            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'crontab-input';
            input.value = currentExpression;
            input.placeholder = 'Introduce una expresion alternativa';
            inputGroup.appendChild(input);

            var compareBtn = createEl('button', 'crontab-btn', 'Comparar');
            inputGroup.appendChild(compareBtn);
            wrapper.appendChild(inputGroup);

            var resultDiv = createEl('div', 'crontab-whatif-results');
            resultDiv.style.cssText = 'margin-top:0.75rem;display:none';
            wrapper.appendChild(resultDiv);

            function computeStats(expr) {
                var parsed = parseCronExpression(expr);
                if (!parsed || !parsed.valid) return null;
                if (parsed.isReboot) return { label: expr, executionsPerDay: 'N/A', executionsPerWeek: 'N/A', executionsPerMonth: 'N/A', valid: true, isReboot: true };
                var fields = parsed.fields;
                if (!fields) return null;
                var min = fields.minute || [], hour = fields.hour || [], dow = fields.dayOfWeek || [], mon = fields.month || [];
                var perDay = 0;
                for (var h = 0; h < 24; h++) {
                    if (hour.length < 24 && hour.indexOf(h) === -1) continue;
                    for (var m = 0; m < 60; m++) {
                        if (min.length < 60 && min.indexOf(m) === -1) continue;
                        perDay++;
                    }
                }
                var perWeek = perDay * 7;
                if (dow.length > 0 && dow.length < 8) {
                    perWeek = perDay * dow.length;
                }
                var perMonth = perDay * 30;
                if (mon.length > 0 && mon.length < 12) {
                    perMonth = perDay * 30 * (mon.length / 12);
                }
                return { label: expr, executionsPerDay: perDay, executionsPerWeek: Math.round(perWeek), executionsPerMonth: Math.round(perMonth), valid: true, isReboot: false };
            }

            function renderComparison() {
                var originalStats = computeStats(currentExpression);
                var modifiedStats = computeStats(input.value);

                if (!modifiedStats) {
                    resultDiv.innerHTML = '';
                    var err = createEl('p', '', 'La expresion modificada no es valida. Revisala e intenta de nuevo.');
                    err.style.color = '#d94a4a';
                    resultDiv.appendChild(err);
                    resultDiv.style.display = 'block';
                    return;
                }

                resultDiv.innerHTML = '';
                resultDiv.style.display = 'block';

                var table = document.createElement('table');
                table.style.cssText = 'width:100%;border-collapse:collapse;font-size:0.9rem';
                var thead = document.createElement('thead');
                var headRow = document.createElement('tr');
                ['Metrica', 'Original', 'Modificada'].forEach(function (h) {
                    var th = document.createElement('th');
                    th.textContent = h;
                    th.style.cssText = 'text-align:left;padding:0.4rem 0.5rem;border-bottom:2px solid #d7dbe2';
                    headRow.appendChild(th);
                });
                thead.appendChild(headRow);
                table.appendChild(thead);

                var tbody = document.createElement('tbody');
                var rows = [
                    { label: 'Expresion', original: originalStats.label, modified: modifiedStats.label },
                    { label: 'Ejecuciones / dia', original: originalStats.isReboot ? 'N/A' : String(originalStats.executionsPerDay), modified: modifiedStats.isReboot ? 'N/A' : String(modifiedStats.executionsPerDay) },
                    { label: 'Ejecuciones / semana', original: originalStats.isReboot ? 'N/A' : String(originalStats.executionsPerWeek), modified: modifiedStats.isReboot ? 'N/A' : String(modifiedStats.executionsPerWeek) },
                    { label: 'Ejecuciones / mes', original: originalStats.isReboot ? 'N/A' : String(originalStats.executionsPerMonth), modified: modifiedStats.isReboot ? 'N/A' : String(modifiedStats.executionsPerMonth) }
                ];
                rows.forEach(function (r) {
                    var tr = document.createElement('tr');
                    [r.label, r.original, r.modified].forEach(function (c) {
                        var td = document.createElement('td');
                        td.textContent = c;
                        td.style.cssText = 'padding:0.4rem 0.5rem;border-bottom:1px solid #eee';
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });
                table.appendChild(tbody);
                resultDiv.appendChild(table);

                // Apply button
                var applyBtn = createEl('button', 'crontab-btn', 'Aplicar');
                applyBtn.style.cssText = 'margin-top:0.5rem';
                applyBtn.addEventListener('click', function () {
                    analyze(input.value);
                });
                resultDiv.appendChild(applyBtn);
            }

            compareBtn.addEventListener('click', renderComparison);
            input.addEventListener('keyup', function (e) {
                if (e.key === 'Enter') renderComparison();
            });

            return wrapper;
        }

        /* ====================================================================
         * FEATURE 7: SHARE AS IMAGE
         * ==================================================================== */

        function renderShare() {
            if (!currentParsed || !currentParsed.valid) return document.createDocumentFragment();
            var wrapper = createEl('div', 'crontab-share');
            var title = createEl('h4', '', 'Compartir como imagen');
            wrapper.appendChild(title);

            var btn = createEl('button', 'crontab-btn', 'Compartir como imagen');
            wrapper.appendChild(btn);

            var previewDiv = createEl('div', 'crontab-share-preview');
            previewDiv.style.cssText = 'display:none;margin-top:0.75rem;text-align:center';
            wrapper.appendChild(previewDiv);

            btn.addEventListener('click', function () {
                previewDiv.innerHTML = '';
                var canvas = document.createElement('canvas');
                canvas.width = 600;
                canvas.height = 400;
                var ctx = canvas.getContext('2d');

                // Background
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                // Header bar
                ctx.fillStyle = '#2c3e50';
                ctx.fillRect(0, 0, canvas.width, 60);
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 22px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('Crontab Helper - atareao.es', canvas.width / 2, 38);

                // Expression
                ctx.fillStyle = '#2c3e50';
                ctx.font = 'bold 18px monospace';
                ctx.textAlign = 'center';
                ctx.fillText(currentExpression, canvas.width / 2, 100);

                // Description
                ctx.fillStyle = '#555';
                ctx.font = '14px sans-serif';
                ctx.textAlign = 'center';
                var desc = currentParsed.description || '';
                ctx.fillText(desc, canvas.width / 2, 128);

                // Next executions
                ctx.fillStyle = '#2c3e50';
                ctx.font = 'bold 14px sans-serif';
                ctx.textAlign = 'left';
                ctx.fillText('Proximas ejecuciones:', 30, 165);

                var dayNames = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
                var execs = currentParsed.nextExecutions || [];
                ctx.font = '13px sans-serif';
                ctx.fillStyle = '#444';
                var yPos = 190;
                for (var i = 0; i < Math.min(execs.length, 3); i++) {
                    var d = execs[i];
                    var dateStr = d.toLocaleDateString('es-ES') + ' ' + formatTime(d) + ' (' + dayNames[d.getDay()] + ')';
                    ctx.fillText((i + 1) + '. ' + dateStr, 50, yPos);
                    yPos += 24;
                }

                // Footer
                ctx.fillStyle = '#eee';
                ctx.fillRect(0, canvas.height - 40, canvas.width, 40);
                ctx.fillStyle = '#999';
                ctx.font = '11px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('Generado con Crontab Helper - atareao.es', canvas.width / 2, canvas.height - 16);

                // QR code
                var qrSize = 80;
                var qrX = canvas.width - qrSize - 20;
                var qrY = 290;
                try {
                    if (typeof QRCode !== 'undefined') {
                        var qrDiv = document.createElement('div');
                        qrDiv.id = 'crontab-share-qr-temp';
                        qrDiv.style.cssText = 'position:absolute;left:-9999px;top:-9999px';
                        document.body.appendChild(qrDiv);
                        var qr = new QRCode(qrDiv, {
                            text: window.location.href,
                            width: qrSize,
                            height: qrSize,
                            colorDark: '#2c3e50',
                            colorLight: '#ffffff',
                            correctLevel: QRCode.CorrectLevel.H
                        });
                        setTimeout(function () {
                            var img = qrDiv.querySelector('img') || qrDiv.querySelector('canvas');
                            if (img) {
                                try {
                                    ctx.drawImage(img, qrX, qrY, qrSize, qrSize);
                                } catch (e) {}
                            }
                            document.body.removeChild(qrDiv);
                            // Draw border
                            ctx.strokeStyle = '#ddd';
                            ctx.lineWidth = 1;
                            ctx.strokeRect(qrX, qrY, qrSize, qrSize);
                            // Update preview
                            updatePreview(canvas);
                        }, 200);
                    } else {
                        // Draw simple QR pattern manually
                        drawSimpleQR(ctx, qrX, qrY, qrSize, window.location.href);
                        ctx.strokeStyle = '#ddd';
                        ctx.lineWidth = 1;
                        ctx.strokeRect(qrX, qrY, qrSize, qrSize);
                        updatePreview(canvas);
                    }
                } catch (e) {
                    // Fallback: draw a placeholder
                    ctx.fillStyle = '#f5f5f5';
                    ctx.fillRect(qrX, qrY, qrSize, qrSize);
                    ctx.fillStyle = '#999';
                    ctx.font = '10px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText('QR', qrX + qrSize / 2, qrY + qrSize / 2 + 3);
                    updatePreview(canvas);
                }

                function updatePreview(c) {
                    previewDiv.innerHTML = '';
                    var img = document.createElement('img');
                    img.src = c.toDataURL('image/png');
                    img.style.cssText = 'max-width:100%;border:2px solid #d7dbe2;border-radius:8px';
                    previewDiv.appendChild(img);
                    previewDiv.style.display = 'block';

                    var dlBtn = createEl('button', 'crontab-btn', 'Descargar PNG');
                    dlBtn.style.cssText = 'margin-top:0.5rem';
                    dlBtn.addEventListener('click', function () {
                        var a = document.createElement('a');
                        a.href = c.toDataURL('image/png');
                        a.download = 'crontab-' + currentExpression.replace(/[\/\s*]/g, '_') + '.png';
                        a.click();
                    });
                    previewDiv.appendChild(dlBtn);
                }
            });

            return wrapper;
        }

        function drawSimpleQR(ctx, x, y, size, text) {
            // Draw a simple QR-like pattern as fallback
            var cellSize = size / 21;
            var seed = 0;
            for (var i = 0; i < text.length; i++) {
                seed = ((seed << 5) - seed) + text.charCodeAt(i);
                seed = seed & seed;
            }
            var rng = function () {
                seed = (seed * 1103515245 + 12345) & 0x7fffffff;
                return (seed >>> 16) & 0x7fff;
            };

            for (var row = 0; row < 21; row++) {
                for (var col = 0; col < 21; col++) {
                    var isDark = false;
                    // Position patterns
                    if ((row < 7 && col < 7) || (row < 7 && col > 13) || (row > 13 && col < 7)) {
                        isDark = (row === 0 || row === 6 || col === 0 || col === 6) ||
                                 (row >= 2 && row <= 4 && col >= 2 && col <= 4);
                    } else if (row === 6 || col === 6) {
                        isDark = (row + col) % 2 === 0;
                    } else {
                        isDark = rng() % 2 === 0;
                    }
                    ctx.fillStyle = isDark ? '#2c3e50' : '#ffffff';
                    ctx.fillRect(x + col * cellSize, y + row * cellSize, Math.ceil(cellSize), Math.ceil(cellSize));
                }
            }
        }

        function renderHistory() {
            var history = getHistory();
            var wrapper = createEl('div', 'crontab-history');
            var title = createEl('h4', '', 'Historial');
            wrapper.appendChild(title);

            if (history.length === 0) {
                var empty = createEl('p', '', 'Aun no hay historial. Las expresiones que analices se guardaran aqui.');
                empty.style.cssText = 'color:#888;font-style:italic;font-size:0.85rem';
                wrapper.appendChild(empty);
                return wrapper;
            }

            var list = document.createElement('ul');
            list.className = 'crontab-history-list';
            history.slice(0, 10).forEach(function (item) {
                var li = document.createElement('li');
                li.className = 'crontab-history-item';
                var code = document.createElement('code');
                code.textContent = item.expression;
                li.appendChild(code);
                var desc = createEl('span', 'crontab-history-desc', item.description || '');
                li.appendChild(desc);
                li.addEventListener('click', function () { analyze(item.expression); });
                list.appendChild(li);
            });
            wrapper.appendChild(list);
            return wrapper;
        }

        function toPlainText(expression, parsed) {
            if (!parsed) return 'Expresion: ' + expression + '\n(Pulsa "Analizar" para ver los detalles)';
            var lines = [];
            lines.push('Expresion: ' + expression);
            if (parsed.valid) {
                lines.push('Descripcion: ' + parsed.description);
                if (parsed.isReboot) lines.push('Se ejecuta al arrancar el sistema.');
                else if (parsed.nextExecutions && parsed.nextExecutions.length > 0) {
                    lines.push(''); lines.push('Proximas ejecuciones:');
                    for (var i = 0; i < Math.min(parsed.nextExecutions.length, 5); i++) {
                        var d = parsed.nextExecutions[i];
                        lines.push('  ' + (i + 1) + '. ' + d.toISOString().replace('T', ' ').slice(0, 19) + ' UTC');
                    }
                }
            } else lines.push('Error: ' + parsed.error);
            return lines.join('\n');
        }

        // Initial render
        render();
    }

    /* ====================================================================
     * INIT
     * ==================================================================== */

    function init() {
        var containers = document.querySelectorAll('.atareao-crontab-helper');
        containers.forEach(function (container) {
            // Comprobar que no este ya inicializado
            if (container.getAttribute('data-initialized')) return;
            container.setAttribute('data-initialized', '1');
            // Quitar loading
            var loading = container.querySelector('.atareao-crontab-loading');
            if (loading) loading.remove();
            var noscript = container.querySelector('.atareao-crontab-noscript');
            if (noscript) noscript.remove();
            // Inicializar
            crontabFrontend(container);
        });
    }

    // Ejecutar cuando el DOM este listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();