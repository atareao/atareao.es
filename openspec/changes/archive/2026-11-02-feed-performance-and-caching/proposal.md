# Proposal: Feed rápido y cacheado

## Why

El 2026-11-02, medido en producción, el feed tarda **~15 s** (`/feed/`) y **~14 s**
(`/podcast/feed/`) y el primer `upstream timed out` aparece a las **14:31**, antes de cualquier
despliegue. En un host de **2 CPUs** con un enjambre de lectores RSS, esa lentitud satura el pool de
FPM y provoca `499`/timeouts intermitentes. Son **dos causas independientes** y ambas se pueden
corregir:

1. **El presupuesto de red del feed no acota una composición.** `remoteBudgetExhausted()` solo se
   comprueba **entre** episodios: una sola resolución remota encadena hasta `REMOTE_MAX_REDIRECTS+1`
   `HEAD` **más** una reserva `Range`, cada una con `REMOTE_TIMEOUT = 5 s`, y puede bloquear un único
   episodio ~25 s sin que el presupuesto de 5 s lo frene. Con 831 episodios de audio externo
   resueltos en vivo, la composición se estira a decenas de segundos.
2. **El feed está excluido de la caché a propósito.** Tanto `nginx/default.conf` como
   `nginx/pro/wp.conf` mapean `"~*/feed/" 1;` a `$skip_cache`, de modo que **cada** lector RSS golpea
   PHP. Un feed es idéntico para todos los lectores, así que esa exclusión solo compra latencia.

## What Changes

- **Composición del feed acotada de verdad (frente 1).** La resolución remota del tamaño pasa a
  respetar una **fecha límite por composición**: cada llamada HTTP se limita al tiempo restante, la
  cadena de redirecciones se corta al agotarse, y un episodio nunca bloquea más que el presupuesto
  restante. El feed siempre termina en un tiempo acotado; lo no resuelto emite `0` y se reintenta en
  la siguiente composición.
- **Feed cacheado siempre (frente 2).** Se deja de excluir el feed de la caché y se le da una
  **política de caché dedicada** (TTL corto, `stale-on-error`): los lectores reciben la copia cacheada
  —o la obsoleta— en vez de bloquearse esperando a PHP. Se conserva la frescura extendiendo la purga
  para que publicar/actualizar un podcast invalide **también** `GET /podcast/feed/`, no solo `/feed/`.

## Capabilities

### New Capabilities

- `feed-cache`: contrato de la caché de feeds en el front (nginx): qué feeds se cachean, con qué
  política (TTL, revalidación, `stale-on-error`) y cómo se invalida al publicar/actualizar, sin servir
  contenido caducado indefinidamente.

### Modified Capabilities

- `podcast-feed`: la composición pasa a respetar una **fecha límite dura** (no solo entre episodios),
  de modo que la resolución remota no puede exceder el presupuesto de red en una sola llamada.
- `cache-purge`: la purga de un podcast publicado/actualizado SHALL incluir el feed del tipo de
  contenido (`/podcast/feed/`) además del feed principal, para refrescar el feed cacheado.

## Impact

- **Código:** `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php`
  (`audioLength`, `remoteBudgetExhausted`, `remoteSize`, `remoteSizeWithRange`),
  `wp-content/plugins/atareao-functionality/includes/class-cache-purge.php` (`getUrlsToPurge`).
- **Configuración de front:** `nginx/default.conf` (dev) y `nginx/pro/wp.conf` (producción) — map
  `$skip_cache`, bloque `location ~ \.php$` / reglas de feed y `fastcgi_cache_*`.
- **Tests:** `tests/` (harness PHP sin framework, invocado por `just test`) y una comprobación CLI de la config nginx de feeds, cableada en `.github/workflows/ci.yml`.
- **No toca:** el contrato de namespaces/metadatos del feed, ni la guarda SSRF, ni la forma del XML.
