# atareao.es — Local WordPress Stack (quadlets + Podman + nginx)

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![GitHub stars](https://img.shields.io/github/stars/atareao/atareao.es.svg?style=social)](https://github.com/atareao/atareao.es/stargazers)
[![Issues](https://img.shields.io/github/issues/atareao/atareao.es.svg)](https://github.com/atareao/atareao.es/issues)
[![Last commit](https://img.shields.io/github/last-commit/atareao/atareao.es.svg)](https://github.com/atareao/atareao.es/commits/main)

> Developer-friendly local WordPress stack using `just` recipes and Podman quadlets.

This repository contains the WordPress site sources (theme, plugin) and a `just` task runner (`.justfile`) that centralizes routines for:

- Installing quadlets (systemd user units for containers)
- Creating required secrets
- Linking nginx configuration into a user directory
- Running WP-CLI inside a disposable container
- Packaging theme/plugin for distribution

Table of contents

- [Quick start](#quick-start)
- [Usage & common commands](#usage--common-commands)
- [Getting backup from VPS & import](#getting-backup-from-vps--import)
- [Troubleshooting](#troubleshooting)
- [Repository layout](#repository-layout)
- [Contributing](#contributing)
- [License](#license)

Why this approach

- Reproducible local environment built on Podman + systemd user units (quadlets).
- `just` recipes make recurring tasks simple and consistent.
- Keeps WordPress sources, infrastructure unit files and helper scripts together for easier development and deployment.

Quick start

1. Clone the repository:

```bash
git clone https://github.com/atareao/atareao.es
cd atareao.es
```

2. Install quadlets, create secrets, and link nginx config:

```fish
just install
```

3. Start services (the quadlet systemd user units will start containers):

```fish
just start
podman ps
```

4. Optional: install WordPress using WP-CLI (runs inside the WordPress CLI container):

```fish
just wp -- core install --url="http://localhost:8080" --title="Local" --admin_user=admin --admin_password=ChangeMe123 --admin_email=you@example.com
```

Usage & common commands

- `just install` — link quadlets and nginx config, create secrets
- `just uninstall` — remove links and stop units
- `just start` / `just stop` — start/stop quadlet-managed services
- `just status` — show link and service status
- `just logs service=<name>` — follow logs for a service
- `just build` — create zip packages for theme and plugin
- `just wp -- <wp-cli-args>` — run WP-CLI inside the WordPress container

Getting backup from VPS & import

1. Export a dump from your VPS database (example):

```bash
docker exec wordpress-mariadb-1 mariadb-dump -u <USER> -p<PASSWORD> <DATABASE> > backup.sql
```

2. Import into local MariaDB managed by the quadlet:

```fish
set SECRET_ID (podman secret inspect atareao_wordpress_db_password | jq -r '.[].ID')
set PASSWORD (crypta lookup $SECRET_ID)
cat backup.sql | podman exec -i atareao-mariadb mariadb -u wp_user -p$PASSWORD wordpress
```

3. Fix site URLs inside WP:

```fish
just wp -- search-replace 'https://old.example' 'http://localhost:8080' --precise --recurse-objects
just wp -- option update home "http://localhost:8080"
just wp -- option update siteurl "http://localhost:8080"
```

Troubleshooting

- Podman secrets missing: `podman secret ls` — recreate with:

```fish
crypta password | podman secret create atareao_wordpress_db_password -
```

- systemd user units not visible: reload and inspect:

```bash
systemctl --user daemon-reload
ls -l ~/.config/containers/systemd
```

- nginx not serving: verify files exist in `~/.config/nginx` and reload your nginx instance (if running system-wide nginx):

```bash
ls -l ~/.config/nginx
sudo systemctl reload nginx
```

- WP-CLI errors: check containers and logs:

```bash
podman ps
podman logs -f atareao-wordpress
just logs service=atareao-wordpress
systemctl --user status atareao-wordpress
```

Repository layout

- `quadlets/` — quadlet unit files (.container, .network, .volume, etc.)
- `nginx/` — nginx configuration snippets to be linked into `~/.config/nginx`
- `wp/` — WordPress content: themes and plugins
- `.justfile` — recipes used to manage the stack

Contributing

Contributions are welcome. Open an issue or a pull request with a clear description. For changes to `just` recipes, include examples and rationale. If you want an automated `first-run` recipe to scaffold WP with sensible defaults, open an issue or request and I can add it.

Badges

- Replace `<OWNER>/<REPO>` in the badges at the top with your GitHub owner and repository name to enable live status (stars, issues, last commit).

License

See the `LICENSE` file in this repository.

Contact

Open an issue or PR for help customizing tasks, ports, SMTP settings, or to request automated first-run support.

---

_This README is tuned for GitHub: clear headings, badges, quick start, and operational commands focused on the `.justfile` workflows._
