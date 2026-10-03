# Design: Eliminación de secretos expuestos

## Context

Ver `proposal.md` — Why para la motivación y los hallazgos verificados. Este documento decide **cómo** se proveen los secretos y cómo se cierra la exposición.

Restricciones del entorno:

- El stack de desarrollo corre con **Podman rootless** como unidades systemd de usuario (`quadlets/`), y los snippets de nginx (`nginx/`) se enlazan en `~/.config/nginx/` con `just install`.
- Los secretos de MariaDB/WordPress ya se gestionan bien: `quadlets/atareao-mariadb.container:11-18` usa `Secret=atareao_wordpress_db_password` y `MARIADB_*_PASSWORD_FILE`; `.justfile:28-32` crea el secreto root con `crypta password | podman secret create … -`. El problema son los casos sueltos (purga, phpMyAdmin, contraseña de BD en `argv`).
- **No hay framework de tests ni build tools.** La verificación es `just php-lint`, `just phpcs` y E2E manual. PSR12, PHP 8.3.
- Producción es un **servidor aparte**; el despliegue y la rotación los ejecuta el usuario a mano.
- Regla de separación: la funcionalidad va en el plugin; el tema es presentación.

Puntos exactos afectados, verificados línea a línea:

| Hallazgo | Ubicación | Línea |
|---|---|---|
| Secreto de purga en PHP | `wp-content/plugins/atareao-functionality/includes/class-cache-purge.php` | `23` (`PURGE_SECRET`), `171` (header) |
| Secreto de purga en nginx | `nginx/default.conf` | `22-24` (`map $http_x_cache_purge $purge_active`) |
| phpMyAdmin publicado | `quadlets/atareao-phpmyadmin.container` | `7` (`PublishPort=8095:80`), `12` (`MYSQL_ROOT_PASSWORD=<valor-comprometido>`) |
| Contraseña en `argv` (healthcheck) | `quadlets/atareao-mariadb.container` | `27` (`--password=$(cat …)`) |
| Contraseña en `argv` (just) | `.justfile` | `341`, `350` (`-e WORDPRESS_DB_PASSWORD=…`) |

## Goals / Non-Goals

**Goals**

- Proveer el secreto de purga en tiempo de ejecución (PHP y nginx) sin que su valor viva en el repositorio.
- Rotar el valor actual comprometido y comparar en tiempo constante (`hash_equals`).
- Cerrar la exposición de phpMyAdmin (loopback + secreto) y sacar del repo la contraseña root literal.
- Sacar la contraseña de base de datos de `argv` (healthcheck y recetas de `just`).
- Dejar documentadas la verificación en desarrollo de phpMyAdmin y la rotación de la contraseña root y del secreto de purga.

**Non-Goals**

- No se cambia la funcionalidad de purga ni el comportamiento observable del stack.
- No se introduce un gestor de secretos nuevo; se usa `podman secret` + `crypta`, ya presentes.
- No se toca `CHANGELOG.md`, `docs/` ni el pipeline de release.
- No se automatiza el despliegue en producción (lo hace el usuario a mano).
- No se aborda el historial de git más allá de la rotación (ver Risks).

## Decisions

### Decisión 1: `PURGE_SECRET` desde `podman secret`, inyectado por entorno/fichero

El valor deja de ser una constante PHP y deja de estar en `nginx/default.conf`. Se crea un `podman secret` (`atareao_purge_secret`) con `crypta`. PHP lo lee de una variable/fichero de entorno (`ATAREAO_PURGE_SECRET` o `ATAREAO_PURGE_SECRET_FILE`) con fallback a vacío, y si no está definido la purga se omite y se registra el motivo (sin filtrar el valor). Nginx alimenta el `map` desde el entorno mediante plantilla renderizada al arranque.

**Consecuencias:** el secreto es rotativo; el repositorio solo referencia el nombre del secreto. La purga legítima sigue funcionando tras definir el secreto.

**Alternativa descartada:** mantener el literal y ocultarlo por ofuscación. No elimina la exposición; cualquiera con el código lo usa igual.

### Decisión 2: Nginx con plantilla renderizada (`envsubst`) en lugar de valor literal

