# Proposal: Post-proceso del SRT — puntuación y mayúsculas

## Why

El SRT que produce el reconocedor es texto crudo: **todo en minúsculas, sin puntuación**, con párrafos partidos por cue y errores de grafía de términos («slim book» por `Slimbook`, `gitea` por `Gitea`). El texto está, pero se lee mal. Se quiere **post-procesar** el SRT (sin volver a transcribir con otro modelo) restaurando **puntuación y mayúsculas** y aplicando un **glosario** de la terminología del programa, manteniendo la sincronía del SubRip.

## What Changes

1. Nuevo paso de **post-proceso del texto** en el pipeline batch (`scripts/podcast-transcripts/transcribe.py`), **opt-in** con `--postprocess`; sin la opción, el comportamiento es **idéntico** al actual.
2. **Invariantes de contrato:** el SRT resultante conserva **los mismos bloques y timecodes** y SubRip válido; la **secuencia de palabras** es idéntica al original salvo los alias **declarados** en el glosario.
3. **Glosario** de términos (grafía canónica + alias) aplicado de forma determinista e idempotente.
4. **Idempotencia y reanudación**: una segunda pasada no cambia nada; una ejecución interrumpida se reanuda.
5. **`--dry-run`**: informa de lo que cambiaría sin escribir ni subir nada.
6. **Backfill** de las transcripciones **ya adjuntas** (~850), reemplazando el fichero del adjunto con **copia recuperable del original** para poder **revertir**; el ID de adjunto y la meta `transcript-srt` no cambian de valor.
7. **Motor pluggable**: la lógica de restauración vive tras una frontera estable y puede sustituirse sin tocar el contrato del SRT ni el pipeline.

## Capabilities

### New Capabilities

_(ninguna)_

### Modified Capabilities

- `podcast-transcripts`: se **añaden** requirements sobre el post-proceso (puntuación y mayúsculas), los invariantes de palabras y rejilla temporal, el glosario, la idempotencia/`--dry-run`, el backfill reversible y la validez SubRip del resultado.

## Impact

- **Código:** `scripts/podcast-transcripts/transcribe.py` (nuevo paso de post-proceso y opciones de línea de órdenes) y `tests/podcast-transcripts/test_transcribe.py`.
- **Spec:** `openspec/specs/podcast-transcripts/spec.md` — requirements ADDED (arriba).
- **No cambia:** comportamiento por defecto del pipeline, contrato de adjunto/meta (`transcript-srt`), emisión del feed, ni el runtime del sitio.

## Open Questions (a resolver en la aprobación)

1. **Motor de restauración**: (A, recomendada) determinista solo-stdlib — mayúscula inicial por bloque/frase + puntuación guiada por los **huecos temporales** del SubRip + glosario; (B) modelo probabilístico (añade torch); (C) LLM local vía `ollama` (mejor calidad, requiere validación anti-invención). Propuesta: motor **pluggable** con A por defecto y el invariante de palabras exigido a cualquier motor.
2. **¿`--postprocess` opt-in (propuesto) o activo por defecto?**
3. **Forma de la copia del original** para la reversión (p. ej. `.orig.srt` junto al adjunto, o copia local con `sha256`).
