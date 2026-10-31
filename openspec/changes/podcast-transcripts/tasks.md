# Tasks: Pipeline de transcripciones del podcast

## 0. Contexto y decisiones previas

- [x] 0.1 Confirmar el alcance: el sitio **no** transcribe en tiempo de ejecución; el pipeline es un
      proceso batch externo que corre donde hay GPU (RTX 4060 Ti, 16 GB)
- [x] 0.2 Confirmar el modelo de Whisper (`turbo` por defecto, mejor español que el `medium` cacheado;
      sobreescribible) y el idioma (`es`)
- [x] 0.3 Confirmar la ubicación del código: `scripts/podcast-transcripts/` (versionado) y
      `tests/podcast-transcripts/` para las pruebas unitarias

## 1. RED — pruebas que describen el contrato (antes de implementar)

- [x] 1.1 `tests/podcast-transcripts/test_transcribe.py` (solo biblioteca estándar, `unittest`), 15 métodos:
      - [x] 1.1.1 `escenario Validez del SubRip producido / bloques numerados consecutivos`
            → `test_render_produces_consecutive_numbered_blocks`, `test_rejects_numbering_gap`
      - [x] 1.1.2 `escenario Las marcas de tiempo son válidas y crecientes`
            → `test_format_timestamp`, `test_timestamps_are_well_formed_and_non_decreasing`,
              `test_rejects_malformed_timing_line`, `test_rejects_non_increasing_times`,
              `test_rejects_block_without_text`
      - [x] 1.1.3 `escenario El SRT conserva los caracteres del idioma` (UTF-8)
            → `test_utf8_characters_are_preserved`
      - [x] 1.1.4 `escenario Un episodio ya transcrito se salta` (decisión de idempotencia, función pura)
            → `test_classify_done_when_transcript_id_positive`, `test_plan_skips_done_episodes`
      - [x] 1.1.5 `escenario Episodio sin mp3-url se omite` y `escenario Episodio con URL no utilizable se omite`
            → `test_classify_no_audio_for_unusable_urls`, `test_is_usable_audio_url_rejects_unusable`,
              `test_plan_counts_no_audio_episodes`
      - [x] 1.1.6 `escenario El alcance se restringe a podcasts publicados` (filtro, función pura)
            → `test_select_episodes_excludes_drafts_and_other_types`
      - [x] 1.1.7 `escenario El modo de ensayo no escribe nada` (el planificador no emite acciones de escritura)
            → `test_dry_run_forbids_writes_but_still_plans`, `test_plan_has_exactly_the_contract_keys`,
              `test_limit_truncates_to_process_only`
- [x] 1.2 Ejecutar las pruebas por CLI y **confirmar que fallan** (las funciones aún no existen):
      `python3 -m unittest discover -s tests/podcast-transcripts`
- [x] 1.3 Receta `just test-transcripts` en `.justfile` que ejecuta 1.2
- [x] 1.4 Registrar la evidencia literal del RED (salida de la CLI)

#### Evidencia — RED (2026-10-31)

Decisiones confirmadas por el titular: el sitio **no** transcribe; modelo `turbo` (sobreescribible),
idioma `es`; código versionado en `scripts/podcast-transcripts/`.

`tests/podcast-transcripts/test_transcribe.py` (279 líneas, 15 métodos, solo biblioteca estándar) cubre
los escenarios del delta. El módulo de implementación **no existe todavía**, así que el RED es una
importación fallida —reportada sin disimular, sin `try/except` ni stubs—:

```
$ python3 -m unittest discover -s tests/podcast-transcripts -v
ERROR: test_transcribe (unittest.loader._FailedTest.test_transcribe)
ImportError: Failed to import test module: test_transcribe
  File ".../tests/podcast-transcripts/test_transcribe.py", line 26, in <module>
    import transcribe
ModuleNotFoundError: No module named 'transcribe'
Ran 1 test in 0.000s
FAILED (errors=1)                                    → EXIT=1

$ just test-transcripts
FAILED (errors=1)
error: recipe `test-transcripts` failed with exit code 1
```

