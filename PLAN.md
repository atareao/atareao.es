# PLAN — cierre de `podcast-transcripts` + dos changes en borrador

> Checkpoint del Director. Actualizado 2026-11-02.
> Regla dura: ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Ningún change nuevo escribe código ni tests hasta que el titular apruebe su spec.
> Temporales en `/tmp/opencode`. Nunca editar el core de WordPress en contenedores.

## 0. Tres frentes

| Frente | Change | Estado real | Puerta que lo desbloquea |
|---|---|---|---|
| A. Cierre de transcripciones | `podcast-transcripts` | **TERMINADO, FUSIONADO Y ARCHIVADO**: 830/838 con transcripción; PR #141 y #142 fusionados; rama podada | — (cerrado) |
| B. Autorrelleno de `mp3-url` | `podcast-audio-sync` | proposal + spec delta (8 req / 27 esc.) + tasks redactados y validados con `--strict`; PR #143 en borrador | **aprobación del titular** |
| C. Diagnóstico de fallos | `podcast-transcribe-diagnostics` | proposal + delta (2 req / 12 esc.) + tasks redactados y validados con `--strict`; PR #144 en borrador | **aprobación del titular** |

**Spotify (URL pública del episodio + enlace visible) queda FUERA de alcance** hasta que exista una app de
desarrollador con cuenta Premium: la Web API oficial (Client Credentials) es hoy el único camino y exige la app.

---

## 1. Frente A — `podcast-transcripts` — CERRADO

### 1.1 Resultado final: TERMINADO, FUSIONADO Y ARCHIVADO (2026-11-02)

**830 de 838 episodios** tienen su transcripción en producción (meta + adjunto + `<podcast:transcript>`).

| | |
|---|---|
| Episodios publicados | 838 |
| Con transcripción | **830** |
| … sin audio utilizable (no transcribibles hoy) | 4 → 17004 (ATA 552, `mp3-url` = `"15"`), 17068, 17173, 17253 |
| … rechazados por el validador (deterministas, sin meta) | 4 → 12096, 17193, 17378, 17736 |

- Feed verificado por HTTP (`?paged=N`, 9 páginas): `items=838 · enclosure=835 · <podcast:transcript>=830`.
- 5 SRT al azar descargados y parseados con el validador del proyecto: HTTP 200, numeración consecutiva,
  **cero bloques vacíos**, timestamps crecientes sin solape, contenido real en español.
- **El lote no tocó ningún `mp3-url`**: los 835 enclosures siguen igual.
- Los 8 sin transcripción están **sin meta**, comprobados uno a uno: ningún fallo silencioso.

### 1.2 Cierres posteriores al lote

- **El `17732` no era un fallo del audio.** Con la misma URL, el mismo `User-Agent` y el comando literal del
  pipeline, Whisper termina `exit 0` y produce un SRT válido (184 bloques, 0 vacíos) en dos ejecuciones
  consecutivas, `stdout=23 025 B` / `stderr=0 B` las dos veces. Se relanzó con `--episode 17732`
  (`resueltos: 1, fallidos: 0`): producción pasó de 829 a **830/838**; el `<podcast:transcript>` sale ya en
  el feed y el SRT servido es idéntico byte a byte al local.
- **El mensaje «sin detalle» era un defecto de observabilidad**: `transcribe_audio` mira solo `proc.stderr`,
  y `openai-whisper 20250625` escribe todo por `stdout`. Se abre el frente C para arreglarlo.
- `transcribe.fish` (yadm) arreglado y commiteado (`9a18ba6`): progreso en vivo y `exit=1` propaga el fallo.

### 1.3 Entrega

- **PR #141** fusionado en `development` (`63aee4f`); **PR #142** (archivado) fusionado (`697815f`).
- `openspec archive podcast-transcripts` ejecutado: spec canónica `openspec/specs/podcast-transcripts/spec.md`
  (**7 requisitos**) y change en `openspec/changes/archive/2026-11-02-podcast-transcripts/`.
- Rama `feature/podcast-transcripts` **podada** (local y remota).
- Suite del cierre: `just test-transcripts` EXIT=0 (Python 26/26, PHP 32/0), `just test` 1068/0,
  `just php-lint` 65 OK, `phpcs` limpio, `openspec validate ... --strict` valid.

---

## 2. Frente B — `podcast-audio-sync` (renombrado desde `podcast-spotify-sync`)

### 2.1 Objetivo

**Autorrelleno de `mp3-url`** por episodio desde el RSS público del origen (Spotify for Podcasters, show
`5a5b39c`), con un comando para resolver el histórico completo (838 episodios) y autorrelleno al publicar.
El `<enclosure>` del feed sale de `mp3-url` y no se toca.

### 2.2 Decisiones del titular (2026-11-02)

- **Autorrelleno solo si `mp3-url` está vacío o no es una URL.** Nunca se sobrescribe una URL válida.
- **`--fix-mp3` eliminado**: la divergencia del ATA 3 (audio recodificado en el origen) **solo se reporta**.
- **Spotify (Web API + enlace visible) fuera de alcance** hasta que haya app de desarrollador con Premium.

### 2.3 Forma prevista

- Comando `wp atareao podcast-audio-sync` (en la línea de `wp atareao podcast-audio-length`).
- Módulo `includes/class-podcast-audio.php`; pruebas en `tests/audio-sync/`; receta `just test-audio`.
- **Clave primaria = identidad del audio** (`.mp3` decodificado de la URL de CloudFront): 844 identidades
  únicas en 844 items del origen, 833 de 834 episodios de WordPress casan. El `itunes:episode` del origen
  **no** es fiable (19 repetidos). Respaldo: número deducido del título con regex tolerante.

