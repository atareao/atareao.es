#!/usr/bin/env bash
#
# release-needed.sh -- puerta de release: decide si un merge a `main` merece
# cortar una versión.
#
# Contrato:
#   - Entrada: asuntos de commit por stdin, uno por línea (puede estar vacío).
#   - Salida : exactamente `releasable` o `not-releasable` por stdout.
#   - Salida 0 SIEMPRE. Entrada vacía -> `not-releasable`.
#
# Regla (es un booleano; la precedencia no aplica):
#   - `releasable` si algún asunto casa un tipo funcional anclado al prefijo:
#       ^[^[:alnum:]]*(feat|fix|perf|refactor|revert|hotfix|deps)(\(|:|!|$)
#     o si algún asunto lleva un marcador de ruptura ANCLADO al prefijo:
#       ^[^[:alnum:]]*(BREAKING CHANGE|BREAKING-CHANGE)
#       ^[^[:alnum:]]*💥
#       ^[^[:alnum:]]*[a-z]+(\([^)]*\))?!:
#   - En cualquier otro caso (solo docs|chore|ci|test|style, merges, rango
#     vacío) -> `not-releasable`.
#
# El anclaje `^[^[:alnum:]]*` tolera el gitmoji y los espacios que Conventional
# Commits admite delante del tipo, pero OBLIGA a que el tipo sea el prefijo real
# del asunto: así `docs: describe the feature` o un merge que mencione `feat`
# dentro de la prosa NO cuentan. El tipo se cierra con `(`, `:`, `!` o fin de
# línea, de modo que un merge hacia `feature/...` (cuyo asunto arrastra el
# nombre de rama) tampoco cuenta: `feature` no es `feat(`, `feat:`, `feat!` ni
# `feat`. Sin ese anclaje, un `feature/...` en el asunto activaría la puerta y
# cortaría una versión sin cambios funcionales.
#
# El marcador de ruptura va TAMBIÉN anclado (a diferencia de `bump-type.sh`,
# cuya detección no está anclada): la puerta decide si se CORTA una release, y
# un asunto `docs: explain BREAKING CHANGE keyword` o `docs: document the 💥
# marker` es prosa documental, no un cambio de ruptura; con la detección como
# subcadena en cualquier posición, un rango solo-`docs` se marcaría `releasable`
# y cortaría una versión idéntica. El anclaje acepta además las formas `chore!:`,
# `style!:`, `docs!:` (cualquier tipo `[a-z]+` seguido de `!:`), que sí son
# rompientes por Conventional Commits.
#
# Solo ERE POSIX (`grep -E`): nada de `grep -P`, `\w` ni `\b`.
#
# El workflow invoca este script sobre `git log --no-merges ... --format="%s"`:
# la exclusión de merges elimina de raíz los asuntos que arrastran el nombre de
# rama; el anclaje del tipo es la segunda barrera para el resto de la prosa.
set -euo pipefail

# Todos los asuntos de una vez.
SUBJECTS=$(cat)

# Here-string en vez de encadenar `printf` y `grep -q` con una tubería: con
# `set -o pipefail`, si el asunto que casa está al principio de una lista larga,
# `grep -q` cierra el pipe antes de leer todo, `printf` recibe SIGPIPE (141) y
# la tubería devuelve 141 -> el `if` sería falso y un release real se degradaría
# silenciosamente a no-release según el ORDEN de los asuntos.
if grep -q -E '^[^[:alnum:]]*(feat|fix|perf|refactor|revert|hotfix|deps)(\(|:|!|$)' <<<"$SUBJECTS" \
   || grep -q -E '^[^[:alnum:]]*(BREAKING CHANGE|BREAKING-CHANGE)' <<<"$SUBJECTS" \
   || grep -q -E '^[^[:alnum:]]*💥' <<<"$SUBJECTS" \
   || grep -q -E '^[^[:alnum:]]*[a-z]+(\([^)]*\))?!:' <<<"$SUBJECTS"; then
  printf 'releasable\n'
else
  printf 'not-releasable\n'
fi
