# Design: Sanear la salida de Whisper antes de validar el SRT

## Contexto

`transcribe.py` (stdlib-only, sin dependencias) llama a `whisper --output_format srt` y luego
`_ensure_valid_srt()` valida el fichero. El ASR puede emitir bloques sin texto; eso es salida
legítima del reconocedor, no un fallo del comando.

## Decisiones

### D1 — Sanear en un paso propio, antes de validar

Se añade una función pura `_sanitize_srt(path)` que reescribe el fichero descartando los bloques cuya
línea de texto esté vacía o solo con espacios y **renumerando** los restantes consecutivos desde 1.
Se invoca **después** de `transcribe_audio()` y **antes** de `_ensure_valid_srt()`.

- **Alternativa descartada — fusionar cues vacíos con el anterior/siguiente:** alteraría marcas de
  tiempo y podría solapar rangos. Descartar es más simple, conserva los tiempos de los bloques válidos
  y produce numeración consecutiva válida.
- **Alternativa descartada — relajar el validador para admitir bloques vacíos:** produciría un SRT
  inválido para los reproductores/lectores y contradice el contrato SubRip.

### D2 — Detección de "sin voz" como caso propio

Tras el saneo, si el fichero queda **sin ningún bloque con texto**, no es un fallo del pipeline: el
audio no tiene voz transcribible. Se contabiliza como **omitido con motivo `sin voz`**.

- Se distingue de "fallo de transcripción" (comando con código ≠ 0, sin SRT, SRT ilegible…) para no
  disparar reintentos inútiles y para que el operador vea que el problema es el **asset de audio**,
  no el proceso.
- Se reporta en el resumen con la categoría `sin voz`.

### D3 — Idempotencia y reanudación intactas

El saneo no cambia el flujo de reanudación: un SRT ya existente en `srt_dir` sigue saltándose. El
saneo se aplica al SRT recién generado por el ASR.

## Fuera de alcance

- Reintentar con otro modelo o cambios de VAD.
- Arreglar el audio de origen en Spotify (resubida).
- Cambiar el contrato del sitio o el runtime de WordPress.

### D4 — "Sin voz" se decide midiendo el audio, no el SRT (corrección tras verificación)

La primera versión definía *sin voz* como "tras el saneo no queda ningún bloque con texto". La
verificación contra los SRT **reales** mostró que es insuficiente: Whisper, sobre silencio, **alucina
texto**, así que el SRT no queda vacío.

Evidencia (`#17378`): `37 bloques → 6 conservados`, todos con texto alucinado
(`"Gracias por ver el video."` ×5, `"Gracias."`) ⇒ el episodio se habría **enlazado con basura**.

Por eso la detección principal se hace **sobre el audio**, antes de transcribir:

- **Medición:** un único pase de `ffmpeg silencedetect` (umbral de ruido `-35 dB`, silencio mínimo
  `0,5 s`) sobre el fichero descargado. De su salida se toma la **duración total** y los intervalos de
  silencio; la **fracción audible** = (duración − silencio) / duración.
- **Criterio:** fracción audible **< 5 % ⇒ sin voz**. Constantes `SILENCE_NOISE_FLOOR`,
  `SILENCE_MIN_DURATION` y `NO_SPEECH_MAX_AUDIBLE_FRACTION`.
- **Beneficio:** además de clasificar bien, **evita gastar la transcripción** en audio mudo.

Medición real que fija el umbral:

| Episodio | mean_volume | fracción audible | veredicto |
|---|---|---|---|
| `#17378` | −43,0 dB | **1,69 %** | sin voz |
| `#12096` | −25,7 dB | **100 %** | normal |

El margen (1,69 % vs 100 %) hace el 5 % un umbral seguro. La comprobación "SRT sin bloques con texto
tras el saneo" se mantiene como **segunda condición** de `sin voz`.
