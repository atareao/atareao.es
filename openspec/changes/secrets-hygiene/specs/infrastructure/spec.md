# Infrastructure Delta

## MODIFIED Requirements

### Requirement: Verificación y rotación de credenciales expuestas

El change SHALL documentar la verificación y el plan de rotación de las credenciales que hayan podido estar expuestas: la **contraseña root de phpMyAdmin del entorno de desarrollo**, la **contraseña root de MariaDB** y el **secreto de purga** (`PURGE_SECRET`), que sí se despliega en producción. La verificación del bind de phpMyAdmin SHALL realizarse en el entorno de desarrollo con `ss -tlnp` y un `curl` desde la red local. La rotación de la contraseña root de phpMyAdmin SHALL ejecutarse en desarrollo. La rotación de la contraseña root de MariaDB y la rotación del secreto de purga SHALL ejecutarlas el usuario como **acción de despliegue**, en desarrollo y en producción, sin que el change modifique la configuración de producción. El change SHALL NOT incluir tareas ni escenarios de endurecimiento o verificación de phpMyAdmin en producción.

El change SHALL dejar constancia explícita de que los valores que hayan quedado en el **historial de git** (`atareao_purge_2026`, `root_password`) se consideran **comprometidos de forma permanente**, y que la mitigación es **rotar y aceptar**: SHALL NOT reescribir el historial de git (nada de BFG, `git filter-repo` ni `force-push`). El change SHALL verificar que el **árbol versionado (HEAD)** no contiene ningún secreto real: el literal `atareao_purge_2026` SHALL estar ausente de todos los ficheros bajo control de versiones, y `root_password`/`MYSQL_ROOT_PASSWORD` SHALL aparecer únicamente como nombre de secret o como texto de remediación, nunca como valor literal. La rotación de un secreto SHALL permitir invalidar el valor antiguo y activar el nuevo reiniciando los servicios que lo consumen, sin editar ficheros del repositorio.

**File:** `README.md`, `.justfile`, `openspec/specs/infrastructure/spec.md`

#### Scenario: Verificación del bind loopback de phpMyAdmin en desarrollo

- **GIVEN** el stack de desarrollo en marcha
- **WHEN** se ejecuta `ss -tlnp` y se prueba el puerto desde la red local
- **THEN** phpMyAdmin solo escucha en `127.0.0.1`, no es alcanzable desde la red local y no hay ninguna otra comprobación sobre el servidor de producción

#### Scenario: Rotación de la contraseña root de phpMyAdmin en desarrollo

- **GIVEN** que `root_password` estuvo versionada en git
- **WHEN** se rota el secreto root de phpMyAdmin en desarrollo y se reinicia el contenedor
- **THEN** phpMyAdmin sigue operativo con el valor nuevo y el valor antiguo queda invalidado, sin tocar producción

#### Scenario: Rotación del secreto de purga en desarrollo y producción

- **GIVEN** que el valor de `PURGE_SECRET` estuvo versionado en git
- **WHEN** el usuario rota el secreto de purga en desarrollo y en producción y reinicia los servicios que lo consumen
- **THEN** la purga legítima sigue funcionando con el valor nuevo en ambos entornos y el valor antiguo se rechaza

#### Scenario: phpMyAdmin fuera del alcance de producción

- **GIVEN** que la configuración de producción no está versionada en este repositorio
- **WHEN** se revisa el alcance del change
- **THEN** no hay tareas, escenarios ni comandos que exijan verificar o endurecer phpMyAdmin en el servidor de producción

#### Scenario: Rotación de la contraseña root de MariaDB

- **GIVEN** que `root_password` (contraseña root de MariaDB) estuvo versionada en git
- **WHEN** el usuario rota la contraseña root de MariaDB en desarrollo y en producción y reinicia los servicios que la consumen
- **THEN** MariaDB y sus clientes siguen operativos con el valor nuevo y el valor antiguo queda invalidado, sin que el change modifique configuración de producción

#### Scenario: Compromiso permanente de los valores en el historial de git

- **GIVEN** que `atareao_purge_2026` y `root_password` son recuperables del historial de git (`git rev-list --all`)
- **WHEN** se documenta la decisión de higiene de secretos
- **THEN** queda escrito que esos valores se consideran comprometidos de forma permanente y que la mitigación es rotarlos, sin reescribir el historial de git

#### Scenario: El árbol versionado no contiene secretos reales

- **WHEN** se busca el literal `atareao_purge_2026` y el valor de `root_password` en los ficheros bajo control de versiones (HEAD)
- **THEN** el literal del secreto de purga no aparece en ningún fichero versionado, y `root_password`/`MYSQL_ROOT_PASSWORD` solo figuran como nombre de secret o como texto de remediación, nunca como valor literal

#### Scenario: No se reescribe el historial de git

- **WHEN** se revisa el alcance y las tareas del change
- **THEN** no existe ninguna tarea de reescritura de historial (BFG, `git filter-repo` o `force-push`), y la decisión de rotar y aceptar queda documentada
