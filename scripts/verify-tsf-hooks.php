<?php
/**
 * Verifica que los hooks de The SEO Framework que registra el plugin existen
 * de verdad en la copia de TSF instalada.
 *
 * La lista verificada y la lógica viven en `tests/t10-residuals/verified-hooks.php`
 * (fuente única de verdad, también usada por la guardia de CI). Este script es
 * el paso ejecutable de verificación en producción: comprueba que cada hook de
 * la lista aparece como `apply_filters()` en el código de TSF y reporta los que
 * falten, con código de salida distinto de cero.
 *
 * Uso:
 *   php scripts/verify-tsf-hooks.php /ruta/a/wp-content/plugins/autodescription
 *
 * En producción (solo lectura, no escribe nada):
 *   ssh atareao 'docker exec wpcli php /ruta/al/repo/scripts/verify-tsf-hooks.php \
 *       /var/www/html/wp-content/plugins/autodescription'
 *
 * @package Atareao\Scripts
 */

// phpcs:disable PSR1.Files.SideEffects

require_once dirname(__DIR__) . '/tests/t10-residuals/verified-hooks.php';

$plugin_dir = isset($argv[1]) ? (string) $argv[1] : '';

if ('' === $plugin_dir) {
    fwrite(STDERR, "Uso: php scripts/verify-tsf-hooks.php <directorio-del-plugin-autodescription>\n");
    exit(2);
}

$policy = tsf_hook_policy();
$result = tsf_verify_installed_hooks($plugin_dir, $policy);

echo 'TSF esperado: ' . $policy['version'] . "\n";
echo 'Directorio: ' . $plugin_dir . "\n";

if ('' !== $result['error']) {
    fwrite(STDERR, 'ERROR: ' . $result['error'] . "\n");
    exit(2);
}

foreach ($result['found'] as $hook => $found) {
    printf("  %s %s\n", $found ? 'OK  ' : 'FALTA', $hook);
}

if (array() !== $result['missing']) {
    fwrite(
        STDERR,
        sprintf(
            "\n%d de %d hooks no aparecen como apply_filters() en TSF: %s\n",
            count($result['missing']),
            $result['checked'],
            implode(', ', $result['missing'])
        )
    );
    exit(1);
}

printf("\n%d/%d hooks verificados.\n", $result['checked'], $result['checked']);
exit(0);