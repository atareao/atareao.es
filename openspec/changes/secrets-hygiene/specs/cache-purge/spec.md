# Cache Purge Delta

## MODIFIED Requirements

### Requirement: Authentication via secret header

Purge requests SHALL include the header `X-Cache-Purge` with a shared secret value that matches the value expected by Nginx. El valor del secreto SHALL NOT estar escrito en el repositorio: ni en el código PHP ni en la configuración de Nginx ni en ningún fichero versionado. El secreto SHALL proveerse en tiempo de ejecución desde `podman secret` (o una variable de entorno derivada de él) y SHALL leerse de esa fuente tanto en PHP como en el `map` de Nginx. La lectura del secreto SHALL normalizar el valor con `trim()` de forma **incondicional en ambas ramas**: tanto cuando se lee de la variable de entorno (`ATAREAO_PURGE_SECRET`) como cuando se lee del fichero provisionado (`ATAREAO_PURGE_SECRET_FILE`), de modo que un salto de línea u otro espacio en blanco final no impida que el header coincida con la clave del `map`. La comparación del secreto en PHP SHALL realizarse en tiempo constante mediante `hash_equals` (o equivalente) y SHALL NOT usar comparaciones de igualdad ordinarias. Si no hay secreto configurado (valor vacío tras `trim()`), el sistema SHALL comportarse de forma **fail-closed**: SHALL NOT enviar la purga y `verifySecret()` SHALL devolver `false` sin revelar ningún valor y sin recurrir a ningún secreto por defecto. El sistema SHALL permitir rotar el secreto cambiando el valor del `podman secret` y reiniciando los servicios que lo consumen, sin editar ni recomitar ficheros. Los valores del secreto que hayan quedado en el historial de git SHALL considerarse **comprometidos de forma permanente** y SHALL rotarse, sin reescribir el historial de git.

**File:** `wp-content/plugins/atareao-functionality/includes/class-cache-purge.php`

#### Scenario: Purge request includes auth header
- **WHEN** a purge request is sent
- **THEN** it includes the `X-Cache-Purge` header with the configured secret

#### Scenario: El secreto no está en el repositorio

- **WHEN** se busca el valor literal del secreto de purga en el árbol versionado
- **THEN** no aparece en `class-cache-purge.php`, en `nginx/default.conf` ni en ningún otro fichero bajo control de versiones

#### Scenario: El secreto se provee desde `podman secret`

- **WHEN** se arranca el stack de desarrollo
- **THEN** PHP y Nginx leen el mismo secreto desde la fuente provisionada por `podman secret` y la purga legítima sigue funcionando

#### Scenario: Comparación en tiempo constante

- **WHEN** llega una petición de purga con un `X-Cache-Purge` que no coincide con el secreto esperado
- **THEN** la comparación en PHP se realiza con `hash_equals` y la purga se rechaza

#### Scenario: Rotación del secreto sin tocar el repositorio

- **WHEN** se cambia el valor del `podman secret` de purga y se reinician los servicios que lo consumen
- **THEN** PHP y Nginx usan el valor nuevo y la purga legítima sigue funcionando sin editar ni recomitar ficheros

#### Scenario: Normalización del secreto leído del entorno

- **WHEN** `ATAREAO_PURGE_SECRET` contiene el secreto con un salto de línea u otro espacio en blanco final
- **THEN** `getSecret()` devuelve el valor normalizado con `trim()` y el header `X-Cache-Purge` coincide con la clave del `map`, de modo que la purga legítima autentica por esa vía

#### Scenario: Normalización del secreto leído del fichero

- **WHEN** `ATAREAO_PURGE_SECRET_FILE` apunta a un fichero cuyo contenido termina en salto de línea
- **THEN** `getSecret()` devuelve el valor normalizado con `trim()` y la purga legítima autentica igual que por la rama de entorno

#### Scenario: Fail-closed sin secreto

- **WHEN** no hay ningún secreto configurado (ni variable de entorno ni fichero legible, o ambos vacíos tras `trim()`)
- **THEN** `getSecret()` devuelve la cadena vacía y `verifySecret()` devuelve `false`, la purga NO se envía y no se usa ningún secreto por defecto hardcodeado

#### Scenario: Valor antiguo comprometido en el historial

- **WHEN** se recupera del historial de git un valor antiguo del secreto de purga
- **THEN** ese valor se considera comprometido de forma permanente, se rota el secreto y el valor antiguo deja de ser aceptado, sin reescribir el historial de git