**Sin regresión en la suite heredada** (el RED no rompe nada existente):

```
$ just test
Total passed: 1068, Total failed: 0
All tests passed successfully!
```

## 2. GREEN — implementación mínima

- [x] 2.1 `scripts/podcast-transcripts/transcribe.py` (solo biblioteca estándar):
      - [x] 2.1.1 Escritura y **validación** del SRT (bloques, numeración, tiempos, UTF-8)
      - [x] 2.1.2 Decisión de idempotencia: un episodio con `transcript-srt` hacia adjunto accesible se salta
      - [x] 2.1.3 Guarda de URL: solo `http`/`https` utilizables; lo demás se omite
      - [x] 2.1.4 Selección de episodios: solo `podcast` **publicado**; `--limit N`; `--dry-run`
      - [x] 2.1.5 Recolección de pendientes desde producción vía WP-CLI (JSON)
      - [x] 2.1.6 Descarga del MP3 remoto (sigue la redirección `anchor.fm` → CloudFront)
      - [x] 2.1.7 Transcripción con `whisper --language es --output_format srt`
      - [x] 2.1.8 Subida por lote: `docker cp` del directorio de SRT + **una** pasada `wp eval-file` que
            crea los adjuntos y escribe las metas
      - [x] 2.1.9 Resumen final: resueltos / omitidos / fallidos
- [x] 2.2 Ejecutar `just test-transcripts` → **verde**
- [x] 2.3 `python3 -m py_compile` (o equivalente) del script, sin errores
- [x] 2.4 REFACTOR: etiqueta propia para el listado de pendientes en `--dry-run`, traza de progreso
      `[i/total] id` con `flush=True` durante el lote y guarda de descarga de 0 bytes

#### Evidencia — GREEN (2026-10-31)

Commits `522ce47` (✨ feat) y `903e127` (♻️ refactor) sobre el RED `5dac502`. Ficheros nuevos:
`transcribe.py`, `wp-collect.php`, `wp-attach.php`. Nada bajo `wp-content/` tocado.

```
$ just test-transcripts
Ran 17 tests in 0.001s
OK                                                   → EXIT=0

$ python3 -m py_compile scripts/podcast-transcripts/transcribe.py
(sin salida)                                         → EXIT=0

$ just test
Total passed: 1068, Total failed: 0
All tests passed successfully!                       → EXIT=0

$ just phpcs scripts/podcast-transcripts
(limpio)                                             → EXIT=0
   control negativo con un fichero mal formado       → EXIT=2

$ just php "-l scripts/podcast-transcripts/wp-attach.php"
No syntax errors detected in scripts/podcast-transcripts/wp-attach.php

$ python3 scripts/podcast-transcripts/transcribe.py --dry-run --limit 3
== ENSAYO (--dry-run): no se escribirá ni se descargará nada ==
pendientes : 3
ya hechos  : 0
sin audio  : 4
pendientes a procesar (limit 3):
  - 11997: https://anchor.fm/s/5a5b39c/podcast/play/1305076/...
  - 12000: https://anchor.fm/s/5a5b39c/podcast/play/1323727/...
  - 12006: https://anchor.fm/s/5a5b39c/podcast/play/1370227/...
(ensayo: ninguna acción ejecutada)                   → EXIT=0
```

Plan sobre el conjunto real: 838 registros publicados = **834 pendientes + 4 sin audio** (3 con `mp3-url`
vacía y 1 con el literal `"15"`), 0 ya hechos. Confirmado con `wp-collect.php` ejecutado en producción.

## 2B. Remediación de la auditoría independiente (RED → GREEN)

Auditoría externa (read-only) sobre `bf8a9cd..HEAD`, **antes** de tocar producción. Cada hallazgo fue
reproducido por CLI antes de aceptarlo; el veredicto fue **no ejecutar contra producción** y estos son
los defectos confirmados:

