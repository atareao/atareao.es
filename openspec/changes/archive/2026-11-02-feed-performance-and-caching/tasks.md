# Tasks: Feed rápido y cacheado

> Estado: **IMPLEMENTADO Y AUDITADO** (RED→GREEN→REFACTOR, 1 auditoría independiente con correcciones).
> Ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Alcance: (1) composición del feed acotada por una fecha límite dura; (2) feed cacheado en el front
> con política dedicada y purga del feed del tipo de contenido.
> Pendiente: E2E en producción (4.3) y archive (5.x).

## 0. Contexto y decisiones previas

- [x] 0.1 Medición base: `/feed/` ≈ 15 s y `/podcast/feed/` ≈ 14 s (UA de lector RSS), primer
      `upstream timed out` a las 14:31 (preexistente al despliegue de v1.21.0).
- [x] 0.2 Causa 1 confirmada: `remoteBudgetExhausted()` solo se evaluaba entre episodios; una resolución
      encadena `REMOTE_MAX_REDIRECTS+1` `HEAD` + `Range`, cada una con `timeout = 5 s`.
- [x] 0.3 Causa 2 confirmada: `"~*/feed/" 1;` en el `map $skip_cache` de las dos configs; la purga no
      añadía `get_post_type_archive_feed_link()`.
- [x] 0.4 Aprobación explícita del titular ("adelante").

## 1. Fecha límite dura en la composición del feed (`podcast-feed`)

### 1.1 RED
- [x] 1.1.1–1.1.3 Casos nuevos añadidos a `tests/test-valet.php`; `just test` en rojo:
      `FAIL: (feed) el timeout del HEAD se recorta al tiempo restante…`, `FAIL: …cada salto de la
      redirección acota su timeout…`.

### 1.2 GREEN
- [x] 1.2.1 `remoteBudgetExhausted()` → `remoteRemaining(): float` (sella `deadline = firstRemoteAttempt + budget`).
- [x] 1.2.2 `remoteTimeout() = min(REMOTE_TIMEOUT, restante)`; `remoteSize()`/`remoteSizeWithRange()`
      no inician petición con restante `<= 0` y cortan la cadena de redirecciones al agotarse.
- [x] 1.2.3 `just test` verde en los casos nuevos; `just php-lint` sin errores.

