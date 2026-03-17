<?php
/**
 * Script de depuración para verificar el registro del bloque
 * Ejecutar con: bash quadlets/wp eval-file debug-block.php
 */

// Obtener todos los bloques registrados
$registry = WP_Block_Type_Registry::get_instance();
$all_blocks = $registry->get_all_registered();

echo "=== BLOQUES REGISTRADOS ===\n\n";

// Buscar nuestro bloque
$found = false;
foreach ($all_blocks as $block_name => $block_type) {
    if (strpos($block_name, 'atareao') !== false || strpos($block_name, 'podcast') !== false) {
        echo "✅ Bloque encontrado: $block_name\n";
        echo "   Título: " . (isset($block_type->title) ? $block_type->title : 'N/A') . "\n";
        echo "   Categoría: " . (isset($block_type->category) ? $block_type->category : 'N/A') . "\n";
        
        if (isset($block_type->editor_script)) {
            echo "   Editor Script: " . $block_type->editor_script . "\n";
        }
        
        if (isset($block_type->editor_style)) {
            echo "   Editor Style: " . $block_type->editor_style . "\n";
        }
        
        if (isset($block_type->style)) {
            echo "   Style: " . $block_type->style . "\n";
        }
        
        echo "\n";
        $found = true;
    }
}

if (!$found) {
    echo "❌ No se encontró ningún bloque de Atareao registrado\n\n";
    
    echo "=== INFORMACIÓN DE DEPURACIÓN ===\n\n";
    
    // Verificar constantes
    echo "ATAREAO_PLUGIN_DIR: " . (defined('ATAREAO_PLUGIN_DIR') ? ATAREAO_PLUGIN_DIR : 'NO DEFINIDA') . "\n";
    echo "ATAREAO_PLUGIN_URL: " . (defined('ATAREAO_PLUGIN_URL') ? ATAREAO_PLUGIN_URL : 'NO DEFINIDA') . "\n";
    echo "ATAREAO_PLUGIN_VERSION: " . (defined('ATAREAO_PLUGIN_VERSION') ? ATAREAO_PLUGIN_VERSION : 'NO DEFINIDA') . "\n\n";
    
    // Verificar que la clase existe
    echo "Clase Atareao_Podcast_Block existe: " . (class_exists('Atareao_Podcast_Block') ? 'SÍ' : 'NO') . "\n\n";
    
    // Verificar archivos
    $block_json_path = ATAREAO_PLUGIN_DIR . 'assets/blocks/podcast-player/block.json';
    echo "Archivo block.json existe: " . (file_exists($block_json_path) ? 'SÍ' : 'NO') . "\n";
    echo "Ruta: $block_json_path\n\n";
    
    // Intentar leer el block.json
    if (file_exists($block_json_path)) {
        $block_json = file_get_contents($block_json_path);
        $block_data = json_decode($block_json, true);
        if ($block_data) {
            echo "block.json es válido\n";
            echo "Nombre del bloque en JSON: " . (isset($block_data['name']) ? $block_data['name'] : 'NO DEFINIDO') . "\n";
        } else {
            echo "❌ Error al parsear block.json: " . json_last_error_msg() . "\n";
        }
    }
}

echo "\n=== SCRIPTS REGISTRADOS ===\n\n";

// Verificar scripts registrados
global $wp_scripts;
if ($wp_scripts) {
    foreach ($wp_scripts->registered as $handle => $script) {
        if (strpos($handle, 'atareao') !== false || strpos($handle, 'podcast') !== false) {
            echo "✅ Script: $handle\n";
            echo "   Src: " . $script->src . "\n";
            echo "   Deps: " . implode(', ', $script->deps) . "\n\n";
        }
    }
}

echo "=== FIN DE LA DEPURACIÓN ===\n";
