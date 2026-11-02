# PLAN — cierre de `podcast-transcripts`, `podcast-audio-sync` y `podcast-transcribe-diagnostics`

> Checkpoint del Director. Actualizado 2026-11-02.
> Regla dura: ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Ningún change nuevo escribe código ni tests hasta que el titular apruebe su spec.
> Temporales en `/tmp/opencode`. Nunca editar el core de WordPress en contenedores.

## 0. Tres frentes

| Frente | Change | Estado real | Puerta que lo desbloquea |
|---|---|---|---|
| A. Cierre de transcripciones | `podcast-transcripts` | **TERMINADO, FUSIONADO Y ARCHIVADO**: **834/838** con transcripción (los 4 que faltaban los cerró 4.6 del frente B); PR #141 y #142 fusionados; rama podada | — (cerrado) |
| B. Autorrelleno de `mp3-url` | `podcast-audio-sync` | **CERRADO Y ARCHIVADO**: E2E 4.1–4.5 verificada en producción con escritura real (4 reparados, feed 838/838 sin `url="15"`); PR #143 fusionado y `openspec archive` ejecutado | — (cerrado) |
| C. Diagnóstico de fallos | `podcast-transcribe-diagnostics` | **CERRADO**: especificado, implementado, auditado, E2E hecha y archivado; PR #144 fusionado | — (cerrado) |
| D. Subida manual de SRT | `podcast-srt-upload` | **PROPUESTO** (2026-11-02): spec escrita, esperando aprobación del titular | Aprobación de la spec y TDD |

**Spotify (URL pública del episodio + enlace visible) queda FUERA de alcance** hasta que exista una app de
desarrollador con cuenta Premium: la Web API oficial (Client Credentials) es hoy el único camino y exige la app.

---

## 1. Frente A — `podcast-transcripts` — CERRADO

### 1.1 Resultado final: TERMINADO, FUSIONADO Y ARCHIVADO (2026-11-02)

**834 de 838 episodios** tienen su transcripción en producción (meta + adjunto + `<podcast:transcript>`);
los 4 que faltaban al cerrar el frente A los resolvió el frente B (tarea 4.6, 2026-11-02).

| | |
|---|---|
| Episodios publicados | 838 |
| Con transcripción | **834** |
| … sin audio utilizable (no transcribibles hoy) | **0** → los 4 (17004 = ATA 552, 17068, 17173, 17253) los reparó el frente B y se transcribieron en su tarea 4.6 |
| … rechazados por el validador (deterministas, sin meta) | 4 → 12096, 17193, 17378, 17736 |

- Feed verificado por HTTP (`?paged=N`, 9 páginas) al cerrar el frente A: `items=838 · enclosure=835 ·
  <podcast:transcript>=830`; **estado actual, tras 4.6 del frente B**: `enclosure=838 · <podcast:transcript>=834`.
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

### 2.5 Estado — **CERRADO: IMPLEMENTADO, AUDITADO, E2E VERIFICADA EN PRODUCCIÓN Y ARCHIVADO** (2026-11-02)

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
- **Despliegue quirúrgico**: el módulo y el bootstrap se desplegaron en producción (`docker cp` + `chown`,
  hashes verificados contra la rama) con el árbol previo respaldado. La E2E 4.1 descubrió el defecto **B3**
  (el tope del cuerpo se había dimensionado con la medida comprimida): ya corregido en `a6da0b9`.

### 2.6 E2E de escritura real — **4.2 a 4.5 verificadas** (2026-11-02)

Autorizada por el titular, igual que la fusión del PR #143 y el `openspec archive`. El censo se obtiene
**sin red pública** con `wp eval-file` (misma condición que gobierna `PodcastFeed::itemElements()`) y el
feed `/podcast/feed/` **no se cachea** en nginx (`nginx/default.conf:8`, `"~*/feed/" 1`), así que refleja
la base de datos al instante.

| Tarea | Resultado |
|---|---|
| 4.1 ensayo | `Reparados: 4 · Ya correctos: 833 · Divergentes: 1` (el divergente es el ATA 3, 12006) |
| 4.2 acotada a 17253 | `repaired:1, remaining:3`; censo 835→836; **una** sola línea de la foto cambió |
| 4.3 ATA 552 | `repaired:3, remaining:0`; censo → 838; **cero** `url="15"` en el feed |
| 4.4 idempotencia | `repaired:0, ok:837, divergent:1`; foto **byte a byte idéntica** → cero escrituras |
| 4.5 gancho | publicar rellena solo; 2.º guardado no repite (sale por la marca de intento); origen caído no rompe y no escribe |

