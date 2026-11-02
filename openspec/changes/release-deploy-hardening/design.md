# Design: Endurecer el ciclo release→despliegue

## Context

Dos fallos de **proceso** medidos el 2026-11-02. No son fallos de la aplicación.

**A) La puerta de release no existe.** `.github/workflows/release-prepare.yml` dispara en cada push
a `main`. El paso `Determine bump type from commits` (líneas 85-92) clasifica los commits reales
con `.github/scripts/bump-type.sh`, y `bump-type.sh` mapea **`docs ⇒ patch`** (igual que cualquier
tipo que no sea `feat`/breaking). El PR #160 (`development → main`) llevaba un único commit real,
`📝 docs(plan): cierra el release v1.20.0 instalado en producción`, y aun así el pipeline:
bump-eó la versión, regeneró `CHANGELOG.md`, creó la rama `release/v1.20.1`, taggeó `v1.20.1`,
disparó `release.yml` (publicó `atareao-theme.zip` + `atareao-functionality.zip`) y abrió el PR
`release/v1.20.1 → main`. El contenido de v1.20.1 es idéntico al de v1.20.0 salvo `PLAN.md`.

El clasificador `bump-type.sh` está **bien**: su contrato exige emitir `major|minor|patch`, y para
un rango solo-`docs` debe emitir `patch` (un bump de mantenimiento es legítimo). Lo que falta es una
decisión **previa y distinta**: *¿este rango merece cortar una release?* Hoy nadie la toma.

**B) El despliegue quirúrgico no está encapsulado.** El procedimiento «copiar ficheros al volumen de
producción» (`docker cp` + `chown www-data:www-data`) solo existe como prosa en `PLAN.md` §2.5 y en
`openspec/changes/archive/2026-11-02-*/tasks.md`. No hay script. Al reinstalar el plugin oficial con
WP-CLI, la instalación falló con `chmod(): Operation not permitted`: tres ficheros
(`atareao-functionality.php`, `includes/class-metaboxes.php`, `includes/class-srt-upload.php`) eran
de `1000:1000` —el uid del host, filtrado por `docker cp`— y `wpcli` corre como uid `82`. Hubo que
normalizar a mano con `chown -R 82:82`. **Es repetible** mientras el `chown` correcto no lo garantice
un programa.

Restricción del repo: no hay framework de tests; el patrón vigente es scripts `bash` con runner
propio (`.github/scripts/bump-type.test.sh`, cableado en el job `lint` de `.github/workflows/ci.yml`).
Producción **no** está versionada aquí: `openspec/specs/infrastructure/spec.md` declara que el change
de infraestructura «no toca producción».

## Goals / Non-Goals

**Goals**

- Que un merge a `main` sin cambios funcionales **no** corte versión, ni tag, ni release, ni PR de release.
- Que la decisión «¿es releasable?» sea determinista, anclada al prefijo, sin merges y **verificable en local**.
- Encapsular el despliegue quirúrgico en un script reproducible que **garantice `82:82`** e **integridad (`sha256`)**.
- Que ambos scripts se prueben con dobles, sin tocar producción, y se ejecuten en CI.

**Non-Goals**

- No cambiar la clasificación `major|minor|patch` ni `bump-type.sh`.
- No cambiar `release.yml`, ni el flujo de publicación, ni `vampus`/`git-cliff`.
- No automatizar despliegue continuo: el script se invoca a mano.
- No tocar la aplicación (feed, CPTs, contrato del feed).

## Decisions

### Decisión 1: Puerta separada del clasificador (`release-needed.sh`, no tocar `bump-type.sh`)

La pregunta "¿qué bump?" y la pregunta "¿hay release?" son **distintas** y sus respuestas legítimas
difieren: un `fix` merece `patch` **y** merece release; un `docs` merece `patch` pero **no** release.
Meter la segunda en `bump-type.sh` rompería su contrato determinista ("emite siempre major|minor|patch")
y confundiría responsabilidades.

Elegido: `.github/scripts/release-needed.sh`, un script puro:
- Entrada: asuntos por stdin (uno por línea), igual que `bump-type.sh` (reutiliza el patrón).
- Salida: `releasable` o `not-releasable` por stdout; **exit 0** siempre (no es un gate por status).
- Regla: releasable si algún asunto casa `^[^[:alnum:]]*(feat|fix|perf|refactor|revert|hotfix|deps)(\(|:|!|$)`
  o contiene un marcador breaking (`BREAKING CHANGE`/`BREAKING-CHANGE`/`💥`); en otro caso (incluido
  rango vacío) `not-releasable`.

