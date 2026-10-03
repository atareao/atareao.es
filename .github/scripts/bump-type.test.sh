#!/usr/bin/env bash
#
# Runner de pruebas para .github/scripts/bump-type.sh
#
# El repositorio no tiene framework de tests. Este script es la verificación
# del clasificador de bump: una tabla de casos "esperado|asunto" se alimenta
# al script por stdin y se compara la salida con lo esperado.
#
#   - Imprime PASS/FAIL por caso.
#   - Si .github/scripts/bump-type.sh no existe o falla, el caso cuenta como
#     FAIL y el runner NO se aborta: acumula fallos hasta el final.
#   - Sale con 1 si hay al menos un fallo, con 0 si todos pasan.
#
# Uso: bash .github/scripts/bump-type.test.sh
set -uo pipefail

SCRIPT=".github/scripts/bump-type.sh"

total=0
passed=0
failed=0

# run_case <esperado> <asunto>
# Ejecuta el clasificador con el asunto por stdin y compara la salida.
# Un asunto vacío se envía como entrada vacía (sin salto de línea).
run_case() {
  local expected="$1"
  local subject="$2"
  local obtained

  if [[ -z "$subject" ]]; then
    obtained=$(printf '' | bash "$SCRIPT" 2>/dev/null) || true
  else
    obtained=$(printf '%s\n' "$subject" | bash "$SCRIPT" 2>/dev/null) || true
  fi

  total=$((total + 1))
  if [[ "$obtained" == "$expected" ]]; then
    passed=$((passed + 1))
    printf 'PASS: %s -> %s\n' "$subject" "$expected"
  else
    failed=$((failed + 1))
    printf 'FAIL: %s -> esperado=%s obtenido=%s\n' "$subject" "$expected" "$obtained"
  fi
}

# Tabla de casos: "esperado|asunto". El asunto nunca contiene '|'.
# La línea "patch|" representa la entrada vacía.
while IFS='|' read -r expected subject; do
  [[ -z "$expected" && -z "$subject" ]] && continue
  run_case "$expected" "$subject"
done <<'CASES'
minor|feat: add thing
minor|✨ feat(pocketid): login resilience
minor|🚀 feat: x
patch|fix: bug
patch|docs: x
patch|ci: x
patch|chore: x
patch|style: x
patch|refactor: x
major|feat!: drop old
major|feat(api)!: drop old
major|fix(scope)!: x
major|BREAKING CHANGE: x
major|💥 rework
patch|Merge pull request #51 from atareao/feature/ci-release-token-validation
patch|Merge pull request #52 from atareao/development
patch|docs: describe the feature
patch|docs: explain !: syntax
patch|
CASES

printf '\nTOTAL=%d PASS=%d FAIL=%d\n' "$total" "$passed" "$failed"

if [[ "$failed" -gt 0 ]]; then
  exit 1
fi
exit 0
