# release-pipeline Specification

## Purpose
Capacidad de infraestructura que gobierna la preparación de releases y la sincronización de ramas en GitHub Actions. Define que el pipeline valide sus credenciales (`GH_PAT`) antes de operar sobre el repositorio y que todo fallo de credenciales sea rápido, accionable y sin filtrar el secreto. No cubre la lógica de versionado ni la publicación de la release, que residen en otros workflows.

## Requirements

### Requirement: Validación de las credenciales del pipeline antes de operar sobre el repositorio

Antes de ejecutar el checkout, cada job del pipeline de preparación de release SHALL validar sus credenciales consultando la API de GitHub sobre el repositorio actual (`GET /repos/{owner}/{repo}`). El pipeline SHALL usar `GH_PAT` como credencial obligatoria, sin fallback a `GITHUB_TOKEN`, y SHALL abortar el job cuando el secreto no esté definido o no permita operar sobre el repositorio. Cuando la validación devuelva HTTP 200, el job SHALL continuar con su comportamiento habitual. El pipeline SHALL preservar `GH_PAT` como la única credencial de escritura y SHALL NOT sustituirla, reducir su ámbito ni modificar sus permisos: la validación, la publicación de releases, la creación de PRs y la sincronización de ramas SHALL seguir realizándose con esa misma credencial y sus permisos actuales (`contents: write` y `pull-requests: write`). El endurecimiento de la cadena de suministro de este cambio SHALL NOT alterar el uso ni el ámbito de `GH_PAT`.

#### Scenario: GH_PAT no definido

- **WHEN** el secreto `GH_PAT` no está definido (se expande a cadena vacía)
- **THEN** el job falla antes del checkout con un mensaje que indica crear un PAT con `Contents: read/write` y `Pull requests: read/write` y guardarlo con `gh secret set GH_PAT`

#### Scenario: Token inválido o revocado

- **WHEN** la API de GitHub responde HTTP 401 a la comprobación de credenciales
- **THEN** el job falla con un mensaje que distingue "token inválido o revocado" y no continúa con el checkout

#### Scenario: Token sin acceso al repositorio

- **WHEN** la API de GitHub responde HTTP 404 a la comprobación de credenciales
- **THEN** el job falla con un mensaje que distingue que el token no tiene acceso al repositorio y no continúa con el checkout

#### Scenario: Token válido

- **WHEN** la API de GitHub responde HTTP 200 a la comprobación de credenciales
- **THEN** el job continúa con su operación normal (bump, changelog, tag/PR o sincronización) sin cambios de comportamiento

#### Scenario: Los permisos del token se conservan

- **WHEN** se ejecuta el pipeline tras aplicar el fijado inmutable de referencias
- **THEN** sigue usando `GH_PAT` con `contents: write` y `pull-requests: write`, y su ámbito y permisos no se han recortado ni ampliado

### Requirement: Los fallos del pipeline son accionables y no filtran el secreto

Todo fallo de validación de credenciales SHALL ser accionable: el mensaje SHALL indicar qué credencial falló y la remediación concreta. Los logs del pipeline SHALL NOT contener el valor del secreto `GH_PAT` en ningún caso (ni en el mensaje de error, ni en la comprobación, ni en el paso `run:`); SHALL exponerse únicamente el código HTTP de la respuesta y la presencia o ausencia del secreto.

#### Scenario: Mensaje con remediación

- **WHEN** un paso de validación de credenciales falla
- **THEN** el mensaje de error incluye la remediación concreta (comando `gh secret set GH_PAT` y los permisos requeridos del PAT)

#### Scenario: El log nunca expone el token

- **WHEN** el secreto está definido y el paso de validación se ejecuta (con éxito o con fallo)
- **THEN** la salida del paso muestra solo el código HTTP y nunca el valor del token

### Requirement: Determinación determinista del tipo de bump

