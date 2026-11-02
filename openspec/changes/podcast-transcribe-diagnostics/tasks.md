# Tasks: Diagnóstico utilizable de un fallo de transcripción

> Estado: **implementado y auditado** (2026-11-02). Spec aprobada por el titular (`143 -> 144`).
> Rama `feature/podcast-transcribe-diagnostics`; **PR en borrador**, sin fusionar. E2E ejecutada.
> Ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Alcance: **solo el diagnóstico**. No hay reintentos, ni cambios en la subida, ni en el validador.

## 0. Contexto y decisiones previas

- [x] 0.1 Localizar el fallo real: el episodio `17732` se contabilizó como fallido con
      `fallo al transcribir: sin detalle` (línea 3038 de `batch-full.log`, 2026-11-01)
- [x] 0.2 Localizar el defecto: `transcribe_audio` construye el motivo **solo** desde
      `_text(proc.stderr)` y no reporta el `returncode` (`scripts/podcast-transcripts/transcribe.py:500`)
- [x] 0.3 Medir el comportamiento real de la herramienta: `openai-whisper 20250625` sobre Python 3.14.8
      escribe **todo por stdout** (`23 025 B`) y **cero por stderr** (`0 B`). Cualquier fallo que no sea
      una excepción de Python produce siempre «sin detalle»
- [x] 0.4 Descartar la hipótesis del audio: mismo `mp3-url`, mismo `User-Agent` y el comando literal del
      pipeline → `exit 0` y SRT válido (184 bloques, 0 vacíos, `validate_srt` del proyecto) en **dos
      ejecuciones consecutivas**, con el mismo tamaño (24 389 B). La única pista que queda es una muerte
      abrupta del proceso, cuyo rastro se perdió
- [x] 0.5 Confirmar el destino en la spec canónica: `openspec/specs/podcast-transcripts/spec.md` →
      requisito «Aislamiento de fallos y contabilidad del lote» (6 escenarios que hay que conservar
      íntegros en el delta `MODIFIED`)
- [x] 0.6 Confirmar el arnés: `tests/podcast-transcripts/test_transcribe.py` con `unittest`, recogido por
      `python3 -m unittest discover -s tests/podcast-transcripts` dentro de `just test-transcripts`. No hay
      linter de Python configurado en el proyecto: la verificación es la suite
- [x] 0.7 Decisión de diseño: `stderr` primero y `stdout` como respaldo (no al revés), y **no** volcar la
      salida completa al journal (23 KB por episodio hacen el log inservible en un lote de 838)

## 1. RED — pruebas que describen el contrato (antes de implementar)

- [x] 1.1 Prueba *con la salida de error vacía, el detalle sale de la salida estándar*: comando que falla
      escribiendo todo por `stdout` y nada por `stderr` → el motivo contiene esas líneas y **no** «sin
      detalle»
- [x] 1.2 Prueba *el código de salida se informa siempre*: comando con `exit 3` → el motivo contiene `3`
- [x] 1.3 Prueba *una muerte por señal se informa como señal*: matar el proceso hijo con `SIGKILL` (o
      doblar un `returncode` negativo) → el motivo nombra la señal y no un código positivo
- [x] 1.4 Prueba *el comando termina bien pero no deja el SRT*: `exit 0` sin fichero → el motivo lo dice y
      es distinguible del fallo del comando
- [x] 1.5 Prueba *el motivo conserva varias líneas*: traza de varias líneas → se conservan las últimas, no
      solo la última
- [x] 1.6 Prueba *un fallo sin salida no inventa un detalle*: sin salida y sin señal → se informa del
      código observado
- [x] 1.7 Ejecutar por CLI (`python3 -m unittest discover -s tests/podcast-transcripts`) y **confirmar que
      las nuevas pruebas fallan** con el código de hoy; pegar la salida literal
- [x] 1.8 Confirmar que **no hay regresión**: el resto de la suite sigue verde (`just test-transcripts` y
      `just test` 1068/0) — pegar la salida literal
- [x] 1.9 Registrar la evidencia del RED en este fichero

#### Evidencia — RED

