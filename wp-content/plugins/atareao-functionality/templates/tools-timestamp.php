<?php
/**
 * Tools - Timestamp Converter
 *
 * Route: /tools/timestamp
 * Refactored to use Gutenberg block atareao/timestamp-helper
 * instead of inline JS+CSS.
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/timestamp/');
$tool_title = 'Timestamp Converter: Unix epoch a fecha online | ' . get_bloginfo('name');
$tool_description = 'Convierte Unix timestamp a fecha UTC/local, ISO 8601 y formato legible. Tambien transforma fecha y hora a epoch segundos y milisegundos.';
$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'WebApplication',
            'name' => 'Timestamp Converter',
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
                    'name' => 'Que convierte esta herramienta de timestamp?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Convierte epoch Unix en segundos o milisegundos a fecha legible y tambien permite obtener epoch a partir de fecha y hora.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Como saber si un timestamp esta en segundos o milisegundos?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Normalmente los timestamps de 10 digitos son segundos y los de 13 digitos son milisegundos. La herramienta detecta automaticamente el formato.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Puedo compartir una conversion concreta?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si. El boton Copiar enlace guarda timestamp, unidad y zona horaria en la URL.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Para que se usa en desarrollo y DevOps?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Es muy util para depurar logs, trazas de APIs, expiraciones de tokens y eventos de monitorizacion.',
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

// Enqueue block assets for standalone page
if (function_exists('Atareao\TimestampBlock')) {
    Atareao\TimestampBlock::registerAssets();
    wp_enqueue_script('atareao-timestamp-frontend');
    wp_enqueue_style('atareao-timestamp-block-style');
}
?>

<main id="primary" class="site-main">
    <article id="post-tools-timestamp" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">Timestamp Converter</h1>
            <?php atareao_tools_render_breadcrumb('timestamp'); ?>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Convierte Unix timestamp a fecha legible y transforma fecha/hora a epoch para depurar logs, APIs y eventos.
                    Soporta auto-deteccion de segundos y milisegundos, 17 zonas horarias, historial local y URLs compartibles.
                </p>
            </div>

            <?php echo do_blocks('<!-- wp:atareao/timestamp-helper /-->'); ?>

            <section class="atareao-tool-seo-content" aria-label="Guia rapida de timestamp">
                <h2>Guia rapida de uso</h2>
                <h3>1. Introduce o pega un timestamp</h3>
                <p>La herramienta detecta automaticamente si son segundos (10 digitos) o milisegundos (13 digitos) y muestra la conversion al instante.</p>

                <h3>2. Ajusta zona horaria de salida</h3>
                <p>Compara UTC con zona local para correlacionar eventos entre aplicaciones, servidores y monitorizacion.</p>

                <h3>3. Convierte en ambos sentidos</h3>
                <p>Pasa de Unix a fecha legible y de fecha a epoch para depurar APIs, logs y expiraciones. El historial guarda tus ultimas 20 conversiones.</p>
            </section>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de timestamp">
                <h2>Preguntas frecuentes</h2>
                <h3>Segundos o milisegundos</h3>
                <p>Como referencia rapida, 10 digitos suele indicar segundos y 13 digitos suele indicar milisegundos. La herramienta lo detecta automaticamente.</p>

                <h3>Usos habituales</h3>
                <p>Se usa para interpretar logs de backend, revisar expiraciones de JWT y validar eventos temporales en bases de datos y colas.</p>

                <h3>UTC frente a hora local</h3>
                <p>En sistemas distribuidos conviene trabajar en UTC y convertir a local solo para visualizacion y soporte.</p>

                <h3>Compartir conversiones</h3>
                <p>Con Copiar enlace puedes enviar el mismo caso a otro miembro del equipo para revisar resultados de forma consistente.</p>

                <h3>Historial de conversiones</h3>
                <p>Las ultimas 20 conversiones se guardan localmente en el navegador. Puedes hacer clic en cualquier entrada del historial para restaurarla.</p>
            </section>
        </div>
    </article>
</main>

<?php get_footer(); ?>