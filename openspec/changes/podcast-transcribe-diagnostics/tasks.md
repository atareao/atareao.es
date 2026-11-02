# Tasks: Diagnóstico utilizable de un fallo de transcripción

> Estado: **borrador de plan**, pendiente de aprobación explícita del titular.
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

- [ ] 1.1 Prueba *con la salida de error vacía, el detalle sale de la salida estándar*: comando que falla
      escribiendo todo por `stdout` y nada por `stderr` → el motivo contiene esas líneas y **no** «sin
      detalle»
- [ ] 1.2 Prueba *el código de salida se informa siempre*: comando con `exit 3` → el motivo contiene `3`
- [ ] 1.3 Prueba *una muerte por señal se informa como señal*: matar el proceso hijo con `SIGKILL` (o
      doblar un `returncode` negativo) → el motivo nombra la señal y no un código positivo
- [ ] 1.4 Prueba *el comando termina bien pero no deja el SRT*: `exit 0` sin fichero → el motivo lo dice y
      es distinguible del fallo del comando
- [ ] 1.5 Prueba *el motivo conserva varias líneas*: traza de varias líneas → se conservan las últimas, no
      solo la última
- [ ] 1.6 Prueba *un fallo sin salida no inventa un detalle*: sin salida y sin señal → se informa del
      código observado
- [ ] 1.7 Ejecutar por CLI (`python3 -m unittest discover -s tests/podcast-transcripts`) y **confirmar que
      las nuevas pruebas fallan** con el código de hoy; pegar la salida literal
- [ ] 1.8 Confirmar que **no hay regresión**: el resto de la suite sigue verde (`just test-transcripts` y
      `just test` 1068/0) — pegar la salida literal
- [ ] 1.9 Registrar la evidencia del RED en este fichero

#### Evidencia — RED

*(pendiente)*

## 2. GREEN — implementación mínima

- [ ] 2.1 `transcribe.py`: un *helper* **puro** que componga el motivo a partir del resultado del comando
      (código de salida, `stderr`, `stdout`) y de la existencia del `.srt`; sin efectos secundarios y
      verificable sin GPU
- [ ] 2.2 `transcribe_audio` usa ese *helper* en lugar de `detail[-1] if detail else "sin detalle"`,
      conservando el tipo `PipelineError` y el prefijo del episodio (el resto del contrato de errores no
      cambia)
- [ ] 2.3 Pasar la suite completa (`just test-transcripts` → `exit 0`) y pegar la salida literal

#### Evidencia — GREEN

*(pendiente)*

## 3. REFACTOR

- [ ] 3.1 Re-ejecutar `just test-transcripts` y `just test` (sin regresiones)
- [ ] 3.2 Repasar el *helper*: sin números mágicos sin nombre (cuántas líneas se conservan), sin
      `stdout`/`stderr` mezclados dos veces, sin dependencia de la GPU ni de la red
- [ ] 3.3 Confirmar que solo se usa la biblioteca estándar (el repo no tiene gestor de paquetes)

#### Evidencia — REFACTOR

*(pendiente)*

## 4. Verificación E2E

- [ ] 4.1 Provocar un fallo **real** del comando contra el pipeline de verdad (no un doble) y comprobar que
      el motivo trae detalle: p. ej. un `whisper` en el `PATH` que salga con código distinto de cero
- [ ] 4.2 Provocar una **muerte por señal** real y comprobar que el motivo la nombra (es el caso del
      `17732`, ahora al menos identificable)
- [ ] 4.3 Comprobar el caso «`exit 0` sin SRT» y que su mensaje no se confunde con el anterior
- [ ] 4.4 Confirmar que el lote sigue igual en lo demás: un fallo no escribe meta y no aborta el lote
- [ ] 4.5 Registrar las evidencias en este fichero

#### Evidencia — E2E

*(pendiente)*

## 5. Entrega

- [ ] 5.1 Rama `feature/podcast-transcribe-diagnostics` desde `development`; commits con gitmoji
      (`✅ test`, `🐛 fix`, `📝 docs`)
- [ ] 5.2 Push y PR a `development` (sin fusionar sin autorización explícita)
- [ ] 5.3 `openspec archive podcast-transcribe-diagnostics` **solo tras autorización** (el delta
      `MODIFIED` debe llevar el texto y los 6 escenarios del requisito destino, o el archivo falla)
- [ ] 5.4 Nota en `proposal.md` y `tasks.md` con lo aprendido: por qué `stderr` estaba siempre vacío y cómo
      se reconoce ahora una muerte por señal

## 6. Deuda aceptada (no bloqueante, solo documentada)

- [ ] 6.1 La causa exacta del fallo del `17732` (probablemente una señal) **no se puede reconstruir**: se
      perdió con el mensaje. Lo que se arregla es que no vuelva a perderse
- [ ] 6.2 Los 4 SRT rechazados por el validador (`12096`, `17193`, `17378`, `17736`) son rechazos
      deterministas del SubRip producido, no fallos de diagnóstico: merecen su propio change
- [ ] 6.3 El lote sigue sin reintento automático: el pipeline es reanudable y ahora el operador puede
      decidir con datos
- [ ] 6.4 CI no ejecuta ninguna suite de pruebas (solo `php -l`): añadirlas es un change aparte
