# PLAN — Change `podcast-transcripts`: pipeline batch de transcripciones SubRip del podcast

> **Estado: código CERRADO y verificado. Falta la ejecución del lote completo (4.1, en curso), su
> verificación en producción (4.2) y la entrega (5.2 PR y 5.3 archive).** Los puntos 4.1, 4.2, 5.2 y
> 5.3 de `tasks.md` quedan abiertos y **4.1/5.2 se ejecutan con autorización explícita del titular**
> (ya concedida el 2026-10-31).

**Objetivo:** dar transcripción SubRip a los episodios del podcast (838 entradas publicadas) mediante un
pipeline batch **externo** al sitio (Whisper local sobre GPU + WP-CLI) que enlaza cada `.srt` con su
episodio por la meta `transcript-srt`, de modo que el feed del podcast emita `<podcast:transcript>`.
El sitio WordPress **no** transcribe nada.

**Restricción transversal:** ningún ciclo edita ficheros de código desde el orquestador; toda escritura
de código se delega a un subagente y toda verificación se hace por CLI y por lectura del fichero real
(nunca sobre el informe del subagente). Los artefactos OpenSpec (`proposal.md`, delta de spec y
`tasks.md`) los redacta el propio orquestador. Prohibido editar el core de WordPress dentro de los
contenedores: solo `wp-content/` está versionado y la funcionalidad vive en el plugin.

---

## 1. Decisiones de diseño (fijadas por el titular)

- **Dirección del pipeline: batch local + WP-CLI.** Whisper corre en la máquina con GPU (RTX 4060 Ti,
  16 GB), modelo `turbo` por defecto (sobreescribible) e idioma `es`. Los SRT se suben por lotes con
  `docker cp` y se resuelven en **una sola** pasada `wp eval-file`. Sin endpoint REST nuevo y sin código
  de sitio nuevo.
- **La fuente de verdad de la idempotencia es WordPress**, no un fichero de estado local: un episodio
  está hecho si su meta `transcript-srt` apunta a un adjunto legible. Una ejecución interrumpida o
  lanzada desde otra máquina converge al mismo resultado sin coordinación.
- **Un `wp eval-file` por lote, no por episodio:** 834 `docker cp` y 834 invocaciones de WP-CLI serían
  lentas y frágiles.
- **El audio no se versiona ni se conserva:** es material de trabajo temporal y se borra tras procesar.
- Descartado: importar el audio como adjunto; endpoint REST propio.

## 2. Mapa de la arquitectura

| Pieza | Ruta | Rol |
| --- | --- | --- |
| Pipeline | `scripts/podcast-transcripts/transcribe.py` | Solo biblioteca estándar. Contrato puro (sin E/S) + CLI. |
| Recolector | `scripts/podcast-transcripts/wp-collect.php` | Solo lectura: lista los episodios publicados y emite **únicamente JSON**. |
| Enlazador | `scripts/podcast-transcripts/wp-attach.php` | **Única** pieza que escribe en la base de datos de producción. Guardas de destino y de resultado. |
| Pruebas Python | `tests/podcast-transcripts/test_transcribe.py` | 26 métodos, `unittest`, solo stdlib. |
| Pruebas PHP | `tests/podcast-transcripts/test_attach.php` | Arnés procedural con stubs de WP; 32 aserciones. |
| Receta | `.justfile` → `just test-transcripts` | Ejecuta Python **y** el arnés PHP y propaga el fallo de cualquiera de los dos. |

**Flujo de datos:** `wp-collect.php` (WP-CLI) → JSON de episodios → descarga local del mp3 →
Whisper (`turbo`, `es`) → `render_srt` + `validate_srt` → escritura del SRT en un directorio de trabajo
→ `tar` por `ssh` + `docker cp` → un único `wp eval-file wp-attach.php <manifiesto>` → adjunto + meta
`transcript-srt` = ID del adjunto → el feed emite `<podcast:transcript>`.

## 3. Contrato congelado por los tests

Funciones puras: `format_timestamp`, `render_srt`, `write_srt`, `validate_srt` (lanza `SrtError`),
`is_usable_audio_url`, `classify` (`done` / `no_audio` / `pending`), `select_episodes` y
`plan_transcript_job` → diccionario con las claves exactas
`{to_process, skipped_done, skipped_no_audio, writes_allowed}`.

