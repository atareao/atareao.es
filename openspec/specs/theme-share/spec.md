# Theme Share Specification

## Purpose

Provide social share buttons (X, Mastodon, Telegram) and a native Web Share API button for blog posts, allowing readers to share content across platforms with a consistent look and feel.

## Requirements

### Requirement: atareao_share_links() return type and structure

The function SHALL return an HTML string `<div class="entry-share">`. The HTML SHALL include:
1. A `<span class="share-label">Comparte en</span>` label
2. Three anchor links — X, Mastodon, and Telegram — each as 28×28 icon buttons
3. A `<button class="share-btn-native">` for native Web Share API as the 4th element

When `$post` is null, the function SHALL fall back to the global `$post` object.
When no valid post ID can be resolved, it SHALL return an empty string `''`.

**File:** `wp-content/themes/atareao-theme/functions.php:475-539`

#### Scenario: Returns HTML for valid post
- **WHEN** `atareao_share_links()` is called with a valid post ID
- **THEN** it returns a string containing `<div class="entry-share">` with X, Mastodon, Telegram anchor links and a native share button

#### Scenario: Falls back to global post
- **WHEN** `atareao_share_links()` is called without arguments and global `$post` is set
- **THEN** it uses the global post to generate share links

#### Scenario: Returns empty string for invalid post
- **WHEN** `atareao_share_links()` is called with an invalid post ID
- **THEN** it returns an empty string `''`

### Requirement: Native Web Share API button

The output HTML SHALL include a `<button class="share-btn-native">` with `data-url` and `data-title` attributes containing the raw (non-encoded) permalink and decoded title. The button SHALL use the SVG icon `#share` from the sprite sheet.

When the user clicks the button, the JavaScript (enqueued as `atareao-share`) SHALL:
- Use `navigator.share()` when available (mobile browsers, Chrome 89+)
- Fall back to copying the URL to clipboard and showing a `.share-toast` notification with text "Enlace copiado"

#### Scenario: Web Share API available
- **WHEN** user clicks `.share-btn-native` and `navigator.share` is available
- **THEN** the native OS share sheet is invoked with the post title and URL

#### Scenario: Web Share API unavailable
- **WHEN** user clicks `.share-btn-native` and `navigator.share` is not available
- **THEN** the URL is copied to clipboard and a toast notification "Enlace copiado" is displayed

#### Scenario: Clipboard API unavailable
- **WHEN** user clicks `.share-btn-native`, `navigator.share` is unavailable, and `navigator.clipboard` is unavailable
- **THEN** the URL is copied using `document.execCommand('copy')` fallback

### Requirement: CSS for native share and toast

The stylesheet SHALL define:
- `.share-btn-native`: 28×28px inline-flex button, transparent background, no border, border-radius 6px, cursor pointer, transition on hover
- `.share-btn-native:hover`: translateY(-2px)
- `.share-toast`: fixed bottom-center toast notification, initially invisible (opacity: 0)
- `.share-toast--show`: opacity 1, visible

**File:** `wp-content/themes/atareao-theme/style.css:1014-1062`

#### Scenario: Native share button styled correctly
- **WHEN** the page renders a `.share-btn-native` element
- **THEN** it appears as a 28×28px inline-flex button consistent with other share buttons

#### Scenario: Toast animation plays
- **WHEN** the toast appears
- **THEN** it fades in with opacity transition and disappears after 2 seconds

### Requirement: SVG sprite includes share icon

The `sprite.svg` file SHALL contain a `#share` symbol with the share network icon path.

**File:** `wp-content/themes/atareao-theme/assets/images/sprite.svg`

Contains 13 symbols: `#apple`, `#cc`, `#github`, `#ivoox`, `#linkedin`, `#mastodon`, `#rss`, `#share`, `#spotify`, `#telegram`, `#x`, `#youtube`. Inlined in `footer.php` via `file_get_contents()`.

#### Scenario: Share icon renders
- **WHEN** a page includes `<use href="#share"/>`
- **THEN** the share icon is displayed correctly

### Requirement: Script enqueue

The theme's `atareao_theme_scripts()` function SHALL enqueue `js/share.min.js` as `atareao-share` with defer strategy.

#### Scenario: Script is loaded on all pages
- **WHEN** any page loads
- **THEN** `share.min.js` is enqueued with the `defer` strategy