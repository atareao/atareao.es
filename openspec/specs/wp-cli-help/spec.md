# wp-cli-help Specification

## Purpose
Contrato de que la interfaz (argumentos posicionales y flags, en particular `--dry-run`) de los
comandos WP-CLI que el plugin `atareao-functionality` registra con `WP_CLI::add_command` es visible en
`wp <comando> --help`, declarándola en un `@synopsis` en el docblock del callback y manteniéndola bajo
una guardia verificada.

## Requirements

### Requirement: Interfaz visible de los comandos WP-CLI

Cada callback que el plugin registra con `WP_CLI::add_command` y que **acepta argumentos o flags**
SHALL declarar en su docblock un `@synopsis` que enumere los argumentos posicionales y los flags que el
callback lee, de modo que `wp <comando> --help` los muestre. Un comando sin argumentos ni flags SHALL
NOT requerir `@synopsis`.

El `@synopsis` SHALL cubrir **exactamente** los flags que el callback lee en su cuerpo: SHALL NOT omitir
ninguno (en particular `--dry-run` donde exista) ni anunciar flags que no lee.

El proyecto SHALL mantener una **lista verificada** `comando → callback → synopsis` y una guardia que
falle si un callback registrado carece del `@synopsis` esperado, si su synopsis omite un flag que el
callback lee o si anuncia uno que no lee.

#### Scenario: La ayuda muestra los flags reales

- **WHEN** se ejecuta `wp <comando> --help` de un comando con flags
- **THEN** la salida lista esos flags (incluido `--dry-run` donde aplique)

#### Scenario: Un callback sin el `@synopsis` esperado rompe la guardia

- **WHEN** un callback registrado no declara el `@synopsis` de la lista verificada
- **THEN** la guardia falla

#### Scenario: El synopsis y los flags leídos coinciden

- **WHEN** se comparan los flags del `@synopsis` con los que el callback lee en su cuerpo
- **THEN** coinciden: ni falta uno leído ni sobra uno no leído

#### Scenario: Un comando sin argumentos no exige synopsis

- **WHEN** un callback no lee ningún flag ni argumento posicional
- **THEN** no se le exige `@synopsis` y la guardia no falla
