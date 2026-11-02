# Design: Feed rápido y cacheado

## Context

Ver `proposal.md` — Why. Estado y restricciones que condicionan el diseño:

- La resolución del tamaño de audio vive en `class-podcast-feed.php`: `audioLength()` consulta adjunto
  local → metadato cacheado → (si el presupuesto no se ha agotado) `remoteSize()`. `remoteSize()`
  encadena hasta `REMOTE_MAX_REDIRECTS = 3` `HEAD` y, si ninguna declara longitud, una reserva
  `Range`. Cada petición usa `timeout => REMOTE_TIMEOUT = 5 s`. `remoteBudgetExhausted()` **solo** se
  evalúa antes de empezar el episodio: `self::$firstRemoteAttempt` se sella en el primer intento y a
  partir de ahí se compara `microtime(true) - firstRemoteAttempt >= budget`. Nada acota la llamada en
  vuelo.
- El front excluye los feeds de la caché con `"~*/feed/" 1;` en el `map ... $skip_cache` de
  `nginx/default.conf` (dev) y `nginx/pro/wp.conf` (producción). El `fastcgi_cache_key` es
  `"$scheme$request_method$host$request_uri"`, ya distingue feed por URI.
- La purga (`class-cache-purge.php`) ya dispara `get_feed_link()` (feed principal) al publicar, pero
  **no** el feed del tipo de contenido del podcast (`/podcast/feed/`).
- **Medido en producción (2026-11-02) — causa raíz real del feed lento:** con los 839 episodios de
  audio ya **cacheados**, `audioLength()` seguía tardando **24,26 s**: `localAudioLength()` llama a
  `attachment_url_to_postid()` (consulta a BD, no lectura de disco) por episodio y **antes** de mirar la
  caché. Medición aislada: leer el tamaño cacheado de 839 episodios = **0,11 s**; `attachment_url_to_postid()`
  ×839 = **24,26 s**. La red no era el cuello de botella (0 episodios sin caché).
- No hay framework de tests: harness PHP propio (`tests/`, invocado por `just test`) más linters.
  Producción se opera con Docker en el host; el repo versiona la config en `nginx/pro/`.

## Goals / Non-Goals

**Goals:**

- Garantizar que **ninguna** composición del feed pueda exceder el presupuesto de red por una sola
  llamada remota en vuelo.
- Servir el feed desde caché de front por defecto, con una política explícita (TTL corto y
  `stale-on-error`), sin depender de PHP en el camino caliente.
- Mantener la frescura: publicar/actualizar invalida el feed cacheado.

**Non-Goals:**

- No cambia el XML, los namespaces, la numeración ni la guarda SSRF.
- No se cachea el feed de comentarios ni rutas de máquina (`/.well-known/...`), que siguen excluidas.
- No se introduce una dependencia externa (plugin de cacheo de feeds): la caché es la del front.

## Decisions

### D1. Fecha límite dura por composición (no solo entre episodios) — frente 1

En vez de comprobar el presupuesto antes de cada episodio, el primer intento remoto sella un
**`deadline = firstRemoteAttempt + budget`**. `remoteSize()`/`remoteSizeWithRange()` reciben el tiempo
restante y lo aplican como `timeout` de cada llamada (`min(REMOTE_TIMEOUT, deadline - now)`); si el
restante es `<= 0`, no se contacta nada y se devuelve `0`. El bucle de redirecciones corta al agotar
la fecha límite. Resultado: la composición queda acotada por `budget` + lo que dure la última llamada
ya iniciada (no por `budget × episodios`).

*Alternativa considerada:* sacar toda resolución remota del request y hacerla solo en un warmer
asíncrono. Se descarta porque la spec actual ya promete resolución en vivo (`HEAD`/`Range` al componer
el feed) y quitarla cambiaría el contrato; acotarla es suficiente y no rompe escenarios existentes.

### D2. Feed cacheado con política dedicada — frente 2

- Quitar `"~*/feed/" 1;` del `map ... $skip_cache` en `nginx/default.conf` y `nginx/pro/wp.conf`.
- Añadir un `location` de feed con `fastcgi_cache_valid 200 5m;` (TTL corto, feeds cambian a diario),
  `fastcgi_cache_use_stale error timeout invalid_header updating http_500 http_503;`,
  `fastcgi_cache_background_update on;` y `fastcgi_cache_lock on;`.
- Conservar el bypass por cookie de sesión (`wordpress_logged_in`) para que un autor vea su contenido
  fresco; el resto de lectores RSS reciben la copia cacheada.

*Alternativa considerada:* cachear el feed completo en un transient de WP. Se descarta: nginx es más
barato (no arranca PHP) y ya existe la maquinaria de purga.

### D3. La purga cubre el feed del tipo de contenido

`getUrlsToPurge()` añade `get_post_type_archive_feed_link($post->post_type)` cuando el tipo tiene
archive. Así publicar un podcast invalida `/podcast/feed/`, y el feed principal sigue purgándose vía
`get_feed_link()`. Combinado con D2, la frescura no depende solo del TTL.

### D4. El tamaño cacheado se consulta ANTES que cualquier resolución (caché primero)

