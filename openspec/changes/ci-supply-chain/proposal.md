# Proposal: Cadena de suministro de CI/CD (referencias inmutables)

## Why

El frente INFRA/CI-CD de la auditoría (2026-10-03) registra el hallazgo **GE-06**: los tres workflows de GitHub Actions referencian acciones de terceros con **tags móviles** y una herramienta se instala sobre un **HEAD móvil**.

- `ci.yml:11,14` usa `actions/checkout@v4` y `shivammathur/setup-php@v2`.
- `release.yml:16,31` usa `actions/checkout@v4` y `softprops/action-gh-release@v2`.
- `release-prepare.yml:34,41,46,49` usa `actions/checkout@v4`, `taiki-e/install-action@v2`, `swatinem/rust-cache@v2` y ejecuta `cargo install vampus --git https://github.com/atareao/vampus` **sin `--tag` ni `--rev`**, por lo que compila el HEAD móvil de ese repositorio.

Un tag como `@v4` o `@v2` es una referencia mutable: el mantenedor (o un atacante que comprometa su cuenta o repositorio) puede repuntarlo a un commit distinto en cualquier momento. Si eso ocurre, el runner de GitHub ejecuta código arbitrario de terceros. En `release-prepare.yml` ese código corre con `GH_PAT` (permisos `contents: write` + `pull-requests: write`), de modo que un compromiso de la cadena de suministro puede hacer push, forzar ramas y publicar releases envenenadas. La explotabilidad está limitada porque este workflow solo se dispara en push a `main` y en `workflow_dispatch` (no en PRs de terceros), pero el endurecimiento es una deuda clara de trazabilidad y reproducibilidad.

**Alcance estricto (corrección del responsable):** este cambio **solo** fija referencias a valores inmutables. **No** se revisan, reducen ni modifican los permisos del `GH_PAT`, ni se altera el flujo de publicación de releases/PRs. El usuario publica directamente con ese PAT y debe seguir valiendo exactamente como hoy (`contents: write` + `pull-requests: write`). La preservación de esa capacidad es un requisito explícito de este change, no un hallazgo a corregir.

## What Changes

- **Acciones de terceros fijadas a SHA.** Cada `uses:` de terceros en `ci.yml`, `release.yml` y `release-prepare.yml` SHALL apuntar a un **SHA de commit completo de 40 caracteres hexadecimales**, acompañado de un comentario con la versión legible (por ejemplo `uses: actions/checkout@<sha> # v4.2.2`). Quedan incluidos, como mínimo, `actions/checkout`, `shivammathur/setup-php`, `taiki-e/install-action`, `swatinem/rust-cache` y `softprops/action-gh-release`. El fijado **no** cambia el comportamiento, los `with:` ni los permisos declarados.
- **`vampus` fijado a una revisión concreta.** `cargo install vampus --git https://github.com/atareao/vampus` SHALL fijarse a un tag o revisión concreta (`--tag <vX.Y.Z>` o `--rev <sha>`). Se prohíbe la forma con HEAD móvil. La versión/revisión SHALL quedar documentada junto al paso.
- **Política de actualización documentada.** El repositorio SHALL documentar cómo actualizar las referencias fijadas (obtención del SHA, anotación de la versión, verificación con revisión de YAML y `actionlint` cuando esté disponible) y que toda actualización es explícita y revisable. Sin herramientas de build ni framework de tests.
- **Preservación total del flujo de release y publicación.** Los eventos (`on:`), los pasos, los bloques `permissions:`, el uso de `GH_PAT` con sus permisos actuales, la creación de PRs (`release/v{X.Y.Z} -> main` y sincronización `main -> development`) y la publicación de los zips (`atareao-theme.zip` y `atareao-functionality.zip`) **no cambian**. Ningún permiso del token se reduce ni se modifica.
- **Sin cambios funcionales.** Cuando las referencias fijadas apunten a la misma versión que hoy, el pipeline opera exactamente igual.

## Capabilities

### New Capabilities

Ninguna. El endurecimiento se incorpora a una capability ya existente.

### Modified Capabilities

- `release-pipeline`: se le añaden las garantías de **referencias inmutables** para acciones de terceros y herramientas instaladas (SHA de commit / tag o revisión concreta), la **política de actualización** de esas referencias y un requisito explícito de **preservación del flujo de release y publicación** (incluido `GH_PAT` con sus permisos actuales). Se modifica el requisito de credenciales para dejar escrito que el ámbito y los permisos del `GH_PAT` se conservan intactos.

## Impact

- **Archivos a modificar (solo en la fase de implementación, tras aprobación):**
  - `.github/workflows/ci.yml` (fijar `actions/checkout` y `shivammathur/setup-php` a SHA).
  - `.github/workflows/release.yml` (fijar `actions/checkout` y `softprops/action-gh-release` a SHA).
  - `.github/workflows/release-prepare.yml` (fijar `actions/checkout`, `taiki-e/install-action` y `swatinem/rust-cache` a SHA; fijar `cargo install vampus` a tag/revisión).
  - Documentación de la política de actualización (nota/README de CI, a definir en implementación).
- **Lo que NO se toca:** los eventos `on:`, los bloques `permissions:`, el uso de `secrets.GH_PAT`, la validación del PAT, la lógica de bump/changelog/tag/PR, la construcción de los zips y la publicación del release. Ningún permiso del token se reduce ni se modifica.
- **Dependencias:** ninguna nueva. `actionlint` es opcional (se usa solo si está disponible en el entorno de verificación).
- **Riesgo operativo:** un SHA o una revisión de `vampus` incorrectos romperían el workflow. Mitigación: comentario de versión junto a cada SHA, resolución del SHA a partir del tag vigente, `actionlint`/parseo de YAML y observación de un PR real y del siguiente release.
- **Rollback:** revertir los YAML al estado previo (los tags móviles/HEAD vuelven a funcionar).
- **Verificación:** no hay build tools ni framework de tests. La verificación combina revisión de YAML, `actionlint` si está disponible, `rg` de que no quedan referencias móviles, un PR real (CI) y el siguiente release real (publicación E2E).
