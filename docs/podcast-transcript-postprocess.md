# podcast-transcript-postprocess — Puntuación y mayúsculas del SRT

## Qué es y qué problema resuelve

Las transcripciones (`<podcast:transcript>`) se generaban con el SRT crudo de Whisper: sin
puntuación ni mayúsculas. Esta feature añade un **post-proceso determinista** que puntúa y capitaliza
el texto respetando la estructura temporal del SubRip.

Es una herramienta **batch externa al runtime del sitio**: no añade código al plugin ni altera la
salida durante las visitas; se ejecuta sobre los ficheros SRT almacenados.

## Ficheros implicados

| Fichero (ruta relativa al repo) | Papel |
|---|---|
| `scripts/podcast-transcripts/transcribe.py` | Motor del post-proceso (opt-in) y utilidades de la pipeline. |
| `scripts/podcast-transcripts/glossary.tsv` | Glosario canónico + alias de transcripción. |
| `tests/podcast-transcripts/test_postprocess.py` | Suite de tests del post-proceso. |
| `.github/workflows/ci.yml` | Ejecuta la suite Python, `ruff` y `test_attach.php`. |

## Cómo funciona

- **Opt-in**: con `--postprocess` se aplica el post-proceso; **sin** la opción, el comportamiento es
  **idéntico** al anterior (cero regresión).
- **Puntuación guiada por huecos temporales**: el motor es **determinista y solo-stdlib**; decide el
  final de frase usando los huecos temporales entre subtítulos del SubRip
  (`SENTENCE_GAP_SECONDS = 0.5`).
- **Glosario** (`glossary.tsv`): pares canónico + alias. Los términos canónicos **no pueden cambiar
  el número de palabras** de la línea (invariante vigilado por test).
- **Reversión**: cada SRT procesado conserva `<fichero>.orig.srt` y un **manifiesto local** con
  `sha256` y verificación **fail-closed**.
- **Invariantes**: mismos bloques y timecodes; secuencia de palabras idéntica salvo alias declarados;
  SubRip válido; **idempotente y reanudable**; `--dry-run` no escribe nada.

## Configuración y variables

- `SENTENCE_GAP_SECONDS` (umbral del hueco, por defecto `0.5`).
- Opciones de CLI: `--postprocess`, `--dry-run`.
- El glosario vive junto al script (`scripts/podcast-transcripts/glossary.tsv`).

## Cómo habilitar / desplegar

**No requiere deploy** (batch externo al runtime). Uso:

1. `--dry-run` completo para validar sin escribir.
2. Aplicar a una **muestra** y revisar.
3. Aplicar al resto; verificar que el feed sigue con **850** `<podcast:transcript>`.

## Decisiones

- Motor **determinista solo-stdlib** (sin dependencias) y puntuación guiada por los huecos del
  SubRip, en vez de heurísticas de longitud.
- `--postprocess` **opt-in** para garantizar cero regresión.
- Reversión con `.orig.srt` + manifiesto `sha256` con verificación fail-closed.

## Follow-ups

- **Backfill real** de los ~850 episodios (operación contra producción, aún no ejecutada).
- Ampliar reglas de `ruff` (hoy `select=["E4","E7","E9","F"]`) cuando se salde la deuda preexistente.
