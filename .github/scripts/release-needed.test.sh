#!/usr/bin/env bash
#
# Runner de pruebas para .github/scripts/release-needed.sh
#
# El repositorio no tiene framework de tests. Este script es la verificación
# de la puerta de release, con tres secciones:
#
#   1. Tabla de casos unitarios "esperado|asunto" (se alimentan por stdin).
#   2. Casos de volumen (SIGPIPE + `pipefail`).
#   3. Contrato del pipeline de release (release-prepare.yml invoca el
#      clasificador, conserva la exclusión de merges, imprime el mensaje de
#      no-release y condiciona los pasos de mutación/instalación a la puerta).
#   4. Contrato de CI (ci.yml ejecuta esta suite, dispara en pull_request hacia
#      main/development y no tiene filtro `paths:` que pueda saltársela).
#
#   - Imprime PASS/FAIL por caso.
#   - Si un script no existe o falla, el caso cuenta como FAIL y el runner NO
#     se aborta: acumula fallos hasta el final.
#   - Además del veredicto, run_case exige que el script salga SIEMPRE con 0
#     (el contrato lo fija): un exit != 0 es FAIL aunque el veredicto coincida.
#   - Sale con 1 si hay al menos un fallo, con 0 si todos pasan.
#   - Deriva sus rutas de ${BASH_SOURCE[0]}: puede ejecutarse desde cualquier
#     CWD (raíz del repo, /tmp, runner de CI).
#
# La sección de VOLUMEN existe porque el clasificador decide con `grep -E`
# sobre todos los asuntos: si un defecto dependiera de la posición del asunto
# significativo en una lista larga (p. ej. encadenar `printf` y `grep -q` por
# tubería bajo `set -o pipefail`, donde `grep -q` cierra el pipe antes de leer
# todo y `printf` recibe SIGPIPE), el resultado cambiaría según el ORDEN. La
# puerta debe ser determinista: mismo veredicto con el asunto al principio o al
# final de la lista.
#
# La sección de CONTRATO existe para que la garantía estructural no dependa de
# revisión manual: si alguien deja de invocar el script, quita `--no-merges`,
# borra el mensaje de no-release o descondiciona los pasos de mutación, la
# suite falla y (vía `.github/workflows/ci.yml`) rompe CI.
#
# Uso: bash .github/scripts/release-needed.test.sh
set -uo pipefail

# Rutas derivadas de la ubicación del propio script, no del CWD.
SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
SCRIPT="$SCRIPT_DIR/release-needed.sh"
REPO_ROOT=$(cd "$SCRIPT_DIR/../.." && pwd)
WORKFLOW="$REPO_ROOT/.github/workflows/release-prepare.yml"
CI_WORKFLOW="$REPO_ROOT/.github/workflows/ci.yml"

total=0
passed=0
failed=0

# run_case <esperado> <asunto>
# Ejecuta el clasificador con el asunto por stdin y compara la salida.
# Un asunto vacío se envía como entrada vacía (sin salto de línea).
# Además del veredicto, exige exit 0 (contrato): un exit != 0 es FAIL.
run_case() {
  local expected="$1"
  local subject="$2"
  local obtained
  local status

  if [[ -z "$subject" ]]; then
    obtained=$(printf '' | bash "$SCRIPT" 2>/dev/null)
    status=$?
  else
    obtained=$(printf '%s\n' "$subject" | bash "$SCRIPT" 2>/dev/null)
    status=$?
  fi

  total=$((total + 1))
  if [[ "$obtained" != "$expected" ]]; then
    failed=$((failed + 1))
    printf 'FAIL: %s -> esperado=%s obtenido=%s\n' "$subject" "$expected" "$obtained"
  elif [[ "$status" -ne 0 ]]; then
    failed=$((failed + 1))
    printf 'FAIL: %s -> veredicto=%s pero exit=%d (el contrato exige exit 0)\n' "$subject" "$obtained" "$status"
  else
    passed=$((passed + 1))
    printf 'PASS: %s -> %s\n' "$subject" "$expected"
  fi
}

# Tabla de casos: "esperado|asunto". El asunto nunca contiene '|'.
# La línea "not-releasable|" representa la entrada vacía.
while IFS='|' read -r expected subject; do
  [[ -z "$expected" && -z "$subject" ]] && continue
  run_case "$expected" "$subject"
