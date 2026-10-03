# Release Pipeline Delta

## ADDED Requirements

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

- **WHEN** un commit tiene un asunto como `BREAKING CHANGE: x` o `💥 rework`
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