Esquema del registro que emite el recolector:
`{"id": int, "post_type": str, "status": str, "mp3_url": str, "transcript_id": int}`.

## 4. Ciclos TDD ejecutados (con evidencia CLI y commit propio)

| Ciclo | Commit | Contenido |
| --- | --- | --- |
| RED | `5dac502` | 17 métodos de test; el módulo no existe → importación fallida (RED honesto, sin stubs). |
| GREEN | `522ce47` | `transcribe.py` + `wp-collect.php` + `wp-attach.php` + `.gitignore` local. 17/17. |
| REFACTOR | `903e127` | Etiqueta del listado en `--dry-run`, traza `[i/total]` con `flush=True`, guarda de descarga de 0 bytes. |
| RED (remediación) | `e04197a` | 9 métodos nuevos en Python + arnés `test_attach.php` nuevo + receta con propagación del fallo. |
| GREEN (remediación) | `4308ef8` | Correcciones B1, B2, B3, I1, I2, I4, M1, M2 y M4; higiene: `__pycache__` fuera de git. |
| Docs | `52c7629`, `68f10ff`, `cfc28fc` | Delta ampliado, evidencia del RED/GREEN de la remediación, prueba de humo en producción, ensayo acotado, limitación del `Content-Type` y mapeo de las subtareas RED. |

## 5. Auditoría independiente y remediación

Una auditoría externa del rango `bf8a9cd..HEAD` emitió un veredicto de **«no ejecutar contra
producción»** hasta corregir los bloqueantes. **Todos los hallazgos se reprodujeron por CLI antes de
tocar nada**: nada se aceptó por el informe.

Defectos corregidos: **B1** un SRT no-UTF8 provocaba un `UnicodeDecodeError` que escapaba y mataba el
lote; **B2** `is_usable_audio_url` aceptaba espacios y caracteres de control y el `InvalidURL` del
cliente HTTP escapaba; **B3** un SRT vacío se enlazaba y marcaba `done` para siempre; **I1**
`wp-attach.php` no validaba tipo ni estado del destino; **I2** `int(record["id"])` estaba fuera del
`try`; **I4** se ignoraba el retorno de `update_post_meta`; **M1** el BOM hacía rechazar un SRT válido;
**M2** se aceptaban minutos y segundos ≥ 60; **M4** `nan` e `inf` llegaban a `ValueError`. Descartada
una sospecha falsa: los consumidores de uploads (`wpcli` y el runtime) **sí** comparten el volumen
`/var/www/html`.

El delta de la spec creció hasta **7 requisitos y 27 escenarios**: guardas del helper de subida,
aislamiento total ante datos sucios, SRT sin bloques, BOM admitido y rangos de tiempo.

Deudas **no bloqueantes** (decisión del titular del 2026-10-31: se dejan documentadas, no se atacan en
este change): I3 (el arnés PHP existe pero vive fuera de `just test`), M3 (bloques separados por doble
línea en blanco), M6 (sin validar `--ssh-host` ni `--wpcli-container`), M7 (`_extract_balanced` ante un
`[` en el ruido), `--limit` negativo clampeado en silencio, ausencia de comprobación de `Content-Type`
y `Content-Length` en la descarga, y `UNKNOWN_EPISODE_ID = -1` duplicado en «IDs fallidos».

## 6. Ensayo real acotado en producción (2026-10-31)

Se transcribieron **5 episodios** (el máximo del rango autorizado), elegidos así: 3 antiguos (`11997`,
`12000`, `12006`) y 2 recientes (`18828`, `18823`) porque el feed **solo lista los 100 más recientes** y
los antiguos no ejercían su rama.

- `--limit 3`: 3 resueltos, 0 fallidos, 239 s (incluye la descarga única del modelo `large-v3-turbo`,
  571 MB).
- `--episode 18828 --episode 18823`: 2 resueltos, 0 fallidos, 172 s.
- **Metas y adjuntos:** los 5 episodios apuntan a adjuntos `application/x-subrip` legibles (`18849` a
  `18853`), sin duplicados.
- **El feed servido emite la etiqueta** por primera vez, en el `<item>` correcto:
  `<podcast:transcript url=".../podcast-18828.srt" type="application/x-subrip" language="es" />`
  (`x-cache-status: BYPASS`).
