#!/usr/bin/env bash
#
# Runner de pruebas para .github/scripts/bump-type.sh
#
# El repositorio no tiene framework de tests. Este script es la verificación
# del clasificador de bump, con tres secciones:
#
#   1. Tabla de casos unitarios "esperado|asunto" (se alimentan por stdin).
#   2. Casos de volumen (SIGPIPE + `pipefail`).
#   3. Contrato del pipeline (el workflow conserva la exclusión de merges,
#      invoca el clasificador y no reintroduce el patrón defectuoso `[^\w]`).
#
#   - Imprime PASS/FAIL por caso.
#   - Si un script no existe o falla, el caso cuenta como FAIL y el runner NO
#     se aborta: acumula fallos hasta el final.
#   - Sale con 1 si hay al menos un fallo, con 0 si todos pasan.
#   - Deriva sus rutas de ${BASH_SOURCE[0]}: puede ejecutarse desde cualquier
#     CWD (raíz del repo, /tmp, runner de CI).
#
# La sección de VOLUMEN existe porque el clasificador original encadenaba
# `printf` y `grep -q` mediante tubería bajo `set -o pipefail`: cuando el asunto
# que casa está al principio de una lista larga, `grep -q` sale en cuanto
# encuentra la coincidencia, `printf` recibe SIGPIPE (exit 141) y, con
# pipefail, la tubería devuelve 141 -> el `if` sería falso y un `major`/`minor`
# se degradaría silenciosamente a `patch` según el ORDEN de los asuntos.
#
# La sección de CONTRATO existe para que la garantía estructural no dependa de
# revisión manual: si alguien quita `--no-merges` del workflow, deja de invocar
# el script o reintroduce `[^\w]`, la suite falla y (vía `.github/workflows/ci.yml`)
# rompe CI.
#
# Uso: bash .github/scripts/bump-type.test.sh
set -uo pipefail

# Rutas derivadas de la ubicación del propio script, no del CWD.
SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
SCRIPT="$SCRIPT_DIR/bump-type.sh"
REPO_ROOT=$(cd "$SCRIPT_DIR/../.." && pwd)
WORKFLOW="$REPO_ROOT/.github/workflows/release-prepare.yml"

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
major|BREAKING-CHANGE: x
patch|breaking change: x
major|💥 rework
patch|Merge pull request #51 from atareao/feature/ci-release-token-validation
patch|Merge pull request #52 from atareao/development
patch|docs: describe the feature
patch|docs: explain !: syntax
patch|
CASES

# run_input_case <esperado> <etiqueta> <entrada>
# Igual que run_case pero con una entrada multilínea ya construida. Se usa en
# los casos de volumen; la etiqueta describe el caso en PASS/FAIL.
run_input_case() {
  local expected="$1"
  local label="$2"
  local input="$3"
  local obtained

  obtained=$(printf '%s' "$input" | bash "$SCRIPT" 2>/dev/null) || true

  total=$((total + 1))
  if [[ "$obtained" == "$expected" ]]; then
    passed=$((passed + 1))
    printf 'PASS: %s -> %s\n' "$label" "$expected"
  else
    failed=$((failed + 1))
    printf 'FAIL: %s -> esperado=%s obtenido=%s\n' "$label" "$expected" "$obtained"
  fi
}

# Casos de volumen: entrada grande con el asunto significativo al principio
# (donde el SIGPIPE degradaba el resultado) y al final (contraste: demuestra
# que el defecto dependía del orden y no del tamaño).
FILLER=""
for i in $(seq 1 5000); do
  FILLER+="docs: filler $i"$'\n'
done

run_input_case minor "volumen: feat en primera posición (5001 asuntos)" "feat: the only real feature
${FILLER}"
run_input_case major "volumen: 💥 en primera posición (5001 asuntos)" "💥 rework
${FILLER}"
run_input_case minor "volumen: feat en última posición (5001 asuntos)" "${FILLER}feat: the only real feature"

# run_contract_case <etiqueta> <predicado...>
# Ejecuta un predicado (función/comando) contra el workflow de release y lo
# contabiliza como un caso más.
run_contract_case() {
  local label="$1"
  shift

  total=$((total + 1))
  if "$@" >/dev/null 2>&1; then
    passed=$((passed + 1))
    printf 'PASS: %s\n' "$label"
  else
    failed=$((failed + 1))
    printf 'FAIL: %s\n' "$label"
  fi
}

# Predicados de contrato del pipeline (release-prepare.yml).
workflow_uses_no_merges() { grep -q -F 'git log --no-merges' "$WORKFLOW"; }
workflow_invokes_bump_type() { grep -q -F 'bump-type.sh' "$WORKFLOW"; }
workflow_has_no_legacy_pattern() { ! grep -q -F '[^\w]' "$WORKFLOW"; }

run_contract_case "contrato: release-prepare.yml excluye merges (git log --no-merges)" workflow_uses_no_merges
run_contract_case "contrato: release-prepare.yml invoca bump-type.sh" workflow_invokes_bump_type
run_contract_case "contrato: release-prepare.yml no reintroduce el patrón defectuoso" workflow_has_no_legacy_pattern

printf '\nTOTAL=%d PASS=%d FAIL=%d\n' "$total" "$passed" "$failed"

if [[ "$failed" -gt 0 ]]; then
  exit 1
fi
exit 0
