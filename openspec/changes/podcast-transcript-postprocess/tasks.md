# Tasks: Post-proceso del SRT (puntuación y mayúsculas)

> Gate SDD: no empezar RED hasta la aprobación del usuario.

## RED
- [ ] Test: `parse_args` acepta `--postprocess` (por defecto, desactivado) y `--dry-run`.
- [ ] Test: invariancia de rejilla (mismos bloques y timecodes) original vs post-procesado.
- [ ] Test: invariancia de palabras (normalizadas) + excepción declarada del glosario.
- [ ] Test: restauración de mayúscula inicial y de fin de frase por hueco temporal.
- [ ] Test: glosario — `gitea` → `Gitea`; alias `slim book` → `Slimbook`; sin alias, no inventa.
- [ ] Test: idempotencia — segunda pasada = 0 cambios.
- [ ] Test: `--dry-run` no escribe ni sube.
- [ ] Test: backfill — mismo ID de adjunto, meta intacta, original recuperable y reversión.
- [ ] Test: `validate_srt` sin errores tras post-proceso.
- [ ] Ejecutar `python3 -m unittest discover -s tests/podcast-transcripts` y confirmar el fallo de los nuevos.

## GREEN
- [ ] `--postprocess` y `--dry-run` en `parse_args`; propagación por `run_pipeline`.
- [ ] Función de post-proceso con contrato estable (motor pluggable) + motor determinista por defecto.
- [ ] Glosario versionado y su aplicación idempotente.
- [ ] Integración del post-proceso tras el saneo del SRT.
- [ ] Backfill (reemplazo del adjunto + copia del original) y reversión.
- [ ] Tests en verde; `python3 -m py_compile`.

## REFACTOR
- [ ] `ruff check` limpio.
- [ ] Re-ejecutar `just test-transcripts` (suite Python + arnés PHP).

## BACKFILL Y DESPLIEGUE
- [ ] Backfill de una muestra; verificación; resto.
- [ ] Release → deploy (si el plugin cambia) → verificación en producción.

## ARCHIVE
- [ ] `openspec archive podcast-transcript-postprocess`.
- [ ] `docs/podcast-transcript-postprocess.md` y actualización de `HANDOFF.md`.