- **Prueba quirúrgica**: foto completa de los **838** `<enclosure>` (`id⇥url`) antes y después; el diff
  global son **solo** 17004, 17068, 17173 y 17253. **Ninguna** de las 835 URL ya correctas cambió.
- **Feed servido por la red interna** (9 páginas): `enclosures=838 · url="15"=0`; el ATA 552 publica ya su
  URL del origen.
- **Estado de producción limpio**: 838 publicados, sin episodios de prueba, option y transients por defecto.
- **Hallazgo menor (cerrado)**: la primera composición del feed tras reparar emite `length="0"` y se
  autocorrige y cachea; el feed completo queda con **0** enclosures sin tamaño.
- **4.6 CERRADO (2026-11-02)**: los 4 reparados vuelven a ser transcribibles **y están transcritos**.
  `--dry-run`: `pendientes 8 / hechos 830 / sin audio 0` (antes: `sin audio 4`). Lote real acotado con
  `--episode` × 4 (`turbo`/`es`, GPU local): **`resueltos 4 · fallidos 0`**, adjuntos 19688–19691
  (`application/x-subrip`). Pipeline tras el lote: `pendientes 4 / hechos 834 / sin audio 0`. Feed por
  HTTP: `enclosure=838 · url="15"=0 · <podcast:transcript>=834`, cada transcript en el mismo `<item>` que
  su audio. **Ningún `mp3-url` se movió.**
- **Entrega**: PR #143 fusionado en `development` (`a3fff84`) y `openspec archive podcast-audio-sync`
  ejecutado (spec canónica en `openspec/specs/podcast-audio-sync/spec.md`); rama podada.

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

### 3.3 Estado — **CERRADO: IMPLEMENTADO, AUDITADO, E2E HECHA Y ARCHIVADO** (2026-11-02)

Spec **aprobada por el titular** (`143 -> 144`). TDD RED→GREEN→REFACTOR→E2E cerrado en la rama
`feature/podcast-transcribe-diagnostics`; **PR #144 en borrador**, sin fusionar:

| commit | fase |
|---|---|
| `363bfcf` | ✅ RED — 9 pruebas (6 unitarias de `failure_detail` + 3 de integración con `whisper` falso en el `PATH`) |
| `253fe61` | 🐛 GREEN — `failure_detail` + uso en `transcribe_audio` (constante `FAILURE_DETAIL_MAX_LINES`) |
| `debd6ea` | 📝 docs — evidencia RED/GREEN/REFACTOR/E2E + lección aprendida |
| `1c407c2` | 📝 docs — marcar 5.2 (push + PR) |

- **Pruebas**: Python **37/0** (`unittest discover`); `just test-transcripts` 32/0 (+Python 37 OK);
  `just test` 1068/0; `openspec validate podcast-transcribe-diagnostics --strict` valid.
- **Auditoría independiente**: **sin bloqueantes ni mayores**; tres menores corregidos en el mismo
  ciclo RED→GREEN — (M-1) el caso «`exit 0` sin SRT» ya no descarta la salida útil; (M-2) `max_lines <= 0`
  no afirma que no hubo salida; (R-1) pruebas de robustez (`señal desconocida`, `max_lines = 0`); (O-1)
  cabecera del test actualizada.
- **E2E contra el pipeline real** (solo lectura + `whisper` falso en el `PATH`): `--dry-run --limit 5`
  → pendientes 4 / hechos 830 / sin audio 4 (medido entonces; tras 4.6 del frente B el mismo ensayo da
  `pendientes 4 / hechos 834 / sin audio 0`); `exit 3` → el motivo trae la línea de `stdout` y el código
  (y el lote **no** se aborta: `fallidos: 2`); `kill -9 $$` → `SIGKILL: …`; `exit 0` sin SRT → mensaje
  explícito y distinguible.
