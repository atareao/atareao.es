# Tasks: Detección determinista del tipo de bump

> Nota: el repositorio no tiene framework de tests. La verificación de este cambio es la ejecución del script de pruebas en shell (`bash .github/scripts/bump-type.test.sh`), que devuelve código de salida distinto de 0 si falla un caso, más la verificación estática del workflow y la E2E diferida al próximo release real.

## 1. Script clasificador

- [x] 1.1 Crear `.github/scripts/bump-type.sh`: lee asuntos por stdin (uno por línea), evalúa BREAKING con `(BREAKING CHANGE|💥)|^[^[:alnum:]]*[a-z]+(\([^)]*\))?!:` y, si no, `feat` con `^[^[:alnum:]]*feat(\(|:|!|$)`, aplica precedencia `major > minor > patch` sobre toda la lista, emite `major|minor|patch` por stdout y `patch` con entrada vacía. Sin dependencias nuevas (`bash` + `grep -E`). Verificación: `bash -n .github/scripts/bump-type.sh` sin errores y `printf 'feat: x\n' | bash .github/scripts/bump-type.sh` imprime `minor`.

- [x] 1.2 Hacer ejecutables los scripts (`chmod +x`). Verificación: `test -x .github/scripts/bump-type.sh && test -x .github/scripts/bump-type.test.sh` con exit 0. `release-prepare.yml` invoca el clasificador explícitamente con `bash`; el bit de ejecución se mantiene como convención del repo.

## 2. Script de pruebas (tabla de casos)

- [x] 2.1 Crear `.github/scripts/bump-type.test.sh` con tabla `esperado|asunto`, ejecutando `bump-type.sh` por caso y acumulando fallos; `exit 1` si algún caso falla, `exit 0` si todos pasan. Verificación: `bash .github/scripts/bump-type.test.sh` con exit 0 y un resumen `PASS`.

- [x] 2.2 Cubrir el happy path de `minor`: `✨ feat(pocketid): login resilience` → `minor`; `feat: add thing` → `minor`. Verificación: los casos figuran en la tabla y el runner pasa.

- [x] 2.3 Cubrir `patch`: `fix: bug`, `docs: x`, `ci: x`, `chore: x`, `style: x`, `refactor: x`. Verificación: los casos figuran y pasan.

- [x] 2.4 Cubrir `major`: `feat!: drop old`, `feat(api)!: drop old`, `fix(scope)!: x`, `BREAKING CHANGE: x`, `💥 rework`. Verificación: los casos figuran y pasan.

- [x] 2.5 **Caso de regresión**: asunto de merge que menciona una rama `feature/...` (`Merge pull request #51 from atareao/feature/ci-release-token-validation`) → `patch`; y `Merge pull request #52 from atareao/development` → `patch`. Verificación: los casos figuran y pasan; además `printf 'Merge pull request #51 from atareao/feature/ci-release-token-validation\n' | bash .github/scripts/bump-type.sh` imprime `patch`.

- [x] 2.6 Cubrir falsos positivos de texto: `docs: describe the feature` → `patch`; `docs: explain !: syntax` → `patch`. Verificación: los casos figuran y pasan.

- [x] 2.7 Cubrir entrada vacía → `patch`. Verificación: `printf '' | bash .github/scripts/bump-type.sh` imprime `patch` y el caso figura en la tabla.

## 3. Integración en el workflow

- [x] 3.1 En `.github/workflows/release-prepare.yml`, sustituir el bloque `if/elif` del paso `Determine bump type from commits` por: `LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || git rev-list --max-parents=0 HEAD | head -1)`, `COMMITS=$(git log --no-merges "$LAST_TAG"..HEAD --format="%s")`, `TYPE=$(printf '%s\n' "$COMMITS" | .github/scripts/bump-type.sh)` y `echo "type=--$TYPE" >> "$GITHUB_OUTPUT"`. Verificación: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/release-prepare.yml'))"` sin excepción y `rg -n -F '[^\w]*feat' .github/workflows/release-prepare.yml` sin resultados.

- [x] 3.2 Confirmar que se conserva el fallback "sin tag previo" (primer commit del historial). Verificación: revisión del `LAST_TAG=...` en el workflow.

- [x] 3.3 Confirmar que no queda ningún `grep -E "(^feat|^[^\w]*feat)"` en el repositorio. Verificación: `rg -n -F '[^\w]*feat' .github/` sin resultados y `rg -n -F 'git log --no-merges' .github/workflows/release-prepare.yml` con una ocurrencia.

## 4. Verificación estática y local

- [x] 4.1 Ejecutar la suite de tests en CLI. Verificación: `bash .github/scripts/bump-type.test.sh; echo "exit=$?"` → `exit=0` y todos los casos `PASS`.