**Alternativa descartada:** reusar `bump-type.sh` y tratar `patch` como "no release". Incorrecto: `fix`
es `patch` y **sí** debe publicar. Se perderían releases de corrección.

**Alternativa descartada:** clasificar dentro del YAML. No verificable en local sin disparar el workflow.

### Decisión 2: Patrón anclado, ERE POSIX, con detección de ruptura anclada (a diferencia de `bump-type.sh`)

El mismo cuidado que ya documenta `bump-type.sh` Decisión 2: `^[^[:alnum:]]*` consume prefijos no
alfanuméricos (gitmojis, espacios) y `(\(|:|!|$)` exige que tras el tipo venga `(`, `:` o fin; así
`feature/...` de un merge **no** casa como `feat` y `docs: describe the feature` tampoco. Todo ERE
POSIX (`grep -E`), sin `\w`/`-P`. La exclusión de merges (`git log --no-merges`) es responsabilidad del
workflow, igual que hoy.

**Diferencia con `bump-type.sh`: aquí el marcador de ruptura va ANCLADO al prefijo.** `bump-type.sh`
detecta `(BREAKING CHANGE|BREAKING-CHANGE|💥)` como subcadena en cualquier posición porque su contrato
es solo clasificar el bump de un rango que ya se va a publicar: un falso positivo allí es inocuo (sube
`patch` a `major`, nunca omite una release). La puerta decide algo distinto —*si se corta una release*—
y el coste del falso positivo es el que motivó este change: un rango **solo-`docs`** que mencione el
token en prosa (`docs: explain BREAKING CHANGE keyword`, `docs: document the 💥 marker`) se marcaría
`releasable` y cortaría una versión idéntica a la anterior. Por eso las tres formas de ruptura se
anclan al prefijo: `^[^[:alnum:]]*(BREAKING CHANGE|BREAKING-CHANGE)`, `^[^[:alnum:]]*💥` y
`^[^[:alnum:]]*[a-z]+(\([^)]*\))?!:`. Esta última acepta cualquier tipo `[a-z]+` con `!:` (`feat!:` y
`fix!:` ya cubiertos, y también `chore!:`, `style!:`, `docs!:`), que Conventional Commits considera
rompientes; un `docs!:` **sí** publica porque el `!` es una señal explícita de breaking, no prosa.

### Decisión 3: La puerta es un paso del job `release`, con `if:` sobre un `output`

En `release-prepare.yml`, tras `Determine bump type` (y tras validar `GH_PAT` y el checkout), un paso
`id: gate` ejecuta `release-needed.sh` sobre el mismo rango `LAST_TAG..HEAD` (`--no-merges`) y escribe
`needed=true|false` en `$GITHUB_OUTPUT`. Los pasos de bump/changelog/tag/PR llevan
`if: steps.gate.outputs.needed == 'true'`. Cuando `needed=false`, el job **termina con éxito** con un
`echo` informativo; el job `sync-development` **no** se ve afectado (sigue su `if` propia / diff guard).

**Alternativa descartada:** fallar el job cuando no hay release. Falso: no es un error, es un no-op
esperado; un job rojo por cada push de docs genera ruido y rompe la señal de CI.

**Riesgo:** una puerta mal calibrada podría **omitir un release legítimo**. Mitigado con la tabla de
casos (un `fix`/`feat`/breaking siempre publica) y con el contrato de CI. Rollback: revertir el workflow.

### Decisión 4: Script de despliegue con dobles, sin tocar producción en las pruebas

`scripts/deploy-to-prod.sh` parametriza host remoto y la lista de ficheros y ejecuta, por cada fichero:
1. `sha256` del origen (host local) y `scp`/`docker cp` al destino remoto (interfaz inyectable).
2. Copia de seguridad con marca de tiempo del fichero previo en el volumen.
3. `chown 82:82` + `chmod` **dentro del contenedor de WP-CLI (uid 82)** — no desde fuera, para no
   reintroducir `1000:1000`.
4. `sha256` del destino y comparación con el origen.
5. Comprobación de propiedad final == `82:82`.
Ante cualquier discrepancia → mensaje accionable y `exit != 0`.

Para que sea verificable, los comandos externos (`ssh`, `docker`, `scp`, `sha256sum`) se invocan de
forma que la suite pueda **sustituirlos por dobles** (funciones/`PATH` con stubs), de modo que
`scripts/deploy-to-prod.test.sh` comprueba los seis escenarios de la spec **sin** conexión real.