- **Entrega**: **PR #144 fusionado** en `development` y `openspec archive podcast-transcribe-diagnostics`
  ejecutado (`openspec/changes/archive/2026-11-02-podcast-transcribe-diagnostics/`).

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
- Los **4 episodios antes sin audio** (17004 con `mp3-url = "15"`, 17068, 17173, 17253) **tienen ya el audio
  reparado y su transcripción** (4.6 cerrado el 2026-11-02): el podcast queda en **834/838** con
  transcripción. Solo quedan los **4 SRT que el validador rechaza** de forma determinista.
- La causa exacta del fallo del `17732` no se puede reconstruir (probablemente una señal): se perdió con el
  mensaje. El frente C arregla que no vuelva a perderse.
- nginx sirve los `.srt` como `application/octet-stream` mientras el feed declara `application/x-subrip`.
- CI no ejecuta ninguna suite de pruebas (solo `php -l` y el test de bump): añadirlas es un change aparte.
- Deuda menor del cierre (5B del change archivado): `test_attach.php` fuera de `just test`, doble línea en
  blanco entre bloques SRT, validación de `--ssh-host`/`--wpcli-container`, etc.

---

## 6. Autorizaciones y puertas (2026-11-02)

El titular autorizó **fusionar el PR #143 y archivar `podcast-audio-sync`**; el PR #144 y el archive de
`podcast-transcribe-diagnostics` ya estaban ejecutados. Puertas que siguen en pie:

- **No** sobrescribir nunca un `mp3-url` válido (el módulo no ofrece ninguna opción que lo haga).
- Cualquier change nuevo: sin spec aprobada no se escribe código ni tests.

Autorizado por el titular el 2026-11-02 (pregunta explícita): el **lote real de transcripción de los 4
episodios reparados** (17004, 17068, 17173, 17253), que escribe adjuntos y metas en producción.

Autorizado por el titular el 2026-11-02 (pregunta explícita): **la spec del change `mcp-era-selection`** y
**arrancar su TDD** (RED → GREEN). Pendiente de autorización explícita: el **despliegue quirúrgico en
producción** (tarea 4.1) y el `archive` del change.

---

## 7. Frente D — `mcp-era-selection` (la era del transporte MCP)

### 7.1 Objetivo

Los agentes MCP construidos sobre el SDK oficial de TypeScript —el puente `mcp-remote`, que usan Claude
Desktop, Cursor, VS Code, Windsurf o Cline— **no consiguen conectar** con `https://atareao.es/mcp`: abortan
con `{"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params"},"id":null}` y `400 Bad Request`. El
titular lo ve como «ahí no hay ningún MCP». El endpoint, sin embargo, está sano: `initialize` `200`
(`atareao-mcp v1.19.0`), `tools/list` `200` con las tres herramientas, tarjeta `/mcp/server-card` y catálogo
`/.well-known/ai-catalog.json` `200`, y el SDK oficial de Python conecta. El fallo es de **selección de era**.

### 7.2 Causa raíz (medida, no supuesta)

`isModernRequest()` daba por «era moderna» toda petición con la cabecera `Mcp-Method`, y el requisito
«Conformidad dual-era de Streamable HTTP» de `openspec/specs/mcp-server/spec.md` (línea 317) **ordenaba** esa
regla: era un desliz de **spec**, no de implementación, así que el arreglo exigía delta de spec. El SDK
oficial envía `Mcp-Method` en todas las peticiones posteriores al `initialize`, también cuando ha negociado
2025-06-18 y no manda `_meta`; el camino moderno exige `params._meta` y responde `-32602`.

### 7.3 Forma aprobada (2026-11-02)

- La era moderna se decide **solo** por `params._meta['io.modelcontextprotocol/protocolVersion']`. Las
  cabeceras `Mcp-Method` y `MCP-Protocol-Version` se siguen **validando** dentro de la era moderna
  (`HeaderMismatch` `-32020`), pero nunca la seleccionan.
- Cualquier mensaje sin `id` (notificación) responde `202 Accepted` sin cuerpo en las dos eras.
- El delta de `mcp-server` se aplica con `REMOVED` + `ADDED` y **renombra el requisito** a «Selección de era
  y conformidad del transporte Streamable HTTP»: el CLI de OpenSpec prohíbe que un `MODIFIED` pierda
  escenarios y el escenario «La cabecera `Mcp-Method` selecciona la era moderna» tenía que desaparecer.
  `archive` simulado sobre una copia de `openspec/`: `+ 1 added, - 1 removed`, requisito en la misma posición.