- [x] 4.2 Simular el caso de regresión real de v1.12.0 contra el script. Verificación: `printf 'Merge pull request #51 from atareao/feature/ci-release-token-validation\n' | bash .github/scripts/bump-type.sh` → `patch`.

- [x] 4.3 Parsear el workflow completo. Verificación: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/release-prepare.yml'))"` con exit 0.

- [x] 4.4 Validar los artefactos OpenSpec. Verificación: `openspec validate release-bump-detection --strict` sin hallazgos.

## 5. Revisión del change y PR

- [x] 5.1 Crear rama `feature/release-bump-detection`, commit convencional y PR a `development` por gitflow. Verificación: PR abierto contra `development` y check de lint en verde; `release-prepare.yml` no se dispara (solo corre en push a `main`). Verificación: PR #56 mergeado en development (merge 100571d); run 37104418750 del workflow CI, job lint, paso "Test release bump type detection" → TOTAL=27 PASS=27 FAIL=0.

## 6. Verificación E2E diferida al próximo release real

- [ ] 6.1 En el próximo release real, comprobar en el log del paso `Determine bump type from commits` que el tipo elegido coincide con los commits reales (sin merges) y que no hay regresión por nombres de rama `feature/`/`hotfix/`. Verificación: revisión del run de GitHub Actions; no se puede provocar con `gh workflow run` sin crear una release espuria. Nota de archivo: la E2E se comprobará en el próximo release real (no se puede provocar con `gh workflow run` sin crear una release espuria), por lo que se difiere y se archiva con esta única tarea pendiente.

## 7. Corrección post-review: SIGPIPE en listas largas

- [x] 7.1 RED: añadir casos de volumen al runner (asunto significativo `feat`/`💥` en primera posición + contraste al final) que fallen con la tubería `printf | grep -q` original. Verificación: `bash .github/scripts/bump-type.test.sh; echo "exit=$?"` → `exit=1` con los casos de volumen en FAIL y los 19 de tabla en PASS.

- [x] 7.2 GREEN: sustituir la tubería por here-string (`grep -q -E '...' <<<"$SUBJECTS"`) para no propagar SIGPIPE bajo `pipefail`. Verificación: `bash .github/scripts/bump-type.test.sh; echo "exit=$?"` → `exit=0` con los 19 casos de tabla + 3 de volumen en PASS y `bash -n` sin errores.

- [x] 7.3 Verificación independiente: reproducir la evidencia (`💥` primero → `major`, `feat` primero → `minor`, `v1.11.0..v1.12.0` → `patch`) y confirmar que no queda ninguna tubería `| grep -q` en `.github/scripts/`. Verificación: salidas esperadas y `rg -n '\| *grep -q' .github/scripts/` sin coincidencias.

## 8. Hallazgos del review

- [x] 8.1 (SEC-GEN-001) Ejecutar la suite en CI: paso `Test release bump type detection` dentro del job `lint` de `.github/workflows/ci.yml` (sin job nuevo ni renombrar `lint`). Verificación: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))"` sin excepción y `git diff development...HEAD -- .github/workflows/ci.yml` con el job `lint` intacto.

- [x] 8.2 (SEC-GEN-002) Sección "contrato del pipeline" en el runner (usa `--no-merges`, invoca `bump-type.sh`, no reintroduce `[^\w]`), dentro del mismo `TOTAL/PASS/FAIL`. Verificación: `bash .github/scripts/bump-type.test.sh` → 27/27 PASS y, en una copia temporal sin `--no-merges`, `exit=1`.

- [x] 8.3 (SEC-GEN-003) Aceptar `BREAKING-CHANGE` (detección sensible a mayúsculas) y añadir `major|BREAKING-CHANGE: x` / `patch|breaking change: x`. Verificación: `printf 'BREAKING-CHANGE: x\n' | bash .github/scripts/bump-type.sh` → `major`.

- [x] 8.4 (SEC-GEN-004) Derivar `SCRIPT`/`REPO_ROOT`/`WORKFLOW` de `${BASH_SOURCE[0]}`. Verificación: `cd /tmp/opencode && bash /data/php/atareao.es/.github/scripts/bump-type.test.sh` → `exit=0`.

- [x] 8.5 (SEC-GEN-005) Renombrar `type` → `bump_type` en `bump-type.sh` (evita confusión con el builtin). Verificación: suite 27/27 PASS.

- [x] 8.6 (SEC-GEN-006) Invocación explícita `bash .github/scripts/bump-type.sh` en `release-prepare.yml`; bit `755` mantenido. Verificación: `rg -n 'bash .github/scripts/bump-type.sh' .github/workflows/release-prepare.yml` con una ocurrencia.

- [x] 8.7 Mantener SEC-GEN-007 (falso `major` por prosa) y SEC-GEN-008 (entradas malformadas) como trade-offs documentados en `design.md` (Risks/Trade-offs), sin cambiarlos.