`audioLength()` pasa a resolver en el orden **caché → local → HEAD → Range → 0**. Un acierto de caché
(episodio identificado y metadato vigente) evita el `attachment_url_to_postid()` de `localAudioLength()`
y toda petición de red. Además, el resultado **local** se cachea también (antes solo se cacheaba el
remoto), para que el camino caliente quede en ~0,1 s. El orden remoto y la guarda SSRF no cambian.

*Alternativa considerada:* optimizar `localAudioLength()` (cachear el ID de adjunto, o usar una consulta
más barata). Se descarta como primer paso porque la caché ya contiene la respuesta: consultarla primero
elimina el coste sin tocar el descubrimiento de adjuntos. Con la llamada de un solo argumento (sin
`postId`) no hay caché y se conserva el orden clásico local → remoto.

### D5. Calentar la caché de feeds tras la purga (después de entregar la respuesta)

La purga del front es **bypass + refresco**, no borrado: `fastcgi_cache_bypass` hace que la petición de
purga no lea la caché y, como `fastcgi_no_cache` no se activa, **vuelve a guardar** la respuesta. Pero
esas peticiones se disparan **no bloqueantes** (`timeout => 0.1`), así que sobre HTTPS pueden abortarse
antes de completar el refresco; en ese caso la entrada antigua permanece y el lector, aunque rápido, ve
contenido viejo hasta que caduque el TTL (5 min). Y si la entrada está **ausente** (caducada o expulsada),
el primer lector paga la composición en frío.

Para cerrar esa ventana, tras `firePurgeRequests()` se agenda un **calentado** de las URLs de feed (feed
principal + feed del tipo de contenido) que se ejecuta **después** de entregar la respuesta al editor:

- en PHP-FPM se llama `fastcgi_finish_request()` antes de calentar (respuesta del admin ya enviada) y
  luego se hace un `GET` **bloqueante** acotado (UA no bloqueado, sin cabecera de purga ni cookies) de
  modo que, cuando la entrada falta, nginx compone y **guarda** el feed; cuando la entrada está, es un
  acierto rápido e inocuo;
- donde no exista `fastcgi_finish_request` (p. ej. WP-CLI) se hace un `GET` acotado best-effort;
- el presupuesto total está acotado (~20 s): cada petición limita su `timeout` al tiempo restante y no
  se inicia ninguna tras agotarse; se protege el worker con `@set_time_limit(0)` + `ignore_user_abort(true)`
  tras `fastcgi_finish_request()`.

*Alternativa considerada:* calentado no bloqueante + `fastcgi_ignore_client_abort on` en el `location` de
feeds. Se descarta como primera vía porque sobre HTTPS el fire-and-forget puede abortar antes del
handshake; el calentado bloqueante tras `fastcgi_finish_request` es determinista y no bloquea al editor.

*Alcance:* solo feeds (no el set completo de purga) para no provocar la estampida de re-cacheo ya
observada al vaciar la caché entera. Las páginas HTML purgadas siguen confiando en su TTL largo y en
`background_update`/`use_stale`.

*Nota de frescura (preexistente):* si la purga no bloqueante se aborta, el feed puede mostrar la copia
anterior hasta el TTL; el calentado no lo empeora y, cuando la entrada falta, lo mejora.

## Risks / Trade-offs

- **[Feed obsoleto tras publicar]** → purga al publicar/actualizar (D3) + TTL corto (5 min) +
  `background_update`. La purga es no bloqueante, como el resto.
- **[Cachear también el tamaño local]** → si un audio local se reemplaza por otro de distinto tamaño, el valor cacheado queda obsoleto hasta la caducidad (larga). Mitigación: los audios locales son nuestros y cambian por publicación, que ya dispara purga; el TTL de acierto acota el resto.
- **[Cachear contenido por usuario]** → los feeds no varían por sesión; el bypass por cookie
  `wordpress_logged_in` se mantiene. La `cache_key` incluye `$request_uri`.
- **[Respuesta cacheada con `Content-Type` erróneo]** → se cachean también las cabeceras; el
  `Content-Type: application/rss+xml; charset=UTF-8` del feed se preserva. Verificable por CLI.
- **[El warmer ahora depende del filtro de presupuesto]** → `warmAudioLengths()` reinicia `firstRemoteAttempt` por episodio, así que el filtro `atareao_podcast_audio_length_budget_seconds` se relee por target; si se fija a `<= 0`, el warmer deja de resolver. Documentado aquí: el warmer debe correr con el presupuesto por defecto.
- **[Timeout peor si el primer episodio arranca justo antes del deadline]** → el `timeout` de esa
  llamada se recorta al restante; la composición sigue acotada.

## Migration Plan

1. **Código:** desplegar plugin por el script verificado (`just deploy-prod`, con backup + `sha256` +
   `chown 82:82`).
2. **Config de front:** versionar `nginx/pro/wp.conf`; en producción, recargar con `nginx -s reload`
   (no reinicio). Rollback: restaurar la config previa y recargar; restaurar el `.bak` del plugin.
3. **Precalentado (mitigación operativa, no de código):** una pasada del warmer
   `wp atareao podcast-audio-length` deja los tamaños en post-meta y reduce las resoluciones en vivo.

## Open Questions

- Valor exacto del TTL del feed (5 min propuesto) — ajustable sin cambiar la spec ni el enfoque.