El `map` de nginx necesita el valor en el fichero de configuración. Para no versionarlo, `default.conf` pasa a ser una plantilla (`default.conf.template`) con `${PURGE_SECRET}` y el valor se renderiza en el arranque con `envsubst` a un include no versionado (por ejemplo `conf.d/`), alimentado por el secreto montado. Mientras el render no exista, `$purge_active` queda vacío y no hay bypass.

**Consecuencias:** nginx sigue usando `map` (comportamiento idéntico) pero el valor se genera en tiempo de ejecución.

**Alternativa descartada:** un `map` que lea de fichero. nginx no lee valores de `map` desde fichero en tiempo de ejecución sin módulos externos. El render por plantilla es el mecanismo estándar del contenedor.

### Decisión 3: Comparación en tiempo constante en PHP

El envío del header no cambia (sigue enviando el secreto), pero cualquier comprobación en PHP pasa a `hash_equals`. El cambio de mecanismo de provisión no altera la firma pública ni el flujo de purga.

**Consecuencias:** se evita la filtración por temporización en comparaciones de secretos.

**Alternativa descartada:** `==`/`===`. Vulnerable a ataques de temporización.

### Decisión 4: phpMyAdmin solo en loopback, con secreto y solo en desarrollo

phpMyAdmin es **exclusivo del entorno de desarrollo** (no existe en producción). Se cambia `PublishPort=8095:80` por `PublishPort=127.0.0.1:8095:80`, de modo que solo escuche en loopback y sea inalcanzable desde la red local. La contraseña root literal se sustituye por un `podman secret` gestionado con `crypta` y referenciado por `*_FILE`/secreto `type=env`. Se rota el valor `root_password` que estuvo publicado. **Este change no toca ni endurece producción**: la configuración de producción no está versionada aquí y no se incluyen tareas ni comandos sobre el servidor para phpMyAdmin.

**Consecuencias:** el acceso administrativo queda restringido a la máquina del desarrollador, y la credencial deja de estar en git. El único punto que sí cruza a producción es el secreto de purga, que se rota por su cuenta (Decisión 6).

**Alternativa descartada:** retirar phpMyAdmin del stack. El desarrollador lo usa; el loopback cierra la exposición sin perder la comodidad. Alternativa descartada: confiar en el firewall de la red local. No es verificable desde el repo y no protege frente a otro equipo de la misma red.

### Decisión 5: Contraseña de BD fuera de `argv`

- **Healthcheck de MariaDB:** usar un fichero de defaults (`--defaults-extra-file=/run/secrets/…`) o un secreto `type=env`, en lugar de `--password=$(cat …)`, que pasa el valor por `argv`.
- **Receta `wp` de `just`:** pasar la credencial con `podman run --secret … ,type=env,target=WORDPRESS_DB_PASSWORD` (o `--env-file` con permisos restringidos), de modo que el valor no aparezca en la línea de comandos del `podman run` en el host.

**Consecuencias:** la contraseña deja de ser legible en `ps`/`/proc`/`journal` por otros usuarios locales.

**Alternativa descartada:** usar variable de entorno "normal" (`-e VAR=valor`). El propio `-e` con el valor forma parte de `argv` del proceso `podman` en el host y queda visible igualmente.

### Decisión 6: Verificación y rotación documentadas

El change documenta en `tasks.md` la verificación y el plan de rotación de las credenciales expuestas:

- **phpMyAdmin (solo desarrollo):** verificación del bind loopback con `ss -tlnp` y `curl` desde la red local; rotación del secreto root en desarrollo.
- **Secreto de purga (`PURGE_SECRET`):** es el único que se despliega en producción; su rotación la ejecuta el **usuario** en desarrollo y producción, y es tarea pendiente del usuario.

Las tareas E2E manual y de rotación del secreto de purga quedan **pendientes del usuario**. Las de phpMyAdmin se resuelven en el entorno de desarrollo. **No hay tareas de endurecimiento ni verificación de phpMyAdmin en producción.**

**Consecuencias:** la propuesta queda cerrada en desarrollo; solo la rotación del secreto de purga en producción espera acción del usuario.

