# Proposal

## Why

El 2026-10-03, un push a `main` hizo fallar `release-prepare.yml` con un error críptico durante `actions/checkout@v4`: `fatal: could not read Username for 'https://github.com': terminal prompts disabled`. El valor guardado de `GH_PAT` no autenticaba. Como el repositorio es público, un PAT inválido no produce un 401 legible: git lo interpreta como acceso anónimo, intenta pedir credenciales y aborta. El diagnóstico fue costoso y exigió comparar runs. Además, el fallback `secrets.GH_PAT || secrets.GITHUB_TOKEN` enmascara la causa raíz: si el PAT falta o es inválido, el pipeline cae al `GITHUB_TOKEN` efímero, cuyos pushes no disparan workflows, rompiendo en silencio el encadenado del tag `vX.Y.Z` → `release.yml` y el PR de sincronización. El PAT es un requisito duro; se necesita validarlo de forma explícita, fail-fast y accionable.

## What Changes

- Añadir un paso de validación de credenciales al inicio de **cada** uno de los dos jobs (`release` y `sync-development`), **antes** de `actions/checkout@v4`.
- El paso comprueba que `secrets.GH_PAT` está definido y consulta la API de GitHub (`GET /repos/{GITHUB_REPOSITORY}`) para mapear el resultado: `200` → continúa; `401` → token inválido o revocado; `404` → token sin acceso al repositorio; ausencia de PAT → error indicando el comando `gh secret set GH_PAT`; cualquier otro código → fallo de red/API. Todos los fallos terminan con `exit 1`.
- Eliminar el fallback `secrets.GH_PAT || secrets.GITHUB_TOKEN` y usar `secrets.GH_PAT` explícito en el `token:` de `actions/checkout@v4` y en la variable `GH_TOKEN` de los pasos de git/`gh`. **BREAKING** para entornos que dependían del fallback silencioso: sin `GH_PAT` el pipeline ahora falla al inicio (comportamiento deseado).
- El log del paso solo muestra el código HTTP y la presencia/ausencia del secreto; nunca su valor.
- Sin cambios funcionales cuando el PAT es válido: el pipeline opera exactamente igual que hoy.

## Capabilities

### New Capabilities

- `release-pipeline`: validación previa de las credenciales del pipeline de release y sincronización (`GH_PAT`), con fallo rápido, mensajes accionables y sin filtrado del secreto.

### Modified Capabilities

Ninguna.

## Impact

- **Archivos**: únicamente `.github/workflows/release-prepare.yml`.
- **Dependencias**: ninguna nueva; `curl` y `python3` ya están preinstalados en `ubuntu-latest`. No se usa `gh api` para la comprobación.
- **Compatibilidad**: no afecta a `release.yml` ni al resto de workflows. El secreto `GH_PAT` debe existir con `Contents: read/write` y `Pull requests: read/write`.
- **Riesgo operativo**: si el PAT es inválido o falta, el pipeline falla antes y con un mensaje claro (objetivo del cambio). Rollback: revertir el YAML.
- **Verificación**: estática (parseo de YAML, `bash -n` del snippet, ejecución local con token falso) más el próximo release real; no se puede ejecutar el happy path con `gh workflow run` sin crear una release espuria.
