# Proposal: Higiene de secretos — rotación aceptada e idempotencia de lectura

## Why

El change `secrets-exposure` limpió el **árbol versionado** (HEAD) pero no el **historial de git**. La auditoría de secretos de la rama `fix/secrets-exposure` (2026-10-03) confirma que los literales comprometidos siguen siendo recuperables del historial:

1. **SEC-GEN-002 (MEDIUM) — El literal `atareao_purge_<valor-comprometido>` y `root_password` persisten en el historial de git.** `git rev-list --all` los recupera de los blobs históricos de `nginx/default.conf`, `README.md` y `class-cache-purge.php`. El árbol versionado (HEAD) está limpio: `atareao_purge_<valor-comprometido>` no aparece en ningún fichero versionado y `root_password`/`MYSQL_ROOT_PASSWORD` solo figuran como *nombre* de secret (`atareao_mariadb_root_password`) y como texto de remediación en `README.md`. El riesgo, por tanto, no está en el árbol sino en el "git leak": cualquiera con acceso al repositorio puede leer las credenciales históricas. **La purga forzada es de bajo impacto, pero la contraseña root de MariaDB no lo es.** La rotación de ambos secretos es obligatoria.

2. **SEC-GEN-003 (LOW) — `getSecret()` no normaliza la rama de variable de entorno.** La lectura del secreto por fichero sí aplica `trim()`, pero la rama de variable de entorno (`ATAREAO_PURGE_SECRET`) no lo hace de forma incondicional. Si el valor llega con un salto de línea final (por ejemplo, `podman secret create` desde stdin conserva el `\n` de la salida de `crypta password`), el header `X-Cache-Purge` no casará con la clave del `map` de Nginx y la purga legítima no autenticará por esa vía. El fallo es *fail-closed* (no hay fuga ni bypass), pero degrada la operación.

## Decisión del usuario (SEC-GEN-002)

**Rotar y aceptar.** El usuario decide **no reescribir el historial de git** (nada de BFG, `git filter-repo`, ni `force-push`). En consecuencia:

- Los valores antiguos (`atareao_purge_<valor-comprometido>`, `root_password`) **se consideran comprometidos de forma permanente**: quedan recuperables del historial y no se intentará purgarlos.
- La mitigación es **rotar** cada credencial y tratar los valores antiguos como inválidos, no reescribir la historia.
- El change **documenta y verifica**; la rotación la **ejecuta el usuario** como acción de despliegue.

## What Changes

- **Rotación documentada de la contraseña root de MariaDB.** Se documenta la rotación de la contraseña root de MariaDB como acción de despliegue del usuario (desarrollo y producción), de modo que el valor histórico `root_password` deja de ser válido. No se modifican quadlets ni configuración de producción.
- **Rotación documentada del secreto de purga (ya efectuada en producción).** Se deja constancia de que el usuario **ya rotó** el secreto de purga en producción; el change documenta el procedimiento y la verificación de que el valor nuevo está activo y el antiguo rechazado.
- **Compromiso permanente de los valores antiguos.** Se hace explícito en la documentación que, al no reescribir el historial, `atareao_purge_<valor-comprometido>` y `root_password` se consideran comprometidos para siempre y que cualquier copia antigua del repositorio los contiene.
- **Verificación del árbol versionado (HEAD).** El change exige verificar que ningún fichero bajo control de versiones contiene un secreto real: `atareao_purge_<valor-comprometido>` ausente en HEAD, `root_password`/`MYSQL_ROOT_PASSWORD` solo como nombre/referencia, y el literal del secreto de purga ausente de PHP, Nginx y `.justfile`.
- **Normalización del secreto en ambas ramas de lectura (SEC-GEN-003).** `getSecret()` SHALL aplicar `trim()` de forma incondicional tanto en la rama de variable de entorno (`ATAREAO_PURGE_SECRET`) como en la de fichero (`ATAREAO_PURGE_SECRET_FILE`), de modo que un salto de línea final no rompa la autenticación de la purga. La lectura SHALL seguir siendo **fail-closed**: sin secreto configurado (valor vacío tras `trim()`), la purga NO se envía y `verifySecret()` devuelve `false`, sin revelar ningún valor y sin fallback hardcodeado.
- **Sin tocar el sitio público ni producción.** El change no modifica quadlets, ni la configuración de Nginx, ni ficheros de producción. El único cambio de código en la fase de implementación es la normalización en `getSecret()`; el resto es documentación y verificación.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `cache-purge`: exige que la lectura del secreto de purga normalice el valor (`trim()`) en las dos ramas (variable de entorno y fichero) y que la ausencia de secreto se comporte de forma *fail-closed*, manteniendo la comparación en tiempo constante y la rotación sin tocar el repositorio.
- `infrastructure`: amplía la verificación y rotación de credenciales expuestas para cubrir explícitamente el historial de git: los valores antiguos (`atareao_purge_<valor-comprometido>`, `root_password`) se asumen comprometidos de forma permanente; la rotación de la contraseña root de MariaDB y del secreto de purga la ejecuta el usuario como acción de despliegue; se verifica que el árbol versionado (HEAD) no contiene ningún secreto real; y se declara que **no** se reescribe el historial de git.

## Impact

- **Archivos a modificar (solo en la fase de implementación, tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-cache-purge.php` — normalización incondicional (`trim()`) en la rama de variable de entorno de `getSecret()`, manteniendo el comportamiento *fail-closed* sin secreto (SEC-GEN-003).
  - `wp-content/plugins/atareao-functionality/README.md` — documentar la rotación (purga y root de MariaDB), el compromiso permanente de los valores antiguos y la decisión de no reescribir el historial (SEC-GEN-002).
  - Specs a fusionar al archivar: `openspec/specs/cache-purge/spec.md` y `openspec/specs/infrastructure/spec.md`, a partir de los deltas de este change.
- **Rutas y contratos que NO se tocan:** el nombre del header `X-Cache-Purge`, la comparación con `hash_equals`, las variables de entorno `ATAREAO_PURGE_SECRET`/`ATAREAO_PURGE_SECRET_FILE`, el `map` de Nginx y el flujo de purga legítimo.
- **Producción:** el change **no** modifica configuración de producción ni quadlets. La rotación de la contraseña root de MariaDB y del secreto de purga (ya efectuada en producción) es una **acción de despliegue del usuario**; el change solo la documenta y verifica.
- **Historial de git:** **no se reescribe** (decisión del usuario). No se usa BFG, `git filter-repo` ni `force-push`; los valores antiguos se asumen comprometidos de forma permanente.
- **Compatibilidad:** PHP 8.3, PSR12, WordPress 6.0+. No hay dependencias nuevas.
- **Verificación:** sin framework de tests en el repositorio; se combinan `just php-lint` (0 errores), `just phpcs` (baseline 752 errores / 429 warnings, objetivo +0), un arnés externo de stubs en `/tmp/opencode/secrets-hygiene-harness/` (no versionado) y E2E manual del usuario en desarrollo y producción.
