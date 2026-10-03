# Design: Detección determinista del tipo de bump

## Context

`release-prepare.yml` dispara en cada push a `main`. El job `release` calcula el tipo de bump en el paso `Determine bump type from commits` (`.github/workflows/release-prepare.yml`, líneas 51-62):

```bash
LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || git rev-list --max-parents=0 HEAD | head -1)
COMMITS=$(git log "$LAST_TAG"..HEAD --format="%s")
if echo "$COMMITS" | grep -q -E "(BREAKING CHANGE|!:|💥)"; then
  echo "type=--major" >> "$GITHUB_OUTPUT"
elif echo "$COMMITS" | grep -q -E "(^feat|^[^\w]*feat)"; then
  echo "type=--minor" >> "$GITHUB_OUTPUT"
else
  echo "type=--patch" >> "$GITHUB_OUTPUT"
fi
```

El resultado alimenta `vampus upgrade $TYPE` y `git-cliff`, por lo que un tipo equivocado se propaga a la versión y al CHANGELOG.

Defectos constatados:

1. **`[^\w]` no es "no-palabra" en ERE POSIX.** `grep -E` usa POSIX ERE, donde `\w` no existe; `[^\w]` es la clase de todo salvo `\` y `w`. Por eso `^[^\w]*feat` casa con casi cualquier prefijo y detecta `feat` dentro de `feature`. Reproducido: `printf 'Merge pull request #51 from atareao/feature/ci-release-token-validation\n' | grep -E "(^feat|^[^\w]*feat)"` casa (salida 0). En el release v1.12.0 ese merge forzó `--minor`.
2. **Los commits de merge contaminan.** Sus asuntos contienen rutas de rama (`feature/...`, `hotfix/...`) y no representan un cambio real. El merge `Merge pull request #52 from atareao/development` tampoco debería influir.
3. **`major` gana por texto anecdótico.** El `if` de BREAKING se evalúa sobre el listado completo y `!: ` puede aparecer en medio de un asunto (p. ej. `docs: explain !: syntax` → `major` falso).

Restricción del repo: **no existe framework de tests** (ni PHPUnit, ni Jest, ni cargo). La lógica vive en YAML de GitHub Actions, difícil de ejecutar en local. Por eso la testabilidad exige extraerla a shell.

## Goals / Non-Goals

**Goals**

- Determinar `major|minor|patch` de forma determinista a partir de los asuntos de los commits **reales** (sin merges).
- Que el nombre de rama de un merge (`feature/...`, `hotfix/...`) no pueda alterar nunca el resultado.
- Anclar la detección al prefijo del asunto y usar solo ERE POSIX (portable en `grep -E` de `ubuntu-latest` y local).
- Corregir el falso `major` por `!:` en texto anecdótico.
- Poder ejecutar y verificar la clasificación en local con CLI, sin GitHub Actions.

**Non-Goals**

- No cambiar el resto del pipeline (bump con vampus, changelog, tag, PRs, sync de `development`).
- No introducir un framework de tests ni dependencias nuevas.
- No reescribir la convención de commits del repositorio ni retirar los emojis (gitmoji).
- No cambiar el comportamiento "sin tag previo".
- No tocar `release.yml` ni otros workflows.

## Decisions

### Decisión 1: Excluir merges con `git log --no-merges`

El nombre de rama de un merge (`feature/ci-release-token-validation`) es la causa directa de la regresión v1.12.0. Los commits de merge no representan cambios de código: son integraciones. Excluirlos con `--no-merges` elimina la clase entera de falsos positivos, no solo el caso `feature/`. Un `feat` real siempre llega en su propio commit (squash o commit directo), no en el asunto de un merge.

**Alternativa descartada:** dejar los merges y confiar solo en el patrón anclado. El patrón `^[^[:alnum:]]*feat(...)` ya ignora la mayoría de asuntos de merge, pero es *defensa en profundidad*: seguir procesando merges deja una superficie innecesaria (p. ej. un merge squash cuyo asunto empiece por el título del PR). `--no-merges` es la garantía estructural; el patrón es la segunda barrera.

