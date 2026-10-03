# Proposal: Detección determinista del tipo de bump

## Why

El release **v1.12.0** se clasificó como `minor` sin ninguna feature real. El paso `Determine bump type from commits` de `release-prepare.yml` usa `grep -E "(^feat|^[^\w]*feat)"`. En ERE POSIX `[^\w]` no significa "no-palabra" (eso es PCRE): es una clase que excluye solo `\` y `w`, así que `^[^\w]*feat` casa con casi cualquier prefijo y detecta `feat` dentro de `feature`. El asunto del merge `Merge pull request #51 from atareao/feature/ci-release-token-validation` —¡una rama `feature/`!— se contó como commit `feat` y forzó `--minor`, dejando el CHANGELOG mal determinado.

Los commits de merge contaminan la detección con nombres de rama (`feature/`, `hotfix/`). Además, el `if` de `BREAKING` evalúa `!: ` en cualquier parte del asunto, de modo que un `major` puede dispararse por texto anecdótico. Se necesita una detección determinista, anclada al prefijo, insensible a los merges y verificable en local con CLI.

## What Changes

- Determinar el tipo de bump sobre los **commits reales**, excluyendo merges (`git log --no-merges`), para que el nombre de rama de un merge no pueda alterar el resultado.
- Sustituir `grep -E "(^feat|^[^\w]*feat)"` por un patrón **anclado y compatible con ERE POSIX**: `^[^[:alnum:]]*feat(\(|:|!|$)`.
- Anclar la detección de BREAKING al prefijo del asunto para `!:` (`^[^[:alnum:]]*[a-z]+(\([^)]*\))?!:`), manteniendo `BREAKING CHANGE` y `💥` en cualquier posición (convención de git-cliff).
- Extraer la lógica a un script determinista `.github/scripts/bump-type.sh` (asuntos por stdin → `major|minor|patch`), más `.github/scripts/bump-type.test.sh` con tabla de casos (sin framework de tests en el repo).
- El workflow invoca el script; el resultado es reproducible en local (`bash .../bump-type.test.sh`).
- Mantener el fallback actual "sin tag previo" (usar el primer commit del historial como base).

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `release-pipeline`: la determinación del tipo de bump pasa a ser determinista, anclada, insensible a merges y verificable con un script de pruebas.

## Impact

- **Archivos**: `.github/workflows/release-prepare.yml` (paso `Determine bump type from commits`) y scripts nuevos `.github/scripts/bump-type.sh` y `.github/scripts/bump-type.test.sh`.
- **Dependencias**: ninguna nueva; `bash` y `grep -E` están preinstalados en `ubuntu-latest` y en el entorno local.
- **Compatibilidad**: no afecta a `release.yml` ni a `vampus`/`git-cliff`; solo cambia cómo se elige `--major|--minor|--patch`.
- **Riesgo operativo**: un patrón mal calibrado podría infravalorar un bump. Mitigado con la tabla de casos y la regresión de `feature/` en el asunto de un merge. Rollback: revertir el workflow (los scripts quedan inertes).
- **Verificación**: `bash .github/scripts/bump-type.test.sh` con código de salida distinto de 0 si falla un caso, más el tipo de bump observado en el próximo release real.