| ID | Defecto (verificado) | Efecto real |
| --- | --- | --- |
| B1 | `_ensure_valid_srt` solo captura `OSError`; un SRT no-UTF8 lanza `UnicodeDecodeError` (`ValueError`) | **aborta el lote entero** y el SRT corrupto no se borra: cada reintento reventaba igual |
| B2 | `is_usable_audio_url` acepta URLs con espacios/caracteres de control; `urlopen` lanza `http.client.InvalidURL`, no capturado | **aborta el lote entero** ante un solo valor sucio en la BD |
| B3 | `validate_srt("")` devuelve `[]` sin error y `_ensure_valid_srt` ignora el retorno | se enlaza un SRT **vacío** y el episodio queda `done` de forma **permanente** |
| I1 | `wp-attach.php` no comprueba tipo ni estado del post destino | puede escribirse meta en un post arbitrario o inexistente (basura huérfana en la BD real) |
| I2 | `int(record["id"])` se evalúa **fuera** del `try` por episodio | un registro sin `id` útil aborta el lote |
| I4 | `wp-attach.php` ignora el retorno de `update_post_meta` | reporta resuelto un episodio cuya meta no llegó a escribirse |
| M1 | `validate_srt` no descarta el BOM | un SRT con BOM se rechaza |
| M2 | `TIMING_RE` acepta minutos/segundos ≥ 60 | se aceptan marcas de tiempo inválidas |
| M4 | `format_timestamp(nan/inf)` lanza `ValueError`/`OverflowError`, no `SrtError` | rompe la promesa de error claro |
| M5 | `tests/podcast-transcripts/__pycache__/*.pyc` versionado | artefacto compilado en el repo |

Descartado tras verificar: la sospecha de que `wpcli` y el runtime no compartieran el volumen de
uploads — **sí lo comparten** (`wordpress_wordpress → /var/www/html` en ambos), así que la
comprobación de legibilidad es fiable.

- [x] 2B.1 RED: pruebas que fijan B1, B2, B3, I2, M1, M2, M4 en `tests/podcast-transcripts/`
- [x] 2B.2 RED: pruebas del helper de subida en `tests/podcast-transcripts/test_attach.php`
      (guardas de tipo/estado, meta verificada, nunca un ID sin adjunto real)
- [x] 2B.3 GREEN: correcciones en `scripts/podcast-transcripts/transcribe.py`
- [x] 2B.4 GREEN: guardas en `scripts/podcast-transcripts/wp-attach.php`
- [x] 2B.5 Higiene: sacar `__pycache__` del control de versiones
- [x] 2B.6 Re-verificación completa: `just test-transcripts`, `just test`, `just php-lint`, `just phpcs`
- [x] 2B.7 Prueba de humo del circuito completo **en producción** con un ID que no es podcast

#### Evidencia — RED de la remediación (commit `e04197a`)

```
$ python3 -m unittest discover -s tests/podcast-transcripts -v
...
Ran 26 tests                                            → FAILED (failures=8, errors=7)
  B1 test_non_utf8_srt_raises_pipeline_error        → UnicodeDecodeError: 'utf-8' codec can't decode byte 0xf3
  B2 test_is_usable_audio_url_rejects_whitespace…   → AssertionError: True is not false (x3)
  B2 test_download_audio_translates_invalid_url…    → http.client.InvalidURL: URL can't contain control characters
  B3 test_empty_or_blank_srt_raises_pipeline_error  → AssertionError: PipelineError not raised (x2)
  I2 test_run_pipeline_continues_when_a_record_has_no_id → KeyError: 'id'
  M1 test_validate_srt_accepts_leading_bom         → SrtError: numeración no consecutiva: hay '\ufeff1'
  M2 test_rejects_minutes_or_seconds_at_60_or_more  → AssertionError: SrtError not raised (x3)
  M4 test_format_timestamp_rejects_non_finite_values→ ValueError/OverflowError
(las 17 pruebas originales siguen OK; la única nueva que pasa es la guarda positiva de M2)

$ podman run --rm … php tests/podcast-transcripts/test_attach.php
Total passed: 26, Total failed: 6
  FAIL: Un post que no es podcast devuelve un resultado con error
  FAIL: Un post que no es podcast: la meta transcript-srt NO se escribe
  FAIL: Un podcast en borrador devuelve un resultado con error
  FAIL: Un podcast en borrador: la meta transcript-srt NO se escribe
  FAIL: Si update_post_meta falla el resultado trae error
  FAIL: Si update_post_meta falla no se declara un attachment_id como éxito

$ just test-transcripts; echo EXIT=$?
EXIT=1                        (el fallo de Python no queda enmascarado por el PHP)
```

