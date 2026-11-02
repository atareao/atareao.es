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
 * `onPublishedPost()`, the CLI command) touches WordPress.
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
     * The hook is `wp_after_insert_post`, which the core fires **after** the
     * post row, its metas and its terms are persisted. It must not be
     * `transition_post_status` (fired before `save_post`, and on every
     * `wp_insert_post`), because `Metaboxes::saveMetaboxes()` writes `number`
     * and `mp3-url` in `save_post`: reading the episode before that would see
     * a stale number and then have the audio overwritten by the metabox.
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('wp_after_insert_post', array(__CLASS__, 'onPublishedPost'), 20, 4);

        if (defined('WP_CLI') && WP_CLI && class_exists('\WP_CLI')) {
            \WP_CLI::add_command('atareao podcast-audio-sync', array(__CLASS__, 'cliSync'));
        }
    }

    /**
     * Normalise the `--limit` value: a negative or non-numeric limit is zero.
     *
     * @param mixed $value Raw option value.
     * @return int Non-negative episode limit (0 = no limit).
     */
    public static function normalizeLimit($value): int
    {
        return max(0, (int) $value);
    }

    /**
     * Normalise the `--format` value: `table` (default) or `json`, else null.
     *
     * @param mixed $format Raw option value.
     * @return string|null The accepted format, or null when it is unknown.
     */
    public static function normalizeFormat($format): ?string
    {
        $format = strtolower(trim((string) $format));

        if ($format === '' || $format === 'table') {
            return 'table';
        }

        if ($format === 'json') {
            return 'json';
        }

        return null;
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

        return strtolower($base);
    }

    /**
     * Episode number deduced from an origin item title.
     *
     * Tolerates the formats the origin actually publishes: `ATA 839`,
     * `ATA839`, `ATA T01E50`, `705 - ...` and `T01E36 ...`. An explicit
     * `ATA <n>` wins over a `TxxExx` suffix (`ATA 839 - T02E05` is 839, not 5),
     * because the former is the programme's own episode number.
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

        if (preg_match('/ATA\s*(\d+)/i', $title, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/T\d+E(\d+)/i', $title, $matches) === 1) {
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

        if (!isset($document->channel)) {
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
     * A value that is not an absolute `http(s)` URL is treated as **absent**,
     * so its file-name identity is ignored and the episode is resolved by
     * number: `repair` when the matched origin enclosure is usable, `failed`
     * when it is not. Only a usable audio is eligible for identity matching,
     * which yields `ok` (matched) or `divergent` (unknown file); a usable URL
     * without a `.mp3` identity falls back to the number just to classify the
     * episode (`ambiguous`, `unmatched` or `ok`) and is never overwritten. A
     * matched number with no usable audio and no `--force` is never written.
     *
     * @param array $episode Episode with `id`, `title`, `mp3_url` and `number`.
     * @param array $index   Index returned by `buildIndex()`.
     * @return array Decision with `class` and `value`.
     */
    public static function planSync(array $episode, array $index): array
    {
        $audio  = isset($episode['mp3_url']) ? trim((string) $episode['mp3_url']) : '';
        $number = $episode['number'] ?? null;

        if (!self::isUsableAudioUrl($audio)) {
            return self::planFromNumber($index, $number, false);
        }

        $identity = self::audioIdentity($audio);
        if ($identity === '') {
            return self::planFromNumber($index, $number, true);
        }

        $byIdentity = isset($index['by_identity']) && is_array($index['by_identity'])
            ? $index['by_identity']
            : array();

        if (isset($byIdentity[$identity])) {
            return array('class' => 'ok', 'value' => null);
        }

        return array('class' => 'divergent', 'value' => null);
    }

    /**
     * Classify an episode through its episode number.
     *
     * @param array    $index     Index returned by `buildIndex()`.
     * @param int|null $number    Episode number (or any raw meta value).
     * @param bool     $satisfied Whether the episode already has a usable URL.
     * @return array Decision with `class` and `value`.
     */
    private static function planFromNumber(array $index, $number, bool $satisfied): array
    {
        $matched = self::matchByNumber($index, $number);
        if ($matched['status'] === 'ambiguous') {
            return array('class' => 'ambiguous', 'value' => null);
        }

        if ($matched['status'] === 'none') {
            return array('class' => 'unmatched', 'value' => null);
        }

        if ($satisfied) {
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
        $limit   = self::normalizeLimit($options['limit'] ?? 0);
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
            $reviewed            = $limit > 0 ? min($limit, $total) : $total;
            $report['failed']    = $reviewed;
            $report['remaining'] = $limit > 0 ? max(0, $total - $reviewed) : 0;

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
     * Resolve the audio of an episode once it has been fully persisted.
     *
     * Hooked to `wp_after_insert_post`, so the `number` and `mp3-url` written
     * by `Metaboxes::saveMetaboxes()` in `save_post` are already in place and
     * cannot overwrite what this writes. Only `podcast` posts in `publish`
     * status are considered; autosaves and revisions are skipped.
     *
     * The attempt is idempotent for **resolved** episodes (`ok` or `repair`),
     * which are the only ones that mark `ATTEMPT_META`. An episode that is
     * `unmatched`, `ambiguous`, `divergent` or `failed` is left unmarked, so a
     * later save can resolve it once the origin publishes it. A network or
     * parsing failure writes nothing and is swallowed.
     *
     * @param int            $postId     Persisted post identifier.
     * @param \WP_Post|null  $post       Persisted post.
     * @param bool|null      $update     Whether this is an update (unused).
     * @param \WP_Post|null  $postBefore Post state before the update (unused).
     * @return void
     */
    public static function onPublishedPost($postId, $post = null, $update = null, $postBefore = null): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $postId = (int) $postId;
        if (!$post instanceof \WP_Post && $postId > 0) {
            $post = get_post($postId);
        }

        if (!$post instanceof \WP_Post) {
            return;
        }

        if ((string) $post->post_type !== self::POST_TYPE) {
            return;
        }

        if ((string) $post->post_status !== 'publish') {
            return;
        }

        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

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
        $class   = (string) $plan['class'];

        if ($class === 'repair') {
            $clean = esc_url_raw(trim((string) $plan['value']));
            if (self::isUsableAudioUrl($clean)) {
                update_post_meta($postId, self::AUDIO_META, $clean);
                update_post_meta($postId, self::ATTEMPT_META, time());
            }

            return;
        }

        if ($class === 'ok') {
            update_post_meta($postId, self::ATTEMPT_META, time());
        }
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
            'limit'   => self::normalizeLimit($assoc['limit'] ?? 0),
        );

        $format = self::normalizeFormat($assoc['format'] ?? '');
        if ($format === null) {
            \WP_CLI::error('Formato no soportado. Usa "table" o "json".');

            return;
        }

        $report = self::syncEpisodes($options);

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
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        $episodes = array();
        foreach ($query->posts as $candidate) {
            $postId = $candidate instanceof \WP_Post ? (int) $candidate->ID : (int) $candidate;
            if ($postId <= 0) {
                continue;
            }

            $audio = (string) get_post_meta($postId, self::AUDIO_META, true);
            if ($missingOnly && self::isUsableAudioUrl($audio)) {
                continue;
            }

            $episodes[] = self::episodeData($postId, '');
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
     * Origin index, preferring the transient cache over a fresh download.
     *
     * The cache is consulted before the failure marker: a stale failure must
     * not hide an index that is already available. A successful resolution
     * (from the cache or from a download) clears the failure marker. An empty
     * parse — invalid XML, an HTML error page or a truncated body — is treated
     * as an origin failure, never cached as a valid index.
     *
     * @return array|null Index, or null when the origin is unreachable.
     */
    private static function originIndex(): ?array
    {
        $cached = get_transient(self::INDEX_TRANSIENT);
        if (is_array($cached) && isset($cached['index']) && is_array($cached['index'])) {
            delete_transient(self::INDEX_TRANSIENT . '_failure');

            return $cached['index'];
        }

        if (get_transient(self::INDEX_TRANSIENT . '_failure')) {
            return null;
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

        $items = self::parseOriginFeed($body);
        if ($items === array()) {
            self::rememberFailure();

            return null;
        }

        $index = self::buildIndex($items);
        delete_transient(self::INDEX_TRANSIENT . '_failure');
        set_transient(self::INDEX_TRANSIENT, array('index' => $index), self::INDEX_TTL);

        return $index;
    }

    /**
     * Download the origin RSS through the safe HTTP API.
     *
     * Uses `wp_safe_remote_get()` and refuses to follow redirects, so a
     * compromised or spoofed origin cannot be used to reach internal hosts
     * (SSRF). The body is bounded by `MAX_BODY_BYTES` and the request by
     * `REQUEST_TIMEOUT`.
     *
     * @return mixed Response or `WP_Error`.
     */
    private static function requestOrigin()
    {
        return wp_safe_remote_get(self::originUrl(), array(
            'timeout'             => self::REQUEST_TIMEOUT,
            'limit_response_size' => self::MAX_BODY_BYTES,
            'redirection'         => 0,
            'reject_unsafe_urls'  => true,
        ));
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