### Decisión 2: Patrón ERE anclado para `feat`

Elegido: `^[^[:alnum:]]*feat(\(|:|!|$)`.

- `^` ancla al inicio del asunto.
- `[^[:alnum:]]*` consume prefijos no alfanuméricos: espacios y **cualquier gitmoji** (`✨ `, `🚀 `...), no solo `✨`. En ERE POSIX, `[^[:alnum:]]` es negación de la clase POSIX `alnum`, portable en `grep -E`.
- `feat` es el tipo literal.
- `(\(|:|!|$)` exige que tras `feat` venga `(` (scope), `:` (tipo), `!` (breaking) o fin de asunto. Esto impide casar `feature` (tras `feat` viene `u`) y `features`.

Ejemplos: `✨ feat(pocketid): ...` → casa; `feat: x` → casa; `  feat: x` → casa; `feature/foo` → no casa; `Merge pull request #51 from atareao/feature/...` → no casa.

**Alternativa evaluada (sugerida):** `^[[:space:]]*(✨[[:space:]]*)?feat(\(|:|!|$)`. Es correcta y más conservadora, pero solo admite el gitmoji `✨`; un `feat` con otro gitmoji (`🚀 feat:`) se clasificaría como `patch` (infravaloración silenciosa). `[^[:alnum:]]*` es igual de anclada y cubre todos los emojis. Se descarta la versión restringida por frágil ante el catálogo de gitmoji.

**Alternativa descartada:** usar `grep -P` con `\w`/`\b`. `grep -P` (PCRE) no está garantizado en todos los `grep`/BSDs y añade dependencia de implementación; el repo debe funcionar con `grep -E`. Además `\b` en PCRE puede dar resultados distintos según locale.

**Alternativa descartada:** corregir solo el patrón (`^feat`) sin excluir merges. Un asunto de merge no empieza por `feat`, así que arreglaría la regresión de `feature/`, pero deja los merges en el listado y cualquier asunto de squash-merge que empiece por `feat` (título del PR) seguiría contando. No cierra la clase de defecto.

### Decisión 3: Anclar también el `!:` de BREAKING

Elegido para BREAKING: `(BREAKING CHANGE|💥)|^[^[:alnum:]]*[a-z]+(\([^)]*\))?!:`.

- `BREAKING CHANGE` y `💥` se detectan en cualquier posición: git-cliff y la convención de Conventional Commits admiten `BREAKING CHANGE:` como token/footer, y el repo usa `💥`.
- El marcador `!:` se ancla al prefijo del tipo: `[a-z]+` + scope opcional `(\([^)]*\))?` + `!:`. Así `feat!:`, `feat(api)!:` y `fix(scope)!:` son `major`, pero `docs: explain !: syntax` queda `patch`.

**Alternativa descartada (sugerida):** `(BREAKING CHANGE|!:)` sin anclar. Mantiene el defecto secundario: cualquier `!: ` anecdótico en un asunto dispara `major`. El anclaje elimina ese falso positivo sin perder los casos legítimos (el `!` breaking de Conventional Commits siempre va inmediatamente antes de `:` y tras el tipo/scope).

**Límite conocido:** un asunto no convencional que use `!:` fuera del prefijo no se detectará como breaking; es coherente con "solo commits convencionales determinan el bump" y evita falsos `major`. Si en el futuro se adoptan footers de breaking en el cuerpo, habría que ampliar `git log --format` más allá de `%s`.

### Decisión 4: Precedencia determinista major > minor > patch

La clasificación evalúa primero BREAKING sobre cada asunto y, si ninguno lo es, `feat`; si no, `patch`. Se recorre la lista completa y se queda la severidad máxima. Esto evita el "el primero que gane" y garantiza que el orden de commits no altere el resultado (determinismo), corrigiendo de paso el defecto de que `major` se decidiera por un `if` sobre el listado completo sin anclaje.

### Decisión 5: Extraer la lógica a `.github/scripts/bump-type.sh`

