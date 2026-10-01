# Cache Purge Specification

## Purpose

Gestiona la purga programática de la caché de Nginx al publicar o actualizar contenido en WordPress, asegurando que los visitantes reciban siempre la versión más reciente de las páginas.

## Requirements

### Requirement: Purge on first publication

When a post transitions to `publish` status from any non-publish status, the system SHALL send purge requests with the header `X-Cache-Purge` to all URLs that may display that post.

URLs to purge SHALL include:
- Site homepage
- Blog page (if different from homepage)
- RSS feed
- Post type archive (if applicable)
- Category archive pages
- Tag archive pages
- Custom taxonomy archive pages

**File:** `wp-content/plugins/atareao-functionality/includes/class-cache-purge.php`

#### Scenario: New post published
- **WHEN** a draft post transitions to `publish`
- **THEN** purge requests are sent to the homepage, blog page, RSS feed, post type archive, and any category/tag/taxonomy archive pages associated with the post

### Requirement: Purge on post update (publish → publish)

When a post that is already published is updated (publish → publish), the system SHALL send purge requests to the same set of URLs listed above. The `post_updated` hook SHALL be used instead of `transition_post_status` for this case to avoid double-purge.

#### Scenario: Published post updated
- **WHEN** a published post is updated via the editor
- **THEN** purge requests are sent to all URLs that may display that post

#### Scenario: Autosave does not trigger purge
- **WHEN** `DOING_AUTOSAVE` is true
- **THEN** no purge requests are sent

#### Scenario: Revision save does not trigger purge
- **WHEN** `wp_is_post_revision()` returns true
- **THEN** no purge requests are sent

### Requirement: Authentication via secret header

Purge requests SHALL include the header `X-Cache-Purge` with a configurable shared secret value that matches the value expected by Nginx.

#### Scenario: Purge request includes auth header
- **WHEN** a purge request is sent
- **THEN** it includes the `X-Cache-Purge` header with the configured secret

### Requirement: Non-blocking purge requests

Purge requests SHALL be sent as non-blocking HTTP requests with a 0.1-second timeout to avoid slowing down the post-publish/update response to the editor.

#### Scenario: Purge is fire-and-forget
- **WHEN** a purge request is sent
- **THEN** the request has `timeout` of 0.1 seconds and `blocking` set to false