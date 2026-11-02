# Change: Sanear la salida de Whisper y detectar el audio sin voz

## Why

El pipeline `scripts/podcast-transcripts/transcribe.py` invoca `whisper --output_format srt` y valida
el fichero **tal cual**. Dos problemas reales, medidos en el lote del 2026-11-02:

1. **Cues vacíos descartan el episodio entero.** Whisper emite, de forma no determinista, bloques cuya
   línea de texto está **vacía** (música, pausas, silencio). El validador exige texto en cada bloque, así
   que un único cue vacío tira la transcripción completa.
   - `#12096` ("Sobre las presentaciones"): SRT de **154 bloques con 2 cues vacíos** ⇒ rechazado entero.

2. **"Audio sin voz" se confunde con un fallo.** `#17378` ("Una terminal en tu navegador"): el audio del
   origen está **mudo** (fracción audible **1,69 %**; 10,2 s–1095,2 s de silencio en 1105 s; md5 estable
   en dos descargas). Whisper, sobre el silencio, **alucina texto** (`"Gracias por ver el video."` ×5) y
   luego emite vacíos: el episodio se contabiliza como **fallo genérico**, sin distinguir "no hay voz".
   *Corrección importante:* una detección basada solo en "el SRT quedó sin texto" **no** basta, porque las
   alucinaciones dejan texto no vacío (se probó: `37 bloques → 6 conservados`, todos alucinados).

## What Changes

1. **Sanear antes de validar**: descartar los *cues* con texto vacío o solo espacios y renumerar los
   restantes consecutivos desde 1, antes de la validación SubRip. El saneo conserva **sin cambios** el
   texto y las marcas de tiempo de los bloques con texto.
2. **Detectar "audio sin voz" midiendo el audio**: un pase de `ffmpeg silencedetect` calcula la
   **fracción audible**; si es **< 5 %**, el episodio se trata como **sin voz**, se contabiliza como
   **omitido con motivo `sin voz`**, y **no se transcribe** ni se escribe adjunto/meta. La condición
   "SRT sin texto tras el saneo" se mantiene como **segunda vía** del mismo resultado.
3. **Reportarlo en el resumen** (categoría `sin voz`, separada de los fallos).

## Impact

- **Código:** `scripts/podcast-transcripts/transcribe.py` (saneo + medición de audio + contabilidad) y
  `tests/podcast-transcripts/test_transcribe.py`.
- **Spec:** `openspec/specs/podcast-transcripts/spec.md`:
  - MODIFIED *Validez del SubRip producido* (saneo de cues vacíos).
  - MODIFIED *Aislamiento de fallos y contabilidad del lote* (sin voz como omitido, no fallido).
  - ADDED *Audio sin voz detectable* (detección por medición del audio).
- **No cambia:** el contrato con el sitio (meta `transcript-srt` / adjunto `.srt`), el runtime de
  WordPress, ni el feed.
- **Efecto esperado:** al re-ejecutar el lote, **#12096 se resuelve** (152 bloques válidos) y **#17378
  queda marcado como `sin voz`** sin gastar transcripción. El arreglo de raíz de #17378 (resubir el
  audio en Spotify) queda **fuera de alcance**.
