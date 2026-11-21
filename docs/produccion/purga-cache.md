# Purga de caché — Configuración operativa en producción

> **Propósito:** documentar cómo funciona la purga de caché del plugin `atareao-functionality` en
> producción, el requisito operativo para que actúe (el secreto debe estar en **todos** los
> servicios que publican contenido) y la causa raíz del issue `#202`. La configuración vive en el
> **host** (`/home/lorenzo/docker/wordpress/compose.yml`), **no** en este repositorio.

## Arquitectura

```
Cliente → Traefik (TLS) → Nginx (fastcgi_cache) → PHP-FPM (WordPress)
```

- Traefik termina el TLS; nginx recibe HTTP interno (por tanto `$scheme` = `http` dentro de nginx).
- La caché de página es `fastcgi_cache` de nginx (`fastcgi_cache_path /var/cache/nginx/fastcgi`).
- La clave de caché es `$scheme$request_method$host$request_uri` (incluye la *query string*).

## Cómo funciona la purga

El plugin engancha la purga a `transition_post_status` (primera publicación) y `post_updated`
(actualización). Al dispararse, envía peticiones con la cabecera `X-Cache-Purge: <secreto>` a las
URLs que pueden mostrar el contenido (home, permalink, archivos, feeds…).

En nginx:

```nginx
map $http_x_cache_purge $purge_active {
    default "";
    include /etc/nginx/conf.d/purge-secret/*.map;   # <secreto> "1";
}

fastcgi_cache_bypass $skip_cache$purge_active;   # si la cabecera casa → BYPASS
fastcgi_no_cache    $skip_cache;                 # $purge_active NO entra aquí
```

Con la cabecera correcta, `$purge_active` vale `1` → la petición **no se sirve de la caché**
(`x-cache-status: BYPASS`), va al backend y, como `fastcgi_no_cache` no incluye `$purge_active`,
**sobrescribe la entrada con contenido fresco**. Es una purga *por refresco*: tras la purga, la URL
vuelve a ser `HIT` pero con contenido nuevo.

## Requisito operativo (clave)

El secreto debe estar disponible en **todos** los servicios que ejecutan WordPress y disparan la
purga:

- Servicio **`wordpress`** (contenedor `atareao`, PHP-FPM): sirve las peticiones web.
- Servicio **`wpcli`** (contenedor `wpcli`): aquí corren los comandos WP-CLI que **publican y
  actualizan** contenido.

Si falta en cualquiera de ellos, la purga se omite silenciosamente desde ese contexto (`fail-closed`).

### Configuración en el host

En `/home/lorenzo/docker/wordpress/compose.yml`, el bloque `environment:` de **cada** servicio debe
incluir:

```yaml
      ATAREAO_PURGE_SECRET: ${ATAREAO_PURGE_SECRET}
```

con el valor definido en `/home/lorenzo/docker/wordpress/.env`. El mapa de nginx
(`/etc/nginx/conf.d/purge-secret/purge.map`, montado desde `nginx/purge-secret/`) debe contener el
**mismo** valor:

```
<secreto> "1";
```

> ⚠️ Este fichero de compose **no está versionado**. Si se reconstruye el host, hay que volver a
> aplicar esta configuración.

## Causa raíz del issue #202

La hipótesis del issue («el secreto no está configurado del lado de WordPress») era **incorrecta**:
el secreto **sí** estaba en el servicio `wordpress` y en el mapa de nginx, pero **faltaba en el
servicio `wpcli`**. Como los comandos que publican/actualizan contenido corren por WP-CLI
(`wpcli`), la purga se omitía y el log mostraba:

```
CachePurge: purga omitida, el secreto no está configurado.
```

**Arreglo aplicado:** añadir `ATAREAO_PURGE_SECRET: ${ATAREAO_PURGE_SECRET}` al `environment:` del
servicio `wpcli` en el compose del host y recrear solo ese servicio:

```bash
cd /home/lorenzo/docker/wordpress
cp -p compose.yml compose.yml.bak.$(date +%Y%m%d%H%M%S)
# editar compose.yml: añadir la línea en environment de wpcli
docker compose up -d wpcli
```

## Rotación del secreto

1. Cambiar el valor en `/home/lorenzo/docker/wordpress/.env`.
2. Actualizar el mismo valor en `nginx/purge-secret/purge.map`.
3. Recrear los servicios que lo consumen: `docker compose up -d wordpress wpcli nginx`.
4. Revalidar con la comprobación de más abajo.

Cualquier valor que haya quedado en el historial de git se considera comprometido y debe rotarse.

## Verificación

```bash
SECRET=$(grep -m1 ATAREAO_PURGE_SECRET /home/lorenzo/docker/wordpress/.env | cut -d= -f2)

# 1) El secreto está presente en ambos servicios
docker exec atareao env | grep ATAREAO_PURGE_SECRET
docker exec wpcli   env | grep ATAREAO_PURGE_SECRET

# 2) PHP lo lee y coincide (dentro de wpcli)
docker exec wpcli wp eval 'echo \Atareao\CachePurge::verifySecret("'$SECRET'") ? "VERIFY-OK\n" : "VERIFY-FAIL\n";'

# 3) nginx autentica la cabecera: con secreto → BYPASS; sin → HIT/MISS
curl -sS -A 'Mozilla/5.0' -H "X-Cache-Purge: $SECRET" -o /dev/null -D- https://atareao.es/ | grep -i x-cache-status
curl -sS -A 'Mozilla/5.0'                          -o /dev/null -D- https://atareao.es/ | grep -i x-cache-status
```

**Prueba de ciclo completo** (publicar por WP-CLI → purga → caché refrescada): la mtime del fichero
de caché de la home (`/var/cache/nginx/fastcgi/0/69/a17be36f7a9bd214502c9b82c30d5690`, clave
md5 de `httpGETatareao.es/`) cambia tras un `wp post update` ejecutado en `wpcli`:

```bash
KEY=a17be36f7a9bd214502c9b82c30d5690
docker exec wordpress-nginx-1 stat -c '%Y' $(docker exec wordpress-nginx-1 find /var/cache/nginx/fastcgi -name "$KEY")
docker exec wpcli wp post update <ID> --post_title="$(docker exec wpcli wp post get <ID> --field=post_title)"
sleep 3
docker exec wordpress-nginx-1 stat -c '%Y' $(docker exec wordpress-nginx-1 find /var/cache/nginx/fastcgi -name "$KEY")
```

## Rollback

Restaurar el backup del compose y recrear el servicio:

```bash
cd /home/lorenzo/docker/wordpress
cp -p compose.yml.bak.<ts> compose.yml
docker compose up -d wpcli
```
