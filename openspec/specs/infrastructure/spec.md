# infrastructure Specification

## Purpose
Define cómo se exponen los servicios auxiliares del stack de **desarrollo** y cómo se proveen las credenciales, de modo que ningún puerto administrativo quede accesible fuera de la propia máquina y ningún secreto viva en el repositorio ni sea visible en `ps`, `/proc` o `journal`. Este repositorio solo versiona el entorno de desarrollo; **el change no toca producción**.

## Requirements

### Requirement: Exposición controlada de servicios administrativos

phpMyAdmin es un servicio **exclusivo del entorno de desarrollo**. Su puerto SHALL publicarse únicamente en loopback (`PublishPort=127.0.0.1:8095:80`), de modo que no sea alcanzable desde la red local ni desde fuera de la máquina del desarrollador, y SHALL NOT publicarse en `0.0.0.0` ni en una interfaz pública. Su credencial root SHALL proveerse por `podman secret` (fichero `*_FILE` o secreto `type=env`) y SHALL NOT escribirse en el repositorio ni pasar por la línea de comandos. Este change **no endurece ni verifica phpMyAdmin en producción**: la configuración de producción no está versionada en este repositorio y queda fuera del alcance.

**File:** `quadlets/atareao-phpmyadmin.container`, `quadlets/atareao-network.network`

#### Scenario: phpMyAdmin solo escucha en loopback

- **GIVEN** el stack de desarrollo en marcha
- **WHEN** se inspecciona el bind del puerto de phpMyAdmin con `ss -tlnp`
- **THEN** el puerto aparece escuchando solo en `127.0.0.1` y no en `0.0.0.0` ni en ninguna interfaz de red local

#### Scenario: No alcanzable desde la red local

- **GIVEN** el stack de desarrollo en marcha
- **WHEN** se hace `curl` al puerto de phpMyAdmin desde otra máquina de la red local
- **THEN** la conexión no se establece y no se obtiene la página de inicio de sesión

#### Scenario: Sin contraseña root en el repositorio

- **WHEN** se busca el literal `root_password` y la directiva `MYSQL_ROOT_PASSWORD` en el árbol versionado
- **THEN** no aparece en ningún quadlet ni fichero bajo control de versiones

#### Scenario: Credencial root de phpMyAdmin por `podman secret`

- **GIVEN** el contenedor de phpMyAdmin del entorno de desarrollo
- **WHEN** se arranca el stack
- **THEN** la credencial root se obtiene de `podman secret` (fichero `*_FILE` o secreto `type=env`) y no está incrustada en el quadlet

#### Scenario: Credencial root fuera de `argv`

- **GIVEN** el contenedor de phpMyAdmin en marcha
- **WHEN** se inspecciona su línea de comandos con `ps` o `/proc`
- **THEN** la contraseña root no aparece en `argv`

### Requirement: Provisión de secretos mediante `podman secret`

Todo secreto del stack de desarrollo (entre ellos el de purga de caché, la contraseña de la base de datos y la contraseña root de phpMyAdmin) SHALL gestionarse mediante `podman secret` con `crypta`, y SHALL NOT escribirse en el repositorio. Los contenedores SHALL recibir los secretos por fichero (`*_FILE`) o como secreto `type=env`, y SHALL NOT incrustarlos en variables de entorno literales dentro de los ficheros versionados. La provisión SHALL permitir rotar cada secreto cambiando su valor en `podman secret` y reiniciando los servicios que lo consumen, sin editar ficheros del repositorio.

**File:** `.justfile`, `quadlets/*.container`

#### Scenario: Secretos no presentes en el repositorio

- **WHEN** se busca cualquier valor de secreto del stack (purga, contraseña de base de datos, contraseña root de phpMyAdmin) en el árbol versionado
- **THEN** no aparece ningún valor literal; solo referencias a `podman secret` o a variables/ficheros derivados

#### Scenario: Rotación de un secreto

- **GIVEN** un secreto gestionado con `podman secret`
- **WHEN** se rota su valor y se reinician los servicios que lo consumen
- **THEN** los servicios usan el valor nuevo y siguen funcionando sin editar ni recomitar ficheros del repositorio

#### Scenario: Receta de `just` sin secreto literal

