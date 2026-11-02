<?php
/**
 * Podcast audio sync — autofill and repair of the `mp3-url` episode meta.
 *
 * The audio of every episode lives in the public RSS of the origin (Spotify for
 * Podcasters / Anchor), a stable index of the whole programme. WordPress is a
 * manual mirror of that index, and a couple of episodes end up published with
 * no audio or with a value that is not a URL. This module resolves the audio
 * link from that public origin feed, matches each episode with its origin item
 * — **the identity of the `.mp3` file first, the number deduced from the title
 * as fallback** — and writes it only when the episode has no usable URL yet. A
 * valid `mp3-url` is **never** overwritten.
 *
 * The matching engine (`audioIdentity()`, `episodeNumberFromTitle()`,
 * `isUsableAudioUrl()`, `parseOriginFeed()`, `buildIndex()` and `planSync()`)
 * is pure on purpose: no network and no WordPress function, so the rules can be
 * verified in isolation. Only the orchestration (`syncEpisodes()`,
 * `onTransition()`, the CLI command) touches WordPress.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class PodcastAudio
{
    /**
     * Post type of the episodes.
     */
    public const POST_TYPE = 'podcast';

    /**
     * Episode meta key holding the audio file URL.
     */
    public const AUDIO_META = 'mp3-url';

    /**
     * Episode meta key holding the episode number.
     */
    public const NUMBER_META = 'number';

    /**
     * Option holding the origin RSS URL.
     */
    public const ORIGIN_RSS_OPTION = 'atareao_podcast_origin_rss';

    /**
     * Default origin RSS URL (the programme's public feed).
     */
    public const ORIGIN_RSS_DEFAULT = 'https://anchor.fm/s/5a5b39c/podcast/rss';

    /**
     * Transient holding the parsed origin index.
     */
    public const INDEX_TRANSIENT = 'atareao_podcast_audio_index';

    /**
     * Lifetime, in seconds, of a resolved origin index (6 hours).
     */
    public const INDEX_TTL = 21600;

    /**
     * Lifetime, in seconds, of an origin failure marker (5 minutes).
     *
     * Short on purpose: a transient origin outage must not hide the index for a
     * whole working day.
     */
    public const INDEX_FAILURE_TTL = 300;

    /**
     * Upper bound, in bytes, of the origin body accepted.
     */
    public const MAX_BODY_BYTES = 4194304;

    /**
     * Timeout, in seconds, of the origin request.
     */
    public const REQUEST_TIMEOUT = 15;

    /**
     * Episode meta marking that the publication attempt already ran.
     */
    public const ATTEMPT_META = '_atareao_podcast_audio_attempt';

    /**
     * Wire the publication hook and the WP-CLI command.
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('transition_post_status', array(__CLASS__, 'onTransition'), 10, 3);

        if (defined('WP_CLI') && WP_CLI && class_exists('\WP_CLI')) {
            \WP_CLI::add_command('atareao podcast-audio-sync', array(__CLASS__, 'cliSync'));
        }
    }

    /**
     * Normalised identity of the audio file carried by a URL.
     *
     * The name of the `.mp3` file, URL-decoded first so a link wrapped by the
     * origin playback redirection and the direct link to the same file share
     * the same identity. A value without a `.mp3` file has no identity and
     * returns the empty string.
     *
     * @param string $url Audio URL (or any raw meta value).
     * @return string Identity, or '' when there is none.
     */
    public static function audioIdentity(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $decoded = rawurldecode($url);
        $parts   = preg_split('/[?#]/', $decoded);
        $path    = is_array($parts) && isset($parts[0]) ? $parts[0] : $decoded;
        $base    = basename($path);

        if ($base === '' || strtolower((string) pathinfo($base, PATHINFO_EXTENSION)) !== 'mp3') {
            return '';
        }

        return $base;
    }

    /**
     * Episode number deduced from an origin item title.
     *
     * Tolerates the formats the origin actually publishes: `ATA 839`,
     * `ATA839`, `ATA T01E50`, `705 - ...` and `T01E36 ...`.
     *
     * @param string $title Origin item title.
     * @return int|null Number, or null when the title carries none.
     */
    public static function episodeNumberFromTitle(string $title): ?int
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        if (preg_match('/T\d+E(\d+)/i', $title, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/ATA\s*(\d+)/i', $title, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^(\d+)/', $title, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Whether a value is an absolute `http(s)` URL.
     *
     * @param string $url Candidate value.
     * @return bool
     */
    public static function isUsableAudioUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return ($scheme === 'http' || $scheme === 'https')
            && isset($parts['host'])
            && $parts['host'] !== '';
    }

    /**
     * Parse the origin RSS into the items the matching engine consumes.
     *
     * @param string $xml Origin RSS body.
     * @return array[] Items with `title`, `enclosure`, `identity` and `number`.
     */
    public static function parseOriginFeed(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '') {
            return array();
        }

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            return array();
        }

        $items = array();
        foreach ($document->channel->item as $item) {
            $title     = trim((string) $item->title);
            $enclosure = isset($item->enclosure['url']) ? trim((string) $item->enclosure['url']) : '';

            $items[] = array(
                'title'     => $title,
                'enclosure' => $enclosure,
                'identity'  => self::audioIdentity($enclosure),
                'number'    => self::episodeNumberFromTitle($title),
            );
        }

        return $items;
    }

    /**
     * Index the origin items by audio identity and by episode number.
     *
     * @param array[] $items Items returned by `parseOriginFeed()`.
     * @return array Index with `by_identity` (identity => item) and
     *               `by_number` (number => list of items).
     */
    public static function buildIndex(array $items): array
    {
        $byIdentity = array();
        $byNumber   = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $identity = isset($item['identity']) ? (string) $item['identity'] : '';
            if ($identity !== '' && !isset($byIdentity[$identity])) {
                $byIdentity[$identity] = $item;
            }

            $number = $item['number'] ?? null;
            if ($number !== null && is_numeric($number)) {
                $key = (int) $number;
                if (!isset($byNumber[$key])) {
                    $byNumber[$key] = array();
                }
                $byNumber[$key][] = $item;
            }
        }

        return array(
            'by_identity' => $byIdentity,
            'by_number'   => $byNumber,
        );
    }

    /**
     * Decide what to do with one episode against the origin index.
     *
     * The audio identity is the primary key: a present identity means the
     * episode is either matched (`ok`) or divergent (`divergent`), and a
     * divergence is only reported. Without identity the episode number is the
     * fallback key and the outcome is `ambiguous` (two candidates) or
     * `unmatched` (no candidate); a matched episode with no usable audio is
     * `repair` when the origin enclosure is a usable URL, or `failed` when it
     * is not.
     *
     * @param array $episode Episode with `id`, `title`, `mp3_url` and `number`.
     * @param array $index   Index returned by `buildIndex()`.
     * @return array Decision with `class` and `value`.
     */
    public static function planSync(array $episode, array $index): array
    {
        $audio    = isset($episode['mp3_url']) ? trim((string) $episode['mp3_url']) : '';
        $number   = $episode['number'] ?? null;
        $identity = self::audioIdentity($audio);
        $usable   = self::isUsableAudioUrl($audio);

        if ($identity !== '') {
            $byIdentity = isset($index['by_identity']) && is_array($index['by_identity'])
                ? $index['by_identity']
                : array();

            if (isset($byIdentity[$identity])) {
                return array('class' => 'ok', 'value' => null);
            }

            return array('class' => 'divergent', 'value' => null);
        }

        $matched = self::matchByNumber($index, $number);
        if ($matched['status'] === 'ambiguous') {
            return array('class' => 'ambiguous', 'value' => null);
        }

        if ($matched['status'] === 'none') {
            return array('class' => 'unmatched', 'value' => null);
        }

        if ($usable) {
            return array('class' => 'ok', 'value' => null);
        }

        $enclosure = isset($matched['item']['enclosure']) ? (string) $matched['item']['enclosure'] : '';
        if (!self::isUsableAudioUrl($enclosure)) {
            return array('class' => 'failed', 'value' => null);
        }

        return array('class' => 'repair', 'value' => $enclosure);
    }

    /**
     * Resolve the `mp3-url` of every published episode in one pass.
     *
     * Downloads the origin at most once (the index is cached in a transient)
     * and reports a count per class. It never overwrites a usable audio URL and
     * has no option to do so. When the origin is unreachable nothing is written
     * and every reviewed episode is counted as failed.
     *
     * @param array $options `dry_run`, `limit` and `missing`.
     * @return array Counts keyed `repaired`, `ok`, `divergent`, `ambiguous`,
     *               `unmatched`, `failed` and `remaining`.
     */
    public static function syncEpisodes(array $options = array()): array
    {
        $dryRun  = !empty($options['dry_run']);
        $limit   = isset($options['limit']) ? (int) $options['limit'] : 0;
        $missing = !empty($options['missing']);

        $report = array(
            'repaired'  => 0,
            'ok'        => 0,
            'divergent' => 0,
            'ambiguous' => 0,
            'unmatched' => 0,
            'failed'    => 0,
            'remaining' => 0,
        );

        $episodes = self::candidateEpisodes($missing);
        $total    = count($episodes);

        $index = self::originIndex();
        if ($index === null) {
            $report['failed'] = $total;

            return $report;
        }

        $processed = 0;
        foreach ($episodes as $episode) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
            $processed++;

            $plan  = self::planSync($episode, $index);
            $class = (string) $plan['class'];

            if ($class === 'repair') {
                $clean = esc_url_raw(trim((string) $plan['value']));
                if (!self::isUsableAudioUrl($clean)) {
                    $report['failed']++;
                    continue;
                }

                if (!$dryRun) {
                    update_post_meta((int) $episode['id'], self::AUDIO_META, $clean);
                }
                $report['repaired']++;
                continue;
            }

            if (isset($report[$class])) {
                $report[$class]++;
            }
        }

        if ($limit > 0) {
            $report['remaining'] = max(0, $total - $processed);
        }

        return $report;
    }

    /**
     * Resolve the audio of a newly published episode, without breaking it.
     *
     * Only `podcast` posts transitioning to `publish` are considered. The
     * attempt is idempotent: an episode already attempted is skipped, so saving
     * again neither repeats the origin download nor changes the meta. A network
     * or parsing failure is swallowed and writes nothing.
     *
     * @param string       $newStatus New post status.
     * @param string       $oldStatus Previous post status (unused).
     * @param \WP_Post|null $post     The post being transitioned.
     * @return void
     */
    public static function onTransition($newStatus, $oldStatus, $post): void
    {
        if ((string) $newStatus !== 'publish') {
            return;
        }

        if (!$post instanceof \WP_Post) {
            return;
        }

        if ((string) $post->post_type !== self::POST_TYPE) {
            return;
        }

        $postId = (int) $post->ID;
        if ($postId <= 0) {
            return;
        }

        if ((string) get_post_meta($postId, self::ATTEMPT_META, true) !== '') {
            return;
        }

        $index = self::originIndex();
        if ($index === null) {
            return;
        }

        $episode = self::episodeData($postId, (string) $post->post_title);
        $plan    = self::planSync($episode, $index);

        if ((string) $plan['class'] === 'repair') {
            $clean = esc_url_raw(trim((string) $plan['value']));
            if (self::isUsableAudioUrl($clean)) {
                update_post_meta($postId, self::AUDIO_META, $clean);
            }
        }

        update_post_meta($postId, self::ATTEMPT_META, time());
    }

    /**
     * WP-CLI callback resolving the whole episode history.
     *
     * @param array $args  Positional arguments (unused).
     * @param array $assoc Associative options: `dry-run`, `limit`, `missing`
     *                     and `format` (`table` or `json`).
     * @return void
     */
    public static function cliSync($args = array(), $assoc = array()): void
    {
        $assoc = is_array($assoc) ? $assoc : array();

        $options = array(
            'dry_run' => !empty($assoc['dry-run']),
            'missing' => !empty($assoc['missing']),
            'limit'   => isset($assoc['limit']) ? (int) $assoc['limit'] : 0,
        );

        $report = self::syncEpisodes($options);
        $format = isset($assoc['format']) ? (string) $assoc['format'] : 'table';

        if ($format === 'json') {
            \WP_CLI::line((string) wp_json_encode($report));

            return;
        }

        $labels = array(
            'repaired'  => 'Reparados',
            'ok'        => 'Ya correctos',
            'divergent' => 'Divergentes',
            'ambiguous' => 'Ambiguos',
            'unmatched' => 'Sin par',
            'failed'    => 'Fallidos',
            'remaining' => 'Pendientes',
        );

        foreach ($labels as $key => $label) {
            \WP_CLI::log($label . ': ' . (int) $report[$key]);
        }

        if (!empty($options['dry_run'])) {
            \WP_CLI::log('Ensayo: no se ha escrito ninguna meta.');
        }

        \WP_CLI::success('Sincronización de audio del podcast completada.');
    }

    /**
     * Match a fallback number against the origin index.
     *
     * @param array    $index  Index returned by `buildIndex()`.
     * @param int|null $number Episode number (or any raw meta value).
     * @return array `status` (`unique`, `ambiguous` or `none`) and `item`.
     */
    private static function matchByNumber(array $index, $number): array
    {
        if ($number === null || $number === '' || !is_numeric($number)) {
            return array('status' => 'none', 'item' => null);
        }

        $byNumber = isset($index['by_number']) && is_array($index['by_number'])
            ? $index['by_number']
            : array();

        $key = (int) $number;
        if (!isset($byNumber[$key]) || !is_array($byNumber[$key]) || $byNumber[$key] === array()) {
            return array('status' => 'none', 'item' => null);
        }

        if (count($byNumber[$key]) > 1) {
            return array('status' => 'ambiguous', 'item' => null);
        }

        return array('status' => 'unique', 'item' => $byNumber[$key][0]);
    }

    /**
     * Published podcast episodes to review, optionally only the broken ones.
     *
     * @param bool $missingOnly Skip episodes that already have a usable URL.
     * @return array Episode descriptors for `planSync()`.
     */
    private static function candidateEpisodes(bool $missingOnly): array
    {
        $query = new \WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ));

        $episodes = array();
        foreach ($query->posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $postId = (int) $post->ID;
            if ($postId <= 0) {
                continue;
            }

            $audio = (string) get_post_meta($postId, self::AUDIO_META, true);
            if ($missingOnly && self::isUsableAudioUrl($audio)) {
                continue;
            }

            $episodes[] = self::episodeData($postId, (string) $post->post_title);
        }

        return $episodes;
    }

    /**
     * Build the pure-engine descriptor of an episode from its metas.
     *
     * @param int    $postId Episode identifier.
     * @param string $title  Episode title (unused by the engine, kept for parity).
     * @return array Descriptor with `id`, `title`, `mp3_url` and `number`.
     */
    private static function episodeData(int $postId, string $title): array
    {
        return array(
            'id'      => $postId,
            'title'   => $title,
            'mp3_url' => (string) get_post_meta($postId, self::AUDIO_META, true),
            'number'  => get_post_meta($postId, self::NUMBER_META, true),
        );
    }

    /**
     * Origin RSS URL: the configured option, or the default.
     *
     * @return string
     */
    private static function originUrl(): string
    {
        $configured = trim((string) get_option(self::ORIGIN_RSS_OPTION, ''));

        return $configured !== '' ? $configured : self::ORIGIN_RSS_DEFAULT;
    }

    /**
     * Origin index, from the transient cache or a single download.
     *
     * @return array|null Index, or null when the origin is unreachable.
     */
    private static function originIndex(): ?array
    {
        if (get_transient(self::INDEX_TRANSIENT . '_failure')) {
            return null;
        }

        $cached = get_transient(self::INDEX_TRANSIENT);
        if (is_array($cached) && isset($cached['index']) && is_array($cached['index'])) {
            return $cached['index'];
        }

        $response = self::requestOrigin();

        if (is_wp_error($response)) {
            self::rememberFailure();

            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            self::rememberFailure();

            return null;
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ($body === '' || strlen($body) > self::MAX_BODY_BYTES) {
            self::rememberFailure();

            return null;
        }

        $index = self::buildIndex(self::parseOriginFeed($body));

        set_transient(
            self::INDEX_TRANSIENT,
            array(
                'index' => $index,
                'etag'  => (string) wp_remote_retrieve_header($response, 'etag'),
            ),
            self::INDEX_TTL
        );

        return $index;
    }

    /**
     * Download the origin RSS, revalidating with the cached `ETag`.
     *
     * @return mixed Response or `WP_Error`.
     */
    private static function requestOrigin()
    {
        $args = array(
            'timeout'             => self::REQUEST_TIMEOUT,
            'limit_response_size' => self::MAX_BODY_BYTES,
        );

        $cached = get_transient(self::INDEX_TRANSIENT);
        if (is_array($cached) && !empty($cached['etag'])) {
            $args['headers'] = array('If-None-Match' => (string) $cached['etag']);
        }

        return wp_remote_get(self::originUrl(), $args);
    }

    /**
     * Remember an origin failure with a short lifetime.
     *
     * @return void
     */
    private static function rememberFailure(): void
    {
        set_transient(self::INDEX_TRANSIENT . '_failure', time(), self::INDEX_FAILURE_TTL);
    }
}
