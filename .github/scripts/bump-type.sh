#!/usr/bin/env bash
#
# bump-type.sh -- determina el tipo de bump semver a partir de asuntos de commit.
#
# Contrato:
#   - Entrada: asuntos de commit por stdin, uno por línea (puede estar vacío).
#   - Salida : exactamente `major`, `minor` o `patch` por stdout.
#   - Salida 0 siempre. Entrada vacía o sin asuntos -> `patch`.
#
# Reglas (gitmoji/Conventional Commits), con precedencia major > minor > patch
# sobre el conjunto completo de asuntos:
#   - major: `BREAKING CHANGE` o `💥` en cualquier posición, o un marcador `!:`
#     anclado al prefijo del tipo (p. ej. `feat!:`, `feat(api)!:`, `fix(scope)!:`).
#   - minor: un tipo `feat` real anclado al inicio (tras prefijos no alfanuméricos
#     como emojis); `feature`/`features` NO cuentan.
#   - patch: el resto.
#
# Solo ERE POSIX (`grep -E`): nada de `grep -P`, `\w` ni `\b`.
#
# El workflow invoca este script sobre `git log --no-merges ... --format="%s"`:
# los commits de merge se excluyen porque su asunto arrastra el nombre de rama
# (`feature/...`, `hotfix/...`) y no representan un cambio real; sin `--no-merges`
# un merge hacia una rama `feature/` se contaría como `feat` y forzaría `minor`.
set -euo pipefail

# Todos los asuntos de una vez, para poder aplicar la precedencia global.
SUBJECTS=$(cat)

if printf '%s\n' "$SUBJECTS" | grep -q -E '(BREAKING CHANGE|💥)|^[^[:alnum:]]*[a-z]+(\([^)]*\))?!:'; then
  type=major
elif printf '%s\n' "$SUBJECTS" | grep -q -E '^[^[:alnum:]]*feat(\(|:|!|$)'; then
  type=minor
else
  type=patch
fi

printf '%s\n' "$type"
