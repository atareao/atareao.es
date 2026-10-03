# Tasks: Validación de credenciales del pipeline de release

> Nota: el repositorio no tiene framework de tests. La "ejecución" de tests en este cambio es la verificación estática y local descrita en cada tarea (parseo YAML, `bash -n` y ejecución del snippet con un token falso), más la verificación E2E diferida al próximo release real.

## 1. Paso de validación en ambos jobs

- [x] 1.1 Redactar el bloque `run:` del paso de validación: `GH_PAT: ${{ secrets.GH_PAT }}` en `env:`, comprobación de cadena vacía, `curl --silent --output /dev/null --write-out '%{http_code}'` a `https://api.github.com/repos/${GITHUB_REPOSITORY}` con `Authorization: Bearer ${GH_PAT}`, y `case` sobre el código (200/401/404/*) con `exit 1` en todos los fallos. Verificación: extraer el snippet a `/tmp/validate.sh` y `bash -n /tmp/validate.sh` sin errores de sintaxis.
- [x] 1.2 Insertar el paso como **primer step** del job `release`, antes de `actions/checkout@v4`. Verificación: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/release-prepare.yml'))"` sin excepción, y revisar que el orden de `steps` sitúa la validación antes del checkout.
- [x] 1.3 Insertar el mismo paso como **primer step** del job `sync-development`, antes de `actions/checkout@v4`. Verificación: mismo parseo YAML y revisión de orden.
- [x] 1.4 Sustituir el fallback `secrets.GH_PAT || secrets.GITHUB_TOKEN` por `secrets.GH_PAT` en el `token:` de ambos checkouts y en los `GH_TOKEN:` de los pasos de git/`gh`. Verificación: `rg -n 'GH_PAT \|\| GITHUB_TOKEN' .github/workflows/release-prepare.yml` no devuelve resultados y `rg -n 'secrets.GH_PAT' .github/workflows/release-prepare.yml` muestra las ocurrencias esperadas.

## 2. Verificación estática y local del snippet

- [x] 2.1 Parsear el workflow completo para descartar YAML inválido. Verificación: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/release-prepare.yml'))"` con exit 0 y sin salida de error.
- [x] 2.2 Verificar la sintaxis del shell extraído. Verificación: `bash -n` sobre el `run:` extraído del paso (ambas copias) con exit 0.
- [x] 2.3 Ejecutar el snippet con `GH_PAT` vacío y comprobar el mensaje de "PAT no definido" y el `exit 1`. Verificación (con `GITHUB_REPOSITORY` definido, p. ej. `atareao/atareao.es`): `env -u GH_PAT GITHUB_REPOSITORY=atareao/atareao.es bash /tmp/validate.sh; echo "exit=$?"` → el mensaje incluye `gh secret set GH_PAT` y `exit=1`.
- [x] 2.4 Ejecutar el snippet con un token falso y comprobar el camino 401 y la ausencia de fuga. Verificación: `GH_PAT=ghp_tokenfalso GITHUB_REPOSITORY=atareao/atareao.es bash /tmp/validate.sh; echo "exit=$?"` → la salida contiene `HTTP 401` y el texto "inválido o revocado", `exit=1`, y `... | grep -c ghp_tokenfalso` devuelve `0`.
- [x] 2.5 Revisar manualmente la rama `404` y la rama `*` (no reproducibles en local sin un token real con/sin acceso). Verificación: revisión del `case` confirmando que cada código tiene mensaje propio y que ninguno interpola `${GH_PAT}`.
- [x] 2.6 Confirmar que ningún mensaje ni comando del paso imprime el token. Verificación: `rg -n 'GH_PAT' .github/workflows/release-prepare.yml` muestra solo definiciones en `env:`/`token:` y referencias en la condición de vacío, nunca un `echo` del valor.

## 3. Revisión del change y PR

- [x] 3.1 Validar los artefactos OpenSpec. Verificación: `openspec validate ci-release-token-validation --strict` sin hallazgos.
- [x] 3.2 Crear rama `feature/ci-release-token-validation`, commit convencional y PR a `development` por gitflow. Verificación: PR #51 (`feature/ci-release-token-validation` → `development`) creado y check lint en verde (run 37102625817). (No se dispara `release-prepare.yml`, que solo corre en push a `main`.)

## 4. Verificación E2E diferida al próximo release real

- [x] 4.1 En el próximo release real, confirmar que el paso de validación devuelve HTTP 200 en ambos jobs y que el pipeline continúa igual que antes del cambio (bump, changelog, tag → `release.yml`, PR de sync). Verificación: revisión del run de GitHub Actions; no se puede provocar con `gh workflow run` sin crear una release espuria. Verificación: E2E superada en el release v1.12.0 — run 37103230160, paso "Validate GH_PAT" OK con "Comprobación de GH_PAT: HTTP 200" en ambos jobs (release y sync-development); tag v1.12.0 y GitHub Release con los zips publicada (run 37103243097).