- Fuera de alcance (deuda §5): bloqueo de User-Agents del nginx de producción, ausencia de `/.well-known/mcp`
  y `405` al `GET /mcp`.

### 7.4 Estado — **spec aprobada; TDD RED→GREEN hecho; auditoría y E2E en producción verificados (ver §8)**

| Fase | Comando | Evidencia |
|---|---|---|
| Línea base | `just test` | `Total passed: 1068, Total failed: 0` |
| RED (1.1–1.7) | `just test` con tests nuevos y plugin intacto | `Total passed: 1069, Total failed: 9`, exit 1; los 9 fallos son los tres escenarios nuevos |
| GREEN (2.1–2.4) | `just test` con el arreglo | `Total passed: 1078, Total failed: 0`, `All tests passed successfully!` |
| Estilo | `just php-lint` / `just phpcs` | lint limpio; fichero tocado con 0 errores y los mismos 5 warnings que en `HEAD` (la receta global ya fallaba en 79 ficheros ajenos) |
| Specs | `openspec validate mcp-era-selection --strict` / `--all --strict` | `is valid` / `35 passed, 0 failed` |

Código: `isModernRequest()` decide la era solo por `_meta` (docblock con el porqué) y la rama legacy acepta
notificaciones con `202` sin cuerpo. Tests: tres escenarios nuevos en la suite «MCP Dual-Era Transport» —con
el caso literal de `mcp-remote`— y el escenario de la regla retirada reescrito.

Hecho: **4.1–4.6** (E2E contra producción, ver §8.2) y **5.1–5.4** (rama, PR #148, nota de aprendizaje y
`archive`); el cierre queda registrado en §8.3.
---

## 8. Frente D — auditoría y E2E en producción (2026-11-02)

### 8.1 Auditoría independiente — APROBADO CON REPAROS (0 bloqueantes)

Revisión externa de `git diff development..HEAD` con matriz escenario → test (los 19 escenarios `ADDED`
tienen aserción). Reparos atendidos antes del E2E: **F3** (se elimina la rama muerta de
`handleModernRequest`: `META_PROTOCOL_VERSION` ya la garantiza `isModernRequest`), **F4/F5** (cuatro
asertos de caracterización: `Mcp-Name` ausente y discordante → `-32020`; notificación legacy distinta de
`initialized` → `202` sin cuerpo) y **F2** (decidido por el titular: la validación precede a la
aceptación; queda explícito en el delta con una frase y un escenario propios). Verificación: `just test`
→ `Total passed: 1082, Total failed: 0`; `just php-lint` limpio; `phpcs` sin errores nuevos.

### 8.2 E2E en producción — DESPLEGADO Y VERIFICADO

`docker cp` quirúrgico al volumen `wordpress_wordpress` (contenedor `atareao`, vía `ssh atareao`) con copia
previa (`a3e2bf0f…`) y hash verificado (`f7c2dfa6…`), permisos restaurados a `82:82 644`. Pruebas: el fallo
del titular (`tools/list` con `Mcp-Method` y sin `_meta`) ahora responde `200` con las tres herramientas;
las notificaciones responden `202` de **0 bytes**; la era moderna sigue intacta (`resultType`, `ttlMs`,
`cacheScope`, `serverInfo`, `supportedVersions`); la validación moderna sigue devolviendo `-32020`.
Clientes reales: `mcp-remote` 0.16.0 conecta sin `-32602` (exit 0), y los SDK oficiales de TS (1.32.2) y de
Python (2.3.0) listan las tres herramientas. El arreglo queda desplegado en producción.

### 8.3 Cierre — **CERRADO: FUSIONADO Y ARCHIVADO** (2026-11-02)

- PR **#148** fusionado en `development` (merge commit `e9509d5`); rama `feature/mcp-era-selection` podada
  en local y en remoto.
- `openspec archive mcp-era-selection` → `openspec/changes/archive/2026-11-02-mcp-era-selection/`. El
  requisito se sustituye **en su misma línea (317)** de `openspec/specs/mcp-server/spec.md`
  («Conformidad dual-era de Streamable HTTP» desaparece y «Selección de era y conformidad del transporte
  Streamable HTTP» ocupa su sitio) y ningún otro requisito se toca; el escenario de la regla retirada pasa
  a ser el inverso y se suman tres escenarios nuevos (69 → 72 en el fichero).
  `openspec validate --all --strict` → `34 passed, 0 failed`; `openspec list` → «No active changes found».
