# Design: Preprocesado de audio (formato + banda)

## Context

El pipeline `scripts/podcast-transcripts/transcribe.py` descarga el MP3 del feed y lo pasa
directo a `whisper`. Tras el change `transcript-srt-sanitize`, la secuencia por episodio es:
descargar → **medir fracción audible (gate `sin voz`)** → `whisper` → `_sanitize_srt` →
`_ensure_valid_srt` → subida por lote.

La entrada nativa de Whisper es **16 kHz mono PCM float/16-bit**; el MP3 del feed es 48 kHz
(y ocasionalmente estéreo). Aceptar ese MP3 obliga a Whisper a remuestrear/mezclar, y le
entrega contenido por encima de **8 kHz** (techo del mel de Whisper) que no aporta.

## Decisions

### D1 — Preprocesar siempre, con formato + banda (sin denoise, sin loudnorm)

Dos pases de filtros fueron evaluados y **descartados**: `afftdn` (denoise) y `loudnorm`.
Evidencia: en el A/B de 3 min de `#12096` (`turbo`, `es`), la variante con `afftdn` **perdió
mayúsculas y nombres propios** (`geneulinus`, `lorenzo`) y **añadió alucinaciones** al final
(`"el otro Instagram"`). El podcast ya es limpio; el denoise solo inventa artefactos. La
variante adoptada, **solo formato + banda**, conserva el contenido.

### D2 — El canal de comando

```
ffmpeg -hide_banner -nostats -y -i <mp3> \
  -ar 16000 -ac 1 -c:a pcm_s16le \
  -af "highpass=f=80,lowpass=f=8000" \
  <wav>
```

Constantes: `PREPROCESS_SAMPLE_RATE = 16000`, `PREPROCESS_CHANNELS = 1`,
`PREPROCESS_CODEC = "pcm_s16le"`, `PREPROCESS_HIGHPASS_HZ = 80`,
`PREPROCESS_LOWPASS_HZ = 8000`.

### D3 — Nombres y ubicación

La copia se escribe en el directorio de audio de trabajo con **el mismo nombre base que el
MP3** (`<episode_id>.wav`) para que `whisper` siga produciendo `<episode_id>.srt` en el
directorio de SRT; así **no cambia** la lógica de rutas ni la reanudación.

### D4 — El gate `sin voz` se mide sobre el original

La fracción audible se calcula **antes** de preprocesar, sobre el MP3 descargado. Motivo:
`loudnorm`/`afftdn` pueden **elevar el nivel del ruido de fondo** de un audio casi mudo y
romper el umbral. Medir el original mantiene el veredicto estable (comprobado: el silencio
digital de `#17378` queda en −91 dB antes y después).

### D5 — Limpieza y aislamiento de fallos

La copia `<episode_id>.wav` se elimina en el `finally` del episodio (también con
`--keep-audio`, que solo conserva el original). Si `ffmpeg` falla, el episodio se contabiliza
como **fallido** (sin adjunto ni meta) y el lote continúa, igual que cualquier otro
`PipelineError`.

### D6 — Coste en disco (nota operativa)

El WAV 16 kHz mono `pcm_s16le` ocupa ≈ 32 KB/s (≈ 40 MB para 21 min), frente a los pocos MB
del MP3. Se crea uno por episodio y se borra al terminar el episodio, de modo que el pico es
**una** copia. La máquina de producción está muy llena de disco: no se acumulan copias.

## Alternatives considered

- **Denoise (`afftdn`)**: ayuda en grabaciones ruidosas, perjudica en limpias → descartado.
- **Loudnorm (`loudnorm`)**: pequeña ganancia para ASR e interacción con el gate → descartado.
- **No preprocesar (status quo)**: deja el remuestreo interno y el ruido fuera de banda → peor.

## Out of scope

- Arreglar el audio mudo de origen de `#17378` (resubida en Spotify).
- Otros modelos o VAD.
