# Tasks: Post-proceso del SRT (puntuación y mayúsculas)

> Gate SDD: no empezar RED hasta la aprobación del usuario.

## RED
- [x] Test: `parse_args` acepta `--postprocess` (por defecto, desactivado) y `--dry-run`.
- [x] Test: invariancia de rejilla (mismos bloques y timecodes) original vs post-procesado.
- [x] Test: invariancia de palabras (normalizadas) + excepción declarada del glosario.
- [x] Test: restauración de mayúscula inicial y de fin de frase por hueco temporal.
- [x] Test: glosario — `gitea` → `Gitea`; alias `slim book` → `Slimbook`; sin alias, no inventa.
- [x] Test: idempotencia — segunda pasada = 0 cambios.
- [x] Test: `--dry-run` no escribe ni sube.
- [x] Test: backfill — mismo ID de adjunto, meta intacta, original recuperable y reversión.
- [x] Test: `validate_srt` sin errores tras post-proceso.
- [x] Ejecutar `python3 -m unittest discover -s tests/podcast-transcripts` y confirmar el fallo de los nuevos.

## GREEN
- [x] `--postprocess` y `--dry-run` en `parse_args`; propagación por `run_pipeline`.
- [x] Función de post-proceso con contrato estable (motor pluggable) + motor determinista por defecto.
- [x] Glosario versionado y su aplicación idempotente.
- [x] Integración del post-proceso tras el saneo del SRT.
- [x] Backfill (reemplazo del adjunto + copia del original) y reversión.
- [x] Tests en verde; `python3 -m py_compile`.

## REFACTOR
- [x] `ruff check` limpio.
- [x] Re-ejecutar `just test-transcripts` (suite Python + arnés PHP).

## BACKFILL Y DESPLIEGUE
- [x] Backfill de una muestra; verificación; resto.
- [x] Release → deploy (si el plugin cambia) → verificación en producción.

## ARCHIVE
- [x] `openspec archive podcast-transcript-postprocess`.
- [x] `docs/podcast-transcript-postprocess.md` y actualización de `HANDOFF.md`.
