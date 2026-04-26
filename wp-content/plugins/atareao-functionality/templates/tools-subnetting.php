<?php
/**
 * Tools - IPv4 Subnet Calculator
 *
 * Route: /tools/subnetting
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/subnetting/');
$tool_title = 'IPv4 Subnet Calculator: calcula red, broadcast y hosts | ' . get_bloginfo('name');
$tool_description = 'Calculadora IPv4 de subredes con CIDR. Obtiene network, broadcast, mascara, wildcard, rango de hosts y representacion binaria.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'IPv4 Subnet Calculator',
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
                    'name' => 'Que datos devuelve la calculadora de subredes?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Devuelve network, broadcast, mascara, wildcard, primer y ultimo host, y datos binarios.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Que prefijos CIDR soporta?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Soporta cualquier prefijo entre /0 y /32.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir un calculo concreto?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. El boton Copiar enlace guarda IP y prefijo en la URL para abrir el mismo resultado.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Para que sirve subnetting en redes reales?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Ayuda a segmentar redes, definir VLANs, reducir broadcast y planificar direccionamiento en oficinas, cloud y laboratorios.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Que errores son frecuentes al calcular subredes?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Los errores tipicos son confundir mascara con wildcard, no validar el rango de hosts y aplicar mal prefijos /31 o /32.',
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
    <article id="post-tools-subnetting" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">IPv4 Subnet Calculator</h1>
            <?php atareao_tools_render_breadcrumb('subnetting'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Calcula red, broadcast, mascara, wildcard, rango de hosts y capacidad para una IPv4.
                </p>
            </div>

            <div id="subnet_error" class="atareao-feedback-error" hidden></div>

            <form id="subnet_form" class="atareao-contact-form" method="post" action="" novalidate>
                <div>
                    <label for="subnet_ip_1">Direccion IPv4 / Prefijo CIDR</label>
                    <div class="subnet-ip-cidr-row">
                        <input id="subnet_ip_1" class="subnet-ip-octet" type="text" value="192" inputmode="numeric" maxlength="3" aria-label="IPv4 octeto 1">
                        <span class="subnet-dot" aria-hidden="true">.</span>
                        <input id="subnet_ip_2" class="subnet-ip-octet" type="text" value="168" inputmode="numeric" maxlength="3" aria-label="IPv4 octeto 2">
                        <span class="subnet-dot" aria-hidden="true">.</span>
                        <input id="subnet_ip_3" class="subnet-ip-octet" type="text" value="1" inputmode="numeric" maxlength="3" aria-label="IPv4 octeto 3">
                        <span class="subnet-dot" aria-hidden="true">.</span>
                        <input id="subnet_ip_4" class="subnet-ip-octet" type="text" value="42" inputmode="numeric" maxlength="3" aria-label="IPv4 octeto 4">
                        <span class="subnet-slash" aria-hidden="true">/</span>
                        <select id="subnet_prefix" class="subnet-prefix-select" aria-label="Prefijo CIDR">
                            <?php for ($i = 0; $i <= 32; $i++) : ?>
                                <option value="<?php echo esc_attr($i); ?>" <?php selected($i, 24); ?>>/<?php echo esc_html($i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <p>
                        <button type="button" class="subnet-prefix-preset subnet-example" data-prefix="8">/8</button>
                        <button type="button" class="subnet-prefix-preset subnet-example" data-prefix="16">/16</button>
                        <button type="button" class="subnet-prefix-preset subnet-example" data-prefix="24">/24</button>
                        <button type="button" class="subnet-prefix-preset subnet-example" data-prefix="27">/27</button>
                        <button type="button" class="subnet-prefix-preset subnet-example" data-prefix="30">/30</button>
                        <button type="button" class="subnet-prefix-preset subnet-example" data-prefix="32">/32</button>
                    </p>
                </div>

                <div>
                    <label>Ejemplos rapidos</label>
                    <p>
                        <button type="button" class="subnet-example" data-ip="10.0.0.15" data-prefix="8">10.0.0.15/8</button>
                        <button type="button" class="subnet-example" data-ip="172.16.20.140" data-prefix="16">172.16.20.140/16</button>
                        <button type="button" class="subnet-example" data-ip="192.168.1.42" data-prefix="24">192.168.1.42/24</button>
                        <button type="button" class="subnet-example" data-ip="192.168.1.42" data-prefix="27">192.168.1.42/27</button>
                        <button type="button" class="subnet-example" data-ip="192.168.1.42" data-prefix="30">192.168.1.42/30</button>
                        <button type="button" class="subnet-example" data-ip="203.0.113.7" data-prefix="32">203.0.113.7/32</button>
                    </p>
                </div>

                <div style="text-align:center;">
                    <button type="submit" id="subnet_analyze">Calcular</button>
                    <button type="button" id="subnet_copy_link" class="subnet-example">Copiar enlace</button>
                </div>

                <section>
                    <label for="subnet_summary">Resumen</label>
                    <p id="subnet_summary" class="atareao-subnet-summary">Red /24 con 254 hosts utilizables.</p>
                </section>

                <section>
                    <label>Resultados</label>
                    <div class="subnet-results-table-wrap">
                        <table class="subnet-results-table" id="subnet_results_table">
                            <thead>
                                <tr>
                                    <th scope="col">Campo</th>
                                    <th scope="col">Valor</th>
                                </tr>
                            </thead>
                            <tbody id="subnet_results"></tbody>
                        </table>
                    </div>
                </section>
            </form>

            <section class="atareao-tool-seo-content" aria-label="Guia rapida de subnetting">
                <h2>Guia rapida de uso</h2>
                <h3>1. Define direccion y prefijo</h3>
                <p>Completa los cuatro octetos IPv4 y selecciona el CIDR para calcular el bloque de red correspondiente.</p>

                <h3>2. Revisa red, broadcast y hosts</h3>
                <p>Comprueba resultados clave para disenar rangos de direccionamiento, VLANs y reglas de seguridad.</p>

                <h3>3. Compara escenarios</h3>
                <p>Prueba varios prefijos para equilibrar capacidad de hosts y segmentacion de red antes de desplegar.</p>
            </section>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de subnetting">
                <h2>Preguntas frecuentes</h2>
                <h3>Que calcula exactamente esta pagina</h3>
                <p>Calcula todos los datos relevantes de una subred IPv4 a partir de una direccion y un prefijo CIDR.</p>

                <h3>Que pasa en prefijos /31 y /32</h3>
                <p>En esos casos no se aplica el rango tradicional de hosts y se muestran las direcciones validas segun el bloque.</p>

                <h3>Como compartir el resultado</h3>
                <p>Pulsa Copiar enlace para obtener una URL con la IP y el prefijo, lista para compartir o guardar.</p>

                <h3>Para que se usa en entornos reales</h3>
                <p>Es una ayuda practica para dividir redes por departamentos, crear bloques para VPN, disenar laboratorios y optimizar despliegues en cloud.</p>

                <h3>Errores comunes de subnetting</h3>
                <p>Entre los fallos habituales estan interpretar mal el numero de hosts utiles, olvidar broadcast y mezclar mascara de red con wildcard.</p>

                <h3>Consejo para planificacion de IPs</h3>
                <p>Empieza por la capacidad futura de hosts, elige el prefijo adecuado y valida varios escenarios antes de documentar la red definitiva.</p>
            </section>
        </div>
    </article>
</main>

<style>
.atareao-subnet-summary {
    font-style: italic;
    color: inherit;
}

.atareao-contact-form .subnet-example {
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

.subnet-ip-cidr-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.atareao-contact-form .subnet-ip-octet {
    width: 4.2rem;
    text-align: center;
}

.atareao-contact-form .subnet-prefix-select {
    width: 5.2rem;
    text-align: center;
    margin-bottom: 1.2rem;
    padding: 0.85rem;
    border: 1.5px solid #c3cfe2;
    border-radius: 8px;
    font-size: 1rem;
    background-color: #f7fafd;
    color: #222;
    line-height: 1.1;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    padding-right: 1.35rem;
    appearance: auto;
}

.subnet-dot,
.subnet-slash {
    font-weight: 700;
    font-size: 1.05rem;
    line-height: 1;
    min-width: 0.35rem;
    text-align: center;
    opacity: 0.8;
}

[data-theme="dark"] .atareao-contact-form .subnet-prefix-select {
    border-color: #2a2a2a;
    background-color: #151617;
    color: #e6e6e6;
}

@media (prefers-color-scheme: dark) {
    html:not([data-theme="light"]) .atareao-contact-form .subnet-prefix-select {
        border-color: #2a2a2a;
        background-color: #151617;
        color: #e6e6e6;
    }
}

.atareao-contact-form .subnet-example:hover {
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

#subnet_results code {
    display: inline-block;
    padding: 0.15rem 0.45rem;
    border-radius: 0.4rem;
    font-size: 0.92em;
    border: 1px solid #d9d9d9;
    background: #f5f5f5;
    color: #1f2937;
}

.subnet-results-table-wrap {
    overflow-x: auto;
}

.subnet-results-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}

.subnet-results-table th,
.subnet-results-table td {
    padding: 0.5rem 0.55rem;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
    color: #222222;
}

.subnet-results-table th {
    font-weight: 700;
}

[data-theme="light"] .subnet-results-table th,
[data-theme="light"] .subnet-results-table td {
    color: #222222;
}

[data-theme="dark"] .subnet-results-table th,
[data-theme="dark"] .subnet-results-table td {
    border-bottom-color: #334155;
    color: #e5e7eb;
}

[data-theme="light"] #subnet_results code {
    border-color: #d7dbe2;
    background: #f7f8fb;
    color: #1f2937;
}

[data-theme="dark"] .atareao-subnet-summary {
    color: #e5e7eb;
}

[data-theme="dark"] #subnet_results code {
    border-color: #44506a;
    background: #1d2538;
    color: #e5e7eb;
}

@media (prefers-color-scheme: dark) {
    html:not([data-theme="light"]) .subnet-results-table th,
    html:not([data-theme="light"]) .subnet-results-table td {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) #subnet_results code {
        border-color: #44506a;
        background: #1d2538;
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .atareao-subnet-summary {
        color: #e5e7eb;
    }
}
</style>

<script>
(function () {
    'use strict';

    var ipInputs = [
        document.getElementById('subnet_ip_1'),
        document.getElementById('subnet_ip_2'),
        document.getElementById('subnet_ip_3'),
        document.getElementById('subnet_ip_4')
    ];
    var prefixInput = document.getElementById('subnet_prefix');
    var form = document.getElementById('subnet_form');
    var summary = document.getElementById('subnet_summary');
    var results = document.getElementById('subnet_results');
    var errorBox = document.getElementById('subnet_error');
    var copyLinkBtn = document.getElementById('subnet_copy_link');
    var prefixPresetButtons = document.querySelectorAll('.subnet-prefix-preset');
    var exampleButtons = document.querySelectorAll('.subnet-example[data-ip]');

    function getIpFromInputs() {
        return ipInputs.map(function (input) {
            return input.value.trim();
        }).join('.');
    }

    function setIpInputs(ip) {
        var parts = ip.split('.');
        for (var i = 0; i < 4; i++) {
            ipInputs[i].value = parts[i] || '';
        }
    }

    function showError(message) {
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function clearError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function ipToInt(ip) {
        var parts = ip.split('.');
        if (parts.length !== 4) {
            throw new Error('La IPv4 debe tener 4 octetos.');
        }

        var out = 0;
        for (var i = 0; i < 4; i++) {
            var n = Number(parts[i]);
            if (!Number.isInteger(n) || n < 0 || n > 255) {
                throw new Error('Octeto fuera de rango en la IPv4.');
            }
            out = (out << 8) + n;
        }

        return out >>> 0;
    }

    function intToIp(value) {
        return [
            (value >>> 24) & 255,
            (value >>> 16) & 255,
            (value >>> 8) & 255,
            value & 255
        ].join('.');
    }

    function maskFromPrefix(prefix) {
        if (prefix === 0) {
            return 0;
        }
        return ((0xffffffff << (32 - prefix)) >>> 0);
    }

    function toBinaryOctets(value) {
        var ip = intToIp(value).split('.');
        return ip.map(function (part) {
            return Number(part).toString(2).padStart(8, '0');
        }).join('.');
    }

    function usableHosts(prefix) {
        if (prefix === 32) {
            return 1;
        }
        if (prefix === 31) {
            return 2;
        }
        return Math.max(0, Math.pow(2, 32 - prefix) - 2);
    }

    function calculate(ip, prefix) {
        var ipInt = ipToInt(ip);
        var mask = maskFromPrefix(prefix);
        var wildcard = (~mask) >>> 0;
        var network = (ipInt & mask) >>> 0;
        var broadcast = (network | wildcard) >>> 0;

        var firstHost;
        var lastHost;

        if (prefix >= 31) {
            firstHost = network;
            lastHost = broadcast;
        } else {
            firstHost = (network + 1) >>> 0;
            lastHost = (broadcast - 1) >>> 0;
        }

        return {
            ip: ip,
            prefix: prefix,
            mask: intToIp(mask),
            wildcard: intToIp(wildcard),
            network: intToIp(network),
            broadcast: intToIp(broadcast),
            firstHost: intToIp(firstHost),
            lastHost: intToIp(lastHost),
            totalHosts: Math.pow(2, 32 - prefix),
            usableHosts: usableHosts(prefix),
            networkBinary: toBinaryOctets(network),
            maskBinary: toBinaryOctets(mask)
        };
    }

    function updateShareUrl(ip, prefix) {
        var url = new URL(window.location.href);
        url.searchParams.set('ip', ip);
        url.searchParams.set('prefix', String(prefix));
        window.history.replaceState({}, '', url.toString());
        return url.toString();
    }

    function fillFromUrl() {
        var url = new URL(window.location.href);
        var ip = url.searchParams.get('ip');
        var prefix = url.searchParams.get('prefix');

        if (ip) {
            setIpInputs(ip);
        }
        if (prefix !== null && prefix !== '') {
            prefixInput.value = prefix;
        }
    }

    function renderResultRows(rows) {
        results.innerHTML = '';

        for (var i = 0; i < rows.length; i++) {
            var tr = document.createElement('tr');

            var fieldTd = document.createElement('td');
            fieldTd.textContent = rows[i].label;

            var valueTd = document.createElement('td');
            var code = document.createElement('code');
            code.textContent = rows[i].value;
            valueTd.appendChild(code);

            tr.appendChild(fieldTd);
            tr.appendChild(valueTd);
            results.appendChild(tr);
        }
    }

    function render() {
        clearError();

        var ip = getIpFromInputs();
        var prefix = Number(prefixInput.value);

        if (!Number.isInteger(prefix) || prefix < 0 || prefix > 32) {
            showError('El prefijo CIDR debe estar entre 0 y 32.');
            return;
        }

        var data;
        try {
            data = calculate(ip, prefix);
        } catch (error) {
            showError(error.message || 'No se pudo calcular la subred.');
            return;
        }

        summary.textContent = 'Red /' + data.prefix + ' con ' + data.usableHosts + ' hosts utilizables.';

        renderResultRows([
            { label: 'Network', value: data.network + '/' + data.prefix },
            { label: 'Broadcast', value: data.broadcast },
            { label: 'Mascara', value: data.mask },
            { label: 'Wildcard', value: data.wildcard },
            { label: 'Primer host', value: data.firstHost },
            { label: 'Ultimo host', value: data.lastHost },
            { label: 'Hosts totales', value: String(data.totalHosts) },
            { label: 'Hosts utilizables', value: String(data.usableHosts) },
            { label: 'Network (bin)', value: data.networkBinary },
            { label: 'Mask (bin)', value: data.maskBinary }
        ]);

        updateShareUrl(ip, prefix);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        render();
    });

    copyLinkBtn.addEventListener('click', function () {
        var url = updateShareUrl(getIpFromInputs(), Number(prefixInput.value));
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

    for (var k = 0; k < ipInputs.length; k++) {
        ipInputs[k].addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                render();
            }
        });

        ipInputs[k].addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3);
        });
    }

    prefixInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            render();
        }
    });

    for (var i = 0; i < exampleButtons.length; i++) {
        exampleButtons[i].addEventListener('click', function () {
            setIpInputs(this.getAttribute('data-ip') || '192.168.1.42');
            prefixInput.value = this.getAttribute('data-prefix') || '24';
            render();
        });
    }

    for (var j = 0; j < prefixPresetButtons.length; j++) {
        prefixPresetButtons[j].addEventListener('click', function () {
            prefixInput.value = this.getAttribute('data-prefix') || '24';
            render();
        });
    }

    fillFromUrl();
    render();
})();
</script>

<?php
get_footer();
