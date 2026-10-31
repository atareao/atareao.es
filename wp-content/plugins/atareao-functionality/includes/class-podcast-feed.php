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
     * Episode meta key holding the transcript attachment ID (integer).
     */
    public const TRANSCRIPT_META = 'transcript-srt';

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
        add_action('rss2_ns', array(__CLASS__, 'namespaceDeclarations'));
        add_action('rss2_head', array(__CLASS__, 'channelElements'));
        add_action('rss2_item', array(__CLASS__, 'itemElements'), 10, 1);
        add_action('pre_get_posts', array(__CLASS__, 'excludeNonPublic'));
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
        $type       = self::setting('atareao_podcast_type', 'serial');
        $category   = self::setting('atareao_podcast_category', 'Technology');
        $ownerName  = self::setting('atareao_podcast_owner_name', (string) get_bloginfo('name'));
        $ownerEmail = self::setting('atareao_podcast_owner_email', (string) get_option('admin_email', ''));
        $explicit   = self::setting('atareao_podcast_explicit', 'no');
        $cover      = self::absoluteHttpsUrl(self::setting('atareao_podcast_cover'));

        if (!in_array($type, array('serial', 'episodic'), true)) {
            $type = 'serial';
        }
        if (!in_array($explicit, array('no', 'yes', 'clean'), true)) {
            $explicit = 'no';
        }

        $out  = '<itunes:author>' . esc_html($author) . '</itunes:author>' . "\n";
        $out .= '<itunes:type>' . esc_html($type) . '</itunes:type>' . "\n";
        $out .= '<itunes:explicit>' . esc_html($explicit) . '</itunes:explicit>' . "\n";
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
        $duration = trim((string) get_post_meta($id, self::DURATION_META, true));

        $out = '<enclosure url="' . esc_attr($audio) . '"'
            . ' type="' . esc_attr(self::audioMediaType($audio)) . '"'
            . ' length="' . esc_attr((string) self::audioLength($audio)) . '" />' . "\n";

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

        $transcriptId = self::normalizeTranscriptAttachment(
            get_post_meta($id, self::TRANSCRIPT_META, true)
        );
        if ($transcriptId > 0 && self::isAccessibleTranscript($transcriptId)) {
            $out .= '<podcast:transcript url="' . esc_attr(self::transcriptUrl($transcriptId)) . '"'
                . ' type="' . esc_attr(self::TRANSCRIPT_MEDIA_TYPE) . '"'
                . ' language="' . esc_attr(self::programLanguageValue()) . '" />' . "\n";
        }

        echo $out;

        return $out;
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
     * A size of `0` is the honest neutral value: the episode stays
     * subscribable instead of advertising a made-up number.
     *
     * @param string $url Audio URL.
     * @return int
     */
    public static function audioLength(string $url): int
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
     * Whether a transcript attachment exists and is readable.
     *
     * @param int $attachmentId Attachment identifier.
     * @return bool
     */
    public static function isAccessibleTranscript(int $attachmentId): bool
    {
        if ($attachmentId <= 0) {
            return false;
        }

        $file = get_attached_file($attachmentId);

        return is_string($file) && $file !== '' && is_readable($file);
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