- **SRT servidos:** HTTP 200 y SubRip válido (102, 335 y 787 bloques); español correcto con acentos.
- **Idempotencia real:** reejecutar con `--episode` → 0 resueltos, 3 omitidos (*ya hechos*), ningún
  adjunto nuevo, 4 s. Nota: la idempotencia se verifica con `--episode`, **no** con `--limit`, que
  filtra primero los ya hechos y luego trunca.
- **Prueba de humo de la fontanería real:** `tar` por `ssh` → `docker cp` → `wp eval-file $args`
  funcionan; un ID que no es podcast y uno inexistente se rechazan con
  `"el destino no es un podcast publicado"` dejando las metas intactas.

## 7. Verificación

`just test-transcripts` EXIT=0 (Python 26/26 `OK`, PHP `Total passed: 32, Total failed: 0`);
`just test` 1068/0; `just php-lint` 65 ficheros sin errores; `phpcs` de `scripts/podcast-transcripts/`
y de `tests/podcast-transcripts/` limpio (control negativo comprobado); `php -l` limpio en los dos
helpers; `openspec validate podcast-transcripts --strict` → valid.

## 8. Estimación del lote completo

Cadencia medida sobre los dos episodios recientes: 51,1 min de audio en 172 s → **17,8× tiempo real**.
El archivo de audio son 13,7 GB ≈ **327 h** (ratio medido: 0,70 MB por minuto de audio) → **~18 h de
GPU** para los 834 pendientes, ~1,3 min por episodio de media. El `itunes:duration` del sitio está
corrupto (0,2-0,6 min por episodio en los 38 ítems que lo traen), así que la estimación se apoya en
bytes y en los SRT, no en esa meta.

## 9. Estado de las tareas y puertas que quedan

Cerradas: contexto (0.x), RED (1.x), GREEN (2.1-2.4), remediación de la auditoría (2B.1-2B.7), ensayo
acotado (3.1-3.3), limitación del `Content-Type` (4.3) y batería de pruebas sin regresiones (5.1).

Pendientes:

- **4.1** Ejecutar el pipeline sobre los 829 pendientes. **Autorizado y en curso desde el 2026-10-31
  18:16:37** (dato del kernel vía `ps -o lstart`), invocado con
  `--work-dir /home/lorenzo/.cache/podcast-transcripts` y registrado en
  `~/.cache/podcast-transcripts/batch-full.log`.
- **4.2** Verificar el resultado en producción: recuento de episodios con `transcript-srt` accesible,
  los 4 sin audio omitidos (no fallidos silenciosos), etiqueta en el feed y SRT de muestra válido.
- **5.2** PR de `feature/podcast-transcripts` a `development`. **Autorizado.**
- **5.3** `openspec archive podcast-transcripts`, fusionando el delta en
  `openspec/specs/podcast-transcripts/spec.md` (tras el merge del PR).

## 10. Limitaciones conocidas y deudas aceptadas

- **`Content-Type` servido incoherente:** el feed declara `type="application/x-subrip"`, pero nginx no
  mapea `.srt` en `mime.types`, así que el fichero se sirve como `application/octet-stream`. Muchos
  clientes lo toleran; corregirlo es configuración de nginx y merece su propio cambio.
- **La subida a producción es una única pasada final.** Durante las ~18 h de lote producción no recibe
  nada (los 829 adjuntos entran de golpe al terminar), y la reanudación *dentro* de un run se apoya en
  los SRT locales del `--work-dir`, no en WordPress. Por eso el lote se lanza con un `--work-dir`
  persistente: el valor por defecto es tmpfs. Candidato a change futuro (subida por tandas o
  directorio de trabajo persistente por defecto).
- **`just phpcs` (receta completa) falla de forma preexistente** escaneando ficheros `.js` como PSR12;
  se usa por rutas.
- Manifiestos `ard.json` y `ai-catalog.json` con caché FastCGI de 6 h (**SEC-BE-002**).
- `itunes:duration` ausente o corrupto en la mayoría de los ítems del feed.
- Referencias colgantes en `AGENTS.md` a `docs/sdd-tdd-workflow.md` y `docs/project-state.md` (`docs/`
  está en `.gitignore` y nunca se versionó).