El pipeline de preparación de release SHALL determinar el tipo de bump (`major`, `minor` o `patch`) de forma determinista a partir de los asuntos de los **commits reales** entre el último tag y `HEAD`, excluyendo los commits de merge (p. ej. `git log --no-merges`), de modo que el nombre de rama de un merge no pueda alterar el resultado. La clasificación SHALL delegarse en un script del repositorio que lea los asuntos por entrada estándar y emita exactamente `major`, `minor` o `patch` por salida estándar; el workflow SHALL invocar ese script en lugar de contener la lógica de clasificación. Los patrones de detección SHALL estar anclados al prefijo del asunto y ser compatibles con ERE POSIX (`grep -E`), sin `\w` ni otras construcciones PCRE. La severidad SHALL aplicarse con precedencia `major` > `minor` > `patch` sobre el conjunto completo de asuntos. SHALL considerarse `minor` únicamente un asunto cuyo tipo sea `feat` de forma real (no una subcadena como `feature`), `major` los asuntos que declaren un cambio incompatible y `patch` el resto. Cuando no exista ningún tag previo, el pipeline SHALL conservar el comportamiento vigente (usar el primer commit del historial como base).

#### Scenario: Commit `feat` real

- **WHEN** el rango contiene un commit con asunto `✨ feat(pocketid): login resilience` o `feat: add thing`
- **THEN** el tipo de bump es `minor`

#### Scenario: Commits de mantenimiento

- **WHEN** el rango solo contiene commits con asuntos de tipo `fix`, `docs`, `ci`, `chore`, `style` o `refactor`
- **THEN** el tipo de bump es `patch`

#### Scenario: Marcador de cambio incompatible con `!`

- **WHEN** un commit tiene un asunto como `feat!: drop old`, `feat(api)!: drop old` o `fix(scope)!: x`
- **THEN** el tipo de bump es `major`

#### Scenario: Marcador de cambio incompatible con `BREAKING CHANGE` o `💥`

- **WHEN** un commit tiene un asunto como `BREAKING CHANGE: x`, `BREAKING-CHANGE: x` o `💥 rework`
- **THEN** el tipo de bump es `major`

#### Scenario: Asunto de merge con nombre de rama `feature/`

- **WHEN** el asunto de un commit de merge es `Merge pull request #51 from atareao/feature/ci-release-token-validation` (o cualquier otro que mencione una rama `feature/...`)
- **THEN** ese asunto no influye en el resultado y el tipo de bump es el mismo que el de los commits reales de su rango, es decir `patch` si no hay ningún `feat` real

#### Scenario: La palabra `feature` fuera del prefijo

- **WHEN** un asunto contiene la palabra `feature` pero no empieza por el tipo `feat`, por ejemplo `docs: describe the feature`
- **THEN** el tipo de bump es `patch`

#### Scenario: Texto anecdótico con `!:`

- **WHEN** un asunto contiene `!:` en medio del texto sin ser un marcador de ruptura, por ejemplo `docs: explain !: syntax`
- **THEN** el tipo de bump es `patch`

#### Scenario: Sin tag previo

- **WHEN** no existe ningún tag anterior en el historial
- **THEN** el pipeline usa el primer commit del historial como base y clasifica los asuntos desde ahí, conservando el comportamiento vigente

#### Scenario: Rango de release con muchos asuntos

- **WHEN** el conjunto de asuntos es grande (miles de líneas) y el asunto significativo (`feat` o un marcador de ruptura) está al principio del listado
- **THEN** el tipo detectado es el esperado (`minor` o `major`) y no `patch`

#### Scenario: Suite de clasificación ejecutada en CI

- **WHEN** se abre un PR contra `main` o `development`
- **THEN** la suite del clasificador se ejecuta y falla si la detección se degrada o si el pipeline pierde la exclusión de merges

### Requirement: Referencias inmutables de acciones de terceros

Todas las acciones de terceros usadas en los workflows de GitHub Actions del repositorio (`ci.yml`, `release.yml` y `release-prepare.yml`) SHALL fijarse a un **SHA de commit completo** (40 caracteres hexadecimales) de forma inmutable. Cada referencia SHALL acompañarse de un comentario con la versión legible correspondiente (por ejemplo `uses: actions/checkout@<sha> # v4.2.2`). El pipeline SHALL NOT referenciar acciones de terceros mediante tags ni ramas móviles (p. ej. `@v4`, `@v2`, `@main`). Como mínimo SHALL quedar fijadas `actions/checkout`, `shivammathur/setup-php`, `taiki-e/install-action`, `swatinem/rust-cache` y `softprops/action-gh-release`. El fijado SHALL NOT cambiar el comportamiento, los parámetros `with:` de cada paso ni los bloques `permissions:` declarados por el workflow.

#### Scenario: Toda acción de terceros está fijada a un SHA

