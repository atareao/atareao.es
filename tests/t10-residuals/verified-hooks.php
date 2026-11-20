<?php
/**
 * Lista verificada de hooks de The SEO Framework (TSF) y guardia anti-falso-verde.
 *
 * El change `seo-visibility` enganchó `the_seo_framework_title` y
 * `the_seo_framework_description`, que **no existen** en TSF 5.2.1: los tests
 * daban verde porque el doble de `add_filter()` no comprueba que el hook exista.
 * Este fichero es la única fuente de verdad de los hooks que el plugin puede
 * registrar, con la referencia `fichero:línea` donde TSF 5.2.1 los dispara.
 *
 * La guardia (`tsf_hook_violations()`) se ejecuta **sin TSF instalado**, como en
 * CI: compara los hooks `the_seo_framework_*` registrados por el plugin con la
 * lista. La verificación (`tsf_verify_installed_hooks()`) es el paso que sí
 * necesita el plugin instalado y comprueba que cada hook de la lista aparece en
 * su código como `apply_filters()`.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * Hooks verificados y hooks prohibidos.
 *
 * @return array{version:string,verified:array<string,string>,prohibited:string[]}
 */
function tsf_hook_policy()
{
    return array(
        'version'    => '5.2.1',
        'verified'   => array(
            'the_seo_framework_title_from_generation' => 'inc/classes/meta/title.class.php:237-241',
            'the_seo_framework_generated_description' => 'inc/classes/meta/description.class.php:176-181',
            'the_seo_framework_use_title_branding'    => 'inc/classes/meta/title/conditions.class.php:172-176',
            'the_seo_framework_schema_graph_data'     => 'inc/classes/meta/schema.class.php:143',
            'the_seo_framework_sitemap_exclude_ids'   => 'inc/classes/sitemap/utils.class.php:97',
        ),
        'prohibited' => array(
            'the_seo_framework_title',
            'the_seo_framework_description',
        ),
    );
}

/**
 * Hooks registrados que no pertenecen a la lista verificada.
 *
 * Solo inspecciona los hooks con el prefijo `the_seo_framework_`; el resto de
 * hooks del plugin (núcleo, tema, WooCommerce…) quedan fuera del contrato.
 *
 * @param string[] $registered_hooks Hooks registrados por el plugin.
 * @param array|null $policy         Política a aplicar (por defecto, la verificada).
 * @return string[] Hooks infractores, sin duplicados.
 */
function tsf_hook_violations($registered_hooks, $policy = null)
{
    $policy = is_array($policy) ? $policy : tsf_hook_policy();
    $violations = array();

    foreach ((array) $registered_hooks as $hook) {
        $hook = (string) $hook;
        if (0 !== strpos($hook, 'the_seo_framework_')) {
            continue;
        }

        if (in_array($hook, $policy['prohibited'], true)
            || !array_key_exists($hook, $policy['verified'])
        ) {
            $violations[] = $hook;
        }
    }

    return array_values(array_unique($violations));
}

/**
 * Comprueba que cada hook de la lista aparece en el código de TSF instalado.
 *
 * @param string     $plugin_dir Directorio del plugin `autodescription`.
 * @param array|null $policy     Política a aplicar (por defecto, la verificada).
 * @return array{checked:int,missing:string[],found:array<string,bool>,error:string}
 */
function tsf_verify_installed_hooks($plugin_dir, $policy = null)
{
    $policy = is_array($policy) ? $policy : tsf_hook_policy();
    $result = array('checked' => 0, 'missing' => array(), 'found' => array(), 'error' => '');

    if (!is_dir($plugin_dir)) {
        $result['error'] = 'no existe el directorio: ' . $plugin_dir;

        return $result;
    }

    $sources = '';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($plugin_dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || 'php' !== strtolower($file->getExtension())) {
            continue;
        }
        $sources .= "\n" . (string) file_get_contents($file->getPathname());
    }

    foreach (array_keys($policy['verified']) as $hook) {
        $result['checked']++;
        $pattern = '/apply_filters\(\s*[\'"]' . preg_quote($hook, '/') . '[\'"]/';
        $found = 1 === preg_match($pattern, $sources);
        $result['found'][$hook] = $found;
        if (!$found) {
            $result['missing'][] = $hook;
        }
    }

    return $result;
}