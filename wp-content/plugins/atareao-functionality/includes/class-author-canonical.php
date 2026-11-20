<?php
/**
 * Autor canónico — nicename heredado y paso de datos idempotente.
 *
 * El autor del sitio (`display_name = atareao`) quedó con un `user_nicename`
 * heredado (`yyyxfsgj`) que hace que `/author/atareao/` responda 404 y que el
 * archivo solo exista bajo una URL ilegible. Este módulo aporta el paso de
 * datos que corrige el nicename y el subcomando WP-CLI que lo ejecuta:
 *
 *     wp atareao-author canonicalize [--dry-run]
 *
 * Es idempotente: si el nicename ya es `atareao` no escribe nada. Si otro
 * usuario ocupa ya ese nicename, falla sin tocar datos. La redirección del path
 * heredado vive en `SEO::redirectLegacyAuthorNicename()`.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class AuthorCanonical
{
    /**
     * Autor cuyo nicename se canoniza.
     */
    public const AUTHOR_ID = 3;

    /**
     * Nicename canónico del autor.
     */
    public const NICENAME = 'atareao';

    /**
     * Nicename heredado que provoca el 404 en `/author/atareao/`.
     */
    public const LEGACY_NICENAME = 'yyyxfsgj';

    /**
     * Registra el subcomando de WP-CLI.
     *
     * @return void
     */
    public static function init()
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('atareao-author canonicalize', array(__CLASS__, 'cliCanonicalize'));
        }
    }

    /**
     * Fija `user_nicename = atareao` para el autor canónico.
     *
     * Devuelve un informe con `needed` (el dato difiere), `changed` (se escribió
     * en esta ejecución), `previous` (valor anterior) y `error` (motivo del
     * fallo, si lo hubo). Con `$dry_run` no escribe.
     *
     * @param bool $dry_run Si es `true`, informa sin escribir.
     * @return array{ok:bool,id:int,nicename:string,previous:string,needed:bool,changed:bool,dry_run:bool,error:string}
     */
    public static function canonicalize($dry_run = false)
    {
        $dry_run = (bool) $dry_run;
        $report = array(
            'ok'       => false,
            'id'       => self::AUTHOR_ID,
            'nicename' => self::NICENAME,
            'previous' => '',
            'needed'   => false,
            'changed'  => false,
            'dry_run'  => $dry_run,
            'error'    => '',
        );

        $user = get_userdata(self::AUTHOR_ID);
        if (!is_object($user) || empty($user->ID)) {
            $report['error'] = 'No existe el autor ' . self::AUTHOR_ID . '.';

            return $report;
        }

        $report['previous'] = (string) $user->user_nicename;
        if (self::NICENAME === $report['previous']) {
            $report['ok'] = true;

            return $report;
        }

        $report['needed'] = true;

        $holder = get_user_by('slug', self::NICENAME);
        if (is_object($holder) && (int) $holder->ID !== self::AUTHOR_ID) {
            $report['error'] = 'El nicename «' . self::NICENAME . '» ya lo usa el usuario '
                . (int) $holder->ID . '.';

            return $report;
        }

        if ($dry_run) {
            $report['ok'] = true;

            return $report;
        }

        $updated = wp_update_user(array(
            'ID'            => self::AUTHOR_ID,
            'user_nicename' => self::NICENAME,
        ));
        if (is_wp_error($updated)) {
            $report['error'] = $updated->get_error_message();

            return $report;
        }

        $report['ok'] = true;
        $report['changed'] = true;

        return $report;
    }

    /**
     * `wp atareao-author canonicalize [--dry-run]`.
     *
     * @param array $args       Argumentos posicionales.
     * @param array $assoc_args Argumentos con nombre.
     * @return void
     */
    public static function cliCanonicalize($args = array(), $assoc_args = array())
    {
        $assoc_args = is_array($assoc_args) ? $assoc_args : array();
        $dry_run = isset($assoc_args['dry-run']);
        $report = self::canonicalize($dry_run);

        if (!$report['ok']) {
            \WP_CLI::error($report['error']);

            return;
        }

        if (!$report['needed']) {
            \WP_CLI::success(
                'El autor ' . $report['id'] . ' ya tiene el nicename «' . $report['nicename'] . '».'
            );

            return;
        }

        $message = 'El autor ' . $report['id'] . ' pasa de «' . $report['previous']
            . '» a «' . $report['nicename'] . '».';

        if ($dry_run) {
            \WP_CLI::log('Dry-run: ' . $message);

            return;
        }

        \WP_CLI::success($message);
    }
}
