# prod-deploy Delta

## ADDED Requirements

### Requirement: Despliegue quirúrgico reproducible con propiedad e integridad verificadas

El repositorio SHALL proveer un script (`scripts/deploy-to-prod.sh`, invocable también mediante una receta `just`) que encapsule el despliegue quirúrgico de un conjunto acotado de ficheros al volumen de producción, de modo que el procedimiento deje de depender de que una persona recuerde los pasos manuales. El script SHALL aceptar como entradas el destino remoto (`host`) y la lista de ficheros a copiar, y SHALL ejecutarse de forma que, ante cualquier discrepancia, **falle** en lugar de dar por bueno un despliegue inconsistente.

El script SHALL garantizar, para cada fichero desplegado, que su **propiedad final** en el volumen es `82:82` (uid/gid con el que corre WP-CLI) y SHALL aplicar los modos previstos, ejecutando el `chown`/`chmod` explícitamente como parte del propio script y no como paso manual separado. El script SHALL verificar la **integridad** de cada fichero comparando su `sha256` origen/destino y SHALL verificar que la propiedad final es la esperada; si una comprobación falla, SHALL abortar con código de salida distinto de cero y un mensaje accionable. El script SHALL crear una copia de seguridad con marca de tiempo de los ficheros que sobrescribe antes de reemplazarlos, y SHALL usar el contenedor de WP-CLI (uid `82`) como contexto de ejecución del `chown`/`chmod`, de forma que no pueda reintroducirse la propiedad `1000:1000` filtrada por `docker cp`.

El script SHALL ser verificable sin tocar producción, mediante dobles (stub) de `ssh`/`docker`/`sha256sum` que permitan comprobar en local: (a) que se fuerza `82:82`, (b) que se detecta y aborta ante un hash discrepante y (c) que se detecta y aborta ante una propiedad final incorrecta.

#### Scenario: Despliegue correcto fuerza la propiedad 82:82

- **WHEN** se despliega un fichero cuyo contenido es correcto y cuyo `chown 82:82` tiene éxito
- **THEN** el script verifica el `sha256` y la propiedad final `82:82`, termina con código de salida 0 y deja el fichero con propietario `82:82`

#### Scenario: Hash discrepante aborta

- **WHEN** el `sha256` del fichero copiado no coincide con el del origen
- **THEN** el script aborta con código distinto de cero y un mensaje que indica qué fichero difiere

#### Scenario: Propiedad final incorrecta aborta

- **WHEN** tras el `chown` el fichero queda con una propiedad distinta de `82:82`
- **THEN** el script aborta con código distinto de cero en lugar de dar el despliegue por bueno

#### Scenario: Copia de seguridad antes de sobrescribir

- **WHEN** se despliega un fichero que ya existe en producción
- **THEN** el script guarda una copia de seguridad con marca de tiempo del fichero previo antes de reemplazarlo

#### Scenario: La propiedad 1000:1000 no puede reintroducirse

- **WHEN** el script completa un despliegue
- **THEN** ninguno de los ficheros desplegados queda con uid `1000` y todos quedan con `82:82`

#### Scenario: Suite del script ejecutada en CI

- **WHEN** se abre un PR que modifica el script de despliegue o su suite
- **THEN** la suite del script se ejecuta en CI sin tocar producción (con dobles) y falla si el forzado de propiedad, la verificación de hash o la de propiedad final se degradan