### 1.3 REFACTOR
- [x] 1.3.1 Helpers consolidados con docstrings; sin cambio funcional.
- [x] 1.3.2 `just phpcs` sin violaciones nuevas (las 2 mostradas son preexistentes: warning de "side
      effects" sistémico y `Expected 1 newline at end of file` en `class-cache-purge.php`, ya en HEAD).

## 1b. Orden caché-primero en `audioLength()` (`podcast-feed`, D4)

### 1b.1 RED
- [x] 1b.1.1 Escenarios añadidos a `tests/test-valet.php` (contador `mock_attachment_url_lookups`
      instrumentado en `tests/wp-stubs.php`): acierto de caché sin lookup de adjunto ni red; tamaño
      local resuelto **y cacheado** (segunda lectura sin tocar el adjunto); composición con caché llena
      que no escala con consultas de adjunto. `just test` en rojo: 6 fallos nuevos
      (`FAIL: … un acierto de caché NO consulta attachment_url_to_postid (Expected: 0, got: 1)`,
      `FAIL: … una composición con caché llena NO consulta attachment_url_to_postid por episodio
      (Expected: 0, got: 200)`, etc.).

### 1b.2 GREEN
- [x] 1b.2.1 `audioLength()` reordenado a **caché → local → remoto → 0**; el tamaño resuelto
      localmente se cachea en el episodio. Con `$postId = 0` se conserva el orden clásico local → remoto.
- [x] 1b.2.2 `just test` verde en los casos nuevos (1117 passed, 1 preexistente); `just php-lint` sin
      errores.

### 1b.3 REFACTOR
- [x] 1b.3.1 Docstrings de `audioLength()` actualizados al orden caché-primero; contador del doble
      documentado; línea >120 envuelta. `just phpcs` sin violaciones nuevas (mismos 2 errores
      preexistentes de la suite SD); `just test` sin regresiones.

## 2. Purga del feed del tipo de contenido (`cache-purge`)

### 2.1 RED
- [x] 2.1.1–2.1.2 Casos añadidos; `just test` en rojo:
      `FAIL: (purge) publicar un podcast purga el feed del tipo de contenido (/podcast/feed/)` (y update).

### 2.2 GREEN
- [x] 2.2.1 `getUrlsToPurge()` añade `get_post_type_archive_feed_link($post->post_type)` cuando hay archive.
- [x] 2.2.2 `just test` + `just php-lint` verdes.

### 2.3 REFACTOR
- [x] 2.3.1 Sin cambio funcional; comentario 4b añadido.
- [x] 2.3.2 `just phpcs` + `just test` sin regresiones.

## 2b. Calentado del feed tras la purga (`cache-purge`, ADDED)

### 2b.1 RED
- [x] 2b.1.1–2b.1.4 Escenarios añadidos a `tests/test-valet.php` (doble de
      `fastcgi_finish_request()` y captura de args de `wp_remote_get()` en `tests/wp-stubs.php`);
      `just test` en rojo: `Fatal error: Uncaught Error: Call to undefined method
      Atareao\CachePurge::getFeedUrlsToWarm() in /workspace/tests/test-valet.php:8473` (exit 255).

### 2b.2 GREEN
- [x] 2b.2.1 `getFeedUrlsToWarm()` (feed principal + feed del archivo), `warmFeeds()` (GET sin
      `X-Cache-Purge` ni cookies, UA `Atareao-Feed-Warmer/1.0`, `fastcgi_finish_request()` antes de
      calentar, fallback no bloqueante), `scheduleWarm()`/`warmScheduledFeeds()` (hook `shutdown`) y
      `firePurgeRequests()` devuelve `bool` para no calentar sin purga.
- [x] 2b.2.2 `just test` → `Total passed: 1139, Total failed: 1` (único fallo preexistente y ajeno);
      `just php-lint` sin errores.

### 2b.3 REFACTOR
- [x] 2b.3.1 Docstrings del porqué añadidos; sin cambio funcional.
- [x] 2b.3.2 `just phpcs` sobre `class-cache-purge.php` → mismos 2 avisos preexistentes
      (side-effects y `Expected 1 newline at end of file`, idénticos en HEAD); `just test` sin
      regresiones.

### 2b.4 Auditoría independiente (BLOCKER + mejoras)
- [x] 2b.4.1 BLOCKER — presupuesto total del calentado (`WARM_BUDGET_SECONDS = 20`, `deadline` y
      corte del bucle) y protección del worker tras `fastcgi_finish_request()` (`@set_time_limit(0)`,
      `ignore_user_abort(true)`); `timeout` por petición = `min(15, restante)`.
- [x] 2b.4.2 Mejora 2 — guarda `$warmScheduled` para registrar el callback `shutdown` una sola vez.
- [x] 2b.4.3 Mejora 3 — `warmRequestArgs(bool $fpm, float $remaining)` puro y testeable; tests afirman
      `timeout`/`blocking` en ambas ramas y el `timeout` real emitido en FPM.
- [x] 2b.4.4 Mejora 4 — tests de idempotencia (dos eventos + un `shutdown` ⇒ cada feed una vez) y de
      alcance (solo feeds, sin portada/archivo/taxonomías).
- [x] 2b.4.5 Mejora 5 — `$cp_warm_reset` limpia `mock_actions['shutdown']` y, por reflexión, el estado
      estático del calentado; los escenarios no dependen del orden.
- [x] 2b.4.6 Evidencia: RED `Fatal error: Uncaught ReflectionException: Property
      Atareao\CachePurge::$warmScheduled does not exist in /workspace/tests/test-valet.php:8475`;
      GREEN `just test` → `Total passed: 1152, Total failed: 1` (solo el preexistente); `just php-lint`
      67 ficheros sin errores; `just phpcs` → mismos 2 avisos preexistentes.

## 3. Caché de feeds en el front (`feed-cache`)

### 3.1 RED
- [x] 3.1.1 Comprobación CLI definida en `scripts/feed-cache-config.test.sh` (asserts a–d sobre ambas
      configs). **RED** contra la config pre-change (`git show HEAD:…`): `TOTAL=20 PASS=2 FAIL=18`,
      falla por la regla `"~*/feed/" 1;` presente. Evidencia pegada en el informe de auditoría.

### 3.2 GREEN
- [x] 3.2.1 `"~*/feed/" 1;` eliminada del `map $skip_cache` en ambas configs.
- [x] 3.2.2 `location ~* /feed/?$` con política dedicada (TTL `200 5m`, `stale`, `cache_lock`,
      `background_update`, `cache_key` por URI). `fastcgi_cache_use_stale` sin `http_502`/`http_504`
      (inválidos en FastCGI; lo corrigió también `design.md`).
- [x] 3.2.3 `bash scripts/feed-cache-config.test.sh` → `TOTAL=18 PASS=18 FAIL=0` (exit 0); `nginx -t`
      sobre ambas configs → `test is successful`.

### 3.3 REFACTOR
- [x] 3.3.1 Comentarios precisados y coherentes entre ambas configs (feed global de comentarios excluido;
      feeds por entrada cacheados a TTL corto; `/feed/rss2/` recae en el TTL largo).
- [x] 3.3.2 Re-verificación 3.1.1 en verde (`18/18`).

## 4. Verificación end-to-end

- [x] 4.1 `just test` → `Total passed: 1104, Total failed: 1`; el único fallo es el **preexistente y
      ajeno** `(reg) version coincide con la del plugin (Expected: '1.21.0', got: '1.19.0')`. `just php-lint`
      sin errores.
- [x] 4.2 Local: **no aplicable**. El stack podman local usa `nginx/default.conf` (dev), no
      `nginx/pro/wp.conf` (producción); la verificación estática (`scripts/feed-cache-config.test.sh`,
      18/18) y `nginx -t` cubren ambas configs. La verificación de runtime significativa es la de producción (4.3).
- [x] 4.3 Producción (desplegado 2026-11-02): `/feed/` y `/podcast/feed/` sirven **HIT ~0,35 s** (y `STALE`
      con `background_update` en la caducidad del TTL), frente a los ~15 s en frío. Frentes desplegados con
      hash idéntico y `owner=82:82`: `class-podcast-feed.php` (`79b2c37a…`) y `class-cache-purge.php`
      (`326ac2e9…`).
- [x] 4.3.1 Loop `audioLength()` sobre 839 episodios con caché llena: **24,36 s → 0,01 s**.
- [x] 4.3.2 Composición fría (purga con secreto): `/feed/` BYPASS ~14,5 s → siguiente lectura HIT; `/podcast/feed/` BYPASS ~8 s → HIT.
- [x] 4.3.3 Calentado tras purga por **FPM real**: re-guardado de un podcast vía REST → en el `shutdown` nginx
      recibe `GET /feed/` y `GET /podcast/feed/` con UA `Atareao-Feed-Warmer/1.0` (log 18:16:59–18:17:00) sin
      cabecera de purga. `getFeedUrlsToWarm()` devuelve solo feeds.
- [x] 4.4 Auditoría independiente realizada; hallazgos B1/M1/M2/m1 corregidos. Frentes PHP aprobados.

## 5. Cierre

- [x] 5.1 Todas las casillas con evidencia CLI/HTTP pegada (producción verificada).
- [x] 5.2 `openspec archive feed-performance-and-caching`.
- [x] 5.3 Git flow: `feature/feed-performance-and-caching` → PR a `development`; commits gitmoji.
