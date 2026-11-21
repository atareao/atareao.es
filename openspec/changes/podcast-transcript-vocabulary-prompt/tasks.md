# Tasks: Prompt de vocabulario opcional

## RED
- [ ] Test: `parse_args` acepta `--initial-prompt` y por defecto es vacío.
- [ ] Test: `transcribe_audio` **sin** prompt no añade `--initial_prompt` a la orden.
- [ ] Test: `transcribe_audio` **con** prompt añade `--initial_prompt` con el texto dado.
- [ ] Ejecutar `python3 -m unittest discover -s tests/podcast-transcripts` y confirmar el fallo de los nuevos.

## GREEN
- [ ] `--initial-prompt` en `parse_args`.
- [ ] Propagar por `run_pipeline`.
- [ ] `initial_prompt` en `transcribe_audio` (añadir a la orden solo si no está vacío).
- [ ] Tests en verde; `python3 -m py_compile`.

## REFACTOR
- [ ] `ruff check` limpio.
- [ ] Re-ejecutar la suite Python y el arnés PHP (`just test-transcripts`).

## ARCHIVE
- [ ] `openspec archive podcast-transcript-vocabulary-prompt`.
