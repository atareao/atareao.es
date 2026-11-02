# PLAN — `podcast-transcripts` (cierre) + `podcast-spotify-sync` (borrador)

> Checkpoint del Director. Actualizado 2026-11-01.
> Regla dura: ninguna casilla se marca sin evidencia CLI pegada literalmente, y no se
> escribe código del frente B hasta que el titular apruebe el change proposal.
> Temporales en `/tmp/opencode`. Nunca editar el core de WordPress en contenedores.

## 0. Dos frentes abiertos

| Frente | Change | Estado real | Puerta que lo desbloquea |
|---|---|---|---|
| A. Cierre de transcripciones | `podcast-transcripts` | **terminado y verificado**: 829/838 con transcripción; PR #141 abierto | autorización de merge + `openspec archive` |
| B. Audio y enlace de Spotify | `podcast-spotify-sync` | proposal + spec delta + tasks redactados y validados | aprobación del titular + decisión de credenciales |

---

## 1. Frente A — `podcast-transcripts`

### 1.1 Resultado final: **TERMINADO Y VERIFICADO** (2026-11-01T22:24:19)

**829 de 838 episodios** tienen ya su transcripción en producción.

| | |
|---|---|
| Episodios publicados | 838 |
| Con transcripción (meta + adjunto + `<podcast:transcript>`) | **829** |
| Sin transcripción | 9 |
| … sin audio utilizable (no transcribibles hoy) | 4 → 17004 (ATA 552), 17068, 17173, 17253 |
| … rechazados por el validador | 5 → 12096, 17193, 17378, 17732, 17736 |

- Feed verificado por HTTP: `items=838 · enclosure=835 · <podcast:transcript>=829`.
- 5 SRT al azar descargados y parseados con el validador: HTTP 200, numeración consecutiva, **cero bloques
  vacíos**, timestamps crecientes y sin solape, contenido real en español.
- **El feed no cambió un solo `enclosure`**: el lote no tocó ningún `mp3-url` (los 835 siguen igual).
- Tasa de fallo real del validador: **5 de 838 = 0,6 %**, mejor que el 1,1 % extrapolado antes.
- Los 9 sin transcripción están **sin meta**, comprobados uno a uno: ningún fallo silencioso.

### 1.2 Historia del lote (tres tramos, horas del kernel)

| tramo | arranque | fin | motivo | exit |
|---|---|---|---|---|
| 1 | 2026-10-31T18:16:37 | (3 min) | relanzado para aplicar el `--work-dir` persistente | — |
| 2 | 2026-10-31T21:47:32 | 2026-11-01T07:21:55 | **pausa pedida por el titular** (SIGTERM) | 143 |
| 3 | 2026-11-01T14:43:19 | 2026-11-01T22:24:19 | fin natural | 1 |

- Parada limpia con `kill -TERM` sobre PIDs confirmados en `/proc/<pid>/cmdline` (nunca `pkill -f`). El
  SIGTERM **no** mata al hijo de Whisper: hubo que terminar ese PID también.
- Pausa reanudable sin pérdidas: el checkpoint local (592 SRT) reanudó y se reutilizó íntegro en el tramo 3.
- Rendimiento: con la GPU compartida con un `llama-server` ajeno, ~4,4 min/episodio; al liberarla (servicio
  `ollama` parado por el Director a petición del titular), **~1,3 min/episodio**.

### 1.3 Hecho y verificado en fases anteriores

- Pipeline **batch local** (Whisper `turbo` → `large-v3-turbo`, `es`) + WP-CLI. Sin endpoint nuevo.
- Contrato congelado por tests: `format_timestamp`, `render_srt`, `write_srt`, `validate_srt`,
  `is_usable_audio_url`, `classify`, `select_episodes`, `plan_transcript_job`, `record`.
- Idempotencia: fuente de verdad = meta `transcript-srt`; subida en una única pasada final del run.
- Suites: `just test-transcripts` EXIT=0 (Python 26/26, PHP 32/0), `just test` 1068/0, `just php-lint` 65
  OK, `phpcs` limpio, `openspec validate podcast-transcripts --strict` valid.
- PR **#141** abierto, 12 commits, CI `lint` en verde. **Sin fusionar.**

### 1.4 Puertas pendientes (en orden)