Commit `363bfcf`. Nueve pruebas nuevas en `TranscriptionFailureDiagnosticsTest` (6 unitarias del helper
puro `failure_detail` + 3 de integración con un `whisper` falso en el `PATH`):

```
$ python3 -m unittest discover -s tests/podcast-transcripts -v
test_empty_stderr_falls_back_to_stdout ... ERROR
test_exit_code_is_always_reported ... ERROR
test_exit_zero_without_srt_is_explicit_and_distinguishable ... ERROR
test_failure_without_output_reports_the_code_without_inventing_detail ... ERROR
test_several_lines_are_kept_not_only_the_last ... ERROR
test_signal_death_is_reported_as_signal ... ERROR
test_transcribe_audio_reports_clean_exit_without_srt ... FAIL
test_transcribe_audio_reports_signal_death ... FAIL
test_transcribe_audio_reports_stdout_and_exit_code ... FAIL
Ran 35 tests
FAILED (failures=3, errors=6)                          ← EXIT=1

$ just test               → EXIT=0    Total passed: 1068, Total failed: 0   (sin regresión)
$ just test-transcripts   → EXIT=1    (Python en rojo; el arnés PHP sigue 32/0)
```

Las 26 pruebas heredadas siguen verdes y `scripts/podcast-transcripts/transcribe.py` queda **intacto**.

## 2. GREEN — implementación mínima

- [x] 2.1 `transcribe.py`: un *helper* **puro** que componga el motivo a partir del resultado del comando
      (código de salida, `stderr`, `stdout`) y de la existencia del `.srt`; sin efectos secundarios y
      verificable sin GPU
- [x] 2.2 `transcribe_audio` usa ese *helper* en lugar de `detail[-1] if detail else "sin detalle"`,
      conservando el tipo `PipelineError` y el prefijo del episodio (el resto del contrato de errores no
      cambia)
- [x] 2.3 Pasar la suite completa (`just test-transcripts` → `exit 0`) y pegar la salida literal

#### Evidencia — GREEN

Commit `253fe61`. En `scripts/podcast-transcripts/transcribe.py`: constante con nombre
`FAILURE_DETAIL_MAX_LINES = 5`, helpers puros `_last_non_empty_lines` / `failure_detail`, y
`transcribe_audio` usa `failure_detail(...)` en lugar de `tail = detail[-1] if detail else "sin detalle"`.

```
$ python3 -m unittest discover -s tests/podcast-transcripts
Ran 37 tests
OK                                                     ← EXIT=0
$ just test-transcripts   → EXIT=0    Ran 37 tests OK + Total passed: 32, Total failed: 0
$ just test               → EXIT=0    Total passed: 1068, Total failed: 0
$ python3 -m py_compile scripts/podcast-transcripts/transcribe.py \
      tests/podcast-transcripts/test_transcribe.py     → EXIT=0
```

**Ronda de auditoría** (una revisión independiente no encontró bloqueantes ni mayores; sí tres menores,
corregidos en el mismo ciclo RED→GREEN, dentro de las 37 pruebas): **M-1** el caso «`exit 0` sin SRT» ya
**no descarta** la salida útil (la anexa); **M-2** con `max_lines <= 0` el mensaje **no** afirma que no
hubo salida cuando sí la hubo; **R-1** se añaden pruebas para las ramas de robustez (`señal desconocida`,
`max_lines = 0`); **O-1** la cabecera del fichero de pruebas cita el change actual y el directorio canónico.

## 3. REFACTOR

- [x] 3.1 Re-ejecutar `just test-transcripts` y `just test` (sin regresiones)
- [x] 3.2 Repasar el *helper*: sin números mágicos sin nombre (cuántas líneas se conservan), sin
      `stdout`/`stderr` mezclados dos veces, sin dependencia de la GPU ni de la red
- [x] 3.3 Confirmar que solo se usa la biblioteca estándar (el repo no tiene gestor de paquetes)

#### Evidencia — REFACTOR

```
$ just test-transcripts   → EXIT=0    Python 37 OK + PHP 32/0
$ just test               → EXIT=0    1068/0
```