**Alternativa descartada:** script sin pruebas. El fallo de `1000:1000` es precisamente el coste de no
tener una verificación reproducible.

**Alternativa descartada:** `docker cp` + `chown` desde el host con `sudo`/root del host. Filtra la
propiedad del host y depende de que el host tenga permiso; hacerlo dentro del contenedor uid `82` es
la vía que garantiza el resultado correcto.

### Decisión 5: Suite en CI dentro del job `lint` (sin job nuevo)

Igual que `bump-type.test.sh` hoy: un paso `bash .github/scripts/release-needed.test.sh` y otro
`bash scripts/deploy-to-prod.test.sh` en el job `lint` de `ci.yml`. **Sin** job nuevo (no duplicar el
required check) y sin recortar permisos. Las suites derivan rutas de `${BASH_SOURCE[0]}`.

### Decisión 6: `tasks.md` marca la puerta como "no hay release" con éxito

El mensaje del job cuando `needed=false` incluirá explícitamente `No hay cambios funcionales; no se
prepara release.` para que el log sea la evidencia buscable.

## Risks / Trade-offs

- **[Puerta demasiado estricta omite releases]** → tabla de tipos releasables explícita; `fix`/`feat`/
  breaking siempre publican; el próximo release real es la verificación E2E.
- **[Puerta demasiado laxa]** → cubre el caso real (solo `docs`); `chore`/`ci`/`test`/`style` también
  no publican. Un cambio funcional nunca llega con esos tipos (convención del repo).
- **[`release-needed.sh` y `bump-type.sh` divergen]** → comparten estilo de patrón anclado; la suite
  fija los casos límite (`feature/`, `docs: ... feature`).
- **[El script de despliegue toca producción]** → en tests se usan dobles; en uso real, hash + propiedad
  + backup convierten cualquier inconsistencia en aborto, no en daño silencioso.
- **[SIGPIPE con listas largas]** → la suite incluye casos de volumen con asunto significativo al
  principio (heredado de la lección de `bump-type.sh`).
- **[Log sin token]** → ni la puerta ni el despliegue imprimen secretos; solo hashes y propiedades.

## Migration Plan

1. **Puerta.** Crear `.github/scripts/release-needed.sh` + `.github/scripts/release-needed.test.sh` (RED → GREEN).
   Cablear el paso en `release-prepare.yml` con `if:` sobre el `output` y el `echo` de no-op.
2. **Despliegue.** Crear `scripts/deploy-to-prod.sh` + `scripts/deploy-to-prod.test.sh` (RED → GREEN);
   receta `deploy-prod` en `.justfile`; actualizar `PLAN.md` §2.5 y `GIT_FLOW.md`/`docs/`.
3. **CI.** Añadir los dos pasos de suite al job `lint` de `ci.yml`.
4. Verificar en local (`bash …test.sh`), `actionlint`/`python3 -c "import yaml; ..."`, `openspec validate --strict`.
5. Land por PRs según gitflow (`feature/release-deploy-hardening` → `development`; luego ciclo de release).
6. E2E: el próximo merge a `main` solo-`docs` (si lo hubiera) no debe cortar release; un `fix`/`feat` sí.

**Rollback:** `git revert` de los commits; no hay estado ni secretos implicados.

## Verification

- `bash .github/scripts/release-needed.test.sh` → `exit 0` con todos los casos PASS.
- `bash scripts/deploy-to-prod.test.sh` → `exit 0` con todos los casos PASS (dobles, sin producción).
- Ambas suites corridas desde otro CWD (`cd /tmp && bash <repo>/…test.sh`) → `exit 0`.
- Regresión de contrato: copia temporal sin `--no-merges` → `exit 1`; el repo real no se toca.
- `printf '📝 docs(plan): x\n' | bash .github/scripts/release-needed.sh` → `not-releasable`.
- `printf '🐛 fix(x): y\n' | bash .github/scripts/release-needed.sh` → `releasable`.
- `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/release-prepare.yml'))"` → sin excepción.
- `openspec validate release-deploy-hardening --strict` → válido.
- E2E diferida al próximo ciclo de release real.

## Open Questions

Ninguna que bloquee. Decisiones tomadas: puerta separada, `if:` sobre `output` con job verde en no-op,
suite en el job `lint`, y script de despliegue con dobles + verificación de hash/propiedad.
