# Change: Preprocesado de audio (formato + banda) antes del reconocimiento

## Why

Hoy el pipeline pasa a `whisper` el **MP3 descargado tal cual**. Whisper lo acepta, pero
tiene que **remuestrear y mezclar canales internamente** (el MP3 del feed es 48 kHz y puede
venir en estéreo), lo que es trabajo redundante y no determinista respecto a su entrada
nativa. Además, el rango útil del modelo mel de Whisper termina en **8 kHz**, de modo que
enviar contenido por encima de esa banda solo añade ruido que puede inducir errores.

Medido en el lote real (A/B de 3 min de `#12096`, `turbo`, `es`): mismo contenido tanto crudo
como preprocesado; el preprocesado **no** perjudicó con solo formato + banda, y se confirmó
que los filtros de denoise/loudnorm (probados aparte) **sí** introducían artefactos, por lo
que **quedan fuera de alcance**.

## What Changes

1. **Copia normalizada antes del reconocimiento**: con `ffmpeg`, una copia del audio en
   **16 kHz**, **mono**, **`pcm_s16le`** y **limitada en banda** (`highpass=f=80`,
   `lowpass=f=8000`). El reconocedor recibe **esa copia**, no el MP3 descargado.
2. **Limpieza**: la copia temporal se elimina al terminar el episodio.
3. **El `sin voz` se sigue midiendo sobre el original** (antes de preprocesar), para que la
   normalización no altere el veredicto.
4. **Un fallo del preprocesado** deja el episodio como **fallido** (sin adjunto ni meta) y el
   lote continúa.

## Impact

- **Código:** `scripts/podcast-transcripts/transcribe.py` (nuevo paso de preprocesado,
  integración en el bucle, limpieza) y `tests/podcast-transcripts/test_transcribe.py`.
- **Spec:** `openspec/specs/podcast-transcripts/spec.md`:
  - ADDED *Preprocesado de audio para el reconocimiento*.
  - ADDED *La detección de audio sin voz usa el original*.
- **No cambia:** el contrato con el sitio (meta `transcript-srt` / adjunto `.srt`), el runtime
  de WordPress, el feed, el saneo del SRT ni la contabilidad del lote.
- **Fuera de alcance:** denoise (`afftdn`) y normalización de sonoridad (`loudnorm`); otros
  modelos; el audio de origen mudo de `#17378`.

## Evidencia A/B (resumen)

| Variante | Cambios | Veredicto |
|---|---|---|
| Solo formato + banda | `-ar 16000 -ac 1 -c:a pcm_s16le -af "highpass=f=80,lowpass=f=8000"` | ✅ adoptada |
| + `afftdn=nr=12:nf=-50` | denoise | ❌ perdía mayúsculas/nombres y añadía alucinaciones |
| + `loudnorm=i=-23` | sonoridad (1 pasada) | ❌ marginal para ASR y eleva el ruido de fondo |
