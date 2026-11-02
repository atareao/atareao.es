# PLAN — Feed rápido y cacheado (`feed-performance-and-caching`)

> Checkpoint del Director. Actualizado 2026-11-02. Estado final: **IMPLEMENTADO, AUDITADO, DESPLEGADO, ARCHIVADO**.
> Regla dura: ninguna casilla se marca sin evidencia CLI/HTTP pegada literalmente.
> Temporales en `/tmp/opencode`. Nunca editar el core de WordPress en contenedores.

## 0. Objetivo

Atacar la lentitud del feed por dos vías independientes:

1. **Hacer el feed rápido**: acotar de verdad el trabajo de la composición (red y trabajo local evitable).
2. **Cachear el feed siempre**: servirlo desde la caché del front, no desde PHP, en cada petición, y
   mantenerlo fresco tras publicar.

## 1. Diagnóstico (medido 2026-11-02, producción)

- Base: `/feed/` ≈ 15 s y `/podcast/feed/` ≈ 14 s con UA de lector RSS; `200` (los "502" eran artefactos
  de que nginx devuelve `444` a `curl`).
- Causa 1 (red): el presupuesto remoto solo se comprobaba **entre episodios**; una resolución encadena
  `REMOTE_MAX_REDIRECTS+1` `HEAD` + un `Range`, cada uno a `REMOTE_TIMEOUT = 5 s` ⇒ ~25 s por episodio.
- Causa 2 (no cacheado): `"~*/feed/" 1;` en el `map $skip_cache` de ambas configs ⇒ cada lector golpeaba PHP.
- Causa 3 (real, medida tras desplegar la 1 y la 2): **no era la red**. Con los 839 episodios ya cacheados,
  la composición seguía en ~24 s porque `audioLength()` llamaba a `localAudioLength()` **antes** de mirar la
  caché, y `localAudioLength()` ejecuta `attachment_url_to_postid()` (consulta a BD, no lectura de disco).
  Medición aislada ×839: leer el tamaño cacheado = 0,11 s; `attachment_url_to_postid()` = 24,26 s.
- La purga ya invalidaba `/feed/`, pero **no** `/podcast/feed/`; y el frío post-publicación no estaba cubierto.

## 2. Change proposal (archivado)

- Change: `openspec/changes/archive/2026-11-02-feed-performance-and-caching/` — **válido** en su momento
  (`openspec validate` OK), **archivado** con éxito (`openspec archive … --yes`, specs actualizadas).
- Deltas mergeados a las specs principales:
  - `openspec/specs/podcast-feed/spec.md` — **MODIFIED** "Episodio con audio y numeración" (fecha límite dura
    + orden caché-primero + cacheo del tamaño local).
  - `openspec/specs/cache-purge/spec.md` — **MODIFIED** (purgar el feed del tipo de contenido) y **ADDED**
    ("Warm the feed cache after purging").
  - `openspec/specs/feed-cache/spec.md` — **ADDED** (contrato de la caché de feeds en el front).

## 3. Frentes y tareas (todos cerrados)

| Frente | Capability | Qué cambió | Ficheros |
|---|---|---|---|
| 1. Feed rápido (red) | `podcast-feed` | Deadline `= inicio + presupuesto`; `timeout` HTTP = `min(REMOTE_TIMEOUT, restante)`; sin petición si `restante <= 0`; corte de redirecciones | `class-podcast-feed.php` |
| 1b. Feed rápido (local) | `podcast-feed` | `audioLength()` reordenado a **caché → local → remoto → 0**; el tamaño local también se cachea | `class-podcast-feed.php` |
| 2a. Cachear feed | `feed-cache` | Quitar `/feed/` de `$skip_cache`; `location` de feed con TTL 5m, `stale`, `cache_lock`, `background_update`; bypass por sesión | `nginx/default.conf`, `nginx/pro/wp.conf` |
| 2b. Frescura | `cache-purge` | `getUrlsToPurge()` añade `get_post_type_archive_feed_link($post->post_type)` | `class-cache-purge.php` |
| 2c. Calentado | `cache-purge` | Tras purgar, calentar feeds por `shutdown` con `fastcgi_finish_request()`, presupuesto acotado, sin cabecera de purga ni cookies | `class-cache-purge.php` |

### 3.1 Estado — IMPLEMENTADO Y AUDITADO

- [x] Frente 1 y 1b: RED → GREEN → REFACTOR en `tests/`; `just test` sin regresiones.
- [x] Frente 2a: `scripts/feed-cache-config.test.sh` en rojo (18 fallos pre-change) y verde (18/18);
      `nginx -t` OK; cableado en `.github/workflows/ci.yml`.
- [x] Frentes 2b y 2c: RED → GREEN → REFACTOR; auditoría independiente con BLOCKER corregido.
- [x] Correcciones de auditoría: M1 (`design.md`: `http_502` inválido en FastCGI); M2 (job CI inexistente);
      m1 (comentario impreciso); O2 (warmer y presupuesto documentados); y en 2c el BLOCKER de cota de
      tiempo/worker + 4 mejoras de test/estado.

## 4. Verificación end-to-end (producción)

- [x] `just test` → `1152 passed / 1 failed`; el único fallo es **preexistente y ajeno**
      (`(reg) version coincide…` 1.21.0 vs 1.19.0). `just php-lint` limpio; `just phpcs` sin violaciones nuevas.
- [x] Despliegue quirúrgico con hash idéntico y `owner=82:82`:
      `class-podcast-feed.php` (`79b2c37a…`) y `class-cache-purge.php` (`326ac2e9…`).
- [x] Loop `audioLength()` ×839 con caché llena: **24,36 s → 0,01 s**.
- [x] Lectores: `/feed/` y `/podcast/feed/` en **HIT ~0,35 s** (y `STALE` con `background_update` al
      caducar el TTL) — nunca esperan la composición.
- [x] Composición fría (purga con secreto): `/feed/` BYPASS ~14,5 s y `/podcast/feed/` BYPASS ~8 s →
      siguiente lectura HIT. El frío restante es renderizado del **núcleo** (`do_blocks`+`wpautop`+
      `wtexturize`) ×100 ítems (`posts_per_rss=100`), no nuestro plugin.
- [x] Calentado tras purga por **FPM real**: re-guardado de un podcast vía REST → en el `shutdown` nginx
      recibe `GET /feed/` y `GET /podcast/feed/` con UA `Atareao-Feed-Warmer/1.0` (log 18:16:59–18:17:00),
      sin cabecera de purga.
- [x] 4.2 (E2E local) **no aplicable**: el stack podman local usa `nginx/default.conf` (dev), no
      `nginx/pro/wp.conf` (producción); cubierto por la verificación estática + `nginx -t`.

## 5. Cierre

- [x] `openspec archive feed-performance-and-caching` (→ `2026-11-02-feed-performance-and-caching`).
- [x] Git flow: rama `feature/feed-performance-and-caching` → PR a `development`; commits gitmoji.

## 6. Estado

**[IMPLEMENTADO - AUDITADO - DESPLEGADO - ARCHIVADO]**. Objetivos 0.1 y 0.2 cumplidos y verificados en
producción. Fuera de alcance (documentado): reducir `posts_per_rss` o renderizado del núcleo.