#### Evidencia — GREEN de la remediación (commit `4308ef8`)

```
$ just test-transcripts
Ran 26 tests
OK                                                     (Python)
Total passed: 32, Total failed: 0
All tests passed successfully!                         (PHP)   → EXIT=0

$ just test
Total passed: 1068, Total failed: 0                    → EXIT=0

$ just phpcs tests/podcast-transcripts   → EXIT=0
$ just phpcs scripts/podcast-transcripts → EXIT=0
$ just php -l wp-attach.php / wp-collect.php → No syntax errors detected
```

Sondas independientes del contrato (16/16), ejerciendo cada defecto por separado: SRT no-UTF8 y SRT
vacío aislados como `PipelineError`; URL con espacio/`\n` rechazada; URL válida y `https://` sin host
sin regresión; `download_audio` con URL inválida → `PipelineError`; BOM aceptado; `60` s y `99` min
rechazados y `59:59` aceptado; `nan`/`inf` → `SrtError`; y el contrato antiguo intacto
(`360000.0` → `100:00:00,000`, `render_srt([])` → `""`, `validate_srt("")` → `[]`).

#### Evidencia — prueba de humo del circuito en producción (2B.7)

Se envió un manifiesto real con `1.srt` y `999999999.srt` y se ejecutó el helper en el contenedor:

```
$ ssh atareao "docker exec wpcli wp eval-file …/wp-attach.php …/manifest.json"
{"1":{"error":"el destino no es un podcast publicado"},
 "999999999":{"error":"el destino no es un podcast publicado"}}

metas antes   : {"1":"","999999999":""}
metas después : {"1":"","999999999":""}          (intactas)
```

Valida de una vez la fontanería real (`tar` por `ssh`, `docker cp`, `$args` de `wp eval-file`, salida
solo-JSON) y la guarda I1 sobre el sistema real. Queda fuera de la prueba, y por tanto pendiente de
verificar en el ensayo, el camino que **sí** escribe: el asistente solo cubre el rechazo con stubs.

## 3. Ensayo real acotado (antes del lote completo)

- [x] 3.1 `--dry-run` sobre el conjunto real: comprobar que **no** escribe nada y que el plan es correcto
- [x] 3.2 Ejecutar con `--limit` reducido (p. ej. 3-5 episodios) y **verificar**:
      - [x] 3.2.1 Se crean los adjuntos `.srt` y las metas apuntan a IDs válidos
      - [x] 3.2.2 El feed **servido** emite `<podcast:transcript>` para esos episodios (primera vez que
            la rama se ejerce en producción)
      - [x] 3.2.3 La URL del adjunto responde `200` y el SRT es válido
      - [x] 3.2.4 Segunda ejecución con el mismo límite: los ya transcritos se **saltan** (idempotencia
            verificada en producción)
- [x] 3.3 Medir el tiempo real por episodio para estimar el lote completo (835 episodios, 13,7 GB)

#### Evidencia — ensayo real acotado (2026-10-31)

Commits `4308ef8` (código) sobre `e04197a` (RED de la remediación). Se eligieron **5 episodios**, el
máximo del rango autorizado, porque el feed solo lista los 100 más recientes: transcribir únicamente
los 3 más antiguos no habría ejercido la rama `<podcast:transcript>` del feed.

