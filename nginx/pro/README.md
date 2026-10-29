# Configuración nginx de producción

Copia de referencia de la configuración nginx que sirve **atareao.es en producción**.
No es la configuración que usa el entorno de desarrollo local: son dos configuraciones
distintas y viven en directorios distintos.

Ambos ficheros se copiaron del servidor de producción el **2026-10-29** con:

```bash
ssh atareao 'cat /home/lorenzo/docker/wordpress/nginx.conf' > nginx/pro/nginx.conf
ssh atareao 'cat /home/lorenzo/docker/wordpress/nginx/wp.conf' > nginx/pro/wp.conf   # saneado
```

Son ficheros de **solo lectura de referencia**: nada del stack local los carga. Sirven
para auditar, comparar y preparar cambios que luego se despliegan a mano en producción.

## Procedencia

| Fichero en el servidor de producción | Montaje en el contenedor | Contenido |
| --- | --- | --- |
| `/home/lorenzo/docker/wordpress/nginx.conf` | `/etc/nginx/nginx.conf` | Contexto `http` completo: caches FastCGI y proxy, `set_real_ip_from`, zonas de `limit_req`, mapas de bots y rutas de máquina, gzip. |
| `/home/lorenzo/docker/wordpress/nginx/wp.conf` | `/etc/nginx/conf.d/wp.conf` | Server block de `atareao.es`: exclusiones de caché, cabeceras de seguridad, ubicaciones y FastCGI. |

El contenedor destino es `wordpress-nginx-1` (imagen `nginx:alpine`, servicio `nginx` del
proyecto compose desplegado en `/home/lorenzo/docker/wordpress/`). Los montajes declarados
en su `compose.yml` son `./nginx.conf:/etc/nginx/nginx.conf` y `./nginx:/etc/nginx/conf.d`.

## Diferencias con la configuración de desarrollo

La configuración de desarrollo son **`nginx/nginx.conf`** y **`nginx/default.conf`**
(cargados por el stack Podman local: `server_name atareao.localhost`, upstream
`fastcgi_pass atareao-wordpress:9000`). Esta copia de `nginx/pro/` es la de
**producción**: `server_name atareao.es`, upstream `fastcgi_pass wordpress:9000`.

Producción añade respecto a desarrollo:

- **Mapas de exención para las rutas de máquina.** El bloqueo por User-Agent (`$bad_bot`,
  que corta `curl`, `wget`, `python-requests`, `Go-http`, etc.) se modula con
  `$mcp_machine_path` para no bloquear a los clientes máquina en `/mcp`,
  `/.well-known/ai-catalog.json` y `/wp-json/atareao/v1/mcp`. De ahí salen
  `$bad_bot_blocked` (bloqueo salvo en esas rutas) y `$mcp_endpoint` / `$method_blocked`
  (OPTIONS solo se admite en `/mcp`, para el preflight CORS).
- **Zonas de `limit_req`.** Rate limiting por endpoint: `bot`, `general`, `login`,
  `xmlrpc`, `ajax`, `rest`, más `limit_conn_zone` para conexiones. Desarrollo no
  define ninguna.
- **`set_real_ip_from`.** Producción confía en `172.0.0.0/8`, `192.0.0.0/8` y
  `10.0.0.0/8` para reconstruir la IP real desde `X-Forwarded-For` (detrás del proxy).
  Desarrollo no lo necesita.
- Además, `worker_connections` sube de 1024 (dev) a 2048 (pro) y producción ajusta
  `fastcgi_ignore_headers` y `fastcgi_cache_use_stale`.

## Secreto de purga de caché

El valor del secreto de purga de caché **no se versiona en ningún fichero del repositorio**
(así lo exige `openspec/specs/cache-purge/spec.md`). En su lugar, el `map` incluye un
fichero generado en tiempo de ejecución:

```nginx
map $http_x_cache_purge $purge_active {
    default "";
    include /etc/nginx/conf.d/purge-secret/*.map;
}
```

Es exactamente el patrón que usa la configuración de desarrollo (`nginx/default.conf`).
El `include` es un **glob**: si no hay ningún fichero `*.map` en
`/etc/nginx/conf.d/purge-secret/`, nginx **no falla** al arrancar y `$purge_active` queda
vacío (la purga simplemente no se activa). El fichero lo genera `just install`, que crea el
`podman secret atareao_purge_secret`, lo lee con `crypta` y escribe
`nginx/purge-secret/purge.map` (ignorado por git vía `.gitignore`).

> **Tarea pendiente de despliegue.** El `nginx.conf`/`wp.conf` que hoy corre en producción
> todavía lleva el literal del secreto escrito dentro del `map`. Esta copia de `nginx/pro/`
> ya está saneada (usa el `include`), pero **migrar producción al `include` está pendiente**:
> mientras no se despliegue, producción sigue con el valor en claro en su fichero y con la
> ausencia de `/etc/nginx/conf.d/purge-secret/`. La rotación del secreto solo es posible
> cuando producción use el `include`.

## Despliegue

Estos ficheros son una **copia de referencia**: editarlos aquí no cambia nada en el servidor.
El despliegue es manual:

1. Copiar cada fichero a su ruta de origen en el servidor de producción:
   - `nginx/pro/nginx.conf` → `/home/lorenzo/docker/wordpress/nginx.conf`
   - `nginx/pro/wp.conf` → `/home/lorenzo/docker/wordpress/nginx/wp.conf`
2. Validar la sintaxis dentro del contenedor:

   ```bash
   ssh atareao bash -c 'podman exec wordpress-nginx-1 nginx -t'
   ```

3. Recargar sin cortar el servicio:

   ```bash
   ssh atareao bash -c 'podman exec wordpress-nginx-1 nginx -s reload'
   ```

Si se migra al `include` del secreto, hay que crear además
`/home/lorenzo/docker/wordpress/nginx/purge-secret/` con el `purge.map` correspondiente
antes de recargar.
