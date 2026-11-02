# Tasks: Sanear la salida de Whisper y detectar el audio sin voz

> Protocolo SDD+TDD. Ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Alcance: únicamente `scripts/podcast-transcripts/transcribe.py` y sus tests.
> Temporales en `/tmp/opencode`.

## 0. Contexto medido

- [x] 0.1 Confirmar: `#12096` rechazado por 2 cues vacíos (SRT 154 bloques);
      `#17378` con fracción audible 1,69 % y alucinaciones de Whisper.
- [x] 0.2 Sujeto: `scripts/podcast-transcripts/transcribe.py`
      (`transcribe_audio`, `_sanitize_srt`, medición de audio y el bucle de `run_pipeline`).

## 1. RED — tests que fallan

- [x] 1.1 `_sanitize_srt()` descarta bloques de texto vacío y renumera consecutivo desde 1.
- [x] 1.2 `_sanitize_srt()` conserva idénticos texto y marcas de tiempo de los bloques con texto.
- [x] 1.3 Un SRT con ≥1 bloque con texto, tras sanear, **se enlaza** (no falla por cues vacíos).
- [x] 1.4 La **fracción audible** se calcula bien a partir de (duración, intervalos de silencio).
- [x] 1.5 Un audio con fracción audible **< 5 %** se clasifica **sin voz** *antes* de transcribir
      (no se invoca el comando de transcripción).
- [x] 1.6 Un audio con voz **no** se clasifica como sin voz.
- [x] 1.7 Un SRT cuyos bloques son **todos** vacíos ⇒ **sin voz** (segunda condición), no fallo.
- [x] 1.8 El resumen informa de los episodios sin voz como omitidos con motivo `sin voz`, no como fallidos.
- [x] 1.9 Ejecutar `python3 -m unittest discover -s tests/podcast-transcripts -v`: los tests nuevos
      fallan (RED) y los heredados siguen en verde.

## 2. GREEN — implementación mínima

- [x] 2.1 `_sanitize_srt()` invocada entre `transcribe_audio()` y `_ensure_valid_srt()`.
- [x] 2.2 Medición de audio (`ffmpeg silencedetect`, umbral y mínimo de silencio fijos) → fracción audible;
      constante `NO_SPEECH_MAX_AUDIBLE_FRACTION`.
- [x] 2.3 Si fracción audible < 5 % ⇒ **sin voz** antes de transcribir (no se lanza whisper).
- [x] 2.4 Categoría `sin voz` en la contabilidad y en el resumen (omitidos con motivo, no fallidos).
- [x] 2.5 Re-ejecutar los tests: 100 % en verde; sin errores de importación ni de sintaxis.

## 3. REFACTOR

- [x] 3.1 Limpiar y tipar sin alterar comportamiento; re-ejecutar en verde.

## 4. Verificación end-to-end

- [x] 4.1 Re-ejecutar con `--episode 12096` → **se resuelve** (adjunto + meta en producción,
      `<podcast:transcript>` en el feed).
- [x] 4.2 Re-ejecutar con `--episode 17378` → **omitido `sin voz`**, sin adjunto ni meta, sin lanzar
      transcripción, sin fallar el lote.
- [x] 4.3 Verificar en producción: `transcript-srt` de #12096 apunta a un `.srt` legible; #17378 sigue sin meta.

## 5. Cierre

- [x] 5.1 `openspec validate transcript-srt-sanitize --strict`.
- [x] 5.2 `openspec archive transcript-srt-sanitize`.
- [x] 5.3 Git flow: `feature/transcript-srt-sanitize` → PR a `development`.
