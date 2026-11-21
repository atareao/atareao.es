<?php
/**
 * Lista verificada de los comandos WP-CLI del plugin y guardia de `@synopsis`.
 *
 * WP-CLI resuelve la ayuda de un comando (`wp <comando> --help`) desde el
 * **docblock del callback** que se pasa a `WP_CLI::add_command()`, no desde el
 * cuerpo del método. Sin un tag `@synopsis` en ese docblock, la ayuda muestra
 * el nombre y la descripción pero **ningún flag**, así que `--dry-run` —el que
 * evita que un comando escriba cientos de filas— queda invisible para quien
 * consulta la ayuda antes de ejecutar.
 *
 * Este fichero es la **única fuente de verdad** de los comandos que el plugin
 * registra, de su callback y de la interfaz (argumentos posicionales y flags)
 * que deben anunciar. La guardia (`wp_cli_synopsis_violations()`) se ejecuta
 * **sin WP-CLI instalado**, como en CI: lee la fuente del plugin y comprueba
 * tres cosas por comando:
 *
 * 1. que el fichero registra ese comando con ese callback;
 * 2. que el docblock inmediatamente anterior al callback contiene el
 *    `@synopsis` esperado (los comandos sin args ni flags no lo exigen);
 * 3. que los flags del `@synopsis` coinciden **exactamente** con los que el
 *    callback lee en su cuerpo (`$assoc['…']` / `$assoc_args['…']`): ni falta
 *    uno leído ni sobra uno no leído.
 *
 * @package Atareao\Tests
 */

// phpcs:disable PSR1.Files.SideEffects
// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * Lista verificada `comando → callback → @synopsis esperado`.
 *
 * El campo `synopsis` vacío marca un comando sin argumentos ni flags: no se le
 * exige `@synopsis` y la guardia solo comprueba que su callback no lea ningún
 * flag (si lo leyera, la lista estaría incompleta).
 *
 * @return array<int,array{command:string,file:string,callback:string,synopsis:string}>
 */
function wp_cli_synopsis_policy()
{
    return array(
        array(
            'command'  => 'atareao-author canonicalize',
            'file'     => 'includes/class-author-canonical.php',
            'callback' => 'cliCanonicalize',
            'synopsis' => '[--dry-run]',
        ),
        array(
            'command'  => 'atareao-pocketid bind-sub',
            'file'     => 'includes/class-pocketid-login.php',
            'callback' => 'cliBindSub',
            'synopsis' => '<user> <sub>',
        ),
        array(
            'command'  => 'atareao-pocketid list-bindings',
            'file'     => 'includes/class-pocketid-login.php',
            'callback' => 'cliListBindings',
            'synopsis' => '',
        ),
        array(
            'command'  => 'atareao podcast-audio-sync',
            'file'     => 'includes/class-podcast-audio.php',
            'callback' => 'cliSync',
            'synopsis' => '[--dry-run] [--missing] [--limit=<n>] [--format=<table|json>]',
        ),
        array(
            'command'  => 'atareao podcast-duration-backfill',
            'file'     => 'includes/class-podcast-duration.php',
            'callback' => 'cliBackfill',
            'synopsis' => '[--dry-run] [--limit=<n>] [--source=<ruta>]',
        ),
        array(
            'command'  => 'atareao podcast-audio-length',
            'file'     => 'includes/class-podcast-feed.php',
            'callback' => 'cliWarmAudioLengths',
            'synopsis' => '',
        ),
        array(
            'command'  => 'atareao podcast-guid-map',
            'file'     => 'includes/class-podcast-guid.php',
            'callback' => 'cliMap',
            'synopsis' => '[--dry-run] [--limit=<n>]',
        ),
        array(
            'command'  => 'atareao podcast-guid-import-missing',
            'file'     => 'includes/class-podcast-guid.php',
            'callback' => 'cliImportMissing',
            'synopsis' => '[--dry-run] [--author=<id>]',
        ),
        array(
            'command'  => 'atareao youtube-video backfill',
            'file'     => 'includes/class-youtube-video.php',
            'callback' => 'cliBackfill',
            'synopsis' => '--file=<ruta> [--dry-run] [--force]',
        ),
    );
}

/**
 * Docblock inmediatamente anterior a un método, leyendo la fuente.
 *
 * Se apoya en `token_get_all()` para que un docblock comentado o una cadena con
 * el mismo texto no cuenten. Si el método no existe, devuelve `''`.
 *
 * @param string $source   Código fuente del fichero.
 * @param string $callback Nombre del método.
 * @return string Docblock, o `''`.
 */
function wp_cli_synopsis_docblock($source, $callback)
{
    $tokens = token_get_all($source);
    $total  = count($tokens);

    for ($i = 0; $i < $total; $i++) {
        if (!is_array($tokens[$i]) || T_FUNCTION !== $tokens[$i][0]) {
            continue;
        }

        $j = $i + 1;
        while ($j < $total && is_array($tokens[$j]) && T_WHITESPACE === $tokens[$j][0]) {
            $j++;
        }
        if ($j >= $total || !is_array($tokens[$j]) || T_STRING !== $tokens[$j][0] || $callback !== $tokens[$j][1]) {
            continue;
        }

        for ($k = $i - 1; $k >= 0; $k--) {
            if (!is_array($tokens[$k])) {
                break;
            }
            $id = $tokens[$k][0];
            if (T_WHITESPACE === $id
                || T_STATIC === $id
                || T_PUBLIC === $id
                || T_PRIVATE === $id
                || T_PROTECTED === $id
                || T_ABSTRACT === $id
                || T_FINAL === $id
            ) {
                continue;
            }
            if (T_DOC_COMMENT === $id) {
                return $tokens[$k][1];
            }
            break;
        }

        return '';
    }

    return '';
}