### 2.4 Huecos reales de producción (medidos)

838 episodios en el feed: **3 sin `<enclosure>`** (mp3-url vacía) y **1 con `<enclosure url="15">`**
(ATA 552, URL rota). El espejo es fiel en 833 de 834 episodios con audio.

### 2.5 Estado — **IMPLEMENTADO Y AUDITADO** (2026-11-02)

Spec **aprobada por el titular** (`143 -> 144`). TDD RED→GREEN→REFACTOR cerrado en la rama
`feature/podcast-audio-sync`; **PR #143 en borrador**, sin fusionar:

| commit | fase |
|---|---|
| `b5a44d9` | ✅ RED — arnés procedural `tests/audio-sync/` + fixture del RSS + receta `just test-audio` |
| `b102560` | ✨ GREEN — módulo `includes/class-podcast-audio.php` + registro en el bootstrap |
| `49f0a41` | 🐛 fix — correcciones de la ronda de auditoría |

- **Pruebas**: `just test-audio` **140/0** (28 escenarios); `just test` 1068/0; `just test-transcripts`
  32/0; `just php-lint` 66 OK; `openspec validate podcast-audio-sync --strict` valid.
- **Auditoría independiente**: encontró 2 defectos **bloqueantes** que ya están corregidos —
  (B1) el gancho de publicación usaba `transition_post_status`, que el core dispara antes de `save_post`
  (donde el metabox persiste `number`/`mp3-url`); ahora es `wp_after_insert_post` con `ATTEMPT_META` solo
  cuando el episodio queda resuelto. (B2) `planSync` miraba la identidad antes que la usabilidad, así que
  un `mp3-url` no-URL terminado en `.mp3` daba `ok` en vez de `repair`. Mayores: SSRF cerrado
  (`wp_safe_remote_get` + `reject_unsafe_urls`/`redirection=0`), XML inválido = fallo, ETag muerto
  eliminado.
- **Pendiente (puertas)**: la verificación **E2E contra producción** (tareas 4.1–4.7) exige **desplegar**
  el módulo en producción —el árbol de trabajo local no está montado allí— y **autorización** para las
  reparaciones reales (4.2/4.3). El `openspec archive` también espera autorización.

---

## 3. Frente C — `podcast-transcribe-diagnostics`

### 3.1 Objetivo

Que el motivo de un fallo de transcripción sea utilizable: sale de la salida real del comando (`stderr` y,
si está vacío, `stdout`) e incluye el código de salida o la señal que terminó el proceso, en vez del «sin
detalle» sistemático. Se distinguen «el comando falló» de «terminó bien pero no dejó el SRT». Fuera de
alcance: reintentos, subida, validador y clases omitidas.

### 3.2 Delta

- **MODIFIED** del requisito «Aislamiento de fallos y contabilidad del lote» (con sus 6 escenarios íntegros)
  en `openspec/specs/podcast-transcripts/spec.md`.
- **ADDED** «El diagnóstico de un fallo de transcripción es utilizable» (6 escenarios).
- **2 requisitos / 12 escenarios**, validado con `openspec validate podcast-transcribe-diagnostics --strict`.

### 3.3 Estado

proposal + delta + `tasks.md` (RED→GREEN→REFACTOR→E2E) + `README.md` redactados. **PR #144 en borrador**,
sin fusionar. **Sin una línea de código ni de test**: pendiente de la aprobación del titular.

---

## 4. Verificación (comandos canónicos)

| Frente | Comando | Qué prueba |
|---|---|---|
| A | `just test-transcripts` | Python 26/26 + PHP 32 casos del pipeline |
| A | `just test` | arnés heredado, 1068 casos, cero regresión |
| A | `openspec validate podcast-transcripts --strict` | spec canónica del cierre |
| B | `just test-audio` (nueva) | arnés offline del módulo nuevo |
| B | `openspec validate podcast-audio-sync --strict` | artefactos del change |
| B | `wp atareao podcast-audio-sync --dry-run` | ensayo contra los 838 episodios, sin escribir |
| C | `just test-transcripts` | pruebas nuevas del camino de error (whisper falso) |
| C | `openspec validate podcast-transcribe-diagnostics --strict` | artefactos del change |

---

## 5. Deuda aceptada (no bloqueante, solo documentada)

- **4 SRT rechazados** por el validador (12096, 17193, 17378, 17736): deterministas (el 12096 falló 4 veces
  con el mismo «bloque 5 sin texto»). El reintento no sirve: merecen su propio change.
- **4 episodios sin audio utilizable** (17004 con `mp3-url = "15"`, 17068, 17173, 17253): no transcribibles.
- La causa exacta del fallo del `17732` no se puede reconstruir (probablemente una señal): se perdió con el
  mensaje. El frente C arregla que no vuelva a perderse.
- nginx sirve los `.srt` como `application/octet-stream` mientras el feed declara `application/x-subrip`.
- CI no ejecuta ninguna suite de pruebas (solo `php -l` y el test de bump): añadirlas es un change aparte.
- Deuda menor del cierre (5B del change archivado): `test_attach.php` fuera de `just test`, doble línea en
  blanco entre bloques SRT, validación de `--ssh-host`/`--wpcli-container`, etc.

---

## 6. Puertas que NO se cruzan sin autorización explícita

- Fusionar el **PR #143** (`podcast-audio-sync`) o el **PR #144** (`podcast-transcribe-diagnostics`).
- `openspec archive` de `podcast-audio-sync` o de `podcast-transcribe-diagnostics`.
- Escribir código o tests de los frentes B y C (hasta que se apruebe su spec).
- Sobrescribir un `mp3-url` válido.
