# Proposal: Diagnóstico utilizable de un fallo de transcripción

## Why

**Medido al cerrar el lote de 838 episodios (2026-11-01/02).** El episodio `17732` se contabilizó como
fallido con este mensaje:

```
⚠️  episodio 17732: episodio 17732: fallo al transcribir: sin detalle
```

Ese mensaje no describe el fallo y, peor, **destruye la evidencia**. En
`scripts/podcast-transcripts/transcribe.py`:

```python
if proc.returncode != 0 or not srt_path.is_file():
    detail = _text(proc.stderr).strip().splitlines()
    tail = detail[-1] if detail else "sin detalle"
```

Solo se mira `stderr`. Pero `openai-whisper 20250625` escribe **todo por la salida estándar y cero por la
de error**: medido en la misma máquina, `stdout = 23 025 B` y `stderr = 0 B` en una transcripción normal. Y
el `returncode` —lo único que diría si el proceso murió por una señal— **no se reporta nunca**. Resultado:
cualquier fallo de Whisper que no sea una excepción de Python produce siempre «sin detalle».

El coste no es solo este episodio: **cualquier fallo futuro del lote será igual de opaco** y el operador
tendrá que repetir la investigación entera para distinguir «el audio está mal» de «el proceso murió».

## Contexto medido: el episodio no era culpable

La prueba se hizo a posteriori y a mano. El mismo audio, con la misma URL, el mismo `User-Agent` y el
comando literal del pipeline:

```
whisper --model turbo --language es --output_format srt --output_dir … 17732.mp3
  ejecución 1 → EXIT=0   stdout=23 025 B   stderr=0 B   SRT=24 389 B
  ejecución 2 → EXIT=0   stdout=23 025 B   stderr=0 B   SRT=24 389 B   (idéntico)
  validate_srt → 184 bloques, 0 vacíos, timestamps crecientes y sin solape
```

No era el audio: fue una muerte abrupta del proceso cuyo rastro se perdió, precisamente por el defecto que
este change arregla.

## What Changes

- **El mensaje de fallo se construye con la salida real del comando**: `stderr` y, cuando está vacío,
  `stdout`, que es donde este Whisper lo escribe todo. Se acaba el «sin detalle» sin motivo.
- **El mensaje incluye el código de salida** del comando y, si el proceso terminó por una **señal**, la
  señal (el `returncode` negativo que hoy se descarta).
- **El detalle deja de ser una línea suelta**: se conservan las últimas líneas, para que una traza de
  Python se pueda leer entera.
- **Se distinguen dos casos hoy fundidos**: «el comando falló» y «el comando terminó sin error pero no dejó
  el `.srt`».

Queda **fuera de alcance**: reintentos automáticos, la subida a WordPress, el criterio de validez del
SubRip y las clases de episodios omitidos. Este change **solo** arregla el diagnóstico. El contrato de
éxito (un SRT válido se enlaza igual que hoy) no cambia.

## Capabilities

### Modified Capabilities

- `podcast-transcripts`: el requisito «Aislamiento de fallos y contabilidad del lote» gana la obligación de
  informar de un fallo con datos utilizables, y se añade el requisito «El diagnóstico de un fallo de
  transcripción es utilizable», con los casos medidos (salida por stdout, código de salida, señal y
  comando sin error pero sin SRT) fijados como escenarios.

## Impact

- **Ficheros a modificar:**
  - `scripts/podcast-transcripts/transcribe.py` — el mensaje de error de `transcribe_audio` y un *helper*
    puro que compone el detalle a partir del resultado del comando y de la existencia del `.srt`.
  - `tests/podcast-transcripts/test_transcribe.py` — pruebas nuevas del camino de error. Se ejecutan con
    un ejecutable `whisper` falso en un directorio temporal (offline, determinista, sin GPU), que es como
    se reproduce una señal sin matar nada.
- **Sin cambios** en la receta `just test-transcripts`: el `python3 -m unittest discover -s
  tests/podcast-transcripts` ya recoge el fichero.
- **Sin cambios en producción**: no se toca WordPress, ni el feed, ni la subida, ni el contrato de las
  metas.

## Decisiones tomadas y alternativas descartadas

- **Arreglar el diagnóstico, no el síntoma.** Se descarta «añadir un reintento»: sin un mensaje utilizable,
  un reintento solo repetiría a ciegas. Repetir es una decisión del operador (el pipeline ya es
  reanudable), y ahora podrá tomarla con datos.
- **`stderr` primero, `stdout` como respaldo** (y no al revés): si algún día Whisper vuelve a usar `stderr`,
  el detalle sigue saliendo de ahí; el respaldo solo entra cuando el primero está vacío.
- **No se registra la salida completa en el log**: se conservan las últimas líneas, que es lo que se lee.
  Volcar 23 KB por episodio haría el journal inservible en un lote de 838.
- **Las pruebas usan un `whisper` falso en el `PATH`**, no un doble de `subprocess`: así se ejerce el código
  real de `run_command` y se puede provocar de verdad una muerte por señal (`kill -9` sobre un proceso hijo
  controlado) sin tocar la GPU ni la red.

## Limitaciones y deuda aceptada (no bloqueantes)

- **El fallo original del `17732` no se puede reconstruir**: su causa exacta (señal, muy probablemente) se
  perdió con el mensaje. Lo que se arregla es que no vuelva a perderse, no la causa.
- **Los 4 SRT rechazados por el validador** (`12096`, `17193`, `17378`, `17736`) siguen fuera: son rechazos
  **deterministas** del SubRip producido, no fallos de diagnóstico, y merecerían su propio change.
- **Sigue sin haber reintento automático** y **CI no ejecuta la suite**: ambas son mejoras independientes.
