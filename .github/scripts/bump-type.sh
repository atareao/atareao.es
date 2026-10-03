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
#   - major: `BREAKING CHANGE`, `BREAKING-CHANGE` o `💥` en cualquier posición,
#     o un marcador `!:` anclado al prefijo del tipo (p. ej. `feat!:`,
#     `feat(api)!:`, `fix(scope)!:`). La detección es SENSIBLE A MAYÚSCULAS: así
#     lo exige Conventional Commits (los tokens van en mayúsculas) y git-cliff;
#     un `breaking change:` en minúsculas es prosa, no un marcador.
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

# Here-string en vez de encadenar `printf` y `grep -q` con una tubería: con
# `set -o pipefail`, si el asunto que casa está al principio de una lista larga,
# `grep -q` cierra el pipe antes de leer todo, `printf` recibe SIGPIPE (141) y
# la tubería devuelve 141 -> el `if` sería falso y major/minor se degradarían a
# patch según el orden.
if grep -q -E '(BREAKING CHANGE|BREAKING-CHANGE|💥)|^[^[:alnum:]]*[a-z]+(\([^)]*\))?!:' <<<"$SUBJECTS"; then
  bump_type=major
elif grep -q -E '^[^[:alnum:]]*feat(\(|:|!|$)' <<<"$SUBJECTS"; then
  bump_type=minor
else
  bump_type=patch
fi

printf '%s\n' "$bump_type"
