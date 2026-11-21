# Tasks: Prompt de vocabulario opcional

## RED
- [x] Test: `parse_args` acepta `--initial-prompt` y por defecto es vacío.
- [x] Test: `transcribe_audio` **sin** prompt no añade `--initial_prompt` a la orden.
- [x] Test: `transcribe_audio` **con** prompt añade `--initial_prompt` con el texto dado.
- [x] Ejecutar `python3 -m unittest discover -s tests/podcast-transcripts` y confirmar el fallo de los nuevos.

## GREEN
- [x] `--initial-prompt` en `parse_args`.
- [x] Propagar por `run_pipeline`.
- [x] `initial_prompt` en `transcribe_audio` (añadir a la orden solo si no está vacío).
- [x] Tests en verde; `python3 -m py_compile`.

## REFACTOR
- [x] `ruff check` limpio.
- [x] Re-ejecutar la suite Python y el arnés PHP (`just test-transcripts`).

## ARCHIVE
- [x] `openspec archive podcast-transcript-vocabulary-prompt`.