1. **Autorización del titular** para fusionar el PR #141.
2. `openspec archive podcast-transcripts` (tarea 5.3) — solo tras el merge.
3. Decidir qué hacer con los 5 SRT rechazados (deuda §5B y hallazgo en `tasks.md`): normalizarlos en el
   validador (cambia el contrato → RED→GREEN) o aceptarlo como deuda.
4. Podar la rama `feature/podcast-transcripts`.
5. `development` (`bf8a9cd`) y `main` (`4f493c8`) siguen intactos.

---

## 2. Frente B — `podcast-spotify-sync`

### 2.1 Objetivo (lo que pidió el titular)

«Poder subir cada episodio cuando lo publico y recuperar la URL del episodio». Traducido a dos metas
por episodio: **autorrelleno de `mp3-url`** (el enlace de audio) y **URL pública del episodio en
Spotify**, esta última además visible como enlace en la ficha pública. Un comando para resolver el
histórico completo (838 episodios) y autorrelleno automático al publicar.

### 2.2 Tareas (objetivos atómicos, aún sin empezar)

1. Módulo `\Atareao\PodcastSpotify` → `includes/class-podcast-spotify.php`: parseo del RSS del origen,
   identidad de audio, índice, **motor de decisión puro**, cliente de la Web API, gancho de
   publicación, comando WP-CLI y composición del enlace.
2. Meta nueva `spotify-episode-url`: `register_post_meta` + campo en el metabox del episodio
   (`class-metaboxes.php`), y alta en `PUBLIC_METAS['podcast']` de `class-mcp.php`.
3. Plantilla `single-podcast.php`: imprimir el HTML del enlace **que compone el plugin** (una línea,
   sin lógica en la plantilla); no imprimir nada si no hay URL.
4. Comando `wp atareao-spotify sync` con `--dry-run`, `--limit`, `--missing`, `--fix-mp3`, `--format`
   y recuento final por clase.
5. Arnés offline `tests/spotify-sync/test_podcast_spotify.php` + fixture recortado del RSS real, y
   receta `just test-spotify`.

### 2.3 Arquitectura decidida, y medida (no supuesta)

- **Clave primaria = identidad del audio** (el `.mp3` decodificado de la URL de CloudFront):
  **844 identidades únicas en 844 items** del origen, y **833 de 834** episodios de WordPress casan.
  El `itunes:episode` del origen **no** es fiable (19 números repetidos; ATA 829 va marcado como 825;
  ATA 712 como 713).
- **Clave de respaldo = número deducido del título** con regex tolerante (`ATA 839`, `ATA839`,
  `ATA T01E50`, `705 - …`, `T01E36`): cubre 832 de 835 números de WordPress; los 3 restantes
  (4, 32, 666) se resuelven por identidad o se reportan. Ambigüedad real: **667 y 3**.
- **El título de WordPress no confirma nada**: el titular lo reescribe a mano (28 de 100 difieren).
- **Nunca se sobrescribe un `mp3-url` válido**: la divergencia se reporta y solo se corrige con
  `--fix-mp3`. Se distingue «no hay URL» (vacío o basura como el `"15"` del ATA 552) de «hay URL y
  apunta a otro fichero» (ATA 3, audio recodificado).
- Huecos reales de producción: **838 episodios en el feed, 3 sin `<enclosure>` y 1 con
  `<enclosure url="15">`**.
- **Sin cron**: `quadlets/atareao-wordpress.container:22` fija `DISABLE_WP_CRON=true` y no hay cron
  de sistema → nada de eventos diferidos; el trabajo se hace en la petición de publicación
  (0,18 s y 915 KB comprimidos medidos, con `ETag` → 304) o a mano por WP-CLI.

### 2.4 La decisión bloqueante del frente B

El `<link>` del RSS del origen termina en un token de 7 caracteres (`…-e3ra5d1`) que **no es un ID de
Spotify**. Control medido: el oEmbed de episodios responde **200 + JSON** con IDs reales de 22
caracteres de otros podcasts y **504** con ese token, en las tres formas de URL probadas; el oEmbed
del show sí funciona (el endpoint está vivo). Consecuencia: la URL pública exige la **Web API
oficial** (`Get Show Episodes`, Client Credentials) y, por tanto, una **app de desarrollador**, que
desde el **2026-02-11** exige **cuenta Premium** en modo desarrollo.

Dos rutas, y el titular tiene que elegir:

