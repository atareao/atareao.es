# Proposal: Endurecer el ciclo release→despliegue

## Why

**Dos fallos medidos el 2026-11-02, ambos de proceso (no de aplicación).**

1. **Un merge a `main` solo con documentación cortó un release.** El PR #160 (`development → main`)
   llevaba un único commit real — `📝 docs(plan): cierra el release v1.20.0 instalado en producción` —
   y aun así `release-prepare.yml` bump-eó (`docs ⇒ patch`), creó el tag **v1.20.1** y publicó una
   release de GitHub sin ningún cambio funcional (assets `atareao-functionality.zip` / `atareao-theme.zip`).
   Evidencia: releases `v1.20.1` y `v1.20.0` con diferencia de contenido nula salvo `PLAN.md`.

2. **El «despliegue quirúrgico» a producción dejó ficheros con uid `1000`.** El procedimiento
   (`docker cp` + `chown www-data:www-data`) es manual y **no está encapsulado en ningún script**: solo
   existe como prosa en `PLAN.md` y en `openspec/changes/archive/...`. Al reinstalar el plugin oficial
   con WP-CLI, la instalación falló con `chmod(): Operation not permitted` porque tres ficheros del
   volumen (`atareao-functionality.php`, `includes/class-metaboxes.php`, `includes/class-srt-upload.php`)
   eran de `1000:1000` (el uid del host, filtrado por `docker cp`) y `wpcli` corre como uid `82`. La
   normalización hubo de hacerse a mano (`chown -R 82:82`). El error es **repetible** mientras el `chown`
   no esté garantizado por código.

Consecuencia: el pipeline infla el versionado por ruido de documentación y el despliegue depende de que
una persona recuerde el `chown`; ninguno de los dos está verificado en CLI.

## What Changes

- **Puerta de release.** `release-prepare.yml` gana un paso previo que, sobre el rango entre el último
  tag y `HEAD` (commits reales, sin merges), decide si hay algo **releasable**. Si el rango solo tiene
  `docs`/`chore`/`ci` —o está vacío— el pipeline **no** bump-ea, ni taggea, ni publica, ni abre PR de
  release; termina con éxito informando de que no hay release. La comprobación se delega en un script
  determinista y verificable en local.
- **Despliegue quirúrgico reproducible.** Script `scripts/deploy-to-prod.sh` (con receta `just`) que:
  copia los ficheros al volumen de producción, **fuerza** la propiedad `82:82` y el modo, verifica el
  `sha256` de cada fichero y **afirma** que la propiedad final es `82:82`; si algo no cuadra, **falla**
  en vez de dar por bueno un despliegue inconsistente. Deja una copia de seguridad con marca de tiempo.

## Capabilities

### New Capabilities

- `prod-deploy`: despliegue quirúrgico reproducible, con propiedad normalizada y verificación de integridad.

### Modified Capabilities

- `release-pipeline`: el pipeline no corta release cuando el rango no aporta cambios funcionales.

## Impact

- **Archivos**: `.github/workflows/release-prepare.yml`, `.github/scripts/release-needed.sh` +
  `.github/scripts/release-needed.test.sh`, `scripts/deploy-to-prod.sh` + `scripts/deploy-to-prod.test.sh`,
  `.justfile` (receta `deploy-prod`), y documentación (`GIT_FLOW.md` / `docs/`).
- **Dependencias**: ninguna nueva; `bash`, `grep -E`, `docker`/`ssh` (con dobles en las pruebas).
- **Compatibilidad**: no cambia `release.yml`, ni `bump-type.sh`, ni `vampus`/`git-cliff`. La
  clasificación de bump es la de siempre; lo nuevo es la *decisión de publicar*.
- **Riesgo operativo**: (a) una puerta mal calibrada podría **omitir** un release legítimo — mitigado con
  la tabla de casos (un `fix` o un `feat` siempre publican); rollback = revertir el workflow.
  (b) El script de despliegue toca producción; mitigado con dobles en las pruebas y con verificación de
  hash/propiedad que aborta ante cualquier discrepancia.
- **Verificación**: `bash .github/scripts/release-needed.test.sh` y `bash scripts/deploy-to-prod.test.sh`
  (código de salida distinto de 0 si falla un caso), más el bump observado en el próximo release real.

## Non-goals

- No cambia la clasificación `major|minor|patch` (`bump-type.sh`) ni el flujo de publicación de
  `release.yml`.
- No automatiza el despliegue continuo: el script se invoca a mano y no despliega nada por sí solo.
- No re-etiqueta adjuntos, no toca el contrato del feed ni la aplicación.
