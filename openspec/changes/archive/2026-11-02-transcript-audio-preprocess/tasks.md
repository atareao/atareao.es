# Tasks: Preprocesado de audio (formato + banda)

> Protocolo SDD+TDD. Ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Alcance: únicamente `scripts/podcast-transcripts/transcribe.py` y sus tests.
> Temporales en `/tmp/opencode`.

## 0. Contexto medido

- [x] 0.1 Confirmar el A/B (3 min de `#12096`, `turbo`, `es`): formato+banda conserva el
      contenido; `afftdn`/`loudnorm` añaden artefactos y quedan fuera.
- [x] 0.2 Sujeto: `scripts/podcast-transcripts/transcribe.py` (`download_audio`,
      `transcribe_audio`, `measure_audible_fraction` y el bucle de `run_pipeline`).

## 1. RED — tests que fallan

- [x] 1.1 El comando de preprocesado se construye con `-ar 16000`, `-ac 1`,
      `-c:a pcm_s16le` y `-af "highpass=f=80,lowpass=f=8000"`.
- [x] 1.2 El reconocedor recibe la **copia preprocesada**, no el MP3 descargado.
- [x] 1.3 La copia temporal se **elimina** al terminar el episodio (resuelto) y también al
      terminar un fallo.
- [x] 1.4 El fichero descargado **no** se altera.
- [x] 1.5 La medición de `sin voz` se hace sobre el **original** (mismo veredicto que antes
      de preprocesar).
- [x] 1.6 Un fallo del preprocesado ⇒ episodio **fallido** (sin adjunto ni meta), el lote
      continúa.
- [x] 1.7 Ejecutar `python3 -m unittest discover -s tests/podcast-transcripts -v`: los tests
      nuevos fallan (RED) y los heredados siguen en verde.

## 2. GREEN — implementación mínima

- [x] 2.1 Constantes `PREPROCESS_*` y una función `preprocess_audio(audio_path, out_dir)`
      (o equivalente) que produce la copia normalizada con `ffmpeg`.
- [x] 2.2 Integrar el preprocesado en `run_pipeline`: tras el gate `sin voz` y **antes** de
      `transcribe_audio`, pasando la copia al reconocedor.
- [x] 2.3 Limpieza de la copia en el `finally` del episodio.
- [x] 2.4 Re-ejecutar los tests: 100 % en verde; sin errores de importación ni de sintaxis.

## 3. REFACTOR

- [x] 3.1 Limpiar y tipar sin alterar comportamiento; re-ejecutar en verde.

## 4. Verificación end-to-end

- [x] 4.1 Ejecutar el pipeline sobre un episodio con voz (`--episode`) y comprobar que el
      reconocedor usa la copia normalizada (traza de comandos) y que el SRT resultante es
      válido.
- [x] 4.2 No queda ninguna copia `.wav` en el directorio de audio al terminar el lote.
- [x] 4.3 Verificación en producción: el episodio sigue resolviendo con adjunto `.srt`
      legible y `<podcast:transcript>` en el feed.

## 5. Cierre

- [x] 5.1 `openspec validate transcript-audio-preprocess --strict`.
- [x] 5.2 `openspec archive transcript-audio-preprocess`.
- [x] 5.3 Git flow: `feature/transcript-audio-preprocess` → PR a `development`.
