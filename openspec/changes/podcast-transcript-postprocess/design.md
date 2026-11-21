# Design: Post-proceso del SRT

## Contexto

El pipeline `scripts/podcast-transcripts/transcribe.py` es **solo stdlib** (importa solo módulos de la biblioteca estándar y llama a herramientas externas por `subprocess`). Produce el SRT y lo adjunta escribiendo `transcript-srt`. La restauración de puntuación/mayúsculas debe **no** cambiar la rejilla temporal del SubRip (los tiempos son la verdad de la sincronía con el audio y con el reproductor).

## Decisión de arquitectura: motor pluggable

Se aísla la restauración tras una función pura con contrato estable:

- **Entrada:** un texto (una cue o el conjunto de cues con sus tiempos).
- **Salida:** el texto restaurado.
- **Invariante exigido a cualquier motor:** mismas palabras (salvo alias declarados del glosario) y mismos timecodes. Si un motor viola el invariante en una cue, **esa cue conserva el texto original** (degradación segura, nunca se corrompe la transcripción).

Motores considerados:

- **A — determinista (solo stdlib, POR DEFECTO):** mayúscula inicial al comenzar una frase/cue; **fin de frase (`.`) cuando el hueco temporal entre cues supera un umbral**; coma cuando el hueco es corto; el signo de interrogación/exclamación se restringe a marcadores explícitos. Sin dependencias, sin riesgo de inventar contenido, rápido.
- **B — modelo probabilístico:** `deepmultilingualpunctuation` / spaCy. Mejor puntuación, pero añade torch (~GB) y rompe el estilo stdlib-only.
- **C — LLM local (`ollama`) por HTTP:** mejor calidad, requiere prompt estricto y **validación posterior** palabra a palabra; más lento (backfill de ~850 episodios).

Cualquiera de los tres se enchufa sin tocar el contrato del SRT.

## Glosario

Fichero de datos versionado (p. ej. `scripts/podcast-transcripts/glossary.tsv`) con dos clases de entrada:

- **Grafía canónica:** aplica la forma correcta sin distinguir mayúsculas/minúsculas (`gitea` → `Gitea`, `slimbook`/`slim book` → `Slimbook`, `atareao` → `atareao`).
- **Alias:** sustitución declarada explícitamente; es la **única** excepción permitida al invariante de palabras, y queda enumerada y auditable.

## Idempotencia y reversión

- Idempotencia: aplicar el post-proceso a un SRT ya post-procesado no lo modifica (segunda pasada = 0 cambios); permite reanudar sin rehacer.
- Reversión del backfill: antes de reemplazar el adjunto se conserva el SRT original (p. ej. `.orig.srt` o copia local con `sha256`), de modo que la operación es reversible episodio a episodio.

## Alternativas descartadas

- **Re-transcribir con otro modelo:** descartado por el usuario; el texto válido ya existe.
- **Reformatear/mergear cues:** descartado; cambiaría los bloques y rompería la sincronía.
