# Tasks: Endurecer el ciclo release→despliegue

> Estado: **DRAFT** — esperando aprobación del change proposal. Ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Alcance: la puerta de release (¿hay cambios funcionales?) y el script de despliegue quirúrgico reproducible.
> TDD estricto: RED → GREEN → REFACTOR, por cada sub-cambio.

## 0. Contexto y decisiones previas

- [x] 0.1 Documentar el fallo de la puerta: el PR #160 (`development → main`) solo llevaba
      `📝 docs(plan): cierra el release v1.20.0 instalado en producción` y aun así `docs ⇒ patch`
      cortó **v1.20.1** con assets (`atareao-functionality.zip` / `atareao-theme.zip`) idénticos a v1.20.0.
- [x] 0.2 Documentar el fallo del despliegue: el `docker cp` + `chown` manual dejó tres ficheros de
      `atareao-functionality` en `1000:1000`; el `wpcli` (uid `82`) falló con
      `chmod(): Operation not permitted` al reinstalar el plugin.
- [x] 0.3 Decidir el reparto: la puerta va a la capability existente `release-pipeline`; el despliegue
      es capability nueva `prod-deploy` (producción está **fuera** del alcance de `infrastructure`).
- [ ] 0.4 Aprobación explícita del usuario del `proposal.md` + deltas + este `tasks.md`.

## 1. Puerta de release (`release-pipeline`)

### 1.1 RED — suite del script de decisión

- [ ] 1.1.1 Crear `.github/scripts/release-needed.test.sh` con los casos de la spec (solo `docs` → no;
      `chore`/`ci`/`test`/`style` → no; rango vacío → no; `fix` → sí; `feat` → sí; `perf`/`refactor`/
      `revert`/`deps` → sí; `!`/`BREAKING CHANGE`/`💥` → sí; merge `feature/...` no cuenta;
      `docs: describe the feature` no cuenta).
- [ ] 1.1.2 Ejecutar `bash .github/scripts/release-needed.test.sh` y pegar el fallo literal
      (el script aún no existe): salida `release-needed.sh: No such file or directory`.

### 1.2 GREEN — script de decisión

- [ ] 1.2.1 Crear `.github/scripts/release-needed.sh`: lee asuntos por stdin (`grep -E`, anclado al
      prefijo, sin `\w`), emite `releasable` / `not-releasable` (o código de salida 0/1), y trata
      el rango vacío como no releasable.
- [ ] 1.2.2 Ejecutar la suite → **100% verde**. Pegar salida literal.

### 1.3 Integración en el workflow

- [ ] 1.3.1 Añadir a `release-prepare.yml`, tras la validación de `GH_PAT` y antes del bump, un paso que
      invoque `release-needed.sh` sobre `git log --no-merges <último tag>..HEAD` y exponga un `output`.
- [ ] 1.3.2 Guardar el bump/changelog/tag/PR/`sync-development` con ese `output`, de modo que el job
      **termine con éxito** indicando que no hay release cuando no proceda (sin fallar).
- [ ] 1.3.3 `actionlint` sobre `release-prepare.yml` (si está disponible) sin hallazgos. Pegar salida.

### 1.4 REFACTOR

- [ ] 1.4.1 Revisar el script (cabecera, `set -euo pipefail`, portabilidad, `shellcheck` si está).
- [ ] 1.4.2 Re-ejecutar la suite → verde sin regresiones. Pegar salida.

## 2. Despliegue quirúrgico (`prod-deploy`)

### 2.1 RED — suite del script

- [ ] 2.1.1 Crear `scripts/deploy-to-prod.test.sh` con dobles de `ssh`/`docker`/`sha256sum` y los casos:
      (a) despliegue correcto fuerza `82:82` y sale 0; (b) hash discrepante aborta ≠0;
      (c) propiedad final ≠ `82:82` aborta ≠0; (d) se crea copia de seguridad con marca de tiempo.
- [ ] 2.1.2 Ejecutar `bash scripts/deploy-to-prod.test.sh` y pegar el fallo literal
      (el script aún no existe).

### 2.2 GREEN — script de despliegue

- [ ] 2.2.1 Crear `scripts/deploy-to-prod.sh`: parametriza host + lista de ficheros; `docker cp`;
      `chown 82:82` y `chmod` ejecutados en el contenedor de WP-CLI (uid `82`); verificación de
      `sha256` origen/destino; verificación de propiedad final `82:82`; copia previa con marca de tiempo;
      `set -euo pipefail`; aborta ≠0 con mensaje accionable ante cualquier discrepancia.
- [ ] 2.2.2 Ejecutar la suite → **100% verde**. Pegar salida literal.

### 2.3 Receta `just` y documentación

- [ ] 2.3.1 Añadir receta `deploy-prod` al `.justfile` que invoque `scripts/deploy-to-prod.sh`.
- [ ] 2.3.2 Actualizar `PLAN.md` §2.5 y `GIT_FLOW.md`/`docs/` para referenciar el script en lugar del
      procedimiento manual (`docker cp` + `chown`).

### 2.4 REFACTOR

- [ ] 2.4.1 Revisar el script (idempotencia, mensajes, portabilidad); `shellcheck` si está disponible.
- [ ] 2.4.2 Re-ejecutar la suite → verde sin regresiones. Pegar salida.

## 3. Verificación final y cierre

- [ ] 3.1 `bash .github/scripts/release-needed.test.sh` y `bash scripts/deploy-to-prod.test.sh` verdes.
- [ ] 3.2 Revisar contra `PLAN.md` y los objetivos del `proposal.md`.
- [ ] 3.3 `openspec validate release-deploy-hardening --strict` sin errores. Pegar salida.
- [ ] 3.4 Land por PRs según gitflow (nunca commit directo a `main`/`development`).
- [ ] 3.5 `openspec archive release-deploy-hardening` (headers del delta EXACTOS a la spec destino).
