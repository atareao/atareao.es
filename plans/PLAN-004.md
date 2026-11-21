# PLAN-004 · #247 — Post-proceso del SRT (puntuación y mayúsculas)

- **Issue de entrada:** atareao/atareao.es#247 (open, label enhancement, assignee atareao).
- **Repo:** /data/php/atareao.es · rama base `development` · rama de trabajo `feature/podcast-transcript-postprocess`.
- **Change OpenSpec:** `podcast-transcript-postprocess` (capability `podcast-transcripts`).
- **Estado:** propuesta SDD creada — PENDIENTE DE APROBACIÓN DEL USUARIO (gate SDD). No se escribe código ni tests hasta la aprobación.

## Objetivo

Restaurar **puntuación y mayúsculas** del SRT crudo de Whisper **sin re-transcribir**, manteniendo la sincronía del SubRip. Aplicable a futuros episodios **y** con **backfill reversible** de los ~850 existentes.

## Alcance

- **En:** `scripts/podcast-transcripts/transcribe.py`, arnés `tests/podcast-transcripts/test_transcribe.py`, glosario de términos, backfill de los adjuntos existentes.
- **Fuera:** re-transcribir con otro modelo; runtime del sitio; feed; contrato de adjunto/meta.

## Contratos (resumen)

- Nuevas opciones: `--postprocess` (opt-in; por defecto, comportamiento actual) y `--dry-run`; glosario de términos.
- **Invariantes:** mismos bloques y timecodes; secuencia de palabras idéntica salvo los alias **declarados** del glosario; SubRip válido según `validate_srt`.
- Idempotente y reanudable; backfill con **copia del original** para poder revertir.
- El **motor** de restauración vive tras una frontera estable (sustituible sin tocar el contrato del SRT ni el pipeline).

## Decisiones abiertas (a resolver en la aprobación)

1. **Motor de puntuación/mayúsculas:**
   - **A (recomendada):** determinista, solo stdlib — capitalización inicial por bloque/frase + puntuación guiada por los **huecos temporales** del SubRip + glosario. Sin dependencias; riesgo nulo de inventar contenido.
   - **B:** modelo probabilístico (`deepmultilingualpunctuation` / spaCy) → añade torch (~GB) y rompe el estilo stdlib-only del script.
   - **C:** LLM local vía `ollama` (ya presente en la máquina) por HTTP; mejor calidad, pero exige validación estricta anti-invención y es más lento.
   - **Decisión propuesta:** motor **pluggable** con A por defecto, exigiendo el invariante de preservación de palabras a cualquier motor.
2. **¿Post-proceso activo por defecto en el pipeline** o solo con `--postprocess`? (propuesto: opt-in).
3. **Forma de la copia del original** para la reversión (p. ej. `.orig.srt` junto al adjunto, o copia local con `sha256`).

## Tareas (TDD)

1. **RED:** tests de argumentos, invariantes (bloques/timecodes/palabras), glosario, idempotencia, `--dry-run`, backfill y validez SubRip → deben fallar.
2. **GREEN:** implementación mínima; suite Python en verde.
3. **REFACTOR:** `ruff check` limpio; `just test-transcripts`.
4. **Backfill controlado:** muestra → verificación → resto; feed intacto.
5. Release → deploy (si el plugin cambia) → verificación en producción → cierre de #247.
6. Archivar el change; `docs/podcast-transcript-postprocess.md`; actualizar `HANDOFF.md`.

## Verificación

- `python3 -m unittest discover -s tests/podcast-transcripts` en verde; `ruff check` limpio.
- Invariantes comprobados por test (bloques, timecodes y palabras).
- Producción: SRT de un episodio con puntuación/mayúsculas; feed con 850 `<podcast:transcript>`; reversión probada.

## Evidencia

(pendiente)