- **B1 — completa.** El titular crea la app y entrega Client ID/Secret. Entrada por el patrón ya
  establecido en el proyecto: `Secret=<nombre>` en el quadlet +
  `Environment=ATAREAO_SPOTIFY_…_FILE=/run/secrets/<nombre>`, leído con el patrón `getSecret()`
  (`getenv` y, si no, fichero) de `includes/class-cache-purge.php`. Se entregan las dos URLs y el
  enlace visible.
- **B2 — sin credenciales.** Se entrega el autorrelleno de `mp3-url` desde el RSS del origen + el
  número + el enlace de `creators.spotify.com` para uso del titular; la meta pública queda vacía y el
  enlace **no se imprime**. El módulo queda preparado con el proveedor intercambiable para enchufar
  B1 más tarde sin tocar el resto.

### 2.5 Fuera de alcance, verificado con controles

- Subir audio o transcripciones por API: **no existe endpoint**.
- Leer analíticas: **no existe endpoint**.
- Publicar el enlace en el feed: el feed no se toca.
- Endpoints internos del reproductor: descartado (no documentados y contra los términos de uso).

---

## 3. Verificación (comandos canónicos por frente)

| Frente | Comando | Qué prueba |
|---|---|---|
| A | `just test-transcripts` | Python 26/26 + PHP 32 casos del pipeline |
| A | `just test` | arnés heredado, 1068 casos, cero regresión |
| A | `just php-lint` / `just phpcs` | 65 ficheros y estilo PSR12 |
| A | `openspec validate podcast-transcripts --strict` | artefactos del change |
| B | `just test-spotify` | arnés offline del módulo nuevo |
| B | `openspec validate podcast-spotify-sync --strict` | artefactos del change |
| B | `wp atareao-spotify sync --dry-run` | ensayo contra los 838 episodios, sin escribir |

---

## 4. Deuda aceptada (no bloqueante, solo documentada)

- **5 SRT rechazados** por el validador (12096, 17193, 17378, 17732, 17736): deterministas, el
  reintento no sirve. Decisión pendiente del titular.
- Los 3 episodios sin `number` (y sin audio) no son emparejables por número: quedan fuera de la
  reparación de audio y se reportan.
- Los episodios 4 y 32 no existen en el origen; el 666 casa por identidad.
- La verificación del enlace público de Spotify es **manual** (abrir la URL en el navegador): el
  oEmbed solo responde para IDs válidos y no es automatizable desde el servidor.
- nginx sirve los `.srt` como `application/octet-stream` mientras el feed declara
  `application/x-subrip` (heredado del frente A).
- CI no ejecuta ninguna suite de pruebas (`.github/workflows/ci.yml` solo hace `php -l` y el test de
  bump): añadirlas es un change aparte.

---

## 5. Puertas que NO se cruzan sin autorización explícita

- Fusionar el PR #141.
- `openspec archive` de `podcast-transcripts` o de `podcast-spotify-sync`.
- Podar la rama `feature/podcast-transcripts`.
- Escribir código o tests del frente B (hasta que se apruebe el proposal).
- Sobrescribir un `mp3-url` válido (solo con `--fix-mp3`).

## 7. Resultado (2026-11-02)

**4.2–4.5 ejecutadas y verificadas en producción** (escritura real, acotada y reversible):

| Tarea | Resultado |
|---|---|
| 4.2 acotada a 17253 | `repaired:1, remaining:3`; censo 835→836; **una** sola línea cambiada |
| 4.3 ATA 552 | `repaired:3, remaining:0`; censo → 838; **cero** `url="15"` en el feed |
| 4.4 idempotencia | `repaired:0, ok:837, divergent:1`; foto idéntica (cero escrituras) |
| 4.5 gancho | publica → rellena solo; 2.º guardado no repite; origen caído no rompe |

- El feed `/podcast/feed/` **no se cachea** en nginx (`"~*/feed/" 1`), así que el arreglo es visible al
  instante: servido por la red interna da `enclosures=838 · url="15"=0`.
- Diff global antes/después: **solo** 17004, 17068, 17173 y 17253. Ninguna de las 835 URL ya correctas cambió.
- Estado de producción limpio: 838 episodios publicados, sin episodios de prueba, transients y option en
  su valor por defecto.
- **Pendiente de decisión del titular**: fusionar el PR #143 y `openspec archive podcast-audio-sync`.
- Nuevo hallazgo: la primera composición del feed tras reparar emite `length="0"` y se autocorrige; el
  feed completo queda con 0 enclosures sin tamaño.