El repositorio no tiene framework de tests y la lógica está embebida en YAML. Un script `bash` que lee asuntos por **stdin** (uno por línea) y escribe `major|minor|patch` por stdout es:

- **Determinista y puro:** sin red, sin git, sin estado; misma entrada → misma salida.
- **Testeable en local:** se le alimenta la tabla de casos directamente.
- **Reutilizable por el workflow:** `printf '%s\n' "$COMMITS" | .github/scripts/bump-type.sh`.
- **Sin dependencias nuevas:** `bash` + `grep -E`.

El workflow queda como orquestador: obtiene `LAST_TAG`, ejecuta `git log --no-merges "$LAST_TAG"..HEAD --format="%s"`, delega la clasificación al script y mapea `major|minor|patch` a `--major|--minor|--patch` para `vampus`.

**Alternativa descartada:** dejar el `if/elif` inline en el YAML. No es verificable en local sin ejecutar el workflow completo (que crearía una release espuria) y mezcla lógica de negocio con orquestación.

### Decisión 6: Test en shell con tabla de casos

`.github/scripts/bump-type.test.sh` es un script `bash` sin framework: define pares `esperado|asunto`, ejecuta `bump-type.sh` por caso, compara y lleva la cuenta de fallos. Termina con `exit 1` si algún caso falla y `exit 0` si todos pasan. Se ejecuta con `bash .github/scripts/bump-type.test.sh`.

Casos cubiertos (mínimos, según spec):

| Asunto | Esperado |
|---|---|
| `✨ feat(pocketid): login resilience` | `minor` |
| `feat: add thing` | `minor` |
| `fix: bug` / `docs: x` / `ci: x` / `chore: x` / `style: x` / `refactor: x` | `patch` |
| `feat!: drop old` | `major` |
| `feat(api)!: drop old` | `major` |
| `fix(scope)!: x` | `major` |
| `BREAKING CHANGE: x` | `major` |
| `💥 rework` | `major` |
| `Merge pull request #51 from atareao/feature/ci-release-token-validation` | `patch` (regresión) |
| `Merge pull request #52 from atareao/development` | `patch` |
| `docs: describe the feature` | `patch` |
| `docs: explain !: syntax` | `patch` |
| (sin asuntos / vacío) | `patch` |

**Alternativa descartada:** introducir bats o shellspec. Añade una dependencia y una instalación en CI que el repo no justifica para una función de ~30 líneas.

### Decisión 7: "Sin tag previo" mantiene el comportamiento actual

Si `git describe --tags --abbrev=0` falla, se conserva el fallback actual: `LAST_TAG=$(git rev-list --max-parents=0 HEAD | head -1)`, es decir, se clasifica todo el historial desde el primer commit. Se decide explícitamente **no cambiarlo** para no ampliar el alcance: el defecto reportado no depende de este caso, y definirlo de otro modo (p. ej. `patch` fijo) alteraría releases de arranque de repos sin tags. Queda documentado como comportamiento heredado; si un repositorio nuevo quiere otra política, será un change aparte.

Además, si el rango no produce asuntos (no hay commits nuevos), `bump-type.sh` sin entrada emite `patch`, igual que el `else` actual.

### Decisión 8: Evitar SIGPIPE con here-string en lugar de tubería

La primera implementación pasaba los asuntos a `grep -q` con `printf '%s\n' "$SUBJECTS" | grep -q -E ...`. Bajo `set -o pipefail`, cuando el asunto que casa está al **principio** de una lista larga (un rango de release con miles de commits), `grep -q` termina en cuanto encuentra la coincidencia y cierra el pipe; `printf` recibe **SIGPIPE** (exit 141) y `pipefail` hace que la tubería completa devuelva 141. El `if` se evalúa entonces como falso y un `major`/`minor` se degrada silenciosamente a `patch` **según el orden de los asuntos**. Reproducido: `{ echo '💥 rework'; for i in $(seq 1 5000); do echo "docs: relleno $i"; done; } | bump-type.sh` devolvía `patch` en vez de `major`.

