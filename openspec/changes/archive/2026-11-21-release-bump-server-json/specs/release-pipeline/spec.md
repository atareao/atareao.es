# release-pipeline Delta

## ADDED Requirements

### Requirement: Coherencia de versión del manifiesto MCP en el bump de release

El pipeline de preparación de release SHALL actualizar la `version` de `server.json` junto con la del
plugin y la del tema, de modo que el manifiesto del registro MCP no pueda quedar en una versión
anterior tras un release. La actualización SHALL realizarse desde un **único punto de configuración**
(`vampus` / `.vampus.yml`), SHALL NOT requerir un paso manual posterior y SHALL preservar `server.json`
como un objeto JSON válido.

#### Scenario: Tras el bump, las tres versiones coinciden

- **WHEN** `release-prepare.yml` prepara un release
- **THEN** la `version` de `server.json`, la del header del plugin y la del tema son iguales

#### Scenario: El arnés de coherencia queda verde tras el bump

- **WHEN** se ejecuta `tests/test-valet.php` sobre un `development` limpio tras el bump
- **THEN** la aserción `(reg) version coincide con la del plugin` pasa

#### Scenario: La configuración del bump cubre el manifiesto

- **WHEN** se inspecciona `.vampus.yml`
- **THEN** declara una entrada `replaces` cuyo `file` es `server.json` y cuya `pattern` cubre su `version`

### Requirement: El arnés de valet se ejecuta en CI

El workflow `ci.yml` SHALL ejecutar `tests/test-valet.php` en el paso de harnesses PHP, de modo que la
coherencia de versión del manifiesto se verifique en cada PR. La incorporación SHALL hacerse efectiva
una vez el arnés pasa en `development`, y SHALL NOT relajar ninguna aserción existente.

#### Scenario: CI ejecuta el arnés de valet

- **WHEN** se abre un PR
- **THEN** el workflow `ci.yml` ejecuta `tests/test-valet.php` y falla si la aserción de versión no se cumple
