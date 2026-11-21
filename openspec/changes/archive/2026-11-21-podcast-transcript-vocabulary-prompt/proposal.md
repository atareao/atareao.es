# Proposal: Prompt de vocabulario opcional en el reconocimiento

## Why

El reconocedor de voz manglea de forma recurrente la **terminología del programa** (nombres de
herramientas y marcas: `Termux`, `Neovim`, `MobaXterm`, `Nexterm`, `WebDAV`, `Dockge`, `Yacht`…),
produciendo transcripciones que hay que corregir a mano. Un **prompt de vocabulario** es el
mecanismo que expone Whisper (`--initial_prompt`) para sesgar el reconocimiento hacia ese léxico.
Hoy el pipeline no permite pasar ningún prompt.

## What Changes

1. **Nueva opción opt-in `--initial-prompt TEXT`**: cuando se facilita, se pasa al reconocedor como
   `--initial_prompt`.
2. **Por defecto, sin prompt**: sin la opción, la invocación al reconocedor es **idéntica** a la
   actual (sin `--initial_prompt`).
3. La opción se propaga por el pipeline (`run_pipeline` → `transcribe_audio`).
4. El prompt **no** altera el audio, el preprocesado, la detección de audio sin voz ni el contrato
   del SRT/adjunto (solo sesga el reconocimiento).

> Nota de alcance: en el episodio que motivó esto, el prompt **solo** no eliminó todos los garbles;
> su valor es como herramienta **opcional** que se suma al saneo determinista, no como sustituto.

## Capabilities

### New Capabilities

_(ninguna)_

### Modified Capabilities

- `podcast-transcripts`: se **añade** una requirement sobre el prompt de vocabulario opt-in
  (`--initial-prompt`) y su invocación al reconocedor.

## Impact

- **Código:** `scripts/podcast-transcripts/transcribe.py` (`parse_args`, `run_pipeline`,
  `transcribe_audio`) y `tests/podcast-transcripts/test_transcribe.py`.
- **Spec:** `openspec/specs/podcast-transcripts/spec.md` — ADDED *Prompt de vocabulario opt-in para
  el reconocimiento*.
- **No cambia:** comportamiento por defecto del pipeline, contrato del sitio ni feed.
