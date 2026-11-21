# podcast-transcripts Delta

## ADDED Requirements

### Requirement: Prompt de vocabulario opt-in para el reconocimiento

El proceso SHALL aceptar un **prompt de vocabulario** opcional (`--initial-prompt`) para sesgar el
reconocimiento hacia la terminología del programa (nombres de herramientas y marcas). Cuando se
facilita, el proceso SHALL pasarlo al reconocedor como `--initial_prompt`. Cuando **no** se
facilita, el proceso SHALL invocar al reconocedor **sin** `--initial_prompt`, manteniendo el
comportamiento actual. El prompt SHALL ser **texto** que viaja en la línea de órdenes y SHALL NOT
alterar el audio, el preprocesado, la detección de audio sin voz ni el contrato del SRT/adjunto.

#### Scenario: Sin prompt, el reconocedor se invoca como antes

- **WHEN** el proceso transcribe y no se facilitó `--initial-prompt`
- **THEN** la invocación al reconocedor no incluye `--initial_prompt`

#### Scenario: Con prompt, se pasa al reconocedor

- **WHEN** el proceso transcribe con `--initial-prompt "…"`
- **THEN** la invocación al reconocedor incluye `--initial_prompt` con ese texto

#### Scenario: El prompt no altera el preprocesado ni el contrato

- **WHEN** se usa `--initial-prompt`
- **THEN** el audio entregado sigue siendo la copia normalizada y el SRT resultante se sanea y valida igual que sin prompt
