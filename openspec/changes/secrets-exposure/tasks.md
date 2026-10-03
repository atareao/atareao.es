# Tasks: Eliminación de secretos expuestos

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación combina análisis estático (`just php-lint`, `just phpcs`) y E2E manual. phpMyAdmin es **exclusivo del entorno de desarrollo**: su verificación y su rotación se hacen en desarrollo, no en producción (cuya configuración no está versionada aquí). Quedan como **pendientes del usuario** únicamente el **E2E**, la **verificación en marcha** (requiere levantar el stack) y la **rotación del secreto de purga** (que afecta a desarrollo y producción). La implementación arranca solo tras la aprobación del usuario.

## Estado

- Implementación y análisis estático: **completados** (verificado en esta rama `fix/secrets-exposure`).
- Verificación en marcha (contenedores), rotación y E2E: **pendientes del usuario** (no se levanta el stack local en este change).
- Despliegue en producción: **pendiente del usuario** (servidor aparte).

## 1. Secreto de purga de caché

- [x] 1.1 Sacar el literal `atareao_purge_<valor-comprometido>` de `wp-content/plugins/atareao-functionality/includes/class-cache-purge.php` y leer el secreto desde la fuente provisionada por `podman secret` (variable/fichero de entorno). **Verificación:** `rg -n "atareao_purge_<valor-comprometido>" wp-content/ nginx/` sin coincidencias; la purga se omite y se registra sin filtrar el valor si el secreto no está definido.
- [x] 1.2 Comparar el secreto en tiempo constante con `hash_equals` donde proceda en PHP. **Verificación:** `rg -n "hash_equals" wp-content/plugins/atareao-functionality/includes/class-cache-purge.php`; `just phpcs` sin empeorar el baseline.
- [x] 1.3 Sacar el literal `atareao_purge_<valor-comprometido>` de `nginx/default.conf` y alimentar `$purge_active` desde el map generado por `just install` (`nginx/purge-secret/purge.map`), incluido por glob (un glob sin coincidencias no es un error: el arranque no depende del secreto). **Verificación:** `rg -n "atareao_purge_<valor-comprometido>" nginx/` sin coincidencias; con el secreto provisionado la purga legítima funciona y con un valor erróneo se rechaza.
- [x] 1.4 Crear/rotar el `podman secret` de purga (`atareao_purge_secret`) en desarrollo con `crypta` (receta `just install`, idempotente). **Verificación:** `podman secret inspect atareao_purge_secret` existe; `just install` no falla.

## 2. phpMyAdmin (solo desarrollo)

- [x] 2.1 Cambiar `PublishPort=8095:80` por `PublishPort=127.0.0.1:8095:80` en `quadlets/atareao-phpmyadmin.container`, de modo que solo escuche en loopback. **Verificación:** `rg -n "PublishPort" quadlets/atareao-phpmyadmin.container` muestra `127.0.0.1`; `ss -tlnp` solo lo lista en `127.0.0.1` y no en `0.0.0.0` ni en la interfaz de red local.
- [x] 2.2 Eliminar `MYSQL_ROOT_PASSWORD=root_password` del repositorio (phpMyAdmin no la usa para autenticar; el login se hace a mano o con `PMA_USER`/`PMA_PASSWORD` desde `podman secret`). **Verificación:** `rg -n "root_password|MYSQL_ROOT_PASSWORD" quadlets/ .justfile` sin coincidencias de valor literal en el quadlet de phpMyAdmin.
- [x] 2.3 Comprobar que la credencial root no queda expuesta en `argv`. **Verificación:** `ps`/`/proc/<pid>/cmdline` del contenedor de phpMyAdmin no contiene la contraseña.

## 3. Contraseña de base de datos fuera de `argv`

- [x] 3.1 Sacar `--password=$(cat /run/secrets/atareao_mariadb_root_password)` del healthcheck de `quadlets/atareao-mariadb.container` y usar un fichero de defaults (`--defaults-extra-file`). **Verificación:** `ps`/`/proc` no muestran la contraseña en la línea de comandos del healthcheck.
- [x] 3.2 Sacar `-e WORDPRESS_DB_PASSWORD=$WORDPRESS_DB_PASSWORD` de `.justfile` y pasar la credencial por `--secret atareao_wordpress_db_password,type=env,target=WORDPRESS_DB_PASSWORD`. **Verificación:** `ps`/`/proc` del proceso `podman run` no muestran la contraseña; `just wp -- option get siteurl` funciona.
- [ ] 3.3 Comprobar que no quedan credenciales de base de datos ni secretos de purga en claro en el `journal` de los servicios. **Verificación:** `journalctl --user -u atareao-mariadb -u atareao-wordpress -n 200` sin contraseñas en claro. **(Pendiente del usuario: requiere el stack en marcha.)**

## 4. Verificación estática (desarrollo)

- [x] 4.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) sin empeorar el baseline.
- [x] 4.2 Spec. **Verificación:** `openspec validate secrets-exposure --strict` válido; `openspec validate --all` válido.
- [x] 4.3 Búsqueda de literales comprometidos en el árbol versionado. **Verificación:** sin coincidencias del literal de purga en `wp-content/` y `nginx/`.

## 5. Verificación de la exposición en desarrollo

- [ ] 5.1 Bind loopback de phpMyAdmin. **Verificación:** `ss -tlnp` muestra el puerto solo en `127.0.0.1`; un `curl` desde otra máquina de la red local no obtiene la página de inicio de sesión; no se comprueba nada en el servidor de producción. **(Pendiente del usuario: requiere el stack en marcha.)**
- [ ] 5.2 Credenciales fuera de `ps`/`journal`. **Verificación:** `ps`/`/proc` del healthcheck de MariaDB y de `just wp` sin contraseñas; `journal` sin secretos en claro. **(Pendiente del usuario: requiere el stack en marcha.)**

## 6. Rotación de credenciales expuestas

- [ ] 6.1 **Pendiente del usuario.** Rotar la contraseña root de phpMyAdmin en **desarrollo** (el valor `root_password` estuvo publicado en git). **Verificación:** phpMyAdmin sigue operativo con el valor nuevo y el valor antiguo queda invalidado; sin tocar producción.
- [ ] 6.2 **Pendiente del usuario.** Rotar el `PURGE_SECRET` en **desarrollo y producción** (`atareao_purge_secret`) y reiniciar los servicios que lo consumen (en desarrollo, `just install` regenera `nginx/purge-secret/purge.map`). **Verificación:** la purga legítima sigue funcionando con el valor nuevo en ambos entornos y el valor antiguo se rechaza.

## 7. E2E manual (usuario)

- [ ] 7.1 **Pendiente del usuario.** E2E de purga legítima: publicar/actualizar un post y comprobar que la purga se acepta con el secreto provisionado y se rechaza con un valor erróneo. **Verificación:** en desarrollo y, para el secreto rotado, en producción.
- [ ] 7.2 **Pendiente del usuario.** E2E de exposición: confirmar desde otra máquina de la red local que phpMyAdmin solo responde en el propio equipo (loopback). **Verificación:** `curl` desde la red local no conecta; `curl` en `127.0.0.1` sí.

## 8. Entrega

- [ ] 8.1 **Pendiente del usuario.** PR por gitflow de `fix/secrets-exposure` a `development` con commits convencionales (gitmoji). **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio.
- [ ] 8.2 **Pendiente del usuario.** Marcar las tareas completadas y archivar el change. **Verificación:** `openspec archive secrets-exposure --yes` aplica los deltas (crea `openspec/specs/infrastructure/spec.md` y actualiza `cache-purge`); `openspec list` ya no muestra el change activo.
