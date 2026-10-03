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
- Running PHP commands inside a disposable local container
- Keeping a persistent PHP CLI container available for local tests
- Running WP-CLI inside a disposable container
- Packaging theme/plugin for distribution

## Table of contents

- [Quick start](#quick-start)
- [Usage & common commands](#usage--common-commands)
- [Local URLs & ports](#local-urls--ports)
- [Getting backup from VPS & import](#getting-backup-from-vps--import)
- [Third-party JavaScript](#third-party-javascript)
- [Troubleshooting](#troubleshooting)
- [Repository layout](#repository-layout)
- [Contributing](#contributing)
- [License](#license)

## Why this approach

- Reproducible local environment built on Podman + systemd user units (quadlets).
- `just` recipes make recurring tasks simple and consistent.
- Keeps WordPress sources, infrastructure unit files and helper scripts together for easier development and deployment.

## Quick start

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

4. Optional: install WordPress using WP-CLI (runs inside the WordPress CLI container). Never hardcode the admin password in the repository. Note that anything passed as `--admin_password=...` ends up in the process **argv**, visible in `ps` (same class as GE-14): treat such a value as a throwaway and rotate it, or replace the marker locally and avoid passing real production secrets on the command line:

```fish
# Sustituye el marcador por un valor local desechable. Un valor real en argv es
# visible en `ps`; no lo uses para credenciales de producción.
set -x WP_ADMIN_PASSWORD <TU_PASSWORD_ADMIN>

just wp -- core install --url="http://localhost:8091" --title="Local" --admin_user=admin --admin_password="$WP_ADMIN_PASSWORD" --admin_email=you@example.com
```

## Usage & common commands

- `just install` — link quadlets and nginx config, create secrets
- `just uninstall` — remove links and stop units
- `just start` / `just stop` — start/stop quadlet-managed services
- `just status` — show link and service status
- `just logs service=<name>` — follow logs for a service
- `just build` — create zip packages for theme and plugin
- `just php -- <php-args>` — run `php` in `atareao-php-cli` if available, otherwise in a disposable container
- `just php-lint` — lint all PHP files in the theme and plugin
- `just php-lint-changed` — lint only changed PHP files detected by git
- `just php-shell` — open an interactive shell in `atareao-php-cli`
- `just phpcs` — run PHP_CodeSniffer on theme and plugin using `PSR12` by default
- `just phpcbf` — auto-fix PHP_CodeSniffer issues where possible
- `just wp -- <wp-cli-args>` — run WP-CLI inside the WordPress container

Examples:

```fish
just php -- -v
just php -- -l wp-content/themes/atareao-theme/functions.php
just php-lint
just php-lint-changed
just phpcs
just phpcs wp-content/themes/atareao-theme/functions.php
just phpcbf 'wp-content/themes/atareao-theme wp-content/plugins/atareao-functionality' PSR12
just php -- -r 'echo PHP_VERSION, PHP_EOL;'
systemctl --user start atareao-php-cli.service
just php-shell
```

## Getting backup from VPS & import

1. Export a dump from your VPS database (example run **on the VPS**, outside this repository and its container stack):

```bash
mariadb-dump -u <USER> -p <DATABASE> > backup.sql
```

2. Import into local MariaDB managed by the quadlet:

```fish
set SECRET_ID (podman secret inspect atareao_wordpress_db_password | jq -r '.[].ID')
set PASSWORD (crypta lookup $SECRET_ID)
cat backup.sql | podman exec -i atareao-mariadb mariadb -u wp_user -p$PASSWORD wordpress
```

3. Fix site URLs inside WP:

```fish
just wp -- search-replace 'https://old.example' 'http://localhost:8091' --precise --recurse-objects
just wp -- option update home "http://localhost:8091"
just wp -- option update siteurl "http://localhost:8091"
```

## Cache purge system

La portada y los listados de este sitio usan **Nginx FastCGI Cache** con un TTL de 1 hora para mejorar el rendimiento. Cuando publicas un artículo nuevo, la caché no se invalida automáticamente, por lo que el contenido puede tardar hasta 60 minutos en aparecer.

Para solucionarlo, hay un sistema de **purga programática** que funciona en dos capas:

### Nginx (`nginx/default.conf`)

Un mapa detecta el header `X-Cache-Purge`. El valor del secreto **no está en
el repositorio**: `just install` genera `nginx/purge-secret/purge.map` desde el
`podman secret` y aquí solo se incluye el directorio con un glob (un glob sin
coincidencias no es un error en nginx, así que el arranque nunca depende de que
el secreto esté generado):

```nginx
map $http_x_cache_purge $purge_active {
    default "";
    include /etc/nginx/conf.d/purge-secret/*.map;
}
```

Cuando el header coincide con el secreto, la petición **bypassea la caché** (sirve desde PHP) pero **sí se guarda en caché** la respuesta fresca, para que el siguiente visitante la reciba actualizada.

### Plugin (`includes/class-cache-purge.php`)

La clase `CachePurge` se engancha a `transition_post_status` y, cuando un post se publica por primera vez (draft → publish), envía peticiones con el header `X-Cache-Purge` a todas las URLs que pueden mostrar ese contenido:

- Portada (`/`)
- Blog page (`page_for_posts`)
- Feed RSS
- Archivo del tipo de post (`/tutoriales/`, `/podcast/`, etc.)
- Categorías del post
- Tags del post
- Taxonomías personalizadas

### Secreto compartido y su provisión

El valor del secreto **nunca vive en el repositorio**. Se provee con `podman
secret` (driver `crypta`) y se inyecta en ambas capas en tiempo de ejecución:

| Capa | Cómo recibe el secreto |
|------|------------------------|
| PHP (`CachePurge`) | `ATAREAO_PURGE_SECRET_FILE=/run/secrets/atareao_purge_secret` (o la variable `ATAREAO_PURGE_SECRET`); la comparación usa `hash_equals` y falla en cerrado si no hay secreto |
| Nginx | `just install` genera `nginx/purge-secret/purge.map` desde el secreto y el contenedor monta ese directorio; `default.conf` lo incluye con un glob |

El `podman secret` se llama `atareao_purge_secret` y lo crea `just install`
(de forma idempotente) con `crypta`. El mapa generado tiene permisos `600` y
está en `.gitignore`.

### Rotación del secreto de purga

Para rotar el secreto de purga, sin editar ni recomitar ficheros del repo:

```fish
# 1. Eliminar el secreto actual
podman secret rm atareao_purge_secret

# 2. Recrearlo (o simplemente volver a ejecutar `just install`, que es idempotente
#    y además regenera el map de nginx)
crypta password | podman secret create atareao_purge_secret -

# 3. Regenerar el map que consume nginx
just install

# 4. Recargar/reiniciar nginx (y WordPress, que lee el secreto del fichero)
systemctl --user restart atareao-nginx.service atareao-wordpress.service
```

En **producción** estos pasos se ejecutan **a mano** en el servidor (este
repositorio solo versiona el entorno de desarrollo): rotar el mismo secreto,
regenerar el include de nginx con el valor nuevo y recargar los servicios.

### Rotación de phpMyAdmin

La contraseña root que estuvo publicada en git (`root_password`) debe rotarse en
desarrollo. phpMyAdmin **no** usa `MYSQL_ROOT_PASSWORD` para autenticar: el
login se hace a mano con la credencial root de MariaDB. Si en el futuro se
quiere autologin, hay que inyectar `PMA_USER`/`PMA_PASSWORD` desde un
`podman secret` del quadlet, nunca en claro.

### Credenciales comprometidas en el historial de git

Los valores que estuvieron versionados en el pasado siguen siendo recuperables
del historial de git (`git rev-list --all`), por lo que se consideran
**comprometidos de forma permanente**. La mitigación es **rotar y aceptar**:
**no** se reescribe el historial de git (nada de BFG, `git filter-repo` ni
`force-push`). Cualquier copia antigua del repositorio contiene esos valores y
deben tratarse como inválidos.

- **Secreto de purga** (`atareao_purge_<valor-comprometido>`): **ya rotado en
  producción** por el usuario. El valor antiguo queda invalidado; el nuevo se
  provisiona con el procedimiento de «Rotación del secreto de purga».
- **Contraseña root de MariaDB** (`atareao_mariadb_root_password`): la rota el
  usuario en **desarrollo y producción** como acción de despliegue.

#### Rotación de la contraseña root de MariaDB

```fish
# Desarrollo (este repositorio):
podman secret rm atareao_mariadb_root_password
crypta password | podman secret create atareao_mariadb_root_password -
systemctl --user restart atareao-mariadb.service atareao-wordpress.service
```

En **producción** la rotación se ejecuta a mano en el servidor: se cambia la
contraseña root de MariaDB, se actualiza el `podman secret` que la provee y se
reinician MariaDB y sus clientes. Este repositorio **no** versiona la
configuración de producción.

#### Verificación del árbol versionado

El árbol versionado (HEAD) no contiene ningún secreto real: el literal del
secreto de purga está ausente y `root_password`/`MYSQL_ROOT_PASSWORD` solo
figuran como *nombre* de secret o como texto de remediación. El valor real del
secreto de purga no se escribe en el repositorio (se usa el marcador
`atareao_purge_<valor-comprometido>`); la comprobación automatizada de que el
literal real no aparece la realiza el **arnés externo** (comprobación **E1**).
`root_password`/`MYSQL_ROOT_PASSWORD` se revisan con:

```bash
git grep -In 'root_password\|MYSQL_ROOT_PASSWORD'   # solo nombre/remediación
```

### Flujo completo

```
Publicas artículo
  → WordPress dispara transition_post_status
  → CachePurge recopila URLs afectadas
  → Envía peticiones con header X-Cache-Purge: <secreto>
  → Nginx bypassea caché, sirve desde PHP, guarda respuesta fresca
  → Siguiente visitante recibe página actualizada
```

## Third-party JavaScript

El JavaScript de terceros vendorizado (`wp-content/plugins/atareao-functionality/assets/vendor/js-yaml.min.js` y `wp-content/plugins/atareao-functionality/assets/blocks/crontab-helper/qrcode.min.js`) y los minificados propios del tema tienen su **procedencia, versión, licencia y hash de integridad** registrados en [`THIRD-PARTY.md`](THIRD-PARTY.md). Consulta ese registro antes de actualizar cualquier `.min.js`.

## Troubleshooting

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

## Local URLs & ports

The development stack publishes (see `quadlets/`):

| Service | URL / port | Note |
|---------|------------|------|
| nginx (site) | http://localhost:8091 | quadlet `atareao-nginx.container` (`PublishPort=8091:80`) |
| phpMyAdmin | http://127.0.0.1:8095 | quadlet `atareao-phpmyadmin.container` (`PublishPort=127.0.0.1:8095:80`): el puerto se publica **solo en loopback**, pero el contenedor también se une a `traefik.network` y queda accesible por el host de Traefik (`Host(\`phpmyadmin.localhost\`)`) si Traefik está en marcha |

Use `http://localhost:8091` as the local site URL in all WP-CLI `core install`, `search-replace` and `home`/`siteurl` commands.

## Repository layout

- `quadlets/` — quadlet unit files (.container, .network, .volume, etc.)
- `nginx/` — nginx configuration snippets to be linked into `~/.config/nginx`
- `php-fpm/` — PHP-FPM performance overrides bind-mounted into the WordPress container
- `wp-content/` — WordPress content: `themes/atareao-theme/` and `plugins/atareao-functionality/` are the only tracked sources
- `.justfile` — recipes used to manage the stack

## Contributing

Contributions are welcome. Open an issue or a pull request with a clear description.

## License

See the `LICENSE` file in this repository.

## Contact

Open an issue or PR for help customizing tasks, ports.

---

_This README is tuned for GitHub: clear headings, badges, quick start, and operational commands focused on the `.justfile` workflows._
