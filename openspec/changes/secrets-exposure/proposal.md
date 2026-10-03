# Proposal: Eliminación de secretos expuestos en el repositorio

## Why

El repositorio versiona hoy al menos tres credenciales en claro que cualquiera con acceso al código (o al historial de git) puede usar. Todos los hallazgos están verificados línea a línea:

1. **Secreto de purga de caché público.** `wp-content/plugins/atareao-functionality/includes/class-cache-purge.php:23` define `private const PURGE_SECRET = 'atareao_purge_<valor-comprometido>';` y `nginx/default.conf:24` lleva **el mismo literal** (`atareao_purge_<valor-comprometido> "1";`). Como está en git, cualquiera puede enviar `X-Cache-Purge: atareao_purge_<valor-comprometido>` y forzar el bypass de la caché de nginx en masa, lo que es un vector de agotamiento de PHP-FPM. Además, la comparación del lado de nginx se hace por igualdad de `map`, pero el valor está fijado en el repositorio; hay que comprobar que la comparación en PHP sea en tiempo constante (`hash_equals`).
2. **phpMyAdmin expuesto con contraseña root en git.** `quadlets/atareao-phpmyadmin.container:7` publica `PublishPort=8095:80` y `:12` fija `MYSQL_ROOT_PASSWORD=root_password`. El contenedor está unido a `atareao-network` (la red de la base de datos) y a `traefik.network`. phpMyAdmin es **solo del entorno de desarrollo** (no existe en producción), pero al publicar en `0.0.0.0` queda alcanzable desde la red local, y la contraseña root está en git.
3. **Contraseña de BD en `argv`.** `.justfile:341,350` pasa `-e WORDPRESS_DB_PASSWORD=$WORDPRESS_DB_PASSWORD` y `quadlets/atareao-mariadb.container:27` la expone en el healthcheck (`--password=$(cat /run/secrets/atareao_mariadb_root_password)`). En ambos casos el valor acaba en la línea de comandos del proceso, visible en `ps`/`/proc`/`journal` para cualquier usuario local.

Contexto: los secretos de MariaDB/WordPress **sí** se gestionan bien con `podman secret` + `crypta` (ver `quadlets/atareao-mariadb.container:11-18` y `.justfile:28-32`); el problema son estos casos sueltos. Producción es un servidor aparte (aquí solo se versiona el entorno de desarrollo) y **el despliegue lo hace el usuario a mano**.

## What Changes

- **Sacar `PURGE_SECRET` del código y de `default.conf`**: el valor deja de estar en `class-cache-purge.php` y en `nginx/default.conf` y se inyecta desde `podman secret` (o variable de entorno leída por PHP y por el `map` de nginx). Se **rota** el valor actual y la comparación en PHP pasa a `hash_equals`.
- **phpMyAdmin (solo desarrollo)**: publicar el puerto únicamente en loopback (`PublishPort=127.0.0.1:8095:80`), eliminar `MYSQL_ROOT_PASSWORD=root_password` del repositorio y gestionarlo por `podman secret`, y **rotar** la contraseña root que estuvo expuesta. No hay acción alguna sobre producción.
- **Contraseña de BD fuera de `argv`**: pasarla por fichero/entorno tanto en el healthcheck de MariaDB como en las recetas de `just`.
- **Verificación en desarrollo y rotación**: verificar el bind loopback de phpMyAdmin con `ss`/`curl` desde la red local, y rotar la contraseña root (desarrollo) y el secreto de purga (desarrollo y producción, ejecutado por el usuario).
- **Se conserva la funcionalidad**: la purga legítima y el stack de desarrollo siguen funcionando. Se cambia el **mecanismo de provisión** del secreto, no el comportamiento.
- **No se toca** `CHANGELOG.md`, `docs/` ni el pipeline de release.

## Capabilities

### New Capabilities

- `infrastructure`: exposición de phpMyAdmin (solo desarrollo) con bind a loopback, provisión de secretos mediante `podman secret` y garantía de que las credenciales no son visibles en `ps`/`journal`, con su verificación y rotación.

### Modified Capabilities

- `cache-purge`: el requirement «Authentication via secret header» pasa a exigir que el secreto no viva en el repositorio, que se provea desde `podman secret`/entorno, que se pueda rotar y que la comparación sea en tiempo constante.

## Impact

- **Archivos** (a modificar en la fase de implementación, no ahora):
  - `wp-content/plugins/atareao-functionality/includes/class-cache-purge.php` (leer el secreto de entorno/fichero y comparar con `hash_equals`).
  - `nginx/default.conf` (dejar de llevar el literal; `map` alimentado desde el entorno/fichero del secreto).
  - `quadlets/atareao-phpmyadmin.container` (bind a `127.0.0.1` y `MYSQL_ROOT_PASSWORD` sustituido por `podman secret`).
  - `quadlets/atareao-mariadb.container` (healthcheck sin `--password=$(cat …)`).
  - `.justfile` (receta `wp` sin la contraseña en `argv`).
  - `README.md` del plugin / nota de rotación — sin tocar `CHANGELOG.md` ni `docs/`.
- **Specs**: nuevo `openspec/specs/infrastructure/spec.md` vía el delta `specs/infrastructure/spec.md`; `openspec/specs/cache-purge/spec.md` actualizado vía `specs/cache-purge/spec.md` (1 requirement MODIFIED).
- **No cambia**: la purga legítima al publicar/actualizar contenido, el resto del stack de desarrollo, el microsite `/tools/`, la analítica, el login/logout ni las notificaciones Matrix.
- **Dependencias**: ninguna nueva (`podman secret` + `crypta` ya están en uso).
- **Compatibilidad**: PHP 8.3, PSR12, entorno de desarrollo con Podman; el despliegue de producción lo ejecuta el usuario a mano.