```
$ python3 scripts/podcast-transcripts/transcribe.py --limit 3
[1/3] 11997: descargando...
[1/3] 11997: transcribiendo con turbo (es)...
[1/3] 11997: ok
[2/3] 12000: ... ok
[3/3] 12006: ... ok
resueltos : 3 | omitidos : 4 (ya hechos: 0, sin audio: 4) | fallidos : 0     → EXIT=0
13:16:08 → 13:20:07 (239 s, incluye la descarga única del modelo large-v3-turbo, 571 MB)

$ python3 scripts/podcast-transcripts/transcribe.py --episode 18828 --episode 18823
[1/2] 18823: ok   [2/2] 18828: ok
resueltos : 2 | omitidos : 0 | fallidos : 0                                   → EXIT=0
13:21:12 → 13:24:04 (172 s)
```

**3.2.1 Metas y adjuntos** (los 5 episodios, verificados con WP-CLI en producción):

```
11997 → meta "18849" → /var/www/html/wp-content/uploads/2026/10/podcast-11997.srt  (12 549 B, legible)
12000 → meta "18850" → …/podcast-12000.srt  (19 878 B, legible)
12006 → meta "18851" → …/podcast-12006.srt  (57 483 B, legible)
18823 → meta "18852"                                      18828 → meta "18853"
tipo de todos los adjuntos: application/x-subrip
sin duplicados: el listado de adjuntos .srt es exactamente [18849..18853]
```

**3.2.2 El feed servido emite la etiqueta** (primera vez en producción; `x-cache-status: BYPASS`, así
que el feed no está cacheado y refleja las metas al instante):

```
$ curl -s https://atareao.es/podcast/feed/ | grep podcast:transcript
<podcast:transcript url="https://atareao.es/wp-content/uploads/2026/10/podcast-18828.srt"
                    type="application/x-subrip" language="es" />
<podcast:transcript url="https://atareao.es/wp-content/uploads/2026/10/podcast-18823.srt"
                    type="application/x-subrip" language="es" />
```
Cada etiqueta aparece en **su** `<item>` (episodios 18828 y 18823), no en otro. 2 de 100 ítems.

**3.2.3 El adjunto servido**:

```
podcast-11997.srt → HTTP 200, 12 549 B → validate_srt: 102 bloques válidos
podcast-18828.srt → HTTP 200, 33 159 B → validate_srt: 335 bloques válidos
podcast-18823.srt → HTTP 200, 50 486 B → validate_srt: 787 bloques válidos
Content-Type: application/octet-stream   (limitación de nginx ya documentada, ver 4.3)
```
Contenido real (primer bloque de 11997): «Sobre el peligro de las apuestas. Sería más o menos abril de
2017 cuando me entrevistó Paco Estrada…» — español correcto, con acentos y sin artefactos.

**3.2.4 Idempotencia en producción** (se ejecuta con `--episode`, **no** con `--limit`: `--limit` filtra
primero los ya hechos y luego trunca, así que repetirlo procesaría episodios *nuevos*, no los mismos):

```
$ python3 … --episode 11997 --episode 12000 --episode 12006
resueltos : 0 | omitidos : 3 (ya hechos: 3, sin audio: 0) | fallidos : 0       (4 s)
adjuntos .srt después: [18853,18852,18851,18850,18849]   → intactos, ningún adjunto nuevo
```

**3.3 Estimación del lote completo.** Cadencia medida sobre los dos episodios recientes: 51,1 min de
audio en 172 s → **17,8× tiempo real**. El audio total del archivo son 13,7 GB ≈ **327 h** (ratio
medido: 0,70 MB por minuto de audio) → **~18 h de GPU** para los 834 pendientes, ~1,3 min por episodio
de media. Dato a tener en cuenta: el `itunes:duration` del sitio está corrupto (0,2-0,6 min por
episodio en los 38 ítems que lo traen), así que la estimación se apoya en bytes y en los SRT, no en esa
meta.

