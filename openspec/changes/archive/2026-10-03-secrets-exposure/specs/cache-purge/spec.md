# Cache Purge Delta

## MODIFIED Requirements

### Requirement: Authentication via secret header

Purge requests SHALL include the header `X-Cache-Purge` with a shared secret value that matches the value expected by Nginx. El valor del secreto SHALL NOT estar escrito en el repositorio: ni en el código PHP ni en la configuración de Nginx ni en ningún fichero versionado. El secreto SHALL proveerse en tiempo de ejecución desde `podman secret` (o una variable de entorno derivada de él) y SHALL leerse de esa fuente tanto en PHP como en el `map` de Nginx. La comparación del secreto en PHP SHALL realizarse en tiempo constante mediante `hash_equals` (o equivalente) y SHALL NOT usar comparaciones de igualdad ordinarias. El sistema SHALL permitir rotar el secreto cambiando el valor del `podman secret` y reiniciando los servicios que lo consumen, sin editar ni recomitar ficheros.

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
