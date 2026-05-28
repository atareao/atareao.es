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

<?php
get_footer();