## 4. Lote completo y verificación

- [ ] 4.1 Ejecutar el pipeline sobre **todos** los pendientes (reanudable si se interrumpe)
- [ ] 4.2 Verificar el resultado en producción:
      - [ ] 4.2.1 Nº de episodios con `transcript-srt` accesible (esperado: los 835 con `mp3-url`)
      - [ ] 4.2.2 Los 3 episodios con `mp3-url` vacía siguen omitidos, no fallidos-silenciosos
      - [ ] 4.2.3 El feed servido declara `<podcast:transcript>` en los ítems con transcripción
      - [ ] 4.2.4 Un SRT servido de muestra es SubRip válido y su URL responde `200`
- [x] 4.3 Documentar la **limitación conocida**: el `.srt` se sirve como `application/octet-stream`
      (nginx sin mapeo `srt`) mientras el feed declara `application/x-subrip`
      — documentada en `proposal.md` («El `Content-Type` servido no coincide con el declarado») y
      confirmada en el ensayo (los 3 SRT servidos respondieron 200 con `application/octet-stream`)
- [ ] 4.4 Anotar la evidencia E2E en este `tasks.md` (convención del proyecto)

## 5B. Deuda aceptada (no bloqueante, decisión del titular del 2026-10-31)

Hallazgos menores de la auditoría independiente que **no** se corrigen en este change. Se dejan
documentados también en `proposal.md` («Deuda aceptada de la auditoría independiente») para que no se
pierdan. Ninguno afecta a la corrección del lote validado:

- [ ] 5B.1 I3 — llevar `tests/podcast-transcripts/test_attach.php` al alcance de `just test`
- [ ] 5B.2 M3 — dos líneas en blanco entre bloques del SRT (estándar SubRip, tolerado por los parsers)
- [ ] 5B.3 M6 — validar `--ssh-host` y `--wpcli-container` (hoy el fallo es confuso, no inseguro)
- [ ] 5B.4 M7 — `_extract_balanced` ante un `[` en el ruido previo al JSON
- [ ] 5B.5 `--limit` negativo se clampea en silencio en vez de dar error de argumento
- [ ] 5B.6 Descarga sin comprobar `Content-Type` ni `Content-Length` (un HTML de error con `200` llegaría a Whisper)
- [ ] 5B.7 `UNKNOWN_EPISODE_ID = -1` colisiona entre «episodio sin id» y «fallo de subida sin id»

## 5. Entrega

- [x] 5.1 `just php-lint` y las pruebas del repo (`just test`) sin regresiones
      → `just test-transcripts` EXIT=0 (Python 26/26 OK, PHP 32/32), `just test` 1068/0,
        `just php-lint` 65 ficheros sin errores, `phpcs` de `scripts/` y `tests/podcast-transcripts/` limpio
- [ ] 5.2 PR de la rama `feature/podcast-transcripts` a `development` (merge commit, convención del proyecto)
- [ ] 5.3 `openspec archive podcast-transcripts` fusionando el delta en `openspec/specs/podcast-transcripts/spec.md`

## Notas de implementación

- **El sitio no transcribe.** Cualquier diseño que ejecute Whisper dentro del contenedor de WordPress es
  incorrecto por construcción: no hay GPU ni herramientas, y el sitio no debe hacer trabajo pesado.
- **La fuente de verdad de la idempotencia es WordPress**, no un fichero de estado local: así una
  ejecución interrumpida o lanzada desde otra máquina converge al mismo resultado sin coordinación.
- **Un `wp eval-file` por lote, no por episodio:** 835 `docker cp` y 835 invocaciones de WP-CLI serían
  lentas y frágiles. Se copia el directorio de SRT de una vez y se resuelven todos los adjuntos en una
  pasada.
- **El audio no se versiona ni se conserva:** es material de trabajo temporal y se elimina tras procesar.
