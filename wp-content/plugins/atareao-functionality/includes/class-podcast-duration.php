<?php
/**
 * Podcast duration — backfill of the episode duration from the origin RSS.
 *
 * The feed declares `<itunes:duration>` from the canonical `duration` meta, and
 * the theme paints the legacy `_duration` meta in the episode card and page.
 * Both must carry the same value, so every write goes through
 * `writeDuration()`, the single point of truth for the pair.
 *
 * The data itself comes from the origin (Spotify for Podcasters / Anchor), the
 * programme's public RSS: each WordPress episode is matched with its origin
 * item — by `anchor-guid` first, by the episode number as fallback **only when
 * there is exactly one candidate** — and the origin `<itunes:duration>` is
 * validated with `PodcastFeed::normalizeDuration()` before being written. A
 * value that does not validate is reported as unreadable and never invented.
 *
 * The command is idempotent: a second run reports zero updates and changes no
 * metadata. It is executed by WP-CLI
 * (`wp atareao podcast-duration-backfill [--dry-run] [--limit=<n>] [--source=<ruta>]`)
 * and touches no other meta: no audio, title, date, guid, numbering or order.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class PodcastDuration
{
    /**
     * Post type of the episodes.
     */
    public const POST_TYPE = 'podcast';

    /**
     * Canonical episode meta key holding the duration.
     */
    public const DURATION_META = PodcastFeed::DURATION_META;

    /**
     * Legacy episode meta key holding the duration (theme contract, mirror).
     */
    public const LEGACY_DURATION_META = PodcastFeed::LEGACY_DURATION_META;

    /**
     * Episode meta key holding the Anchor UUID.
     */
    public const GUID_META = PodcastFeed::GUID_META;

    /**
     * Episode meta key holding the episode number.
     */
    public const NUMBER_META = PodcastFeed::NUMBER_META;

    /**
     * Wire the module: WP-CLI backfill command.
     *
     * @return void
     */
    public static function init(): void
    {
        if (defined('WP_CLI') && WP_CLI && class_exists('\WP_CLI')) {
            \WP_CLI::add_command('atareao podcast-duration-backfill', array(__CLASS__, 'cliBackfill'));
        }
    }

    /**
     * Write the canonical duration and its mirror, only when the value changes.
     *
     * This is the **only** write path of the duration: the backfill and the
     * episode importer both go through it, so `duration` and `_duration` can
     * never drift apart. An empty post or a value that does not normalise is
     * refused (no write, `false`).
     *
     * @param int    $postId    Episode identifier.
     * @param string $canonical Duration to persist.
     * @return bool Whether anything was written.
     */
    public static function writeDuration(int $postId, string $canonical): bool
    {
        $canonical = PodcastFeed::normalizeDuration($canonical);
        if ($postId <= 0 || $canonical === '') {
            return false;
        }

        $current = trim((string) get_post_meta($postId, self::DURATION_META, true));
        $legacy  = trim((string) get_post_meta($postId, self::LEGACY_DURATION_META, true));
        if ($current === $canonical && $legacy === $canonical) {
            return false;
        }

        update_post_meta($postId, self::DURATION_META, $canonical);
        update_post_meta($postId, self::LEGACY_DURATION_META, $canonical);

        return true;
    }

    /**
     * Origin RSS URL: the configured option, or the default.
     *
     * Same contract as `PodcastAudio::originUrl()` and `PodcastGuid::originUrl()`:
     * production keeps `atareao_podcast_origin_rss` empty, so the normal path is
     * the default programme feed.
     *
     * @return string
     */
    private static function originUrl(): string
    {
        $configured = trim((string) get_option(PodcastAudio::ORIGIN_RSS_OPTION, ''));

        return $configured !== '' ? $configured : PodcastAudio::ORIGIN_RSS_DEFAULT;
    }

    /**
     * Fill and repair the episode duration from the origin RSS.
     *
     * Matching precedence: trimmed `anchor-guid` against the origin `<guid>`
     * first; the episode number against the number deduced from the origin title
     * as fallback, and **only when exactly one origin item carries it** — a
     * repeated number is ambiguous and reported as unmatched rather than
     * guessed. The origin duration is validated with `normalizeDuration()`; an
     * unreadable value never overwrites the stored one.
     *
     * @param array $options `dry_run`, `limit` (0 = no limit) and `source`
     *                       (local file; empty means download the origin).
     * @return array Report with `actualizados`, `sin_cambios`,
     *               `sin_correspondencia`, `ilegibles`, `pendientes`, `origin`
     *               and `origin_error`.
     */
    public static function backfill(array $options = array()): array
    {
        $dryRun = !empty($options['dry_run']);
        $limit  = max(0, (int) ($options['limit'] ?? 0));
        $source = trim((string) ($options['source'] ?? ''));
        $origin = $source !== '' ? $source : self::originUrl();

        $report = array(
            'actualizados'       => 0,
            'sin_cambios'        => 0,
            'sin_correspondencia' => 0,
            'ilegibles'          => 0,
            'pendientes'         => 0,
            'origin'             => $origin,
            'origin_error'       => false,
        );

        $body = self::readOrigin($origin, $source !== '');
        if ($body === null) {
            $report['origin_error'] = true;

            return $report;
        }

        $items = PodcastGuid::parseOriginFeed($body);
        if ($items === array()) {
            $report['origin_error'] = true;

            return $report;
        }

        $byGuid   = array();
        $byNumber = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $guid = trim((string) ($item['guid'] ?? ''));
            if ($guid !== '' && !isset($byGuid[$guid])) {
                $byGuid[$guid] = $item;
            }

            $number = $item['number'] ?? null;
            if ($number !== null && $number !== '' && is_numeric($number)) {
                $byNumber[(int) $number][] = $item;
            }
        }

        foreach (self::candidateEpisodes() as $postId) {
            $guid = trim((string) get_post_meta($postId, self::GUID_META, true));
            $item = null;

            if ($guid !== '' && isset($byGuid[$guid])) {
                $item = $byGuid[$guid];
            } else {
                $number = trim((string) get_post_meta($postId, self::NUMBER_META, true));
                if ($number !== '' && is_numeric($number)) {
                    $candidates = $byNumber[(int) $number] ?? array();
                    if (count($candidates) === 1) {
                        $item = $candidates[0];
                    }
                }
            }

            if ($item === null) {
                $report['sin_correspondencia']++;
                continue;
            }

            $value = PodcastFeed::normalizeDuration((string) ($item['duration'] ?? ''));
            if ($value === '') {
                $report['ilegibles']++;
                continue;
            }

            $current = PodcastFeed::normalizeDuration(
                (string) get_post_meta($postId, self::DURATION_META, true)
            );
            if ($current === $value) {
                $report['sin_cambios']++;
                continue;
            }

            if ($limit > 0 && $report['actualizados'] >= $limit) {
                $report['pendientes']++;
                continue;
            }

            if (!$dryRun) {
                self::writeDuration($postId, $value);
            }
            $report['actualizados']++;
        }

        return $report;
    }

    /**
     * WP-CLI callback for the duration backfill.
     *
     * @param array $args  Positional arguments (unused).
     * @param array $assoc Associative options: `dry-run`, `limit` and `source`.
     * @return void
     */
    public static function cliBackfill($args = array(), $assoc = array()): void
    {
        $assoc = is_array($assoc) ? $assoc : array();
        $dryRun = !empty($assoc['dry-run']);

        $report = self::backfill(array(
            'dry_run' => $dryRun,
            'limit'   => max(0, (int) ($assoc['limit'] ?? 0)),
            'source'  => (string) ($assoc['source'] ?? ''),
        ));

        if (!empty($report['origin_error'])) {
            \WP_CLI::error('No se pudo leer el origen: ' . (string) $report['origin']);

            return;
        }

        \WP_CLI::log('Origen: ' . (string) $report['origin']);
        \WP_CLI::log(
            ($dryRun ? 'Habría actualizado: ' : 'Actualizados: ') . (int) $report['actualizados']
        );
        \WP_CLI::log('Sin cambios: ' . (int) $report['sin_cambios']);
        \WP_CLI::log('Sin correspondencia: ' . (int) $report['sin_correspondencia']);
        \WP_CLI::log('Ilegibles: ' . (int) $report['ilegibles']);
        \WP_CLI::log('Pendientes: ' . (int) $report['pendientes']);
        \WP_CLI::success(
            $dryRun ? 'Ensayo del backfill de duración completado' : 'Backfill de duración completado'
        );
    }

    /**
     * Read the origin body, from a local file or over HTTP.
     *
     * @param string $origin Origin path or URL.
     * @param bool   $local  Whether the origin is a local file.
     * @return string|null Body, or null when the origin is unreadable.
     */
    private static function readOrigin(string $origin, bool $local): ?string
    {
        if ($local) {
            if (!is_file($origin) || !is_readable($origin)) {
                return null;
            }

            $body = file_get_contents($origin);

            return is_string($body) && trim($body) !== '' ? $body : null;
        }

        $response = wp_safe_remote_get($origin, array(
            'timeout'             => PodcastAudio::REQUEST_TIMEOUT,
            'limit_response_size' => PodcastAudio::MAX_BODY_BYTES + 1,
            'redirection'         => 0,
            'reject_unsafe_urls'  => true,
        ));
        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ($body === '' || strlen($body) > PodcastAudio::MAX_BODY_BYTES) {
            return null;
        }

        return $body;
    }

    /**
     * Published episodes, as identifiers.
     *
     * @return int[]
     */
    private static function candidateEpisodes(): array
    {
        $query = new \WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        $episodes = array();
        foreach ($query->posts as $candidate) {
            $postId = $candidate instanceof \WP_Post ? (int) $candidate->ID : (int) $candidate;
            if ($postId > 0) {
                $episodes[] = $postId;
            }
        }

        return $episodes;
    }
}
