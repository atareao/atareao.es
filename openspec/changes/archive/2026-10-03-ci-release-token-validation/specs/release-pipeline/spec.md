# Release Pipeline Specification

## Purpose

Capacidad de infraestructura que gobierna la preparación de releases y la sincronización de ramas en GitHub Actions. Define que el pipeline valide sus credenciales (`GH_PAT`) antes de operar sobre el repositorio y que todo fallo de credenciales sea rápido, accionable y sin filtrar el secreto. No cubre la lógica de versionado ni la publicación de la release, que residen en otros workflows.

## ADDED Requirements

### Requirement: Validación de las credenciales del pipeline antes de operar sobre el repositorio

Antes de ejecutar `actions/checkout@v4`, cada job del pipeline de preparación de release SHALL validar sus credenciales consultando la API de GitHub sobre el repositorio actual (`GET /repos/{owner}/{repo}`). El pipeline SHALL usar `GH_PAT` como credencial obligatoria, sin fallback a `GITHUB_TOKEN`, y SHALL abortar el job cuando el secreto no esté definido o no permita operar sobre el repositorio. Cuando la validación devuelva HTTP 200, el job SHALL continuar con su comportamiento habitual.

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

### Requirement: Los fallos del pipeline son accionables y no filtran el secreto

Todo fallo de validación de credenciales SHALL ser accionable: el mensaje SHALL indicar qué credencial falló y la remediación concreta. Los logs del pipeline SHALL NOT contener el valor del secreto `GH_PAT` en ningún caso (ni en el mensaje de error, ni en la comprobación, ni en el paso `run:`); SHALL exponerse únicamente el código HTTP de la respuesta y la presencia o ausencia del secreto.

#### Scenario: Mensaje con remediación

- **WHEN** un paso de validación de credenciales falla
- **THEN** el mensaje de error incluye la remediación concreta (comando `gh secret set GH_PAT` y los permisos requeridos del PAT)

#### Scenario: El log nunca expone el token

- **WHEN** el secreto está definido y el paso de validación se ejecuta (con éxito o con fallo)
- **THEN** la salida del paso muestra solo el código HTTP y nunca el valor del token
