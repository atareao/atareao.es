<?php
/**
 * Youtube video — backfill of the episode video URL from a mapping TSV.
 *
 * The `youtube_url` meta is the declarative channel the SEO/datos-estructurados
 * layer reads as a fallback when an episode does not embed the player in its
 * body (`SEO::resolveVideo()`). The episode↔video mapping is resolved **outside**
 * the site (repository `atareao/visibilidad`), so the site never calls the
 * YouTube API: it receives a TSV and fills the meta by `post_id`.
 *
 * The command is idempotent: a second run reports zero updates and changes no
 * metadata. `--dry-run` reports what it would do without writing and `--force`
 * rewrites an existing (possibly wrong) value. A row whose `post_id` does not
 * exist is warned about and skipped, never aborting the whole pass.
 *
 * Executed by WP-CLI
 * (`wp atareao youtube-video backfill --file=<tsv> [--dry-run] [--force]`).
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class YoutubeVideo
{
    /**
     * Episode meta key holding the video URL (single source in `SEO`).
     */
    public const META_KEY = SEO::YOUTUBE_URL_META;

    /**
     * Metadata key of the `post_id` column in the mapping TSV.
     */
    private const COLUMN_POST_ID = 'post_id';

    /**
     * Metadata key of the `videoId` column in the mapping TSV.
     */
    private const COLUMN_VIDEO_ID = 'videoId';

    /**
     * Wire the module: WP-CLI backfill command.
     *
     * @return void
     */
    public static function init(): void
    {
        if (defined('WP_CLI') && WP_CLI && class_exists('\WP_CLI')) {
            \WP_CLI::add_command('atareao youtube-video backfill', array(__CLASS__, 'cliBackfill'));
        }
    }

    /**
     * Fill `youtube_url` for every row of a mapping TSV.
     *
     * The TSV carries a header row; only `post_id` and `videoId` are read (the
     * rest —`slug`, `fecha_ep`, `num_video`, `fecha_video`, `titulo`— is
     * ignored). The URL is built from `videoId`, or used verbatim when the cell
     * already holds an `http(s)` URL. Writes go only through here, so the meta
     * never drifts; the pass is idempotent and never touches any other meta.
     *
     * @param array $options `file` (path), `dry_run` and `force` flags.
     * @return array{
     *     total:int,
     *     actualizados:int,
     *     sin_cambios:int,
     *     protegidos:int,
     *     sin_video:int,
     *     inexistentes:int,
     *     changes:array,
     *     avisos:string[],
     *     origin_error:string
     * }
     */
    public static function backfill(array $options = array()): array
    {
        $file  = isset($options['file']) ? (string) $options['file'] : '';
        $dry   = !empty($options['dry_run']);
        $force = !empty($options['force']);

        $report = array(
            'total'        => 0,
            'actualizados' => 0,
            'sin_cambios'  => 0,
            'protegidos'   => 0,
            'sin_video'    => 0,
            'inexistentes' => 0,
            'changes'      => array(),
            'avisos'       => array(),
            'origin_error' => '',
        );

        if ('' === $file || !is_file($file) || !is_readable($file)) {
            $report['origin_error'] = 'No se pudo leer el fichero de mapeo: ' . $file;

            return $report;
        }

        $rows = self::parseTsv($file);
        if (null === $rows) {
            $report['origin_error'] = 'El fichero de mapeo no tiene cabecera post_id/videoId: ' . $file;

            return $report;
        }

        foreach ($rows as $row) {
            $report['total']++;

            $post_id = (int) trim((string) ($row[self::COLUMN_POST_ID] ?? ''));
            $raw     = trim((string) ($row[self::COLUMN_VIDEO_ID] ?? ''));
            if ('' === $raw) {
                $report['sin_video']++;

                continue;
            }

            $url = self::buildUrl($raw);
            if ('' === $url) {
                $report['sin_video']++;
                $report['avisos'][] = 'post_id ' . $post_id
                    . ': el videoId «' . $raw . '» no es válido; se omite';

                continue;
            }

            if ($post_id <= 0 || !get_post($post_id) instanceof \WP_Post) {
                $report['inexistentes']++;
                $report['avisos'][] = 'post_id ' . $post_id . ' del mapeo no existe; se omite';

                continue;
            }

            $current = (string) get_post_meta($post_id, self::META_KEY, true);
            if ('' !== $current && $current === $url) {
                $report['sin_cambios']++;

                continue;
            }
            if ('' !== $current && !$force) {
                $report['protegidos']++;

                continue;
            }

            $report['changes'][] = array(
                'post_id' => $post_id,
                'from'    => $current,
                'to'      => $url,
            );
            if (!$dry) {
                update_post_meta($post_id, self::META_KEY, $url);
            }
            $report['actualizados']++;
        }

        return $report;
    }

    /**
     * Read a mapping TSV into rows keyed by the header column names.
     *
     * @param string $file Path to the TSV.
     * @return array<int,array<string,string>>|null Rows, or null when the
     *                                              header lacks the required
     *                                              columns.
     */
    private static function parseTsv(string $file): ?array
    {
        $content = file_get_contents($file);
        if (!is_string($content) || '' === $content) {
            return null;
        }

        // Un TSV exportado con BOM UTF-8 haría que la primera cabecera fuese
        // «\xEF\xBB\xBFpost_id» y no casaría con `post_id`.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $header  = null;
        $rows    = array();
        $lines   = preg_split('/\r\n|\r|\n/', $content);
        foreach ($lines as $line) {
            if ('' === trim((string) $line)) {
                continue;
            }

            $cells = explode("\t", (string) $line);
            if (null === $header) {
                $header = array_map('trim', $cells);

                continue;
            }

            $row = array();
            foreach ($header as $index => $name) {
                $row[$name] = isset($cells[$index]) ? trim($cells[$index]) : '';
            }
            $rows[] = $row;
        }

        if (!is_array($header)
            || !in_array(self::COLUMN_POST_ID, $header, true)
            || !in_array(self::COLUMN_VIDEO_ID, $header, true)
        ) {
            return null;
        }

        return $rows;
    }

    /**
     * Build the canonical video URL from a `videoId` cell.
     *
     * The cell must yield a valid YouTube identifier: a cell holding an
     * `http(s)` URL must be a YouTube URL (embed, watch or short form), from
     * which the identifier is extracted with `SEO::youtubeVideoId()`; a bare
     * cell must itself match `^[A-Za-z0-9_-]{6,}$`. Anything else returns ''
     * so the caller reports it as `sin_video` instead of writing garbage.
     *
     * @param string $raw Raw `videoId` cell.
     * @return string URL, or '' when it cannot be built.
     */
    private static function buildUrl(string $raw): string
    {
        if ('' === $raw) {
            return '';
        }

        if (1 === preg_match('#^https?://#i', $raw)) {
            $id = SEO::youtubeVideoId($raw);
        } elseif (1 === preg_match('#^[A-Za-z0-9_-]{6,}$#', $raw)) {
            $id = $raw;
        } else {
            $id = '';
        }

        if ('' === $id) {
            return '';
        }

        return esc_url_raw('https://www.youtube.com/watch?v=' . $id);
    }

    /**
     * WP-CLI callback for the youtube-video backfill.
     *
     * @synopsis --file=<ruta> [--dry-run] [--force]
     *
     * @param array $args  Positional arguments (unused).
     * @param array $assoc Associative options: `file`, `dry-run` and `force`.
     * @return void
     */
    public static function cliBackfill($args = array(), $assoc = array()): void
    {
        $assoc = is_array($assoc) ? $assoc : array();

        $report = self::backfill(array(
            'file'    => (string) ($assoc['file'] ?? ''),
            'dry_run' => !empty($assoc['dry-run']),
            'force'   => !empty($assoc['force']),
        ));

        if ('' !== $report['origin_error']) {
            \WP_CLI::error((string) $report['origin_error']);

            return;
        }

        $dryRun = !empty($assoc['dry-run']);
        \WP_CLI::log('Filas: ' . (int) $report['total']);
        \WP_CLI::log(
            ($dryRun ? 'Habría actualizado: ' : 'Actualizados: ') . (int) $report['actualizados']
        );
        \WP_CLI::log('Sin cambios: ' . (int) $report['sin_cambios']);
        \WP_CLI::log('Protegidos: ' . (int) $report['protegidos']);
        \WP_CLI::log('Sin vídeo: ' . (int) $report['sin_video']);
        \WP_CLI::log('Inexistentes: ' . (int) $report['inexistentes']);

        if ($dryRun) {
            $total = count($report['changes']);
            foreach ($report['changes'] as $index => $change) {
                if ($index >= 20) {
                    \WP_CLI::log('  … y ' . ($total - 20) . ' más');
                    break;
                }

                \WP_CLI::log(sprintf(
                    '  %d: %s → %s',
                    (int) $change['post_id'],
                    $change['from'] !== '' ? $change['from'] : '(vacío)',
                    (string) $change['to']
                ));
            }
        }

        foreach ($report['avisos'] as $aviso) {
            \WP_CLI::warning((string) $aviso);
        }

        \WP_CLI::success(
            $dryRun ? 'Ensayo del backfill de vídeo completado' : 'Backfill de vídeo completado'
        );
    }
}