- Producción sigue sirviendo el arreglo: md5 `f7c2dfa6f705ff0ffc729df13f75aca8`; T1 (`tools/list` con
  `Mcp-Method` y sin `_meta`) → `200` con las tres herramientas.
- `just test` en `development` → `Total passed: 1082, Total failed: 0`.
- Pendiente y **ya autorizado, para sesión nueva**: el change aparte del bloqueo por User-Agent del nginx
  (§6.1–6.3: `444`/`502`, `/.well-known/mcp`, `405` del `GET /mcp`).


---

## 4. Frente D — `podcast-srt-upload` — PROPUESTO (2026-11-02)

### 4.1 Síntoma y causa medida

El titular no puede subir archivos `.srt` desde la biblioteca de medios. No es un permiso ni nginx: es
`wp_check_filetype_and_ext()`. Medido en producción (WordPress 7.1.4) con un SRT mínimo de 44 B:

```
finfo_file('/tmp/prueba.srt')                        → 'application/x-subrip'
wp_get_mime_types()['txt|asc|c|cc|h|srt']            → 'text/plain'
wp_check_filetype_and_ext('/tmp/prueba.srt', ...)    → array('ext' => false, 'type' => false, ...)
```

Core declara `.srt` como `text/plain`; libmagic detecta `application/x-subrip`; la discrepancia hace que
`_wp_handle_upload()` aborte con «Lo siento, no tienes permiso para subir este tipo de archivo».

La vía automática funciona porque `scripts/podcast-transcripts/wp-attach.php` usa `wp_upload_bits()` +
`wp_insert_attachment()` con `application/x-subrip` fijado a mano. En producción hay **834 adjuntos
`application/x-subrip` y 0 `text/plain`**: el tipo correcto ya está decidido por los datos.

### 4.2 Corrección propuesta (medida antes de escribirla)

Añadir `srt => application/x-subrip` **al final** de `upload_mimes` NO arregla nada: `wp_check_filetype()`
devuelve el primer patrón que casa y el de core (`txt|asc|c|cc|h|srt => text/plain`) gana. **Anteponerlo**
sí: `wp_check_filetype()` → `application/x-subrip` y `wp_check_filetype_and_ext()` → `ext = srt`,
`type = application/x-subrip`.

| Pieza | Cambio |
|---|---|
| `includes/class-srt-upload.php` | módulo nuevo `\Atareao\SrtUpload`; filtro `upload_mimes` que antepone el tipo desde `PodcastFeed::TRANSCRIPT_MEDIA_TYPE` |
| `atareao-functionality.php` | `require_once` + `init()` + subida de versión |
| `class-metaboxes.php` | el selector `wp.media` acepta `['application/x-subrip', 'text/plain']` |
| `tests/podcast-srt-upload/test_srt_upload.php` | arnés procedural nuevo (estilo `tests/audio-sync`) |
| `.justfile` | receta `test-srt-upload` |
| `README.md` del plugin | la vía manual deja de ser una promesa |

### 4.3 Puertas

1. **Aprobación de la spec** (`openspec/changes/podcast-srt-upload/`). Sin ella no se escribe código.
2. **RED** con evidencia CLI (`just test-srt-upload` fallando por «módulo no implementado»).
3. **GREEN** con `just test-srt-upload`, `just php-lint` y `just test` en verde.
4. **E2E en producción**: subir un SRT real, comprobar mime `application/x-subrip`, elegirlo en el
   selector, guardar el ID y verificar el `<podcast:transcript>` del feed. Limpiar el adjunto de prueba.

### 4.4 Fuera de alcance

`.vtt`, el contrato del feed, el pipeline batch, el validador de SubRip y el re-etiquetado de adjuntos
(no hay ninguno `text/plain`). Endurecer la validación del adjunto en el guardado (extensión/mime) es
deuda aparte.

### 4.5 Estado

- [x] Diagnóstico medido en producción
- [x] Change proposal redactado
- [ ] Spec aprobada por el titular
- [ ] RED / GREEN / REFACTOR
- [ ] E2E en producción
- [ ] PR y archivo
