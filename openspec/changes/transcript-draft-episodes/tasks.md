# Tasks: Transcribir episodios en borrador (opt-in)

> Protocolo SDD+TDD. Ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Alcance de código: `scripts/podcast-transcripts/{transcribe.py,wp-collect.php,wp-attach.php}`,
> `tests/podcast-transcripts/test_transcribe.py` y `tests/podcast-transcripts/test_attach.php`.
> Temporales en `/tmp/opencode`.

## 0. Contexto medido

- [x] 0.1 Confirmar los 5 borradores (17343, 17267, 17269, 17598, 17599) con `mp3-url` válida y
      audio descargable (200 `audio/mpeg`). — Evidencia: `HEAD` de cada URL → `HTTP/2 200`,
      `content-type: audio/mpeg`, `content-length` 14–22 MB.
- [x] 0.2 Confirmar que hoy el pipeline no los procesa: `wp-collect.php` filtra `publish` y
      `wp-attach.php` rechaza destinos no publicados. — Evidencia: lectura de ambos helpers.

## 1. RED — tests que fallan

- [x] 1.1 `select_episodes()` / planificación incluyen borradores solo cuando se pide incluir.
- [x] 1.2 Sin la opción, el alcance sigue siendo solo publicados (no se consideran borradores).
- [x] 1.3 La recolección pide el estado correcto a `wp-collect.php` según la opción.
- [x] 1.4 El conjunto de estados permitidos se pasa a `wp-attach.php` según la opción.
- [x] 1.5 La subida en modo borradores acepta un destino `draft` de tipo `podcast` (arnés PHP).
- [x] 1.6 Un borrador ya transcrito se omite (idempotencia) sin crear adjunto ni descargar audio.
- [x] 1.7 Ejecutar los tests: los nuevos fallan (RED) y los heredados siguen en verde. — Evidencia:
      `Ran 79 tests ... FAILED (failures=2, errors=2)` en la defensa nueva; PHP `Total passed: 44,
      Total failed: 2` (whitelist).

## 2. GREEN — implementación mínima

- [x] 2.1 Argumento `--include-drafts` en `parse_args`.
- [x] 2.2 `wp-collect.php` acepta el argumento de estado (por defecto `publish`).
- [x] 2.3 `wp-attach.php` acepta el conjunto de estados permitidos (por defecto `publish`).
- [x] 2.4 `transcribe.py` propaga la opción a recolección y subida.
- [x] 2.5 Re-ejecutar los tests: 100 % en verde. — Evidencia: `Ran 79 tests ... OK`;
      `just test-transcripts` → `Total passed: 46, Total failed: 0`.

## 3. REFACTOR

- [x] 3.1 Limpiar y tipar sin alterar comportamiento; re-ejecutar en verde. — Evidencia:
      `_statuses_for()` como fuente única; `ruff` → `All checks passed!`; `py_compile` OK;
      `just test-transcripts` en verde.

## 4. Verificación end-to-end (con parada para revisión)

- [ ] 4.1 `--dry-run` con `--include-drafts` lista los borradores pendientes sin escribir nada.
- [ ] 4.2 Ejecutar **solo con 1 borrador** (piloto) y verificar en producción: adjunto `.srt`
      legible, meta `transcript-srt` escrita, estado **sigue** en borrador, feed sin cambios.
- [ ] 4.3 Tras el OK del usuario, ejecutar los otros 4 borradores y verificar de igual modo.

## 5. Cierre

- [x] 5.1 `openspec validate transcript-draft-episodes --strict`. — Evidencia: `is valid`.
- [ ] 5.2 `openspec archive transcript-draft-episodes`.
- [ ] 5.3 Git flow: `feature/transcript-draft-episodes` → PR a `development`.

## 6. Fase de contenido (fuera de código; tras las transcripciones)

- [ ] 6.1 Redactar el artículo (`post_content`) de cada borrador a partir de su transcripción.
- [ ] 6.2 Escribir la metadescripción (`_genesis_description`) y completar los datos del episodio.
