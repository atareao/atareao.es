# AGENTS.md — atareao.es

WordPress site with a Podman quadlet dev stack and `just` task runner.

## Prerequisites

- **fish shell** — all `just` recipes use `#!/usr/bin/env fish`.
- **podman** — containers run rootless as user systemd units.
- **crypta** — required for `just install` (podman secret creation).
- **just** — command runner. Run `just --list` to see all recipes.

## Architecture

- Only `wp-content/` is tracked. WordPress core lives in containers — never edit core files in containers.
- Theme: `wp-content/themes/atareao-theme/` (handwritten PHP, monolithic `style.css`, no framework).
- Plugin: `wp-content/plugins/atareao-functionality/` (registers 5 CPTs, Gutenberg block, `/tools/` microsite with rewrite rules).
- **Separation rule:** all functionality and business logic goes in the plugin. The theme is for presentation only — templates, styles, and front-end scripts.
- Quadlets: `quadlets/` — systemd container units (`.container`, `.network`, `.volume`). Symlinked into `~/.config/containers/systemd/` by `just install`.
- Nginx: `nginx/` — config snippets symlinked into `~/.config/nginx/`. Acts as reverse proxy to the WordPress FPM container.
- PHP-FPM overrides: `php-fpm/zz-atareao-performance.conf` — bind-mounted into the WordPress container.

## Dev environment

```
just install   # link quadlets + nginx config, create podman secrets
just start     # start all services
just stop      # stop all services
just status    # show link/run status per container
```

Theme and plugin directories are bind-mounted into the WordPress container — edits reflect immediately with no rebuild.

Containers: wordpress (FPM), mariadb, nginx (port 8080), valkey (Redis-alternative cache), phpmyadmin (port 8081), php-cli (persistent workspace).

## Coding standards

**No build tools exist.** No `package.json`, `composer.json`, webpack, or CSS preprocessors. All JS and CSS are edited directly as source files.

```bash
just php-lint          # lint all PHP files
just php-lint-changed  # lint only git-changed PHP files
just phpcs             # PSR12 check (default paths: theme + plugin)
just phpcbf            # auto-fix PSR12 violations
```

- PSR12 is the enforced standard.
- PHP version: 8.3 (matches `wordpress:cli-php8.3` image).
- Gutenberg block JS uses vanilla `wp.element.createElement` — no JSX or transpile step.
- Plugin version constant: `ATAREAO_PLUGIN_VERSION` in `atareao-functionality.php`.

## Running commands

```bash
just php -- -l path/to/file.php          # lint single file
just php-shell                           # interactive shell (requires php-cli running)
just wp -- search-replace 'old' 'new'    # WP-CLI (requires wordpress+mariadb running)
just logs service=atareao-wordpress      # journalctl follow for a service
```

## Building for distribution

```bash
just build   # creates atareao-theme.zip and atareao-functionality.zip in repo root
```

Zip files are gitignored.

## Important gotchas

- **No tests.** No phpunit, no Jest, nothing. Manual verification only.
- **No autoloader.** PHP classes are manually `require_once`'d. Keep includes in sync.
- **Secret-dependent.** WP-CLI commands depend on `podman secret` + `crypta`. If secrets are missing, re-run `just install`.
- **The plugin is a microsite.** Custom rewrite rules for `/tools/crontab/`, `/tools/uuid/`, etc. are in `class-post-types.php` and served from `templates/`. Do not delete those templates without updating rewrite rules.
- **Matrix protocol integration.** Contact form (`includes/class-contact-form.php`) and comment notifications (`includes/class-matrix-config.php`) go to Matrix (not email). Credentials stored as WP options. Both classes live in the plugin. The theme's `page-contact.php` is the presentation template only — no business logic.
- **No i18n files.** `Text Domain` headers are declared but no `.po`/`.mo` files exist.
- **Volumes are persistent.** Use `just clean_volumes` to wipe DB and WP data. Bind-mounts (theme/plugin) are not affected.
- **PHP-FPM config is bind-mounted.** `php-fpm/zz-atareao-performance.conf` is mounted into the WordPress container as `/usr/local/etc/php-fpm.d/`. If deleted, the container may fail to start.