/**
 * Cuerpo ejecutable de un método, sin comentarios.
 *
 * @param string $source   Código fuente del fichero.
 * @param string $callback Nombre del método.
 * @return string Cuerpo con llaves, o `''` si no existe.
 */
function wp_cli_synopsis_body($source, $callback)
{
    $tokens = token_get_all($source);
    $total  = count($tokens);

    for ($i = 0; $i < $total; $i++) {
        if (!is_array($tokens[$i]) || T_FUNCTION !== $tokens[$i][0]) {
            continue;
        }

        $j = $i + 1;
        while ($j < $total && is_array($tokens[$j]) && T_WHITESPACE === $tokens[$j][0]) {
            $j++;
        }
        if ($j >= $total || !is_array($tokens[$j]) || T_STRING !== $tokens[$j][0] || $callback !== $tokens[$j][1]) {
            continue;
        }

        while ($j < $total && '{' !== $tokens[$j]) {
            $j++;
        }

        $depth = 0;
        $body  = '';
        for (; $j < $total; $j++) {
            if (is_array($tokens[$j]) && (T_COMMENT === $tokens[$j][0] || T_DOC_COMMENT === $tokens[$j][0])) {
                continue;
            }
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            $body .= $text;
            if ('{' === $text) {
                $depth++;
            } elseif ('}' === $text) {
                $depth--;
                if (0 === $depth) {
                    return $body;
                }
            }
        }

        return '';
    }

    return '';
}

/**
 * Flags anunciados por un `@synopsis`, sin el prefijo `--`.
 *
 * @param string $synopsis Synopsis, p. ej. `[--dry-run] [--limit=<n>]`.
 * @return string[] Nombres de flag ordenados y sin duplicados.
 */
function wp_cli_synopsis_flags($synopsis)
{
    preg_match_all('/--[a-z0-9][a-z0-9-]*/i', (string) $synopsis, $matches);
    $flags = array();
    foreach ($matches[0] as $flag) {
        $flags[] = substr($flag, 2);
    }
    $flags = array_values(array_unique($flags));
    sort($flags);

    return $flags;
}

/**
 * Flags que un callback lee de `$assoc` o `$assoc_args`.
 *
 * @param string $body Cuerpo ejecutable del callback.
 * @return string[] Nombres de flag ordenados y sin duplicados.
 */
function wp_cli_synopsis_read_flags($body)
{
    preg_match_all('/\$(?:assoc|assoc_args)\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]/', (string) $body, $matches);
    $flags = array_values(array_unique($matches[1]));
    sort($flags);

    return $flags;
}

/**
 * Comandos que incumplen la lista verificada.
 *
 * @param string        $plugin_dir Directorio del plugin `atareao-functionality`.
 * @param array|null    $policy     Política a aplicar (por defecto, la verificada).
 * @param callable|null $reader     Lector `function(string $relative): string|false`
 *                                  inyectable para las pruebas de la guardia.
 * @return string[] Infracciones legibles, sin duplicados.
 */
function wp_cli_synopsis_violations($plugin_dir, $policy = null, $reader = null)
{
    $policy = is_array($policy) ? $policy : wp_cli_synopsis_policy();
    if (!is_callable($reader)) {
        $reader = function ($relative) use ($plugin_dir) {
            $path = rtrim((string) $plugin_dir, '/') . '/' . ltrim((string) $relative, '/');

            return is_readable($path) ? (string) file_get_contents($path) : false;
        };
    }

    $violations = array();

    foreach ($policy as $entry) {
        $command  = (string) $entry['command'];
        $callback = (string) $entry['callback'];
        $synopsis = trim((string) $entry['synopsis']);
        $source   = call_user_func($reader, (string) $entry['file']);

        if (!is_string($source) || '' === $source) {
            $violations[] = $command . ': no se pudo leer ' . (string) $entry['file'];

            continue;
        }

        $pattern = '/add_command\s*\(\s*[\'"]' . preg_quote($command, '/') . '[\'"]\s*,\s*array\s*\(\s*__CLASS__\s*,\s*[\'"]'
            . preg_quote($callback, '/') . '[\'"]\s*\)/';
        if (1 !== preg_match($pattern, $source)) {
            $violations[] = $command . ': no se registra con ' . $callback . ' en ' . (string) $entry['file'];
        }

        $body = wp_cli_synopsis_body($source, $callback);
        $read = wp_cli_synopsis_read_flags($body);

        if ('' === $synopsis) {
            if (!empty($read)) {
                $violations[] = $command . ': lee flags (' . implode(', ', $read) . ') sin @synopsis';
            }

            continue;
        }

        $docblock   = preg_replace('/\s+/', ' ', wp_cli_synopsis_docblock($source, $callback));
        $declared   = wp_cli_synopsis_flags($synopsis);
        $normalised = trim((string) $docblock);
        if (false === strpos($normalised, '@synopsis ' . $synopsis)) {
            $violations[] = $command . ': falta "@synopsis ' . $synopsis . '"';

            continue;
        }

        if ($declared !== $read) {
            $violations[] = $command . ': flags no coinciden (synopsis: [' . implode(', ', $declared)
                . '], leídos: [' . implode(', ', $read) . '])';
        }
    }

    return array_values(array_unique($violations));
}
