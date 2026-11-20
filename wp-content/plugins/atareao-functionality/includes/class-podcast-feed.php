<?php
/**
 * Podcast feed — Podcasting 2.0 / Apple Podcasts vocabulary for the CPT archive feed.
 *
 * The archive feed of the `podcast` post type (`/podcast/feed/`) is today a plain
 * RSS 2.0 feed: it declares no `podcast:`/`itunes:` namespaces, carries no
 * `<enclosure>` (the audio lives in the `mp3-url` meta, which the feed never
 * publishes) and exposes no transcript, so a podcast reader or a directory sees
 * a generic blog feed with nothing to subscribe to.
 *
 * This module extends that existing feed — never creates a new one, so the URL
 * the theme footer already announces keeps working — by declaring the
 * namespaces on the root element and adding the channel and item vocabulary at
 * the core hooks (`rss2_ns`, `rss2_head`, `rss2_item`). Every hook is global to
 * any RSS2 feed, so all emission is scoped by `isPodcastFeed()`: the main feed
 * and the feeds linked to other post type archives stay untouched.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class PodcastFeed
{
    /**
     * Post type served by this feed.
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
     * Episode meta key holding the season number.
     */
    public const SEASON_META = 'season';

    /**
     * Episode meta key holding the duration.
     */
    public const DURATION_META = 'duration';

    /**
     * Legacy episode meta key holding the duration (theme contract).
     *
     * The theme reads `_duration`; the feed historically read `duration`. Both
     * are honoured so the item always declares a duration when either exists.
     */
    public const LEGACY_DURATION_META = '_duration';

    /**
     * Episode meta key holding the resolved Anchor UUID.
     *
     * Single source of truth of the item `<guid>`; written by
     * `PodcastGuid::mapEpisodes()` and read here.
     */
    public const GUID_META = 'anchor-guid';

    /**
     * Priority of the `the_guid` emission filter.
     *
     * Core registers `add_filter( 'the_guid', 'esc_url' )` at priority 10, and
     * `esc_url()` prepends `http://` to any string without a scheme. This filter
     * must run **after** it to restore the persisted guid, so its priority is
     * strictly greater than 10.
     */
    public const GUID_EMISSION_PRIORITY = 20;

    /**
     * Episode meta key holding the episode image attachment ID (integer).
     */
    public const EPISODE_IMAGE_META = 'episode-image';

    /**
     * Episode meta key holding the chapters document attachment ID (integer).
     */
    public const CHAPTERS_META = 'podcast-chapters';

    /**
     * Episode meta key holding the transcript attachment ID (integer).
     */
    public const TRANSCRIPT_META = 'transcript-srt';

    /**
     * Media type of a Podcasting 2.0 chapters document.
     */
    public const CHAPTERS_MEDIA_TYPE = 'application/json+chapters';

    /**
     * Item ceiling applied to the podcast feed.
     *
     * `posts_per_rss` caps every feed at 100 items. The podcast feed lifts that
     * ceiling **only for itself** by raising the option to this value: a finite
     * number is required because `WP_Query` builds a SQL `LIMIT`, and it is far
     * above the current archive (849 episodes), so the feed publishes the whole
     * archive without a practical cap.
     */
    public const FEED_ITEM_LIMIT = 100000;

    /**
     * Podcasting 2.0 namespace.
     */
    public const PODCAST_NAMESPACE = 'https://podcastindex.org/namespace/1.0';

    /**
     * Apple Podcasts namespace.
     */
    public const ITUNES_NAMESPACE = 'http://www.itunes.com/dtds/podcast-1.0.dtd';

    /**
     * Atom namespace (declared by core; kept as a single source of truth).
     */
    public const ATOM_NAMESPACE = 'http://www.w3.org/2005/Atom';

    /**
     * Media type of a SubRip transcript.
     */
    public const TRANSCRIPT_MEDIA_TYPE = 'application/x-subrip';

    /**
     * Episode meta key holding the cached audio size, in bytes.
     *
     * Stored as `array( 'bytes' => int, 'expires' => int )`, where `expires`
     * is a Unix timestamp.
     */
    public const AUDIO_LENGTH_META = '_atareao_podcast_audio_length';

    /**
     * Cache lifetime, in seconds, of a resolved audio size (30 days).
     */
    public const AUDIO_LENGTH_SUCCESS_TTL = 2592000;

    /**
     * Cache lifetime, in seconds, of an unresolved audio size (6 hours).
     *
     * Short on purpose: a transient failure (timeout, CDN hiccup) must not be
     * remembered for a month.
     */
    public const AUDIO_LENGTH_FAILURE_TTL = 21600;

    /**
     * Timeout, in seconds, of every remote request.
     */
    public const REMOTE_TIMEOUT = 5;

    /**
     * Maximum number of redirects followed manually while resolving a size.
     */
    public const REMOTE_MAX_REDIRECTS = 3;

    /**
     * Wall-clock budget, in seconds, for the remote work of a whole request.
     */
    public const REMOTE_BUDGET_SECONDS = 5.0;

    /**
     * Upper bound, in bytes, of the body accepted by the ranged reservation.
     *
     * The reservation asks for `Range: bytes=0-0`, but a server is free to
     * ignore the range and answer with the whole file. The real size travels in
     * the `Content-Range` header, so the body is never needed; it is capped so
     * that an oversized response cannot be pulled into memory. Maps to the
     * physical HTTP transport cap through core's `limit_response_size` argument.
     */
    public const REMOTE_RANGE_MAX_BYTES = 1024;

    /**
     * Media type of the podcast feed.
     *
     * No `charset` parameter on purpose: core composes the real header as
     * `'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' )`
     * (`wp-includes/feed-rss2.php` and `wp-includes/class-wp.php`), so it appends
     * the charset itself. Declaring it here as well would duplicate the parameter
     * and produce an invalid header; the wire media type stays
     * `application/rss+xml; charset=UTF-8`.
     */
    public const FEED_CONTENT_TYPE = 'application/rss+xml';

    /**
     * RFC 4122 NAMESPACE_URL, used to derive the program guid (16 bytes).
     */
    private const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Option holding the canonical feed URL.
     */
    private const FEED_URL_OPTION = 'atareao_podcast_feed';

    /**
     * Option holding the program language.
     */
    private const LANGUAGE_OPTION = 'atareao_podcast_language';

    /**
     * Filter overriding the remote wall-clock budget, in seconds.
     */
    private const REMOTE_BUDGET_FILTER = 'atareao_podcast_audio_length_budget_seconds';

    /**
     * Timestamp of the first remote attempt of the process, or null.
     *
     * Shared by every `audioLength()` call of the same request so the whole
     * feed composition stays within `REMOTE_BUDGET_SECONDS`: a feed with many
     * remote episodes must not turn into an unbounded number of HTTP calls.
     *
     * @var float|null
     */
    private static $firstRemoteAttempt = null;

    /**
     * Wire the feed hooks.
     *
     * The core prints `<title>`, `<atom:link rel="self">`, `<link>`,
     * `<description>` and `<language>` before `rss2_head`, so this module only
     * adds the podcasting vocabulary; re-emitting those five would duplicate
     * them and invalidate the feed. The methods below are action callbacks, so
     * they print their descriptor and also return it (the suite composes the
     * document from the returned value).
     *
     * `rss2_item` is dispatched with no arguments (`do_action( 'rss2_item' )` in
     * `wp-includes/feed-rss2.php`), so the declared count is not what the loop
     * passes but what the callback accepts: the optional episode identifier.
     * Registering it with 0 would silently discard the identifier a third party
     * may pass.
     *
     * @return void
     */
    public static function init()
    {
        add_filter('feed_content_type', array(__CLASS__, 'contentType'), 10, 2);
        add_filter('get_bloginfo_rss', array(__CLASS__, 'programLanguage'), 10, 2);
        add_filter('option_posts_per_rss', array(__CLASS__, 'feedLimit'), 10, 2);
        add_filter('get_the_guid', array(__CLASS__, 'filterGuid'), 10, 2);
        add_filter('the_guid', array(__CLASS__, 'filterGuidEmission'), self::GUID_EMISSION_PRIORITY, 2);
        add_filter('the_excerpt_rss', array(__CLASS__, 'excerptFilter'), 10, 1);
        add_action('rss2_ns', array(__CLASS__, 'namespaceDeclarations'));
        add_action('rss2_head', array(__CLASS__, 'channelElements'));
        add_action('rss2_item', array(__CLASS__, 'itemElements'), 10, 1);
        add_action('pre_get_posts', array(__CLASS__, 'excludeNonPublic'));

        if (defined('WP_CLI') && WP_CLI && class_exists('\WP_CLI')) {
            \WP_CLI::add_command('atareao podcast-audio-length', array(__CLASS__, 'cliWarmAudioLengths'));
        }
    }

    /**
     * Whether the current request is the podcast archive feed.
     *
     * Scoping guard for every hook: it is true only inside `/podcast/feed/`.
     *
     * @return bool
     */
    public static function isPodcastFeed(): bool
    {
        return is_feed() && is_post_type_archive(self::POST_TYPE);
    }

    /**
     * Canonical URL of the program feed.
     *
     * Single source of truth: the option the theme already announces, with a
     * fallback to the feed link core resolves for the archive.
     *
     * @return string
     */
    public static function feedUrl(): string
    {
        $configured = trim((string) get_option(self::FEED_URL_OPTION, ''));
        if ($configured !== '') {
            return $configured;
        }

        $link = get_post_type_archive_feed_link(self::POST_TYPE);

        return is_string($link) ? $link : '';
    }

    /**
     * Stable program guid.
     *
     * RFC 4122 UUIDv5 over the feed URL, so it never changes between feed
     * rebuilds or plugin versions: a directory that already registered the
     * program would stop recognising it otherwise.
     *
     * @return string
     */
    public static function programGuid(): string
    {
        return self::uuidV5(self::NAMESPACE_URL, self::feedUrl());
    }

    /**
     * Media type of the podcast feed, or the incoming type when it is not one.
     *
     * @param string $type     Incoming media type.
     * @param string $feedType Feed type (unused; part of the hook signature).
     * @return string
     */
    public static function contentType(string $type, string $feedType = ''): string
    {
        return self::isPodcastFeed() ? self::FEED_CONTENT_TYPE : $type;
    }

    /**
     * Namespace declarations added to the `<rss>` root element.
     *
     * The method both prints and returns on purpose: WordPress `do_action()`
     * discards the return value of its callbacks, so the live feed only receives
     * the namespaces if they are echoed — while the function suite measures the
     * return value. Both paths must stay identical; do not drop the `echo`.
     *
     * @return string
     */
    public static function namespaceDeclarations(): string
    {
        $out = self::isPodcastFeed()
            ? 'xmlns:podcast="' . self::PODCAST_NAMESPACE . '" '
                . 'xmlns:itunes="' . self::ITUNES_NAMESPACE . '"'
            : '';

        echo $out;

        return $out;
    }

    /**
     * Podcasting channel elements added to `<channel>`.
     *
     * Only the vocabulary the core does not print; the title, link,
     * description, language and `atom:link` are emitted by core.
     *
     * The method both prints and returns on purpose: WordPress `do_action()`
     * discards the return value of its callbacks, so the live feed only receives
     * the elements if they are echoed — while the function suite measures the
     * return value. Both paths must stay identical; do not drop the `echo`.
     *
     * @return string
     */
    public static function channelElements(): string
    {
        if (!self::isPodcastFeed()) {
            return '';
        }

        $author     = self::setting('atareao_podcast_author', (string) get_bloginfo('name'));
        $type       = self::setting('atareao_podcast_type', 'episodic');
        $category   = self::setting('atareao_podcast_category', 'Technology');
        $ownerName  = self::setting('atareao_podcast_owner_name', (string) get_bloginfo('name'));
        $ownerEmail = self::setting('atareao_podcast_owner_email', (string) get_option('admin_email', ''));
        $explicit   = self::setting('atareao_podcast_explicit', 'false');
        $cover      = self::absoluteHttpsUrl(self::setting('atareao_podcast_cover'));

        if (!in_array($type, array('serial', 'episodic'), true)) {
            $type = 'episodic';
        }
        /*
         * Apple exige un booleano en `itunes:explicit`: `true` o `false`. Los
         * valores heredados del ajuste (`no`, `yes`, `clean`) se normalizan para
         * no romper un valor ya guardado; cualquier otro valor cae a `false`.
         */
        $explicit = in_array(strtolower($explicit), array('yes', 'true'), true) ? 'true' : 'false';

        $out  = '<itunes:author>' . esc_html($author) . '</itunes:author>' . "\n";
        $out .= '<itunes:type>' . esc_html($type) . '</itunes:type>' . "\n";
        $out .= '<itunes:explicit>' . esc_html($explicit) . '</itunes:explicit>' . "\n";
        $out .= '<copyright>' . esc_html('© ' . $ownerName . ' ' . gmdate('Y')) . '</copyright>' . "\n";
        $out .= '<itunes:category text="' . esc_attr($category) . '" />' . "\n";

        if ($ownerEmail !== '') {
            $out .= '<itunes:owner>'
                . '<itunes:name>' . esc_html($ownerName) . '</itunes:name>'
                . '<itunes:email>' . esc_html($ownerEmail) . '</itunes:email>'
                . '</itunes:owner>' . "\n";
        }

        if ($cover !== '') {
            $out .= '<itunes:image href="' . esc_attr($cover) . '" />' . "\n";
        }

        if ($ownerEmail !== '') {
            $out .= '<podcast:locked owner="' . esc_attr($ownerEmail) . '">yes</podcast:locked>' . "\n";
        } else {
            $out .= '<podcast:locked>yes</podcast:locked>' . "\n";
        }

        $out .= '<podcast:guid>' . esc_html(self::programGuid()) . '</podcast:guid>' . "\n";

        echo $out;

        return $out;
    }

    /**
     * Program language, or the incoming value for any other bloginfo field.
     *
     * The core prints `<language>` through `bloginfo_rss('language')`, which is
     * filtered here, so the channel serves the program language without a
     * second `<language>` element.
     *
     * @param string $info Incoming value.
     * @param string $show Requested bloginfo field.
     * @return string
     */
    public static function programLanguage(string $info, string $show = ''): string
    {
        if ($show === 'language' && self::isPodcastFeed()) {
            return self::programLanguageValue();
        }

        return $info;
    }

    /**
     * Podcasting item elements for an episode.
     *
     * Returns (and prints) nothing outside the podcast feed, for a non-public
     * episode, or for an episode without audio: an item without `mp3-url` is
     * not a playable episode and must not declare one.
     *
     * The method both prints and returns on purpose: WordPress `do_action()`
     * discards the return value of its callbacks, so the live feed only receives
     * the elements if they are echoed — while the function suite measures the
     * return value. Both paths must stay identical; do not drop the `echo`.
     *
     * The parameter type is tolerant on purpose and must not be narrowed back to
     * `?int`. Core dispatches `do_action( 'rss2_item' )` with no arguments, and
     * `wp-includes/plugin.php` turns that empty argument list into `array( '' )`
     * before dispatching, so the callback receives the empty string; a third
     * party may instead pass the episode ID. A type error here is not caught by
     * core and aborts the item loop halfway, truncating the feed.
     *
     * @param int|string|null $postId Episode ID when a caller supplies one, or
     *                                the empty string core sends; any value that
     *                                is not a positive integer means the queried
     *                                post.
     * @return string
     */
    public static function itemElements(int | string | null $postId = null): string
    {
        // Only a positive integer survives; the empty string core sends (and any
        // other value) becomes null, i.e. "the episode the loop is on".
        $postId = ctype_digit((string) $postId) && (int) $postId > 0 ? (int) $postId : null;

        if (!self::isPodcastFeed()) {
            return '';
        }

        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            return '';
        }

        $id = (int) $post->ID;
        if (!self::isPublicEpisode($id)) {
            return '';
        }

        $audio = trim((string) get_post_meta($id, self::AUDIO_META, true));
        if ($audio === '') {
            return '';
        }

        $number   = trim((string) get_post_meta($id, self::NUMBER_META, true));
        $season   = trim((string) get_post_meta($id, self::SEASON_META, true));
        $duration = self::durationMeta($id);

        $out = '<enclosure url="' . esc_attr($audio) . '"'
            . ' type="' . esc_attr(self::audioMediaType($audio)) . '"'
            . ' length="' . esc_attr((string) self::audioLength($audio, $id)) . '" />' . "\n";

        if ($number !== '') {
            $out .= '<itunes:episode>' . esc_html($number) . '</itunes:episode>' . "\n";
        }
        if ($season !== '') {
            $out .= '<itunes:season>' . esc_html($season) . '</itunes:season>' . "\n";
        }
        if ($number !== '') {
            $out .= '<podcast:episode>' . esc_html($number) . '</podcast:episode>' . "\n";
        }
        if ($season !== '') {
            $out .= '<podcast:season>' . esc_html($season) . '</podcast:season>' . "\n";
        }
        if ($duration !== '') {
            $out .= '<itunes:duration>' . esc_html($duration) . '</itunes:duration>' . "\n";
        }

        $image = self::episodeImageUrl($id);
        if ($image !== '') {
            $out .= '<itunes:image href="' . esc_attr($image) . '" />' . "\n";
        }

        $transcriptId = self::normalizeTranscriptAttachment(
            get_post_meta($id, self::TRANSCRIPT_META, true)
        );
        if ($transcriptId > 0 && self::isAccessibleTranscript($transcriptId)) {
            $out .= '<podcast:transcript url="' . esc_attr(self::transcriptUrl($transcriptId)) . '"'
                . ' type="' . esc_attr(self::TRANSCRIPT_MEDIA_TYPE) . '"'
                . ' language="' . esc_attr(self::programLanguageValue()) . '" />' . "\n";
        }

        $chaptersId = self::attachmentIdFromMeta(get_post_meta($id, self::CHAPTERS_META, true));
        if ($chaptersId > 0) {
            $chaptersUrl = self::chaptersUrl($chaptersId);
            if ($chaptersUrl !== '') {
                $out .= '<podcast:chapters url="' . esc_attr($chaptersUrl) . '"'
                    . ' type="' . esc_attr(self::CHAPTERS_MEDIA_TYPE) . '" />' . "\n";
            }
        }

        echo $out;

        return $out;
    }

    /**
     * Raise the item ceiling of the podcast feed only.
     *
     * Filter callback for `option_posts_per_rss` (`$value, $option`). Outside
     * the podcast feed —the main feed, other post type archive feeds, the admin
     * or any other option read— the incoming value is returned untouched.
     *
     * @param mixed  $value  Incoming option value.
     * @param string $option Option name (part of the hook signature).
     * @return mixed The raised ceiling inside the podcast feed, else `$value`.
     */
    public static function feedLimit($value, $option = '')
    {
        if ($option !== '' && $option !== 'posts_per_rss') {
            return $value;
        }

        if (!self::isPodcastFeed()) {
            return $value;
        }

        return max((int) $value, self::FEED_ITEM_LIMIT);
    }

    /**
     * Replace the core item guid with the Anchor-compatible one.
     *
     * Filter callback for `get_the_guid` (`$guid, $post_id`). Outside the
     * podcast feed the core guid is preserved.
     *
     * @param string $guid   Core guid.
     * @param int    $postId Post identifier (part of the hook signature).
     * @return string
     */
    public static function filterGuid($guid, $postId = 0)
    {
        if (!self::isPodcastFeed()) {
            return $guid;
        }

        $resolved = self::itemGuid((int) $postId);

        return $resolved !== '' ? $resolved : $guid;
    }

    /**
     * Emit the persisted guid verbatim in the podcast feed.
     *
     * Filter callback for `the_guid`. Core registers
     * `add_filter( 'the_guid', 'esc_url' )`, and `esc_url()` prepends `http://`
     * to any string without a scheme: a bare UUID would be published as
     * `http://<uuid>` and would stop matching its origin item. Registered at a
     * priority **after** the core filter so it can restore the persisted value.
     *
     * Scoped to the podcast feed, and escaped for XML (`esc_html`) rather than
     * as a URL (`esc_url`). Outside the podcast feed the incoming value is
     * preserved untouched.
     *
     * @param string $guid   Core-escaped guid.
     * @param int    $postId Post identifier (part of the hook signature).
     * @return string
     */
    public static function filterGuidEmission($guid, $postId = 0)
    {
        if (!self::isPodcastFeed()) {
            return $guid;
        }

        $resolved = self::itemGuid((int) $postId);

        return $resolved !== '' ? esc_html($resolved) : $guid;
    }

    /**
     * Guid of an episode: the persisted Anchor UUID, or a stable own one.
     *
     * The persisted `anchor-guid` meta is the single source of truth: when the
     * episode has no origin pair, a deterministic UUIDv5 over the feed URL and
     * the episode identifier keeps the guid stable and unique without inventing
     * an Anchor value.
     *
     * @param int $postId Episode identifier.
     * @return string
     */
    public static function itemGuid(int $postId): string
    {
        $postId = (int) $postId;
        if ($postId <= 0) {
            return '';
        }

        $stored = trim((string) get_post_meta($postId, self::GUID_META, true));
        if ($stored !== '') {
            return $stored;
        }

        return self::uuidV5(self::NAMESPACE_URL, self::feedUrl() . '#episode-' . $postId);
    }

    /**
     * Serve the full episode notes as the feed description.
     *
     * Filter callback for `the_excerpt_rss`. Core publishes a truncated excerpt
     * in `<description>`; the item must carry the complete notes. Only inside
     * the podcast feed, and only when the episode has content, does the excerpt
     * give way to the content; otherwise the incoming value is preserved.
     *
     * @param string $excerpt Incoming excerpt.
     * @return string
     */
    public static function excerptFilter($excerpt)
    {
        if (!self::isPodcastFeed()) {
            return $excerpt;
        }

        $post = get_post();
        if (!$post instanceof \WP_Post) {
            return $excerpt;
        }

        $content = trim((string) $post->post_content);

        return $content !== '' ? $content : $excerpt;
    }

    /**
     * Duration of an episode: the feed meta, or the theme's legacy meta.
     *
     * @param int $postId Episode identifier.
     * @return string Duration, or '' when neither meta carries one.
     */
    private static function durationMeta(int $postId): string
    {
        $duration = trim((string) get_post_meta($postId, self::DURATION_META, true));
        if ($duration !== '') {
            return $duration;
        }

        return trim((string) get_post_meta($postId, self::LEGACY_DURATION_META, true));
    }

    /**
     * Absolute HTTPS cover of an episode, falling back to the program cover.
     *
     * The episode image is an attachment identifier in `episode-image`; when it
     * is absent (or not an absolute HTTPS URL) the program cover is used, so
     * every item declares `itunes:image`.
     *
     * @param int $postId Episode identifier.
     * @return string Cover URL, or '' when none is configured.
     */
    private static function episodeImageUrl(int $postId): string
    {
        $attachmentId = self::attachmentIdFromMeta(get_post_meta($postId, self::EPISODE_IMAGE_META, true));
        if ($attachmentId > 0) {
            $url = wp_get_attachment_url($attachmentId);
            if (is_string($url)) {
                $absolute = self::absoluteHttpsUrl($url);
                if ($absolute !== '') {
                    return $absolute;
                }
            }
        }

        return self::absoluteHttpsUrl(self::setting('atareao_podcast_cover'));
    }

    /**
     * Absolute URL of a chapters document, or '' when it is not accessible.
     *
     * @param int $attachmentId Attachment identifier.
     * @return string
     */
    public static function chaptersUrl(int $attachmentId): string
    {
        if (!self::isAccessibleAttachment($attachmentId)) {
            return '';
        }

        $url = wp_get_attachment_url($attachmentId);

        return is_string($url) ? $url : '';
    }

    /**
     * Spoken text of a transcript attachment, without timing marks.
     *
     * Reads the same SRT attachment the feed declares (the one referenced by
     * `transcript-srt`) and strips the cue numbers and the `-->` timecodes,
     * leaving only the spoken text. Never downloads audio nor transcribes.
     *
     * @param int $attachmentId Transcript attachment identifier.
     * @return string Spoken text, or '' when there is no accessible transcript.
     */
    public static function transcriptText(int $attachmentId): string
    {
        if (!self::isAccessibleTranscript($attachmentId)) {
            return '';
        }

        $file = get_attached_file($attachmentId);
        if (!is_string($file) || !is_readable($file)) {
            return '';
        }

        $raw = (string) file_get_contents($file);
        if ($raw === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $spoken = array();
        foreach ((array) $lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '-->') !== false || preg_match('/^\d+$/', $line) === 1) {
                continue;
            }
            $spoken[] = $line;
        }

        return trim(implode("\n", $spoken));
    }

    /**
     * Indexable HTML of a transcript, or '' when there is none.
     *
     * Wraps the spoken text in a `<section>` of paragraphs so a search engine
     * can read it in the episode page, not only through the linked `.srt`.
     *
     * @param int $attachmentId Transcript attachment identifier.
     * @return string
     */
    public static function transcriptHtml(int $attachmentId): string
    {
        $text = self::transcriptText($attachmentId);
        if ($text === '') {
            return '';
        }

        $paragraphs = preg_split('/\n+/', $text);
        $out = '<section class="episode-transcript" aria-label="'
            . esc_attr__('Transcripción del episodio', 'atareao-functionality') . '">';
        $out .= '<h2 class="episode-transcript__title">'
            . esc_html__('Transcripción', 'atareao-functionality') . '</h2>';
        foreach ((array) $paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph !== '') {
                $out .= '<p>' . esc_html($paragraph) . '</p>';
            }
        }
        $out .= '</section>';

        return $out;
    }

    /**
     * Whether an attachment exists and is readable.
     *
     * @param int $attachmentId Attachment identifier.
     * @return bool
     */
    private static function isAccessibleAttachment(int $attachmentId): bool
    {
        if ($attachmentId <= 0) {
            return false;
        }

        $file = get_attached_file($attachmentId);

        return is_string($file) && $file !== '' && is_readable($file);
    }

    /**
     * Canonicalise an attachment reference to a positive integer.
     *
     * @param mixed $raw Raw value (string or number).
     * @return int Attachment ID, or 0 when the value is not a positive integer.
     */
    private static function attachmentIdFromMeta($raw): int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : 0;
        }

        if (is_string($raw) && preg_match('/^\d+$/', trim($raw)) === 1) {
            $id = (int) trim($raw);

            return $id > 0 ? $id : 0;
        }

        return 0;
    }

    /**
     * Media type of an audio file, from its extension.
     *
     * @param string $url Audio URL.
     * @return string
     */
    public static function audioMediaType(string $url): string
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $ext  = '';
        if (is_string($path) && $path !== '') {
            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        }

        $types = array(
            'mp3'  => 'audio/mpeg',
            'm4a'  => 'audio/mp4',
            'ogg'  => 'audio/ogg',
            'oga'  => 'audio/ogg',
            'wav'  => 'audio/wav',
            'aac'  => 'audio/aac',
            'opus' => 'audio/opus',
        );

        return $types[$ext] ?? 'audio/mpeg';
    }

    /**
     * Size in bytes of an audio file, or 0 when it cannot be determined.
     *
     * Resolution order is **cache first**: a live cached size for an identified
     * episode is returned before any other work, so a cache hit never reaches
     * `localAudioLength()` (whose `attachment_url_to_postid()` is a database
     * query, not a disk read) nor the network. When there is no live cache, the
     * local attachment is measured on disk and its size is cached on the
     * episode; otherwise, while the remote budget lasts, the size is resolved
     * over HTTP and cached on the episode.
     *
     * A size of `0` is the honest neutral value: the episode stays
     * subscribable instead of advertising a made-up number.
     *
     * @param string $url    Audio URL.
     * @param int    $postId Episode identifier, used to read and write the
     *                       size cache. `0` (the default) disables caching,
     *                       which keeps the single-argument call working with
     *                       the classic local → remote order.
     * @return int
     */
    public static function audioLength(string $url, int $postId = 0): int
    {
        $url = trim($url);
        if ($url === '') {
            return 0;
        }

        if ($postId > 0) {
            $cached = self::cachedAudioLength($postId);
            if ($cached !== null) {
                return $cached;
            }
        }

        $local = self::localAudioLength($url);
        if ($local > 0) {
            if ($postId > 0) {
                self::storeAudioLength($postId, $local);
            }

            return $local;
        }

        if (self::remoteRemaining() <= 0.0) {
            return 0;
        }

        $bytes = self::remoteSize($url);

        if ($postId > 0) {
            self::storeAudioLength($postId, $bytes);
        }

        return $bytes;
    }

    /**
     * Resolve and cache the audio size of podcast episodes.
     *
     * Intended for the WP-CLI warmer: unlike the feed, each episode gets a
     * **fresh** remote budget window (the deadline is reset per target), so a
     * maintenance run can resolve every episode in one pass while a single slow
     * episode stays bounded by the budget. With no identifiers it walks every
     * published episode that declares audio.
     *
     * @param array $postIds Episodes to process, or empty for every episode.
     * @return array Resolved and failed counts under `resolved`/`failed`.
     */
    public static function warmAudioLengths(array $postIds = array()): array
    {
        $resolved = 0;
        $failed   = 0;

        foreach (self::warmTargets($postIds) as $postId) {
            $audio = trim((string) get_post_meta($postId, self::AUDIO_META, true));
            if ($audio === '') {
                $failed++;
                continue;
            }

            // Fresh deadline for this episode: the composition budget is
            // per-request, not per maintenance run.
            self::$firstRemoteAttempt = null;

            $bytes = self::localAudioLength($audio);
            if ($bytes <= 0) {
                $bytes = self::remoteSize($audio);
            }

            self::storeAudioLength($postId, $bytes);

            if ($bytes > 0) {
                $resolved++;
            } else {
                $failed++;
            }
        }

        return array('resolved' => $resolved, 'failed' => $failed);
    }

    /**
     * WP-CLI callback warming the audio size cache.
     *
     * @return void
     */
    public static function cliWarmAudioLengths(): void
    {
        $report = self::warmAudioLengths();

        \WP_CLI::log('Tamaños resueltos: ' . (int) $report['resolved']);
        \WP_CLI::log('Tamaños fallidos: ' . (int) $report['failed']);
        \WP_CLI::success('Caché de tamaños de audio actualizada.');
    }

    /**
     * Episode identifiers the warmer must process.
     *
     * An explicit list is honoured as given; an empty one means every
     * published episode that declares audio, looked up through `get_posts()`.
     *
     * @param array $postIds Explicit identifiers, or empty for every episode.
     * @return array Positive identifiers, in the order found.
     */
    private static function warmTargets(array $postIds): array
    {
        if ($postIds !== array()) {
            return array_values(array_unique(array_map('intval', $postIds)));
        }

        if (!function_exists('get_posts')) {
            return array();
        }

        $found = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => self::AUDIO_META,
        ));

        return is_array($found) ? array_values(array_map('intval', $found)) : array();
    }

    /**
     * Size in bytes of a local attachment, or 0 when there is none/readable.
     *
     * Never touches the network nor the cache: a file that is already on disk
     * is the cheapest and most reliable source.
     *
     * @param string $url Audio URL.
     * @return int
     */
    private static function localAudioLength(string $url): int
    {
        $attachmentId = (int) attachment_url_to_postid($url);
        if ($attachmentId <= 0) {
            return 0;
        }

        $file = get_attached_file($attachmentId);
        if (!is_string($file) || $file === '' || !is_readable($file)) {
            return 0;
        }

        $size = @filesize($file);

        return is_int($size) && $size > 0 ? $size : 0;
    }

    /**
     * Cached audio size for an episode, or null when there is no live entry.
     *
     * @param int $postId Episode identifier.
     * @return int|null
     */
    private static function cachedAudioLength(int $postId): ?int
    {
        $raw = get_post_meta($postId, self::AUDIO_LENGTH_META, true);
        if (!is_array($raw) || !isset($raw['bytes'], $raw['expires'])) {
            return null;
        }

        return (int) $raw['expires'] >= time() ? (int) $raw['bytes'] : null;
    }

    /**
     * Cache an audio size on the episode, with a lifetime tied to the outcome.
     *
     * @param int $postId Episode identifier.
     * @param int $bytes  Resolved size in bytes (`0` when unresolved).
     * @return void
     */
    private static function storeAudioLength(int $postId, int $bytes): void
    {
        $ttl = $bytes > 0 ? self::AUDIO_LENGTH_SUCCESS_TTL : self::AUDIO_LENGTH_FAILURE_TTL;

        update_post_meta($postId, self::AUDIO_LENGTH_META, array(
            'bytes'   => $bytes,
            'expires' => time() + $ttl,
        ));
    }

    /**
     * Seconds left of the composition's hard remote deadline, or `0.0`.
     *
     * The first remote request of the composition stamps the clock and seals a
     * deadline at `firstRemoteAttempt + budget`; every call afterwards shares
     * that deadline, so a single resolution cannot chain redirects and ranged
     * reservations past the budget. Returns `0.0` when the budget is spent (or
     * when the budget is `0` or less, which disables remote resolution), so a
     * caller can refuse to start a request without reading the clock again.
     *
     * The budget filter is read on every call: a run that asks for `0` (or
     * less) disables remote resolution immediately, and a rendered feed keeps a
     * single deadline because the filter returns the same value throughout the
     * request.
     *
     * @return float Remaining seconds, `0.0` when the deadline is reached.
     */
    private static function remoteRemaining(): float
    {
        $budget = (float) apply_filters(self::REMOTE_BUDGET_FILTER, self::REMOTE_BUDGET_SECONDS);
        if ($budget <= 0.0) {
            return 0.0;
        }

        if (self::$firstRemoteAttempt === null) {
            self::$firstRemoteAttempt = microtime(true);
        }

        $remaining = (self::$firstRemoteAttempt + $budget) - microtime(true);

        return $remaining > 0.0 ? $remaining : 0.0;
    }

    /**
     * Timeout to apply to the next remote request, or `0.0` when exhausted.
     *
     * `min(REMOTE_TIMEOUT, remaining)`: a request already in flight can never
     * outlive the composition's deadline, and every hop of a redirect chain
     * (and the ranged reservation) re-reads the remaining time. A result of
     * `0.0` means no request may be started at all.
     *
     * @return float
     */
    private static function remoteTimeout(): float
    {
        $remaining = self::remoteRemaining();
        if ($remaining <= 0.0) {
            return 0.0;
        }

        return min((float) self::REMOTE_TIMEOUT, $remaining);
    }

    /**
     * Resolve an audio size over HTTP, following redirects manually.
     *
     * Tries a `HEAD` first; when the server refuses it or omits the length,
     * falls back to a one-byte `Range` request whose `Content-Range` carries
     * the total size. Every hop is validated before it is contacted, so a
     * redirect to an internal host is never followed.
     *
     * @param string $url Audio URL.
     * @return int Size in bytes, or 0 when it cannot be determined.
     */
    private static function remoteSize(string $url): int
    {
        $current = $url;

        for ($hop = 0; $hop <= self::REMOTE_MAX_REDIRECTS; $hop++) {
            $timeout = self::remoteTimeout();
            if ($timeout <= 0.0) {
                return 0;
            }

            if (!self::isSafeRemoteUrl($current)) {
                return 0;
            }

            $head = wp_safe_remote_head($current, array(
                'timeout'            => $timeout,
                'redirection'        => 0,
                'reject_unsafe_urls' => true,
            ));

            if (is_wp_error($head)) {
                return 0;
            }

            $code = (int) wp_remote_retrieve_response_code($head);

            if ($code >= 300 && $code < 400) {
                $location = (string) wp_remote_retrieve_header($head, 'location');
                if ($location === '') {
                    return 0;
                }

                $current = self::resolveRedirect($current, $location);
                continue;
            }

            if ($code === 200) {
                $length = wp_remote_retrieve_header($head, 'content-length');
                if (is_numeric($length) && (int) $length > 0) {
                    return (int) $length;
                }
            }

            break;
        }

        return self::remoteSizeWithRange($current);
    }

    /**
     * Resolve an audio size with a one-byte `Range` request.
     *
     * A `Content-Range` of `bytes 0-0/24594909` carries the total size after
     * the slash; anything else (a server ignoring the range, a missing or
     * malformed header) yields 0.
     *
     * @param string $url Audio URL.
     * @return int
     */
    private static function remoteSizeWithRange(string $url): int
    {
        $timeout = self::remoteTimeout();
        if ($timeout <= 0.0) {
            return 0;
        }

        if (!self::isSafeRemoteUrl($url)) {
            return 0;
        }

        $response = wp_safe_remote_get($url, array(
            'timeout'             => $timeout,
            'redirection'         => 0,
            'reject_unsafe_urls'  => true,
            'headers'             => array('Range' => 'bytes=0-0'),
            'limit_response_size' => self::REMOTE_RANGE_MAX_BYTES,
        ));

        if (is_wp_error($response)) {
            return 0;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 206 && $code !== 200) {
            return 0;
        }

        $range = trim((string) wp_remote_retrieve_header($response, 'content-range'));
        if (preg_match('#^bytes\s+\d+-\d+/(\d+)$#i', $range, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Whether a URL may be contacted without exposing internal services.
     *
     * `wp_http_validate_url()` already rejects non-`http(s)` schemes, embedded
     * credentials and private/reserved/loopback IPv4 literals; IPv6 literals
     * are checked on top as defence in depth.
     *
     * @param string $url Candidate URL.
     * @return bool
     */
    private static function isSafeRemoteUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (wp_http_validate_url($url) === false) {
            return false;
        }

        $host = trim((string) wp_parse_url($url, PHP_URL_HOST), '[]');
        if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        return true;
    }

    /**
     * Resolve a `Location` header against the URL that produced it.
     *
     * @param string $base     URL of the response carrying the redirect.
     * @param string $location Raw `Location` value (absolute or relative).
     * @return string
     */
    private static function resolveRedirect(string $base, string $location): string
    {
        $location = trim($location);
        if ($location === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = wp_parse_url($base);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $location;
        }

        $authority = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        if (strpos($location, '//') === 0) {
            return $parts['scheme'] . ':' . $location;
        }

        if (strpos($location, '/') === 0) {
            return $authority . $location;
        }

        $path  = isset($parts['path']) ? (string) $parts['path'] : '/';
        $slash = strrpos($path, '/');
        $dir   = $slash === false ? '/' : substr($path, 0, $slash + 1);

        return $authority . $dir . $location;
    }

    /**
     * Whether a transcript attachment exists and is readable.
     *
     * @param int $attachmentId Attachment identifier.
     * @return bool
     */
    public static function isAccessibleTranscript(int $attachmentId): bool
    {
        return self::isAccessibleAttachment($attachmentId);
    }

    /**
     * Absolute URL of a transcript, or '' when it is not accessible.
     *
     * @param int $attachmentId Attachment identifier.
     * @return string
     */
    public static function transcriptUrl(int $attachmentId): string
    {
        if (!self::isAccessibleTranscript($attachmentId)) {
            return '';
        }

        $url = wp_get_attachment_url($attachmentId);

        return is_string($url) ? $url : '';
    }

    /**
     * Canonicalise a transcript attachment reference to a positive integer.
     *
     * Single source of truth of the transcript contract: the metabox and the
     * feed both use it, so a manually attached SRT and a pipeline-attached one
     * are indistinguishable downstream.
     *
     * @param mixed $raw Raw value (string or number).
     * @return int Attachment ID, or 0 when the value is not a positive integer.
     */
    public static function normalizeTranscriptAttachment($raw): int
    {
        return self::attachmentIdFromMeta($raw);
    }

    /**
     * Whether a post is a published, publicly reachable podcast episode.
     *
     * @param int $postId Post identifier.
     * @return bool
     */
    public static function isPublicEpisode(int $postId): bool
    {
        if ($postId <= 0 || get_post_type($postId) !== self::POST_TYPE) {
            return false;
        }

        if (get_post_status($postId) !== 'publish') {
            return false;
        }

        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            return false;
        }

        return '' === (string) $post->post_password;
    }

    /**
     * Restrict the podcast feed query to published, unprotected episodes.
     *
     * `pre_get_posts` runs before the query, so `is_post_type_archive()` is not
     * reliable there: the scope is checked with `is_feed()` plus the query's own
     * `post_type`.
     *
     * @param mixed $query Query object exposing get()/set().
     * @return void
     */
    public static function excludeNonPublic($query): void
    {
        if (!is_feed()) {
            return;
        }

        if ((string) $query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        $query->set('has_password', false);
        $query->set('post_status', 'publish');
    }

    /**
     * Program language value (option, or the site language).
     *
     * @return string
     */
    private static function programLanguageValue(): string
    {
        $configured = trim((string) get_option(self::LANGUAGE_OPTION, ''));
        if ($configured !== '') {
            return $configured;
        }

        return (string) get_bloginfo('language');
    }

    /**
     * Option value with a non-empty fallback.
     *
     * @param string $key     Option name.
     * @param string $default Fallback used when the option is missing or empty.
     * @return string
     */
    private static function setting(string $key, string $default = ''): string
    {
        $value = get_option($key, null);
        if ($value === null || $value === false) {
            return $default;
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }

    /**
     * Return the URL only when it is an absolute HTTPS URL.
     *
     * The feed must never declare a relative or insecure cover URL.
     *
     * @param string $url Candidate URL.
     * @return string
     */
    private static function absoluteHttpsUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        return strtolower((string) $parts['scheme']) === 'https' ? $url : '';
    }

    /**
     * RFC 4122 UUIDv5 of a name within a namespace.
     *
     * SHA-1 over the 16 raw bytes of the namespace plus the name, with the
     * version (5) and variant (RFC 4122) bits fixed.
     *
     * @param string $namespaceUuid Namespace UUID in dashed form.
     * @param string $name          Name to hash.
     * @return string
     */
    private static function uuidV5(string $namespaceUuid, string $name): string
    {
        $namespaceBytes = hex2bin(str_replace('-', '', $namespaceUuid));
        if (!is_string($namespaceBytes)) {
            return '';
        }

        $bytes = substr(sha1($namespaceBytes . $name, true), 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}