Elegido: **here-string** `grep -q -E '...' <<<"$SUBJECTS"`. No hay un segundo proceso en tubería que pueda recibir SIGPIPE; `grep` lee el here-string completo y su corte interno al encontrar la coincidencia no propaga un estado distinto. Se conservan `set -euo pipefail`, los patrones anclados y la precedencia global.

**Alternativas descartadas:**

- **Quitar `pipefail`:** debilita todo el script y enmascara fallos de otras etapas; inaceptable como arreglo local.
- **Volcar `$SUBJECTS` a un fichero temporal y `grep fichero`:** correcto, pero añade gestión de temporales y E/S extra sin necesidad.
- **Evaluar con `case`/glob de bash:** los patrones deben seguir siendo ERE POSIX verificables, no globs.

**Riesgo:** el here-string materializa todos los asuntos en memoria (variable + here-string). Para rangos de release de miles de líneas (~decenas de KB) es irrelevante.

## Risks / Trade-offs

- **[Degradación silenciosa por SIGPIPE en listas largas]** → El productor `printf` podía recibir SIGPIPE bajo `pipefail` y degradar `major`/`minor` a `patch` según el orden de los asuntos. Corregido con here-string (Decisión 8) y cubierto por la sección de casos de volumen del runner.
- **[Infravalorar un `feat` con un gitmoji no contemplado]** → Mitigado con `[^[:alnum:]]*` (cualquier prefijo no alfanumérico) y con el caso de prueba `✨ feat(...)`. Un prefijo alfanumérico delante de `feat` (p. ej. `v2 feat:`) no se detectaría; no es una forma convencional de commit en el repo.
- **[`grep -E` con emojis y locale]** → Los bytes UTF-8 de un emoji no son `alnum` en el locale C/UTF-8 del runner; verificado en local con `✨` y `💥`. Si el runner forzara `LC_ALL` restrictivo, `grep` sigue tratando los bytes altos como no imprimibles/no alnum. No se requiere acción.
- **[Lost breaking changes en el cuerpo]** → Solo se lee `%s` (asunto); un `BREAKING CHANGE:` únicamente en el cuerpo no se detecta. Es una limitación preexistente y aceptada; el repo usa el marcador en el asunto (`💥`/`!`). Ampliarla exigiría leer `%B` y un parser de footers, fuera de alcance.
- **[Colisión con `release.yml`]** → No hay: el cambio solo toca la elección del flag de bump.
- **[Verificación del camino real]** → No se puede provocar un release sin efectos secundarios; el happy path se valida en el próximo release real.

## Migration Plan

1. Crear `.github/scripts/bump-type.sh` (clasificador) y `.github/scripts/bump-type.test.sh` (tabla de casos).
2. Ejecutar `bash .github/scripts/bump-type.test.sh` en local; debe salir con código 0.
3. Sustituir el bloque `if/elif` del paso `Determine bump type from commits` por la invocación `git log --no-merges ... | .github/scripts/bump-type.sh` y el mapeo a `--$TYPE`.
4. Verificar estáticamente el YAML (`python3 -c "import yaml; yaml.safe_load(...)"`).
5. PR de la rama `feature/release-bump-detection` a `development` por gitflow.
6. E2E diferida: en el próximo release real, comprobar en el log que el tipo elegido es el esperado.

**Rollback:** `git revert` del commit que toca `release-prepare.yml` y los scripts. No hay estado, secretos ni datos implicados. Detectar los scripts huérfanos no es necesario, pero pueden eliminarse en el mismo revert.

## Verification

- `bash .github/scripts/bump-type.test.sh` → `exit 0` con todos los casos en `PASS`.
- Inyección manual: `printf 'Merge pull request #51 from atareao/feature/x\n' | bash .github/scripts/bump-type.sh` → `patch`.
- `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/release-prepare.yml'))"` → sin excepción.
- `openspec validate release-bump-detection --strict` → sin hallazgos.
- E2E diferida en el próximo release real.

## Open Questions

Ninguna. Las decisiones de patrón, exclusión de merges, extracción a script y política "sin tag previo" quedan resueltas arriba.