El helper es puro, sin efectos secundarios ni estado global; las 5 líneas conservadas viven en la
constante con nombre `FAILURE_DETAIL_MAX_LINES` (sin números mágicos sueltos); `stderr` se intenta una
sola vez por rama y solo se cae a `stdout` si aquél no tiene contenido utilizable; `signal.Signals`
desconocido se captura (`ValueError` → `señal N`); solo biblioteca estándar (`signal`, `subprocess`,
`re`); sin GPU ni red (las pruebas usan un `whisper` falso en el `PATH`).

## 4. Verificación E2E

- [x] 4.1 Provocar un fallo **real** del comando contra el pipeline de verdad (no un doble) y comprobar que
      el motivo trae detalle: p. ej. un `whisper` en el `PATH` que salga con código distinto de cero
- [x] 4.2 Provocar una **muerte por señal** real y comprobar que el motivo la nombra (es el caso del
      `17732`, ahora al menos identificable)
- [x] 4.3 Comprobar el caso «`exit 0` sin SRT» y que su mensaje no se confunde con el anterior
- [x] 4.4 Confirmar que el lote sigue igual en lo demás: un fallo no escribe meta y no aborta el lote
- [x] 4.5 Registrar las evidencias en este fichero

#### Evidencia — E2E

Ensayo acotado contra el pipeline **real** (solo lectura) y fallos forzados con un `whisper` falso en el
`PATH`, ejecutando el pipeline de verdad (`transcribe.py` por CLI, `ssh atareao`):

```
$ python3 transcribe.py --dry-run --limit 5
== ENSAYO (--dry-run): no se escribirá ni se descargará nada ==
pendientes : 4     ya hechos : 830     sin audio : 4        ← EXIT=0
  (los 4 pendientes = 12096, 17193, 17378, 17736: rechazos deterministas del validador)

# 4.1 — fallo real del comando (exit 3): el motivo trae la salida de stdout y el código
⚠️  episodio 12096: ... fallo al transcribir: exit code 3: fake-whisper: procesando --model turbo ...
    | fake-whisper: RuntimeError simulado en la decodificacion

# 4.4 — el lote NO se aborta: con dos episodios, ambos se intentan y se contabilizan
  == Resumen ==  resueltos: 0 · fallidos: 2 · IDs fallidos: 12096, 17193      ← EXIT=1

# 4.2 — muerte por señal de verdad (`kill -9 $$`)
⚠️  episodio 17378: ... fallo al transcribir: SIGKILL: fake-whisper: arrancando...

# 4.3 — exit 0 sin escribir el SRT (distinguible del fallo del comando)
⚠️  episodio 17736: ... fallo al transcribir: el comando terminó sin error pero no dejó el fichero SRT esperado: ...
```

Los cuatro casos confirman que el diagnóstico es utilizable, que un fallo **no** escribe meta ni aborta el
lote, y que el resumen y el código de salida (`1` si hay fallos) lo reflejan.

## 5. Entrega

- [x] 5.1 Rama `feature/podcast-transcribe-diagnostics` desde `development`; commits con gitmoji
      (`✅ test`, `🐛 fix`, `📝 docs`)
- [ ] 5.2 Push y PR a `development` (sin fusionar sin autorización explícita)
- [ ] 5.3 `openspec archive podcast-transcribe-diagnostics` **solo tras autorización** (el delta
      `MODIFIED` debe llevar el texto y los 6 escenarios del requisito destino, o el archivo falla)
- [x] 5.4 Nota en `proposal.md` y `tasks.md` con lo aprendido: por qué `stderr` estaba siempre vacío y cómo
      se reconoce ahora una muerte por señal

## 6. Deuda aceptada (no bloqueante, solo documentada)

- [ ] 6.1 La causa exacta del fallo del `17732` (probablemente una señal) **no se puede reconstruir**: se
      perdió con el mensaje. Lo que se arregla es que no vuelva a perderse
- [ ] 6.2 Los 4 SRT rechazados por el validador (`12096`, `17193`, `17378`, `17736`) son rechazos
      deterministas del SubRip producido, no fallos de diagnóstico: merecen su propio change
- [ ] 6.3 El lote sigue sin reintento automático: el pipeline es reanudable y ahora el operador puede
      decidir con datos
- [ ] 6.4 CI no ejecuta ninguna suite de pruebas (solo `php -l`): añadirlas es un change aparte
