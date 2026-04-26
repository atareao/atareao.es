<?php
/**
 * Tools - JWT Decoder Inspector
 *
 * Route: /tools/jwt
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/jwt/');
$tool_title = 'JWT Decoder Inspector online | ' . get_bloginfo('name');
$tool_description = 'Decodifica JWT online, inspecciona header y payload, revisa expiracion exp/nbf/iat y valida formato Base64URL.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'JWT Decoder Inspector',
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
                    'name' => 'Que hace esta herramienta JWT?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Decodifica header y payload de un token JWT, muestra su contenido y analiza claims temporales como exp, nbf e iat.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Verifica la firma del JWT?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Esta version inspecciona formato y contenido, pero no valida criptograficamente la firma contra una clave.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Se envian datos del token al servidor?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'No. El analisis se realiza en el navegador.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir un analisis concreto?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. Puedes copiar un enlace con estado para reproducir el mismo analisis.',
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
    <article id="post-tools-jwt" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">JWT Decoder Inspector</h1>
            <?php atareao_tools_render_breadcrumb('jwt'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Decodifica JWT para inspeccionar header, payload y claims temporales. Util para debugging de APIs y autenticacion.
                </p>
            </div>

            <div id="jwt_error" class="atareao-feedback-error" hidden></div>

            <form id="jwt_form" class="atareao-contact-form" method="post" action="" novalidate>
                <div>
                    <label for="jwt_token">Token JWT</label>
                    <textarea id="jwt_token" rows="7" spellcheck="false" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...">eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkF0YXJlYW8iLCJpYXQiOjE3MTQxMzI4MDAsImV4cCI6MTcxNDEzNjQwMH0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c</textarea>
                </div>

                <div>
                    <label>Ejemplos rapidos</label>
                    <p>
                        <button type="button" class="jwt-example" data-token="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NSIsInJvbGUiOiJhZG1pbiIsImlhdCI6MTcwMDAwMDAwMCwiZXhwIjoyMDAwMDAwMDAwfQ.signature">JWT HS256</button>
                        <button type="button" class="jwt-example" data-token="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpc3MiOiJhdGFyZWFvLmVzIiwiYXVkIjoiYXBpIiwiZXhwIjoyMDAwMDAwMDAwLCJuYmYiOjE3MDAwMDAwMDB9.signature">JWT RS256</button>
                    </p>
                </div>

                <div style="text-align:center;">
                    <button type="submit" id="jwt_decode">Decodificar</button>
                    <button type="button" id="jwt_copy_link" class="jwt-example">Copiar enlace</button>
                </div>

                <section>
                    <label for="jwt_summary">Resumen</label>
                    <p id="jwt_summary" class="atareao-jwt-summary">Listo para decodificar.</p>
                </section>

                <section>
                    <label>Header</label>
                    <pre id="jwt_header" class="jwt-output" aria-live="polite"></pre>
                </section>

                <section>
                    <label>Payload</label>
                    <pre id="jwt_payload" class="jwt-output" aria-live="polite"></pre>
                </section>

                <section>
                    <label>Estado de claims temporales</label>
                    <div class="jwt-results-table-wrap">
                        <table class="jwt-results-table">
                            <thead>
                                <tr>
                                    <th scope="col">Claim</th>
                                    <th scope="col">Valor</th>
                                    <th scope="col">Fecha UTC</th>
                                    <th scope="col">Estado</th>
                                </tr>
                            </thead>
                            <tbody id="jwt_claims"></tbody>
                        </table>
                    </div>
                </section>
            </form>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de JWT">
                <h2>Preguntas frecuentes</h2>
                <h3>Que es un JWT</h3>
                <p>Un JWT (JSON Web Token) es un token con tres partes: header, payload y firma, usado en autenticacion y autorizacion.</p>

                <h3>Esta herramienta valida la firma</h3>
                <p>No, esta herramienta inspecciona contenido y formato. La validacion criptografica requiere clave y algoritmo correctos.</p>

                <h3>Como interpretar exp, nbf e iat</h3>
                <p>exp indica expiracion, nbf el momento desde el que es valido e iat la fecha de emision del token.</p>

                <h3>Uso recomendado en debugging</h3>
                <p>Utilizala para detectar tokens caducados, claims inesperados y errores de reloj entre cliente y servidor.</p>
            </section>
        </div>
    </article>
</main>

<style>
.atareao-jwt-summary {
    font-style: italic;
    color: inherit;
}

.atareao-contact-form .jwt-example {
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

.atareao-contact-form .jwt-example:hover {
    filter: brightness(0.94);
}

.jwt-output {
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

.jwt-results-table-wrap {
    overflow-x: auto;
}

.jwt-results-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}

.jwt-results-table th,
.jwt-results-table td {
    padding: 0.5rem 0.55rem;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
    color: #222222;
}

.jwt-results-table th {
    font-weight: 700;
}

.jwt-status-ok {
    color: #0f766e;
    font-weight: 600;
}

.jwt-status-warn {
    color: #b45309;
    font-weight: 600;
}

.jwt-status-bad {
    color: #b91c1c;
    font-weight: 600;
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

[data-theme="dark"] .atareao-jwt-summary {
    color: #e5e7eb;
}

[data-theme="dark"] .jwt-output {
    border-color: #44506a;
    background: #1d2538;
    color: #e5e7eb;
}

[data-theme="dark"] .jwt-results-table th,
[data-theme="dark"] .jwt-results-table td {
    border-bottom-color: #334155;
    color: #e5e7eb;
}

[data-theme="dark"] .jwt-status-ok {
    color: #5eead4;
}

[data-theme="dark"] .jwt-status-warn {
    color: #facc15;
}

[data-theme="dark"] .jwt-status-bad {
    color: #fca5a5;
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
    html:not([data-theme="light"]) .atareao-jwt-summary {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .jwt-output {
        border-color: #44506a;
        background: #1d2538;
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .jwt-results-table th,
    html:not([data-theme="light"]) .jwt-results-table td {
        color: #e5e7eb;
    }
}
</style>

<script>
(function () {
    'use strict';

    var form = document.getElementById('jwt_form');
    var tokenInput = document.getElementById('jwt_token');
    var summary = document.getElementById('jwt_summary');
    var headerBox = document.getElementById('jwt_header');
    var payloadBox = document.getElementById('jwt_payload');
    var claimsBody = document.getElementById('jwt_claims');
    var errorBox = document.getElementById('jwt_error');
    var copyLinkBtn = document.getElementById('jwt_copy_link');
    var exampleButtons = document.querySelectorAll('.jwt-example[data-token]');

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function b64urlDecode(input) {
        var normalized = String(input).replace(/-/g, '+').replace(/_/g, '/');
        var padding = normalized.length % 4;

        if (padding === 2) {
            normalized += '==';
        } else if (padding === 3) {
            normalized += '=';
        } else if (padding !== 0) {
            throw new Error('Segmento Base64URL invalido.');
        }

        var binary = atob(normalized);
        var bytes = [];
        for (var i = 0; i < binary.length; i++) {
            bytes.push(binary.charCodeAt(i));
        }

        try {
            return new TextDecoder('utf-8').decode(new Uint8Array(bytes));
        } catch (_err) {
            return binary;
        }
    }

    function safeJsonParse(value, label) {
        try {
            return JSON.parse(value);
        } catch (_err) {
            throw new Error('No se pudo parsear JSON en ' + label + '.');
        }
    }

    function formatJson(value) {
        return JSON.stringify(value, null, 2);
    }

    function toDateString(epochSeconds) {
        var d = new Date(epochSeconds * 1000);
        if (isNaN(d.getTime())) {
            return '-';
        }
        return d.toISOString();
    }

    function statusForClaim(name, epochSeconds, now) {
        if (!Number.isFinite(epochSeconds)) {
            return { text: 'No numerico', cls: 'jwt-status-warn' };
        }

        if (name === 'exp') {
            if (epochSeconds <= now) {
                return { text: 'Expirado', cls: 'jwt-status-bad' };
            }
            return { text: 'Vigente', cls: 'jwt-status-ok' };
        }

        if (name === 'nbf') {
            if (epochSeconds > now) {
                return { text: 'Aun no valido', cls: 'jwt-status-warn' };
            }
            return { text: 'Ya valido', cls: 'jwt-status-ok' };
        }

        if (name === 'iat') {
            if (epochSeconds > now) {
                return { text: 'Emision futura', cls: 'jwt-status-warn' };
            }
            return { text: 'Emitido', cls: 'jwt-status-ok' };
        }

        return { text: 'Informativo', cls: 'jwt-status-ok' };
    }

    function renderClaims(payload) {
        claimsBody.innerHTML = '';

        var now = Math.floor(Date.now() / 1000);
        var claimNames = ['exp', 'nbf', 'iat'];
        var hasAny = false;

        for (var i = 0; i < claimNames.length; i++) {
            var name = claimNames[i];
            if (typeof payload[name] === 'undefined') {
                continue;
            }

            hasAny = true;
            var raw = Number(payload[name]);
            var status = statusForClaim(name, raw, now);

            var tr = document.createElement('tr');
            var c1 = document.createElement('td');
            var c2 = document.createElement('td');
            var c3 = document.createElement('td');
            var c4 = document.createElement('td');

            c1.textContent = name;
            c2.textContent = String(payload[name]);
            c3.textContent = Number.isFinite(raw) ? toDateString(raw) : '-';
            c4.textContent = status.text;
            c4.className = status.cls;

            tr.appendChild(c1);
            tr.appendChild(c2);
            tr.appendChild(c3);
            tr.appendChild(c4);
            claimsBody.appendChild(tr);
        }

        if (!hasAny) {
            var row = document.createElement('tr');
            var cell = document.createElement('td');
            cell.colSpan = 4;
            cell.textContent = 'No hay claims temporales (exp/nbf/iat) en este token.';
            row.appendChild(cell);
            claimsBody.appendChild(row);
        }
    }

    function updateShareUrl(token) {
        var url = new URL(window.location.href);
        url.searchParams.set('token', String(token || '').slice(0, 1200));
        window.history.replaceState({}, '', url.toString());
        return url.toString();
    }

    function fillFromUrl() {
        var url = new URL(window.location.href);
        var token = url.searchParams.get('token');
        if (token) {
            tokenInput.value = token;
        }
    }

    function decodeJwt() {
        clearError();

        var token = tokenInput.value.trim();
        if (!token) {
            showError('Introduce un JWT para analizar.');
            return;
        }

        var parts = token.split('.');
        if (parts.length < 2) {
            showError('Un JWT debe tener al menos header.payload.');
            return;
        }

        try {
            var decodedHeader = safeJsonParse(b64urlDecode(parts[0]), 'header');
            var decodedPayload = safeJsonParse(b64urlDecode(parts[1]), 'payload');

            headerBox.textContent = formatJson(decodedHeader);
            payloadBox.textContent = formatJson(decodedPayload);
            renderClaims(decodedPayload);

            var alg = decodedHeader.alg || 'desconocido';
            summary.textContent = 'JWT decodificado. Algoritmo: ' + alg + '. Firma ' + (parts[2] ? 'presente' : 'ausente') + '.';
            updateShareUrl(token);
        } catch (err) {
            showError(err.message || 'No se pudo decodificar el JWT.');
            summary.textContent = 'Error al decodificar token.';
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        decodeJwt();
    });

    copyLinkBtn.addEventListener('click', function () {
        var url = updateShareUrl(tokenInput.value.trim());

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
            tokenInput.value = this.getAttribute('data-token') || '';
            decodeJwt();
        });
    }

    fillFromUrl();
    decodeJwt();
})();
</script>

<?php
get_footer();