done <<'CASES'
not-releasable|📝 docs(plan): cierra el release v1.20.0 instalado en producción
not-releasable|docs: x
not-releasable|chore: x
not-releasable|ci: x
not-releasable|test: x
not-releasable|style: x
releasable|fix: bug
releasable|🐛 fix(podcast): arregla esto
releasable|feat: add thing
releasable|✨ feat(pocketid): login resilience
releasable|🚀 feat: x
releasable|perf: x
releasable|refactor: x
releasable|revert: x
releasable|hotfix: x
releasable|deps: bump
releasable|feat!: drop old
releasable|fix(scope)!: x
releasable|chore!: drop legacy
releasable|style!: x
releasable|BREAKING CHANGE: x
releasable|BREAKING-CHANGE: x
releasable|💥 rework
not-releasable|docs: explain BREAKING CHANGE keyword
not-releasable|docs: document the 💥 marker
not-releasable|Merge pull request #51 from atareao/feature/ci-release-token-validation
not-releasable|Merge pull request #52 from atareao/development
not-releasable|docs: describe the feature
not-releasable|
CASES

# run_input_case <esperado> <etiqueta> <entrada>
# Igual que run_case pero con una entrada multilínea ya construida. Se usa en
# los casos de volumen; la etiqueta describe el caso en PASS/FAIL.
# Al igual que run_case, exige exit 0 (contrato): un exit != 0 es FAIL.
run_input_case() {
  local expected="$1"
  local label="$2"
  local input="$3"
  local obtained
  local status

  obtained=$(printf '%s' "$input" | bash "$SCRIPT" 2>/dev/null)
  status=$?

  total=$((total + 1))
  if [[ "$obtained" != "$expected" ]]; then
    failed=$((failed + 1))
    printf 'FAIL: %s -> esperado=%s obtenido=%s\n' "$label" "$expected" "$obtained"
  elif [[ "$status" -ne 0 ]]; then
    failed=$((failed + 1))
    printf 'FAIL: %s -> veredicto=%s pero exit=%d (el contrato exige exit 0)\n' "$label" "$obtained" "$status"
  else
    passed=$((passed + 1))
    printf 'PASS: %s -> %s\n' "$label" "$expected"
  fi
}

# Casos de volumen: entrada grande con el asunto significativo al principio
# (donde el SIGPIPE degradaba el resultado) y al final (contraste: demuestra
# que el veredicto no depende del orden ni del tamaño).
FILLER=""
for i in $(seq 1 5000); do
  FILLER+="docs: filler $i"$'\n'
done

run_input_case releasable "volumen: feat en primera posición (5001 asuntos)" "feat: the only real feature
${FILLER}"
run_input_case releasable "volumen: 💥 en primera posición (5001 asuntos)" "💥 rework
${FILLER}"
run_input_case not-releasable "volumen: solo docs (5000 asuntos)" "${FILLER}"
run_input_case releasable "volumen: feat en última posición (5001 asuntos)" "${FILLER}feat: the only real feature"

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
workflow_invokes_release_needed() { grep -q -F 'release-needed.sh' "$WORKFLOW"; }
workflow_uses_no_merges() { grep -q -F 'git log --no-merges' "$WORKFLOW"; }
workflow_has_no_release_message() { grep -q -F 'No hay cambios funcionales; no se prepara release.' "$WORKFLOW"; }
workflow_conditions_mutations() { grep -q -F 'steps.gate.outputs.needed' "$WORKFLOW"; }

run_contract_case "contrato: release-prepare.yml invoca release-needed.sh" workflow_invokes_release_needed
run_contract_case "contrato: release-prepare.yml excluye merges (git log --no-merges)" workflow_uses_no_merges
run_contract_case "contrato: release-prepare.yml imprime el mensaje de no-release" workflow_has_no_release_message
run_contract_case "contrato: release-prepare.yml condiciona los pasos de mutación a la puerta" workflow_conditions_mutations

# Predicados de contrato de CI (ci.yml): la suite debe ejecutarse en cada PR
# hacia main/development y no puede quedar escondida tras un filtro `paths:`.
ci_runs_gate_suite() { grep -q -F 'bash .github/scripts/release-needed.test.sh' "$CI_WORKFLOW"; }
ci_triggers_on_pull_request() {
  grep -q -E '^[[:space:]]*pull_request:' "$CI_WORKFLOW" \
    && grep -q -F 'branches: [main, development]' "$CI_WORKFLOW"
}
ci_has_no_paths_filter() { ! grep -q -E '^[[:space:]]*paths:' "$CI_WORKFLOW"; }

run_contract_case "contrato: ci.yml ejecuta la suite de la puerta" ci_runs_gate_suite
run_contract_case "contrato: ci.yml dispara en pull_request hacia main/development" ci_triggers_on_pull_request
run_contract_case "contrato: ci.yml sin filtro paths: que salte la suite" ci_has_no_paths_filter

printf '\nTOTAL=%d PASS=%d FAIL=%d\n' "$total" "$passed" "$failed"

if [[ "$failed" -gt 0 ]]; then
  exit 1
fi
exit 0