**Alternativa descartada:** incluir la verificación de phpMyAdmin en producción. No aplica: producción no está versionada aquí y no contiene phpMyAdmin.

## Risks / Trade-offs

- **[Rotación incompleta de un secreto]** → Si se rota en un sitio y no en el otro (PHP vs nginx), la purga deja de funcionar. Mitigado porque ambos leen del mismo `podman secret` y la rotación reinicia ambos servicios (Decisión 1).
- **[Historial de git con los valores antiguos]** → Aunque se rote, los valores siguen en el historial. Mitigación: la rotación invalida el valor comprometido; no se reescribe el historial en este change (evitaría reescrituras peligrosas en `main`).
- **[phpMyAdmin accesible desde la red local]** → Si el bind queda en `0.0.0.0`, otro equipo de la red podría alcanzarlo. Mitigado con el bind a `127.0.0.1` (Decisión 4); no se depende del firewall de la red local. No se incluye ninguna acción sobre producción (no versionada aquí).
- **[Historial de git con `root_password`]** → Aunque se rote, el valor antiguo sigue en el historial. Mitigación: la rotación lo invalida; no se reescribe el historial en este change. phpMyAdmin es solo de desarrollo, así que el valor no se despliega.
- **[Nginx no arranca si falta el secreto]** → Si el render por plantilla falla, nginx podría quedar sin `map`. Mitigado dejando `$purge_active` vacío por defecto (sin bypass) y documentando el orden de arranque.
- **[Receta `wp` rota si la imagen no soporta el mecanismo elegido]** → Se valida en desarrollo antes de dar el change por cerrado; alternativa `--env-file` con permisos `0600`.
- **[Sin framework de tests]** → La verificación es estática (`just php-lint`, `just phpcs`) y E2E manual; no hay tests automatizados que protejan contra regresiones.

## Migration Plan

Orden obligatorio (lo ejecuta el usuario; este change solo especifica):

1. **Crear/rotar los secretos** en desarrollo: `atareao_purge_secret` y el secreto de la contraseña root de phpMyAdmin, con `crypta password | podman secret create … -`.
2. **Aplicar los cambios de provisión** (PHP, plantilla de nginx, quadlets, `.justfile`) y reinstalar/enlazar (`just install`).
3. **Reiniciar los servicios** que consumen los secretos (`wordpress`, `nginx`, `mariadb`, `phpmyadmin`) y comprobar que la purga legítima funciona.
4. **Verificar phpMyAdmin en desarrollo** (bind loopback con `ss`/`curl`) y **rotar** la contraseña root.
5. **Rotar el secreto de purga en producción** (lo ejecuta el usuario) y verificar que la purga legítima sigue funcionando.
6. **Rollback:** revertir el commit de provisión restaura los ficheros; como los valores quedan rotados, hay que volver a fijar el secreto en la fuente correspondiente. Las opciones de datos no se tocan.

## Verification

- **Lint**: `just php-lint` → 0 errores; `just phpcs` sin empeorar el baseline.
- **Estática**: `openspec validate secrets-exposure --strict` válido; búsqueda del literal `atareao_purge_<valor-comprometido>` y de `root_password`/`MYSQL_ROOT_PASSWORD` en el árbol versionado → sin coincidencias.
- **Purga legítima**: publicar/actualizar un post y comprobar que la purga se acepta con el secreto provisionado y se rechaza con un valor erróneo.
- **Puertos**: `ss -tlnp` en desarrollo muestra phpMyAdmin solo en `127.0.0.1`; no es alcanzable desde la red local.
- **`argv`**: `ps`/`/proc` no muestran la contraseña de BD en el healthcheck ni en `just wp`.
- **Rotación (usuario)**: rotación del secreto de purga en desarrollo y producción; rotación de la contraseña root de phpMyAdmin en desarrollo. No hay pasos sobre phpMyAdmin en producción.

## Open Questions

Ninguna. La fuente de provisión, el mecanismo de nginx, la exposición de phpMyAdmin y el paso de credenciales fuera de `argv` quedan resueltos arriba. La elección exacta entre `type=env` y `--env-file` en la receta `wp` se valida en desarrollo durante la implementación, sin cambiar las specs ni el enfoque.