- **WHEN** se revisa cada `uses:` de `ci.yml`, `release.yml` y `release-prepare.yml`
- **THEN** todas las acciones de terceros usan un SHA de 40 caracteres hexadecimales con un comentario de versión, y ninguna usa un tag o una rama móvil

#### Scenario: El fijado no altera el comportamiento

- **WHEN** se compara cada workflow antes y después del fijado
- **THEN** los eventos `on:`, el orden de los pasos, los `with:` y los bloques `permissions:` permanecen idénticos

#### Scenario: Una versión nueva upstream no cambia el pipeline solo

- **WHEN** una acción upstream publica una versión nueva moviendo su tag mayor
- **THEN** el pipeline sigue usando el SHA fijado hasta que una persona actualice la referencia de forma explícita

### Requirement: Referencias inmutables de herramientas instaladas

Las herramientas instaladas durante el pipeline SHALL referenciarse a una revisión inmutable. En particular, `cargo install vampus` SHALL fijarse a un **tag o revisión concreta** (`--tag <vX.Y.Z>` o `--rev <sha>`) del repositorio `https://github.com/atareao/vampus`, y SHALL NOT instalar el HEAD móvil (la forma `--git <url>` sin `--tag`/`--rev` queda prohibida). La versión o revisión fijada SHALL quedar documentada junto al paso y SHALL poder reproducirse. Las herramientas instaladas a través de una acción de terceros (p. ej. `git-cliff` vía `taiki-e/install-action`) SHALL heredar el fijado inmutable de la acción que las instala.

#### Scenario: `vampus` se instala desde una revisión concreta

- **WHEN** se revisa el paso de instalación de `vampus` en `release-prepare.yml`
- **THEN** usa `--tag` o `--rev` con una revisión concreta y no instala el HEAD móvil del repositorio

#### Scenario: Instalación reproducible

- **WHEN** se instala la misma revisión fijada de `vampus` dos veces
- **THEN** se obtiene la misma versión de la herramienta

#### Scenario: Actualización explícita de `vampus`

- **WHEN** se decide actualizar la revisión fijada de `vampus`
- **THEN** el cambio es explícito y actualiza en el mismo paso la versión o revisión documentada

### Requirement: Política de actualización de las referencias fijadas

El repositorio SHALL documentar la política para actualizar las referencias inmutables de CI: dónde se anotan las versiones legibles, cómo se obtiene el SHA de commit de una versión, qué verificación se exige antes de fusionar (revisión del YAML y `actionlint` cuando esté disponible) y que la actualización de una referencia SHALL ser explícita y revisable. La política SHALL NOT exigir herramientas de build ni framework de tests.

#### Scenario: Guía para actualizar una referencia

- **WHEN** una persona necesita actualizar una acción o herramienta fijada
- **THEN** la política documentada indica cómo obtener el nuevo SHA (o la nueva revisión de `vampus`) y dónde anotar la versión legible

#### Scenario: Actualización revisable

- **WHEN** se propone cambiar una referencia fijada
- **THEN** el cambio es explícito, revisable y supera la revisión del YAML y `actionlint` cuando está disponible

### Requirement: Preservación del flujo de release y publicación

El fijado inmutable de referencias SHALL NOT alterar el flujo de release ni de publicación. Los workflows SHALL seguir disparándose en los mismos eventos, ejecutando los mismos pasos, usando `GH_PAT` con sus permisos actuales (`contents: write` y `pull-requests: write`) y publicando los mismos artefactos (`atareao-theme.zip` y `atareao-functionality.zip`) y los mismos PRs (`release/v{X.Y.Z} -> main` y la sincronización `main -> development`). Ningún permiso del token SHALL reducirse, modificarse ni sustituirse.

#### Scenario: Preparación de release sin cambios

- **WHEN** se ejecuta `release-prepare.yml` después del fijado de referencias
- **THEN** valida el `GH_PAT`, crea la rama/tag/PR y dispara `release.yml` igual que antes del cambio

#### Scenario: Publicación de release sin cambios

- **WHEN** se publica un tag `v*` después del fijado de referencias
- **THEN** `release.yml` construye y publica `atareao-theme.zip` y `atareao-functionality.zip` igual que antes del cambio

#### Scenario: Sin cambios en eventos ni permisos

- **WHEN** se revisa el diff de los tres workflows
- **THEN** no hay cambios en los eventos `on:`, en los bloques `permissions:` ni en el uso de `secrets.GH_PAT`
