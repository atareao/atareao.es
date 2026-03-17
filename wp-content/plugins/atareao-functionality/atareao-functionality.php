<?php
/**
 * Plugin Name: Atareao Functionality
 * Plugin URI: https://atareao.es
 * Description: Plugin con todas las funcionalidades personalizadas para Atareao (Custom Post Types, Taxonomías y más)
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Atareao
 * Author URI: https://atareao.es
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: atareao-functionality
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('ATAREAO_PLUGIN_VERSION', '1.0.0');
define('ATAREAO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ATAREAO_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Cargar archivos del plugin
 */
require_once ATAREAO_PLUGIN_DIR . 'includes/class-post-types.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-taxonomies.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-metaboxes.php';
require_once ATAREAO_PLUGIN_DIR . 'includes/class-podcast-block.php';

/**
 * Inicializar el plugin
 */
function atareao_functionality_init() {
    // Inicializar Custom Post Types
    Atareao_Post_Types::init();
    
    // Inicializar Taxonomías
    Atareao_Taxonomies::init();
    
    // Inicializar Metaboxes
    Atareao_Metaboxes::init();
    
    // Inicializar Bloque de Podcast
    Atareao_Podcast_Block::init();
}
add_action('init', 'atareao_functionality_init');

/**
 * Activación del plugin
 */
function atareao_functionality_activate() {
    // Registrar post types y taxonomías
    Atareao_Post_Types::init();
    Atareao_Taxonomies::init();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'atareao_functionality_activate');

/**
 * Desactivación del plugin
 */
function atareao_functionality_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'atareao_functionality_deactivate');

/**
 * Cargar traducciones
 */
function atareao_functionality_load_textdomain() {
    load_plugin_textdomain('atareao-functionality', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'atareao_functionality_load_textdomain');
