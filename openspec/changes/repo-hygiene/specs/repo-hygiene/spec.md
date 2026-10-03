# repo-hygiene Delta

## Purpose

Esta capability gobierna la **higiene y la trazabilidad del repositorio**: que no se versionen ni se distribuyan artefactos de depuración; que la documentación de desarrollo describa el stack real (Podman/quadlets/`just`) sin credenciales de ejemplo; y que el JavaScript de terceros vendorizado tenga versión, origen, licencia e integridad documentados, con una decisión razonada sobre SRI y con los minificados propios ligados a sus fuentes. No define lógica de aplicación: es limpieza, documentación y cadena de suministro front-end. El único efecto observable es que `wp-content/debug-block.php` deja de existir y de servirse.

## ADDED Requirements

### Requirement: Eliminación del script de depuración y limpieza de los artefactos

`wp-content/debug-block.php` SHALL NOT estar versionado ni existir en el árbol de trabajo, de modo que la URL `/wp-content/debug-block.php` deje de servirse. Los procesos de empaquetado (`just build` y `.github/workflows/release.yml`) SHALL incluir **únicamente** el tema `atareao-theme/` y el plugin `atareao-functionality/`, y SHALL NOT incluir ningún fichero suelto de `wp-content/` ni ningún script de depuración. La exclusión SHALL ser explícita o verificable: la comprobación del contenido del zip SHALL confirmar la ausencia de `debug-block.php` y de cualquier otro artefacto de depuración.

#### Scenario: El fichero no está en el árbol versionado

- **GIVEN** el repositorio con el change aplicado
- **WHEN** se inspecciona el control de versiones (`git ls-files` y el árbol de trabajo)
- **THEN** `wp-content/debug-block.php` no aparece por ningún lado

#### Scenario: El fichero no entra en los zips de distribución

- **GIVEN** los artefactos generados por `just build` (y el workflow de release)
- **WHEN** se lista el contenido del `atareao-theme.zip` y del `atareao-functionality.zip`
- **THEN** ninguno contiene `debug-block.php` ni ningún fichero fuera de las carpetas `atareao-theme/` y `atareao-functionality/`

#### Scenario: La URL de depuración deja de servirse

- **WHEN** se solicita `/wp-content/debug-block.php` al sitio
- **THEN** el recurso no existe y la respuesta no devuelve el script de depuración

#### Scenario: Cobertura de empaquetado acotada

- **WHEN** se revisan las recetas de empaquetado (`just build` y `release.yml`)
- **THEN** ambas zips solo el tema y el plugin, sin incluir ficheros sueltos de `wp-content/`

### Requirement: Documentación de desarrollo fiel al stack real

El `README.md` SHALL describir el entorno real del repositorio: **Podman con quadlets y el task runner `just`**, sin referirse a `docker`/`docker-compose` (que no existen en el repo) ni a puertos inexistentes. SHALL documentar los puertos reales del stack de desarrollo —nginx en `8091` y phpMyAdmin en `127.0.0.1:8095` (loopback)— y las URLs locales coherentes con ellos. SHALL documentar el layout real (`wp-content/`, `quadlets/`, `nginx/`, `.justfile`) y los comandos de operación existentes. El `README.md` SHALL NOT contener credenciales de ejemplo ni passwords por defecto copiables (`admin_password=ChangeMe123` o similares): cuando una credencial sea necesaria, SHALL indicarse su provisión vía `podman secret`/variable de entorno o mediante un marcador.

#### Scenario: Sin credenciales de ejemplo

- **WHEN** se busca en el `README.md` un password, token o credencial de ejemplo (`ChangeMe123`, `admin_password=<valor>`, etc.)
- **THEN** no aparece ninguna credencial real o reutilizable, solo marcadores o la referencia a `podman secret`/variable de entorno

#### Scenario: Comandos acordes al stack real

- **WHEN** se revisan los comandos de operación del `README.md`
- **THEN** usan `podman`, `just` y los quadlets, y no invocan `docker`/`docker-compose` (inexistentes en el repositorio)

#### Scenario: Puertos reales del stack

- **WHEN** se comparan los puertos documentados con los quadlets
- **THEN** nginx figura en `8091` y phpMyAdmin en `127.0.0.1:8095`, y no se documentan `8080`/`8081` como puertos del stack

#### Scenario: Layout y URLs locales coherentes

- **WHEN** una persona sigue el quick start del `README.md`
- **THEN** las rutas del layout (`wp-content/`), las URLs locales y los comandos de instalación de WordPress coinciden con el estado real del repositorio

### Requirement: Trazabilidad e integridad del JavaScript vendorizado

Todo JavaScript de terceros vendorizado SHALL tener un **registro de procedencia versionado** que indique, por fichero, la **versión exacta**, el **origen upstream** (repositorio/URL), la **licencia** y un **hash de integridad** (SHA-256) del fichero tal como se sirve. El registro SHALL cubrir al menos `assets/blocks/crontab-helper/qrcode.min.js` y `assets/vendor/js-yaml.min.js`. La **decisión sobre SRI** SHALL quedar documentada y razonada: al ser recursos locales del mismo origen, el SRI no protege frente a la modificación del propio fichero en el servidor, por lo que la integridad se cubre con el hash versionado y, si se decide aplicar `integrity`, el valor SHALL registrarse y mantenerse sincronizado con el fichero. Los minificados propios del tema (`js/*.min.js`) SHALL documentarse como derivados de sus fuentes `.js` versionadas (o de su proceso de minificación), de modo que la fuente legible sea la referencia y su versión quede ligada a la del tema. Los minificados SHALL NOT cambiar de contenido a causa de este change.

#### Scenario: Versión, origen y licencia documentados

- **WHEN** se revisa el registro de procedencia del JS vendorizado
- **THEN** cada fichero de terceros declara su versión exacta, su origen upstream y su licencia

#### Scenario: El hash de integridad coincide con el fichero

- **WHEN** se recalcula el hash (SHA-256) de cada JS de terceros y se compara con el registrado
- **THEN** ambos coinciden para el fichero tal como se sirve

#### Scenario: Una alteración del vendor es detectable

- **WHEN** el contenido de un JS de terceros cambia sin actualizar el registro
- **THEN** la comparación de hashes evidencia la discrepancia y obliga a revisar el cambio

#### Scenario: Decisión sobre SRI razonada

- **WHEN** se consulta la documentación de integridad
- **THEN** aparece la decisión sobre SRI (aplicar o no) con su justificación para recursos locales del mismo origen

#### Scenario: Minificados propios con fuente de referencia

- **WHEN** se revisan los `js/*.min.js` del tema
- **THEN** se documenta su correspondencia con las fuentes `.js` versionadas (o su proceso de minificación) y no se altera su contenido