- **WHEN** se inspeccionan las recetas de `just` que necesitan una credencial
- **THEN** la credencial se obtiene de `podman secret`/`crypta` y no aparece escrita en la receta

### Requirement: Credenciales no visibles en `ps` ni `journal`

El sistema SHALL NOT pasar credenciales en la línea de comandos de ningún proceso. Las comprobaciones de salud, las recetas de `just` y cualquier comando que necesite una credencial SHALL recibirla por fichero, por `--defaults-extra-file` o por secreto `type=env`, de modo que el valor no aparezca en `ps`, en `/proc/<pid>/cmdline` ni en `journal`.

**File:** `quadlets/atareao-mariadb.container`, `.justfile`

#### Scenario: Healthcheck sin contraseña en `argv`

- **GIVEN** el contenedor de MariaDB en marcha
- **WHEN** se inspecciona la línea de comandos del healthcheck con `ps` o `/proc`
- **THEN** la contraseña no aparece en `argv`; el healthcheck usa un fichero de defaults o un secreto `type=env`

#### Scenario: Receta `wp` sin contraseña en `argv`

- **GIVEN** una ejecución de la receta `just wp …`
- **WHEN** se inspecciona la línea de comandos del proceso de `podman run`
- **THEN** la contraseña de la base de datos no aparece en `argv`

#### Scenario: Sin credenciales en el journal

- **WHEN** se consulta el `journal` de los servicios tras un arranque
- **THEN** no aparecen contraseñas de base de datos ni secretos de purga en claro

### Requirement: Verificación y rotación de credenciales expuestas

El change SHALL documentar la verificación y el plan de rotación de las credenciales que hayan podido estar expuestas: la **contraseña root de phpMyAdmin del entorno de desarrollo**, la **contraseña root de MariaDB** y el **secreto de purga** (`PURGE_SECRET`), que sí se despliega en producción. La verificación del bind de phpMyAdmin SHALL realizarse en el entorno de desarrollo con `ss -tlnp` y un `curl` desde la red local. La rotación de la contraseña root de phpMyAdmin SHALL ejecutarse en desarrollo. La rotación de la contraseña root de MariaDB y la rotación del secreto de purga SHALL ejecutarlas el usuario como **acción de despliegue**, en desarrollo y en producción, sin que el change modifique la configuración de producción. El change SHALL NOT incluir tareas ni escenarios de endurecimiento o verificación de phpMyAdmin en producción.

El change SHALL dejar constancia explícita de que los valores que hayan quedado en el **historial de git** (`atareao_purge_<valor-comprometido>`, `root_password`) se consideran **comprometidos de forma permanente**, y que la mitigación es **rotar y aceptar**: SHALL NOT reescribir el historial de git (nada de BFG, `git filter-repo` ni `force-push`). El change SHALL verificar que el **árbol versionado (HEAD)** no contiene ningún secreto real: el literal `atareao_purge_<valor-comprometido>` SHALL estar ausente de todos los ficheros bajo control de versiones, y `root_password`/`MYSQL_ROOT_PASSWORD` SHALL aparecer únicamente como nombre de secret o como texto de remediación, nunca como valor literal. La rotación de un secreto SHALL permitir invalidar el valor antiguo y activar el nuevo reiniciando los servicios que lo consumen, sin editar ficheros del repositorio.

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

- **GIVEN** que `atareao_purge_<valor-comprometido>` y `root_password` son recuperables del historial de git (`git rev-list --all`)
- **WHEN** se documenta la decisión de higiene de secretos
- **THEN** queda escrito que esos valores se consideran comprometidos de forma permanente y que la mitigación es rotarlos, sin reescribir el historial de git

#### Scenario: El árbol versionado no contiene secretos reales

- **WHEN** se busca el literal `atareao_purge_<valor-comprometido>` y el valor de `root_password` en los ficheros bajo control de versiones (HEAD)
- **THEN** el literal del secreto de purga no aparece en ningún fichero versionado, y `root_password`/`MYSQL_ROOT_PASSWORD` solo figuran como nombre de secret o como texto de remediación, nunca como valor literal

#### Scenario: No se reescribe el historial de git

- **WHEN** se revisa el alcance y las tareas del change
- **THEN** no existe ninguna tarea de reescritura de historial (BFG, `git filter-repo` o `force-push`), y la decisión de rotar y aceptar queda documentada
