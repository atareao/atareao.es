<?php
/**
 * Podcast guid — mapeo determinista desde el RSS de Anchor al metadato del episodio.
 *
 * El feed del podcast debe declarar, en cada `<item>`, el **mismo UUID** que el
 * item homólogo del feed de Anchor: migrar sin replicar los guid duplicaría los
 * episodios en los directorios. El emparejamiento usa la **identidad del fichero
 * de audio** como clave primaria —la misma que valida `PodcastAudio`— y el
 * número deducido del título como respaldo, y persiste el UUID resuelto en el
 * metadato del episodio (`anchor-guid`), que pasa a ser la única fuente de
 * verdad del `<guid>`.
 *
 * El mapeo es reproducible y auditable: se ejecuta por WP-CLI
 * (`wp atareao podcast-guid-map`) y es idempotente (una segunda pasada marca los
 * episodios ya resueltos sin reescribirlos). Un episodio sin par no recibe
 * guid: se reporta y el feed le asigna un guid estable propio.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class PodcastGuid
{
    /**
     * Post type of the episodes.
     */
    public const POST_TYPE = 'podcast';

    /**
     * Episode meta key holding the audio file URL (matching key, primary).
     */
    public const AUDIO_META = 'mp3-url';

    /**
     * Episode meta key holding the episode number (matching key, fallback).
     */
    public const NUMBER_META = 'number';

    /**
     * Episode meta key holding the resolved Anchor UUID.
     *
     * Single source of truth of the item guid. Shared with the feed through
     * `PodcastFeed::GUID_META`, which is the constant the feed consumes.
     */
    public const GUID_META = PodcastFeed::GUID_META;

    /**
     * Episode meta key holding the season number.
     */
    public const SEASON_META = PodcastFeed::SEASON_META;

    /**
     * Episode meta key holding the origin `itunes:duration` value.
     */
    public const DURATION_META = PodcastFeed::DURATION_META;

    /**
     * Transient holding the parsed origin index (separate from the audio one).
     *
     * The audio index carries no guid, so the guid mapper keeps its own cache.
     */
    public const INDEX_TRANSIENT = 'atareao_podcast_guid_index';

    /**
     * Cache lifetime, in seconds, of a resolved origin index (6 hours).
     */
    public const INDEX_TTL = 21600;

    /**
     * Cache lifetime, in seconds, of an origin failure (5 minutes).
     */
    public const INDEX_FAILURE_TTL = 300;

    /**
     * Upper bound, in bytes, of the accepted origin body.
     */
    public const MAX_BODY_BYTES = 8388608;

    /**
     * Timeout, in seconds, of the origin request.
     */
    public const REQUEST_TIMEOUT = 15;

    /**
     * Wire the module: WP-CLI mapping command.
     *
     * @return void
     */
    public static function init(): void
    {
        if (defined('WP_CLI') && WP_CLI && class_exists('\WP_CLI')) {
            \WP_CLI::add_command('atareao podcast-guid-map', array(__CLASS__, 'cliMap'));
            \WP_CLI::add_command('atareao podcast-guid-import-missing', array(__CLASS__, 'cliImportMissing'));
        }
    }

    /**
     * Parse the origin RSS into the items the matching engine consumes.
     *
     * Reuses `PodcastAudio`'s identity and number helpers, so both modules agree
     * on the matching key; adds the Anchor `guid`, the `itunes:duration`, the
     * `itunes:season` and the item `pubDate` the importer needs.
     *
     * @param string $xml Origin RSS body.
     * @return array[] Items with `title`, `enclosure`, `identity`, `number`,
     *                 `guid`, `duration`, `season` and `pub_date`.
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

        if ($document === false || !isset($document->channel)) {
            return array();
        }

        $items = array();
        foreach ($document->channel->item as $item) {
            $title     = trim((string) $item->title);
            $enclosure = isset($item->enclosure['url']) ? trim((string) $item->enclosure['url']) : '';
            $guid      = isset($item->guid) ? trim((string) $item->guid) : '';
            $pubDate   = isset($item->pubDate) ? trim((string) $item->pubDate) : '';
            $itunes    = $item->children(PodcastFeed::ITUNES_NAMESPACE);
            $duration  = isset($itunes->duration) ? trim((string) $itunes->duration) : '';
            $seasonRaw = isset($itunes->season) ? trim((string) $itunes->season) : '';
            $season    = $seasonRaw !== '' && is_numeric($seasonRaw) ? (int) $seasonRaw : null;

            $items[] = array(
                'title'     => $title,
                'enclosure' => $enclosure,
                'identity'  => PodcastAudio::audioIdentity($enclosure),
                'number'    => PodcastAudio::episodeNumberFromTitle($title),
                'guid'      => $guid,
                'duration'  => $duration,
                'season'    => $season,
                'pub_date'  => $pubDate,
            );
        }

        return $items;
    }

    /**
     * Index the origin items by audio identity and by episode number.
     *
     * @param array[] $items Items returned by `parseOriginFeed()`.
     * @return array Index with `by_identity` and `by_number`.
     */
    public static function buildIndex(array $items): array
    {
        return PodcastAudio::buildIndex($items);
    }

    /**
     * Decide the Anchor guid of one episode against the origin index.
     *
     * Identity of the audio file first (unique per item in Anchor), episode
     * number as fallback. An episode that resolves to no origin item is
     * `unmatched` with an empty guid: it never invents a value.
     *
     * @param array $episode Episode with `id`, `mp3_url` and `number`.
     * @param array $index   Index returned by `buildIndex()`.
     * @return array Decision with `class` (`mapped`/`unmatched`) and `guid`.
     */
    public static function planMapping(array $episode, array $index): array
    {
        $audio    = isset($episode['mp3_url']) ? trim((string) $episode['mp3_url']) : '';
        $identity = $audio !== '' ? PodcastAudio::audioIdentity($audio) : '';

        $byIdentity = isset($index['by_identity']) && is_array($index['by_identity'])
            ? $index['by_identity']
            : array();

        if ($identity !== '' && isset($byIdentity[$identity])) {
            $guid = self::guidOf($byIdentity[$identity]);
            if ($guid !== '') {
                return array('class' => 'mapped', 'guid' => $guid);
            }
        }

        $match = self::matchByNumber($index, $episode['number'] ?? null);
        if ($match['status'] === 'unique') {
            $guid = self::guidOf($match['item']);
            if ($guid !== '') {
                return array('class' => 'mapped', 'guid' => $guid);
            }
        }

        return array('class' => 'unmatched', 'guid' => '');
    }

    /**
     * Resolve and persist the Anchor guid of every published episode.
     *
     * Downloads the origin at most once (cached in a transient) and reports a
     * count per class. Idempotent: an episode that already carries a guid is
     * counted as `ok` and never rewritten. A `dry_run` never writes a meta.
     *
     * @param array $options `dry_run` and `limit`.
     * @return array Counts keyed `mapped`, `ok`, `unmatched`, `failed` and
     *               `remaining`.
     */
    public static function mapEpisodes(array $options = array()): array
    {
        $dryRun = !empty($options['dry_run']);
        $limit  = max(0, (int) ($options['limit'] ?? 0));

        $report = array(
            'mapped'    => 0,
            'ok'        => 0,
            'unmatched' => 0,
            'failed'    => 0,
            'remaining' => 0,
        );

        $episodes = self::candidateEpisodes();
        $total    = count($episodes);

        $index = self::originIndex();
        if ($index === null) {
            $reviewed         = $limit > 0 ? min($limit, $total) : $total;
            $report['failed'] = $reviewed;
            if ($limit > 0) {
                $report['remaining'] = max(0, $total - $reviewed);
            }

            return $report;
        }

        $processed = 0;
        foreach ($episodes as $episode) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
            $processed++;

            $postId = (int) $episode['id'];
            if (trim((string) get_post_meta($postId, self::GUID_META, true)) !== '') {
                $report['ok']++;
                continue;
            }

            $plan = self::planMapping($episode, $index);
            if ($plan['class'] === 'mapped' && (string) $plan['guid'] !== '') {
                if (!$dryRun) {
                    update_post_meta($postId, self::GUID_META, (string) $plan['guid']);
                }
                $report['mapped']++;
                continue;
            }

            $report['unmatched']++;
        }

        if ($limit > 0) {
            $report['remaining'] = max(0, $total - $processed);
        }

        return $report;
    }

    /**
     * Import the origin episodes that are missing from WordPress.
     *
     * Compares the origin feed with the `podcast` archive —by Anchor guid first
     * and by episode number as fallback—: an origin item that matches neither is
     * missing. A missing episode is created (post type `podcast`, status
     * `publish`, the origin title and date in the site timezone) with the metas
     * the feed consumes. An origin item with neither guid nor number cannot be
     * deduplicated, so it is reported as `skipped` and never invented.
     *
     * Idempotent: a second run creates nothing. A `dry_run` reports what it would
     * create without writing a single post or meta.
     *
     * @param array $options `dry_run` and `author` (forced author id).
     * @return array Counts keyed `missing`, `created`, `skipped` and `failed`.
     */
    public static function importMissingEpisodes(array $options = array()): array
    {
        $dryRun   = !empty($options['dry_run']);
        $authorId = max(0, (int) ($options['author'] ?? 0));

        $report = array(
            'missing' => 0,
            'created' => 0,
            'skipped' => 0,
            'failed'  => 0,
        );

        $items = self::originItems();
        if ($items === null) {
            $report['failed'] = 1;

            return $report;
        }

        $knownGuids   = array();
        $knownNumbers = array();
        foreach (self::candidateEpisodes() as $episode) {
            $guid = trim((string) get_post_meta($episode['id'], self::GUID_META, true));
            if ($guid !== '') {
                $knownGuids[$guid] = (int) $episode['id'];
            }

            $number = $episode['number'] ?? null;
            if ($number !== null && $number !== '' && is_numeric($number)) {
                $knownNumbers[(int) $number] = (int) $episode['id'];
            }
        }

        foreach ($items as $item) {
            $guid      = trim((string) ($item['guid'] ?? ''));
            $number    = $item['number'] ?? null;
            $hasNumber = $number !== null && $number !== '' && is_numeric($number);

            $knownByGuid   = $guid !== '' && isset($knownGuids[$guid]);
            $knownByNumber = $hasNumber && isset($knownNumbers[(int) $number]);
            if ($knownByGuid || $knownByNumber) {
                continue;
            }

            if ($guid === '' && !$hasNumber) {
                $report['skipped']++;
                continue;
            }

            $report['missing']++;
            if ($dryRun) {
                continue;
            }

            $postId = self::createEpisode($item, $authorId);
            if ($postId <= 0) {
                $report['failed']++;
                continue;
            }

            $report['created']++;
            if ($guid !== '') {
                $knownGuids[$guid] = $postId;
            }
            if ($hasNumber) {
                $knownNumbers[(int) $number] = $postId;
            }
        }

        return $report;
    }

    /**
     * Create one missing episode with the metas the feed consumes.
     *
     * Only the metas the origin actually carries are written: a value absent
     * from the origin is omitted rather than invented.
     *
     * @param array $item     Origin item.
     * @param int   $authorId Forced author, or 0 to inherit the latest episode's.
     * @return int New post identifier, or 0 on failure.
     */
    private static function createEpisode(array $item, int $authorId = 0): int
    {
        $title = trim((string) ($item['title'] ?? ''));
        if ($title === '') {
            return 0;
        }

        $postarr = array(
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $title,
        );

        $author = $authorId > 0 ? $authorId : self::defaultAuthorId();
        if ($author > 0) {
            $postarr['post_author'] = $author;
        }

        $date = self::localDate((string) ($item['pub_date'] ?? ''));
        if ($date !== '') {
            $postarr['post_date'] = $date;
        }

        $postId = wp_insert_post($postarr, true);
        if (is_wp_error($postId) || (int) $postId <= 0) {
            return 0;
        }

        $postId = (int) $postId;

        $guid = trim((string) ($item['guid'] ?? ''));
        if ($guid !== '') {
            update_post_meta($postId, self::GUID_META, $guid);
        }

        $number = $item['number'] ?? null;
        if ($number !== null && $number !== '' && is_numeric($number)) {
            update_post_meta($postId, self::NUMBER_META, (int) $number);
        }

        $season = $item['season'] ?? null;
        if ($season !== null && is_numeric($season)) {
            update_post_meta($postId, self::SEASON_META, (int) $season);
        }

        $duration = trim((string) ($item['duration'] ?? ''));
        if ($duration !== '') {
            update_post_meta($postId, self::DURATION_META, $duration);
        }

        $audio = trim((string) ($item['enclosure'] ?? ''));
        if ($audio !== '') {
            update_post_meta($postId, self::AUDIO_META, $audio);
        }

        return $postId;
    }

    /**
     * Origin `pubDate` as a site-local `Y-m-d H:i:s`, or '' when unparseable.
     *
     * @param string $pubDate RFC 2822 date from the origin.
     * @return string
     */
    private static function localDate(string $pubDate): string
    {
        $pubDate = trim($pubDate);
        if ($pubDate === '') {
            return '';
        }

        $timestamp = strtotime($pubDate);
        if ($timestamp === false) {
            return '';
        }

        return (string) wp_date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Author of the most recent published podcast episode, or 0 when none.
     *
     * @return int
     */
    private static function defaultAuthorId(): int
    {
        $query = new \WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ));

        foreach ($query->posts as $candidate) {
            $post = $candidate instanceof \WP_Post ? $candidate : get_post($candidate);
            if ($post instanceof \WP_Post && (int) $post->post_author > 0) {
                return (int) $post->post_author;
            }
        }

        return 0;
    }

    /**
     * WP-CLI callback resolving the whole episode history.
     *
     * @param array $args  Positional arguments (unused).
     * @param array $assoc Associative options: `dry-run` and `limit`.
     * @return void
     */
    public static function cliMap($args = array(), $assoc = array()): void
    {
        $assoc = is_array($assoc) ? $assoc : array();

        $report = self::mapEpisodes(array(
            'dry_run' => !empty($assoc['dry-run']),
            'limit'   => max(0, (int) ($assoc['limit'] ?? 0)),
        ));

        \WP_CLI::log('Mapeados: ' . (int) $report['mapped']);
        \WP_CLI::log('Ya resueltos: ' . (int) $report['ok']);
        \WP_CLI::log('Sin par: ' . (int) $report['unmatched']);
        \WP_CLI::log('Fallidos: ' . (int) $report['failed']);

        if (!empty($assoc['dry-run'])) {
            \WP_CLI::log('Ensayo: no se ha escrito ninguna meta.');
        }

        \WP_CLI::success('Mapeo de guid del podcast completado.');
    }

    /**
     * WP-CLI callback importing the origin episodes missing from WordPress.
     *
     * @param array $args  Positional arguments (unused).
     * @param array $assoc Associative options: `dry-run` and `author`.
     * @return void
     */
    public static function cliImportMissing($args = array(), $assoc = array()): void
    {
        $assoc = is_array($assoc) ? $assoc : array();

        $report = self::importMissingEpisodes(array(
            'dry_run' => !empty($assoc['dry-run']),
            'author'  => max(0, (int) ($assoc['author'] ?? 0)),
        ));

        \WP_CLI::log('Episodios ausentes: ' . (int) $report['missing']);
        \WP_CLI::log('Creados: ' . (int) $report['created']);
        \WP_CLI::log('Omitidos: ' . (int) $report['skipped']);

        if (!empty($assoc['dry-run'])) {
            \WP_CLI::log('Ensayo: no se ha creado ninguna entrada.');
        }

        if ((int) $report['failed'] > 0) {
            \WP_CLI::warning('No se pudo leer el RSS de origen: no se ha creado nada.');

            return;
        }

        \WP_CLI::success('Importación de episodios ausentes completada.');
    }

    /**
     * Anchor guid carried by an origin item, or ''.
     *
     * @param array $item Origin item.
     * @return string
     */
    private static function guidOf($item): string
    {
        return is_array($item) && isset($item['guid']) ? trim((string) $item['guid']) : '';
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
     * Published podcast episodes to map.
     *
     * @return array Episode descriptors for `planMapping()`.
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
            if ($postId <= 0) {
                continue;
            }

            $episodes[] = array(
                'id'      => $postId,
                'mp3_url' => (string) get_post_meta($postId, self::AUDIO_META, true),
                'number'  => get_post_meta($postId, self::NUMBER_META, true),
            );
        }

        return $episodes;
    }

    /**
     * Origin RSS URL: the configured option, or the default.
     *
     * @return string
     */
    private static function originUrl(): string
    {
        $configured = trim((string) get_option(PodcastAudio::ORIGIN_RSS_OPTION, ''));

        return $configured !== '' ? $configured : PodcastAudio::ORIGIN_RSS_DEFAULT;
    }

    /**
     * Origin feed payload: parsed items plus index, from cache or download.
     *
     * @return array|null `items` and `index`, or null when the origin is
     *                    unreachable.
     */
    private static function originPayload(): ?array
    {
        $cached = get_transient(self::INDEX_TRANSIENT);
        if (
            is_array($cached)
            && isset($cached['index'], $cached['items'])
            && is_array($cached['index'])
            && is_array($cached['items'])
        ) {
            delete_transient(self::INDEX_TRANSIENT . '_failure');

            return array('index' => $cached['index'], 'items' => $cached['items']);
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

        $index   = self::buildIndex($items);
        $payload = array('index' => $index, 'items' => $items);
        delete_transient(self::INDEX_TRANSIENT . '_failure');
        set_transient(self::INDEX_TRANSIENT, $payload, self::INDEX_TTL);

        return $payload;
    }

    /**
     * Origin index, or null when the origin is unreachable.
     *
     * @return array|null Index with `by_identity` and `by_number`.
     */
    private static function originIndex(): ?array
    {
        $payload = self::originPayload();

        return $payload === null ? null : $payload['index'];
    }

    /**
     * Origin items, or null when the origin is unreachable.
     *
     * @return array[]|null Items returned by `parseOriginFeed()`.
     */
    private static function originItems(): ?array
    {
        $payload = self::originPayload();

        return $payload === null ? null : $payload['items'];
    }

    /**
     * Download the origin RSS through the safe HTTP API.
     *
     * @return mixed Response or `WP_Error`.
     */
    private static function requestOrigin()
    {
        return wp_safe_remote_get(self::originUrl(), array(
            'timeout'             => self::REQUEST_TIMEOUT,
            'limit_response_size' => self::MAX_BODY_BYTES + 1,
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
