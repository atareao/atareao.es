<?php
/**
 * Tools - Crontab Helper
 *
 * Route: /tools/crontab
 * Ahora renderiza el bloque Gutenberg atareao/crontab-helper
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
                        'text' => 'Soporta alias comunes como @hourly, @daily, @weekly y @monthly, ademas de @yearly y @reboot.',
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

// Enqueue block frontend assets
wp_enqueue_style('atareao-crontab-block-style');
wp_enqueue_script('atareao-crontab-frontend');

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
                    y las proximas ejecuciones previstas. Tambien puedes usar el
                    <strong>constructor visual</strong>, el
                    <strong>lenguaje natural</strong>, el
                    <strong>calendario termico anual</strong> y exportar a
                    systemd, Kubernetes, AWS EventBridge o iCal.
                </p>
            </div>

            <?php
            // Renderizar el bloque de Crontab Helper
            echo \Atareao\CrontabBlock::renderCrontabHelper(array(
                'expression' => '*/15 * * * *',
                'timezone' => 'UTC',
                'mode' => 'expert',
                'showCalendar' => false,
                'nextExecutions' => 5,
                'useSeconds' => false,
            ));
            ?>

            <section class="atareao-tool-seo-content" aria-label="Guia rapida de cron">
                <h2>Guia rapida de uso</h2>
                <h3>1. Escribe o selecciona una expresion</h3>
                <p>Introduce una expresion cron de 5 campos (minuto, hora, dia del mes, mes, dia de la semana) o activa la opcion <strong>Incluir segundos</strong> para usar 6 campos. Tambien puedes usar el constructor visual (modo Asistente) o escribir en espanol (modo Natural).</p>

                <h3>2. Revisa el analisis</h3>
                <p>La herramienta te muestra la descripcion en lenguaje natural, las proximas ejecuciones con zona horaria, y un analisis de riesgos que detecta anti-patrones como ejecuciones cada minuto, conflictos con cambio de hora o frecuencias excesivas.</p>

                <h3>3. Visualiza el calendario termico</h3>
                <p>Activa el calendario termico para ver la frecuencia de ejecucion de cada dia del ano con un heatmap de colores (verde = pocas, rojo = muchas ejecuciones).</p>

                <h3>4. Prueba el simulador What-If</h3>
                <p>Modifica la expresion y compara las estadisticas (ejecuciones/dia, /semana, /mes) antes y despues del cambio. Ideal para optimizar horarios sin romper lo que funciona.</p>

                <h3>5. Exporta a tu plataforma</h3>
                <p>Exporta la expresion a systemd timer, Kubernetes CronJob, AWS EventBridge, AWS CloudWatch, GitHub Actions, Ansible, iCal o SQL. Elije el formato que mejor se adapte a tu infraestructura.</p>

                <h3>6. Comparte el resultado</h3>
                <p>Usa <strong>Copiar enlace</strong> para generar una URL compartible con la expresion y timezone, o <strong>Compartir como imagen</strong> para descargar una tarjeta PNG con codigo QR.</p>
            </section>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de cron">
                <h2>Preguntas frecuentes</h2>
                <h3>Que formato cron usa esta herramienta</h3>
                <p>Usa cron estandar de 5 campos: minuto, hora, dia del mes, mes y dia de la semana. Tambien soporta 6 campos con segundos (opcional), util para schedulers como Java Quartz o Spring. Activa la opcion <strong>Incluir segundos</strong> en la barra de ajustes.</p>

                <h3>Que alias cron reconoce</h3>
                <p>Admite @hourly, @daily, @weekly, @monthly, @yearly, @annually y @reboot. Los alias se expanden automaticamente a su expresion equivalente de 5 campos.</p>

                <h3>Para que sirve el constructor visual</h3>
                <p>El modo <strong>Asistente</strong> permite seleccionar cada campo con desplegables, ideal para quienes no recuerdan la sintaxis exacta. Cuando activas segundos, aparecen 6 selectores en lugar de 5.</p>

                <h3>Funciona el lenguaje natural</h3>
                <p>Si, en el modo <strong>Natural</strong> escribe frases como "cada 15 minutos de lunes a viernes" o "a las 9 de la mañana cada dia" y la herramienta las convertira automaticamente a una expresion cron valida.</p>

                <h3>Que es el calendario termico</h3>
                <p>Es un heatmap que muestra la frecuencia de ejecucion de cada dia del ano. Los dias con mas ejecuciones aparecen en rojo intenso, los dias sin ejecuciones en verde claro. Util para detectar picos de carga y distribuir tareas uniformemente.</p>

                <h3>Que es el simulador What-If</h3>
                <p>Permite modificar la expresion y comparar estadisticas (ejecuciones/dia, /semana, /mes) entre la version actual y la modificada, antes de aplicar el cambio definitivo.</p>

                <h3>Para que sirve el diagrama de Gantt</h3>
                <p>Muestra las proximas ejecuciones como barras horizontales en una linea de tiempo. Puedes hacer zoom por hora, 4 horas, 24 horas o 7 dias para ver la distribucion temporal de las tareas programadas.</p>

                <h3>Como compartir el resultado</h3>
                <p>Con el boton <strong>Copiar enlace</strong> generas una URL con la expresion y el timezone. Con <strong>Compartir como imagen</strong> descargas una tarjeta PNG con la expresion, descripcion, proximas ejecuciones y codigo QR.</p>

                <h3>Errores frecuentes al escribir cron</h3>
                <p>Los fallos mas comunes son: usar 6 campos cuando se esperan 5 (o viceversa), confundir el orden de los campos (dia del mes vs dia de la semana), olvidar la zona horaria, y usar 31 en meses sin 31 dias. La herramienta detecta y advierte de todos estos errores.</p>

                <h3>Que plataformas de export soporta</h3>
                <p>Puedes exportar a: systemd timer (OnCalendar=), Kubernetes CronJob (YAML), AWS EventBridge, AWS CloudWatch, GitHub Actions, Ansible cron, iCal (.ics) y SQL (WHERE clause). Cada formato se adapta a la sintaxis especifica de la plataforma.</p>

                <h3>Casos de uso habituales</h3>
                <p>Esta herramienta es util para planificar tareas de backup, renovaciones de certificados SSL, limpieza de temporales, envios de reportes, reinicios programados, y cualquier tarea periodica en servidores Linux, Kubernetes o entornos cloud.</p>

                <h3>Buenas practicas en produccion</h3>
                <p>Antes de activar una tarea: (1) valida la expresion con el analizador, (2) confirma la zona horaria, (3) revisa las proximas ejecuciones para evitar ventanas conflictivas, (4) usa el analisis de riesgos para detectar problemas como ejecuciones cada minuto, (5) prueba con el simulador What-If antes de aplicar, y (6) distribuye las tareas en el tiempo para evitar picos de carga.</p>
            </section>
        </div>
    </article>
</main>

<?php
get_footer();