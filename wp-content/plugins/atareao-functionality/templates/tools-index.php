<?php
/**
 * Tools - Index
 *
 * Route: /tools
 */

if (!defined('ABSPATH')) {
    exit;
}

$tool_url = home_url('/tools/');
$tool_title = 'Herramientas online para sysadmin y desarrollo | ' . get_bloginfo('name');
$tool_description = 'Coleccion de herramientas tecnicas: cron, subnetting, regex y JSON. Acceso rapido en tabla con descripcion de cada utilidad.';
$catalog = atareao_tools_catalog();

$item_list = array();
$position = 1;
foreach ($catalog as $slug => $item) {
    $item_list[] = array(
        '@type' => 'ListItem',
        'position' => $position,
        'url' => home_url('/tools/' . $slug . '/'),
        'name' => $item['label'],
    );
    $position++;
}

$tool_schema = array(
    '@context' => 'https://schema.org',
    '@graph' => array(
        array(
            '@type' => 'CollectionPage',
            'name' => 'Tools',
            'url' => $tool_url,
            'description' => $tool_description,
        ),
        array(
            '@type' => 'ItemList',
            'name' => 'Listado de herramientas',
            'itemListElement' => $item_list,
        ),
        array(
            '@type' => 'FAQPage',
            'mainEntity' => array(
                array(
                    '@type' => 'Question',
                    'name' => 'Que tipo de herramientas incluye esta pagina?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Incluye utilidades para tareas tecnicas frecuentes como cron, subnetting, expresiones regulares y validacion JSON.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Las herramientas son gratuitas?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'Si, las herramientas estan disponibles de forma gratuita.',
                    ),
                ),
                array(
                    '@type' => 'Question',
                    'name' => 'Se procesan datos en servidor?',
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => 'En general el procesamiento se realiza en navegador para agilizar uso y proteger la privacidad de datos de prueba.',
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
    <article id="post-tools-index" class="post type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title">Tools</h1>
        </header>

        <div class="entry-content atareao-contact-wrapper">
            <div class="atareao-page-entry-content">
                <p>
                    Directorio de herramientas para tareas de administracion de sistemas, desarrollo y diagnostico tecnico.
                </p>
            </div>

            <section class="tools-table-section" aria-label="Listado de herramientas">
                <div class="tools-table-wrap">
                    <table class="tools-table">
                        <thead>
                            <tr>
                                <th scope="col">Tool</th>
                                <th scope="col">Descripcion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($catalog as $slug => $item) : ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(home_url('/tools/' . $slug . '/')); ?>">
                                            <?php echo esc_html($item['label']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($item['description']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="atareao-tool-seo-content" aria-label="Preguntas frecuentes de tools">
                <h2>Preguntas frecuentes</h2>
                <h3>Que herramientas hay disponibles</h3>
                <p>Actualmente puedes acceder a utilidades de cron, subnetting, regex y JSON, con mejoras continuas.</p>

                <h3>Como elegir la herramienta adecuada</h3>
                <p>Usa la tabla para identificar rapidamente cada utilidad por nombre y descripcion de uso principal.</p>

                <h3>Hay soporte para movil</h3>
                <p>Si. La tabla se adapta con desplazamiento horizontal para mantener legibilidad en pantallas pequenas.</p>

                <h3>Se iran anadiendo nuevas tools</h3>
                <p>Si. Esta pagina centraliza el acceso para que cada nueva herramienta quede disponible de forma inmediata.</p>
            </section>
        </div>
    </article>
</main>

<style>
.tools-table-section {
    width: 80%;
    margin: 0 auto;
}

.tools-table-wrap {
    overflow-x: auto;
}

.tools-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}

.tools-table th,
.tools-table td {
    padding: 0.65rem 0.7rem;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
    color: #222222;
}

.tools-table th {
    font-weight: 700;
}

.tools-table td a {
    color: #0073aa;
    text-decoration: none;
    font-weight: 600;
}

.tools-table td a:hover {
    text-decoration: underline;
}

.atareao-tools-nav {
    width: 80%;
    margin: 0.75rem auto 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.atareao-tools-nav-title {
    font-weight: 600;
    color: #0073aa;
}

.atareao-tools-nav-item {
    display: inline-block;
    border: 1px solid #c3cfe2;
    border-radius: 999px;
    background: #f7fafd;
    color: #1f2937;
    text-decoration: none;
    padding: 0.35rem 0.7rem;
    font-size: 0.9rem;
}

.atareao-tools-nav-item:hover {
    filter: brightness(0.97);
}

.atareao-tools-nav-item.is-active {
    background: #0073aa;
    border-color: #0073aa;
    color: #ffffff;
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

[data-theme="dark"] .tools-table th,
[data-theme="dark"] .tools-table td {
    border-bottom-color: #334155;
    color: #e5e7eb;
}

[data-theme="dark"] .tools-table td a {
    color: #8bc3e6;
}

[data-theme="dark"] .atareao-tools-nav-title {
    color: #8bc3e6;
}

[data-theme="dark"] .atareao-tools-nav-item {
    background: #1d2538;
    border-color: #44506a;
    color: #e5e7eb;
}

[data-theme="dark"] .atareao-tools-nav-item.is-active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #ffffff;
}

@media (prefers-color-scheme: dark) {
    html:not([data-theme="light"]) .tools-table th,
    html:not([data-theme="light"]) .tools-table td {
        color: #e5e7eb;
    }

    html:not([data-theme="light"]) .tools-table td a {
        color: #8bc3e6;
    }
}
</style>

<?php
get_footer();
