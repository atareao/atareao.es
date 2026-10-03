<?php
/**
 * Hub de ajustes «Atareao»
 *
 * Punto de entrada único en el menú Ajustes de wp-admin que reúne en cinco
 * pestañas (`matrix`, `pocketid`, `umami`, `mastodon` y `tema`) la configuración
 * de los módulos Matrix, PocketID, Analítica, Mastodon y Tema. El hub es el
 * dueño del registro de la página y del envoltorio (`.wrap`/`<h1>`) y delega el
 * contenido de cada pestaña en el render público del módulo correspondiente.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hub de ajustes «Atareao».
 */
class Settings
{
    /**
     * Slug de la página del hub.
     */
    public const PAGE_SLUG = 'atareao-settings';

    /**
     * Inicializar: engancha el registro de la página. Idempotente.
     *
     * @return void
     */
    public static function init()
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;

        add_action('admin_menu', array(__CLASS__, 'registerPage'));
    }

    /**
     * Pestañas del hub, en orden: slug => etiqueta y render del módulo.
     *
     * @return array<string, array{label: string, callback: callable}>
     */
    public static function tabs()
    {
        return array(
            'matrix' => array(
                'label' => __('Matrix', 'atareao-functionality'),
                'callback' => array(MatrixConfig::class, 'renderConfigPage'),
            ),
            'pocketid' => array(
                'label' => __('PocketID', 'atareao-functionality'),
                'callback' => array(PocketIDLogin::class, 'renderSettingsPage'),
            ),
            'umami' => array(
                'label' => __('Umami', 'atareao-functionality'),
                'callback' => array(Analytics::class, 'renderSettingsPage'),
            ),
            'mastodon' => array(
                'label' => __('Mastodon', 'atareao-functionality'),
                'callback' => array(MastodonReplies::class, 'renderSettingsPage'),
            ),
            'tema' => array(
                'label' => __('Tema', 'atareao-functionality'),
                'callback' => array(ThemeOptions::class, 'renderOptionsPage'),
            ),
        );
    }

    /**
     * Registrar el punto de entrada único en Ajustes.
     *
     * @return void
     */
    public static function registerPage()
    {
        add_options_page(
            __('Ajustes de Atareao', 'atareao-functionality'),
            __('Atareao', 'atareao-functionality'),
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'renderPage')
        );
    }

    /**
     * Resolver la pestaña activa contra la whitelist de pestañas.
     *
     * @return string Slug de la pestaña activa; `matrix` si falta o es desconocida.
     */
    public static function activeTab()
    {
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

        return array_key_exists($tab, self::tabs()) ? $tab : 'matrix';
    }

    /**
     * URL canónica de una pestaña del hub.
     *
     * @param string $tab Slug de la pestaña.
     * @return string
     */
    public static function tabUrl($tab)
    {
        return add_query_arg(
            array(
                'page' => self::PAGE_SLUG,
                'tab' => $tab,
            ),
            admin_url('options-general.php')
        );
    }

    /**
     * Renderizar el hub: envoltorio único, pestañas y delegación.
     *
     * @return void
     */
    public static function renderPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tabs = self::tabs();
        $active = self::activeTab();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Ajustes de Atareao', 'atareao-functionality'); ?></h1>
            <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Pestañas de configuración de Atareao', 'atareao-functionality'); ?>">
                <?php foreach ($tabs as $slug => $tab) : ?>
                    <?php $is_active = ($slug === $active); ?>
                    <a class="nav-tab<?php echo $is_active ? ' nav-tab-active' : ''; ?>"
                        href="<?php echo esc_url(self::tabUrl($slug)); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
                        <?php echo esc_html($tab['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <?php call_user_func($tabs[$active]['callback']); ?>
        </div>
        <?php
    }
}
