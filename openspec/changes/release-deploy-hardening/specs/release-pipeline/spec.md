# release-pipeline Delta

## ADDED Requirements

### Requirement: El pipeline no corta release sin cambios funcionales

Antes de bump-ear versión, crear el tag, publicar la release o abrir el PR de release, `release-prepare.yml` SHALL decidir si el rango entre el último tag y `HEAD` aporta algún cambio **releasable**. La decisión SHALL delegarse en un script del repositorio que lea los asuntos de los commits reales (sin merges, p. ej. `git log --no-merges`) por entrada estándar y emita un veredicto por salida estándar, de modo que el workflow no contenga la lógica de la puerta y ésta sea verificable en local.

SHALL considerarse **releasable** todo rango que contenga al menos un asunto cuyo tipo sea `feat`, `fix`, `perf`, `refactor`, `revert`, `hotfix`, `deps` o un cambio incompatible (`major`). SHALL considerarse **no releasable** todo rango cuyos únicos asuntos sean de tipo `docs`, `chore`, `ci`, `test` o `style`, así como un rango **vacío** (ningún commit real). Cuando el rango no sea releasable, el job SHALL NOT bump-ear, ni crear tag, ni publicar release, ni abrir PR de release, y SHALL terminar **con éxito** dejando constancia de que no hay release que preparar. Cuando el rango sí sea releasable, el pipeline SHALL continuar con su comportamiento habitual de bump/tag/publicación.

La puerta SHALL evaluarse de forma determinista y anclada al prefijo del asunto, con ERE POSIX (`grep -E`), sin `\w` ni construcciones PCRE, y el nombre de rama de un merge SHALL NOT influir en el veredicto.

#### Scenario: Rango solo con documentación

- **WHEN** el rango entre el último tag y `HEAD` solo contiene asuntos de tipo `docs` (por ejemplo `📝 docs(plan): cierra el release ...`)
- **THEN** el pipeline no bump-ea, no taggea, no publica y no abre PR de release, y el job termina con éxito indicando que no hay release

#### Scenario: Rango vacío

- **WHEN** el rango entre el último tag y `HEAD` no contiene ningún commit real
- **THEN** el pipeline no bump-ea, no taggea, no publica y no abre PR de release, y el job termina con éxito

#### Scenario: Rango solo con mantenimiento no funcional

- **WHEN** el rango solo contiene asuntos de tipo `chore`, `ci`, `test` o `style`
- **THEN** el pipeline se comporta como en el caso no releasable (no publica) y termina con éxito

#### Scenario: Un `fix` siempre publica

- **WHEN** el rango contiene al menos un asunto `🐛 fix(...)` junto a otros de tipo `docs`/`chore`
- **THEN** el pipeline continúa con el flujo habitual (bump `patch`, tag, release y PR)

#### Scenario: Un `feat` siempre publica

- **WHEN** el rango contiene al menos un asunto `✨ feat(...)`
- **THEN** el pipeline continúa con el flujo habitual (bump `minor`, tag, release y PR)

#### Scenario: Cambio incompatible siempre publica

- **WHEN** el rango contiene un asunto con marcador de ruptura (`feat!:`, `BREAKING CHANGE:` o `💥`)
- **THEN** el pipeline continúa con el flujo habitual (bump `major`)

#### Scenario: Un merge con nombre de rama `feature/` no dispara la puerta

- **WHEN** entre los asuntos del rango aparece `Merge pull request #N from atareao/feature/...` pero no hay ningún commit real de tipo `feat`/`fix`
- **THEN** ese asunto no cuenta como releasable y, con el resto solo de `docs`/`chore`, el pipeline no publica

#### Scenario: La palabra `feature` fuera del prefijo no dispara la puerta

- **WHEN** un asunto del rango es `docs: describe the feature`
- **THEN** no se cuenta como releasable

#### Scenario: Suite de la puerta ejecutada en CI

- **WHEN** se abre un PR contra `main` o `development`
- **THEN** la suite de la puerta se ejecuta y falla si la decisión releasable/no-releasable se degrada o si el pipeline pierde la exclusión de merges
